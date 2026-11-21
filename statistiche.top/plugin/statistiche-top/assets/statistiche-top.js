/* statistiche.top — tema chiaro/scuro, aiuti a fumetto e piccoli aiuti ai moduli.
   Il sito funziona anche senza JavaScript: in quel caso gli aiuti restano visibili
   sotto ogni campo e il tema segue le impostazioni del dispositivo. */
(function () {
  'use strict';
  var root = document.documentElement;

  // --- Tema: automatico, chiaro, scuro ---------------------------------------
  // Stessa logica di miglioramento.com: chiave "tema" in localStorage, valori
  // "light" o "dark"; nessun valore = automatico (segue il dispositivo).
  var STATI = ['auto', 'light', 'dark'];
  var NOMI = { auto: 'automatico', light: 'chiaro', dark: 'scuro' };
  var ICONE = {
    auto: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="3"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
    light: '<circle cx="12" cy="12" r="5" fill="currentColor"/><g stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 1.5v2.5M12 20v2.5M1.5 12H4M20 12h2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8"/></g>',
    dark: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z" fill="currentColor"/>'
  };
  var pulsanti = document.querySelectorAll('[data-st-tema]');

  function leggi() {
    try {
      var t = localStorage.getItem('tema');
      return t === 'light' || t === 'dark' ? t : 'auto';
    } catch (e) { return 'auto'; }
  }
  function salva(stato) {
    try {
      if (stato === 'auto') { localStorage.removeItem('tema'); } else { localStorage.setItem('tema', stato); }
    } catch (e) { /* senza memoria del browser la scelta vale solo per questa pagina */ }
  }
  function applica(stato) {
    if (stato === 'auto') { root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme', stato); }
    var prossimo = STATI[(STATI.indexOf(stato) + 1) % STATI.length];
    pulsanti.forEach(function (p) {
      p.hidden = false;
      p.setAttribute('data-stato', stato);
      p.querySelector('.st-tema-icona').innerHTML = ICONE[stato];
      p.querySelector('.st-tema-etichetta').textContent = NOMI[stato].charAt(0).toUpperCase() + NOMI[stato].slice(1);
      p.setAttribute('aria-label', 'Tema: ' + NOMI[stato] + '. Premi per passare al tema ' + NOMI[prossimo] + '.');
      p.title = 'Tema ' + NOMI[stato] + ' (premi per ' + NOMI[prossimo] + ')';
    });
  }
  function corrente() {
    var t = root.getAttribute('data-theme');
    return t === 'light' || t === 'dark' ? t : 'auto';
  }
  if (pulsanti.length) {
    applica(leggi());
    pulsanti.forEach(function (p) {
      p.addEventListener('click', function () {
        var nuovo = STATI[(STATI.indexOf(corrente()) + 1) % STATI.length];
        salva(nuovo);
        applica(nuovo);
      });
    });
  }
  // Le altre schede aperte del sito si aggiornano subito.
  window.addEventListener('storage', function (e) {
    if (e.key === 'tema' || e.key === null) {
      var stato = leggi();
      if (pulsanti.length) { applica(stato); } else if (stato === 'auto') { root.removeAttribute('data-theme'); } else { root.setAttribute('data-theme', stato); }
    }
  });

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
