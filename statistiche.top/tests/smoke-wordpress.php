<?php
/**
 * Collaudo su un WordPress vero (gira in CI con WP-CLI e SQLite):
 *   wp eval-file statistiche.top/tests/smoke-wordpress.php
 *
 * Simula il modulo semplice (anche senza account), la conferma dell'email, la
 * moderazione, la richiesta "Crea la tua scheda", gli indirizzi wiki senza
 * trattini, il calcolo notturno ed eccellenze.org.
 */

use StatisticheTop\Eccellenze;
use StatisticheTop\Indirizzi;
use StatisticheTop\Installazione;
use StatisticheTop\Modulo;
use StatisticheTop\Registro;
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
// Le email non partono davvero: le contiamo.
$GLOBALS['email_inviate'] = array();
add_filter(
	'pre_wp_mail',
	static function ( $nulla, $atts ) {
		$GLOBALS['email_inviate'][] = $atts;
		return true;
	},
	10,
	2
);

controlla( 'tabella voti presente', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Installazione::tabella_voti() ) ) );
controlla( 'tabella registro presente', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Registro::tabella() ) ) );
controlla( 'calcolo notturno pianificato', (bool) wp_next_scheduled( Installazione::EVENTO_NOTTURNO ) );

// Validazione del modulo semplice.
$errori = Modulo::valida( array( 'st_tipo' => 'azienda', 'st_nome' => 'X', 'st_stelle' => '6', 'st_recensione' => 'corta', 'st_email' => 'no' ), false, false, 'azienda' );
controlla( 'modulo: errori su nome, stelle, testo, email, dichiarazioni', isset( $errori['st_nome'], $errori['st_stelle'], $errori['st_recensione'], $errori['st_email'], $errori['st_veridicita'], $errori['st_privacy'] ) );
$errori = Modulo::valida( array( 'st_tipo' => 'prodotto', 'st_nome' => 'Moka', 'st_stelle' => '5', 'st_recensione' => str_repeat( 'Ottima moka, fa un caffè buono. ', 3 ), 'st_email' => 'a@example.org', 'st_veridicita' => '1', 'st_privacy' => '1', 'st_relazione' => 'concorrente' ), false, false, 'prodotto' );
controlla( 'modulo: un concorrente non può recensire', isset( $errori['st_relazione'] ) && 1 === count( $errori ) );

// Account leggero creato dall'email, senza email di WordPress.
$u1 = Modulo::utente_da_email( 'lettore@example.org' );
controlla( 'account creato dall\'email', $u1 > 0 && Modulo::utente_da_email( 'lettore@example.org' ) === $u1 );
controlla( 'nome visualizzato neutro', 0 === strpos( get_userdata( $u1 )->display_name, 'Recensore' ) );

// Scheda nata da una recensione: in revisione, nome breve senza trattini.
$scheda = wp_insert_post( array( 'post_type' => Schede::TIPO, 'post_status' => 'pending', 'post_title' => 'Officina Rossi Bologna', 'post_author' => $u1 ) );
update_post_meta( $scheda, '_st_tipo', 'azienda' );
controlla( 'nome breve senza trattini', 'officinarossibologna' === get_post_field( 'post_name', $scheda ) );
controlla( 'la stessa scheda si trova con il nome scritto in altro modo', $scheda === Schede::cerca_doppione( 'officina rossi bologna' ) );

$dati = array( 'stelle' => 4, 'titolo' => 'Buon lavoro', 'aspetti' => array(), 'relazione' => 'cliente', 'esperienza' => '', 'frequenza' => '', 'canale' => '', 'consiglia' => null, 'tornerebbe' => null, 'problema' => '', 'pro' => '', 'contro' => '', 'commento' => 'Buon lavoro, prezzi onesti e tempi rispettati.' );
$token = bin2hex( random_bytes( 16 ) );
controlla( 'recensione senza account: da confermare', 'conferma' === Voti::salva( $scheda, $u1, $dati, 'conferma', true, array( 'telefono' => '+39 333 1234567', 'token' => $token ), true ) );
$voto = Voti::recensione_utente( $scheda, $u1 );
controlla( 'telefono e codice di conferma salvati', '+39 333 1234567' === $voto['telefono'] && $token === $voto['token'] );
controlla( 'registro: la recensione è registrata con l\'autore', (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Registro::tabella() . ' WHERE voto_id = %d AND utente_id = %d', $voto['id'], $u1 ) ) );

// Conferma: simuliamo il clic sul link.
$wpdb->update( Installazione::tabella_voti(), array( 'stato' => 'moderazione', 'token' => '' ), array( 'id' => $voto['id'] ) );
controlla( 'dopo la conferma: in moderazione', 1 === count( array_filter( Voti::da_moderare(), static function ( $r ) use ( $voto ) { return (int) $r['id'] === (int) $voto['id']; } ) ) );

// La redazione pubblica la scheda e approva la recensione.
wp_update_post( array( 'ID' => $scheda, 'post_status' => 'publish' ) );
$GLOBALS['email_inviate'] = array();
Voti::cambia_stato( (int) $voto['id'], 'valido' );
controlla( 'recensione approvata: conta nella scheda', 1 === Schede::aggregato( $scheda )['n_recensioni'] );
controlla( 'email di pubblicazione a chi ha scritto', (bool) array_filter( $GLOBALS['email_inviate'], static function ( $m ) { return 'lettore@example.org' === $m['to'] || array( 'lettore@example.org' ) === (array) $m['to']; } ) );

