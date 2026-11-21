# statistiche.top

Archivio collaborativo di schede su persone e aziende degne di nota, con
recensioni per criteri, media bayesiana e passaggio a **eccellenze.org** delle
schede che superano una soglia pubblica di consenso. Il progetto completo è nel
documento `statistiche-top-progetto.md` (cartella Dropbox `appostatistiche`).

Il sito gira su **WordPress** con due pezzi sviluppati qui, separati:

- il **plugin** `statistiche-top`: schede, recensioni, moderazione, statistiche, pagine wiki, eccellenze.org;
- il **tema** `statistiche-top-tema`: l'aspetto grafico (font, colori, testata, tema chiaro/scuro).

## Cartella

```
statistiche.top/
├── tema/statistiche-top-tema/    il tema a blocchi (si copia in wp-content/themes/)
├── plugin/statistiche-top/       il plugin (si copia in wp-content/plugins/)
│   ├── statistiche-top.php       intestazione e avvio
│   ├── includes/
│   │   ├── class-punteggi.php    medie, media bayesiana, regola eccellenze.org (logica pura)
│   │   ├── class-regole.php      chi può recensire e quando (logica pura)
│   │   ├── class-schede.php      tipo di contenuto "scheda", settori, invio dal sito, storico
│   │   ├── class-voti.php        recensioni, limiti, impronta IP, ricalcolo
│   │   ├── class-segnalazioni.php segnalazioni e risposte motivate
│   │   ├── class-eccellenze.php  calcolo notturno, coda di verifica, API
│   │   ├── class-vista.php       pagina della scheda, moduli, metodologia, classifica
│   │   ├── class-amministrazione.php bacheca di moderazione e impostazioni
│   │   ├── class-impostazioni.php soglie e limiti modificabili
│   │   └── class-installazione.php tabelle, permessi, pianificazione
│   ├── assets/                   CSS (token per tema chiaro e scuro) e JS minimo
│   └── uninstall.php
├── tests/
│   ├── test-logica.php           collaudo della logica, senza WordPress
│   └── smoke-wordpress.php       collaudo su WordPress vero (in CI)
├── scripts/verifica_colori.py    contrasto WCAG e daltonismo della palette
└── CHANGELOG.md
```

## Verifiche prima di ogni commit

```sh
cd statistiche.top
php tests/test-logica.php             # punteggi, regole, soglie eccellenze.org
python3 scripts/verifica_colori.py    # contrasto anche con protanopia, deuteranopia, tritanopia
python3 scripts/verifica_tema.py      # colori del tema = token del plugin, modelli ben formati
find plugin tema -name '*.php' -exec php -l {} \;
```

La CI (`.github/workflows/statistiche.yml`) esegue le stesse verifiche con PHP 7.4
e 8.3, installa WordPress con SQLite, attiva il plugin, lancia
`tests/smoke-wordpress.php` e produce lo zip installabile.

## Cosa fa il plugin

