(function () {
  "use strict";

  var main = document.getElementById("main");
  var S = window.SITO;
  var G = window.GUIDE;

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

  function home() {
    document.title = S.nome;
    return (
      '<section class="hero"><h1>' + esc(S.nome) + '</h1><p class="lead">' + esc(S.slogan) + "</p>" +
      "<p>" + esc(S.intro) + '</p><p class="cta"><a class="btn" href="#/guide">Sfoglia le guide</a>' +
      ' <a class="btn ghost" href="#/progetto">Il progetto</a></p></section>' +
      '<section><h2 class="section-title">Guide in evidenza</h2><div class="grid">' +
      G.slice(0, 3).map(card).join("") + "</div></section>"
    );
  }

  function elenco() {
    document.title = "Guide — " + S.nome;
    var cats = G.map(function (g) { return g.categoria; }).filter(function (c, i, a) { return a.indexOf(c) === i; });
    return (
      '<h1>Tutte le guide</h1><div class="filters">' +
      '<input id="q" type="search" placeholder="Cerca una guida…" aria-label="Cerca una guida">' +
      '<select id="cat" aria-label="Categoria"><option value="">Tutte le categorie</option>' +
      cats.map(function (c) { return "<option>" + esc(c) + "</option>"; }).join("") +
      '</select></div><div id="risultati" class="grid"></div>'
    );
  }

  function filtra() {
    var q = document.getElementById("q").value.trim().toLowerCase();
    var cat = document.getElementById("cat").value;
    var out = G.filter(function (g) {
      var testo = (g.titolo + " " + g.sommario + " " + g.categoria).toLowerCase();
      return (!cat || g.categoria === cat) && (!q || testo.indexOf(q) !== -1);
    });
    document.getElementById("risultati").innerHTML = out.length
      ? out.map(card).join("")
      : '<p class="vuoto">Nessuna guida trovata.</p>';
  }

  function guida(slug) {
    var g = G.filter(function (x) { return x.slug === slug; })[0];
    if (!g) return nonTrovata();
    document.title = g.titolo + " — " + S.nome;
    return (
      '<article class="articolo"><p class="breadcrumb"><a href="#/guide">← Tutte le guide</a></p>' +
      '<span class="tag">' + esc(g.categoria) + "</span><h1>" + esc(g.titolo) + "</h1>" +
      '<p class="meta">' + esc(g.durata) + " di lettura · aggiornata il " + data(g.aggiornata) + "</p>" +
      '<div class="prosa">' + g.contenuto + "</div></article>"
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
    else if (parts[0] === "guide" && parts[1]) html = guida(decodeURIComponent(parts[1]));
    else if (parts[0] === "guide") html = elenco();
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
