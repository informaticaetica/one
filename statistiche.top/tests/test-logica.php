<?php
/**
 * Collaudo della logica pura del plugin (senza WordPress).
 *
 * Uso: php statistiche.top/tests/test-logica.php
 */

define( 'ST_TEST', true );
$base = dirname( __DIR__ ) . '/plugin/statistiche-top/includes/';
require $base . 'class-punteggi.php';
require $base . 'class-regole.php';

use StatisticheTop\Punteggi;
use StatisticheTop\Regole;

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

function recensioni( $n, $voto, $primo_utente = 1 ) {
	$r = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$r[] = array( 'utente_id' => $primo_utente + $i, 'competenza' => $voto, 'affidabilita' => $voto, 'impatto' => $voto, 'trasparenza' => $voto );
	}
	return $r;
}

// --- Validazione dei voti ---
verifica( 'voti validi', array( 'competenza' => 5, 'affidabilita' => 4, 'impatto' => 3, 'trasparenza' => 1 ),
	Punteggi::normalizza_voti( array( 'competenza' => '5', 'affidabilita' => 4, 'impatto' => '3', 'trasparenza' => 1 ) ) );
verifica( 'manca un criterio', null, Punteggi::normalizza_voti( array( 'competenza' => 5, 'affidabilita' => 4, 'impatto' => 3 ) ) );
verifica( 'voto 6 rifiutato', null, Punteggi::normalizza_voti( array( 'competenza' => 6, 'affidabilita' => 4, 'impatto' => 3, 'trasparenza' => 1 ) ) );
verifica( 'voto 0 rifiutato', null, Punteggi::normalizza_voti( array( 'competenza' => 0, 'affidabilita' => 4, 'impatto' => 3, 'trasparenza' => 1 ) ) );
verifica( 'decimale rifiutato', null, Punteggi::normalizza_voti( array( 'competenza' => '4.5', 'affidabilita' => 4, 'impatto' => 3, 'trasparenza' => 1 ) ) );
verifica( 'testo rifiutato', null, Punteggi::normalizza_voti( array( 'competenza' => 'cinque', 'affidabilita' => 4, 'impatto' => 3, 'trasparenza' => 1 ) ) );

// --- Media bayesiana ---
verifica( 'nessun voto = media generale', 3.5, Punteggi::media_bayesiana( 0, 0, 3.5, 20 ) );
verifica( 'tre voti da 5 restano vicini alla media generale', ( 20 * 3.5 + 3 * 5 ) / 23, Punteggi::media_bayesiana( 5, 3, 3.5, 20 ) );
verifica( 'C = 0 dà la media semplice', 4.2, Punteggi::media_bayesiana( 4.2, 10, 3.0, 0 ) );
verifica( 'nessun voto e C = 0', 0.0, Punteggi::media_bayesiana( 0, 0, 3.0, 0 ) );

// --- Aggregazione ---
$agg = Punteggi::aggrega( recensioni( 3, 5 ), 3.5, 20 );
verifica( 'aggregato: n recensioni', 3, $agg['n_recensioni'] );
verifica( 'aggregato: n recensori', 3, $agg['n_recensori'] );
verifica( 'aggregato: media semplice', 5.0, $agg['media'] );
verifica( 'aggregato: tre 5 non sono eccellenza', true, $agg['media_bayes'] < 4.5 );
$misti = array(
	array( 'utente_id' => 1, 'competenza' => 5, 'affidabilita' => 3, 'impatto' => 4, 'trasparenza' => 4 ),
	array( 'utente_id' => 2, 'competenza' => 3, 'affidabilita' => 5, 'impatto' => 2, 'trasparenza' => 2 ),
);
$agg = Punteggi::aggrega( $misti, 3, 0 );
verifica( 'media per criterio', array( 'competenza' => 4.0, 'affidabilita' => 4.0, 'impatto' => 3.0, 'trasparenza' => 3.0 ), $agg['criteri'] );
verifica( 'media complessiva', 3.5, $agg['media'] );
$vuoto = Punteggi::aggrega( array(), 3.5, 20 );
verifica( 'scheda senza voti', 0, $vuoto['n_recensioni'] );

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

// --- Regole di ammissione ---
$imp   = array( 'eta_minima_giorni' => 7, 'giorni_attivita_minimi' => 2, 'limite_giornaliero' => 10, 'giorni_modifica' => 30 );
$base_fatti = array(
	'eta_account_giorni' => 10, 'giorni_attivita' => 3, 'recensioni_24h' => 0,
	'e_autore_scheda' => false, 'e_referente' => false, 'conflitto_dichiarato' => false,
	'scheda_pubblicata' => true, 'giorni_dalla_recensione' => null,
);
verifica( 'utente regolare', array( true, '' ), Regole::puo_recensire( $base_fatti, $imp ) );
verifica( 'account di 6 giorni', array( false, 'account_recente' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'eta_account_giorni' => 6 ) ), $imp ) );
verifica( 'un solo giorno di attività', array( false, 'attivita_insufficiente' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'giorni_attivita' => 1 ) ), $imp ) );
verifica( 'limite giornaliero', array( false, 'limite_giornaliero' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'recensioni_24h' => 10 ) ), $imp ) );
verifica( 'autore della scheda', array( false, 'autore_scheda' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'e_autore_scheda' => true ) ), $imp ) );
verifica( 'referente', array( false, 'referente' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'e_referente' => true ) ), $imp ) );
verifica( 'conflitto dichiarato', array( false, 'conflitto' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'conflitto_dichiarato' => true ) ), $imp ) );
verifica( 'scheda in revisione', array( false, 'scheda_non_pubblicata' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'scheda_pubblicata' => false ) ), $imp ) );
verifica( 'modifica al giorno 29', array( true, '' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'giorni_dalla_recensione' => 29, 'recensioni_24h' => 50 ) ), $imp ) );
verifica( 'modifica al giorno 30', array( false, 'modifica_scaduta' ), Regole::puo_recensire( array_merge( $base_fatti, array( 'giorni_dalla_recensione' => 30 ) ), $imp ) );
verifica( 'IP nuovo: valido', 'valido', Regole::stato_iniziale( 0 ) );
verifica( 'IP già usato: sospeso', 'sospeso', Regole::stato_iniziale( 1 ) );

echo "\n$totali verifiche, $falliti fallite.\n";
exit( $falliti ? 1 : 0 );
