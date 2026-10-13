#!/usr/bin/env python3
"""Importa il libro da EPUB (generato da pandoc) in sorgenti DocBook 5.

Uso (una tantum, o quando arriva un nuovo EPUB):

    python3 scripts/epub2docbook.py libro.epub sezioni/libro/docbook --edizione 1.8

Produce:
  - libro.xml            il libro, che include i capitoli via XInclude
  - capitoli/*.xml       un file per prefazione / capitolo / appendice

Dopo l'importazione i file DocBook sono la fonte ufficiale del libro:
le correzioni si fanno lì, non nell'EPUB.
"""
import argparse
import re
import unicodedata
import zipfile
import xml.etree.ElementTree as ET

XH = "{http://www.w3.org/1999/xhtml}"
DB = "http://docbook.org/ns/docbook"
XI = "http://www.w3.org/2001/XInclude"
XML_ID = "{http://www.w3.org/XML/1998/namespace}id"
ET.register_namespace("", DB)
ET.register_namespace("xi", XI)

PUNTINI = re.compile(r"^[\s.…]*$")
LIVELLO = re.compile(r"^Livello:\s*([FGSM])\b")
CAPITOLO = re.compile(r"^Capitolo\s+(\d+)\s+—\s+(.*)$")

# Struttura in parti, come nell'indice originale del libro
PARTI = [
    ("parte-1", "Parte I: Aspirazione", range(1, 8)),
    ("parte-2", "Parte II: Problema", range(8, 12)),
    ("parte-3", "Parte III: Progetto", range(12, 17)),
    ("parte-4", "Parte IV: Opportunità", range(17, 20)),
]

# Intestazioni di tabella spezzate male da pandoc nell'Eserciziario
INTESTAZIONI = {
    ("Giorno La", "cosa più importante di oggi"): ("Giorno", "La cosa più importante di oggi"),
    ("Giorno Co", "sa mi ha rallentato di più (o: cosa ho imparato oggi)"):
        ("Giorno", "Cosa mi ha rallentato di più (o: cosa ho imparato oggi)"),
    ("Giorno Qu", "DiVi (1-10)"): ("Giorno", "QuDiVi (1-10)"),
}


def slug(testo):
    t = unicodedata.normalize("NFKD", testo).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", t).strip("-")


def testo(el):
    return "".join(el.itertext()).strip()


def db(tag, **attr):
    return ET.Element(f"{{{DB}}}{tag}", {XML_ID if k == "id" else k: v for k, v in attr.items()})


def sub(parent, tag, **attr):
    e = db(tag, **attr)
    parent.append(e)
    return e


