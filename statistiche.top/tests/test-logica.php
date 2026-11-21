<?php
/**
 * Collaudo della logica pura del plugin (senza WordPress).
 *
 * Uso: php statistiche.top/tests/test-logica.php
 */

define( 'ST_TEST', true );
$base = dirname( __DIR__ ) . '/plugin/statistiche-top/includes/';
require $base . 'class-punteggi.php';
require $base . 'class-modulo.php';
require $base . 'class-indirizzi.php';

use StatisticheTop\Punteggi;
use StatisticheTop\Modulo;
use StatisticheTop\Indirizzi;

$falliti = 0;
$totali  = 0;
function verifica( $nome, $atteso, $ottenuto ) {
	global $falliti, $totali;
	$totali++;
	$ok = is_float( $atteso ) ? abs( $atteso - $ottenuto ) < 0.0001 : $atteso === $ottenuto;
	if ( ! $ok ) {
		$falliti++;
		echo "FALLITO: $nome\n  atteso:   " . var_export( $atteso, true ) . "\n  ottenuto: " . var_export( $ottenuto, true ) . "\n";
	}
}

function recensioni( $n, $stelle, $primo_utente = 1, $extra = array() ) {
	$r = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$r[] = array_merge( array( 'utente_id' => $primo_utente + $i, 'stelle' => $stelle, 'aspetti' => array() ), $extra );
	}
	return $r;
}

// --- Validazione ---
verifica( 'stelle valide', 4, Punteggi::normalizza_stelle( '4' ) );
verifica( 'stelle 0 rifiutate', null, Punteggi::normalizza_stelle( 0 ) );
verifica( 'stelle 6 rifiutate', null, Punteggi::normalizza_stelle( '6' ) );
verifica( 'stelle decimali rifiutate', null, Punteggi::normalizza_stelle( '4.5' ) );
verifica( 'stelle testo rifiutate', null, Punteggi::normalizza_stelle( 'cinque' ) );
$asp = Punteggi::normalizza_aspetti( array( 'qualita' => '5', 'prezzo' => '0', 'sconosciuto' => '3' ), 'azienda' );
verifica( 'aspetti: tutti presenti', count( Punteggi::ASPETTI_AZIENDA ), count( $asp ) );
verifica( 'aspetti: mancante = non so', 0, $asp['tempi'] );
verifica( 'aspetti: sconosciuto ignorato', false, isset( $asp['sconosciuto'] ) );
verifica( 'aspetti: valutati', array( 'qualita' => 5 ), Punteggi::aspetti_valutati( $asp ) );
verifica( 'aspetti: valore 7 rifiutato', null, Punteggi::normalizza_aspetti( array( 'qualita' => 7 ), 'azienda' ) );
verifica( 'aspetti persona', count( Punteggi::ASPETTI_PERSONA ), count( Punteggi::normalizza_aspetti( array(), 'persona' ) ) );

// --- Media bayesiana ---
verifica( 'nessun voto = media generale', 3.5, Punteggi::media_bayesiana( 0, 0, 3.5, 20 ) );
verifica( 'tre voti da 5 restano vicini alla media generale', ( 20 * 3.5 + 3 * 5 ) / 23, Punteggi::media_bayesiana( 5, 3, 3.5, 20 ) );
verifica( 'C = 0 dà la media semplice', 4.2, Punteggi::media_bayesiana( 4.2, 10, 3.0, 0 ) );
verifica( 'nessun voto e C = 0', 0.0, Punteggi::media_bayesiana( 0, 0, 3.0, 0 ) );

// --- Aggregazione (radiografia) ---
$agg = Punteggi::aggrega( recensioni( 3, 5 ), 3.5, 20 );
verifica( 'aggregato: n recensioni', 3, $agg['n_recensioni'] );
verifica( 'aggregato: media semplice', 5.0, $agg['media'] );
verifica( 'aggregato: tre 5 non sono eccellenza', true, $agg['media_bayes'] < 4.5 );
verifica( 'distribuzione', array( 5 => 3, 4 => 0, 3 => 0, 2 => 0, 1 => 0 ), $agg['distribuzione'] );
$miste = array(
	array( 'utente_id' => 1, 'stelle' => 5, 'aspetti' => array( 'qualita' => 5, 'prezzo' => 2 ), 'consiglia' => 10, 'tornerebbe' => 1, 'problema' => 'nessuno', 'relazione' => 'cliente' ),
	array( 'utente_id' => 2, 'stelle' => 3, 'aspetti' => array( 'qualita' => 4, 'prezzo' => 0 ), 'consiglia' => 5, 'tornerebbe' => 0, 'problema' => 'risolto', 'relazione' => 'cliente' ),
	array( 'utente_id' => 3, 'stelle' => 4, 'aspetti' => array( 'qualita' => 3 ), 'consiglia' => 7, 'tornerebbe' => null, 'problema' => 'non_risolto', 'relazione' => 'fornitore' ),
);
$agg = Punteggi::aggrega( $miste, 3, 0 );
verifica( 'media stelle', 4.0, $agg['media'] );
verifica( 'aspetto qualità', array( 'media' => 4.0, 'n' => 3 ), $agg['aspetti']['qualita'] );
verifica( 'aspetto prezzo: i "non so" non contano', array( 'media' => 2.0, 'n' => 1 ), $agg['aspetti']['prezzo'] );
verifica( 'consiglia: promotori', 33.3, $agg['consiglia']['promotori'] );
verifica( 'consiglia: detrattori', 33.3, $agg['consiglia']['detrattori'] );
verifica( 'consiglia: NPS', 0, $agg['consiglia']['nps'] );
verifica( 'tornerebbe: solo chi ha risposto', array( 'n' => 2, 'si' => 50.0 ), $agg['tornerebbe'] );
verifica( 'problemi risolti', array( 'n' => 2, 'risolti' => 50.0, 'nessuno' => 1 ), $agg['problemi'] );
verifica( 'relazioni', array( 'cliente' => 2, 'fornitore' => 1 ), $agg['relazioni'] );
$vuoto = Punteggi::aggrega( array(), 3.5, 20 );
verifica( 'scheda senza voti', 0, $vuoto['n_recensioni'] );
verifica( 'scheda senza voti: indicatori a zero', 0, $vuoto['consiglia']['n'] );

