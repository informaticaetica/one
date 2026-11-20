<?php
/**
 * Le recensioni: registrazione, regole di ammissione, ricalcolo dei punteggi.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Voti {

	const MAX_COMMENTO = 2000;
	const STATI        = array( 'valido', 'sospeso', 'annullato' );

	public static function avvia() {
		add_action( 'wp_login', array( __CLASS__, 'registra_giorno_accesso' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'registra_giorno_sessione' ) );
		add_action( 'admin_post_st_voto', array( __CLASS__, 'gestisci_voto' ) );
		add_action( 'admin_post_nopriv_st_voto', array( Schede::class, 'rimanda_al_login' ) );
		add_action( 'admin_post_st_conflitto', array( __CLASS__, 'gestisci_conflitto' ) );
		add_action( 'admin_post_nopriv_st_conflitto', array( Schede::class, 'rimanda_al_login' ) );
		add_action( 'deleted_user', array( __CLASS__, 'utente_cancellato' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'scheda_cancellata' ) );
	}

	/* ---------- Attività dell'utente ---------- */

	public static function registra_giorno_accesso( $login, $utente ) {
		self::segna_giorno( $utente->ID );
	}

	/** Chi resta connesso per giorni conta comunque come attivo nei giorni in cui visita il sito. */
	public static function registra_giorno_sessione() {
		if ( is_user_logged_in() ) {
			self::segna_giorno( get_current_user_id() );
		}
	}

	private static function segna_giorno( $utente_id ) {
		$oggi   = current_time( 'Y-m-d' );
		$giorni = (array) get_user_meta( $utente_id, 'st_giorni_accesso', true );
		if ( in_array( $oggi, $giorni, true ) ) {
			return;
		}
		$giorni[] = $oggi;
		$giorni   = array_values( array_filter( $giorni ) );
		update_user_meta( $utente_id, 'st_giorni_accesso', array_slice( $giorni, -60 ) );
	}

	/* ---------- Lettura ---------- */

	public static function recensione_utente( $scheda_id, $utente_id ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE scheda_id = %d AND utente_id = %d", $scheda_id, $utente_id ), ARRAY_A );
	}

	public static function recensioni_valide( $scheda_id ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT utente_id, competenza, affidabilita, impatto, trasparenza FROM $t WHERE scheda_id = %d AND stato = 'valido'", $scheda_id ),
			ARRAY_A
		);
	}

	/** Recensioni con commento da mostrare sulla scheda, le più recenti prima. */
	public static function recensioni_pubbliche( $scheda_id, $limite = 20, $scarto = 0 ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $t WHERE scheda_id = %d AND stato = 'valido' AND commento <> '' ORDER BY modificato DESC LIMIT %d OFFSET %d",
				$scheda_id,
				$limite,
				$scarto
			),
			ARRAY_A
		);
	}

	/** Media generale di tutto il sito, calcolata di notte; con pochi dati si usa il valore a priori. */
	public static function media_generale() {
		$salvata = get_option( 'st_media_generale' );
		return is_numeric( $salvata ) ? (float) $salvata : (float) Impostazioni::valore( 'media_priori' );
	}

	public static function calcola_media_generale() {
		global $wpdb;
		$t   = Installazione::tabella_voti();
		$riga = $wpdb->get_row( "SELECT COUNT(*) AS n, AVG((competenza + affidabilita + impatto + trasparenza) / 4) AS m FROM $t WHERE stato = 'valido'", ARRAY_A );
		$m    = ( $riga && (int) $riga['n'] >= 50 ) ? round( (float) $riga['m'], 4 ) : (float) Impostazioni::valore( 'media_priori' );
		update_option( 'st_media_generale', $m, false );
		return $m;
	}

	public static function ricalcola_scheda( $scheda_id ) {
		$agg = Punteggi::aggrega( self::recensioni_valide( $scheda_id ), self::media_generale(), (float) Impostazioni::valore( 'peso_bayes' ) );
		Schede::salva_aggregato( $scheda_id, $agg );
		return $agg;
	}

	/* ---------- Regole ---------- */

	/** Raccoglie i fatti che servono a Regole::puo_recensire(). */
	public static function fatti( $scheda_id, $utente_id, $recensione = null ) {
		global $wpdb;
		$utente = get_userdata( $utente_id );
		$t      = Installazione::tabella_voti();
		$adesso = current_time( 'timestamp', true );

		$eta = $utente ? floor( ( $adesso - strtotime( $utente->user_registered . ' UTC' ) ) / DAY_IN_SECONDS ) : 0;

		$giorni_dalla = null;
		if ( $recensione ) {
			$giorni_dalla = (int) floor( ( current_time( 'timestamp' ) - strtotime( $recensione['creato'] ) ) / DAY_IN_SECONDS );
		}

		$ultime_24h = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $t WHERE utente_id = %d AND creato >= %s",
				$utente_id,
				wp_date( 'Y-m-d H:i:s', $adesso - DAY_IN_SECONDS )
			)
		);

		return array_merge(
			array(
				'eta_account_giorni'      => (int) $eta,
				'giorni_attivita'         => count( array_filter( (array) get_user_meta( $utente_id, 'st_giorni_accesso', true ) ) ),
				'recensioni_24h'          => $ultime_24h,
				'scheda_pubblicata'       => 'publish' === get_post_status( $scheda_id ),
				'giorni_dalla_recensione' => $giorni_dalla,
			),
			Schede::legami_utente( $scheda_id, $utente_id )
		);
	}

	/**
	 * Impronta dell'indirizzo IP: non si salva l'IP, ma un'impronta con il sale
	 * del sito, che basta a riconoscere lo stesso indirizzo senza poterlo risalire.
	 */
	public static function impronta_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ) : '';
	}

	/* ---------- Registrazione di un voto ---------- */

	public static function gestisci_voto() {
		$scheda_id = isset( $_POST['st_scheda'] ) ? absint( $_POST['st_scheda'] ) : 0;
		$torna     = $scheda_id ? get_permalink( $scheda_id ) . '#st-recensisci' : home_url( '/' );

		if ( ! $scheda_id || get_post_type( $scheda_id ) !== Schede::TIPO
			|| ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_voto_' . $scheda_id ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida', 'st-recensisci' );
		}

		$utente_id  = get_current_user_id();
		$esistente  = self::recensione_utente( $scheda_id, $utente_id );
		$imp        = Impostazioni::tutte();

		if ( $esistente && 'annullato' === $esistente['stato'] ) {
			Vista::reindirizza( $torna, 'errore', 'recensione_annullata', 'st-recensisci' );
		}

		list( $ammesso, $motivo ) = Regole::puo_recensire( self::fatti( $scheda_id, $utente_id, $esistente ), $imp );
		if ( ! $ammesso ) {
			Vista::reindirizza( $torna, 'errore', $motivo, 'st-recensisci' );
		}

		$in   = wp_unslash( $_POST );
		$voti = Punteggi::normalizza_voti( isset( $in['st_voto'] ) ? (array) $in['st_voto'] : array() );
		if ( null === $voti ) {
			Vista::reindirizza( $torna, 'errore', 'voti_non_validi', 'st-recensisci' );
		}
		if ( empty( $in['st_nessun_conflitto'] ) ) {
			Vista::reindirizza( $torna, 'errore', 'dichiarazione', 'st-recensisci' );
		}
		$commento = isset( $in['st_commento'] ) ? sanitize_textarea_field( $in['st_commento'] ) : '';
		$commento = mb_substr( $commento, 0, self::MAX_COMMENTO );

		global $wpdb;
		$t      = Installazione::tabella_voti();
		$adesso = current_time( 'mysql' );
		$ip     = self::impronta_ip();

		if ( $esistente ) {
			$wpdb->update(
				$t,
				array_merge( $voti, array( 'commento' => $commento, 'modificato' => $adesso ) ),
				array( 'id' => (int) $esistente['id'] ),
				array( '%d', '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
			$esito = 'recensione_modificata';
		} else {
			$altri = $ip ? (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(DISTINCT utente_id) FROM $t WHERE scheda_id = %d AND ip_hash = %s AND utente_id <> %d", $scheda_id, $ip, $utente_id )
			) : 0;
			$stato = Regole::stato_iniziale( $altri );
			$ok    = $wpdb->insert(
				$t,
				array_merge(
					array( 'scheda_id' => $scheda_id, 'utente_id' => $utente_id ),
					$voti,
					array(
						'commento'   => $commento,
						'stato'      => $stato,
						'ip_hash'    => $ip,
						'creato'     => $adesso,
						'modificato' => $adesso,
					)
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( ! $ok ) {
				// Il vincolo UNIQUE impedisce il doppio voto anche con due invii simultanei.
				Vista::reindirizza( $torna, 'errore', 'salvataggio', 'st-recensisci' );
			}
			$esito = 'sospeso' === $stato ? 'recensione_sospesa' : 'recensione_salvata';
		}

		self::ricalcola_scheda( $scheda_id );
		Vista::reindirizza( $torna, 'ok', $esito, 'st-recensisci' );
	}

	/** L'utente dichiara (o ritira) un legame con il soggetto della scheda. */
	public static function gestisci_conflitto() {
		$scheda_id = isset( $_POST['st_scheda'] ) ? absint( $_POST['st_scheda'] ) : 0;
		$torna     = $scheda_id ? get_permalink( $scheda_id ) . '#st-recensisci' : home_url( '/' );
		if ( ! $scheda_id || ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_conflitto_' . $scheda_id ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida', 'st-recensisci' );
		}
		$utente_id = get_current_user_id();
		$conflitti = array_map( 'intval', (array) get_user_meta( $utente_id, 'st_conflitti', true ) );
		$conflitti = array_values( array_filter( $conflitti ) );
		if ( ! in_array( $scheda_id, $conflitti, true ) ) {
			$conflitti[] = $scheda_id;
			update_user_meta( $utente_id, 'st_conflitti', $conflitti );
			// Una recensione già lasciata da chi dichiara un legame non deve più contare.
			$esistente = self::recensione_utente( $scheda_id, $utente_id );
			if ( $esistente && 'valido' === $esistente['stato'] ) {
				self::cambia_stato( (int) $esistente['id'], 'annullato' );
			}
		}
		Vista::reindirizza( $torna, 'ok', 'conflitto_registrato', 'st-recensisci' );
	}

	/* ---------- Moderazione ---------- */

	public static function cambia_stato( $voto_id, $stato ) {
		global $wpdb;
		if ( ! in_array( $stato, self::STATI, true ) ) {
			return false;
		}
		$t      = Installazione::tabella_voti();
		$scheda = (int) $wpdb->get_var( $wpdb->prepare( "SELECT scheda_id FROM $t WHERE id = %d", $voto_id ) );
		if ( ! $scheda ) {
			return false;
		}
		$wpdb->update( $t, array( 'stato' => $stato ), array( 'id' => $voto_id ), array( '%s' ), array( '%d' ) );
		self::ricalcola_scheda( $scheda );
		return true;
	}

	public static function sospese( $limite = 50 ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE stato = 'sospeso' ORDER BY creato ASC LIMIT %d", $limite ), ARRAY_A );
	}

	/** Diritto alla cancellazione: quando un utente viene cancellato, spariscono anche le sue recensioni. */
	public static function utente_cancellato( $utente_id ) {
		global $wpdb;
		$t      = Installazione::tabella_voti();
		$schede = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT scheda_id FROM $t WHERE utente_id = %d", $utente_id ) );
		$wpdb->delete( $t, array( 'utente_id' => $utente_id ), array( '%d' ) );
		foreach ( $schede as $s ) {
			self::ricalcola_scheda( (int) $s );
		}
	}

	public static function scheda_cancellata( $post_id ) {
		if ( get_post_type( $post_id ) !== Schede::TIPO ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( Installazione::tabella_voti(), array( 'scheda_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( Installazione::tabella_segnalazioni(), array( 'scheda_id' => $post_id ), array( '%d' ) );
	}
}
