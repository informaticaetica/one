# Changelog di statistiche.top

Versionamento semantico. La versione è quella del plugin `statistiche-top`
(intestazione di `plugin/statistiche-top/statistiche-top.php` e costante `VERSIONE`).

## [0.2.2] - 2026-11-20

### Corretto
- Sulle schede non compare più il nome di chi le ha proposte: il modello del tema mostrava la riga "Scritto da … in …". Tolti anche, sulle schede, il blocco "Altri articoli" del blog e i commenti, che lasciavano grandi spazi vuoti.

## [0.2.1] - 2026-11-20

### Corretto
- I colori dei link e dei pulsanti valgono solo dentro il sito (`.wp-site-blocks`): il banner dei cookie, che resta chiaro anche nel tema scuro, aveva il link "Powered by" chiaro su bianco (contrasto insufficiente segnalato da axe-core).

## [0.2.0] - 2026-11-20

### Aggiunto
- Aspetto del sito sopra Twenty Twenty-Five: serif Newsreader ospitato sul sito (licenza OFL, nessuna richiesta a servizi esterni), testo a 1,25rem con interlinea 1,6, colonna di lettura di 38,75rem, fondo avorio e testo ardesia, link sottolineati.
- Interruttore del tema a tre stati (automatico, chiaro, scuro) con lo shortcode `[st_tema]`: scelta salvata in `localStorage` con la chiave `tema`, applicata prima del disegno della pagina e sincronizzata fra le schede aperte.
- Aiuti a fumetto su ogni parte del modulo "Proponi una scheda": pulsante "?" da 44×44 px, chiusura con `Esc`, con il pulsante × o con un clic fuori; senza JavaScript il testo resta visibile sotto il campo; collegati ai campi con `aria-describedby`.
- Impostazione "Aspetto del sito" per spegnere font, colori e interruttore e usare solo il tema.

### Modificato
- Palette dei token `--st-*` sui toni avorio e ardesia, con blu e ambra come accenti; contrasto verificato anche con daltonismo.
- Le descrizioni delle fonti nel modulo si chiamano "Che cosa conferma la fonte".

## [0.1.0] - 2026-11-20

Prima versione del plugin (MVP del progetto, punti 1–3 della roadmap e classifica di base).

### Aggiunto
- Tipo di contenuto "scheda" con settori, fonti obbligatorie, licenza dell'immagine, revisioni e storico pubblico delle modifiche.
- Proposta di schede dal sito (`[st_nuova_scheda]`) con revisione obbligatoria, controllo dei doppioni e campo trappola.
- Recensioni su quattro criteri (competenza, affidabilità, impatto, trasparenza), una per utente e scheda, modificabili per 30 giorni.
- Regole anti-manipolazione: anzianità e attività minime, limite giornaliero, sospensione delle recensioni da IP ripetuti, conflitto di interesse, referenti senza voto.
- Media bayesiana e calcolo notturno; regola eccellenze.org con verifica manuale, soglia di uscita più bassa e API pubblica.
- Segnalazioni con risposta motivata via email; moderazione dalla bacheca.
- Pagina metodologia (`[st_metodologia]`) e classifica (`[st_classifica]`).
- Palette accessibile (WCAG 2.2 AA, daltonismo) con tema chiaro e scuro; verifiche automatiche in CI.
