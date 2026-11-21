<?php
/**
 * Tema statistiche.top: risorse, tema chiaro/scuro e interruttore.
 *
 * Il tema scelto si salva nel browser (localStorage, chiave "tema", valori
 * "light" o "dark"; assente = automatico), vale per tutto il sito e per le
 * visite successive e si sincronizza fra le schede aperte.
 *
 * @package StatisticheTopTema
 */

defined( 'ABSPATH' ) || exit;

/** Stile del tema e script dell'interruttore. */
function statistiche_tema_risorse() {
	$versione = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'statistiche-top-tema', get_stylesheet_uri(), array(), $versione );
	wp_enqueue_script( 'statistiche-top-tema', get_theme_file_uri( 'assets/tema.js' ), array(), $versione, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'statistiche_tema_risorse' );

/** Lo stesso stile nell'editor, per vedere le pagine come sul sito. */
function statistiche_tema_editor() {
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'statistiche_tema_editor' );

/**
 * Applica il tema salvato prima che la pagina venga disegnata, così non
 * compare mai per un istante il tema sbagliato.
 */
function statistiche_tema_iniziale() {
	wp_print_inline_script_tag( 'try{var t=localStorage.getItem("tema");if(t==="light"||t==="dark")document.documentElement.setAttribute("data-theme",t)}catch(e){}' );
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_theme_file_uri( 'assets/fonts/newsreader.woff2' ) ) );
}
add_action( 'wp_head', 'statistiche_tema_iniziale', 1 );

/** Pulsante del tema: funziona solo con JavaScript, quindi parte nascosto e lo mostra lo script. */
function statistiche_tema_interruttore() {
	return '<button type="button" class="st-tema" data-st-tema hidden aria-label="Tema: automatico">'
		. '<span class="st-tema-pista" aria-hidden="true"><span class="st-tema-pomello"><svg class="st-tema-icona" width="14" height="14" viewBox="0 0 24 24" focusable="false"></svg></span></span>'
		. '<span class="st-tema-etichetta" aria-hidden="true">Automatico</span>'
		. '</button>';
}
add_shortcode( 'st_tema', 'statistiche_tema_interruttore' );
