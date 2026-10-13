#!/usr/bin/env python3
"""Genera il sito statico miglioramento.com nella cartella dist/.

    python3 build.py              # genera dist/
    python3 build.py --verifica   # controlla solo versioni e sorgenti, senza scrivere
    python3 build.py --rigoroso   # come build, ma fallisce se restano segnaposto "DA COMPLETARE"

Nessuna dipendenza: solo la libreria standard di Python 3.9+.
"""
import argparse
import html
import json
import re
import shutil
import sys
from datetime import date
from pathlib import Path
import xml.etree.ElementTree as ET
from xml.etree import ElementInclude

RADICE = Path(__file__).resolve().parent
DIST = RADICE / "dist"
SEMVER = re.compile(r"^\d+\.\d+\.\d+$")
DB = "{http://docbook.org/ns/docbook}"
XML_ID = "{http://www.w3.org/XML/1998/namespace}id"
SEGNAPOSTO = "DA COMPLETARE"


class ErroreSorgente(Exception):
    pass


def leggi_json(p):
    return json.loads(p.read_text(encoding="utf-8"))


def versione_changelog(p):
    """Prima versione rilasciata elencata in un CHANGELOG (formato Keep a Changelog)."""
    m = re.search(r"^## \[(\d+\.\d+\.\d+)\]", p.read_text(encoding="utf-8"), re.M)
    return m.group(1) if m else None


def esc(s):
    return html.escape(str(s), quote=True)


# ---------------------------------------------------------------------------
# Caricamento e verifica dei sorgenti
# ---------------------------------------------------------------------------

def carica():
    sito = leggi_json(RADICE / "sito.json")
    errori = []

    def controlla_versione(nome, versione, changelog):
        if not SEMVER.match(versione or ""):
            errori.append(f"{nome}: versione '{versione}' non nel formato X.Y.Z")
        if not changelog.exists():
            errori.append(f"{nome}: manca {changelog.relative_to(RADICE)}")
        elif versione_changelog(changelog) != versione:
            errori.append(
                f"{nome}: versione {versione} diversa dall'ultima voce di "
                f"{changelog.relative_to(RADICE)} ({versione_changelog(changelog)})"
            )

    controlla_versione("sito", sito["versione"], RADICE / "CHANGELOG.md")

    sezioni = {}
    for cartella in sorted((RADICE / "sezioni").iterdir()):
        meta_p = cartella / "sezione.json"
        if not meta_p.exists():
            continue
        meta = leggi_json(meta_p)
        meta["slug"] = cartella.name
        meta["cartella"] = cartella
        controlla_versione(f"sezione '{cartella.name}'", meta.get("versione"), cartella / "CHANGELOG.md")
        for pagina in meta["pagine"]:
            if not (cartella / pagina["file"]).exists():
                errori.append(f"sezione '{cartella.name}': manca il file {pagina['file']}")
        sezioni[cartella.name] = meta

    for slug in sito["menu"]:
        if slug not in sezioni:
            errori.append(f"menu: la sezione '{slug}' non esiste")

    if errori:
        raise ErroreSorgente("\n".join("  - " + e for e in errori))
    return sito, sezioni


# ---------------------------------------------------------------------------
# Impaginazione
# ---------------------------------------------------------------------------

def url_pagina(sito, slug, file):
    base = sito["base"]
    cartella = "" if slug == "home" else slug + "/"
    nome = "" if file == "index.html" else file.removesuffix(".html") + "/"
    return base + cartella + nome


def percorso_dist(url, sito):
    rel = url[len(sito["base"]):]
    return DIST / rel / "index.html"


def sostituisci(testo, valori):
    return re.sub(r"\{\{(\w+)\}\}", lambda m: str(valori.get(m.group(1), m.group(0))), testo)


def collana_html(sito):
    """Schede dei libri della collana già su Amazon, da sito.json → collana."""
    schede = []
    for i, libro in enumerate(sito.get("collana", []), 1):
        bottone = (
            f'<a class="btn" href="{esc(libro["amazon_url"])}" rel="noopener" '
            f'aria-label="Vedi {esc(libro["titolo"])} su Amazon">Vedi su Amazon</a>'
            if libro.get("amazon_url") else ""
        )
        sotto = f'<p class="sottotitolo">{esc(libro["sottotitolo"])}</p>' if libro.get("sottotitolo") else ""
        schede.append(
            f'    <section class="libro-collana" aria-labelledby="libro-{i}">'
            f'<p class="etichetta-sezione">Su Amazon</p><h2 id="libro-{i}">{esc(libro["titolo"])}</h2>{sotto}'
            f'<p>{esc(libro["descrizione"])}</p><div class="azioni">{bottone}</div></section>'
        )
    return "\n".join(schede)


