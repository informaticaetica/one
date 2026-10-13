(function () {
  "use strict";
  var root = document.documentElement;

  // --- Menu su schermi stretti -------------------------------------------
  var apri = document.querySelector(".apri-menu");
  var menu = document.getElementById("menu");
  function chiudiMenu() {
    menu.classList.remove("aperto");
    apri.setAttribute("aria-expanded", "false");
  }
  apri.addEventListener("click", function () {
    var aperto = menu.classList.toggle("aperto");
    apri.setAttribute("aria-expanded", String(aperto));
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && menu.classList.contains("aperto")) {
      chiudiMenu();
      apri.focus();
    }
  });

  // --- Tema: automatico, chiaro, scuro ------------------------------------
  // La scelta resta nel browser (localStorage) e vale per tutto il sito e per
  // le visite successive, finché non la si cambia. "auto" segue il dispositivo.
  var STATI = ["auto", "light", "dark"];
  var NOMI = { auto: "automatico", light: "chiaro", dark: "scuro" };
  var ICONE = {
    auto: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
    light: '<circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></g>',
    dark: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z" fill="currentColor"/>'
  };
  var pulsante = document.querySelector(".cambia-tema");

  function leggi() {
    try {
      var t = localStorage.getItem("tema");
      return t === "light" || t === "dark" ? t : "auto";
    } catch (e) { return "auto"; }
  }
  function applica(stato) {
    if (stato === "auto") root.removeAttribute("data-theme");
    else root.setAttribute("data-theme", stato);
    var prossimo = STATI[(STATI.indexOf(stato) + 1) % STATI.length];
    pulsante.querySelector(".icona-tema").innerHTML = ICONE[stato];
    pulsante.querySelector(".etichetta-tema").textContent = "Tema: " + NOMI[stato];
    pulsante.setAttribute("aria-label", "Tema: " + NOMI[stato] + ". Premi per passare al tema " + NOMI[prossimo] + ".");
    pulsante.title = "Tema " + NOMI[stato] + " (premi per " + NOMI[prossimo] + ")";
  }
  function salva(stato) {
    try {
      if (stato === "auto") localStorage.removeItem("tema");
      else localStorage.setItem("tema", stato);
    } catch (e) { /* senza memoria del browser vale solo per questa pagina */ }
  }

  applica(leggi());
  pulsante.addEventListener("click", function () {
    var nuovo = STATI[(STATI.indexOf(leggiCorrente()) + 1) % STATI.length];
    salva(nuovo);
    applica(nuovo);
  });
  function leggiCorrente() {
    var t = root.getAttribute("data-theme");
    return t === "light" || t === "dark" ? t : "auto";
  }
  // Le altre schede aperte del sito si aggiornano subito
  window.addEventListener("storage", function (e) {
    if (e.key === "tema" || e.key === null) applica(leggi());
  });

  // --- Stampa delle schede -------------------------------------------------
  document.querySelectorAll("[data-stampa]").forEach(function (b) {
    b.addEventListener("click", function () { window.print(); });
  });
})();
