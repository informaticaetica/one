(function () {
  "use strict";
  var root = document.documentElement;

  var apri = document.querySelector(".apri-menu");
  var menu = document.getElementById("menu");
  apri.addEventListener("click", function () {
    var aperto = menu.classList.toggle("aperto");
    apri.setAttribute("aria-expanded", String(aperto));
  });

  document.querySelector(".cambia-tema").addEventListener("click", function () {
    var scuro = root.getAttribute("data-theme") === "dark" ||
      (!root.getAttribute("data-theme") && matchMedia("(prefers-color-scheme: dark)").matches);
    var nuovo = scuro ? "light" : "dark";
    root.setAttribute("data-theme", nuovo);
    try { localStorage.setItem("tema", nuovo); } catch (e) {}
  });

  document.querySelectorAll("[data-stampa]").forEach(function (b) {
    b.addEventListener("click", function () { window.print(); });
  });
})();
