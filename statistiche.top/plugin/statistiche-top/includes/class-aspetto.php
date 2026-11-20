<?php
/**
 * Aspetto del sito: font Newsreader ospitato sul sito, palette avorio/ardesia,
 * colonna di lettura stretta e interruttore del tema a tre stati
 * (automatico, chiaro, scuro).
 *
 * Il tema scelto si salva nel browser (localStorage, chiave "tema", valori
 * "light" o "dark"; assente = automatico), vale per tutto il sito e per le
 * visite successive e si sincronizza fra le schede aperte.
 *
 * Pensato per il tema Twenty Twenty-Five: ne ridefinisce le variabili di
 * colore, font e larghezza, così vale per tutte le pagine senza toccare i template.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Aspetto {

	public static function avvia() {
		if ( ! (int) Impostazioni::valore( 'aspetto' ) ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'tema_iniziale' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'precarica_font' ), 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'risorse' ), 20 );
		add_shortcode( 'st_tema', array( __CLASS__, 'shortcode_tema' ) );
	}

	/**
	 * Applica il tema salvato prima che la pagina venga disegnata, così non
	 * compare mai per un istante il tema sbagliato.
	 */
	public static function tema_iniziale() {
		wp_print_inline_script_tag( 'try{var t=localStorage.getItem("tema");if(t==="light"||t==="dark")document.documentElement.setAttribute("data-theme",t)}catch(e){}' );
	}

	public static function precarica_font() {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( plugins_url( 'assets/fonts/newsreader.woff2', FILE ) )
		);
	}

	public static function risorse() {
		wp_enqueue_style( 'statistiche-top' );
		wp_enqueue_style( 'statistiche-top-aspetto', plugins_url( 'assets/aspetto.css', FILE ), array( 'statistiche-top' ), VERSIONE );
		wp_enqueue_script( 'statistiche-top' );
	}

	/** Pulsante del tema: funziona solo con JavaScript, quindi parte nascosto e lo mostra lo script. */
	public static function shortcode_tema() {
		return '<button type="button" class="st-tema" data-st-tema hidden aria-label="Tema: automatico">'
			. '<span class="st-tema-pista" aria-hidden="true"><span class="st-tema-pomello"><svg class="st-tema-icona" width="14" height="14" viewBox="0 0 24 24" focusable="false"></svg></span></span>'
			. '<span class="st-tema-etichetta" aria-hidden="true">Automatico</span>'
			. '</button>';
	}
}
