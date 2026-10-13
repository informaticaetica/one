/*
 * Contenuti del sito Guide APPO.
 * Per aggiungere una guida basta aggiungere un oggetto all'array GUIDE.
 * Il campo "contenuto" accetta HTML semplice (p, h2, ul, ol, li, strong, a, table).
 * Una guida può avere un elenco di "regole": ognuna ottiene una pagina dedicata
 * all'indirizzo #/guide/<slug-guida>/<numero-regola>.
 */
window.FONTE = {
  titolo: "Metodo APPO 2.0 — Osa Realizzarti Ora",
  autore: "Marco Costanzo",
  sito: "https://miglioramento.com",
};

window.SITO = {
  nome: "Guide APPO",
  slogan: "Quattro domande per mettere mezzo secondo fra l'impulso e la scelta.",
  versione: "1.1.0",
  email: "info@example.com", // TODO: sostituire con l'indirizzo reale

  // Le quattro parole del metodo, mostrate in home e in "Come iniziare"
  parole: [
    { lettera: "A", nome: "Aspirazione", domanda: "Dove voglio arrivare davvero?", testo: "Un risultato specifico, misurabile, con un tempo e un perché. Non un desiderio vago." },
    { lettera: "P", nome: "Problema", domanda: "Qual è l'ostacolo vero?", testo: "Non quello di comodo che ti assolve in anticipo, ma quello più vicino e più scomodo da ammettere." },
    { lettera: "P", nome: "Progetto", domanda: "Qual è il primo passo?", testo: "Chi fa cosa, entro quando. Meglio un'azione piccola mantenuta per mesi che dieci azioni ambiziose abbandonate." },
    { lettera: "O", nome: "Opportunità", domanda: "Cosa si è aperto?", testo: "Quello che vedi solo perché ti sei messo in movimento, anche dentro un evento che sembrava remarti contro." },
  ],

  progetto: `
    <p><strong>Guide APPO</strong> raccoglie guide pratiche per gestire la propria vita con il
    <strong>metodo APPO</strong>: Aspirazione, Problema, Progetto, Opportunità. Un sistema di
    problem solving personale nato da oltre venticinque anni di lavoro accanto a persone e piccole
    imprese reali, e affinato nel tempo secondo la logica del miglioramento continuo (Kaizen).</p>
    <h2>Da dove vengono i contenuti</h2>
    <p>Le guide sono tratte dal manuale <em>Metodo APPO 2.0 — Osa Realizzarti Ora</em> di Marco
    Costanzo. Articoli e approfondimenti sul metodo sono pubblicati sul sito dell'autore,
    <a href="https://miglioramento.com" rel="noopener">miglioramento.com</a>.</p>
    <h2>Come sono scritte le guide</h2>
    <ul>
      <li><strong>Brevi e operative</strong>: ogni guida si chiude con qualcosa da fare oggi.</li>
      <li><strong>Fonti dichiarate</strong>: quando si cita una ricerca, si indicano autore e anno.</li>
      <li><strong>Nessuna promessa miracolosa</strong>: il metodo non ti trasforma in trenta giorni;
      ti dà uno strumento abbastanza leggero da reggere anche nei giorni peggiori.</li>
    </ul>
    <h2>Un confine importante</h2>
    <p>Il metodo APPO è uno strumento di crescita personale, non una terapia. Se ti accorgi che
    l'agenda piena è un modo per non restare solo con i tuoi pensieri, o se stai attraversando un
    periodo di forte sofferenza, parlane con un professionista.</p>`,

  inizia: `
    <p class="lead">Se hai un minuto solo, leggi soltanto questa pagina. Contiene il cuore del metodo.</p>

    <h2>1. Le quattro parole</h2>
    <p><strong>Aspirazione</strong>: dove vuoi arrivare. <strong>Problema</strong>: cosa te lo impedisce,
    detto senza sconti. <strong>Progetto</strong>: il primo passo, piccolo, per superarlo.
    <strong>Opportunità</strong>: quello che si apre mentre cammini, e che vedi solo perché ti sei mosso.</p>
    <p class="nota">Un porto, uno scoglio, una remata, una corrente che non avevi previsto.</p>

    <h2>2. Quanto tempo ti chiede davvero</h2>
    <div class="tabella"><table>
      <thead><tr><th>Quando</th><th>Cosa fai</th><th>Tempo</th></tr></thead>
      <tbody>
        <tr><td>Ogni mattina</td><td>Una riga: qual è la cosa più importante di oggi</td><td>15 secondi</td></tr>
        <tr><td>Ogni sera</td><td>Una riga: cosa mi ha rallentato di più (oppure: cosa ho imparato oggi), più un voto da 1 a 10 alla giornata</td><td>15 secondi</td></tr>
        <tr><td>Una volta a settimana</td><td>Rileggi le sette coppie di righe e cerca cosa si ripete</td><td>5 minuti</td></tr>
        <tr><td>Una volta al mese</td><td>Tre domande sulla direzione generale</td><td>10 minuti</td></tr>
      </tbody>
    </table></div>
    <p>Totale: <strong>meno di un'ora al mese</strong>. Più sali di livello, meno tempo ti viene
    chiesto: è una scelta di progetto. Un metodo che chiede sempre di più muore soffocato.</p>

    <h2>3. I primi tre gesti</h2>
    <ol class="passi">
      <li><strong>Stasera, prima di dormire.</strong> Scrivi una riga: cosa ti ha rallentato oggi.
      Non deve essere bella. Deve solo esistere.</li>
      <li><strong>Domani mattina.</strong> Prima di guardare il telefono, scrivi un'altra riga: qual è
      la cosa più importante che vuoi vedere fatta entro sera.</li>
      <li><strong>Fra una settimana.</strong> Rileggi le sette coppie di righe. Cerca una parola o un
      tema che torna più volte: è il tuo punto di lavoro per la settimana che comincia.</li>
    </ol>

    <h2>4. Il piano della prima settimana</h2>
    <ul>
      <li><strong>Giorno 1</strong>: prima di dormire scrivi la tua prima riga della sera. Una sola. Anche brutta.</li>
      <li><strong>Giorni 2–4</strong>: aggiungi la riga del mattino e il voto da 1 a 10 alla giornata.</li>
      <li><strong>Giorni 5–7</strong>: continua le due righe, poi fermati cinque minuti e rileggi tutto.</li>
    </ul>
    <p>Alla fine avrai sette coppie di righe, sette numeri e un primo schema sotto gli occhi. Se dopo
    sette giorni non hai scritto nulla, il problema non è il metodo: lo stai leggendo come un romanzo.
    Torna al Giorno 1.</p>

    <h2>5. Cosa aspettarti</h2>
    <ul>
      <li><strong>Prima settimana</strong>: scrivere richiede ancora uno sforzo consapevole. Aiutati con un
      promemoria, un post-it vicino alla macchinetta del caffè o una persona che ti chieda se hai scritto.</li>
      <li><strong>Settimane 2–4</strong>: il momento più delicato. La novità è finita, l'automatismo non è
      ancora arrivato. Se vuoi mollare, non cambiare lo strumento: <a href="#/guide/produttivita/10">affilalo</a>.</li>
      <li><strong>Dopo il primo mese</strong>: per la maggior parte delle persone la pratica si regge quasi
      da sola, e le revisioni iniziano a restituire le prime vere scoperte.</li>
    </ul>

    <h2>6. Tre cose che il metodo non farà</h2>
    <ul>
      <li><strong>Non ti trasformerà in trenta giorni.</strong> Perché un comportamento diventi automatico
      servono in media circa due mesi, con differenze enormi da persona a persona.</li>
      <li><strong>Non ti darà motivazione a comando.</strong> È costruito per funzionare anche quando la
      motivazione non c'è: per questo gli strumenti sono così leggeri.</li>
      <li><strong>Non ti giudicherà.</strong> Salterai giorni e avrai settimane da 3 su 10. È previsto.</li>
    </ul>

    <h2>Scheda di riferimento rapido</h2>
    <div class="scheda">
      <p><strong>Ogni mattina</strong> — «Qual è la cosa più importante che voglio vedere realizzata, oggi, entro sera?»</p>
      <p><strong>Ogni sera</strong> — «Cosa, oggi, mi ha rallentato di più?» + un voto da 1 a 10.</p>
      <p><strong>Ogni settimana</strong> — Cinque minuti: quale riga si ripete più spesso?</p>
      <p><strong>Ogni mese</strong> — Quale Aspirazione è rimasta costante? Quale Problema si è ripresentato?
      Quale Opportunità è comparsa senza che tu l'avessi pianificata?</p>
      <p><strong>Sempre</strong> — Non cambiare lo strumento. Affilalo.</p>
    </div>

    <p class="cta"><a class="btn" href="#/guide/produttivita">Continua con la guida rapida alla produttività</a></p>`,
};

