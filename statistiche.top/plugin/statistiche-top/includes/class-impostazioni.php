<?php
/**
 * Impostazioni del plugin, con valori predefiniti presi dal documento di progetto.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Impostazioni {

	const OPZIONE = 'st_impostazioni';

	/** Valori predefiniti: chiave => array( valore, etichetta, tipo, min, max ). */
	public static function campi() {
		return array(
			'eta_minima_giorni'      => array( 7, 'Anzianità minima dell\'account per recensire (giorni)', 'int', 0, 365 ),
			'giorni_attivita_minimi' => array( 2, 'Giorni distinti di accesso richiesti prima di recensire', 'int', 0, 60 ),
			'limite_giornaliero'     => array( 10, 'Recensioni nuove consentite a un utente in 24 ore', 'int', 1, 200 ),
			'giorni_modifica'        => array( 30, 'Giorni entro cui si può modificare la propria recensione', 'int', 0, 365 ),
			'peso_bayes'             => array( 20, 'Peso della media generale (recensioni virtuali, C)', 'float', 0, 1000 ),
			'media_priori'           => array( 3.5, 'Media generale usata finché il sito ha meno di 50 recensioni', 'float', 1, 5 ),
			'min_recensori'          => array( 100, 'eccellenze.org: recensori unici necessari', 'int', 1, 100000 ),
			'soglia_ingresso'        => array( 4.5, 'eccellenze.org: media bayesiana minima per la candidatura', 'float', 1, 5 ),
			'soglia_uscita'          => array( 4.3, 'eccellenze.org: sotto questa media si apre la revisione di uscita', 'float', 1, 5 ),
			'url_eccellenze'         => array( 'https://eccellenze.org', 'Indirizzo di eccellenze.org', 'url', null, null ),
		);
	}

	public static function avvia() {
		add_action( 'admin_init', array( __CLASS__, 'registra' ) );
	}

	/** Tutte le impostazioni, con i predefiniti al posto di quelle mancanti. */
	public static function tutte() {
		$salvate = get_option( self::OPZIONE, array() );
		$salvate = is_array( $salvate ) ? $salvate : array();
		$valori  = array();
		foreach ( self::campi() as $chiave => $campo ) {
			$valori[ $chiave ] = array_key_exists( $chiave, $salvate ) ? $salvate[ $chiave ] : $campo[0];
		}
		return $valori;
	}

	public static function valore( $chiave ) {
		$tutte = self::tutte();
		return isset( $tutte[ $chiave ] ) ? $tutte[ $chiave ] : null;
	}

	public static function registra() {
		register_setting(
			'st_impostazioni',
			self::OPZIONE,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'pulisci' ),
				'default'           => array(),
			)
		);
	}

	public static function pulisci( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$pulite = array();
		foreach ( self::campi() as $chiave => $campo ) {
			list( $predefinito, , $tipo, $min, $max ) = $campo;
			$v = isset( $input[ $chiave ] ) ? wp_unslash( $input[ $chiave ] ) : $predefinito;
			if ( 'url' === $tipo ) {
				$v = esc_url_raw( trim( (string) $v ) );
				$v = $v ? untrailingslashit( $v ) : $predefinito;
			} else {
				$v = 'int' === $tipo ? (int) $v : (float) str_replace( ',', '.', (string) $v );
				$v = max( $min, min( $max, $v ) );
			}
			$pulite[ $chiave ] = $v;
		}
		if ( $pulite['soglia_uscita'] > $pulite['soglia_ingresso'] ) {
			$pulite['soglia_uscita'] = $pulite['soglia_ingresso'];
			add_settings_error( self::OPZIONE, 'soglie', 'La soglia di uscita non può superare quella di ingresso: l\'ho portata allo stesso valore.' );
		}
		return $pulite;
	}

	/** Soglie per Punteggi::stato_eccellenze(). */
	public static function soglie() {
		$i = self::tutte();
		return array(
			'min_recensori'   => (int) $i['min_recensori'],
			'soglia_ingresso' => (float) $i['soglia_ingresso'],
			'soglia_uscita'   => (float) $i['soglia_uscita'],
		);
	}
}