// --- Punti di forza e punti deboli ---
$aspetti = array(
	'qualita' => array( 'media' => 4.8, 'n' => 10 ), 'prezzo' => array( 'media' => 2.1, 'n' => 10 ),
	'tempi' => array( 'media' => 3.9, 'n' => 10 ), 'assistenza' => array( 'media' => 4.5, 'n' => 10 ),
	'reclami' => array( 'media' => 2.9, 'n' => 10 ), 'etica' => array( 'media' => 5.0, 'n' => 1 ),
);
$fd = Punteggi::forza_debolezza( $aspetti );
verifica( 'forza: i migliori con abbastanza risposte', array( 'qualita', 'assistenza' ), $fd['forza'] );
verifica( 'debolezza: i peggiori', array( 'prezzo', 'reclami' ), $fd['debolezza'] );
verifica( 'forza/debolezza: un solo aspetto non basta', array( 'forza' => array(), 'debolezza' => array() ), Punteggi::forza_debolezza( array( 'qualita' => array( 'media' => 4, 'n' => 9 ) ) ) );

// --- Regola eccellenze.org ---
$soglie = array( 'min_recensori' => 100, 'soglia_ingresso' => 4.5, 'soglia_uscita' => 4.3 );
$molti_5 = Punteggi::aggrega( array_merge( recensioni( 95, 5 ), recensioni( 5, 4, 1000 ) ), 3.5, 20 );
verifica( '100 recensori con media alta: candidata', 'candidata', Punteggi::stato_eccellenze( '', $molti_5, $soglie ) );
$pochi = Punteggi::aggrega( recensioni( 99, 5 ), 3.5, 20 );
verifica( '99 recensori: non candidata', '', Punteggi::stato_eccellenze( '', $pochi, $soglie ) );
$medi = Punteggi::aggrega( recensioni( 200, 4 ), 3.5, 20 );
verifica( 'media 4: non candidata', '', Punteggi::stato_eccellenze( '', $medi, $soglie ) );
verifica( 'candidata che scende decade', '', Punteggi::stato_eccellenze( 'candidata', $medi, $soglie ) );
$fascia = array( 'n_recensori' => 150, 'media_bayes' => 4.4 );
verifica( 'pubblicata a 4,4 resta (isteresi)', 'pubblicata', Punteggi::stato_eccellenze( 'pubblicata', $fascia, $soglie ) );
$bassa = array( 'n_recensori' => 150, 'media_bayes' => 4.29 );
verifica( 'pubblicata sotto 4,3 va in revisione', 'revisione_uscita', Punteggi::stato_eccellenze( 'pubblicata', $bassa, $soglie ) );
verifica( 'revisione che risale torna pubblicata', 'pubblicata', Punteggi::stato_eccellenze( 'revisione_uscita', $fascia, $soglie ) );
verifica( 'rifiutata resta rifiutata', 'rifiutata', Punteggi::stato_eccellenze( 'rifiutata', $molti_5, $soglie ) );

// --- Titolo automatico della recensione ---
verifica( 'titolo: prima frase', 'Lavoro preciso', Modulo::titolo( 'Lavoro preciso. Tempi un po\' lunghi ma ne è valsa la pena.' ) );
verifica( 'titolo: frase lunga accorciata', 80, mb_strlen( Modulo::titolo( str_repeat( 'parola ', 30 ) ) ) <= 80 ? 80 : 0 );
verifica( 'titolo: senza punteggiatura finale', 'Ottimo servizio', Modulo::titolo( 'Ottimo servizio!' ) );

// --- Prodotti ---
verifica( 'aspetti dei prodotti', count( Punteggi::ASPETTI_PRODOTTO ), count( Punteggi::aspetti( 'prodotto' ) ) );
verifica( 'etichetta aspetto prodotto', 'Facilità d\'uso', Punteggi::etichetta_aspetto( 'uso' ) );

// --- Indirizzi wiki ---
verifica( 'slug senza trattini', 'officinarossi', Indirizzi::compatta( 'officina-rossi' ) );
verifica( 'slug persona', 'mariobianchi', Indirizzi::compatta( 'mario-bianchi' ) );
verifica( 'slug con numeri', 'caffe3000', Indirizzi::compatta( 'caffe-3000' ) );
verifica( 'slug normale libero', false, Indirizzi::riservato( 'officinarossi' ) );
verifica( 'slug di una pagina esistente', true, Indirizzi::riservato( 'chi-siamo', true ) );
verifica( 'slug riservato: schede', true, Indirizzi::riservato( 'schede' ) );
verifica( 'slug riservato: wp-admin', true, Indirizzi::riservato( 'wp-admin' ) );
verifica( 'slug solo numerico', true, Indirizzi::riservato( '2026' ) );

echo "\n$totali verifiche, $falliti fallite.\n";
exit( $falliti ? 1 : 0 );
