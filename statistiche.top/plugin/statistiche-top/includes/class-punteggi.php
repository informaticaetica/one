<?php
/**
 * Calcoli sulle recensioni: stelle, media bayesiana, radiografia per aspetti,
 * indicatori di gradimento e regola eccellenze.org.
 *
 * Questa classe non usa funzioni di WordPress, così si può collaudare da sola
 * (vedi tests/test-logica.php).
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ST_TEST' ) || defined( 'ABSPATH' ) || exit;

final class Punteggi {

	const STELLE_MIN = 1;
	const STELLE_MAX = 5;

	/** Aspetti valutati per le aziende e le organizzazioni: chiave => etichetta. */
	const ASPETTI_AZIENDA = array(
		'qualita'      => 'Qualità del prodotto o servizio',
		'prezzo'       => 'Rapporto qualità/prezzo',
		'tempi'        => 'Rispetto dei tempi',
		'assistenza'   => 'Comunicazione e assistenza',
		'reclami'      => 'Gestione di problemi e reclami',
		'affidabilita' => 'Affidabilità degli impegni',
		'trasparenza'  => 'Trasparenza',
		'competenza'   => 'Competenza',
		'etica'        => 'Etica e sostenibilità',
		'innovazione'  => 'Innovazione',
	);

	/** Aspetti valutati per le persone con un ruolo pubblico. */
	const ASPETTI_PERSONA = array(
		'competenza'    => 'Competenza',
		'coerenza'      => 'Coerenza',
		'trasparenza'   => 'Trasparenza',
		'impatto'       => 'Impatto',
		'affidabilita'  => 'Affidabilità',
		'comunicazione' => 'Comunicazione',
	);

	/** Quanti aspetti vanno valutati come minimo in una recensione (gli altri possono essere "Non so"). */
	const MIN_ASPETTI = 3;

	public static function aspetti( $tipo ) {
		return 'persona' === $tipo ? self::ASPETTI_PERSONA : self::ASPETTI_AZIENDA;
	}

	/** Etichetta di un aspetto, qualunque sia il tipo di scheda. */
	public static function etichetta_aspetto( $chiave ) {
		if ( isset( self::ASPETTI_AZIENDA[ $chiave ] ) ) {
			return self::ASPETTI_AZIENDA[ $chiave ];
		}
		return isset( self::ASPETTI_PERSONA[ $chiave ] ) ? self::ASPETTI_PERSONA[ $chiave ] : $chiave;
	}

	/** Un numero intero entro un intervallo, oppure null. Accetta solo interi scritti per intero. */
	public static function intero( $valore, $min, $max ) {
		if ( is_int( $valore ) ) {
			$v = $valore;
		} elseif ( is_string( $valore ) && preg_match( '/^\s*-?\d+\s*$/', $valore ) ) {
			$v = (int) $valore;
		} else {
			return null;
		}
		return ( $v < $min || $v > $max ) ? null : $v;
	}

	/** Stelle complessive da 1 a 5, oppure null se il valore non è valido. */
	public static function normalizza_stelle( $valore ) {
		return self::intero( $valore, self::STELLE_MIN, self::STELLE_MAX );
	}

	/**
	 * Voti sugli aspetti: per ogni aspetto del tipo di scheda un valore da 1 a 5,
	 * oppure 0 = "Non so / non applicabile". Aspetti sconosciuti vengono ignorati.
	 *
	 * @return array|null chiave => 0..5 per tutti gli aspetti del tipo, oppure null se un valore non è valido.
	 */
	public static function normalizza_aspetti( array $voti, $tipo ) {
		$puliti = array();
		foreach ( array_keys( self::aspetti( $tipo ) ) as $chiave ) {
			if ( ! isset( $voti[ $chiave ] ) || '' === $voti[ $chiave ] ) {
				$puliti[ $chiave ] = 0;
				continue;
			}
			$v = self::intero( $voti[ $chiave ], 0, self::STELLE_MAX );
			if ( null === $v ) {
				return null;
			}
			$puliti[ $chiave ] = $v;
		}
		return $puliti;
	}

	/** Solo gli aspetti davvero valutati (da 1 a 5). */
	public static function aspetti_valutati( array $aspetti ) {
		return array_filter(
			$aspetti,
			static function ( $v ) {
				return (int) $v >= 1;
			}
		);
	}

	/**
	 * Media bayesiana: (C × m + n × media) / (C + n).
	 *
	 * Con pochi voti il punteggio resta vicino alla media generale m; cresce verso
	 * la media reale della scheda solo quando i voti sono tanti.
	 */
	public static function media_bayesiana( $media, $n, $m, $c ) {
		$n = max( 0, (int) $n );
		$c = max( 0.0, (float) $c );
		if ( 0 === $n && 0.0 === $c ) {
			return 0.0;
		}
		return round( ( $c * $m + $n * $media ) / ( $c + $n ), 4 );
	}

	private static function percento( $parte, $totale ) {
		return $totale ? round( 100 * $parte / $totale, 1 ) : 0.0;
	}

	/**
	 * Aggrega le recensioni valide di una scheda nella sua "radiografia".
	 *
	 * Ogni recensione è un array con: utente_id, stelle (1-5), aspetti
	 * (chiave => 1-5, i "Non so" esclusi), consiglia (0-10 o null),
	 * tornerebbe (1, 0 o null), problema ('risolto', 'non_risolto', 'nessuno' o ''),
	 * relazione (stringa).
	 *
	 * @return array media, media_bayes, n_recensioni, n_recensori, distribuzione,
	 *               aspetti, consiglia, tornerebbe, problemi, relazioni.
	 */
	public static function aggrega( array $recensioni, $m, $c ) {
		$n             = count( $recensioni );
		$utenti        = array();
		$somma         = 0;
		$distribuzione = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$aspetti       = array();
		$consiglia     = array( 'n' => 0, 'promotori' => 0, 'detrattori' => 0 );
		$tornerebbe    = array( 'n' => 0, 'si' => 0 );
		$problemi      = array( 'n' => 0, 'risolti' => 0, 'nessuno' => 0 );
		$relazioni     = array();

		foreach ( $recensioni as $r ) {
			$utenti[ (int) $r['utente_id'] ] = true;
			$stelle = (int) $r['stelle'];
			$somma += $stelle;
			if ( isset( $distribuzione[ $stelle ] ) ) {
				$distribuzione[ $stelle ]++;
			}
			foreach ( self::aspetti_valutati( isset( $r['aspetti'] ) ? (array) $r['aspetti'] : array() ) as $chiave => $v ) {
				if ( ! isset( $aspetti[ $chiave ] ) ) {
					$aspetti[ $chiave ] = array( 'somma' => 0, 'n' => 0 );
				}
				$aspetti[ $chiave ]['somma'] += (int) $v;
				$aspetti[ $chiave ]['n']++;
			}
			if ( isset( $r['consiglia'] ) && null !== $r['consiglia'] && '' !== $r['consiglia'] ) {
				$v = (int) $r['consiglia'];
				$consiglia['n']++;
				if ( $v >= 9 ) {
					$consiglia['promotori']++;
				} elseif ( $v <= 6 ) {
					$consiglia['detrattori']++;
				}
			}
			if ( isset( $r['tornerebbe'] ) && null !== $r['tornerebbe'] && '' !== $r['tornerebbe'] ) {
				$tornerebbe['n']++;
				$tornerebbe['si'] += (int) $r['tornerebbe'] ? 1 : 0;
			}
			$problema = isset( $r['problema'] ) ? (string) $r['problema'] : '';
			if ( 'risolto' === $problema || 'non_risolto' === $problema ) {
				$problemi['n']++;
				$problemi['risolti'] += 'risolto' === $problema ? 1 : 0;
			} elseif ( 'nessuno' === $problema ) {
				$problemi['nessuno']++;
			}
			$rel = isset( $r['relazione'] ) && '' !== $r['relazione'] ? (string) $r['relazione'] : 'altro';
			$relazioni[ $rel ] = isset( $relazioni[ $rel ] ) ? $relazioni[ $rel ] + 1 : 1;
		}

		$media = $n ? round( $somma / $n, 4 ) : 0.0;
		$medie = array();
		foreach ( $aspetti as $chiave => $a ) {
			$medie[ $chiave ] = array( 'media' => round( $a['somma'] / $a['n'], 2 ), 'n' => $a['n'] );
		}
		arsort( $relazioni );

		return array(
			'media'         => $media,
			'media_bayes'   => self::media_bayesiana( $media, $n, $m, $c ),
			'n_recensioni'  => $n,
			'n_recensori'   => count( $utenti ),
			'distribuzione' => $distribuzione,
			'aspetti'       => $medie,
			'consiglia'     => array(
				'n'          => $consiglia['n'],
				'promotori'  => self::percento( $consiglia['promotori'], $consiglia['n'] ),
				'detrattori' => self::percento( $consiglia['detrattori'], $consiglia['n'] ),
				'nps'        => $consiglia['n'] ? (int) round( self::percento( $consiglia['promotori'], $consiglia['n'] ) - self::percento( $consiglia['detrattori'], $consiglia['n'] ) ) : 0,
			),
			'tornerebbe'    => array( 'n' => $tornerebbe['n'], 'si' => self::percento( $tornerebbe['si'], $tornerebbe['n'] ) ),
			'problemi'      => array( 'n' => $problemi['n'], 'risolti' => self::percento( $problemi['risolti'], $problemi['n'] ), 'nessuno' => $problemi['nessuno'] ),
			'relazioni'     => $relazioni,
		);
	}

	/**
	 * Punti di forza e punti deboli: i migliori e i peggiori aspetti fra quelli
	 * con abbastanza risposte. Un aspetto non compare in entrambe le liste.
	 *
	 * @return array{forza: string[], debolezza: string[]}
	 */
	public static function forza_debolezza( array $aspetti, $min_risposte = 3, $quanti = 3 ) {
		$validi = array_filter(
			$aspetti,
			static function ( $a ) use ( $min_risposte ) {
				return $a['n'] >= $min_risposte;
			}
		);
		if ( count( $validi ) < 2 ) {
			return array( 'forza' => array(), 'debolezza' => array() );
		}
		$medie = array_map(
			static function ( $a ) {
				return $a['media'];
			},
			$validi
		);
		arsort( $medie );
		$quanti    = min( $quanti, (int) floor( count( $medie ) / 2 ) );
		$forza     = array_slice( array_keys( $medie ), 0, $quanti );
		$debolezza = array_reverse( array_slice( array_keys( $medie ), -$quanti ) );
		return array( 'forza' => $forza, 'debolezza' => array_values( array_diff( $debolezza, $forza ) ) );
	}

	/**
	 * Stato della scheda rispetto a eccellenze.org dopo il calcolo notturno.
	 *
	 * Stati: '' (nessuno), 'candidata' (attende verifica manuale), 'pubblicata',
	 * 'revisione_uscita' (attende verifica manuale per l'uscita), 'rifiutata'.
	 * Il passaggio a 'pubblicata' o l'uscita li decide sempre una persona:
	 * qui si propongono solo candidature e revisioni.
	 */
	public static function stato_eccellenze( $stato_attuale, array $aggregato, array $soglie ) {
		$supera_ingresso = $aggregato['n_recensori'] >= $soglie['min_recensori']
			&& $aggregato['media_bayes'] >= $soglie['soglia_ingresso'];
		$sotto_uscita    = $aggregato['media_bayes'] < $soglie['soglia_uscita'];

		switch ( $stato_attuale ) {
			case '':
				return $supera_ingresso ? 'candidata' : '';
			case 'candidata':
				return $supera_ingresso ? 'candidata' : '';
			case 'pubblicata':
				return $sotto_uscita ? 'revisione_uscita' : 'pubblicata';
			case 'revisione_uscita':
				return $sotto_uscita ? 'revisione_uscita' : 'pubblicata';
			case 'rifiutata':
			default:
				return $stato_attuale;
		}
	}
}