// Indirizzi wiki.
controlla( 'indirizzo wiki /officinarossibologna/', home_url( user_trailingslashit( 'officinarossibologna' ) ) === get_permalink( $scheda ) );
$vars = Indirizzi::richiesta( array( 'pagename' => 'officina-rossi-bologna', 'page' => '' ) );
controlla( 'l\'indirizzo con i trattini porta alla scheda', isset( $vars['post_type'] ) && Schede::TIPO === $vars['post_type'] && 'officinarossibologna' === $vars['name'] );
wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Pagina', 'post_name' => 'nomeoccupato' ) );
$omonima = wp_insert_post( array( 'post_type' => Schede::TIPO, 'post_status' => 'publish', 'post_title' => 'Nome occupato' ) );
controlla( 'una scheda non prende il nome di una pagina', 'nomeoccupato2' === get_post_field( 'post_name', $omonima ) );

// Pagina della scheda come voce wiki, con il modulo semplice.
$GLOBALS['wp_query']     = new WP_Query( array( 'p' => $scheda, 'post_type' => Schede::TIPO ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$html = '';
while ( have_posts() ) {
	the_post();
	$html = apply_filters( 'the_content', get_the_content() );
}
controlla( 'voce wiki: riquadro, indice, statistiche, modulo', false !== strpos( $html, 'st-infobox' ) && false !== strpos( $html, 'st-indice' ) && false !== strpos( $html, 'st-radiografia' ) && false !== strpos( $html, 'name="action" value="st_recensione"' ) );
wp_reset_postdata();

// Home: modulo completo; "Crea la tua scheda".
$modulo = Modulo::shortcode();
controlla( 'modulo in home: nome, categoria, stelle, testo, email, telefono', false !== strpos( $modulo, 'st_nome' ) && false !== strpos( $modulo, 'st_categoria' ) && false !== strpos( $modulo, 'st_stelle' ) && false !== strpos( $modulo, 'maxlength="2000"' ) && false !== strpos( $modulo, 'st_email' ) && false !== strpos( $modulo, 'st_telefono' ) );
controlla( 'modulo "Crea la tua scheda"', false !== strpos( StatisticheTop\Richieste::shortcode(), 'st_r_referente' ) );
controlla( 'notifiche alla redazione: due indirizzi', in_array( 'informaticaetica@gmail.com', StatisticheTop\Notifiche::destinatari(), true ) );

// eccellenze.org: 100 recensioni valide da 5.
$adesso = current_time( 'mysql' );
for ( $i = 1; $i <= 100; $i++ ) {
	$u = Modulo::utente_da_email( "r$i@example.org" );
	$wpdb->insert( Installazione::tabella_voti(), array( 'scheda_id' => $scheda, 'utente_id' => $u, 'stelle' => 5, 'titolo' => 'Ottimo', 'aspetti' => '[]', 'commento' => 'Ottimo servizio.', 'stato' => 'valido', 'creato' => $adesso, 'modificato' => $adesso ) );
}
Eccellenze::calcolo_notturno();
controlla( 'scheda candidata a eccellenze.org', 'candidata' === Eccellenze::stato( $scheda ) );
controlla( 'approvazione manuale', Eccellenze::decidi( $scheda, 'approva', 'Verificata.' ) && 'pubblicata' === Eccellenze::stato( $scheda ) );
$risposta = rest_do_request( new WP_REST_Request( 'GET', '/statistiche-top/v1/eccellenze' ) );
controlla( 'API per eccellenze.org', 200 === $risposta->get_status() && 1 === count( $risposta->get_data()['schede'] ) );
controlla( 'metodologia', false !== strpos( Vista::shortcode_metodologia(), 'Conferma dell' ) );

// Recensioni mai confermate: cancellate dopo la scadenza, con la scheda nata solo da loro.
$u2      = Modulo::utente_da_email( 'mai@example.org' );
$fantasma = wp_insert_post( array( 'post_type' => Schede::TIPO, 'post_status' => 'pending', 'post_title' => 'Scheda fantasma', 'post_author' => $u2 ) );
Voti::salva( $fantasma, $u2, $dati, 'conferma', true, array( 'token' => bin2hex( random_bytes( 16 ) ) ), true );
$wpdb->update( Installazione::tabella_voti(), array( 'creato' => '2020-01-01 00:00:00' ), array( 'scheda_id' => $fantasma ) );
controlla( 'recensione non confermata cancellata', 1 === Modulo::pulisci_non_confermate() && 'trash' === get_post_status( $fantasma ) );

// Cancellazione di un utente: le sue recensioni spariscono (GDPR).
require_once ABSPATH . 'wp-admin/includes/user.php';
$prima = Schede::aggregato( $scheda )['n_recensori'];
wp_delete_user( $u1 );
controlla( 'recensioni dell\'utente cancellato rimosse', $prima - 1 === Schede::aggregato( $scheda )['n_recensori'] );

if ( $falliti ) {
	WP_CLI::error( "$falliti controlli falliti." );
}
WP_CLI::success( 'Collaudo superato.' );