window.GUIDE = [
  {
    slug: "produttivita",
    titolo: "Guida rapida alla produttività",
    categoria: "Produttività",
    durata: "10 min",
    aggiornata: "2026-10-13",
    sommario:
      "Dieci regole per fare ogni giorno la cosa che conta, con un minuto di scrittura e senza dipendere dalla motivazione.",
    contenuto: `
      <p>Questa non è una guida per fare di più. È una guida per fare, ogni giorno, la cosa giusta, e
      per accorgerti di cosa ti rallenta prima che diventi un'abitudine. Le regole vengono dallo
      <strong>schema snello</strong> del metodo APPO: una riga al mattino, una alla sera, cinque minuti
      a settimana.</p>
      <p>Leggi l'elenco, poi apri una regola alla volta: ognuna ha la sua pagina con il perché, il
      come e un esempio concreto.</p>`,
    conclusione: `
      <h2>Se ricordi una cosa sola</h2>
      <p>Domani mattina, prima di guardare il telefono, scrivi: <em>qual è la cosa più importante che
      voglio vedere realizzata, oggi, entro sera?</em> Tutto il resto serve a farti fare questo gesto
      meglio e più a lungo.</p>
      <p>Non sai da dove partire? Vai a <a href="#/inizia">Come iniziare</a>.</p>`,
    regole: [
      {
        titolo: "Prima la riga, poi il telefono",
        sintesi: "Ogni mattina scrivi una riga sulla cosa più importante di oggi, prima di aprire notifiche ed email.",
        contenuto: `
          <p>La domanda del mattino è una sola: <strong>«Qual è la cosa più importante che voglio vedere
          realizzata, oggi, entro sera?»</strong> Una riga di risposta, scritta prima di qualunque altra cosa.</p>
          <h2>Perché funziona</h2>
          <p>Nel momento in cui apri il telefono prima di aver scritto la tua riga, hai già lasciato che
          sia il mondo esterno, e non tu, a scegliere la priorità della giornata. L'«attimo libero» in
          cui scriverla più tardi, nella grande maggioranza dei casi, non arriva mai, oppure arriva quando
          la giornata è già stata vissuta senza una direzione.</p>
          <p>È come il controllo che l'auto esegue quando giri la chiave: per un istante si accendono tutte
          le spie, poi si spengono e parti. La riga del mattino accende la tua attenzione, verifica la
          rotta e solo dopo ti lascia partire.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Tieni un taccuino o una nota sul telefono sempre nello stesso posto.</li>
            <li>Appena sveglio, prima delle notifiche, scrivi una riga. Anche in piedi.</li>
            <li>Non serve che sia bella: serve che esista.</li>
          </ol>
          <h2>Esempio</h2>
          <p>Chi lavora su turni non ha un «mattino» fisso. Per lui la regola non è «alle sette», ma
          «appena mi sveglio, prima di controllare il telefono», qualunque ora segni l'orologio.</p>`,
      },
      {
        titolo: "Una sola cosa: l'importante, non l'urgente",
        sintesi: "Scegli una cosa, non un elenco. E preferisci ciò che conta a ciò che grida più forte.",
        contenuto: `
          <p>La riga del mattino contiene <strong>una sola cosa</strong>. Se ne scrivi più di una, non hai
          ancora scelto la priorità vera.</p>
          <h2>Perché funziona</h2>
          <p>Un'email da chiudere entro sera è quasi sempre urgente, ma raramente è importante. Una
          telefonata che rimandi da settimane a una persona che conta per te è quasi sempre importante,
          ma non sembra mai urgente, perché nessuno te la chiede con una scadenza. Senza una scelta
          esplicita, la giornata viene assorbita da ciò che è solo urgente, e resta indietro proprio ciò
          che, se ignorato troppo a lungo, produce il rimpianto più grande.</p>
          <p>Scegliere una cosa non vuol dire trascurare il resto: continuerai a rispondere alle email e a
          fare il tuo lavoro. La riga aggiunge un <strong>filtro d'intenzione</strong>: fra le decine di
          cose che farai comunque, ce n'è una che proteggerai se la giornata minaccia di travolgerla.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Chiediti: quale cosa, se fatta entro sera, mi farà sentire la giornata riuscita?</li>
            <li>Se la giornata è decisa da altri, scegli la postura: «arrivare a ogni riunione con una domanda utile», «restare calmo anche se sarà stressante».</li>
            <li>Va bene anche un obiettivo piccolo e personale: «leggere dieci pagine», «chiamare un'amica».</li>
          </ol>
          <h2>Un segnale da osservare</h2>
          <p>Se la tua riga è quasi sempre la stessa, è un buon segno: hai trovato un'aspirazione che ti sta
          a cuore. Se ogni mattina scrivi qualcosa di completamente diverso, probabilmente stai elencando
          ciò che sembra urgente in quell'istante.</p>`,
      },
      {
        titolo: "Chiama per nome il problema vero",
        sintesi: "Il primo problema che dichiari è quasi sempre quello di comodo. Scava finché trovi quello che dipende da te.",
        contenuto: `
          <p>«Non ho tempo», «il mercato è cambiato», «il momento non è quello giusto»: il problema che
          dichiariamo per primo è quasi sempre <strong>di comodo</strong>, comodo perché non dipende da noi.
          Il problema vero è più vicino e più scomodo da ammettere.</p>
          <h2>Perché funziona</h2>
          <p>Finché il problema dichiarato resta quello comodo, nessun progetto può funzionare, perché sta
          cercando di risolvere l'ostacolo sbagliato. «Un problema ben definito è un problema per metà
          risolto»: la frase è spesso attribuita a John Dewey, ma più probabilmente risale all'ingegnere
          Charles Kettering.</p>
          <h2>Il caso del «non ho tempo»</h2>
          <p>Quando diciamo «non ho tempo», quasi sempre stiamo dicendo una di queste tre cose:</p>
          <ul>
            <li>«Non è una mia priorità.» Legittimo, ma allora diciamolo così.</li>
            <li>«Ho paura di scoprire qualcosa che non voglio vedere.» La più frequente, e la meno detta.</li>
            <li>«Non so da dove cominciare, quindi non comincio.»</li>
          </ul>
          <h2>Come applicarla</h2>
          <ol>
            <li>Scrivi il problema come ti viene.</li>
            <li>Chiediti: «Questo dipende da me?» Se no, scava ancora.</li>
            <li>Fermati quando trovi un ostacolo su cui puoi fare un primo passo.</li>
          </ol>
          <h2>Esempio</h2>
          <p>Chi rimanda da anni l'apertura di una piccola attività dice sempre «il momento non è quello
          giusto». Scavando emerge altro: la paura di essere giudicato se le cose andassero male. Non è il
          momento a non essere giusto: è la paura a non essere ancora stata affrontata.</p>`,
      },
      {
        titolo: "La sera, una riga sull'ostacolo",
        sintesi: "Chiudi la giornata scrivendo cosa ti ha rallentato di più, con il tono di un'osservazione, non di un'accusa.",
        contenuto: `
          <p>Se la domanda del mattino apre la direzione, quella della sera la verifica. Puoi porla in due
          modi, e funzionano entrambi:</p>
          <div class="tabella"><table>
            <thead><tr><th>Versione</th><th>Domanda</th><th>Quando conviene</th></tr></thead>
            <tbody>
              <tr><td>Originale</td><td>«Cosa ho imparato oggi?»</td><td>Se tendi a essere severo con te stesso</td></tr>
              <tr><td>Affilata</td><td>«Cosa, oggi, mi ha rallentato di più?»</td><td>Se vuoi andare dritto all'ostacolo</td></tr>
            </tbody>
          </table></div>
          <p>Provale una settimana ciascuna e tieni quella a cui rispondi con meno fatica.</p>
          <h2>Perché funziona</h2>
          <p>«È andata bene» o «è andata male» non ti servono a niente il giorno dopo. Una riga precisa
          trasforma una sensazione in un'informazione. E una volta che ti sei impegnato con una
          dichiarazione, anche minima, tendi a comportarti in modo coerente con essa: è il principio di
          coerenza documentato da Robert Cialdini, usato qui solo su te stesso, a viso aperto.</p>
          <h2>Il tono conta</h2>
          <p>Non stai chiedendo «cosa ho sbagliato», che è una domanda giudicante. Stai chiedendo «cosa mi
          ha rallentato», come si chiede a un meccanico da dove viene un rumore nel motore, senza dare la
          colpa all'auto. Un ostacolo non è una colpa: è un dato.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Scrivi quando sei ancora lucido: spesso subito dopo cena va meglio che a letto.</li>
            <li>Individua un ostacolo specifico, non una sensazione vaga come «giornata pesante».</li>
            <li>Scrivi anche, anzi soprattutto, nelle giornate peggiori: sono quelle che contengono più informazione.</li>
          </ol>`,
      },
      {
        titolo: "Un voto alla giornata, non alla produttività",
        sintesi: "Ogni sera dai alla giornata un numero da 1 a 10 sulla qualità della vita vissuta: il QuDiVi.",
        contenuto: `
          <p>Dopo la riga della sera, assegnati un numero da 1 a 10: il <strong>QuDiVi</strong>, Qualità
          della Vita. Non misura quanto hai fatto. Misura come hai vissuto la giornata.</p>
          <p>Puoi aver risposto a cinquanta email e chiuso tre pratiche urgenti, e finire esausto e vuoto:
          quella giornata può valere un 3. Puoi aver passato un pomeriggio a giocare con tuo figlio senza
          concludere nulla di «produttivo»: quella giornata può valere un 9.</p>
          <div class="tabella"><table>
            <thead><tr><th>Voto</th><th>Nome</th><th>Cosa significa</th></tr></thead>
            <tbody>
              <tr><td>1–3</td><td>Giornata subìta</td><td>Hai attraversato le ore senza sceglierle</td></tr>
              <tr><td>4–6</td><td>Giornata ordinaria</td><td>Qualcosa ha funzionato, qualcosa no</td></tr>
              <tr><td>7–9</td><td>Giornata scelta</td><td>La tua Aspirazione ha retto anche sotto pressione</td></tr>
              <tr><td>10</td><td>Giornata piena</td><td>Rara: annota cosa l'ha resa tale</td></tr>
            </tbody>
          </table></div>
          <h2>Tre regole per non falsare il dato</h2>
          <ol>
            <li>Assegna il numero <strong>prima</strong> di rileggere la riga appena scritta.</li>
            <li>Non è un voto su di te: un 3 dice solo che oggi è stata una giornata subìta.</li>
            <li>Non correggerlo il giorno dopo: un dato aggiustato diventa una narrazione.</li>
          </ol>
          <h2>Perché funziona</h2>
          <p>Un singolo numero non dice quasi nulla. Quattro settimane di numeri mostrano che i tuoi 8 e 9
          cadono negli stessi giorni, dopo le stesse azioni, con le stesse persone. Per vederlo basta la
          media della settimana: sette numeri, sommati e divisi per sette.</p>`,
      },
      {
        titolo: "Aggancia le righe a un gesto che fai già",
        sintesi: "Non decidere ogni giorno quando scrivere: lega la riga a un gesto quotidiano che non salti mai.",
        contenuto: `
          <p>Non lasciare che la domanda del mattino «capiti» quando ci pensi. <strong>Agganciala a un gesto
          che fai già</strong>: mentre il caffè si scalda, mentre sali sull'autobus, appena spegni il motore
          in parcheggio. Per la sera: lavarti i denti, mettere in carica il telefono, controllare le serrature.</p>
          <h2>Perché funziona</h2>
          <p>Lo psicologo Peter Gollwitzer ha chiamato <em>implementation intentions</em> i piani fatti nella
          forma «se succede X, allora faccio Y». Una meta-analisi su 94 studi (Gollwitzer e Sheeran, 2006)
          ne ha misurato un effetto medio-alto sul raggiungimento degli obiettivi. Se la decisione l'hai già
          presa una volta per tutte, non stai più decidendo: stai eseguendo. E nei giorni pieni è proprio
          l'energia per decidere che manca.</p>
          <h2>Come scegliere un buon aggancio</h2>
          <ul>
            <li><strong>Quotidiano senza eccezioni</strong>: accade tutti i giorni, non solo «di solito».</li>
            <li><strong>Sequenziale, non orario</strong>: «appena mi sveglio», non «alle sette».</li>
            <li><strong>Già esistente</strong>: un gesto che fai indipendentemente da APPO.</li>
          </ul>
          <h2>Come applicarla</h2>
          <ol>
            <li>Scegli un gesto per il mattino e uno per la sera.</li>
            <li>Scrivilo: «Quando verso il caffè, scrivo la mia riga».</li>
            <li>Dopo due settimane verifica se regge, e affilalo se serve.</li>
          </ol>
          <p class="nota">Trasloco, nuovo lavoro, viaggio? Scegli subito un aggancio provvisorio, anche
          imperfetto. È meglio di nessun aggancio in attesa di condizioni perfette.</p>`,
      },
      {
        titolo: "Proteggi un minuto, non un orario",
        sintesi: "Non serve alzarsi all'alba: serve un piccolo blocco di tempo protetto, sempre lo stesso.",
        contenuto: `
          <p>Il metodo ti chiede trenta secondi al mattino e trenta alla sera, più cinque minuti a
          settimana: <strong>meno di un'ora al mese</strong>. Quanti minuti hai passato ieri sul telefono
          senza uno scopo preciso? Se più di dodici, il tempo ce l'hai. Non lo stai usando per questo.</p>
          <h2>Sull'alzarsi presto</h2>
          <p>Ti hanno detto che le persone di successo si alzano alle cinque. La ricerca ha mappato oltre
          trecento geni legati all'essere mattinieri o nottambuli: il tuo cronotipo è in buona parte
          scritto nella biologia, non nella disciplina. Forzare un nottambulo ad alzarsi all'alba lo rende
          solo una persona che dorme male.</p>
          <p>Quello che le persone che ottengono risultati hanno in comune non è l'orario: è un
          <strong>blocco di tempo protetto</strong>, in cui nessun altro può entrare. L'orario è irrilevante.
          La protezione è tutto.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Individua un momento che esiste già e si ripete sempre: il caffè, il motore spento, i minuti prima di spegnere la luce.</li>
            <li>Non aggiungerlo alla giornata: attaccalo a quel momento.</li>
            <li>Difendilo come un appuntamento dal medico.</li>
          </ol>
          <p class="nota">Se ritagliarti anche cinque minuti ti sembra davvero impossibile, chiediti se non
          sia l'agenda piena a proteggerti dal fermarti. In quel caso il problema non è l'organizzazione, e
          vale la pena parlarne con un professionista.</p>`,
      },
      {
        titolo: "Tieni lo strumento leggero",
        sintesi: "Meno campi, più costanza. Nei giorni impossibili basta una parola; nei periodi difficili riduci, non abbandonare.",
        contenuto: `
          <p>La scheda giornaliera APPO contiene <strong>due righe</strong>: una al mattino, una alla sera.
          Niente sezione note, niente campi per l'umore, niente grafici. Ogni campo in più è un campo che,
          prima o poi, resterà vuoto, e una scheda con campi vuoti è la prima che si smette di compilare.</p>
          <h2>Perché funziona</h2>
          <ul>
            <li>Lo psicologo BJ Fogg (<em>Tiny Habits</em>, 2019) ha osservato che un'abitudine minuscola
            sopravvive più a lungo di un proposito ambizioso, perché richiede così poca motivazione da reggere
            anche nei giorni peggiori.</li>
            <li>Nel celebre esperimento delle marmellate (Iyengar e Lepper, 2000) comprava il 30% di chi
            trovava 6 varietà e solo il 3% di chi ne trovava 24: troppa scelta paralizza.</li>
            <li>La causa più comune di abbandono di un metodo non è la mancanza di volontà, ma il carico:
            lo strumento chiede più tempo di quello che la vita reale concede.</li>
          </ul>
          <h2>Come applicarla</h2>
          <ol>
            <li>Non aggiungere campi nei primi mesi, anche se sembrano utili (energia, gratitudine…).</li>
            <li>Nei giorni caotici scrivi anche una sola parola: mantiene viva la catena.</li>
            <li>Se in un periodo difficile anche due righe pesano troppo, tieni solo quella del mattino.
            Meglio una domanda su due per mesi che due su due abbandonate dopo una settimana.</li>
          </ol>
          <h2>Il formato giusto</h2>
          <p>Carta o telefono, scegli quello che ti fa scrivere più spesso. Tre criteri: deve stare in tasca,
          deve poterlo leggere solo tu (così scrivi con onestà), e quando lo apri devi sapere subito cosa fare.</p>`,
      },
      {
        titolo: "Cinque minuti a settimana: cerca la riga che si ripete",
        sintesi: "Rileggi le sette coppie di righe, sottolinea ciò che torna e trasformalo in un piccolo esperimento.",
        contenuto: `
          <p>Una volta a settimana, la domenica sera o il lunedì mattina, rileggi le sette righe del mattino
          e le sette della sera. Una sola domanda: <strong>quale riga si ripete più spesso?</strong></p>
          <h2>Perché funziona</h2>
          <p>Uno studio della Harvard Business School (Di Stefano, Gino, Pisano e Staats, 2014) ha confrontato
          due gruppi di neoassunti: chi dedicava gli ultimi quindici minuti della giornata a scrivere cosa
          aveva imparato ha ottenuto risultati del 23% migliori nel test finale rispetto a chi si era solo
          esercitato. Fermarsi a capire batte spesso il semplice accumulo di azione.</p>
          <p>Le righe quotidiane sono dati grezzi. È rileggerle insieme a trasformarle in informazione: una
          parola come «interruzioni» resta invisibile giorno per giorno e diventa ovvia vista tutta insieme.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Rileggi le sette coppie di righe della settimana.</li>
            <li>Con una matita sottolinea ogni parola o concetto che torna più di una volta.</li>
            <li>Trasforma il pattern in <strong>un piccolo esperimento</strong> per la settimana dopo
            (per esempio: un'ora al giorno senza notifiche).</li>
            <li>Se emergono più pattern, intervieni su <strong>uno solo</strong>; lascia l'altro sotto osservazione.</li>
          </ol>
          <h2>Il grazie della settimana</h2>
          <p>Aggiungi una riga di gratitudine con un nome e un fatto preciso: «Grazie a [nome], per [cosa]».
          La gratitudine specifica cambia le relazioni; quella generica non lascia traccia. Se puoi, dillo a voce.</p>`,
      },
      {
        titolo: "Non cambiare lo strumento: affilalo",
        sintesi: "Quando qualcosa si inceppa, correggi un dettaglio invece di ricominciare con un metodo nuovo. E dai tempo all'abitudine.",
        contenuto: `
          <p>Quando qualcosa non funziona, la tentazione è buttare tutto e provare un nuovo diario, una
          nuova app, un nuovo sistema. Il principio <strong>Kaizen</strong> (Masaaki Imai, 1986) dice il
          contrario: piccoli aggiustamenti costanti, non rivoluzioni. Prima di aggiungere qualcosa, cerca
          cosa puoi togliere.</p>
          <h2>Dai tempo all'abitudine</h2>
          <p>I famosi «21 giorni» per creare un'abitudine non hanno basi: la cifra nasce da osservazioni
          sull'adattamento dopo la chirurgia plastica. Lo studio di Phillippa Lally e colleghi (University
          College London, 2010) ha trovato una media di <strong>66 giorni</strong>, con una variabilità
          enorme: da 18 a 254. I comportamenti più semplici diventano automatici prima.</p>
          <p>Lo stesso studio mostra che <strong>saltare un giorno non compromette il processo</strong>.
          Il sistema perdona, se lo strumento resta leggero.</p>
          <h2>Il momento delicato</h2>
          <p>Fra la seconda e la quarta settimana la novità è finita e l'automatismo non è ancora arrivato:
          è lì che si concentra il rischio di abbandono. Saperlo in anticipo aiuta ad attraversarlo.</p>
          <h2>Come applicarla</h2>
          <ol>
            <li>Chiediti cosa ha funzionato meglio finora.</li>
            <li>Chiediti cosa ti ha fatto perdere più tempo del necessario.</li>
            <li>Scegli un solo piccolo aggiustamento per questa settimana, senza cambiare lo strumento nella sostanza.</li>
          </ol>
          <p class="nota">Se in passato hai abbandonato un metodo, questo non dice nulla sulla tua forza di
          volontà. Dice che quello strumento, in quel momento, chiedeva più di quanto potessi sostenere.
          La costanza non è un tratto di carattere: è l'effetto di uno strumento ben calibrato.</p>`,
      },
    ],
  },
  {
    slug: "domande-frequenti",
    titolo: "Domande frequenti",
    categoria: "Supporto",
    durata: "4 min",
    aggiornata: "2026-10-13",
    sommario: "Tempo, giorni saltati, carta o telefono: le risposte ai dubbi più comuni su chi inizia con APPO.",
    contenuto: `
      <h2>Cosa vuol dire APPO?</h2>
      <p>È l'acronimo di <strong>Aspirazione, Problema, Progetto, Opportunità</strong>: le quattro domande
      che, ogni giorno, mettono un gradino fra l'impulso e l'azione. Trovi tutto in
      <a href="#/inizia">Come iniziare</a>.</p>
      <h2>Non ho tempo. Funziona lo stesso?</h2>
      <p>Il metodo chiede un minuto al giorno e cinque minuti a settimana. Se hai davvero zero margine
      (due lavori, turni, un familiare da assistere), bastano le due righe quotidiane: la revisione
      settimanale è un potenziamento, non un obbligo. Vedi la regola
      <a href="#/guide/produttivita/7">Proteggi un minuto, non un orario</a>.</p>
      <h2>Ho saltato un giorno (o una settimana). Devo ricominciare da capo?</h2>
      <p>No. Saltare un giorno non compromette la formazione dell'abitudine. Riprendi dalla prossima riga,
      senza recuperare quelle perse.</p>
      <h2>Meglio carta o telefono?</h2>
      <p>Quello che ti fa scrivere più spesso. Conta che stia sempre con te e che possa leggerlo solo tu:
      una nota protetta da password o un taccuino personale.</p>
      <h2>Devo condividere le mie righe con qualcuno?</h2>
      <p>No. La pratica può restare del tutto privata, ed è uno dei suoi punti di forza: non richiede il
      consenso di nessuno per funzionare.</p>
      <h2>Posso proporre una nuova guida o segnalare un errore?</h2>
      <p>Certo: scrivici dalla pagina <a href="#/contatti">Contatti</a>.</p>`,
  },
];

// Aree della vita a cui sono dedicate le prossime guide (mostrate in home)
window.IN_ARRIVO = [
  "Lavoro e carriera",
  "Studio e formazione",
  "Salute e forma fisica",
  "Relazioni e famiglia",
  "Decisioni difficili e reazioni a caldo",
  "Finanze personali",
];
