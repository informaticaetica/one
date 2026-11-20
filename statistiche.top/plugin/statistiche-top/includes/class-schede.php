<?php
/**
 * Le schede: tipo di contenuto, settori, dati, invio dal sito e storico.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Schede {

	const TIPO       = 'st_scheda';
	const SETTORE    = 'st_settore';
	const MAX_SINTESI = 500;
	const MAX_FONTI  = 5;
	/** Schede in attesa di revisione che un utente può avere contemporaneamente. */
	const MAX_IN_ATTESA = 3;

	const TIPI_SOGGETTO = array(
		'persona' => 'Persona',
		'azienda' => 'Azienda',
	);

	public static function avvia() {
		add_action( 'init', array( __CLASS__, 'registra_tipi' ) );
		add_action( 'admin_post_st_nuova_scheda', array( __CLASS__, 'gestisci_invio' ) );
		add_action( 'admin_post_nopriv_st_nuova_scheda', array( __CLASS__, 'rimanda_al_login' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'registra_cambio_stato' ), 10, 3 );
	}

	public static function registra_tipi() {
		register_post_type(
			self::TIPO,
			array(
				'labels'       => array(
					'name'               => 'Schede',
					'singular_name'      => 'Scheda',
					'add_new'            => 'Aggiungi scheda',
					'add_new_item'       => 'Nuova scheda',
					'edit_item'          => 'Modifica scheda',
					'view_item'          => 'Vedi scheda',
					'search_items'       => 'Cerca schede',
					'not_found'          => 'Nessuna scheda trovata',
					'all_items'          => 'Tutte le schede',
					'menu_name'          => 'statistiche.top',
				),
				'public'       => true,
				'has_archive'  => 'schede',
				'rewrite'      => array( 'slug' => 'schede', 'with_front' => false ),
				'menu_icon'    => 'dashicons-chart-bar',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions', 'author' ),
				'show_in_rest' => true,
				'taxonomies'   => array( self::SETTORE ),
			)
		);

		register_taxonomy(
			self::SETTORE,
			self::TIPO,
			array(
				'labels'            => array(
					'name'          => 'Settori',
					'singular_name' => 'Settore',
					'add_new_item'  => 'Nuovo settore',
					'edit_item'     => 'Modifica settore',
					'search_items'  => 'Cerca settori',
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'settore', 'with_front' => false ),
			)
		);

		$pubblici = array(
			'_st_tipo'        => 'string',
			'_st_luogo'       => 'string',
			'_st_media_bayes' => 'number',
			'_st_n_recensori' => 'integer',
		);
		foreach ( $pubblici as $chiave => $tipo ) {
			register_post_meta(
				self::TIPO,
				$chiave,
				array(
					'type'          => $tipo,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function () {
						return current_user_can( 'st_modera' );
					},
				)
			);
		}
	}

	/* ---------- Lettura dei dati di una scheda ---------- */

	public static function dati( $id ) {
		$fonti = get_post_meta( $id, '_st_fonti', true );
		return array(
			'tipo'             => (string) get_post_meta( $id, '_st_tipo', true ),
			'luogo'            => (string) get_post_meta( $id, '_st_luogo', true ),
			'fonti'            => is_array( $fonti ) ? $fonti : array(),
			'figura_pubblica'  => (bool) get_post_meta( $id, '_st_figura_pubblica', true ),
			'licenza_immagine' => (string) get_post_meta( $id, '_st_licenza_immagine', true ),
			'referenti'        => array_map( 'intval', (array) get_post_meta( $id, '_st_referenti', true ) ),
			'settori'          => wp_get_post_terms( $id, self::SETTORE, array( 'fields' => 'names' ) ),
		);
	}

	public static function aggregato( $id ) {
		$criteri = get_post_meta( $id, '_st_criteri', true );
		return array(
			'media'        => (float) get_post_meta( $id, '_st_media', true ),
			'media_bayes'  => (float) get_post_meta( $id, '_st_media_bayes', true ),
			'n_recensioni' => (int) get_post_meta( $id, '_st_n_recensioni', true ),
			'n_recensori'  => (int) get_post_meta( $id, '_st_n_recensori', true ),
			'criteri'      => is_array( $criteri ) ? $criteri : array(),
		);
	}

	public static function salva_aggregato( $id, array $agg ) {
		update_post_meta( $id, '_st_media', $agg['media'] );
		update_post_meta( $id, '_st_media_bayes', $agg['media_bayes'] );
		update_post_meta( $id, '_st_n_recensioni', $agg['n_recensioni'] );
		update_post_meta( $id, '_st_n_recensori', $agg['n_recensori'] );
		update_post_meta( $id, '_st_criteri', $agg['criteri'] );
	}

	/* ---------- Storico delle modifiche ---------- */

	public static function aggiungi_storico( $id, $testo ) {
		$storico = get_post_meta( $id, '_st_storico', true );
		$storico = is_array( $storico ) ? $storico : array();
		$storico[] = array(
			'data'   => current_time( 'mysql' ),
			'utente' => get_current_user_id(),
			'testo'  => $testo,
		);
		// Teniamo le ultime 200 voci: bastano a ricostruire la storia di una scheda.
		update_post_meta( $id, '_st_storico', array_slice( $storico, -200 ) );
	}

	public static function storico( $id ) {
		$storico = get_post_meta( $id, '_st_storico', true );
		return is_array( $storico ) ? $storico : array();
	}

	public static function registra_cambio_stato( $nuovo, $vecchio, $post ) {
		if ( self::TIPO !== $post->post_type || $nuovo === $vecchio || 'auto-draft' === $nuovo ) {
			return;
		}
		$nomi = array(
			'publish' => 'pubblicata',
			'pending' => 'in revisione',
			'draft'   => 'bozza',
			'trash'   => 'cestinata',
			'private' => 'privata',
		);
		if ( isset( $nomi[ $nuovo ] ) && 'new' !== $vecchio ) {
			self::aggiungi_storico( $post->ID, 'Stato cambiato: ' . $nomi[ $nuovo ] . '.' );
		}
	}

	/* ---------- Invio di una scheda dal sito ---------- */

	public static function rimanda_al_login() {
		wp_safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Controlla i dati del modulo. Restituisce array( dati puliti, errori ).
	 * Gli errori sono indicizzati per nome del campo, per mostrarli accanto al campo giusto.
	 */
	public static function valida_invio( array $in ) {
		$errori = array();
		$d      = array();

		$d['tipo'] = isset( $in['st_tipo'] ) ? sanitize_key( $in['st_tipo'] ) : '';
		if ( ! isset( self::TIPI_SOGGETTO[ $d['tipo'] ] ) ) {
			$errori['st_tipo'] = 'Scegli se la scheda riguarda una persona o un\'azienda.';
		}

		$d['nome'] = isset( $in['st_nome'] ) ? sanitize_text_field( $in['st_nome'] ) : '';
		if ( mb_strlen( $d['nome'] ) < 2 || mb_strlen( $d['nome'] ) > 150 ) {
			$errori['st_nome'] = 'Scrivi il nome (da 2 a 150 caratteri).';
		}

		$d['settore'] = isset( $in['st_settore'] ) ? absint( $in['st_settore'] ) : 0;
		if ( ! $d['settore'] || ! term_exists( $d['settore'], self::SETTORE ) ) {
			$errori['st_settore'] = 'Scegli un settore dall\'elenco.';
		}

		$d['luogo'] = isset( $in['st_luogo'] ) ? sanitize_text_field( $in['st_luogo'] ) : '';
		if ( mb_strlen( $d['luogo'] ) > 100 ) {
			$errori['st_luogo'] = 'Il luogo può avere al massimo 100 caratteri.';
		}

		$d['sintesi'] = isset( $in['st_sintesi'] ) ? sanitize_textarea_field( $in['st_sintesi'] ) : '';
		$lunghezza    = mb_strlen( $d['sintesi'] );
		if ( $lunghezza < 30 || $lunghezza > self::MAX_SINTESI ) {
			$errori['st_sintesi'] = sprintf( 'La sintesi deve avere da 30 a %d caratteri (ora: %d).', self::MAX_SINTESI, $lunghezza );
		}

		$d['fonti'] = array();
		$url        = isset( $in['st_fonte_url'] ) ? (array) $in['st_fonte_url'] : array();
		$desc       = isset( $in['st_fonte_descrizione'] ) ? (array) $in['st_fonte_descrizione'] : array();
		foreach ( array_slice( $url, 0, self::MAX_FONTI, true ) as $i => $u ) {
			$u = trim( (string) $u );
			if ( '' === $u ) {
				continue;
			}
			$pulito = esc_url_raw( $u, array( 'http', 'https' ) );
			if ( ! $pulito || ! wp_http_validate_url( $pulito ) ) {
				$errori['st_fonte_url'] = 'Controlla gli indirizzi delle fonti: devono iniziare con https:// o http://.';
				continue;
			}
			$d['fonti'][] = array(
				'url'         => $pulito,
				'descrizione' => isset( $desc[ $i ] ) ? sanitize_text_field( $desc[ $i ] ) : '',
			);
		}
		if ( ! $d['fonti'] && ! isset( $errori['st_fonte_url'] ) ) {
			$errori['st_fonte_url'] = 'Indica almeno una fonte verificabile (sito ufficiale, articolo, bilancio, registro imprese).';
		}

		$d['figura_pubblica'] = ! empty( $in['st_figura_pubblica'] );
		if ( 'persona' === $d['tipo'] && ! $d['figura_pubblica'] ) {
			$errori['st_figura_pubblica'] = 'Si possono creare schede solo su persone che hanno un ruolo pubblico.';
		}

		if ( empty( $in['st_nessun_conflitto'] ) ) {
			$errori['st_nessun_conflitto'] = 'Conferma di non essere il soggetto della scheda né un suo collaboratore.';
		}

		return array( $d, $errori );
	}

	public static function gestisci_invio() {
		$torna = Vista::url_ritorno();

		if ( ! isset( $_POST['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_nonce'] ), 'st_nuova_scheda' ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida' );
		}
		// Campo trappola: le persone non lo vedono, i programmi automatici sì.
		if ( ! empty( $_POST['st_sito_web'] ) ) {
			Vista::reindirizza( $torna, 'errore', 'richiesta_non_valida' );
		}

		$utente = get_current_user_id();
		$attesa = new \WP_Query(
			array(
				'post_type'      => self::TIPO,
				'post_status'    => 'pending',
				'author'         => $utente,
				'fields'         => 'ids',
				'posts_per_page' => self::MAX_IN_ATTESA,
				'no_found_rows'  => true,
			)
		);
		if ( count( $attesa->posts ) >= self::MAX_IN_ATTESA ) {
			Vista::reindirizza( $torna, 'errore', 'troppe_in_attesa' );
		}

		list( $d, $errori ) = self::valida_invio( wp_unslash( $_POST ) );

		if ( ! $errori ) {
			$doppione = self::cerca_doppione( $d['nome'] );
			if ( $doppione ) {
				$errori['st_nome'] = 'Esiste già una scheda con questo nome: ' . get_the_title( $doppione ) . '. Se è un soggetto diverso, aggiungi un dettaglio al nome (per esempio la città).';
			}
		}

		if ( $errori ) {
			// I dati inseriti restano per 10 minuti, così chi scrive non perde il lavoro.
			set_transient( 'st_bozza_' . $utente, array( 'dati' => wp_unslash( $_POST ), 'errori' => $errori ), 10 * MINUTE_IN_SECONDS );
			Vista::reindirizza( $torna, 'errore', 'modulo' );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => self::TIPO,
				'post_status'  => 'pending',
				'post_title'   => $d['nome'],
				'post_content' => $d['sintesi'],
				'post_author'  => $utente,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			Vista::reindirizza( $torna, 'errore', 'salvataggio' );
		}

		wp_set_object_terms( $id, array( $d['settore'] ), self::SETTORE );
		update_post_meta( $id, '_st_tipo', $d['tipo'] );
		update_post_meta( $id, '_st_luogo', $d['luogo'] );
		update_post_meta( $id, '_st_fonti', $d['fonti'] );
		update_post_meta( $id, '_st_figura_pubblica', $d['figura_pubblica'] ? 1 : 0 );
		self::salva_aggregato( $id, Punteggi::aggrega( array(), Voti::media_generale(), (float) Impostazioni::valore( 'peso_bayes' ) ) );
		self::aggiungi_storico( $id, 'Scheda proposta dal sito, in attesa di revisione.' );
		delete_transient( 'st_bozza_' . $utente );

		wp_mail(
			get_option( 'admin_email' ),
			'[statistiche.top] Nuova scheda da rivedere: ' . $d['nome'],
			"È arrivata una nuova scheda da rivedere.\n\n" . admin_url( 'post.php?post=' . $id . '&action=edit' )
		);

		Vista::reindirizza( $torna, 'ok', 'scheda_inviata' );
	}

	/** Cerca una scheda (pubblicata o in revisione) con lo stesso nome. */
	public static function cerca_doppione( $nome ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','pending') AND LOWER(post_title) = LOWER(%s) LIMIT 1",
				self::TIPO,
				$nome
			)
		);
	}

	/** Un utente non può recensire se è autore, referente o ha dichiarato un legame. */
	public static function legami_utente( $scheda_id, $utente_id ) {
		$post      = get_post( $scheda_id );
		$referenti = array_map( 'intval', (array) get_post_meta( $scheda_id, '_st_referenti', true ) );
		$conflitti = array_map( 'intval', (array) get_user_meta( $utente_id, 'st_conflitti', true ) );
		return array(
			'e_autore_scheda'      => $post && (int) $post->post_author === (int) $utente_id,
			'e_referente'          => in_array( (int) $utente_id, $referenti, true ),
			'conflitto_dichiarato' => in_array( (int) $scheda_id, $conflitti, true ),
		);
	}
}