class Convertitore:
    def __init__(self):
        self.ids = set()

    def nuovo_id(self, base):
        i, n = base, 2
        while i in self.ids:
            i, n = f"{base}-{n}", n + 1
        self.ids.add(i)
        return i

    # --- contenuto in linea -------------------------------------------------
    def inline(self, src, dst):
        """Copia testo e figli in linea di un elemento XHTML dentro dst."""
        dst.text = (dst.text or "") + (src.text or "")
        for c in src:
            tag = c.tag.replace(XH, "")
            if tag == "strong":
                e = sub(dst, "emphasis", role="strong")
            elif tag == "em":
                e = sub(dst, "emphasis")
            elif tag == "code":
                e = sub(dst, "quote")
            elif tag == "a":
                e = sub(dst, "phrase")  # i soli link del libro puntano all'indice, rigenerato
            else:
                e = sub(dst, "phrase")
            self.inline(c, e)
            e.tail = c.tail
        return dst

    # --- blocchi --------------------------------------------------------------
    def blocchi(self, figli, dst, prefisso):
        figli = list(figli)
        i = 0
        while i < len(figli):
            c = figli[i]
            tag = c.tag.replace(XH, "")
            t = testo(c)
            if tag in ("hr", "h1", "h2"):
                pass
            elif tag == "p":
                if PUNTINI.match(t):
                    pass
                elif "## Scheda di riferimento rapido" in t:  # difetto di pandoc nel capitolo 18
                    sez = sub(dst, "section", id=self.nuovo_id(f"{prefisso}-scheda-di-riferimento-rapido"), role="scheda")
                    sub(sez, "title").text = "Scheda di riferimento rapido"
                    j = i + 1
                    while j < len(figli) and figli[j].tag == XH + "p":
                        self.inline(figli[j], sub(sez, "para"))
                        j += 1
                    i = j
                    continue
                elif LIVELLO.match(t):
                    sub(dst, "para", role="livello").text = t
                elif self.citazione_iniziale(c, dst):
                    ep = sub(dst, "epigraph")
                    self.inline(c[0], sub(ep, "para"))
                else:
                    self.inline(c, sub(dst, "para"))
            elif tag in ("ul", "ol"):
                lista = sub(dst, "itemizedlist" if tag == "ul" else "orderedlist")
                if tag == "ol" and c.get("start"):
                    lista.set("startingnumber", c.get("start"))
                for li in c:
                    item = sub(lista, "listitem")
                    if any(x.tag.replace(XH, "") in ("p", "ul", "ol") for x in li):
                        self.blocchi(li, item, prefisso)
                    else:
                        self.inline(li, sub(item, "para"))
            elif tag == "blockquote":
                self.citazione(c, dst)
            elif tag == "table":
                self.tabella(c, dst)
            elif tag == "section":
                self.sezione(c, dst, prefisso)
            else:
                raise ValueError(f"elemento non gestito: {tag}")
            i += 1

    @staticmethod
    def citazione_iniziale(p, dst):
        """Il motto in corsivo che apre ogni capitolo, subito dopo la riga "Livello"."""
        ultimo = dst[-1] if len(dst) else None
        return (
            ultimo is not None and ultimo.get("role") == "livello"
            and len(p) == 1 and p[0].tag == XH + "em" and not (p.text or "").strip()
            and testo(p).startswith(("“", "‘", "\""))
        )

    def citazione(self, c, dst):
        paras = [p for p in c if p.tag == XH + "p"]
        primo = testo(paras[0]) if paras else ""
        if primo.startswith("Cosa imparerai qui"):
            box = sub(dst, "sidebar", role="obiettivi")
            sub(box, "title").text = "Cosa imparerai qui"
            voci = [v.strip() for v in primo[len("Cosa imparerai qui"):].split(" - ") if v.strip()]
            lista = sub(box, "itemizedlist")
            for v in voci:
                sub(sub(lista, "listitem"), "para").text = v.lstrip("- ").strip()
        elif len(paras) == 2:
            ep = sub(dst, "epigraph")
            firma = self.inline(paras[1], sub(ep, "attribution"))
            for e in firma.iter(f"{{{DB}}}emphasis"):  # in <attribution> il titolo dell'opera è <citetitle>
                e.tag = f"{{{DB}}}citetitle"
            self.inline(paras[0], sub(ep, "para"))
        else:
            bq = sub(dst, "blockquote")
            for p in paras:
                self.inline(p, sub(bq, "para"))

    def tabella(self, c, dst):
        tab = sub(dst, "informaltable")
        for parte in c:
            nome = parte.tag.replace(XH, "")
            if nome not in ("thead", "tbody"):
                continue
            out = sub(tab, nome)
            for tr in parte:
                riga = sub(out, "tr")
                celle = list(tr)
                chiave = tuple(testo(x) for x in celle)
                if chiave in INTESTAZIONI:
                    for x, nuovo in zip(celle, INTESTAZIONI[chiave]):
                        x.text, x[:] = nuovo, []
                for cella in celle:
                    self.inline(cella, sub(riga, cella.tag.replace(XH, "")))

    def sezione(self, c, dst, prefisso):
        h = c.find(XH + "h2")
        titolo = testo(h)
        figli = [x for x in c if x is not h]
        if not any(PUNTINI.match(testo(x)) is None or x.tag == XH + "table" for x in figli):
            return  # sezioni vuote (es. "Note e riferimenti" senza contenuto)
        attr = {}
        if titolo.startswith("Riquadro di sintesi"):
            attr["role"] = "sintesi"
        elif titolo.startswith("Scheda di riferimento rapido"):
            attr["role"] = "scheda"
        sez = sub(dst, "section", id=self.nuovo_id(f"{prefisso}-{slug(titolo)}"), **attr)
        self.inline(h, sub(sez, "title"))
        self.blocchi(figli, sez, prefisso)

    # --- componenti -----------------------------------------------------------
    def componente(self, sezione, tipo, titolo, ident, label=None, edizione=None):
        attr = {"id": self.nuovo_id(ident)}
        if label:
            attr["label"] = label
        radice = db(tipo, **attr)
        radice.set("version", "5.0")
        radice.set(f"{{http://www.w3.org/XML/1998/namespace}}lang", "it")
        sub(radice, "title").text = titolo
        corpo = [x for x in sezione if x.tag != XH + "h1"]
        if edizione:  # il manoscritto riporta un numero interno: mostriamo l'edizione pubblica
            for x in sezione.iter():
                for campo in ("text", "tail"):
                    v = getattr(x, campo)
                    if v and "Versione 1.29" in v:
                        setattr(x, campo, v.replace("Versione 1.29", f"Edizione {edizione}"))
        self.blocchi(corpo, radice, attr["id"])
        return radice


