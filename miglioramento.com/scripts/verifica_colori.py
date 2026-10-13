#!/usr/bin/env python3
"""Verifica contrasto WCAG e daltonismo della palette in statici/stile.css.

Legge i colori dei due temi dal CSS (blocco :root e blocco [data-theme="dark"]),
simula protanopia, deuteranopia e tritanopia (Machado, Oliveira e Fernandes, 2009)
e controlla che ogni coppia testo/sfondo usata dal sito resti leggibile.
Esce con errore se una coppia scende sotto la soglia.

    python3 scripts/verifica_colori.py
"""
import math
import re
import sys
from pathlib import Path

CSS = Path(__file__).resolve().parent.parent / "statici" / "stile.css"

# Coppie (testo, sfondo, dove si usa, soglia WCAG). 4,5:1 = testo normale (AA), 3:1 = elementi grafici.
COPPIE = [
    ("testo", "sfondo", "testo principale", 4.5),
    ("testo", "superficie", "testo nei riquadri", 4.5),
    ("testo", "primario-tenue", "box \"Cosa imparerai qui\"", 4.5),
    ("testo", "accento-tenue", "avviso edizione, note", 4.5),
    ("tenue", "sfondo", "testo secondario", 4.5),
    ("tenue", "superficie", "testo secondario su schede", 4.5),
    ("primario", "sfondo", "link", 4.5),
    ("primario", "superficie", "link su schede", 4.5),
    ("primario", "primario-tenue", "badge F/G/S/M", 4.5),
    ("primario-testo", "primario", "pulsanti, barra in alto", 4.5),
    ("accento", "sfondo", "occhielli, titoli di parte", 4.5),
    ("accento", "superficie", "domande delle quattro parole", 4.5),
    ("accento", "accento-tenue", "etichette (Edizione, sezioni)", 4.5),
    ("primario", "sfondo", "contorno di focus", 3.0),
]

SIMULAZIONI = {
    "visione normale": None,
    "protanopia": [[0.152286, 1.052583, -0.204868], [0.114503, 0.786281, 0.099216], [-0.003882, -0.048116, 1.051998]],
    "deuteranopia": [[0.367322, 0.860646, -0.227968], [0.280085, 0.672501, 0.047413], [-0.011820, 0.042940, 0.968881]],
    "tritanopia": [[1.255528, -0.076749, -0.178779], [-0.078411, 0.930809, 0.147602], [0.004733, 0.691367, 0.303900]],
}


def token(blocco):
    return dict(re.findall(r"--([\w-]+):\s*(#[0-9a-fA-F]{6})", blocco))


def temi():
    css = CSS.read_text(encoding="utf-8")
    chiaro = token(re.search(r":root\s*\{(.*?)\}", css, re.S).group(1))
    scuro = dict(chiaro)
    scuro.update(token(re.search(r':root\[data-theme="dark"\]\s*\{(.*?)\}', css, re.S).group(1)))
    return {"chiaro": chiaro, "scuro": scuro}


def lineare(hex_):
    c = [int(hex_[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    return [v / 12.92 if v <= 0.04045 else ((v + 0.055) / 1.055) ** 2.4 for v in c]


def simula(hex_, m):
    l = lineare(hex_)
    return l if m is None else [max(0.0, min(1.0, sum(m[i][j] * l[j] for j in range(3)))) for i in range(3)]


def contrasto(a, b):
    la, lb = (0.2126 * x[0] + 0.7152 * x[1] + 0.0722 * x[2] for x in (a, b))
    alto, basso = max(la, lb), min(la, lb)
    return (alto + 0.05) / (basso + 0.05)


def main():
    errori = 0
    for nome, t in temi().items():
        print(f"Tema {nome}")
        for fg, bg, uso, soglia in COPPIE:
            valori = {s: contrasto(simula(t[fg], m), simula(t[bg], m)) for s, m in SIMULAZIONI.items()}
            peggiore = min(valori, key=valori.get)
            ok = valori[peggiore] >= soglia
            errori += not ok
            print(f"  {'ok ' if ok else 'NO '} {uso:32s} {valori['visione normale']:5.2f}:1, "
                  f"minimo {valori[peggiore]:5.2f}:1 ({peggiore}), soglia {soglia}:1")
    if errori:
        print(f"\n{errori} coppie sotto soglia.")
        return 1
    print("\nTutte le coppie superano la soglia, anche con protanopia, deuteranopia e tritanopia.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
