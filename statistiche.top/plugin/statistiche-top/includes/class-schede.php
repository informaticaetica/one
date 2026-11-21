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
			'sito'             => (string) get_post_meta( $id, '_st_sito', true ),
			'categoria'        => (string) get_post_meta( $id, '_st_categoria_suggerita', true ),
			'fonti'            => is_array( $fonti ) ? $fonti : array(),
			'figura_pubblica'  => (bool) get_post_meta( $id, '_st_figura_pubblica', true ),
			'licenza_immagine' => (string) get_post_meta( $id, '_st_licenza_immagine', true ),
			'referenti'        => array_map( 'intval', (array) get_post_meta( $id, '_st_referenti', true ) ),
			'settori'          => wp_get_post_terms( $id, self::SETTORE, array( 'fields' => 'names' ) ),
		);
	}

	/** La radiografia della scheda, salvata a ogni ricalcolo. */
	public static function aggregato( $id ) {
		$salvata = get_post_meta( $id, '_st_radiografia', true );
		$vuota   = Punteggi::aggrega( array(), 0, 0 );
		$agg     = is_array( $salvata ) ? array_merge( $vuota, $salvata ) : $vuota;
		// I campi usati per ordinare e filtrare restano anche come dati separati.
		$agg['media_bayes'] = (float) get_post_meta( $id, '_st_media_bayes', true );
		$agg['n_recensori'] = (int) get_post_meta( $id, '_st_n_recensori', true );
		return $agg;
	}

	public static function salva_aggregato( $id, array $agg ) {
		update_post_meta( $id, '_st_radiografia', $agg );
		update_post_meta( $id, '_st_media', $agg['media'] );
		update_post_meta( $id, '_st_media_bayes', $agg['media_bayes'] );
		update_post_meta( $id, '_st_n_recensioni', $agg['n_recensioni'] );
		update_post_meta( $id, '_st_n_recensori', $agg['n_recensori'] );
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

	/* ---------- Utilità per la guida di proposta ---------- */

	public static function rimanda_al_login() {
		wp_safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
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
