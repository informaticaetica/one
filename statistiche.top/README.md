# statistiche.top

Archivio collaborativo di schede su persone e aziende degne di nota, con
recensioni per criteri, media bayesiana e passaggio a **eccellenze.org** delle
schede che superano una soglia pubblica di consenso. Il progetto completo è nel
documento `statistiche-top-progetto.md` (cartella Dropbox `appostatistiche`).

Il sito gira su **WordPress**. Tutta la logica sta nel plugin `statistiche-top`,
sviluppato qui; il resto sono WordPress, un tema accessibile e pochi plugin standard.

## Cartella

```
statistiche.top/
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
find plugin -name '*.php' -exec php -l {} \;
```

La CI (`.github/workflows/statistiche.yml`) esegue le stesse verifiche con PHP 7.4
e 8.3, installa WordPress con SQLite, attiva il plugin, lancia
`tests/smoke-wordpress.php` e produce lo zip installabile.

## Cosa fa il plugin

| Funzione | Come |
|---|---|
| Schede | Tipo di contenuto `st_scheda` (indirizzo `/schede/nome/`), settori in `/settore/…`. Tipo, luogo, sintesi (max 500 caratteri), fonti obbligatorie, licenza dell'immagine, revisioni. |
| Proposta dal sito | Shortcode `[st_nuova_scheda]`: solo utenti registrati, scheda in stato "in revisione", massimo 3 in attesa per utente, controllo dei doppioni, campo trappola contro i programmi automatici. |
| Recensioni | Voto da 1 a 5 su competenza, affidabilità, impatto, trasparenza; commento facoltativo; una sola per utente e scheda (vincolo nel database); modificabile per 30 giorni. |
| Anti-manipolazione | Account di almeno 7 giorni e accesso in almeno 2 giorni diversi; massimo 10 recensioni in 24 ore; recensioni dallo stesso indirizzo di rete sulla stessa scheda sospese finché un moderatore non le guarda (si salva solo un'impronta cifrata dell'IP). |
| Conflitto di interesse | Chi crea la scheda, i referenti verificati e chi dichiara un legame non possono votare; la dichiarazione annulla un'eventuale recensione già data. |
| Punteggio | Media bayesiana `(C × m + n × media) ÷ (C + n)`, con C = 20 e m = media del sito (3,5 finché ci sono meno di 50 recensioni). |
| eccellenze.org | Ogni notte: candidatura con ≥ 100 recensori unici e punteggio ≥ 4,5; revisione di uscita sotto 4,3. Il passaggio e l'uscita li decide sempre una persona dalla bacheca, con motivazione nello storico pubblico. |
| API | `GET /wp-json/statistiche-top/v1/eccellenze`: schede pubblicate su eccellenze.org, con link di ritorno. eccellenze.org le **copia**, l'originale resta qui. |
| Segnalazioni | Modulo su ogni scheda, anche senza account (con limite orario); tipi: errore, fonte, conflitto, rettifica/rimozione, altro; risposta motivata inviata per email. |
| Pagine | `[st_metodologia]` spiega regole e soglie con i valori attuali; `[st_classifica settore="slug" numero="20" minimo="10"]`. |
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

Un tema a blocchi leggero e accessibile (per esempio Twenty Twenty-Five). Il
plugin usa token di colore propri (`--st-*`) per tema chiaro e scuro e rispetta
`data-theme="dark"` / `"light"` su `<html>`, se il tema offre il selettore a tre stati.

## Prima del lancio

- **Parere legale** sui punti della sezione 5 del progetto: GDPR, diffamazione
  (d.lgs. 70/2003 e Digital Services Act), recensioni false.
- Informativa privacy: account, recensioni (pubbliche con il nome visualizzato),
  segnalazioni, impronta cifrata dell'IP, email di servizio.
- Decisioni aperte del progetto ancora da chiudere: chi può contestare una scheda,
  schede su privati (oggi: no, solo figure pubbliche e aziende).
