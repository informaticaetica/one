# Changelog di statistiche.top

Versionamento semantico. La versione è quella del plugin `statistiche-top`
(intestazione di `plugin/statistiche-top/statistiche-top.php` e costante `VERSIONE`).

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
