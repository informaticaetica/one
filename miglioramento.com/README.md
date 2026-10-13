# miglioramento.com

Sorgenti del sito **miglioramento.com**: crescita personale con il metodo APPO.
Il sito si sviluppa qui e si pubblica su un server esterno.

## In breve

```sh
python3 build.py              # genera il sito in dist/
cd dist && python3 -m http.server 8000   # anteprima su http://localhost:8000
./deploy.sh                   # prova a secco del caricamento sul server
./deploy.sh --vai             # caricamento vero
```

Non serve installare nulla: basta Python 3.9 o successivo. Per pubblicare serve anche `rsync`.

Per un'anteprima senza server, aprendo i file con il doppio clic, genera con
`python3 build.py --relativi`: i link diventano relativi. Non usarla per il deploy;
`deploy.sh` rigenera comunque il sito con i link normali.

## Come è fatto

```
miglioramento.com/
├── sito.json            nome, URL, versione del sito, menu, dati del libro
├── CHANGELOG.md         storico delle versioni del sito
├── build.py             generatore: sorgenti → dist/
├── deploy.sh            pubblicazione di dist/ sul server (rsync su SSH)
├── modelli/pagina.html  intestazione, menu e piè di pagina comuni a tutte le pagine
├── statici/             CSS, JavaScript, icona
├── sezioni/
│   └── <sezione>/
│       ├── sezione.json   titolo, VERSIONE e data della sezione, elenco pagine
│       ├── CHANGELOG.md   storico della sezione
│       └── *.html         contenuto delle pagine (solo il corpo)
├── sezioni/libro/docbook/ il libro in DocBook 5 (libro.xml + capitoli/*.xml)
├── scripts/epub2docbook.py importazione del libro da EPUB a DocBook
└── dist/                sito generato (escluso da git)
```

Le pagine delle sezioni contengono solo il corpo. Intestazione, menu, avvertenza
e piè di pagina li aggiunge `build.py` dal modello. Nei testi puoi usare questi
segnaposto: `{{base}}`, `{{autore}}`, `{{email}}`, `{{libro_titolo}}`,
`{{libro_edizione}}`, `{{libro_prossima}}`, `{{amazon_bottone}}`.

**Collana APPO.** I libri già su Amazon si elencano in `sito.json` → `collana`, uno
per voce (`titolo`, `sottotitolo`, `descrizione`, `amazon_url`); la pagina
`/libro/collana/` si aggiorna da sola. Una voce senza link Amazon viene segnalata dal build.

Indirizzi: `sezioni/metodo/index.html` → `/metodo/`,
`sezioni/legale/privacy.html` → `/legale/privacy/`. La sezione `home` è la radice `/`.

### Sezioni iniziali

| Sezione | Indirizzo | Contenuto |
|---|---|---|
| Home | `/` | presentazione, le quattro parole, accessi rapidi |
| Il metodo | `/metodo/` | cos'è APPO, quadrato di Cipolla, livelli d'uso |
| Inizia qui | `/inizia/` | la prima settimana, giorno per giorno |
| Strumenti | `/strumenti/` | schede da stampare: giornaliera, settimanale, mensile |
| Articoli | `/articoli/` | approfondimenti con fonti (2 articoli iniziali) |
| Il libro | `/libro/`, `/libro/leggi/`, `/libro/collana/` | presentazione, lettura online dell'edizione 1.8, collana APPO su Amazon |
| Comunità | `/comunita/` | Comunità pensanti APPO, contatto WhatsApp dell'autore |
| L'autore | `/autore/` | biografia e contatti |
| Note legali | `/legale/` | disclaimer, privacy, cookie, diritti d'autore |

In più `build.py` genera `/versioni/`, `versioni.json`, `sitemap.xml`,
`robots.txt` e `404.html`.

## Versioning

