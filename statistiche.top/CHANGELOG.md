# Changelog di statistiche.top

Versionamento semantico. Il plugin `statistiche-top` ha la versione nell'intestazione di
`plugin/statistiche-top/statistiche-top.php` e nella costante `VERSIONE`; il tema
`statistiche-top-tema` nell'intestazione di `tema/statistiche-top-tema/style.css`.

## [0.5.1] - 2026-11-21

### Tolto
- La registrazione dei giorni di accesso degli utenti, che serviva solo alla vecchia regola sull'attività: i dati già raccolti vengono cancellati all'aggiornamento (principio di minimizzazione del GDPR).

## [0.5.0] - 2026-11-21 (plugin) · tema 1.0.0

### Aggiunto
- **Tema separato** `statistiche-top-tema` (tema a blocchi autonomo): Newsreader ospitato sul sito, palette avorio/ardesia nei colori del tema, colonna di lettura di 38,75rem, testata con interruttore chiaro/scuro/automatico (`[st_tema]`, chiave `tema` in localStorage), piè di pagina, modelli per pagine, articoli, schede, archivi, ricerca e 404.
- **Modulo semplice** (`[st_recensisci]`, anche `[st_nuova_scheda]`), usabile senza account: tipo (persona, azienda, prodotto), nome, categoria libera, stelle da 1 a 5, recensione fino a 2000 caratteri, email obbligatoria, telefono facoltativo, dettagli facoltativi (rapporto con il soggetto, aspetti), due dichiarazioni.
- Dall'email si crea da solo un account leggero; un link di conferma (valido 7 giorni) verifica l'indirizzo; le recensioni mai confermate vengono cancellate di notte con le schede nate solo da loro.
- Se la scheda esiste già (stesso nome, anche scritto diversamente) la recensione va in coda al moderatore; altrimenti nasce una scheda nuova in revisione.
- Moderazione di ogni recensione: pagina "Recensioni da moderare" con testo, stelle, email, telefono, IP; "Approva e pubblica" o "Rifiuta".
- Ogni invio viene notificato alla redazione: email dell'amministratore e indirizzo "email_notifiche" (predefinito informaticaetica@gmail.com).
- **"Crea la tua scheda"** (`[st_crea_scheda]`): aziende, professionisti e produttori propongono la propria scheda o l'aggiornamento di quella esistente; la richiesta resta nel database (tipo st_richiesta), arriva per email con ricevuta a chi la invia; in bacheca si applica con un clic (crea o aggiorna la scheda, il richiedente diventa referente) o si scarta. Sulla scheda compare "Dati forniti dal soggetto", con partita IVA e anno di fondazione.
- Tipo di scheda **prodotto**, con i suoi aspetti.

### Modificato
- **Nomi brevi senza trattini**: statistiche.top/officinarossi; gli indirizzi con i trattini e quelli vecchi reindirizzano (301). Database alla versione 4 (telefono e codice di conferma; conversione dei nomi esistenti).
- La sezione della scheda "Scrivi una recensione" usa lo stesso modulo semplice.
- Metodologia riscritta: conferma dell'email e moderazione al posto delle regole di anzianità e attività.

### Tolto
- Dal plugin: font, colori del sito e interruttore del tema (ora nel tema), la guida a passi, le regole di anzianità dell'account e dei giorni di accesso.

## [0.4.0] - 2026-11-21

### Aggiunto
- Indirizzi in stile wiki: ogni scheda pubblicata si apre su `statistiche.top/nome-scheda/`; i vecchi indirizzi `/schede/nome-scheda/` reindirizzano in modo permanente; il nome breve di una scheda non può coincidere con una pagina del sito o con un indirizzo riservato (riceve un suffisso).
- Pagina della scheda come voce wiki: riquadro "In breve" (tipo, settore, luogo, sito, punteggio, recensioni, quanti lo consiglierebbero, ultima modifica), indice delle sezioni, "Note e fonti" numerate, "Cronologia delle modifiche", "Proponi una correzione" e nota finale sull'origine dei contenuti.
- Email di conferma all'indirizzo dell'account: quando la redazione pubblica una scheda proposta e quando una recensione diventa valida (subito, dopo il controllo della redazione o dopo i 7 giorni dell'account).
- Registro delle richieste (solo amministratori): chi ha proposto schede, scritto o modificato recensioni, inviato segnalazioni, con data, indirizzo IP, browser e numero di utenti per IP; filtri per utente, scheda e IP; cancellazione automatica dopo 90 giorni (impostazione "giorni_registro").

### Modificato
- Database alla versione 3 (tabella del registro).

## [0.3.0] - 2026-11-21

Nuovo sistema di recensioni sul modello dei grandi negozi online, pensato per fare la "radiografia" della qualità di un'azienda o di una persona con un ruolo pubblico.

### Aggiunto
- Guida a passi (al massimo 4): chi è, la tua esperienza, la radiografia, racconto e conferma. Funziona senza JavaScript, conserva i dati fra un passo e l'altro per un giorno, si può tornare indietro; indicatore "Passo N di M"; aiuti a fumetto su ogni parte.
- Chi propone una scheda deve recensirla: la sua recensione resta "in attesa" e conta quando la scheda è pubblicata e l'account ha almeno 7 giorni (promozione automatica alla pubblicazione e ogni notte).
- Ruolo "Membro di statistiche.top" (capacità `st_aggiungi`, anche per amministratori e redattori): aggiunge schede pubblicate subito, con o senza recensione.
- Categoria libera in stile wiki: chi propone scrive la categoria con parole sue (con i settori suggeriti); se coincide con un settore esistente viene assegnata, altrimenti va nella coda "Categorie da decidere", dove la redazione la collega, crea un settore nuovo o la scarta, con nota nello storico.
- Recensione: stelle complessive da 1 a 5, titolo, rapporto con il soggetto (dipendente, socio, familiare e concorrente bloccano la recensione), mese e anno, frequenza, canale, voti da 1 a 5 su 10 aspetti per le aziende e 6 per le persone (con "Non so", almeno 3 valutati), consiglio da 0 a 10, "ci torneresti", problemi risolti, cosa è piaciuto, cosa no, racconto.
- Pagina della scheda come "Statistiche di gradimento": punteggio ponderato, distribuzione delle stelle con filtro, indicatori (consiglio con convinzione e indice di raccomandazione, ritorno, problemi risolti), punti di forza e punti deboli, media per aspetto, chi ha valutato.
- Recensioni ordinabili per utilità o data, filtrabili per stelle e rapporto, con il voto "Utile" e il segno "Utente verificato (email)".
- Sito ufficiale della scheda.

### Modificato
- Il punteggio e la regola di eccellenze.org (100 recensori, valutazione globale di almeno 4,5) usano le stelle complessive.
- L'API per eccellenze.org restituisce distribuzione, aspetti e indice di raccomandazione al posto dei quattro criteri.
- Schema del database alla versione 2: le recensioni con i quattro criteri vengono convertite in stelle e aspetti.
- Metodologia riscritta per il nuovo sistema.

### Tolto
- Il modulo di recensione a pagina unica con i quattro criteri.

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
