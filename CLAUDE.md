# Regole del repository

Il repository contiene due siti: `guide/` (Guide APPO, GitHub Pages) e
`miglioramento.com/` (sito statico pubblicato su un server esterno).
Lingua dei contenuti, dei commit e dei commenti: italiano.

## Accessibilità e daltonismo: sempre, su ogni modifica

Ogni pagina, colore o componente nuovo o modificato deve rispettare queste regole.
Non sono facoltative.

- **WCAG 2.2 livello AA** come minimo, in tema chiaro e in tema scuro, su desktop e telefono.
- **Palette adatta ai daltonici**: ogni coppia testo/sfondo deve avere contrasto
  di almeno 4,5:1 (3:1 per elementi grafici e testo grande) anche simulando
  protanopia, deuteranopia e tritanopia. Blu e ambra sono la coppia di base perché
  restano distinguibili per tutti.
- **Mai informazioni affidate al solo colore**: usa anche testo, forma, icona,
  sottolineatura o grassetto (i link nel testo sono sottolineati, la pagina
  corrente del menu è in grassetto, i livelli dei capitoli hanno una lettera).
- **Colori solo tramite i token** di `:root` in `statici/stile.css`, definiti per
  entrambi i temi; niente colori letterali nei componenti.
- **Tema**: tre stati (automatico, chiaro, scuro). La scelta si salva in
  `localStorage` con la chiave `tema`, vale per tutto il sito e per le visite
  successive, e si sincronizza fra le schede aperte. Non rompere questo comportamento.
- **Tastiera**: tutto si usa senza mouse; focus sempre visibile; le cornici che
  scorrono hanno `tabindex="0"` e un nome; `Esc` chiude il menu.
- **Bersagli** cliccabili di almeno 44×44 px per i pulsanti.
- **Struttura**: un solo `h1` per pagina, titoli senza salti di livello, lingua `it`,
  testo alternativo per le immagini che informano (`alt=""` per quelle decorative).
- **Movimento**: animazioni solo dentro `prefers-reduced-motion: no-preference`.
- Niente font, script o contenuti di terze parti senza aggiornare privacy e cookie.

### Verifiche da eseguire prima di ogni commit su miglioramento.com

```sh
cd miglioramento.com
python3 build.py --verifica                 # versioni coerenti con i CHANGELOG
python3 scripts/verifica_colori.py          # contrasto + daltonismo della palette
python3 build.py && (cd dist && python3 -m http.server 8000 &)
node scripts/verifica_accessibilita.mjs http://localhost:8000   # axe-core, WCAG 2.2 AA
```

Le stesse verifiche girano in CI (`.github/workflows/miglioramento.yml`).

## Versioning di miglioramento.com

Ogni modifica a una sezione alza la sua versione in `sezioni/<nome>/sezione.json`
con una voce nel suo `CHANGELOG.md`; ogni modifica al sito alza la versione in
`sito.json` con una voce nel `CHANGELOG.md` principale (versionamento semantico).
Dettagli in `miglioramento.com/README.md`.