def banner_libro(sito, compatto=False):
    lib = sito["libro"]
    link = (
        f'<a href="{esc(lib["amazon_url"])}" rel="noopener">disponibile su Amazon</a>'
        if lib["amazon_url"] else "in uscita su Amazon"
    )
    if compatto:
        return (
            '<div class="fascia-libro" role="note" aria-label="Il libro">'
            f'<p><span class="etichetta">Il libro</span> <strong>{esc(lib["titolo"])}</strong> è {link}. '
            f'Intanto puoi <a href="{sito["base"]}libro/leggi/">leggere gratis l\'edizione {esc(lib["edizione"])}</a>.</p>'
            "</div>"
        )
    return (
        '<div class="avviso-edizione" role="note">'
        f'<p class="etichetta">Edizione {esc(lib["edizione"])} · anteprima gratuita</p>'
        f"<p>Stai leggendo l'<strong>edizione {esc(lib['edizione'])}</strong> di <em>{esc(lib['titolo'])}</em>, "
        f"una versione di lavoro del manoscritto. L'<strong>edizione {esc(lib['prossima'])}</strong>, quella "
        f"definitiva, rivista e completa, è {link}.</p></div>"
    )


def menu_html(sito, sezioni, attiva):
    voci = []
    for slug in sito["menu"]:
        s = sezioni[slug]
        corrente = ' aria-current="page"' if slug == attiva else ""
        voci.append(f'<a href="{url_pagina(sito, slug, "index.html")}"{corrente}>{esc(s["menu"])}</a>')
    return "\n        ".join(voci)


def impagina(sito, sezioni, modello, *, slug, titolo, descrizione, contenuto, url, extra_classi=""):
    sez = sezioni.get(slug)
    titolo_completo = titolo if slug == "home" else f"{titolo} — {sito['nome']}"
    valori = {
        "lingua": sito["lingua"],
        "titolo": esc(titolo_completo),
        "descrizione": esc(descrizione),
        "canonical": esc(sito["url"].rstrip("/") + url),
        "base": sito["base"],
        "nome": esc(sito["nome"]),
        "menu": menu_html(sito, sezioni, slug),
        "claim": esc(sito["claim"]),
        "immagine_condivisione": esc(sito["url"].rstrip("/") + sito["base"] + "statici/logo-francobollo.png"),
        "contenuto": contenuto,
        "classi": extra_classi,
        "versioni": (
            f'Sezione «{esc(sez["titolo"])}» v{esc(sez["versione"])} · aggiornata il {esc(sez["aggiornata"])} · '
            if sez else ""
        ) + f'Sito v{esc(sito["versione"])}',
        "anno": date.today().year,
        "autore": esc(sito["autore"]),
    }
    return sostituisci(modello, valori)


def accessibile(testo):
    """Ritocchi di accessibilità validi per tutte le pagine (WCAG 2.2 AA)."""
    # Le cornici scorrevoli devono essere raggiungibili e scorribili da tastiera
    # (con nomi diversi fra loro, se in una pagina ce n'è più d'una)
    for classe, nome in (("tabella", "Tabella"), ("foglio", "Scheda da compilare")):
        tag = f'<div class="{classe}">'
        totale, n = testo.count(tag), iter(range(1, 10_000))
        etichetta = (lambda: nome) if totale == 1 else (lambda: f"{nome} {next(n)} di {totale}")
        while tag in testo:
            testo = testo.replace(
                tag, f'<div class="{classe}" role="region" aria-label="{etichetta()}" tabindex="0">', 1)
    # Una cella d'angolo vuota non è un'intestazione
    return testo.replace("<th></th>", "<td></td>")


def scrivi(url, sito, testo, scritte):
    p = percorso_dist(url, sito)
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(accessibile(testo), encoding="utf-8")
    scritte.append(url)


# ---------------------------------------------------------------------------
# Libro: DocBook -> pagine HTML singole
# ---------------------------------------------------------------------------

