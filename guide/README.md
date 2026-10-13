# Guide APPO

Sito statico del progetto **Guide APPO**: nessuna dipendenza, nessuna build.

## Struttura

- `index.html` — pagina unica (navigazione tramite `#/…`)
- `assets/guide.js` — **tutti i contenuti**: testi del sito e elenco delle guide
- `assets/app.js` — logica (pagine, ricerca, filtri, tema chiaro/scuro)
- `assets/style.css` — stile

## Aggiungere una guida

Aggiungi un oggetto all'array `GUIDE` in `assets/guide.js`:

```js
{
  slug: "nome-url",
  titolo: "Titolo della guida",
  categoria: "Procedure",
  durata: "5 min",
  aggiornata: "2026-10-13",
  sommario: "Breve descrizione.",
  contenuto: "<p>Testo in HTML…</p>",
  // facoltativo: regole con una pagina ciascuna (#/guide/nome-url/1, /2, …)
  regole: [{ titolo: "…", sintesi: "…", contenuto: "<p>…</p>" }],
  conclusione: "<p>…</p>",
}
```

I testi della home e della sezione "Come iniziare" sono in `SITO` nello
stesso file; la fonte dei contenuti è in `FONTE`.

## Anteprima locale

```sh
cd guide && python3 -m http.server 8000
```

## Pubblicazione

Automatica: a ogni aggiornamento del branch `main` il workflow
`.github/workflows/pubblica.yml`:

1. pubblica la cartella `guide/` su GitHub Pages
   (<https://informaticaetica.github.io/one/>);
2. se la `versione` in `assets/guide.js` non ha ancora una release, crea il tag
   `vX.Y.Z` e la release con le note prese dal CHANGELOG.

Per rilasciare una nuova versione basta quindi aggiornare `versione` e il
CHANGELOG, poi unire in `main`.

## Versione e licenza

Versione corrente in `assets/guide.js` (campo `versione`), storico nel
[CHANGELOG](../CHANGELOG.md). Licenza [CC BY-NC-ND 4.0](../LICENSE).
