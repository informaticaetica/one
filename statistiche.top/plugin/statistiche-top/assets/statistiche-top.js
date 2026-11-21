/* statistiche.top — aiuti a fumetto e piccoli aiuti ai moduli. Il tema chiaro/scuro
   è nel tema grafico (statistiche-top-tema). Senza JavaScript gli aiuti restano
   visibili sotto ogni campo. */
(function () {
  'use strict';
  // --- Aiuti a fumetto --------------------------------------------------------
  // Ogni aiuto ha un pulsante "?" (aria-expanded) che apre e chiude il balloon.
  // Esc o il pulsante × lo chiudono e riportano il focus sul "?". Un solo
  // balloon aperto alla volta. Il testo resta collegato al campo con
  // aria-describedby, quindi i lettori di schermo lo leggono comunque.
  var aperto = null;
  function chiudi(pulsante, focus) {
    var balloon = document.getElementById(pulsante.getAttribute('aria-controls'));
    pulsante.setAttribute('aria-expanded', 'false');
    if (balloon) { balloon.hidden = true; }
    if (focus) { pulsante.focus(); }
    if (aperto === pulsante) { aperto = null; }
  }
  function apri(pulsante) {
    if (aperto && aperto !== pulsante) { chiudi(aperto, false); }
    var balloon = document.getElementById(pulsante.getAttribute('aria-controls'));
    pulsante.setAttribute('aria-expanded', 'true');
    if (balloon) { balloon.hidden = false; }
    aperto = pulsante;
  }
  document.querySelectorAll('.st-aiuto-pulsante').forEach(function (pulsante) {
    var balloon = document.getElementById(pulsante.getAttribute('aria-controls'));
    if (!balloon) { return; }
    balloon.classList.add('st-aiuto--balloon');
    balloon.hidden = true;
    var x = document.createElement('button');
    x.type = 'button';
    x.className = 'st-aiuto-chiudi';
    x.setAttribute('aria-label', 'Chiudi l\'aiuto');
    x.innerHTML = '<span aria-hidden="true">×</span>';
    x.addEventListener('click', function () { chiudi(pulsante, true); });
    balloon.insertBefore(x, balloon.firstChild);
    pulsante.hidden = false;
    pulsante.addEventListener('click', function () {
      if (pulsante.getAttribute('aria-expanded') === 'true') { chiudi(pulsante, false); } else { apri(pulsante); }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && aperto) { chiudi(aperto, true); }
  });
  document.addEventListener('click', function (e) {
    if (!aperto) { return; }
    var balloon = document.getElementById(aperto.getAttribute('aria-controls'));
    if (!aperto.contains(e.target) && balloon && !balloon.contains(e.target)) { chiudi(aperto, false); }
  });

  // --- Messaggio di esito e contatore della sintesi ---------------------------
  var avviso = document.querySelector('[data-st-focus]');
  var titoloPasso = document.getElementById('st-passo-titolo');
  if (avviso) {
    avviso.focus();
  } else if (titoloPasso && /[?&]st_passo=/.test(window.location.search)) {
    // Nella guida a passi il focus va sul titolo del passo appena aperto.
    titoloPasso.focus();
  }

  document.querySelectorAll('textarea[data-st-contatore]').forEach(function (campo) {
    var uscita = document.getElementById(campo.getAttribute('data-st-contatore'));
    if (!uscita) { return; }
    var max = parseInt(campo.getAttribute('maxlength'), 10);
    var timer;
    function aggiorna() { uscita.textContent = campo.value.length + ' caratteri su ' + max; }
    campo.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(aggiorna, 400); });
    aggiorna();
  });
})();
