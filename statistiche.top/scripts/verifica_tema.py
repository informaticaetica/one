#!/usr/bin/env python3
"""Verifica il tema statistiche-top-tema.

- theme.json è JSON valido;
- i colori del tema (chiari in theme.json, scuri in style.css) sono identici ai
  token --st-* del plugin, già verificati per contrasto e daltonismo da
  verifica_colori.py: così tema e plugin non possono divergere;
- i modelli e le parti hanno i commenti dei blocchi aperti e chiusi correttamente;
- c'è un solo h1 per modello (il titolo della pagina o un titolo di livello 1).

    python3 scripts/verifica_tema.py
"""
import json
import re
import sys
from pathlib import Path

RADICE = Path(__file__).resolve().parent.parent
TEMA = RADICE / "tema" / "statistiche-top-tema"
CSS_PLUGIN = RADICE / "plugin" / "statistiche-top" / "assets" / "statistiche-top.css"
CORRISPONDENZE = {
    "base": "sfondo", "surface": "superficie", "contrast": "testo", "secondary": "tenue",
    "border": "bordo", "primary": "primario", "primary-soft": "primario-tenue",
    "accent": "accento", "accent-soft": "accento-tenue",
}


def token_plugin():
    css = CSS_PLUGIN.read_text(encoding="utf-8")
    chiaro = dict(re.findall(r"--st-([\w-]+):\s*(#[0-9a-fA-F]{6})", re.search(r":root\s*\{(.*?)\}", css, re.S).group(1)))
    scuro = dict(chiaro)
    scuro.update(re.findall(r"--st-([\w-]+):\s*(#[0-9a-fA-F]{6})", re.search(r':root\[data-theme="dark"\]\s*\{(.*?)\}', css, re.S).group(1)))
    return chiaro, scuro


def main():
    errori = []
    tema = json.loads((TEMA / "theme.json").read_text(encoding="utf-8"))
    chiaro, scuro = token_plugin()
    palette = {c["slug"]: c["color"].lower() for c in tema["settings"]["color"]["palette"]}
    css = (TEMA / "style.css").read_text(encoding="utf-8")
    blocchi_scuri = re.findall(r"(?:prefers-color-scheme: dark\)\s*\{\s*:root:not\(\[data-theme=\"light\"\]\)|:root\[data-theme=\"dark\"\])\s*\{(.*?)\}", css, re.S)
    if len(blocchi_scuri) != 2:
        errori.append("style.css: servono i due blocchi del tema scuro (automatico e scelto)")
    scuri = [dict(re.findall(r"--wp--preset--color--([\w-]+):\s*(#[0-9a-fA-F]{6})", b)) for b in blocchi_scuri]
    for slug, token in CORRISPONDENZE.items():
        if palette.get(slug) != chiaro[token].lower():
            errori.append(f"chiaro: {slug} {palette.get(slug)} ≠ --st-{token} {chiaro[token]}")
        for i, b in enumerate(scuri):
            if b.get(slug, "").lower() != scuro[token].lower():
                errori.append(f"scuro ({'automatico' if i == 0 else 'scelto'}): {slug} {b.get(slug)} ≠ --st-{token} {scuro[token]}")

    for file in sorted(list((TEMA / "templates").glob("*.html")) + list((TEMA / "parts").glob("*.html"))):
        testo = file.read_text(encoding="utf-8")
        pila = []
        for chiusura, nome, auto in re.findall(r"<!-- (/?)wp:([a-z0-9/-]+)(?: \{.*?\})? (/?)-->", testo):
            if auto:
                continue
            if chiusura:
                if not pila or pila.pop() != nome:
                    errori.append(f"{file.name}: chiusura di {nome} fuori posto")
            else:
                pila.append(nome)
        if pila:
            errori.append(f"{file.name}: blocchi non chiusi {pila}")
        if file.parent.name == "templates":
            h1 = len(re.findall(r'wp:post-title \{"level":1', testo)) + len(re.findall(r"<h1", testo)) + len(re.findall(r'wp:query-title \{[^}]*"level":1', testo))
            if h1 != 1:
                errori.append(f"{file.name}: {h1} titoli di livello 1 (ne serve uno)")

    for e in errori:
        print("NO ", e)
    print(f"\n{len(errori)} problemi." if errori else "Tema coerente con il plugin; modelli e parti ben formati.")
    return 1 if errori else 0


if __name__ == "__main__":
    sys.exit(main())
