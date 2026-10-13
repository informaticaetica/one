#!/usr/bin/env python3
"""Converte una guida in Markdown in sorgenti DocBook 5 (un file per capitolo).

Uso:

    python3 scripts/md2docbook.py guida.md sezioni/guide/metodo-zdc/docbook

Struttura attesa del Markdown:
  - `# Titolo` e, subito dopo, `## Sottotitolo`;
  - righe di metadati `**Autore**: ...`, `**Versione**: ...`, `**Data**: ...`, `**Licenza**: ...`;
  - `## PREFAZIONE` (diventa <preface>), `## INDICE` (ignorato: l'indice si rigenera);
  - `## CAPITOLO N: TITOLO` (diventa <chapter label="N">), `## APPENDICE ...` (<appendix>);
  - `###` e `####` sezioni annidate; paragrafi, elenchi `-` e `1.`, tabelle a pipe, `**grassetto**`.

Produce guida.xml (metadati + xi:include) e capitoli/*.xml. Dopo l'importazione i file
DocBook sono la fonte ufficiale: le correzioni si fanno lì.
"""
import argparse
import os
import re
import unicodedata
import xml.etree.ElementTree as ET

DB = "http://docbook.org/ns/docbook"
XI = "http://www.w3.org/2001/XInclude"
XML_ID = "{http://www.w3.org/XML/1998/namespace}id"
XML_LANG = "{http://www.w3.org/XML/1998/namespace}lang"
ET.register_namespace("", DB)
ET.register_namespace("xi", XI)

SIGLE = ("Z.D.C.", "APPO", "PMI", "PRINCE2")


def d(tag):
    return f"{{{DB}}}{tag}"


def slug(t):
    t = unicodedata.normalize("NFKD", t).encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "-", t).strip("-")


def frase(t):
    """TITOLO IN MAIUSCOLO → Titolo in maiuscolo, rispettando le sigle."""
    if t != t.upper():
        return t
    out = t.lower()
    out = out[:1].upper() + out[1:]
    for s in SIGLE:
        out = re.sub(re.escape(s.lower()), s, out, flags=re.I)
    return out


def inline(testo, el):
    """Testo con **grassetto** dentro l'elemento el."""
    parti = re.split(r"(\*\*[^*]+\*\*)", testo)
    el.text = ""
    ultimo = None
    for p in parti:
        if p.startswith("**") and p.endswith("**") and len(p) > 4:
            ultimo = ET.SubElement(el, d("emphasis"), role="strong")
            ultimo.text = p[2:-2]
            ultimo.tail = ""
        elif ultimo is None:
            el.text += p
        else:
            ultimo.tail += p
    return el


