# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/)
e il progetto adotta il [Versionamento Semantico](https://semver.org/lang/it/):

- **MAJOR** (2.0.0): riorganizzazioni che cambiano indirizzi o struttura del sito;
- **MINOR** (1.1.0): nuove guide o nuove sezioni;
- **PATCH** (1.0.1): correzioni di testi, refusi e piccoli ritocchi grafici.

Ad ogni rilascio: aggiornare `versione` in `guide/assets/guide.js`, aggiungere
una voce qui sotto e creare il tag git corrispondente (es. `v1.1.0`).

## [Non rilasciato]

## [1.1.0] - 2026-10-13

### Aggiunto
- Home dedicata al metodo APPO: le quattro parole (Aspirazione, Problema,
  Progetto, Opportunità), il "mezzo secondo" fra impulso e scelta, le aree
  della vita a cui saranno dedicate le prossime guide.
- Sezione **Come iniziare** (`#/inizia`): il metodo in 60 secondi, tempi
  reali, i primi tre gesti, il piano della prima settimana, cosa aspettarsi,
  scheda di riferimento rapido.
- **Guida rapida alla produttività** con 10 regole in elenco numerato e una
  pagina dedicata per ogni regola (`#/guide/produttivita/1` … `/10`), con
  navigazione fra regola precedente e successiva.
- Indicazione della fonte: *Metodo APPO 2.0 — Osa Realizzarti Ora* di
  Marco Costanzo.

### Modificato
- Domande frequenti riscritte sul metodo APPO.
- La ricerca nelle guide cerca anche nei titoli delle regole.
- Menu compatto anche sui tablet (fino a 820px).

### Rimosso
- Le guide modello "Primi passi" e "Guida di esempio passo-passo".

## [1.0.0] - 2026-10-13

### Aggiunto
- Sito statico **Guide APPO** nella cartella `guide/`: home, elenco guide con
  ricerca e filtro per categoria, pagina della singola guida, pagine
  "Il progetto" e "Contatti".
- Tema chiaro/scuro e layout responsive.
- Tre guide modello: "Primi passi", "Guida di esempio passo-passo",
  "Domande frequenti".
- Licenza Creative Commons BY-NC-ND 4.0, numero di versione nel piè di pagina.
- Pubblicazione automatica su GitHub Pages e creazione automatica delle release.

[Non rilasciato]: https://github.com/informaticaetica/one/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/informaticaetica/one/releases/tag/v1.1.0
[1.0.0]: https://github.com/informaticaetica/one/releases/tag/v1.0.0