COMPONENTI = ("preface", "dedication", "chapter", "appendix")


class DocBookHTML:
    """Converte il sottoinsieme di DocBook 5 usato dal libro. Elementi sconosciuti = errore."""

    def __init__(self, base_libro):
        self.base = base_libro

    def inline(self, el):
        out = esc(el.text or "")
        for c in el:
            tag = c.tag.replace(DB, "")
            dentro = self.inline(c)
            if tag == "emphasis":
                t = "strong" if c.get("role") in ("strong", "bold") else "em"
                out += f"<{t}>{dentro}</{t}>"
            elif tag == "quote":
                out += f"“{dentro}”"
            elif tag == "citetitle":
                out += f"<cite>{dentro}</cite>"
            elif tag == "phrase":
                out += dentro
            elif tag == "link":
                out += f'<a href="{self.base}{esc(c.get("linkend"))}/">{dentro}</a>'
            else:
                raise ErroreSorgente(f"DocBook: elemento in linea non gestito <{tag}>")
            out += esc(c.tail or "")
        return out

    def blocchi(self, el, livello):
        out = []
        for c in el:
            tag = c.tag.replace(DB, "")
            if tag in ("title", "info"):
                continue
            if tag == "para":
                classe = f' class="{esc(c.get("role"))}"' if c.get("role") else ""
                out.append(f"<p{classe}>{self.inline(c)}</p>")
            elif tag in ("itemizedlist", "orderedlist"):
                t = "ul" if tag == "itemizedlist" else "ol"
                start = f' start="{esc(c.get("startingnumber"))}"' if c.get("startingnumber") else ""
                voci = "".join(f"<li>{self.voce(li, livello)}</li>" for li in c)
                out.append(f"<{t}{start}>{voci}</{t}>")
            elif tag == "section":
                ident = c.get(XML_ID)
                classe = f' class="{esc(c.get("role"))}"' if c.get("role") else ""
                h = min(livello + 1, 6)
                out.append(
                    f'<section id="{esc(ident)}"{classe}><h{h}>{self.inline(c.find(DB + "title"))}</h{h}>'
                    + self.blocchi(c, livello + 1) + "</section>"
                )
            elif tag == "sidebar":
                titolo = c.find(DB + "title")
                testa = f'<p class="titolo-box">{self.inline(titolo)}</p>' if titolo is not None else ""
                out.append(f'<div class="box {esc(c.get("role") or "")}" role="note">{testa}{self.blocchi(c, livello)}</div>')
            elif tag == "epigraph":
                attr = c.find(DB + "attribution")
                firma = f'<p class="attribuzione">— {self.inline(attr)}</p>' if attr is not None else ""
                corpo = "".join(f"<p>{self.inline(p)}</p>" for p in c if p.tag == DB + "para")
                out.append(f'<blockquote class="epigrafe">{corpo}{firma}</blockquote>')
            elif tag == "blockquote":
                out.append(f"<blockquote>{self.blocchi(c, livello)}</blockquote>")
            elif tag == "informaltable":
                out.append(f'<div class="tabella"><table>{self.tabella(c)}</table></div>')
            else:
                raise ErroreSorgente(f"DocBook: elemento di blocco non gestito <{tag}>")
        return "".join(out)

    def voce(self, li, livello):
        paras = list(li)
        if len(paras) == 1 and paras[0].tag == DB + "para":
            return self.inline(paras[0])
        return self.blocchi(li, livello)

    def tabella(self, t):
        out = ""
        for parte in t:
            nome = parte.tag.replace(DB, "")
            righe = "".join(
                "<tr>" + "".join(
                    f"<{t}>{self.inline(c)}</{t}>"
                    for c in tr
                    for t in [("td" if not "".join(c.itertext()).strip() else c.tag.replace(DB, ""))]
                ) + "</tr>"
                for tr in parte
            )
            out += f"<{nome}>{righe}</{nome}>"
        return out


