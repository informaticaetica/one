<?php
/**
 * Le recensioni: salvataggio, stati, regole, voti "utile" e ricalcolo della radiografia.
 *
 * Stati di una recensione:
 * - valido: conta nei punteggi;
 * - attesa: recensione di chi ha proposto la scheda, conta quando la scheda è
 *   pubblicata e l'account ha l'anzianità minima (promossa in automatico);
 * - sospeso: da controllare (stesso indirizzo di rete di un altro recensore);
 * - annullato: non conta più (conflitto di interesse, decisione della redazione).
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Voti {

	const MAX_TESTO  = 3000;
	const MAX_BREVE  = 600;
	const MAX_TITOLO = 120;
	const STATI      = array( 'valido', 'attesa', 'sospeso', 'annullato' );

	/** Che rapporto ha avuto chi recensisce con il soggetto. Le voci "conflitto" bloccano la recensione. */
	const RELAZIONI = array(
		'cliente'     => array( 'Cliente o utente dei suoi prodotti o servizi', false ),
		'fornitore'   => array( 'Fornitore', false ),
		'partner'     => array( 'Partner o collaboratore esterno occasionale', false ),
		'osservatore' => array( 'Ne seguo il lavoro pubblico (articoli, interventi, progetti)', false ),
		'altro'       => array( 'Altro rapporto, senza legami di interesse', false ),
		'dipendente'  => array( 'Dipendente o ex dipendente, socio, familiare', true ),
		'concorrente' => array( 'Concorrente diretto', true ),
	);

	const FREQUENZE = array(
		'una'        => 'Una volta',
		'qualche'    => 'Qualche volta',
		'abituale'   => 'Abitualmente',
	);

	const CANALI = array(
		'persona'  => 'Di persona',
		'online'   => 'Online',
		'telefono' => 'Al telefono',
		'altro'    => 'Altro',
	);

	const PROBLEMI = array(
		'nessuno'     => 'Non ci sono stati problemi',
		'risolto'     => 'C\'è stato un problema ed è stato risolto',
		'non_risolto' => 'C\'è stato un problema e non è stato risolto',
	);

	public static function avvia() {
		add_action( 'wp_login', array( __CLASS__, 'registra_giorno_accesso' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'registra_giorno_sessione' ) );
		add_action( 'admin_post_st_conflitto', array( __CLASS__, 'gestisci_conflitto' ) );
		add_action( 'admin_post_nopriv_st_conflitto', array( Schede::class, 'rimanda_al_login' ) );
		add_action( 'admin_post_st_utile', array( __CLASS__, 'gestisci_utile' ) );
		add_action( 'admin_post_nopriv_st_utile', array( Schede::class, 'rimanda_al_login' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'scheda_pubblicata' ), 20, 3 );
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

	/** Decodifica i campi strutturati di una riga. */
	public static function prepara( $riga ) {
		if ( ! $riga ) {
			return $riga;
		}
		$aspetti         = json_decode( (string) $riga['aspetti'], true );
		$riga['aspetti'] = is_array( $aspetti ) ? $aspetti : array();
		return $riga;
	}

	public static function recensione( $id ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return self::prepara( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ) );
	}

	public static function recensione_utente( $scheda_id, $utente_id ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return self::prepara( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE scheda_id = %d AND utente_id = %d", $scheda_id, $utente_id ), ARRAY_A ) );
	}

	/** Le recensioni che contano, nel formato di Punteggi::aggrega(). */
	public static function recensioni_valide( $scheda_id ) {
		global $wpdb;
		$t     = Installazione::tabella_voti();
		$righe = $wpdb->get_results(
			$wpdb->prepare( "SELECT utente_id, stelle, aspetti, consiglia, tornerebbe, problema, relazione FROM $t WHERE scheda_id = %d AND stato = 'valido' AND stelle IS NOT NULL", $scheda_id ),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'prepara' ), $righe );
	}

	/**
	 * Recensioni da mostrare sulla scheda, con filtri e ordinamento.
	 *
	 * @param array $filtri stelle (1-5), relazione (chiave), ordine ('utili' o 'recenti').
	 * @return array{0: array, 1: int} righe e totale.
	 */
	public static function recensioni_pubbliche( $scheda_id, array $filtri = array(), $limite = 10, $scarto = 0 ) {
		global $wpdb;
		$t     = Installazione::tabella_voti();
		$dove  = $wpdb->prepare( "scheda_id = %d AND stato = 'valido' AND stelle IS NOT NULL", $scheda_id );
		if ( ! empty( $filtri['stelle'] ) ) {
			$dove .= $wpdb->prepare( ' AND stelle = %d', (int) $filtri['stelle'] );
		}
		if ( ! empty( $filtri['relazione'] ) && isset( self::RELAZIONI[ $filtri['relazione'] ] ) ) {
			$dove .= $wpdb->prepare( ' AND relazione = %s', $filtri['relazione'] );
		}
		$ordine = ( isset( $filtri['ordine'] ) && 'recenti' === $filtri['ordine'] ) ? 'modificato DESC' : 'utili DESC, modificato DESC';
		$totale = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE $dove" ); // phpcs:ignore WordPress.DB
		$righe  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $dove ORDER BY $ordine LIMIT %d OFFSET %d", $limite, $scarto ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array( array_map( array( __CLASS__, 'prepara' ), $righe ), $totale );
	}

	/** Media generale di tutto il sito (stelle), calcolata di notte; con pochi dati si usa il valore a priori. */
	public static function media_generale() {
		$salvata = get_option( 'st_media_generale' );
		return is_numeric( $salvata ) ? (float) $salvata : (float) Impostazioni::valore( 'media_priori' );
	}

	public static function calcola_media_generale() {
		global $wpdb;
		$t    = Installazione::tabella_voti();
		$riga = $wpdb->get_row( "SELECT COUNT(*) AS n, AVG(stelle) AS m FROM $t WHERE stato = 'valido' AND stelle IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB
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
		$t      = Installazione::tabella_voti();
		$adesso = current_time( 'timestamp', true );

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
				'eta_account_giorni'      => self::eta_account( $utente_id ),
				'giorni_attivita'         => count( array_filter( (array) get_user_meta( $utente_id, 'st_giorni_accesso', true ) ) ),
				'recensioni_24h'          => $ultime_24h,
				'scheda_pubblicata'       => 'publish' === get_post_status( $scheda_id ),
				'giorni_dalla_recensione' => $giorni_dalla,
			),
			Schede::legami_utente( $scheda_id, $utente_id )
		);
	}

	public static function eta_account( $utente_id ) {
		$utente = get_userdata( $utente_id );
		return $utente ? (int) floor( ( current_time( 'timestamp', true ) - strtotime( $utente->user_registered . ' UTC' ) ) / DAY_IN_SECONDS ) : 0;
	}

	/**
	 * Impronta dell'indirizzo IP: non si salva l'IP, ma un'impronta con il sale
	 * del sito, che basta a riconoscere lo stesso indirizzo senza poterlo risalire.
	 */
	public static function impronta_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ) : '';
	}

	/* ---------- Scrittura ---------- */

	/**
	 * Salva (o aggiorna) la recensione di un utente su una scheda.
	 *
	 * @param array  $d       dati già validati dalla guida: stelle, titolo, aspetti, relazione,
	 *                        esperienza, frequenza, canale, consiglia, tornerebbe, problema,
	 *                        pro, contro, commento.
	 * @param string $stato   stato per una recensione nuova (per una modifica resta quello di prima,
	 *                        salvo 'attesa' che viene ricontrollato).
	 * @return string|false   stato finale, o false se il salvataggio non riesce.
	 */
	public static function salva( $scheda_id, $utente_id, array $d, $stato, $da_proposta = false ) {
		global $wpdb;
		$t         = Installazione::tabella_voti();
		$adesso    = current_time( 'mysql' );
		$esistente = self::recensione_utente( $scheda_id, $utente_id );
		$campi     = array(
			'stelle'     => (int) $d['stelle'],
			'titolo'     => (string) $d['titolo'],
			'aspetti'    => wp_json_encode( Punteggi::aspetti_valutati( (array) $d['aspetti'] ) ),
			'relazione'  => (string) $d['relazione'],
			'esperienza' => (string) $d['esperienza'],
			'frequenza'  => (string) $d['frequenza'],
			'canale'     => (string) $d['canale'],
			'consiglia'  => null === $d['consiglia'] ? null : (int) $d['consiglia'],
			'tornerebbe' => null === $d['tornerebbe'] ? null : (int) $d['tornerebbe'],
			'problema'   => (string) $d['problema'],
			'pro'        => (string) $d['pro'],
			'contro'     => (string) $d['contro'],
			'commento'   => (string) $d['commento'],
			'modificato' => $adesso,
		);

		if ( $esistente ) {
			$ok    = false !== $wpdb->update( $t, $campi, array( 'id' => (int) $esistente['id'] ) );
			$stato = $esistente['stato'];
		} else {
			$ok = (bool) $wpdb->insert(
				$t,
				array_merge(
					$campi,
					array(
						'scheda_id'   => $scheda_id,
						'utente_id'   => $utente_id,
						'stato'       => $stato,
						'da_proposta' => $da_proposta ? 1 : 0,
						'ip_hash'     => self::impronta_ip(),
						'creato'      => $adesso,
					)
				)
			);
		}
		if ( ! $ok ) {
			return false;
		}
		if ( 'attesa' === $stato ) {
			$stato = self::promuovi_in_attesa( $scheda_id ) ? 'valido' : 'attesa';
		}
		self::ricalcola_scheda( $scheda_id );
		return $stato;
	}

	/** Stato di una recensione nuova di un utente qualsiasi (non di chi propone). */
	public static function stato_nuova( $scheda_id, $utente_id ) {
		global $wpdb;
		$ip    = self::impronta_ip();
		$t     = Installazione::tabella_voti();
		$altri = $ip ? (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT utente_id) FROM $t WHERE scheda_id = %d AND ip_hash = %s AND utente_id <> %d", $scheda_id, $ip, $utente_id )
		) : 0;
		return Regole::stato_iniziale( $altri );
	}

	/**
	 * Le recensioni "in attesa" (di chi ha proposto la scheda) diventano valide quando
	 * la scheda è pubblicata e l'account ha l'anzianità minima. Gira alla pubblicazione
	 * della scheda, a ogni salvataggio e ogni notte.
	 *
	 * @return int quante recensioni sono state promosse.
	 */
	public static function promuovi_in_attesa( $scheda_id = 0 ) {
		global $wpdb;
		$t     = Installazione::tabella_voti();
		$dove  = $scheda_id ? $wpdb->prepare( ' AND scheda_id = %d', $scheda_id ) : '';
		$righe = $wpdb->get_results( "SELECT id, scheda_id, utente_id FROM $t WHERE stato = 'attesa'$dove", ARRAY_A ); // phpcs:ignore WordPress.DB
		$eta   = (int) Impostazioni::valore( 'eta_minima_giorni' );
		$fatte = 0;
		$da_ricalcolare = array();
		foreach ( $righe as $r ) {
			if ( 'publish' === get_post_status( (int) $r['scheda_id'] ) && self::eta_account( (int) $r['utente_id'] ) >= $eta ) {
				$wpdb->update( $t, array( 'stato' => 'valido' ), array( 'id' => (int) $r['id'] ), array( '%s' ), array( '%d' ) );
				$da_ricalcolare[ (int) $r['scheda_id'] ] = true;
				$fatte++;
			}
		}
		foreach ( array_keys( $da_ricalcolare ) as $s ) {
			self::ricalcola_scheda( $s );
		}
		return $fatte;
	}

	public static function scheda_pubblicata( $nuovo, $vecchio, $post ) {
		if ( Schede::TIPO === $post->post_type && 'publish' === $nuovo && 'publish' !== $vecchio ) {
			self::promuovi_in_attesa( $post->ID );
		}
	}

	/** L'utente dichiara un legame con il soggetto della scheda. */
	public static function gestisci_conflitto() {
		$scheda_id = isset( $_POST['st_scheda'] ) ? absint( $_POST['st_scheda'] ) : 0;
		$torna     = $scheda_id ? get_permalink( $scheda_id ) . '#st-recensisci' : home_url( '/' );
		if ( ! $scheda_id || ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_conflitto_' . $scheda_id ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida', 'st-recensisci' );
		}
		$utente_id = get_current_user_id();
		$conflitti = array_values( array_filter( array_map( 'intval', (array) get_user_meta( $utente_id, 'st_conflitti', true ) ) ) );
		if ( ! in_array( $scheda_id, $conflitti, true ) ) {
			$conflitti[] = $scheda_id;
			update_user_meta( $utente_id, 'st_conflitti', $conflitti );
			// Una recensione già lasciata da chi dichiara un legame non deve più contare.
			$esistente = self::recensione_utente( $scheda_id, $utente_id );
			if ( $esistente && in_array( $esistente['stato'], array( 'valido', 'attesa', 'sospeso' ), true ) ) {
				self::cambia_stato( (int) $esistente['id'], 'annullato' );
			}
		}
		Vista::reindirizza( $torna, 'ok', 'conflitto_registrato', 'st-recensisci' );
	}

	/* ---------- Voti "utile" ---------- */

	public static function ha_votato_utile( $voto_id, $utente_id ) {
		global $wpdb;
		$t = Installazione::tabella_utili();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $t WHERE voto_id = %d AND utente_id = %d", $voto_id, $utente_id ) );
	}

	/** Segna o toglie "utile" su una recensione. Non vale per le proprie recensioni. */
	public static function gestisci_utile() {
		$voto_id = isset( $_POST['st_voto'] ) ? absint( $_POST['st_voto'] ) : 0;
		$voto    = $voto_id ? self::recensione( $voto_id ) : null;
		$torna   = $voto ? get_permalink( (int) $voto['scheda_id'] ) : home_url( '/' );
		$torna   = add_query_arg( array_filter( array( 'st_ordine' => isset( $_POST['st_ordine'] ) ? sanitize_key( $_POST['st_ordine'] ) : '' ) ), $torna ) . '#st-recensione-' . $voto_id;
		if ( ! $voto || 'valido' !== $voto['stato'] || ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_utile_' . $voto_id ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida', 'st-recensioni' );
		}
		$utente_id = get_current_user_id();
		if ( (int) $voto['utente_id'] === $utente_id ) {
			Vista::reindirizza( $torna, 'errore', 'utile_propria', 'st-recensioni' );
		}
		global $wpdb;
		$tu = Installazione::tabella_utili();
		if ( self::ha_votato_utile( $voto_id, $utente_id ) ) {
			$wpdb->delete( $tu, array( 'voto_id' => $voto_id, 'utente_id' => $utente_id ), array( '%d', '%d' ) );
		} else {
			$wpdb->insert( $tu, array( 'voto_id' => $voto_id, 'utente_id' => $utente_id, 'creato' => current_time( 'mysql' ) ), array( '%d', '%d', '%s' ) );
		}
		$conta = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $tu WHERE voto_id = %d", $voto_id ) );
		$wpdb->update( Installazione::tabella_voti(), array( 'utili' => $conta ), array( 'id' => $voto_id ), array( '%d' ), array( '%d' ) );
		wp_safe_redirect( $torna );
		exit;
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
		return array_map( array( __CLASS__, 'prepara' ), $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE stato = 'sospeso' ORDER BY creato ASC LIMIT %d", $limite ), ARRAY_A ) );
	}

	/** Diritto alla cancellazione: quando un utente viene cancellato, spariscono anche le sue recensioni e i suoi voti "utile". */
	public static function utente_cancellato( $utente_id ) {
		global $wpdb;
		$t      = Installazione::tabella_voti();
		$tu     = Installazione::tabella_utili();
		$schede = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT scheda_id FROM $t WHERE utente_id = %d", $utente_id ) );
		$voti   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE utente_id = %d", $utente_id ) );
		foreach ( $voti as $v ) {
			$wpdb->delete( $tu, array( 'voto_id' => (int) $v ), array( '%d' ) );
		}
		$votati = $wpdb->get_col( $wpdb->prepare( "SELECT voto_id FROM $tu WHERE utente_id = %d", $utente_id ) );
		$wpdb->delete( $tu, array( 'utente_id' => $utente_id ), array( '%d' ) );
		foreach ( $votati as $v ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET utili = (SELECT COUNT(*) FROM $tu WHERE voto_id = %d) WHERE id = %d", $v, $v ) ); // phpcs:ignore WordPress.DB
		}
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
		$t = Installazione::tabella_voti();
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t WHERE scheda_id = %d", $post_id ) ) as $v ) {
			$wpdb->delete( Installazione::tabella_utili(), array( 'voto_id' => (int) $v ), array( '%d' ) );
		}
		$wpdb->delete( $t, array( 'scheda_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( Installazione::tabella_segnalazioni(), array( 'scheda_id' => $post_id ), array( '%d' ) );
	}
}
