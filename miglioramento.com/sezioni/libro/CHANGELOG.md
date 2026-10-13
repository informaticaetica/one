# Changelog — sezione Il libro

Questa è la versione della **sezione web**. L'edizione del libro (oggi 1.8) è
indicata separatamente in `sito.json` e in `docbook/libro.xml` (`<edition>`).

## [1.1.0] - 2026-10-13

### Aggiunto
- Pagina «La collana APPO» (`/libro/collana/`) con i libri già su Amazon, letti da
  `sito.json` → `collana`, e Metodo APPO 2.0 in uscita.

## [1.0.1] - 2026-10-13

### Modificato
- L'avviso sull'edizione è una nota dentro la pagina (non più un riquadro laterale per gli screen reader).

## [1.0.0] - 2026-10-13

### Aggiunto
- Pagina di presentazione del libro con avviso sull'edizione.
- Lettura online dell'edizione 1.8, un capitolo per pagina, generata dai
  sorgenti DocBook 5 in `docbook/` (importati dall'EPUB con `scripts/epub2docbook.py`).
