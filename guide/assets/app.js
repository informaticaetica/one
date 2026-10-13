(function () {
  "use strict";

  var main = document.getElementById("main");
  var S = window.SITO;
  var G = window.GUIDE;
  var F = window.FONTE;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function data(iso) {
    return new Date(iso + "T00:00:00").toLocaleDateString("it-IT", { day: "numeric", month: "long", year: "numeric" });
  }

  function card(g) {
    return (
      '<a class="card" href="#/guide/' + esc(g.slug) + '">' +
      '<span class="tag">' + esc(g.categoria) + "</span>" +
      "<h3>" + esc(g.titolo) + "</h3>" +
      "<p>" + esc(g.sommario) + "</p>" +
      '<span class="meta">' + esc(g.durata) + " di lettura</span></a>"
    );
  }

  function trova(slug) {
    return G.filter(function (x) { return x.slug === slug; })[0];
  }

  function fonte() {
    return (
      '<p class="fonte">Tratto da <em>' + esc(F.titolo) + "</em> di " + esc(F.autore) +
      ' · <a href="' + esc(F.sito) + '" rel="noopener">' + esc(F.sito.replace(/^https?:\/\//, "")) + "</a></p>"
    );
  }

  function parole() {
    return '<div class="parole">' + S.parole.map(function (p) {
      return (
        '<div class="parola"><span class="lettera" aria-hidden="true">' + esc(p.lettera) + "</span>" +
        "<h3>" + esc(p.nome) + '</h3><p class="domanda">' + esc(p.domanda) + "</p>" +
        "<p>" + esc(p.testo) + "</p></div>"
      );
    }).join("") + "</div>";
  }

  function home() {
    document.title = S.nome + " — il metodo APPO per gestire la propria vita";
    var prod = trova("produttivita");
    return (
      '<section class="hero"><p class="occhiello">Il metodo APPO</p>' +
      "<h1>Guide per gestire la tua vita, una domanda alla volta</h1>" +
      '<p class="lead">' + esc(S.slogan) + "</p>" +
      "<p>Tutto, in questo sito, si basa sul <strong>metodo APPO</strong>: un sistema di problem solving " +
      "personale costruito su quattro parole, <strong>Aspirazione, Problema, Progetto, Opportunità</strong>. " +
      "Non ti chiede di diventare più motivato né di stravolgere le tue giornate: ti chiede una riga al " +
      "mattino, una alla sera e cinque minuti a settimana.</p>" +
      '<p class="cta"><a class="btn" href="#/inizia">Come iniziare</a>' +
      ' <a class="btn ghost" href="#/guide/produttivita">La guida rapida alla produttività</a></p></section>' +

      '<section><h2 class="section-title">Le quattro parole</h2>' +
      "<p class=\"intro-sezione\">Il nostro cervello è stato messo a punto per reagire in fretta a pericoli " +
      "immediati. Oggi quello stesso scatto si accende per un'email o per una risposta storta, e ci fa " +
      "compiere azioni che danneggiano gli altri e noi stessi. Le quattro domande di APPO mettono " +
      "<strong>mezzo secondo</strong> fra quello che senti e quello che fai: abbastanza per scegliere la mossa " +
      "che conviene a te e anche a chi hai davanti.</p>" + parole() + "</section>" +

      '<section><h2 class="section-title">Inizia da qui</h2><div class="grid">' +
      '<a class="card evidenza" href="#/inizia"><span class="tag">Primo passo</span>' +
      "<h3>Come iniziare con il metodo APPO</h3><p>Il metodo in sessanta secondi, il piano della prima " +
      'settimana e cosa aspettarti.</p><span class="meta">5 min di lettura</span></a>' +
      (prod ? card(prod) : "") + "</div></section>" +

      '<section><h2 class="section-title">Le prossime guide</h2>' +
      '<p class="intro-sezione">Le quattro parole funzionano su qualunque obiettivo: far crescere un\'attività, ' +
      "cambiare lavoro, riprendere gli studi, ricucire un rapporto, rimettere ordine nelle giornate. " +
      "Nuove guide si aggiungeranno nel tempo, dedicate a queste aree della vita:</p>" +
      '<ul class="arrivo">' + window.IN_ARRIVO.map(function (a) { return "<li>" + esc(a) + "</li>"; }).join("") +
      "</ul></section>"
    );
  }

  function elenco() {
    document.title = "Guide — " + S.nome;
    var cats = G.map(function (g) { return g.categoria; }).filter(function (c, i, a) { return a.indexOf(c) === i; });
    return (
      '<h1>Tutte le guide</h1><div class="filters">' +
      '<input id="q" type="search" placeholder="Cerca una guida o una regola…" aria-label="Cerca una guida">' +
      '<select id="cat" aria-label="Categoria"><option value="">Tutte le categorie</option>' +
      cats.map(function (c) { return "<option>" + esc(c) + "</option>"; }).join("") +
      '</select></div><div id="risultati" class="grid"></div>'
    );
  }

  function filtra() {
    var q = document.getElementById("q").value.trim().toLowerCase();
    var cat = document.getElementById("cat").value;
    var out = G.filter(function (g) {
      var regole = (g.regole || []).map(function (r) { return r.titolo + " " + r.sintesi; }).join(" ");
      var testo = (g.titolo + " " + g.sommario + " " + g.categoria + " " + regole).toLowerCase();
      return (!cat || g.categoria === cat) && (!q || testo.indexOf(q) !== -1);
    });
    document.getElementById("risultati").innerHTML = out.length
      ? out.map(card).join("")
      : '<p class="vuoto">Nessuna guida trovata.</p>';
  }

  function guida(slug) {
    var g = trova(slug);
    if (!g) return nonTrovata();
    document.title = g.titolo + " — " + S.nome;
    var regole = g.regole
      ? '<ol class="regole">' + g.regole.map(function (r, i) {
          return (
            '<li><a href="#/guide/' + esc(g.slug) + "/" + (i + 1) + '"><strong>' + esc(r.titolo) +
            "</strong><span>" + esc(r.sintesi) + "</span></a></li>"
          );
        }).join("") + "</ol>"
      : "";
    return (
      '<article class="articolo"><p class="breadcrumb"><a href="#/guide">← Tutte le guide</a></p>' +
      '<span class="tag">' + esc(g.categoria) + "</span><h1>" + esc(g.titolo) + "</h1>" +
      '<p class="meta">' + esc(g.durata) + " di lettura · aggiornata il " + data(g.aggiornata) + "</p>" +
      '<div class="prosa">' + g.contenuto + "</div>" + regole +
      (g.conclusione ? '<div class="prosa">' + g.conclusione + "</div>" : "") +
      (g.regole ? fonte() : "") + "</article>"
    );
  }

  function regola(slug, num) {
    var g = trova(slug);
    var n = parseInt(num, 10);
    if (!g || !g.regole || !(n >= 1 && n <= g.regole.length) || String(n) !== num) return nonTrovata();
    var r = g.regole[n - 1];
    var base = "#/guide/" + esc(g.slug) + "/";
    document.title = "Regola " + n + ": " + r.titolo + " — " + S.nome;
    return (
      '<article class="articolo"><p class="breadcrumb"><a href="#/guide/' + esc(g.slug) + '">← ' +
      esc(g.titolo) + "</a></p>" +
      '<span class="tag">Regola ' + n + " di " + g.regole.length + "</span><h1>" + esc(r.titolo) + "</h1>" +
      '<p class="lead">' + esc(r.sintesi) + "</p>" +
      '<div class="prosa">' + r.contenuto + "</div>" + fonte() +
      '<nav class="pager" aria-label="Altre regole">' +
      (n > 1 ? '<a class="prev" href="' + base + (n - 1) + '"><small>← Regola ' + (n - 1) + "</small>" +
        esc(g.regole[n - 2].titolo) + "</a>" : "<span></span>") +
      (n < g.regole.length ? '<a class="next" href="' + base + (n + 1) + '"><small>Regola ' + (n + 1) +
        " →</small>" + esc(g.regole[n].titolo) + "</a>"
        : '<a class="next" href="#/inizia"><small>E adesso →</small>Come iniziare</a>') +
      "</nav></article>"
    );
  }

  function inizia() {
    document.title = "Come iniziare con il metodo APPO — " + S.nome;
    return (
      '<article class="articolo"><span class="tag">Primo passo</span>' +
      "<h1>Come iniziare con il metodo APPO</h1>" +
      '<div class="prosa">' + S.inizia + "</div>" + fonte() + "</article>"
    );
  }

  function progetto() {
    document.title = "Il progetto — " + S.nome;
    return '<article class="articolo"><h1>Il progetto</h1><div class="prosa">' + S.progetto + "</div></article>";
  }

  function contatti() {
    document.title = "Contatti — " + S.nome;
    return (
      '<article class="articolo"><h1>Contatti</h1><div class="prosa">' +
      "<p>Hai una domanda, vuoi proporre una guida o segnalare un errore? Scrivici.</p></div>" +
      '<form id="contatto" class="form">' +
      '<label>Nome<input name="nome" required autocomplete="name"></label>' +
      '<label>Oggetto<input name="oggetto" required></label>' +
      '<label>Messaggio<textarea name="messaggio" rows="6" required></textarea></label>' +
      '<button class="btn" type="submit">Invia email</button></form></article>'
    );
  }

  function nonTrovata() {
    document.title = "Pagina non trovata — " + S.nome;
    return '<section class="hero"><h1>Pagina non trovata</h1><p><a class="btn" href="#/">Torna alla home</a></p></section>';
  }

  function route() {
    var parts = (location.hash.replace(/^#\/?/, "") || "").split("/");
    var html;
    if (!parts[0]) html = home();
    else if (parts[0] === "guide" && parts[1] && parts[2]) html = regola(decodeURIComponent(parts[1]), parts[2]);
    else if (parts[0] === "guide" && parts[1]) html = guida(decodeURIComponent(parts[1]));
    else if (parts[0] === "guide") html = elenco();
    else if (parts[0] === "inizia") html = inizia();
    else if (parts[0] === "progetto") html = progetto();
    else if (parts[0] === "contatti") html = contatti();
    else html = nonTrovata();
    main.innerHTML = html;

    if (document.getElementById("q")) {
      document.getElementById("q").addEventListener("input", filtra);
      document.getElementById("cat").addEventListener("change", filtra);
      filtra();
    }
    var form = document.getElementById("contatto");
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var f = form.elements;
        location.href = "mailto:" + S.email +
          "?subject=" + encodeURIComponent("[Guide APPO] " + f.oggetto.value) +
          "&body=" + encodeURIComponent(f.messaggio.value + "\n\n— " + f.nome.value);
      });
    }

    document.querySelectorAll(".nav a").forEach(function (a) {
      var h = a.getAttribute("href").slice(2);
      a.classList.toggle("attivo", h === "" ? !parts[0] : parts[0] === h);
    });
    document.getElementById("nav").classList.remove("aperto");
    document.querySelector(".nav-toggle").setAttribute("aria-expanded", "false");
    window.scrollTo(0, 0);
    main.focus({ preventScroll: true });
  }

  document.querySelector(".nav-toggle").addEventListener("click", function () {
    var nav = document.getElementById("nav");
    var open = nav.classList.toggle("aperto");
    this.setAttribute("aria-expanded", String(open));
  });

  var root = document.documentElement;
  try {
    var t = localStorage.getItem("tema");
    if (t) root.setAttribute("data-theme", t);
  } catch (e) {}
  document.querySelector(".theme-toggle").addEventListener("click", function () {
    var dark = root.getAttribute("data-theme") === "dark" ||
      (!root.getAttribute("data-theme") && matchMedia("(prefers-color-scheme: dark)").matches);
    var next = dark ? "light" : "dark";
    root.setAttribute("data-theme", next);
    try { localStorage.setItem("tema", next); } catch (e) {}
  });

  document.getElementById("year").textContent = new Date().getFullYear();
  document.getElementById("versione").textContent = S.versione;
  window.addEventListener("hashchange", route);
  route();
})();
