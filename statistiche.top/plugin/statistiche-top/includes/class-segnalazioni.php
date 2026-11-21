<?php
/**
 * Segnalazioni di errori, fonti mancanti e conflitti di interesse.
 * Chiunque può segnalare, anche senza account; ogni segnalazione riceve una risposta motivata.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Segnalazioni {

	const TIPI = array(
		'errore'    => 'Un dato è sbagliato',
		'fonte'     => 'Manca una fonte o la fonte non dice quello che è scritto',
		'conflitto' => 'Conflitto di interesse (recensioni o scheda di parte)',
		'rimozione' => 'Chiedo la rettifica o la rimozione (sono il soggetto o lo rappresento)',
		'altro'     => 'Altro',
	);
	const MAX_TESTO        = 2000;
	const MAX_ORARIE_PER_IP = 5;

	public static function avvia() {
		add_action( 'admin_post_st_segnalazione', array( __CLASS__, 'gestisci' ) );
		add_action( 'admin_post_nopriv_st_segnalazione', array( __CLASS__, 'gestisci' ) );
	}

	public static function gestisci() {
		$scheda_id = isset( $_POST['st_scheda'] ) ? absint( $_POST['st_scheda'] ) : 0;
		$torna     = $scheda_id ? get_permalink( $scheda_id ) . '#st-segnala' : home_url( '/' );

		if ( ! $scheda_id || get_post_type( $scheda_id ) !== Schede::TIPO || 'publish' !== get_post_status( $scheda_id )
			|| ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_segnala_' . $scheda_id )
			|| ! empty( $_POST['st_sito_web'] ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida', 'st-segnala' );
		}

		$chiave = 'st_segn_' . substr( Voti::impronta_ip(), 0, 32 );
		$conta  = (int) get_transient( $chiave );
		if ( $conta >= self::MAX_ORARIE_PER_IP ) {
			Vista::reindirizza( $torna, 'errore', 'troppe_segnalazioni', 'st-segnala' );
		}

		$in    = wp_unslash( $_POST );
		$tipo  = isset( $in['st_tipo_segnalazione'] ) ? sanitize_key( $in['st_tipo_segnalazione'] ) : '';
		$testo = isset( $in['st_testo'] ) ? sanitize_textarea_field( $in['st_testo'] ) : '';
		$email = isset( $in['st_email'] ) ? sanitize_email( $in['st_email'] ) : '';
		$utente_id = get_current_user_id();
		if ( ! $email && $utente_id ) {
			$email = wp_get_current_user()->user_email;
		}

		if ( ! isset( self::TIPI[ $tipo ] ) || mb_strlen( $testo ) < 10 ) {
			Vista::reindirizza( $torna, 'errore', 'segnalazione_incompleta', 'st-segnala' );
		}
		if ( ! empty( $in['st_email'] ) && ! is_email( $email ) ) {
			Vista::reindirizza( $torna, 'errore', 'email_non_valida', 'st-segnala' );
		}

		global $wpdb;
		$wpdb->insert(
			Installazione::tabella_segnalazioni(),
			array(
				'scheda_id' => $scheda_id,
				'utente_id' => $utente_id ? $utente_id : null,
				'tipo'      => $tipo,
				'testo'     => mb_substr( $testo, 0, self::MAX_TESTO ),
				'email'     => $email,
				'stato'     => 'aperta',
				'creato'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' ) // wpdb scrive NULL quando il valore è null.
		);
		set_transient( $chiave, $conta + 1, HOUR_IN_SECONDS );
		Registro::scrivi( 'segnalazione', $scheda_id );

		Schede::aggiungi_storico( $scheda_id, 'Ricevuta una segnalazione: ' . self::TIPI[ $tipo ] . '.' );
		wp_mail(
			get_option( 'admin_email' ),
			'[statistiche.top] Segnalazione su ' . get_the_title( $scheda_id ),
			self::TIPI[ $tipo ] . "\n\n" . $testo . "\n\n" . admin_url( 'admin.php?page=st-segnalazioni' )
		);

		Vista::reindirizza( $torna, 'ok', 'segnalazione_inviata', 'st-segnala' );
	}

	public static function elenco( $stato = 'aperta', $limite = 50 ) {
		global $wpdb;
		$t = Installazione::tabella_segnalazioni();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE stato = %s ORDER BY creato ASC LIMIT %d", $stato, $limite ), ARRAY_A );
	}

	public static function aperte_conteggio() {
		global $wpdb;
		$t = Installazione::tabella_segnalazioni();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE stato = 'aperta'" );
	}

	/** Chiude una segnalazione con una risposta motivata, inviata per email se c'è un indirizzo. */
	public static function rispondi( $id, $risposta ) {
		global $wpdb;
		$t    = Installazione::tabella_segnalazioni();
		$riga = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
		if ( ! $riga || '' === trim( $risposta ) ) {
			return false;
		}
		$wpdb->update(
			$t,
			array( 'stato' => 'chiusa', 'risposta' => $risposta, 'chiuso' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		Schede::aggiungi_storico( (int) $riga['scheda_id'], 'Segnalazione chiusa con risposta motivata.' );
		if ( $riga['email'] ) {
			wp_mail(
				$riga['email'],
				'Risposta alla tua segnalazione su ' . get_the_title( (int) $riga['scheda_id'] ),
				"Grazie per la segnalazione. Ecco la risposta della redazione:\n\n" . $risposta . "\n\n" . get_permalink( (int) $riga['scheda_id'] )
			);
		}
		return true;
	}
}
