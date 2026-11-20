<?php
/**
 * Plugin Name:       statistiche.top
 * Description:       Schede su persone e aziende degne di nota, recensioni per criteri, media bayesiana, difesa dalle manipolazioni e passaggio a eccellenze.org con verifica manuale.
 * Version:           0.2.1
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

const VERSIONE    = '0.2.1';
const VERSIONE_DB = '1';
const FILE        = __FILE__;

require_once __DIR__ . '/includes/class-punteggi.php';
require_once __DIR__ . '/includes/class-regole.php';
require_once __DIR__ . '/includes/class-impostazioni.php';
require_once __DIR__ . '/includes/class-installazione.php';
require_once __DIR__ . '/includes/class-schede.php';
require_once __DIR__ . '/includes/class-voti.php';
require_once __DIR__ . '/includes/class-segnalazioni.php';
require_once __DIR__ . '/includes/class-eccellenze.php';
require_once __DIR__ . '/includes/class-vista.php';
require_once __DIR__ . '/includes/class-aspetto.php';
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
		Aspetto::avvia();
		if ( is_admin() ) {
			Amministrazione::avvia();
		}
	}
);
