<?php
/**
 * Regola eccellenze.org: calcolo notturno, coda di verifica manuale e API.
 *
 * eccellenze.org COPIA le schede (l'originale resta qui): legge l'elenco dalla
 * API pubblica GET /wp-json/statistiche-top/v1/eccellenze e mostra il link di
 * ritorno alla scheda completa.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Eccellenze {

	const STATI = array(
		''                 => 'Nessuno',
		'candidata'        => 'Candidata: attende la verifica',
		'pubblicata'       => 'Su eccellenze.org',
		'revisione_uscita' => 'Su eccellenze.org, ma sotto la soglia di uscita: attende la revisione',
		'rifiutata'        => 'Candidatura respinta',
	);

	/** Decisioni manuali: azione => array( stati di partenza ammessi, stato di arrivo, nota per lo storico ). */
	const DECISIONI = array(
		'approva'   => array( array( 'candidata' ), 'pubblicata', 'Verifica superata: scheda copiata su eccellenze.org.' ),
		'respingi'  => array( array( 'candidata' ), 'rifiutata', 'Candidatura a eccellenze.org respinta dopo la verifica.' ),
		'mantieni'  => array( array( 'revisione_uscita' ), 'pubblicata', 'Revisione di uscita: la scheda resta su eccellenze.org.' ),
		'esci'      => array( array( 'revisione_uscita' ), '', 'Revisione di uscita: la scheda lascia eccellenze.org.' ),
		'riammetti' => array( array( 'rifiutata' ), '', 'La scheda torna candidabile a eccellenze.org.' ),
	);

	public static function avvia() {
		add_action( Installazione::EVENTO_NOTTURNO, array( __CLASS__, 'calcolo_notturno' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'registra_api' ) );
	}

	public static function stato( $id ) {
		return (string) get_post_meta( $id, '_st_ecc_stato', true );
	}

	/** Ricalcola tutte le schede pubblicate e propone candidature e revisioni. */
	public static function calcolo_notturno() {
		Voti::promuovi_in_attesa();
		Registro::pulisci();
		Modulo::pulisci_non_confermate();
		Voti::calcola_media_generale();
		$soglie  = Impostazioni::soglie();
		$nuove   = array();
		$pagina  = 1;
		do {
			$q = new \WP_Query(
				array(
					'post_type'      => Schede::TIPO,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'posts_per_page' => 200,
					'paged'          => $pagina,
					'no_found_rows'  => false,
				)
			);
			foreach ( $q->posts as $id ) {
				$agg    = Voti::ricalcola_scheda( $id );
				$prima  = self::stato( $id );
				$dopo   = Punteggi::stato_eccellenze( $prima, $agg, $soglie );
				if ( $dopo !== $prima ) {
					update_post_meta( $id, '_st_ecc_stato', $dopo );
					Schede::aggiungi_storico( $id, 'eccellenze.org: ' . self::STATI[ $dopo ] . '.' );
					if ( in_array( $dopo, array( 'candidata', 'revisione_uscita' ), true ) ) {
						$nuove[] = get_the_title( $id ) . ' — ' . self::STATI[ $dopo ];
					}
				}
			}
			$pagina++;
		} while ( $pagina <= $q->max_num_pages );

		update_option( 'st_ultimo_calcolo', current_time( 'mysql' ), false );
		if ( $nuove ) {
			wp_mail(
				get_option( 'admin_email' ),
				'[statistiche.top] Schede da verificare per eccellenze.org',
				implode( "\n", $nuove ) . "\n\n" . admin_url( 'admin.php?page=st-eccellenze' )
			);
		}
	}

	/** Applica una decisione manuale. Restituisce true se lo stato è cambiato. */
	public static function decidi( $id, $azione, $nota = '' ) {
		if ( ! isset( self::DECISIONI[ $azione ] ) || get_post_type( $id ) !== Schede::TIPO ) {
			return false;
		}
		list( $da, $a, $testo ) = self::DECISIONI[ $azione ];
		if ( ! in_array( self::stato( $id ), $da, true ) ) {
			return false;
		}
		update_post_meta( $id, '_st_ecc_stato', $a );
		if ( 'pubblicata' === $a && ! get_post_meta( $id, '_st_ecc_dal', true ) ) {
			update_post_meta( $id, '_st_ecc_dal', current_time( 'mysql' ) );
		}
		if ( '' === $a ) {
			delete_post_meta( $id, '_st_ecc_dal' );
		}
		Schede::aggiungi_storico( $id, $testo . ( $nota ? ' Motivazione: ' . $nota : '' ) );
		return true;
	}

	public static function in_stato( array $stati, $limite = 100 ) {
		return get_posts(
			array(
				'post_type'      => Schede::TIPO,
				'post_status'    => 'publish',
				'posts_per_page' => $limite,
				'meta_query'     => array(
					array(
						'key'     => '_st_ecc_stato',
						'value'   => $stati,
						'compare' => 'IN',
					),
				),
				'orderby'        => 'modified',
				'order'          => 'ASC',
			)
		);
	}

	/* ---------- API per eccellenze.org ---------- */

	public static function registra_api() {
		register_rest_route(
			'statistiche-top/v1',
			'/eccellenze',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_elenco' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Elenco delle schede su eccellenze.org (dati già pubblici sulla scheda). */
	public static function api_elenco() {
		$schede = array();
		foreach ( self::in_stato( array( 'pubblicata', 'revisione_uscita' ), 1000 ) as $p ) {
			$d   = Schede::dati( $p->ID );
			$agg = Schede::aggregato( $p->ID );
			$schede[] = array(
				'id'           => $p->ID,
				'nome'         => get_the_title( $p ),
				'tipo'         => $d['tipo'],
				'settori'      => $d['settori'],
				'luogo'        => $d['luogo'],
				'sintesi'      => wp_strip_all_tags( $p->post_content ),
				'fonti'        => $d['fonti'],
				'media_bayes'  => $agg['media_bayes'],
				'n_recensori'  => $agg['n_recensori'],
				'distribuzione' => $agg['distribuzione'],
				'aspetti'      => $agg['aspetti'],
				'consiglia'    => $agg['consiglia'],
				'url'          => get_permalink( $p ),
				'dal'          => (string) get_post_meta( $p->ID, '_st_ecc_dal', true ),
				'aggiornata'   => get_post_modified_time( 'c', true, $p ),
			);
		}
		$risposta = rest_ensure_response(
			array(
				'generato'      => current_time( 'c' ),
				'metodologia'   => Impostazioni::soglie(),
				'schede'        => $schede,
			)
		);
		$risposta->header( 'Cache-Control', 'public, max-age=3600' );
		return $risposta;
	}
}
