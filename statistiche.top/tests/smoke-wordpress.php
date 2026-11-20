<?php
/**
 * Collaudo su un WordPress vero (gira in CI con WP-CLI e SQLite):
 *   wp eval-file statistiche.top/tests/smoke-wordpress.php
 *
 * Crea dati di prova, simula 100 recensioni, esegue il calcolo notturno e
 * controlla candidatura, decisione manuale, API e pagine pubbliche.
 */

use StatisticheTop\Eccellenze;
use StatisticheTop\Installazione;
use StatisticheTop\Schede;
use StatisticheTop\Vista;
use StatisticheTop\Voti;

$falliti = 0;
function controlla( $nome, $condizione ) {
	global $falliti;
	if ( $condizione ) {
		WP_CLI::log( "ok   $nome" );
	} else {
		$falliti++;
		WP_CLI::warning( "NO   $nome" );
	}
}

global $wpdb;

// Tabelle create all'attivazione.
controlla( 'tabella voti presente', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Installazione::tabella_voti() ) ) );
controlla( 'calcolo notturno pianificato', (bool) wp_next_scheduled( Installazione::EVENTO_NOTTURNO ) );

// Una scheda pubblicata.
$settore = wp_insert_term( 'Industria', Schede::SETTORE );
$autore  = wp_insert_user( array( 'user_login' => 'autore', 'user_pass' => wp_generate_password(), 'user_email' => 'autore@example.org' ) );
$scheda  = wp_insert_post(
	array(
		'post_type'    => Schede::TIPO,
		'post_status'  => 'publish',
		'post_title'   => 'Azienda di prova',
		'post_content' => 'Azienda di prova usata dal collaudo automatico del plugin.',
		'post_author'  => $autore,
	)
);
wp_set_object_terms( $scheda, array( (int) $settore['term_id'] ), Schede::SETTORE );
update_post_meta( $scheda, '_st_tipo', 'azienda' );
update_post_meta( $scheda, '_st_fonti', array( array( 'url' => 'https://example.org', 'descrizione' => 'Sito ufficiale' ) ) );

// Regole: l'autore non può recensire, un account appena creato nemmeno.
$fatti = Voti::fatti( $scheda, $autore );
controlla( 'autore riconosciuto', $fatti['e_autore_scheda'] );
$nuovo = wp_insert_user( array( 'user_login' => 'nuovo', 'user_pass' => wp_generate_password(), 'user_email' => 'nuovo@example.org' ) );
list( $ok, $motivo ) = StatisticheTop\Regole::puo_recensire( Voti::fatti( $scheda, $nuovo ), StatisticheTop\Impostazioni::tutte() );
controlla( 'account nuovo respinto', ! $ok && 'account_recente' === $motivo );

// 100 recensioni da 100 utenti diversi, tutte da 5.
$adesso = current_time( 'mysql' );
for ( $i = 1; $i <= 100; $i++ ) {
	$u = wp_insert_user( array( 'user_login' => "recensore$i", 'user_pass' => wp_generate_password(), 'user_email' => "r$i@example.org" ) );
	$wpdb->insert(
		Installazione::tabella_voti(),
		array(
			'scheda_id' => $scheda, 'utente_id' => $u, 'competenza' => 5, 'affidabilita' => 5, 'impatto' => 5, 'trasparenza' => 5,
			'commento' => 1 === $i ? 'Commento di prova.' : '', 'stato' => 'valido', 'ip_hash' => hash( 'sha256', "ip$i" ), 'creato' => $adesso, 'modificato' => $adesso,
		)
	);
}

// Doppio voto impedito dal vincolo UNIQUE.
$wpdb->suppress_errors( true );
$doppio = $wpdb->insert( Installazione::tabella_voti(), array( 'scheda_id' => $scheda, 'utente_id' => $u, 'competenza' => 1, 'affidabilita' => 1, 'impatto' => 1, 'trasparenza' => 1, 'stato' => 'valido', 'creato' => $adesso, 'modificato' => $adesso ) );
$wpdb->suppress_errors( false );
controlla( 'doppio voto impedito', false === $doppio );

Eccellenze::calcolo_notturno();
$agg = Schede::aggregato( $scheda );
controlla( '100 recensori contati', 100 === $agg['n_recensori'] );
controlla( 'punteggio ponderato sopra 4,5', $agg['media_bayes'] >= 4.5 );
controlla( 'scheda candidata', 'candidata' === Eccellenze::stato( $scheda ) );
controlla( 'decisione su stato sbagliato rifiutata', ! Eccellenze::decidi( $scheda, 'esci' ) );
controlla( 'approvazione manuale', Eccellenze::decidi( $scheda, 'approva', 'Fonti verificate.' ) && 'pubblicata' === Eccellenze::stato( $scheda ) );

// API per eccellenze.org.
$risposta = rest_do_request( new WP_REST_Request( 'GET', '/statistiche-top/v1/eccellenze' ) );
$dati     = $risposta->get_data();
controlla( 'API risponde 200', 200 === $risposta->get_status() );
controlla( 'API elenca la scheda con link di ritorno', 1 === count( $dati['schede'] ) && get_permalink( $scheda ) === $dati['schede'][0]['url'] );

// Pagine pubbliche: metodologia e pagina della scheda generano HTML.
controlla( 'metodologia', false !== strpos( Vista::shortcode_metodologia(), 'media bayesiana' ) );
controlla( 'classifica', false !== strpos( Vista::shortcode_classifica( array( 'minimo' => 50 ) ), 'Azienda di prova' ) );
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $scheda, 'post_type' => Schede::TIPO ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$html = '';
while ( have_posts() ) {
	the_post();
	$html = apply_filters( 'the_content', get_the_content() );
}
controlla( 'pagina della scheda con punteggio', false !== strpos( $html, 'st-punteggio' ) && false !== strpos( $html, 'eccellenze.org' ) );
controlla( 'pagina della scheda con modulo di segnalazione', false !== strpos( $html, 'st_segnalazione' ) );
controlla( 'storico pubblico', false !== strpos( $html, 'Fonti verificate.' ) );

// Modulo di proposta con gli aiuti a fumetto e interruttore del tema.
wp_set_current_user( $nuovo );
$modulo = Vista::shortcode_nuova_scheda();
controlla( 'modulo di proposta con 9 aiuti', 9 === substr_count( $modulo, 'class="st-aiuto-pulsante"' ) );
controlla( 'interruttore del tema', false !== strpos( do_shortcode( '[st_tema]' ), 'data-st-tema' ) );
wp_set_current_user( 0 );

// Cancellazione di un utente: le sue recensioni spariscono (GDPR).
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $u );
controlla( 'recensioni dell\'utente cancellato rimosse', 99 === Schede::aggregato( $scheda )['n_recensori'] );

if ( $falliti ) {
	WP_CLI::error( "$falliti controlli falliti." );
}
WP_CLI::success( 'Collaudo superato.' );
