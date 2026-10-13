/*
 * Contenuti del sito Guide APPO.
 * Per aggiungere una guida basta aggiungere un oggetto all'array GUIDE.
 * Il campo "contenuto" accetta HTML semplice (p, h2, ul, ol, li, strong, a, code).
 */
window.SITO = {
  nome: "Guide APPO",
  slogan: "Guide pratiche, chiare e sempre aggiornate.",
  intro:
    "Guide APPO raccoglie in un unico posto istruzioni passo-passo, risposte alle domande frequenti e materiali utili. Scegli un argomento e inizia.",
  progetto:
    "<p>Guide APPO nasce per rendere semplici e accessibili le informazioni che servono davvero. Ogni guida è scritta in linguaggio chiaro, divisa in passaggi brevi e verificata prima della pubblicazione.</p>" +
    "<h2>I nostri principi</h2><ul><li><strong>Chiarezza</strong>: niente gergo inutile.</li><li><strong>Accessibilità</strong>: leggibile da tutti, su ogni dispositivo.</li><li><strong>Aggiornamento</strong>: ogni guida riporta la data dell'ultima revisione.</li></ul>",
  email: "info@example.com", // TODO: sostituire con l'indirizzo reale
};

window.GUIDE = [
  {
    slug: "primi-passi",
    titolo: "Primi passi",
    categoria: "Introduzione",
    durata: "5 min",
    aggiornata: "2026-10-13",
    sommario: "Cos'è Guide APPO, come è organizzato il sito e come trovare rapidamente ciò che ti serve.",
    contenuto:
      "<p>Benvenuto! Questa guida ti spiega come muoverti tra i contenuti del sito.</p>" +
      "<h2>Come sono organizzate le guide</h2><p>Ogni guida appartiene a una <strong>categoria</strong> e indica il tempo di lettura stimato.</p>" +
      "<h2>Trovare una guida</h2><ol><li>Apri la pagina <a href=\"#/guide\">Guide</a>.</li><li>Scrivi una parola chiave nella casella di ricerca.</li><li>Filtra per categoria se necessario.</li></ol>",
  },
  {
    slug: "guida-di-esempio",
    titolo: "Guida di esempio passo-passo",
    categoria: "Procedure",
    durata: "8 min",
    aggiornata: "2026-10-13",
    sommario: "Un modello da duplicare per scrivere nuove guide con passaggi numerati e suggerimenti.",
    contenuto:
      "<p>Questa è una guida modello: sostituisci il testo con i tuoi contenuti.</p>" +
      "<h2>Prima di iniziare</h2><ul><li>Requisito 1</li><li>Requisito 2</li></ul>" +
      "<h2>Procedura</h2><ol><li>Primo passaggio.</li><li>Secondo passaggio.</li><li>Terzo passaggio.</li></ol>" +
      "<p class=\"nota\"><strong>Suggerimento:</strong> usa frasi brevi e un'azione per passaggio.</p>",
  },
  {
    slug: "domande-frequenti",
    titolo: "Domande frequenti",
    categoria: "Supporto",
    durata: "4 min",
    aggiornata: "2026-10-13",
    sommario: "Le risposte alle domande più comuni sul progetto e sull'uso delle guide.",
    contenuto:
      "<h2>Le guide sono gratuite?</h2><p>Sì, tutti i contenuti sono liberamente consultabili.</p>" +
      "<h2>Posso proporre una nuova guida?</h2><p>Certo: scrivici dalla pagina <a href=\"#/contatti\">Contatti</a>.</p>" +
      "<h2>Ho trovato un errore, cosa faccio?</h2><p>Segnalacelo indicando il titolo della guida e il passaggio interessato.</p>",
  },
];