| Funzione | Come |
|---|---|
| Schede | Tipo di contenuto `st_scheda` (indirizzo `/schede/nome/`), settori in `/settore/…`. Tipo, luogo, sintesi (max 500 caratteri), fonti obbligatorie, licenza dell'immagine, revisioni. |
| Proposta dal sito | Shortcode `[st_nuova_scheda]`: guida in 4 passi con recensione obbligatoria di chi propone (conta dopo la pubblicazione e i 7 giorni dell'account); massimo 3 schede in attesa per utente, controllo dei doppioni, campo trappola. I membri (`st_membro`) aggiungono schede pubblicate subito, anche senza recensione. |
| Categorie | Categoria libera scritta da chi propone; la redazione decide nella coda "Categorie da decidere" (stile wiki). |
| Recensioni | Guida in 3 passi: stelle da 1 a 5, titolo, rapporto con il soggetto, quando, frequenza, canale, voti sugli aspetti (o "Non so"), consiglio 0-10, ritorno, problemi, pro, contro, racconto. Una per utente e scheda, modificabile per 30 giorni. Voto "Utile", filtri e ordinamento. |
| Radiografia | Distribuzione delle stelle, media per aspetto, punti di forza e deboli, indice di raccomandazione, ritorno, problemi risolti, chi ha valutato. |
| Anti-manipolazione | Account di almeno 7 giorni e accesso in almeno 2 giorni diversi; massimo 10 recensioni in 24 ore; recensioni dallo stesso indirizzo di rete sulla stessa scheda sospese finché un moderatore non le guarda (si salva solo un'impronta cifrata dell'IP). |
| Conflitto di interesse | Chi crea la scheda, i referenti verificati e chi dichiara un legame non possono votare; la dichiarazione annulla un'eventuale recensione già data. |
| Punteggio | Media bayesiana delle stelle `(C × m + n × media) ÷ (C + n)`, con C = 20 e m = media del sito (3,5 finché ci sono meno di 50 recensioni). |
| eccellenze.org | Ogni notte: candidatura con ≥ 100 recensori unici e punteggio ≥ 4,5; revisione di uscita sotto 4,3. Il passaggio e l'uscita li decide sempre una persona dalla bacheca, con motivazione nello storico pubblico. |
| API | `GET /wp-json/statistiche-top/v1/eccellenze`: schede pubblicate su eccellenze.org, con link di ritorno. eccellenze.org le **copia**, l'originale resta qui. |
| Segnalazioni | Modulo su ogni scheda, anche senza account (con limite orario); tipi: errore, fonte, conflitto, rettifica/rimozione, altro; risposta motivata inviata per email. |
| Pagine | `[st_metodologia]` spiega regole e soglie con i valori attuali; `[st_classifica settore="slug" numero="20" minimo="10"]`. |
| Aspetto | Font Newsreader ospitato sul sito, palette avorio/ardesia, colonna stretta; interruttore del tema `[st_tema]` (automatico, chiaro, scuro; chiave `tema` in `localStorage`). Si spegne in Impostazioni. |
| Aiuti | Balloon "?" su ogni parte del modulo di proposta; senza JavaScript il testo resta visibile. |
| Indirizzi wiki | Ogni scheda pubblicata su `/nome-scheda/` con riquadro "In breve", indice, note e fonti, cronologia, "Proponi una correzione". I vecchi `/schede/…` reindirizzano. |
| Email | Conferma a chi propone quando la scheda è pubblicata, e a chi recensisce quando la recensione conta. |
| Registro | Chi ha fatto ogni proposta, recensione e segnalazione, con IP e browser; solo amministratori; cancellato dopo 90 giorni. |
| GDPR | Cancellando un utente si cancellano le sue recensioni; schede su persone solo se hanno un ruolo pubblico. |

Tutte le soglie si cambiano in **Schede → Impostazioni**.

## Messa in opera

1. Installare WordPress su statistiche.top (stesso hosting degli altri siti) e
   collegarlo a Jetpack, così si gestisce anche da qui.
2. Copiare `plugin/statistiche-top` in `wp-content/plugins/` (o caricare lo zip
   prodotto dalla CI) e attivarlo.
3. **Impostazioni → Generali**: spuntare "Chiunque può registrarsi", ruolo
   predefinito "Sottoscrittore". La registrazione di WordPress manda per email il
   link per scegliere la password: è la verifica dell'indirizzo.
4. **Impostazioni → Permalink**: "Nome articolo".
5. Creare i settori (Schede → Settori) e le pagine:
   - `metodologia` con `[st_metodologia]`
   - `proponi` con `[st_nuova_scheda]`
   - `classifica` con `[st_classifica]`
   - privacy e cookie (vedi sotto).
6. Verificare che il cron di WordPress giri davvero: meglio un cron di sistema che
   chiami `wp-cron.php` ogni 15 minuti e `define( 'DISABLE_WP_CRON', true );`.

### Plugin di terze parti consigliati

| Bisogno | Plugin | Note |
|---|---|---|
| Email affidabili (registrazione, risposte alle segnalazioni) | **WP Mail SMTP** | Indispensabile: senza SMTP le email di verifica finiscono nello spam. |
| Backup | **UpdraftPlus** o Jetpack VaultPress Backup | Copia giornaliera di database e file su spazio esterno. |
| Protezione accessi | **Jetpack** (già usato sugli altri siti) | Blocco dei tentativi di accesso a forza bruta, 2FA. |
| Captcha (facoltativo) | **Simple Cloudflare Turnstile** | Solo se il campo trappola e i limiti non bastano. È un servizio di terze parti: va aggiunto a privacy e cookie. |

Non servono plugin per recensioni, moduli o campi personalizzati: li sostituisce
il plugin di statistiche.top, che così evita dipendenze pesanti e mantiene le
regole anti-frode in un solo posto.

### Tema

Il plugin è pensato per **Twenty Twenty-Five**: `assets/aspetto.css` ne
ridefinisce le variabili di colore, font, dimensioni e larghezza, così l'aspetto
vale per tutte le pagine senza modificare i template. Per l'interruttore del
tema basta un blocco Shortcode con `[st_tema]` nell'header.

Il font Newsreader (Production Type, licenza SIL Open Font License 1.1, testo in
`assets/fonts/OFL-Newsreader.txt`) è ridotto ai caratteri latini e ospitato
nel plugin.

## Prima del lancio

- **Parere legale** sui punti della sezione 5 del progetto: GDPR, diffamazione
  (d.lgs. 70/2003 e Digital Services Act), recensioni false.
- Informativa privacy: account, recensioni (pubbliche con il nome visualizzato),
  segnalazioni, impronta cifrata dell'IP, email di servizio.
- Decisioni aperte del progetto ancora da chiudere: chi può contestare una scheda,
  schede su privati (oggi: no, solo figure pubbliche e aziende).
