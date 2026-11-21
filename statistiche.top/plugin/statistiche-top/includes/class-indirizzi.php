<?php
/**
 * Indirizzi in stile wiki: ogni scheda pubblicata si apre su statistiche.top/nomescheda.
 *
 * - I link alle schede pubblicate puntano a /nome-scheda/.
 * - Una richiesta di un solo segmento che non corrisponde a una pagina o a un
 *   articolo viene risolta come scheda.
 * - I nomi brevi non hanno trattini: "Officina Rossi" → /officinarossi/.
 * - Gli indirizzi vecchi (/schede/nome/, /nome-con-trattini/) reindirizzano (301).
 * - Il nome breve di una scheda non può coincidere con una pagina del sito o con
 *   un indirizzo riservato: in quel caso riceve un numero (officinarossi2…).
 *
 * L'elenco di tutte le schede resta su /schede/ e i settori su /settore/nome/.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ST_TEST' ) || defined( 'ABSPATH' ) || exit;

final class Indirizzi {

	/** Indirizzi di primo livello che una scheda non può prendere. */
	const RISERVATI = array(
		'schede', 'settore', 'wp-admin', 'wp-login', 'wp-json', 'wp-content', 'wp-includes', 'feed', 'comments',
		'page', 'search', 'author', 'category', 'tag', 'embed', 'trackback', 'sitemap', 'robots', 'favicon',
		'login', 'admin', 'privacy', 'cookie', 'metodologia', 'proponi', 'classifica',
	);

	public static function avvia() {
		add_filter( 'post_type_link', array( __CLASS__, 'link' ), 10, 2 );
		add_filter( 'request', array( __CLASS__, 'richiesta' ) );
		add_action( 'template_redirect', array( __CLASS__, 'reindirizza_vecchi' ) );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'slug_unico' ), 10, 6 );
	}

	/**
	 * Nome breve senza trattini: "Officina Rossi" → "officinarossi".
	 * Riceve un nome breve già pulito da sanitize_title() (logica pura, collaudabile).
	 */
	public static function compatta( $slug ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $slug ) );
	}

	/** È un nome breve che una scheda non può usare? (logica pura, collaudabile) */
	public static function riservato( $slug, $esiste_pagina = false ) {
		return $esiste_pagina || in_array( $slug, self::RISERVATI, true ) || (bool) preg_match( '/^\d+$/', $slug );
	}

	public static function link( $url, $post ) {
		if ( Schede::TIPO !== $post->post_type || 'publish' !== $post->post_status || '' === $post->post_name ) {
			return $url;
		}
		return home_url( user_trailingslashit( $post->post_name ) );
	}

	/** Trova una scheda pubblicata con questo nome breve. */
	private static function scheda( $slug ) {
		$q = get_posts(
			array(
				'post_type'      => Schede::TIPO,
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return $q ? (int) $q[0] : 0;
	}

	/**
	 * /nome-scheda/ viene interpretato da WordPress come pagina (o articolo).
	 * Se quella pagina non esiste e c'è una scheda con quel nome, la mostriamo.
	 */
	public static function richiesta( $vars ) {
		if ( is_admin() ) {
			return $vars;
		}
		$slug = '';
		// La regola delle pagine produce pagename e, vuoto, page.
		if ( isset( $vars['pagename'] ) && count( array_diff( array_keys( $vars ), array( 'pagename', 'page' ) ) ) === 0 && false === strpos( $vars['pagename'], '/' ) ) {
			$slug = $vars['pagename'];
		} elseif ( isset( $vars['name'] ) && ! isset( $vars['post_type'] ) && count( array_diff( array_keys( $vars ), array( 'name', 'page' ) ) ) === 0 ) {
			$slug = $vars['name'];
		}
		if ( '' === $slug ) {
			return $vars;
		}
		$slug = sanitize_title( $slug );
		if ( get_page_by_path( $slug ) || get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
			return $vars;
		}
		// Anche "officina-rossi" porta a "officinarossi": poi template_redirect corregge l'indirizzo.
		foreach ( array_unique( array( $slug, self::compatta( $slug ) ) ) as $prova ) {
			if ( '' !== $prova && self::scheda( $prova ) ) {
				return array(
					Schede::TIPO => $prova,
					'post_type'  => Schede::TIPO,
					'name'       => $prova,
				);
			}
		}
		return $vars;
	}

	/**
	 * Indirizzo canonico: /schede/nome/, /nome-con-trattini/ e simili portano
	 * con un reindirizzamento permanente a /nomecompatto/.
	 */
	public static function reindirizza_vecchi() {
		if ( ! is_singular( Schede::TIPO ) || is_preview() ) {
			return;
		}
		$giusto    = (string) wp_parse_url( get_permalink( get_queried_object_id() ), PHP_URL_PATH );
		$richiesto = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH );
		if ( '' !== $giusto && untrailingslashit( $giusto ) !== untrailingslashit( $richiesto ) ) {
			$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
			wp_safe_redirect( get_permalink( get_queried_object_id() ) . $query, 301 );
			exit;
		}
	}

	/** Il nome breve di una scheda non deve coincidere con pagine o indirizzi riservati. */
	public static function slug_unico( $slug, $post_id, $stato, $tipo, $genitore, $originale ) {
		if ( Schede::TIPO !== $tipo ) {
			return $slug;
		}
		$base = self::compatta( $slug );
		$base = '' !== $base ? $base : 'scheda';
		$slug = $base;
		$n    = 2;
		while ( self::riservato( $slug, (bool) get_page_by_path( $slug ) ) || self::occupato( $slug, $post_id ) ) {
			$slug = $base . $n++;
		}
		return $slug;
	}

	/** Un'altra scheda o un articolo usa già questo nome? */
	private static function occupato( $slug, $post_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN (%s, 'post') AND ID <> %d LIMIT 1",
				$slug,
				Schede::TIPO,
				(int) $post_id
			)
		);
	}
}
