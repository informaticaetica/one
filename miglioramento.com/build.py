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


def banner_libro(sito, compatto=False):
    lib = sito["libro"]
    link = (
        f'<a href="{esc(lib["amazon_url"])}" rel="noopener">disponibile su Amazon</a>'
        if lib["amazon_url"] else "in uscita su Amazon"
    )
    if compatto:
        return (
            f'<div class="banner"><div class="wrap">Il libro <strong>{esc(lib["titolo"])}</strong> è {link}. '
            f'Intanto puoi <a href="{sito["base"]}libro/leggi/">leggere gratis l\'edizione {esc(lib["edizione"])}</a>.'
            "</div></div>"
        )
    return (
        '<aside class="avviso-edizione" role="note">'
        f'<p class="etichetta">Edizione {esc(lib["edizione"])} · anteprima gratuita</p>'
        f"<p>Stai leggendo l'<strong>edizione {esc(lib['edizione'])}</strong> di <em>{esc(lib['titolo'])}</em>, "
        f"una versione di lavoro del manoscritto. L'<strong>edizione {esc(lib['prossima'])}</strong>, quella "
        f"definitiva, rivista e completa, è {link}.</p></aside>"
    )


def menu_html(sito, sezioni, attiva):
    voci = []
    for slug in sito["menu"]:
        s = sezioni[slug]
        corrente = ' aria-current="page"' if slug == attiva else ""
        voci.append(f'<a href="{url_pagina(sito, slug, "index.html")}"{corrente}>{esc(s["menu"])}</a>')
    return "\n        ".join(voci)


def impagina(sito, sezioni, modello, *, slug, titolo, descrizione, contenuto, url, extra_classi="", banner=""):
    sez = sezioni.get(slug)
    titolo_completo = titolo if slug == "home" and titolo == sito["titolo"] else f"{titolo} — {sito['nome']}"
    valori = {
        "lingua": sito["lingua"],
        "titolo": esc(titolo_completo),
        "descrizione": esc(descrizione),
        "canonical": esc(sito["url"].rstrip("/") + url),
        "base": sito["base"],
        "nome": esc(sito["nome"]),
        "menu": menu_html(sito, sezioni, slug),
        "banner": banner,
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


def scrivi(url, sito, testo, scritte):
    p = percorso_dist(url, sito)
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(testo, encoding="utf-8")
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
                out.append(f'<aside class="box {esc(c.get("role") or "")}">{testa}{self.blocchi(c, livello)}</aside>')
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
                    f"<{c.tag.replace(DB, '')}>{self.inline(c)}</{c.tag.replace(DB, '')}>" for c in tr
                ) + "</tr>"
                for tr in parte
            )
            out += f"<{nome}>{righe}</{nome}>"
        return out


def carica_libro(cartella):
    radice = ET.parse(cartella / "libro.xml").getroot()
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