Il versioning ha due livelli, entrambi in
[versionamento semantico](https://semver.org/lang/it/) `MAJOR.MINOR.PATCH`.

**Versione di sezione** (`sezioni/<nome>/sezione.json` + `CHANGELOG.md`).
Cambia solo quando cambia quella sezione:
- **PATCH** (1.0.0 → 1.0.1): refusi, correzioni, ritocchi;
- **MINOR** (1.0.0 → 1.1.0): nuove pagine o contenuti nuovi (per esempio un articolo);
- **MAJOR** (1.0.0 → 2.0.0): riorganizzazione, pagine spostate o eliminate.

**Versione del sito** (`sito.json` + `CHANGELOG.md` principale).
Cambia a ogni pubblicazione, e sale almeno quanto la modifica più grande fra le
sezioni pubblicate. Ogni pubblicazione ha un tag git `miglioramento-vX.Y.Z`.

**Edizione del libro** (`sito.json` → `libro.edizione` e `<edition>` in
`docbook/libro.xml`). È indipendente dalle versioni web: oggi l'edizione online
è la 1.8, mentre la 2.0 esce su Amazon. Il build si ferma se i due valori non coincidono.

`build.py` **controlla** tutto questo e si ferma se:
- una versione non è nel formato `X.Y.Z`;
- la versione in `sezione.json` non coincide con l'ultima voce del suo `CHANGELOG.md`;
- manca un file elencato.

Le versioni compaiono in fondo a ogni pagina e nella pagina `/versioni/`.

## Flusso di lavoro consigliato

1. **Modifica** su un branch: testi in `sezioni/`, stile in `statici/`.
2. **Annota**: alza la versione in `sezione.json` e aggiungi la voce nel
   `CHANGELOG.md` della sezione (stessa versione, data di oggi).
3. **Controlla in locale**: `python3 build.py` e anteprima in `dist/`.
4. **Pull request**: la CI (`.github/workflows/miglioramento.yml`) verifica
   versioni e libro e genera il sito, che puoi scaricare come artifact.
5. **Rilascio**: dopo l'unione in `main`, alza la versione in `sito.json`,
   aggiorna il `CHANGELOG.md` principale e crea il tag:
   `git tag miglioramento-v1.1.0 && git push origin miglioramento-v1.1.0`.
6. **Pubblica** dal tag: `./deploy.sh` per controllare, poi `./deploy.sh --vai`.

## Pubblicare sul server

Il sito generato è statico: va bene qualunque hosting che serva file HTML
(Apache, Nginx, hosting condiviso). Si carica il **contenuto** di `dist/` nella
cartella pubblica del dominio.

**Opzione consigliata, rsync su SSH (`deploy.sh`).** Trasferisce solo i file
cambiati e rimuove quelli obsoleti. Crea un file `.deploy.env` (escluso da git):

```sh
DEPLOY_DEST="utente@server.example:/var/www/miglioramento.com/"
DEPLOY_PORT=22
```

Lo script:
- rigenera il sito in modalità rigorosa (rifiuta segnaposto "DA COMPLETARE");
- avvisa se ci sono modifiche non salvate o manca il tag;
- senza `--vai` esegue solo una prova a secco.

**Hosting solo FTP/SFTP, senza SSH.** Genera il sito e carica `dist/` con un
client come FileZilla (abilita "elimina i file non presenti", oppure svuota
prima la cartella remota).

**Automatico da GitHub (passo successivo).** Si può aggiungere un job che, a
ogni tag `miglioramento-v*`, pubblica via SSH. Servono una chiave SSH dedicata
salvata nei *Secrets* del repository e l'accesso del server da GitHub.

Configurazioni utili sul server:
- servire `404.html` per le pagine inesistenti. Apache: `ErrorDocument 404 /404.html`;
  Nginx: `error_page 404 /404.html;`;
- forzare HTTPS e un solo dominio (con o senza `www`);
- cache lunga per `/statici/`, breve per le pagine HTML.

## Il libro in DocBook

Il libro *Metodo APPO 2.0 — Osa Realizzarti Ora* è in DocBook 5 in
`sezioni/libro/docbook/`:
- `libro.xml` contiene i metadati (`<info>`, con `<edition>1.8</edition>`), le
  parti e gli `xi:include` dei capitoli;
- `capitoli/*.xml` ha un file per prefazione, capitolo o appendice.

`build.py` lo trasforma in **una pagina HTML per capitolo** (`/libro/leggi/<id>/`),
con un indice generato e l'avviso sull'edizione in cima a ogni pagina.

- **Correggere il testo**: modifica i file in `capitoli/`, che sono la fonte ufficiale.
- **Validare** con lo schema ufficiale (lo fa anche la CI):
  `xmllint --noout --xinclude --relaxng /usr/share/xml/docbook/schema/rng/5.0/docbook.rng sezioni/libro/docbook/libro.xml`
  (pacchetti `docbook5-xml` e `libxml2-utils` su Debian/Ubuntu).
- **Altri formati**: gli stessi sorgenti funzionano con le catene DocBook
  standard (DocBook XSL, xsltproc, Apache FOP per il PDF).
- **Reimportare da un nuovo EPUB**: `python3 scripts/epub2docbook.py nuovo.epub sezioni/libro/docbook --edizione X.Y`.
  Attenzione: sovrascrive le correzioni fatte a mano nei capitoli.

## Accessibilità

Le regole complete sono in `CLAUDE.md`, nella radice del repository. In sintesi:
il sito deve rispettare le WCAG 2.2 AA in tema chiaro e scuro, con una palette
adatta ai daltonici e senza informazioni affidate al solo colore. Due controlli
automatici, eseguiti anche dalla CI:

- `python3 scripts/verifica_colori.py`: contrasto e daltonismo della palette;
- `node scripts/verifica_accessibilita.mjs http://localhost:8000` (dopo aver avviato
  l'anteprima di `dist/`): axe-core su tutte le pagine, in entrambi i temi, su
  desktop e telefono. Serve una volta `npm install --no-save playwright@1.47.2 axe-core@4.10.2`.

**Tema.** Il pulsante nella testata alterna *automatico* (segue il dispositivo),
*chiaro* e *scuro*. La scelta resta nel browser (`localStorage`, chiave `tema`),
vale per tutto il sito e per le visite successive, si aggiorna nelle altre schede
aperte e si cancella tornando ad *automatico*.

### Colori

`python3 scripts/verifica_colori.py` legge la palette da `statici/stile.css` e
controlla, per entrambi i temi, che ogni coppia testo/sfondo raggiunga il
contrasto WCAG AA (4,5:1) anche simulando protanopia, deuteranopia e tritanopia.
Lo esegue anche la CI. Se cambi un colore e il controllo fallisce, scurisci o
schiarisci quel token finché non passa. Inoltre nessuna informazione è affidata
al solo colore: i link nel testo sono sottolineati, la pagina corrente del menu
è in grassetto, i livelli dei capitoli hanno una lettera (F, G, S, M).

## Prima di andare online

`python3 build.py --rigoroso` deve terminare senza avvisi. Oggi mancano:

- [ ] dati del titolare in `sezioni/legale/index.html` e `sezioni/legale/privacy.html`
      (nome o ragione sociale, indirizzo, email, partita IVA se c'è un'attività economica);
- [ ] durata dei log e nome del fornitore di hosting in `privacy.html`;
- [ ] titoli, descrizioni e link Amazon dei libri della collana in `sito.json` → `collana`;
- [ ] biografia estesa in `sezioni/autore/index.html`;
- [ ] link Amazon dell'edizione 2.0 in `sito.json` → `libro.amazon_url`.

Da fare anche:
- [ ] verificare i testi legali con un professionista (sono una base di partenza);
- [ ] se aggiungi statistiche di visita o contenuti di terze parti (video, font
      esterni, social), aggiorna privacy e cookie e, se servono, il banner di consenso.
