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

	public static function tabella_utili() {
		global $wpdb;
		return $wpdb->prefix . 'st_utili';
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
		$prima = get_option( 'st_versione_db' );
		if ( $prima !== VERSIONE_DB ) {
			self::crea_tabelle();
			self::aggiungi_capacita();
			if ( $prima && version_compare( $prima, '2', '<' ) ) {
				self::migra_a_2();
			}
		}
	}

	/**
	 * Gli amministratori e i redattori moderano schede, recensioni e segnalazioni.
	 * Il ruolo "Membro di statistiche.top" può aggiungere schede direttamente,
	 * senza la recensione obbligatoria e senza passare dalla revisione.
	 */
	private static function aggiungi_capacita() {
		foreach ( array( 'administrator', 'editor' ) as $nome ) {
			$ruolo = get_role( $nome );
			if ( $ruolo ) {
				$ruolo->add_cap( 'st_modera' );
				$ruolo->add_cap( 'st_aggiungi' );
			}
		}
		if ( ! get_role( 'st_membro' ) ) {
			add_role( 'st_membro', 'Membro di statistiche.top', array( 'read' => true, 'st_aggiungi' => true ) );
		}
	}

	/** Dalla versione 1 (quattro criteri) alla 2 (stelle complessive e aspetti). */
	private static function migra_a_2() {
		global $wpdb;
		$t = self::tabella_voti();
		foreach ( array( 'competenza', 'affidabilita', 'impatto', 'trasparenza' ) as $colonna ) {
			$wpdb->query( "ALTER TABLE $t MODIFY $colonna tinyint(1) unsigned NULL DEFAULT NULL" ); // phpcs:ignore WordPress.DB
		}
		$righe = $wpdb->get_results( "SELECT id, competenza, affidabilita, impatto, trasparenza FROM $t WHERE stelle IS NULL AND competenza IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( $righe as $r ) {
			$aspetti = array(
				'competenza'   => (int) $r['competenza'],
				'affidabilita' => (int) $r['affidabilita'],
				'impatto'      => (int) $r['impatto'],
				'trasparenza'  => (int) $r['trasparenza'],
			);
			$wpdb->update(
				$t,
				array( 'stelle' => (int) round( array_sum( $aspetti ) / 4 ), 'aspetti' => wp_json_encode( $aspetti ) ),
				array( 'id' => (int) $r['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
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
  stelle tinyint(1) unsigned NULL,
  titolo varchar(150) NOT NULL DEFAULT '',
  aspetti text NULL,
  relazione varchar(20) NOT NULL DEFAULT '',
  esperienza char(7) NOT NULL DEFAULT '',
  frequenza varchar(20) NOT NULL DEFAULT '',
  canale varchar(20) NOT NULL DEFAULT '',
  consiglia tinyint(2) unsigned NULL,
  tornerebbe tinyint(1) unsigned NULL,
  problema varchar(12) NOT NULL DEFAULT '',
  pro text NULL,
  contro text NULL,
  commento text NULL,
  da_proposta tinyint(1) unsigned NOT NULL DEFAULT 0,
  utili int(10) unsigned NOT NULL DEFAULT 0,
  competenza tinyint(1) unsigned NULL,
  affidabilita tinyint(1) unsigned NULL,
  impatto tinyint(1) unsigned NULL,
  trasparenza tinyint(1) unsigned NULL,
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

		$utili = self::tabella_utili();
		dbDelta(
			"CREATE TABLE $utili (
  voto_id bigint(20) unsigned NOT NULL,
  utente_id bigint(20) unsigned NOT NULL,
  creato datetime NOT NULL,
  PRIMARY KEY  (voto_id,utente_id),
  KEY utente (utente_id)
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