def importa(epub, uscita, edizione):
    import os
    z = zipfile.ZipFile(epub)
    opf_path = ET.fromstring(z.read("META-INF/container.xml")).find(".//{*}rootfile").get("full-path")
    base = opf_path.rsplit("/", 1)[0] + "/"
    opf = ET.fromstring(z.read(opf_path))
    meta = opf.find("{*}metadata")
    autore = meta.find("{http://purl.org/dc/elements/1.1/}creator").text
    man = {i.get("id"): i.get("href") for i in opf.find("{*}manifest")}
    spine = [man[r.get("idref")] for r in opf.find("{*}spine") if man[r.get("idref")].startswith("text/")]

    conv = Convertitore()
    os.makedirs(os.path.join(uscita, "capitoli"), exist_ok=True)
    davanti, capitoli, dietro = [], {}, []
    for href in spine:
        sezione = ET.fromstring(z.read(base + href)).find(f"{XH}body/{XH}section")
        titolo = testo(sezione.find(XH + "h1"))
        m = CAPITOLO.match(titolo)
        if titolo.startswith("Versione "):
            continue  # frontespizio: diventa <info> del libro
        if titolo == "Indice":
            # l'indice si rigenera; resta la nota di lettura e la mappa del metodo
            for p in list(sezione):
                if p.tag == XH + "p" and not testo(p).startswith("Come leggere"):
                    sezione.remove(p)
                elif p.tag == XH + "ol":
                    sezione.remove(p)
            comp = conv.componente(sezione, "preface", "La mappa del metodo", "mappa-del-metodo")
            davanti.append(("mappa-del-metodo", comp))
        elif titolo == "Dedica":
            comp = conv.componente(sezione, "dedication", titolo, "dedica")
            davanti.append(("dedica", comp))
        elif m:
            n = int(m.group(1))
            ident = f"capitolo-{n:02d}"
            capitoli[n] = (ident, conv.componente(sezione, "chapter", m.group(2), ident, label=str(n)))
        elif titolo.startswith("Scheda di riferimento rapido"):
            capitoli[11.5] = ("intermezzo-meta-libro", conv.componente(sezione, "chapter", titolo, "intermezzo-meta-libro"))
        elif titolo == "Eserciziario":
            dietro.append(("eserciziario", conv.componente(sezione, "appendix", titolo, "eserciziario", label="A")))
        else:
            ident = slug(titolo)
            comp = conv.componente(sezione, "preface", titolo, ident, edizione=edizione)
            davanti.append((ident, comp))

    def scrivi(ident, el):
        ET.indent(el, space="  ")
        with open(os.path.join(uscita, "capitoli", ident + ".xml"), "wb") as f:
            f.write(b'<?xml version="1.0" encoding="UTF-8"?>\n')
            f.write(ET.tostring(el, encoding="utf-8"))
            f.write(b"\n")
        return f"capitoli/{ident}.xml"

    libro = db("book", id="metodo-appo")
    libro.set("version", "5.0")
    libro.set("{http://www.w3.org/XML/1998/namespace}lang", "it")
    info = sub(libro, "info")
    sub(info, "title").text = "Metodo APPO 2.0"
    sub(info, "subtitle").text = "Osa Realizzarti Ora"
    nome, _, cognome = autore.partition(" ")
    pn = sub(sub(info, "author"), "personname")
    sub(pn, "firstname").text = nome
    sub(pn, "surname").text = cognome
    sub(info, "edition").text = edizione
    sub(info, "releaseinfo").text = (
        f"Edizione {edizione}: anteprima gratuita. L'edizione 2.0 definitiva è in uscita su Amazon."
    )
    copyright = sub(info, "copyright")
    sub(copyright, "year").text = "2026"
    sub(copyright, "holder").text = autore

    def includi(parent, ident, el):
        ET.SubElement(parent, f"{{{XI}}}include", href=scrivi(ident, el))

    for ident, el in davanti:
        includi(libro, ident, el)
    for pid, titolo, numeri in PARTI:
        parte = sub(libro, "part", id=pid)
        sub(parte, "title").text = titolo
        chiavi = sorted(k for k in capitoli if int(k) in numeri or (k == 11.5 and pid == "parte-2"))
        for k in chiavi:
            includi(parte, *capitoli[k])
    for ident, el in dietro:
        includi(libro, ident, el)

    ET.indent(libro, space="  ")
    with open(os.path.join(uscita, "libro.xml"), "wb") as f:
        f.write(b'<?xml version="1.0" encoding="UTF-8"?>\n')
        f.write(ET.tostring(libro, encoding="utf-8"))
        f.write(b"\n")
    print(f"{len(davanti)} parti iniziali, {len(capitoli)} capitoli, {len(dietro)} appendici → {uscita}")


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("epub")
    ap.add_argument("uscita")
    ap.add_argument("--edizione", required=True, help="numero di edizione da mostrare, es. 1.8")
    a = ap.parse_args()
    importa(a.epub, a.uscita, a.edizione)
