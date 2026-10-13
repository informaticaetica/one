# Changelog del sito miglioramento.com

Versione complessiva del sito. Ogni sezione ha anche un proprio CHANGELOG in
`sezioni/<nome>/CHANGELOG.md`, con una versione indipendente.

Formato [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/),
[versionamento semantico](https://semver.org/lang/it/). Tag git dei rilasci:
`miglioramento-vX.Y.Z`.

## [Non rilasciato]

## [1.0.0] - 2026-10-13

### Aggiunto
- Prima versione del sito: sezioni Home, Il metodo, Inizia qui, Strumenti,
  Articoli, Il libro, L'autore, Note legali.
- Lettura online del libro *Metodo APPO 2.0 — Osa Realizzarti Ora*,
  edizione 1.8, un capitolo per pagina, generata da sorgenti DocBook 5.
- Pagina "Versioni" e file `versioni.json` con la versione di ogni sezione.
- Generatore `build.py` con verifica delle versioni, `sitemap.xml`, `robots.txt`, `404.html`.