def carica_libro(cartella, file="libro.xml"):
    radice = ET.parse(cartella / file).getroot()
    ElementInclude.include(radice, loader=lambda href, parse, encoding=None: (
        ET.parse(cartella / href).getroot() if parse == "xml" else (cartella / href).read_text(encoding="utf-8")
    ))
    info = radice.find(DB + "info")
    componenti = []  # (parte, elemento)
    for c in radice:
        tag = c.tag.replace(DB, "")
        if tag in COMPONENTI:
            componenti.append((None, c))
        elif tag == "part":
            titolo_parte = "".join(c.find(DB + "title").itertext())
            for cc in c:
                if cc.tag.replace(DB, "") in COMPONENTI:
                    componenti.append((titolo_parte, cc))
    return info, componenti


def etichetta(el):
    tag = el.tag.replace(DB, "")
    if tag == "chapter" and el.get("label"):
        return f"Capitolo {el.get('label')}"
    if tag == "appendix":
        return "Appendice"
    return ""


def titolo_di(el):
    return "".join(el.find(DB + "title").itertext())


def pubblica_docbook(sito, sezioni, modello, scritte, *, slug, componenti, base, titolo_opera,
                     intestazione, avviso, nota_indice, descrizione_indice, descrizione_capitolo,
                     fine_url, fine_testo, badge_livello=False):
    """Pubblica un'opera DocBook come indice + una pagina per componente (prefazione, capitolo, appendice)."""
    conv = DocBookHTML(base)

    # Indice, raggruppato per parti se ci sono
    righe, parte_corrente = [], object()
    for parte, el in componenti:
        if parte != parte_corrente:
            if righe:
                righe.append("</ol>")
            if parte:
                righe.append(f'<h2 class="parte">{esc(parte)}</h2>')
            righe.append('<ol class="indice-libro">')
            parte_corrente = parte
        badge = ""
        liv = el.find(DB + "para[@role='livello']") if badge_livello else None
        if liv is not None:
            badge = f'<span class="livello">{esc(liv.text.split(":")[1].split("—")[0].strip())}</span>'
        righe.append(
            f'<li><a href="{base}{esc(el.get(XML_ID))}/"><small>{esc(etichetta(el))}</small>'
            f"<span>{esc(titolo_di(el))}</span>{badge}</a></li>"
        )
    righe.append("</ol>")
    indice = (
        f'<article class="lettura">{intestazione}{avviso}'
        + (f'<p class="nota-indice">{nota_indice}</p>' if nota_indice else "")
        + "".join(righe) + "</article>"
    )
    scrivi(base, sito, impagina(
        sito, sezioni, modello, slug=slug, titolo=f"{titolo_opera} — indice",
        descrizione=descrizione_indice, contenuto=indice, url=base, extra_classi="pagina-libro"), scritte)

    # Una pagina per componente
    for i, (parte, el) in enumerate(componenti):
        tit, eti = titolo_di(el), etichetta(el)
        nav = '<nav class="pager" aria-label="Capitoli">'
        if i > 0:
            p = componenti[i - 1][1]
            nav += f'<a class="prev" href="{base}{esc(p.get(XML_ID))}/"><small>← Precedente</small>{esc(titolo_di(p))}</a>'
        else:
            nav += f'<a class="prev" href="{base}"><small>← Indice</small>Torna all\'indice</a>'
        if i < len(componenti) - 1:
            n = componenti[i + 1][1]
            nav += f'<a class="next" href="{base}{esc(n.get(XML_ID))}/"><small>Successivo →</small>{esc(titolo_di(n))}</a>'
        else:
            nav += f'<a class="next" href="{fine_url}"><small>Fine →</small>{esc(fine_testo)}</a>'
        nav += "</nav>"
        corpo = (
            f'<article class="lettura"><p class="briciole"><a href="{base}">{esc(titolo_opera)}</a>'
            + (f" · {esc(parte)}" if parte else "") + "</p>"
            + (f'<p class="occhiello">{esc(eti)}</p>' if eti else "")
            + f"<h1>{esc(tit)}</h1>{avviso}" + conv.blocchi(el, 1) + nav + "</article>"
        )
        url = f"{base}{el.get(XML_ID)}/"
        scrivi(url, sito, impagina(
            sito, sezioni, modello, slug=slug, titolo=f"{(eti + ': ') if eti else ''}{tit} — {titolo_opera}",
            descrizione=descrizione_capitolo.format(titolo=tit), contenuto=corpo, url=url,
            extra_classi="pagina-libro"), scritte)
    return len(componenti)


