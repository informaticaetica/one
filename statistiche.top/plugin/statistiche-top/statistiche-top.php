<?php
/**
 * Plugin Name:       statistiche.top
 * Description:       Schede su persone, aziende e prodotti, recensioni umane moderate, statistiche di gradimento, pagine in stile wiki e passaggio a eccellenze.org. L'aspetto grafico è nel tema statistiche-top-tema.
 * Version:           0.5.3
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            statistiche.top
 * License:           GPL-2.0-or-later
 * Text Domain:       statistiche-top
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

const VERSIONE    = '0.5.3';
const VERSIONE_DB = '4';
const FILE        = __FILE__;

require_once __DIR__ . '/includes/class-punteggi.php';
require_once __DIR__ . '/includes/class-impostazioni.php';
require_once __DIR__ . '/includes/class-installazione.php';
require_once __DIR__ . '/includes/class-schede.php';
require_once __DIR__ . '/includes/class-voti.php';
require_once __DIR__ . '/includes/class-segnalazioni.php';
require_once __DIR__ . '/includes/class-eccellenze.php';
require_once __DIR__ . '/includes/class-vista.php';
require_once __DIR__ . '/includes/class-radiografia.php';
require_once __DIR__ . '/includes/class-campi.php';
require_once __DIR__ . '/includes/class-modulo.php';
require_once __DIR__ . '/includes/class-richieste.php';
require_once __DIR__ . '/includes/class-registro.php';
require_once __DIR__ . '/includes/class-notifiche.php';
require_once __DIR__ . '/includes/class-indirizzi.php';
require_once __DIR__ . '/includes/class-amministrazione.php';

register_activation_hook( __FILE__, array( Installazione::class, 'attiva' ) );
register_deactivation_hook( __FILE__, array( Installazione::class, 'disattiva' ) );

add_action(
	'plugins_loaded',
	static function () {
		Installazione::aggiorna_se_serve();
		Impostazioni::avvia();
		Schede::avvia();
		Voti::avvia();
		Segnalazioni::avvia();
		Eccellenze::avvia();
		Vista::avvia();
		Modulo::avvia();
		Richieste::avvia();
		Notifiche::avvia();
		Indirizzi::avvia();
		add_action( 'deleted_user', array( Registro::class, 'utente_cancellato' ) );
		if ( is_admin() ) {
			Amministrazione::avvia();
		}
	}
);
