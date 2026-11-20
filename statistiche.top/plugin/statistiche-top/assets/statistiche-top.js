/* statistiche.top — piccoli aiuti all'uso dei moduli. Il sito funziona anche senza JavaScript. */
(function () {
  'use strict';

  // Porta il focus sul messaggio di esito, così chi usa la tastiera o un lettore di schermo lo trova subito.
  var avviso = document.querySelector('[data-st-focus]');
  if (avviso) {
    avviso.focus();
  }

  // Contatore dei caratteri per la sintesi, annunciato con discrezione (aria-live="polite").
  document.querySelectorAll('textarea[data-st-contatore]').forEach(function (campo) {
    var uscita = document.getElementById(campo.getAttribute('data-st-contatore'));
    if (!uscita) {
      return;
    }
    var max = parseInt(campo.getAttribute('maxlength'), 10);
    var timer;
    function aggiorna() {
      uscita.textContent = campo.value.length + ' caratteri su ' + max;
    }
    campo.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(aggiorna, 400);
    });
    aggiorna();
  });
})();