def genera_libro(sito, sezioni, modello, scritte):
    info, componenti = carica_libro(sezioni["libro"]["cartella"] / "docbook")
    titolo = titolo_di(info)
    edizione = info.findtext(DB + "edition")
    if edizione != sito["libro"]["edizione"]:
        raise ErroreSorgente(
            f"libro: edizione {edizione} nel DocBook diversa da sito.json ({sito['libro']['edizione']})"
        )
    return pubblica_docbook(
        sito, sezioni, modello, scritte, slug="libro", componenti=componenti,
        base=url_pagina(sito, "libro", "index.html") + "leggi/", titolo_opera=titolo,
        intestazione=(f'<p class="occhiello">Leggi online</p><h1>{esc(titolo)}</h1>'
                      f'<p class="lead">{esc(info.findtext(DB + "subtitle"))} · di {esc(sito["autore"])}</p>'),
        avviso=banner_libro(sito),
        nota_indice=("Ogni capitolo ha un'etichetta: <strong>F</strong> fondamenta (si legge una volta), "
                     "<strong>G</strong> giornaliero, <strong>S</strong> settimanale, <strong>M</strong> mensile. "
                     "Se hai fretta, leggi prima tutti gli F."),
        descrizione_indice=f"Leggi gratis online l'edizione {edizione} di {titolo}, capitolo per capitolo.",
        descrizione_capitolo=f"{titolo}, edizione {edizione}: {{titolo}}.",
        fine_url=url_pagina(sito, "libro", "index.html"), fine_testo="Il libro", badge_livello=True,
    )


def carica_guide(sezione):
    """Le guide sono le sottocartelle di sezioni/guide con docbook/guida.xml."""
    guide = []
    for cartella in sorted(p for p in sezione["cartella"].iterdir() if (p / "docbook" / "guida.xml").exists()):
        info, componenti = carica_libro(cartella / "docbook", "guida.xml")
        extra = leggi_json(cartella / "guida.json") if (cartella / "guida.json").exists() else {}
        guide.append({"slug": cartella.name, "info": info, "componenti": componenti, **extra})
    return guide


def scheda_guida(sito, g):
    info = g["info"]
    licenza = info.findtext(f"{DB}legalnotice/{DB}para") or ""
    dati = " · ".join(x for x in (
        f"Versione {info.findtext(DB + 'edition')}" if info.findtext(DB + "edition") else "",
        info.findtext(DB + "pubdate") or "",
        f"{len(g['componenti'])} parti",
    ) if x)
    return (
        f'<a class="scheda-link" href="{url_pagina(sito, "guide", "index.html")}{g["slug"]}/">'
        f'<span class="etichetta-sezione">Guida</span><h2>{esc(titolo_di(info))}</h2>'
        f'<p>{esc(info.findtext(DB + "subtitle") or "")}</p>'
        f'<span class="metadati">{esc(dati)}' + (f"<br>Licenza: {esc(licenza)}" if licenza else "") + "</span></a>"
    )


def genera_guide(sito, sezioni, modello, scritte):
    if "guide" not in sezioni:
        return 0
    totale = 0
    for g in carica_guide(sezioni["guide"]):
        info = g["info"]
        titolo = titolo_di(info)
        autore = " ".join(info.find(f"{DB}author/{DB}personname").itertext()).split()
        licenza = info.findtext(f"{DB}legalnotice/{DB}para") or ""
        edizione = info.findtext(DB + "edition") or ""
        avviso = (
            '<div class="avviso-guida" role="note">'
            + (f'<p class="etichetta">Versione {esc(edizione)}'
               + (f" · {esc(info.findtext(DB + 'pubdate'))}" if info.findtext(DB + "pubdate") else "") + "</p>")
            + (f"<p>{esc(g['avviso'])}</p>" if g.get("avviso") else "")
            + (f'<p class="licenza">Licenza: {esc(licenza)}.</p>' if licenza else "")
            + "</div>"
        )
        totale += pubblica_docbook(
            sito, sezioni, modello, scritte, slug="guide", componenti=g["componenti"],
            base=f'{url_pagina(sito, "guide", "index.html")}{g["slug"]}/', titolo_opera=titolo,
            intestazione=(f'<p class="occhiello">Guide · {esc(" ".join(autore))}</p><h1>{esc(titolo)}</h1>'
                          f'<p class="lead">{esc(info.findtext(DB + "subtitle") or "")}</p>'),
            avviso=avviso, nota_indice="",
            descrizione_indice=f"{titolo}: {info.findtext(DB + 'subtitle') or ''}. Guida online, una pagina per capitolo.",
            descrizione_capitolo=f"{titolo}: {{titolo}}.",
            fine_url=url_pagina(sito, "guide", "index.html"), fine_testo="Tutte le guide",
        )
    return totale


