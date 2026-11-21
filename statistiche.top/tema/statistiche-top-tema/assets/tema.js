/* statistiche.top — tema chiaro, scuro o automatico.
   Chiave "tema" in localStorage, valori "light" o "dark"; nessun valore = automatico
   (segue il dispositivo). La scelta vale per tutto il sito e per le visite successive,
   e si sincronizza fra le schede aperte. Senza JavaScript il tema segue il dispositivo. */
(function () {
  'use strict';
  var root = document.documentElement;
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

  applica(leggi());
  pulsanti.forEach(function (p) {
    p.addEventListener('click', function () {
      var nuovo = STATI[(STATI.indexOf(corrente()) + 1) % STATI.length];
      salva(nuovo);
      applica(nuovo);
    });
  });
  // Le altre schede aperte del sito si aggiornano subito.
  window.addEventListener('storage', function (e) {
    if (e.key === 'tema' || e.key === null) { applica(leggi()); }
  });
})();
