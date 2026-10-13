# Changelog del sito miglioramento.com

Versione complessiva del sito. Ogni sezione ha anche un proprio CHANGELOG in
`sezioni/<nome>/CHANGELOG.md`, con una versione indipendente.

Formato [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/),
[versionamento semantico](https://semver.org/lang/it/). Tag git dei rilasci:
`miglioramento-vX.Y.Z`.

## [Non rilasciato]

## [1.4.0] - 2026-10-13

### Aggiunto
- Logo del metodo APPO (francobollo con cigno nero e cigno d'oro) nella testata
  di tutte le pagine, come icona del sito e in grande nella home (sezione Home 1.3.0).
- Anteprima per la condivisione sui social (Open Graph) con il logo.

### Rimosso
- L'icona provvisoria `statici/favicon.svg`.

## [1.3.0] - 2026-10-13

### Modificato
- Claim del sito: «Resisti al cigno nero. Cogli il cigno d'oro.» (sezione Home 1.2.0,
  con due riquadri che spiegano i due termini).

## [1.2.0] - 2026-10-13

### Aggiunto
- Claim «Scegli meglio, un giorno alla volta.» sotto il logo in tutte le pagine
  (`claim` in `sito.json`) e come titolo della home (sezione Home 1.1.0).

### Modificato
- La fascia sul libro su Amazon scende dalla cima della pagina a sotto l'apertura della home.

## [1.1.1] - 2026-10-13

### Modificato
- Sezione Note legali 1.0.1: tolti i riferimenti ad affiliazioni e pubblicità.

## [1.1.0] - 2026-10-13

### Modificato
- Sezione Inizia qui 1.1.0: nuova apertura su come è nato il metodo APPO e su
  tutto ciò a cui serve.

## [1.0.0] - 2026-10-13

### Aggiunto
- Prima versione del sito: sezioni Home, Il metodo, Inizia qui, Strumenti,
  Articoli, Il libro, L'autore, Note legali.
- Lettura online del libro *Metodo APPO 2.0 — Osa Realizzarti Ora*,
  edizione 1.8, un capitolo per pagina, generata da sorgenti DocBook 5.
- Pagina "Versioni" e file `versioni.json` con la versione di ogni sezione.
- Generatore `build.py` con verifica delle versioni, `sitemap.xml`, `robots.txt`, `404.html`.

### Accessibilità
- Palette verificata per daltonismo (protanopia, deuteranopia, tritanopia) e
  contrasto WCAG AA (almeno 4,5:1 per tutti i testi, in entrambi i temi).
- Ambra del tema chiaro scurito (`#b8742a` → `#8f5719`): il testo in ambra era a 3,0–3,5:1.
- Contorno di focus marcato per la navigazione da tastiera; pagina corrente
  del menu in grassetto, non segnalata solo dal colore.