def blocchi(righe):
    """Raggruppa le righe in blocchi: ('h', livello, testo), ('p', testo), ('ul'|'ol', voci), ('tab', righe)."""
    out, i = [], 0
    while i < len(righe):
        r = righe[i].rstrip()
        if not r.strip() or r.strip() == "---":
            i += 1
        elif m := re.match(r"^(#{1,6})\s+(.*)$", r):
            out.append(("h", len(m.group(1)), m.group(2).strip()))
            i += 1
        elif r.lstrip().startswith("|"):
            tab = []
            while i < len(righe) and righe[i].lstrip().startswith("|"):
                celle = [c.strip() for c in righe[i].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{2,}:?", c) for c in celle):
                    tab.append(celle)
                i += 1
            out.append(("tab", tab))
        elif re.match(r"^\s*[-*]\s+", r):
            voci = []
            while i < len(righe) and re.match(r"^\s*[-*]\s+", righe[i]):
                voci.append(re.sub(r"^\s*[-*]\s+", "", righe[i]).strip())
                i += 1
            out.append(("ul", voci))
        elif re.match(r"^\s*\d+\.\s+", r):
            voci = []
            while i < len(righe) and re.match(r"^\s*\d+\.\s+", righe[i]):
                voci.append(re.sub(r"^\s*\d+\.\s+", "", righe[i]).strip())
                i += 1
            out.append(("ol", voci))
        else:
            par = []
            while i < len(righe) and righe[i].strip() and not re.match(r"^(#|\||\s*[-*]\s|\s*\d+\.\s|---)", righe[i]):
                par.append(righe[i].strip())
                i += 1
            out.append(("p", " ".join(par)))
    return out


def importa(md, uscita):
    righe = open(md, encoding="utf-8").read().splitlines()
    bl = blocchi(righe)

    # Metadati in testa: titolo, sottotitolo, **Chiave**: valore
    titolo = sottotitolo = None
    meta = {}
    while bl and (bl[0][0] == "h" and bl[0][1] <= 2 and not re.match(r"^(PREFAZIONE|INDICE|CAPITOLO|APPENDICE)", bl[0][2], re.I)
                  or bl[0][0] == "p" and re.match(r"^\*\*[^*]+\*\*:", bl[0][1])):
        b = bl.pop(0)
        if b[0] == "h" and titolo is None:
            titolo = frase(b[2])
        elif b[0] == "h":
            sottotitolo = b[2]
        else:
            for k, v in re.findall(r"\*\*([^*]+)\*\*:\s*(.*?)(?=\s*\*\*[^*]+\*\*:|$)", b[1]):
                meta[k.strip().lower()] = v.strip()

    # Componenti: un ## apre un nuovo capitolo/prefazione/appendice
    componenti, corrente = [], None
    for b in bl:
        if b[0] == "h" and b[1] == 2:
            t = b[2]
            if re.match(r"^INDICE$", t, re.I):
                corrente = None  # l'indice si rigenera
                continue
            if m := re.match(r"^CAPITOLO\s+(\d+)\s*[:—-]\s*(.*)$", t, re.I):
                corrente = ("chapter", f"capitolo-{int(m.group(1)):02d}", frase(m.group(2)), m.group(1), [])
            elif m := re.match(r"^APPENDICE\s*[:—-]?\s*(.*)$", t, re.I):
                corrente = ("appendix", "appendice", frase(m.group(1) or "Appendice"), "A", [])
            else:
                corrente = ("preface", slug(t), frase(t), None, [])
            componenti.append(corrente)
        elif corrente is not None:
            corrente[4].append(b)

    os.makedirs(os.path.join(uscita, "capitoli"), exist_ok=True)
    guida = ET.Element(d("book"), {XML_ID: slug(titolo), "version": "5.0", XML_LANG: "it"})
    info = ET.SubElement(guida, d("info"))
    ET.SubElement(info, d("title")).text = titolo
    if sottotitolo:
        ET.SubElement(info, d("subtitle")).text = sottotitolo
    if "autore" in meta:
        nome = re.sub(r"\s*\(.*\)$", "", meta["autore"])
        n, _, c = nome.partition(" ")
        pn = ET.SubElement(ET.SubElement(info, d("author")), d("personname"))
        ET.SubElement(pn, d("firstname")).text = n
        ET.SubElement(pn, d("surname")).text = c
    if "versione" in meta:
        ET.SubElement(info, d("edition")).text = meta["versione"]
    if "data" in meta:
        ET.SubElement(info, d("pubdate")).text = meta["data"]
    if "licenza" in meta:
        ET.SubElement(ET.SubElement(info, d("legalnotice")), d("para")).text = meta["licenza"]

    for tipo, ident, tit, label, corpo in componenti:
        radice = ET.Element(d(tipo), {XML_ID: ident, "version": "5.0", XML_LANG: "it"})
        if label:
            radice.set("label", label)
        ET.SubElement(radice, d("title")).text = tit
        pila = [(2, radice)]  # (livello del titolo Markdown, elemento contenitore)
        for b in corpo:
            if b[0] == "h":
                while pila[-1][0] >= b[1]:
                    pila.pop()
                sez = ET.SubElement(pila[-1][1], d("section"), {XML_ID: f"{ident}-{slug(b[2])}"})
                inline(b[2], ET.SubElement(sez, d("title")))
                pila.append((b[1], sez))
                continue
            dove = pila[-1][1]
            if b[0] == "p":
                inline(b[1], ET.SubElement(dove, d("para")))
            elif b[0] in ("ul", "ol"):
                lista = ET.SubElement(dove, d("itemizedlist" if b[0] == "ul" else "orderedlist"))
                for v in b[1]:
                    inline(v, ET.SubElement(ET.SubElement(lista, d("listitem")), d("para")))
            elif b[0] == "tab":
                tab = ET.SubElement(dove, d("informaltable"))
                testa, *dati = b[1]
                tr = ET.SubElement(ET.SubElement(tab, d("thead")), d("tr"))
                for c in testa:
                    inline(c, ET.SubElement(tr, d("th")))
                corpo_t = ET.SubElement(tab, d("tbody"))
                for riga in dati:
                    tr = ET.SubElement(corpo_t, d("tr"))
                    for c in riga:
                        inline(c, ET.SubElement(tr, d("td")))
        # Una sezione senza contenuto non è valida in DocBook: le si dà un paragrafo vuoto esplicito
        for sez in radice.iter(d("section")):
            if len(sez) == 1:
                ET.SubElement(sez, d("para")).text = "(Testo non ancora disponibile.)"
        ET.indent(radice, space="  ")
        with open(os.path.join(uscita, "capitoli", ident + ".xml"), "wb") as f:
            f.write(b'<?xml version="1.0" encoding="UTF-8"?>\n' + ET.tostring(radice, encoding="utf-8") + b"\n")
        ET.SubElement(guida, f"{{{XI}}}include", href=f"capitoli/{ident}.xml")

    ET.indent(guida, space="  ")
    with open(os.path.join(uscita, "guida.xml"), "wb") as f:
        f.write(b'<?xml version="1.0" encoding="UTF-8"?>\n' + ET.tostring(guida, encoding="utf-8") + b"\n")
    print(f"{titolo}: {len(componenti)} parti → {uscita}")


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("markdown")
    ap.add_argument("uscita")
    a = ap.parse_args()
    importa(a.markdown, a.uscita)
