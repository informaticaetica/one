<?php
/**
 * Tabelle, ruoli e operazioni pianificate.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Installazione {

	const EVENTO_NOTTURNO = 'st_calcolo_notturno';

	public static function tabella_voti() {
		global $wpdb;
		return $wpdb->prefix . 'st_voti';
	}

	public static function tabella_segnalazioni() {
		global $wpdb;
		return $wpdb->prefix . 'st_segnalazioni';
	}

	public static function attiva() {
		self::crea_tabelle();
		Schede::registra_tipi();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( self::EVENTO_NOTTURNO ) ) {
			// Alle 3 di notte, ora del sito.
			$prossime_tre = strtotime( 'tomorrow 03:00', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			wp_schedule_event( $prossime_tre, 'daily', self::EVENTO_NOTTURNO );
		}
		self::aggiungi_capacita();
	}

	public static function disattiva() {
		wp_clear_scheduled_hook( self::EVENTO_NOTTURNO );
		flush_rewrite_rules();
	}

	public static function aggiorna_se_serve() {
		if ( get_option( 'st_versione_db' ) !== VERSIONE_DB ) {
			self::crea_tabelle();
			self::aggiungi_capacita();
		}
	}

	/** Gli amministratori e i redattori moderano schede, recensioni e segnalazioni. */
	private static function aggiungi_capacita() {
		foreach ( array( 'administrator', 'editor' ) as $nome ) {
			$ruolo = get_role( $nome );
			if ( $ruolo ) {
				$ruolo->add_cap( 'st_modera' );
			}
		}
	}

	private static function crea_tabelle() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$voti    = self::tabella_voti();
		$segn    = self::tabella_segnalazioni();

		// dbDelta vuole due spazi dopo PRIMARY KEY e una definizione per riga.
		dbDelta(
			"CREATE TABLE $voti (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scheda_id bigint(20) unsigned NOT NULL,
  utente_id bigint(20) unsigned NOT NULL,
  competenza tinyint(1) unsigned NOT NULL,
  affidabilita tinyint(1) unsigned NOT NULL,
  impatto tinyint(1) unsigned NOT NULL,
  trasparenza tinyint(1) unsigned NOT NULL,
  commento text NULL,
  stato varchar(20) NOT NULL DEFAULT 'valido',
  ip_hash char(64) NOT NULL DEFAULT '',
  creato datetime NOT NULL,
  modificato datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY scheda_utente (scheda_id,utente_id),
  KEY utente_creato (utente_id,creato),
  KEY scheda_stato (scheda_id,stato),
  KEY scheda_ip (scheda_id,ip_hash)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE $segn (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scheda_id bigint(20) unsigned NOT NULL,
  utente_id bigint(20) unsigned NULL,
  tipo varchar(20) NOT NULL,
  testo text NOT NULL,
  email varchar(190) NOT NULL DEFAULT '',
  stato varchar(20) NOT NULL DEFAULT 'aperta',
  risposta text NULL,
  creato datetime NOT NULL,
  chiuso datetime NULL,
  PRIMARY KEY  (id),
  KEY stato_creato (stato,creato),
  KEY scheda (scheda_id)
) $charset;"
		);

		update_option( 'st_versione_db', VERSIONE_DB );
	}
}