def genera_libro(sito, sezioni, modello, scritte):
    sez = sezioni["libro"]
    cartella = sez["cartella"] / "docbook"
    info, componenti = carica_libro(cartella)
    base = url_pagina(sito, "libro", "index.html") + "leggi/"
    conv = DocBookHTML(base)
    banner = banner_libro(sito)
    titolo_libro = "".join(info.find(DB + "title").itertext())
    edizione = info.findtext(DB + "edition")
    if edizione != sito["libro"]["edizione"]:
        raise ErroreSorgente(
            f"libro: edizione {edizione} nel DocBook diversa da sito.json ({sito['libro']['edizione']})"
        )

    # Indice
    righe, parte_corrente = [], object()
    for parte, el in componenti:
        if parte != parte_corrente:
            if righe:
                righe.append("</ol>")
            if parte:
                righe.append(f'<h2 class="parte">{esc(parte)}</h2>')
            righe.append('<ol class="indice-libro">')
            parte_corrente = parte
        tit = "".join(el.find(DB + "title").itertext())
        liv = el.find(DB + "para[@role='livello']")
        badge = f'<span class="livello">{esc(liv.text.split(":")[1].split("—")[0].strip())}</span>' if liv is not None else ""
        eti = etichetta(el)
        righe.append(
            f'<li><a href="{base}{esc(el.get(XML_ID))}/"><small>{esc(eti)}</small>'
            f"<span>{esc(tit)}</span>{badge}</a></li>"
        )
    righe.append("</ol>")
    indice = (
        f'<article class="lettura"><p class="occhiello">Leggi online</p><h1>{esc(titolo_libro)}</h1>'
        f'<p class="lead">{esc(info.findtext(DB + "subtitle"))} · di {esc(sito["autore"])}</p>{banner}'
        '<p class="nota-indice">Ogni capitolo ha un\'etichetta: <strong>F</strong> fondamenta (si legge una volta), '
        "<strong>G</strong> giornaliero, <strong>S</strong> settimanale, <strong>M</strong> mensile. "
        "Se hai fretta, leggi prima tutti gli F.</p>"
        + "".join(righe) + "</article>"
    )
    scrivi(base, sito, impagina(
        sito, sezioni, modello, slug="libro", titolo=f"{titolo_libro} — edizione {edizione} online",
        descrizione=f"Leggi gratis online l'edizione {edizione} di {titolo_libro}, capitolo per capitolo.",
        contenuto=indice, url=base, extra_classi="pagina-libro"), scritte)

    # Un file per componente
    for i, (parte, el) in enumerate(componenti):
        ident = el.get(XML_ID)
        tit = "".join(el.find(DB + "title").itertext())
        eti = etichetta(el)
        nav = '<nav class="pager" aria-label="Capitoli">'
        if i > 0:
            p = componenti[i - 1][1]
            nav += f'<a class="prev" href="{base}{esc(p.get(XML_ID))}/"><small>← Precedente</small>{esc("".join(p.find(DB + "title").itertext()))}</a>'
        else:
            nav += f'<a class="prev" href="{base}"><small>← Indice</small>Torna all\'indice</a>'
        if i < len(componenti) - 1:
            n = componenti[i + 1][1]
            nav += f'<a class="next" href="{base}{esc(n.get(XML_ID))}/"><small>Successivo →</small>{esc("".join(n.find(DB + "title").itertext()))}</a>'
        else:
            nav += f'<a class="next" href="{url_pagina(sito, "libro", "index.html")}"><small>Fine →</small>Il libro</a>'
        nav += "</nav>"
        corpo = (
            f'<article class="lettura"><p class="briciole"><a href="{base}">{esc(titolo_libro)}</a>'
            + (f" · {esc(parte)}" if parte else "") + "</p>"
            + (f'<p class="occhiello">{esc(eti)}</p>' if eti else "")
            + f"<h1>{esc(tit)}</h1>{banner}" + conv.blocchi(el, 1) + nav + "</article>"
        )
        url = f"{base}{ident}/"
        scrivi(url, sito, impagina(
            sito, sezioni, modello, slug="libro", titolo=f"{(eti + ': ') if eti else ''}{tit}",
            descrizione=f"{titolo_libro}, edizione {edizione}: {tit}.",
            contenuto=corpo, url=url, extra_classi="pagina-libro"), scritte)
    return len(componenti)


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
    (DIST / "404.html").write_text(impagina(
        sito, sezioni, modello, slug="", titolo="Pagina non trovata", descrizione="Pagina non trovata.",
        contenuto=corpo, url=sito["base"] + "404.html"), encoding="utf-8")
    radice = sito["url"].rstrip("/")
    voci = "".join(f"<url><loc>{esc(radice + u)}</loc></url>" for u in scritte)
    (DIST / "sitemap.xml").write_text(
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        f'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{voci}</urlset>\n', encoding="utf-8")
    (DIST / "robots.txt").write_text(f"User-agent: *\nAllow: /\nSitemap: {radice}/sitemap.xml\n", encoding="utf-8")


# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--verifica", action="store_true", help="controlla i sorgenti senza generare dist/")
    ap.add_argument("--rigoroso", action="store_true", help=f"fallisce se resta il segnaposto '{SEGNAPOSTO}'")
    a = ap.parse_args()

    try:
        sito, sezioni = carica()
        if a.verifica:
            carica_libro(sezioni["libro"]["cartella"] / "docbook")
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
        }

        scritte = []
        for slug, s in sezioni.items():
            for pagina in s["pagine"]:
                url = url_pagina(sito, slug, pagina["file"])
                contenuto = sostituisci((s["cartella"] / pagina["file"]).read_text(encoding="utf-8"), valori)
                scrivi(url, sito, impagina(
                    sito, sezioni, modello, slug=slug, titolo=pagina["titolo"],
                    descrizione=pagina["descrizione"], contenuto=contenuto, url=url,
                    banner=banner_libro(sito, compatto=True) if slug == "home" else ""), scritte)
        n_libro = genera_libro(sito, sezioni, modello, scritte)
        genera_versioni(sito, sezioni, modello, scritte)
        genera_extra(sito, sezioni, modello, scritte)

        # Segnaposto rimasti e {{chiavi}} non sostituite
        mancanti = []
        for f in DIST.rglob("*.html"):
            t = f.read_text(encoding="utf-8")
            if SEGNAPOSTO in t:
                mancanti.append(str(f.relative_to(DIST)))
            if re.search(r"\{\{\w+\}\}", t):
                raise ErroreSorgente(f"{f.relative_to(DIST)}: segnaposto {{{{...}}}} non sostituito")
        print(f"Generato dist/: sito {sito['versione']}, {len(scritte)} pagine ({n_libro} del libro).")
        if not sito["libro"]["amazon_url"]:
            mancanti.append("sito.json: libro.amazon_url vuoto")
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
