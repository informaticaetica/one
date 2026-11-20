<?php
/**
 * Calcoli sui voti: medie, media bayesiana, regola eccellenze.org.
 *
 * Questa classe non usa funzioni di WordPress, così si può collaudare da sola
 * (vedi tests/test-logica.php).
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ST_TEST' ) || defined( 'ABSPATH' ) || exit;

final class Punteggi {

	/** Criteri fissi di valutazione: chiave => etichetta. */
	const CRITERI = array(
		'competenza'    => 'Competenza',
		'affidabilita'  => 'Affidabilità',
		'impatto'       => 'Impatto',
		'trasparenza'   => 'Trasparenza',
	);

	const VOTO_MIN = 1;
	const VOTO_MAX = 5;

	/**
	 * Controlla e normalizza i voti dei criteri.
	 *
	 * @param array $voti chiave criterio => voto.
	 * @return array|null voti interi per tutti i criteri, oppure null se manca o non è valido qualcosa.
	 */
	public static function normalizza_voti( array $voti ) {
		$puliti = array();
		foreach ( array_keys( self::CRITERI ) as $criterio ) {
			if ( ! isset( $voti[ $criterio ] ) || ! is_numeric( $voti[ $criterio ] ) ) {
				return null;
			}
			$v = (int) $voti[ $criterio ];
			if ( (string) $v !== (string) trim( (string) $voti[ $criterio ] ) || $v < self::VOTO_MIN || $v > self::VOTO_MAX ) {
				return null;
			}
			$puliti[ $criterio ] = $v;
		}
		return $puliti;
	}

	/** Media dei criteri di una singola recensione. */
	public static function media_recensione( array $voti ) {
		return round( array_sum( $voti ) / count( $voti ), 4 );
	}

	/**
	 * Media bayesiana: (C × m + n × media) / (C + n).
	 *
	 * Con pochi voti il punteggio resta vicino alla media generale m; cresce verso
	 * la media reale della scheda solo quando i voti sono tanti.
	 *
	 * @param float $media    media semplice della scheda.
	 * @param int   $n        numero di recensioni valide.
	 * @param float $m        media generale di tutte le recensioni del sito (o valore a priori).
	 * @param float $c        peso del valore a priori, in "recensioni virtuali".
	 */
	public static function media_bayesiana( $media, $n, $m, $c ) {
		$n = max( 0, (int) $n );
		$c = max( 0.0, (float) $c );
		if ( 0 === $n && 0.0 === $c ) {
			return 0.0;
		}
		return round( ( $c * $m + $n * $media ) / ( $c + $n ), 4 );
	}

	/**
	 * Aggrega le recensioni valide di una scheda.
	 *
	 * @param array $recensioni elenco di array con le chiavi dei criteri e 'utente_id'.
	 * @param float $m          media generale.
	 * @param float $c          peso del valore a priori.
	 * @return array media, media_bayes, n_recensioni, n_recensori, criteri (media per criterio).
	 */
	public static function aggrega( array $recensioni, $m, $c ) {
		$n        = count( $recensioni );
		$utenti   = array();
		$somme    = array_fill_keys( array_keys( self::CRITERI ), 0 );
		$somma    = 0.0;
		foreach ( $recensioni as $r ) {
			$utenti[ (int) $r['utente_id'] ] = true;
			$voti = array();
			foreach ( array_keys( self::CRITERI ) as $criterio ) {
				$voti[ $criterio ]   = (int) $r[ $criterio ];
				$somme[ $criterio ] += (int) $r[ $criterio ];
			}
			$somma += self::media_recensione( $voti );
		}
		$media   = $n ? round( $somma / $n, 4 ) : 0.0;
		$criteri = array();
		foreach ( $somme as $criterio => $s ) {
			$criteri[ $criterio ] = $n ? round( $s / $n, 2 ) : 0.0;
		}
		return array(
			'media'        => $media,
			'media_bayes'  => self::media_bayesiana( $media, $n, $m, $c ),
			'n_recensioni' => $n,
			'n_recensori'  => count( $utenti ),
			'criteri'      => $criteri,
		);
	}

	/**
	 * Stato della scheda rispetto a eccellenze.org dopo il calcolo notturno.
	 *
	 * Stati: '' (nessuno), 'candidata' (attende verifica manuale), 'pubblicata',
	 * 'revisione_uscita' (attende verifica manuale per l'uscita), 'rifiutata'.
	 * Il passaggio a 'pubblicata' o l'uscita li decide sempre una persona:
	 * qui si propongono solo candidature e revisioni.
	 *
	 * @param string $stato_attuale stato salvato.
	 * @param array  $aggregato     risultato di aggrega().
	 * @param array  $soglie        min_recensori, soglia_ingresso, soglia_uscita.
	 * @return string nuovo stato.
	 */
	public static function stato_eccellenze( $stato_attuale, array $aggregato, array $soglie ) {
		$supera_ingresso = $aggregato['n_recensori'] >= $soglie['min_recensori']
			&& $aggregato['media_bayes'] >= $soglie['soglia_ingresso'];
		$sotto_uscita    = $aggregato['media_bayes'] < $soglie['soglia_uscita'];

		switch ( $stato_attuale ) {
			case '':
				return $supera_ingresso ? 'candidata' : '';
			case 'candidata':
				// Se nel frattempo è scesa sotto la soglia, la candidatura decade.
				return $supera_ingresso ? 'candidata' : '';
			case 'pubblicata':
				return $sotto_uscita ? 'revisione_uscita' : 'pubblicata';
			case 'revisione_uscita':
				// Se è risalita prima della revisione, resta pubblicata.
				return $sotto_uscita ? 'revisione_uscita' : 'pubblicata';
			case 'rifiutata':
			default:
				// Una scheda rifiutata torna candidabile solo per decisione manuale.
				return $stato_attuale;
		}
	}
}
