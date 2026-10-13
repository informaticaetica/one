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
}
```

## Anteprima locale

```sh
cd guide && python3 -m http.server 8000
```

## Pubblicazione

Su GitHub: *Settings → Pages → Deploy from a branch*, cartella `/ (root)`.
Il sito sarà raggiungibile all'indirizzo `…/guide/`.

## Versione e licenza

Versione corrente in `assets/guide.js` (campo `versione`), storico nel
[CHANGELOG](../CHANGELOG.md). Licenza [CC BY-NC-ND 4.0](../LICENSE).