# ---------------------------------------------------------------------------
# Pagine generate: versioni, 404, sitemap
# ---------------------------------------------------------------------------

def genera_versioni(sito, sezioni, modello, scritte):
    righe = "".join(
        f'<tr><td><a href="{url_pagina(sito, s["slug"], "index.html")}">{esc(s["titolo"])}</a></td>'
        f'<td>{esc(s["versione"])}</td><td>{esc(s["aggiornata"])}</td></tr>'
        for s in sorted(sezioni.values(), key=lambda s: s["titolo"])
    )
    corpo = (
        '<article class="testo"><h1>Versioni</h1>'
        f'<p>Il sito è alla versione <strong>{esc(sito["versione"])}</strong>. Ogni sezione ha anche una '
        "sua versione, che cambia solo quando cambia quella sezione: così puoi sapere se una pagina "
        "che hai già letto è stata aggiornata.</p>"
        '<div class="tabella"><table><thead><tr><th>Sezione</th><th>Versione</th><th>Aggiornata</th></tr></thead>'
        f"<tbody>{righe}</tbody></table></div>"
        f'<p>Libro <em>{esc(sito["libro"]["titolo"])}</em>: online l\'edizione <strong>{esc(sito["libro"]["edizione"])}</strong>; '
        f'edizione {esc(sito["libro"]["prossima"])} in uscita su Amazon.</p>'
        "<p>Le versioni seguono il <em>versionamento semantico</em>: il primo numero cambia per le "
        "riorganizzazioni, il secondo per i contenuti nuovi, il terzo per le correzioni.</p></article>"
    )
    url = sito["base"] + "versioni/"
    scrivi(url, sito, impagina(sito, sezioni, modello, slug="", titolo="Versioni",
                               descrizione="Versione del sito e di ogni sezione.", contenuto=corpo, url=url), scritte)
    dati = {
        "sito": sito["versione"],
        "generato": date.today().isoformat(),
        "libro": {"edizione": sito["libro"]["edizione"], "prossima": sito["libro"]["prossima"]},
        "sezioni": {s["slug"]: {"versione": s["versione"], "aggiornata": s["aggiornata"]} for s in sezioni.values()},
    }
    (DIST / "versioni.json").write_text(json.dumps(dati, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def genera_extra(sito, sezioni, modello, scritte):
    corpo = (
        '<article class="testo"><h1>Pagina non trovata</h1><p>La pagina che cerchi non esiste o è stata spostata.</p>'
        f'<p><a class="btn" href="{sito["base"]}">Torna alla home</a></p></article>'
    )
    (DIST / "404.html").write_text(accessibile(impagina(
        sito, sezioni, modello, slug="", titolo="Pagina non trovata", descrizione="Pagina non trovata.",
        contenuto=corpo, url=sito["base"] + "404.html")), encoding="utf-8")
    radice = sito["url"].rstrip("/")
    voci = "".join(f"<url><loc>{esc(radice + u)}</loc></url>" for u in scritte)
    (DIST / "sitemap.xml").write_text(
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        f'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{voci}</urlset>\n', encoding="utf-8")
    (DIST / "robots.txt").write_text(f"User-agent: *\nAllow: /\nSitemap: {radice}/sitemap.xml\n", encoding="utf-8")


def rendi_relativi(sito):
    """Riscrive href/src che partono dalla radice in percorsi relativi a ogni file.

    Le cartelle diventano .../index.html, così le pagine funzionano anche aperte
    dal disco o da un'anteprima che non serve gli indici di cartella.
    """
    base = sito["base"]
    for f in DIST.rglob("*.html"):
        risali = "../" * (len(f.relative_to(DIST).parts) - 1)

        def relativo(m):
            attr, url = m.group(1), m.group(2)
            percorso, _, ancora = url.partition("#")
            percorso = percorso[len(base):]
            if percorso == "" or percorso.endswith("/"):
                percorso += "index.html"
            return f'{attr}="{risali}{percorso}{"#" + ancora if ancora else ""}"'

        t = f.read_text(encoding="utf-8")
        t = re.sub(r'\b(href|src)="(' + re.escape(base) + r'[^"]*)"', relativo, t)
        f.write_text(t, encoding="utf-8")


# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--verifica", action="store_true", help="controlla i sorgenti senza generare dist/")
    ap.add_argument("--rigoroso", action="store_true", help=f"fallisce se resta il segnaposto '{SEGNAPOSTO}'")
    ap.add_argument("--relativi", action="store_true",
                    help="link relativi (anteprima senza server: doppio clic sui file); non usare per il deploy")
    a = ap.parse_args()

    try:
        sito, sezioni = carica()
        if a.verifica:
            carica_libro(sezioni["libro"]["cartella"] / "docbook")
            if "guide" in sezioni:
                carica_guide(sezioni["guide"])
            print(f"OK: sito {sito['versione']}, {len(sezioni)} sezioni con versioni coerenti.")
            return 0

        if DIST.exists():
            shutil.rmtree(DIST)
        DIST.mkdir()
        shutil.copytree(RADICE / "statici", DIST / "statici")
        modello = (RADICE / "modelli" / "pagina.html").read_text(encoding="utf-8")
        valori = {
            "base": sito["base"],
            "libro_titolo": esc(sito["libro"]["titolo"]),
            "libro_edizione": esc(sito["libro"]["edizione"]),
            "libro_prossima": esc(sito["libro"]["prossima"]),
            "amazon_bottone": (
                f'<a class="btn secondario" href="{esc(sito["libro"]["amazon_url"])}" rel="noopener">'
                f'Edizione {esc(sito["libro"]["prossima"])} su Amazon</a>'
                if sito["libro"]["amazon_url"]
                else f'<span class="btn secondario disattivo">Edizione {esc(sito["libro"]["prossima"])}: presto su Amazon</span>'
            ),
            "email": esc(sito["email"]),
            "autore": esc(sito["autore"]),
            "sito_versione": esc(sito["versione"]),
            "claim": esc(sito["claim"]),
            "fascia_libro": banner_libro(sito, compatto=True),
            "collana": collana_html(sito),
            "guide_elenco": "".join(scheda_guida(sito, g) for g in carica_guide(sezioni["guide"]))
            if "guide" in sezioni else "",
        }

        scritte = []
        for slug, s in sezioni.items():
            for pagina in s["pagine"]:
                url = url_pagina(sito, slug, pagina["file"])
                contenuto = sostituisci((s["cartella"] / pagina["file"]).read_text(encoding="utf-8"), valori)
                scrivi(url, sito, impagina(
                    sito, sezioni, modello, slug=slug, titolo=pagina["titolo"],
                    descrizione=pagina["descrizione"], contenuto=contenuto, url=url), scritte)
        n_libro = genera_libro(sito, sezioni, modello, scritte)
        n_guide = genera_guide(sito, sezioni, modello, scritte)
        genera_versioni(sito, sezioni, modello, scritte)
        genera_extra(sito, sezioni, modello, scritte)

        if a.relativi:
            rendi_relativi(sito)

        # Segnaposto rimasti e {{chiavi}} non sostituite
        mancanti = []
        for f in DIST.rglob("*.html"):
            t = f.read_text(encoding="utf-8")
            if SEGNAPOSTO in t:
                mancanti.append(str(f.relative_to(DIST)))
            if re.search(r"\{\{\w+\}\}", t):
                raise ErroreSorgente(f"{f.relative_to(DIST)}: segnaposto {{{{...}}}} non sostituito")
        print(f"Generato dist/: sito {sito['versione']}, {len(scritte)} pagine ({n_libro} del libro, {n_guide} delle guide).")
        if not sito["libro"]["amazon_url"]:
            mancanti.append("sito.json: libro.amazon_url vuoto")
        for libro in sito.get("collana", []):
            if not libro.get("amazon_url"):
                mancanti.append(f"sito.json: collana, link Amazon mancante per «{libro['titolo']}»")
        if mancanti:
            print(f"Attenzione, {len(mancanti)} elementi da completare prima della pubblicazione:")
            for m in sorted(mancanti):
                print(f"  - {m}")
            if a.rigoroso:
                return 1
        return 0
    except ErroreSorgente as e:
        print(f"Errore nei sorgenti:\n{e}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
