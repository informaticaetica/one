<?php
/**
 * Guida a passi per proporre una scheda (4 passi) o recensire una scheda
 * esistente (3 passi). Ogni passo è un modulo vero che si invia al server: la
 * guida funziona anche senza JavaScript e conserva i dati fra un passo e l'altro.
 *
 * Passi:
 *   chi         – chi è (solo nella proposta): tipo, nome, sito, luogo, categoria libera, sintesi, fonti;
 *   esperienza  – rapporto, quando, quanto spesso, come, stelle complessive, titolo;
 *   radiografia – voto sugli aspetti (o "Non so"), consiglio da 0 a 10, ci torneresti, problemi;
 *   racconto    – cosa è piaciuto, cosa no, racconto libero, riepilogo e dichiarazioni.
 *
 * I membri di statistiche.top (capacità st_aggiungi) possono aggiungere una
 * scheda dal primo passo senza recensione; le loro schede sono pubblicate subito.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Guida {

	const PASSI = array(
		'chi'         => 'Chi è',
		'esperienza'  => 'La tua esperienza',
		'radiografia' => 'La radiografia',
		'racconto'    => 'Racconto e conferma',
	);
	const MAX_CATEGORIA = 60;

	/** Testi degli aiuti a fumetto (HTML fisso, scritto qui). */
	const AIUTI = array(
		'tipo'        => '<p>Scegli <strong>persona</strong> solo se ha un ruolo pubblico: amministratore d\'azienda, professionista noto, figura istituzionale, artista, sportivo. Non si creano schede su privati cittadini.</p><p>Scegli <strong>azienda o organizzazione</strong> per imprese, negozi, studi professionali, associazioni, enti e fondazioni.</p>',
		'nome'        => '<p>Il nome completo, come compare nelle fonti ufficiali: per le persone nome e cognome, per le aziende la ragione sociale o il marchio più noto (per esempio «Rossi Costruzioni S.r.l.»).</p><p>Se esiste già una scheda con lo stesso nome, aggiungi un dettaglio per distinguerla, come la città.</p>',
		'sito'        => '<p>Facoltativo. L\'indirizzo del sito ufficiale, se c\'è. Aiuta la redazione a verificare la scheda e i lettori a trovare il soggetto.</p>',
		'luogo'       => '<p>Facoltativo. Dove opera il soggetto: la città della sede o dell\'attività principale, oppure la regione o il paese se lavora su un territorio ampio.</p><p>Per le persone non indicare mai l\'indirizzo di casa.</p>',
		'categoria'   => '<p>Facoltativo. Scrivi con parole tue in che categoria la metteresti (per esempio «Officina meccanica» o «Pasticceria»), oppure scegli uno dei settori suggeriti mentre scrivi.</p><p>Come in un wiki, la redazione decide poi la categoria definitiva: può usare la tua o sceglierne un\'altra.</p>',
		'sintesi'     => '<p>In poche righe: chi è, che cosa fa e <strong>perché merita una scheda</strong>.</p><p>Scrivi solo fatti che si trovano nelle fonti, senza giudizi personali: la tua opinione va nei passi successivi. Per esempio: «Fondata nel 1987 a Bologna, produce macchine per l\'imballaggio.»</p>',
		'fonti'       => '<p>Almeno una fonte, fino a tre. Valgono il sito ufficiale, articoli di giornali e riviste, bilanci, il registro delle imprese, atti pubblici.</p><p>Non valgono profili social personali, forum o pagine pubblicitarie. Accanto a ogni indirizzo scrivi che cosa conferma, per esempio «Registro imprese: data di fondazione».</p>',
		'figura'      => '<p>Serve solo per le schede su una persona. La legge sulla privacy consente di parlare pubblicamente di una persona per il ruolo pubblico che ricopre, non della sua vita privata: per questo la scheda deve riguardare solo quel ruolo.</p>',
		'relazione'   => '<p>Che rapporto hai avuto con il soggetto: è il dato più importante per leggere le statistiche. Le recensioni dei clienti, dei fornitori e dei partner vengono mostrate separatamente.</p><p>Se ci lavori o ci hai lavorato, ne sei socio, parente o concorrente, non puoi recensirlo: è un conflitto di interesse.</p>',
		'quando'      => '<p>Il mese e l\'anno dell\'esperienza che racconti, o dell\'ultima volta se sono state più di una. Un\'esperienza di dieci anni fa conta meno di una recente: chi legge deve saperlo.</p>',
		'frequenza'   => '<p>Una sola esperienza o un rapporto che dura nel tempo? Aiuta a capire quanto è solida la tua valutazione.</p>',
		'canale'      => '<p>Come sei entrato in contatto: di persona (in negozio, in ufficio, a un evento), online, al telefono o in altro modo.</p>',
		'stelle'      => '<p>La tua valutazione complessiva, da 1 stella (pessima) a 5 stelle (ottima). È il voto che entra nel punteggio della scheda e nella regola di eccellenze.org.</p><p>Pensa all\'insieme: i dettagli li dai nel passo successivo.</p>',
		'titolo'      => '<p>Una frase che riassume la tua esperienza, come il titolo di un articolo. Per esempio: «Lavoro preciso, ma tempi lunghi».</p>',
		'aspetti'     => '<p>Un voto da 1 a 5 su ogni aspetto: è la «radiografia» che mostra dove il soggetto eccelle e dove no.</p><p>Se un aspetto non lo conosci o non riguarda la tua esperienza, lascia «Non so»: non conta nel calcolo. Servono almeno 3 aspetti valutati.</p>',
		'consiglia'   => '<p>Da 0 (per niente) a 10 (sicuramente): quanto lo consiglieresti a un amico o a un collega? Da queste risposte nasce l\'indice di raccomandazione della scheda.</p>',
		'tornerebbe'  => '<p>Se ne avessi di nuovo bisogno, ti rivolgeresti ancora a lui? Per una persona con un ruolo pubblico: ti affideresti di nuovo al suo lavoro?</p>',
		'problema'    => '<p>Un problema capita a tutti: conta come viene gestito. Indica se c\'è stato e se è stato risolto.</p>',
		'pro'         => '<p>Facoltativo. Le cose che ti sono piaciute di più, in poche parole.</p>',
		'contro'      => '<p>Facoltativo. Le cose che non ti sono piaciute o che miglioreresti.</p>',
		'racconto'    => '<p>Facoltativo, ma prezioso: racconta l\'esperienza con fatti concreti (che cosa hai chiesto, che cosa è successo, come è finita). Niente insulti e niente dati personali di altre persone.</p><p>Serve almeno un po\' di testo fra «Cosa ti è piaciuto», «Cosa non ti è piaciuto» e il racconto.</p>',
		'dichiarazioni' => '<p>Le recensioni false sono vietate dalla legge. Con queste dichiarazioni confermi che racconti un\'esperienza reale e personale e che non hai legami con il soggetto.</p>',
		'invio'       => '<p>Le recensioni di chi propone una scheda contano dopo l\'approvazione della redazione e quando l\'account ha almeno 7 giorni. Le schede proposte dagli utenti compaiono dopo la revisione della redazione.</p><p>Fino all\'invio i dati restano salvati per un giorno: puoi tornare indietro e correggere.</p>',
	);

	public static function avvia() {
		add_action( 'admin_post_st_guida', array( __CLASS__, 'gestisci' ) );
		add_action( 'admin_post_nopriv_st_guida', array( Schede::class, 'rimanda_al_login' ) );
		add_shortcode( 'st_nuova_scheda', array( __CLASS__, 'shortcode_proposta' ) );
	}

	/* ---------- Stato della guida ---------- */

	private static function chiave( $modo, $scheda_id ) {
		return 'st_guida_' . get_current_user_id() . '_' . $modo . '_' . (int) $scheda_id;
	}

	private static function stato( $modo, $scheda_id ) {
		$s = get_transient( self::chiave( $modo, $scheda_id ) );
		return is_array( $s ) ? array_merge( array( 'dati' => array(), 'errori' => array(), 'fatti' => array() ), $s ) : array( 'dati' => array(), 'errori' => array(), 'fatti' => array() );
	}

	private static function salva_stato( $modo, $scheda_id, array $s ) {
		set_transient( self::chiave( $modo, $scheda_id ), $s, DAY_IN_SECONDS );
	}

	private static function cancella_stato( $modo, $scheda_id ) {
		delete_transient( self::chiave( $modo, $scheda_id ) );
	}

	/** I passi di un modo, in ordine. */
	public static function passi( $modo ) {
		$tutti = array_keys( self::PASSI );
		return 'proposta' === $modo ? $tutti : array_slice( $tutti, 1 );
	}

	/** Campi di ciascun passo: nome => tipo di pulizia. */
	private static function campi( $passo ) {
		$campi = array(
			'chi'         => array( 'st_tipo' => 'chiave', 'st_nome' => 'testo', 'st_sito' => 'testo', 'st_luogo' => 'testo', 'st_categoria' => 'testo', 'st_sintesi' => 'area', 'st_fonte_url' => 'lista', 'st_fonte_descrizione' => 'lista', 'st_figura_pubblica' => 'spunta' ),
			'esperienza'  => array( 'st_relazione' => 'chiave', 'st_mese' => 'testo', 'st_anno' => 'testo', 'st_frequenza' => 'chiave', 'st_canale' => 'chiave', 'st_stelle' => 'testo', 'st_titolo' => 'testo' ),
			'radiografia' => array( 'st_aspetto' => 'mappa', 'st_consiglia' => 'testo', 'st_tornerebbe' => 'chiave', 'st_problema' => 'chiave' ),
			'racconto'    => array( 'st_pro' => 'area', 'st_contro' => 'area', 'st_commento' => 'area', 'st_nessun_conflitto' => 'spunta', 'st_veridicita' => 'spunta' ),
		);
		return isset( $campi[ $passo ] ) ? $campi[ $passo ] : array();
	}

	/** Prende dal modulo i campi del passo, puliti. */
	private static function leggi_passo( $passo, array $in ) {
		$dati = array();
		foreach ( self::campi( $passo ) as $nome => $tipo ) {
			$v = isset( $in[ $nome ] ) ? $in[ $nome ] : null;
			switch ( $tipo ) {
				case 'chiave':
					$dati[ $nome ] = is_scalar( $v ) ? sanitize_key( $v ) : '';
					break;
				case 'area':
					$dati[ $nome ] = is_scalar( $v ) ? sanitize_textarea_field( $v ) : '';
					break;
				case 'spunta':
					$dati[ $nome ] = empty( $v ) ? '' : '1';
					break;
				case 'lista':
					$dati[ $nome ] = array_slice( array_map( 'sanitize_text_field', array_filter( (array) $v, 'is_scalar' ) ), 0, Schede::MAX_FONTI );
					break;
				case 'mappa':
					$dati[ $nome ] = array();
					foreach ( (array) $v as $k => $x ) {
						if ( is_scalar( $x ) ) {
							$dati[ $nome ][ sanitize_key( $k ) ] = sanitize_text_field( $x );
						}
					}
					break;
				default:
					$dati[ $nome ] = is_scalar( $v ) ? sanitize_text_field( $v ) : '';
			}
		}
		return $dati;
	}

	/* ---------- Validazione ---------- */

	private static function lunghezza( $s ) {
		return mb_strlen( (string) $s );
	}

	/**
	 * Controlla un passo. Restituisce gli errori indicizzati per nome del campo.
	 *
	 * @param string $tipo 'persona' o 'azienda'.
	 */
	public static function valida( $passo, array $d, $tipo, $modo ) {
		$e = array();
		$g = static function ( $k ) use ( $d ) {
			return isset( $d[ $k ] ) ? $d[ $k ] : '';
		};
		if ( 'chi' === $passo ) {
			if ( ! isset( Schede::TIPI_SOGGETTO[ $g( 'st_tipo' ) ] ) ) {
				$e['st_tipo'] = 'Scegli se la scheda riguarda una persona o un\'azienda.';
			}
			$nome = $g( 'st_nome' );
			if ( self::lunghezza( $nome ) < 2 || self::lunghezza( $nome ) > 150 ) {
				$e['st_nome'] = 'Scrivi il nome (da 2 a 150 caratteri).';
			} elseif ( Schede::cerca_doppione( $nome ) ) {
				$e['st_nome'] = 'Esiste già una scheda con questo nome. Se è un soggetto diverso, aggiungi un dettaglio (per esempio la città); se è lo stesso, cercalo e recensiscilo dalla sua scheda.';
			}
			if ( '' !== $g( 'st_sito' ) && ! wp_http_validate_url( esc_url_raw( $g( 'st_sito' ), array( 'http', 'https' ) ) ) ) {
				$e['st_sito'] = 'L\'indirizzo del sito deve iniziare con https:// o http://.';
			}
			if ( self::lunghezza( $g( 'st_luogo' ) ) > 100 ) {
				$e['st_luogo'] = 'Il luogo può avere al massimo 100 caratteri.';
			}
			if ( self::lunghezza( $g( 'st_categoria' ) ) > self::MAX_CATEGORIA ) {
				$e['st_categoria'] = sprintf( 'La categoria può avere al massimo %d caratteri.', self::MAX_CATEGORIA );
			}
			$l = self::lunghezza( $g( 'st_sintesi' ) );
			if ( $l < 30 || $l > Schede::MAX_SINTESI ) {
				$e['st_sintesi'] = sprintf( 'La sintesi deve avere da 30 a %d caratteri (ora: %d).', Schede::MAX_SINTESI, $l );
			}
			if ( ! self::fonti( $d ) ) {
				$e['st_fonte_url'] = 'Indica almeno una fonte con un indirizzo valido, che inizi con https:// o http://.';
			}
			if ( 'persona' === $g( 'st_tipo' ) && '1' !== $g( 'st_figura_pubblica' ) ) {
				$e['st_figura_pubblica'] = 'Si possono creare schede solo su persone che hanno un ruolo pubblico.';
			}
		} elseif ( 'esperienza' === $passo ) {
			$rel = $g( 'st_relazione' );
			if ( ! isset( Voti::RELAZIONI[ $rel ] ) ) {
				$e['st_relazione'] = 'Indica che rapporto hai avuto con il soggetto.';
			} elseif ( Voti::RELAZIONI[ $rel ][1] ) {
				$e['st_relazione'] = 'Con questo rapporto c\'è un conflitto di interesse: non puoi recensire il soggetto. Puoi comunque segnalare errori dalla sua scheda.';
			}
			$mese = Punteggi::intero( $g( 'st_mese' ), 1, 12 );
			$anno = Punteggi::intero( $g( 'st_anno' ), 1950, (int) current_time( 'Y' ) );
			if ( null === $mese || null === $anno ) {
				$e['st_quando'] = 'Indica il mese e l\'anno dell\'esperienza.';
			} elseif ( sprintf( '%04d-%02d', $anno, $mese ) > current_time( 'Y-m' ) ) {
				$e['st_quando'] = 'La data dell\'esperienza non può essere nel futuro.';
			}
			if ( ! isset( Voti::FREQUENZE[ $g( 'st_frequenza' ) ] ) ) {
				$e['st_frequenza'] = 'Indica quante volte hai avuto a che fare con il soggetto.';
			}
			if ( ! isset( Voti::CANALI[ $g( 'st_canale' ) ] ) ) {
				$e['st_canale'] = 'Indica come sei entrato in contatto.';
			}
			if ( null === Punteggi::normalizza_stelle( $g( 'st_stelle' ) ) ) {
				$e['st_stelle'] = 'Scegli da 1 a 5 stelle.';
			}
			$l = self::lunghezza( $g( 'st_titolo' ) );
			if ( $l < 5 || $l > Voti::MAX_TITOLO ) {
				$e['st_titolo'] = sprintf( 'Scrivi un titolo da 5 a %d caratteri.', Voti::MAX_TITOLO );
			}
		} elseif ( 'radiografia' === $passo ) {
			$aspetti = Punteggi::normalizza_aspetti( (array) ( isset( $d['st_aspetto'] ) ? $d['st_aspetto'] : array() ), $tipo );
			if ( null === $aspetti ) {
				$e['st_aspetti'] = 'Controlla i voti sugli aspetti: vanno da 1 a 5, oppure «Non so».';
			} elseif ( count( Punteggi::aspetti_valutati( $aspetti ) ) < Punteggi::MIN_ASPETTI ) {
				$e['st_aspetti'] = sprintf( 'Valuta almeno %d aspetti; per gli altri puoi lasciare «Non so».', Punteggi::MIN_ASPETTI );
			}
			if ( null === Punteggi::intero( $g( 'st_consiglia' ), 0, 10 ) ) {
				$e['st_consiglia'] = 'Indica da 0 a 10 quanto lo consiglieresti.';
			}
			if ( ! in_array( $g( 'st_tornerebbe' ), array( 'si', 'no', 'nonso' ), true ) ) {
				$e['st_tornerebbe'] = 'Rispondi «Sì», «No» o «Non so».';
			}
			if ( ! isset( Voti::PROBLEMI[ $g( 'st_problema' ) ] ) ) {
				$e['st_problema'] = 'Indica se ci sono stati problemi.';
			}
		} elseif ( 'racconto' === $passo ) {
			foreach ( array( 'st_pro', 'st_contro' ) as $k ) {
				if ( self::lunghezza( $g( $k ) ) > Voti::MAX_BREVE ) {
					$e[ $k ] = sprintf( 'Massimo %d caratteri.', Voti::MAX_BREVE );
				}
			}
			if ( self::lunghezza( $g( 'st_commento' ) ) > Voti::MAX_TESTO ) {
				$e['st_commento'] = sprintf( 'Il racconto può avere al massimo %d caratteri.', Voti::MAX_TESTO );
			}
			if ( self::lunghezza( trim( $g( 'st_pro' ) . $g( 'st_contro' ) . $g( 'st_commento' ) ) ) < 20 ) {
				$e['st_commento'] = 'Scrivi almeno qualche parola (20 caratteri) fra cosa ti è piaciuto, cosa no e il racconto.';
			}
			if ( '1' !== $g( 'st_nessun_conflitto' ) ) {
				$e['st_nessun_conflitto'] = 'Conferma di non avere legami con il soggetto.';
			}
			if ( '1' !== $g( 'st_veridicita' ) ) {
				$e['st_veridicita'] = 'Conferma che racconti un\'esperienza reale e personale.';
			}
		}
		return $e;
	}

	/** Fonti valide dal modulo. */
	private static function fonti( array $d ) {
		$fonti = array();
		$url   = isset( $d['st_fonte_url'] ) ? (array) $d['st_fonte_url'] : array();
		$desc  = isset( $d['st_fonte_descrizione'] ) ? (array) $d['st_fonte_descrizione'] : array();
		foreach ( $url as $i => $u ) {
			$pulito = esc_url_raw( trim( (string) $u ), array( 'http', 'https' ) );
			if ( $pulito && wp_http_validate_url( $pulito ) ) {
				$fonti[] = array( 'url' => $pulito, 'descrizione' => isset( $desc[ $i ] ) ? (string) $desc[ $i ] : '' );
			}
		}
		return $fonti;
	}

	/** Dati della recensione pronti per Voti::salva(). */
	private static function dati_recensione( array $d, $tipo ) {
		$tornerebbe = isset( $d['st_tornerebbe'] ) ? $d['st_tornerebbe'] : 'nonso';
		return array(
			'stelle'     => Punteggi::normalizza_stelle( $d['st_stelle'] ),
			'titolo'     => (string) $d['st_titolo'],
			'aspetti'    => (array) Punteggi::normalizza_aspetti( (array) $d['st_aspetto'], $tipo ),
			'relazione'  => (string) $d['st_relazione'],
			'esperienza' => sprintf( '%04d-%02d', (int) $d['st_anno'], (int) $d['st_mese'] ),
			'frequenza'  => (string) $d['st_frequenza'],
			'canale'     => (string) $d['st_canale'],
			'consiglia'  => Punteggi::intero( $d['st_consiglia'], 0, 10 ),
			'tornerebbe' => 'si' === $tornerebbe ? 1 : ( 'no' === $tornerebbe ? 0 : null ),
			'problema'   => (string) $d['st_problema'],
			'pro'        => (string) $d['st_pro'],
			'contro'     => (string) $d['st_contro'],
			'commento'   => (string) $d['st_commento'],
		);
	}

	/** Dati di una recensione esistente nel formato del modulo, per modificarla. */
	private static function dati_da_recensione( array $r ) {
		$aspetti = array();
		foreach ( (array) $r['aspetti'] as $k => $v ) {
			$aspetti[ $k ] = (string) $v;
		}
		list( $anno, $mese ) = array_pad( explode( '-', (string) $r['esperienza'] ), 2, '' );
		return array(
			'st_relazione'  => $r['relazione'],
			'st_mese'       => ltrim( $mese, '0' ),
			'st_anno'       => $anno,
			'st_frequenza'  => $r['frequenza'],
			'st_canale'     => $r['canale'],
			'st_stelle'     => (string) $r['stelle'],
			'st_titolo'     => $r['titolo'],
			'st_aspetto'    => $aspetti,
			'st_consiglia'  => null === $r['consiglia'] ? '' : (string) $r['consiglia'],
			'st_tornerebbe' => null === $r['tornerebbe'] ? 'nonso' : ( (int) $r['tornerebbe'] ? 'si' : 'no' ),
			'st_problema'   => $r['problema'],
			'st_pro'        => (string) $r['pro'],
			'st_contro'     => (string) $r['contro'],
			'st_commento'   => (string) $r['commento'],
		);
	}

	/* ---------- Invio dei passi ---------- */

	/** Indirizzo della guida per un passo. */
	private static function url( $base, $passo, $modo ) {
		$url = remove_query_arg( array( 'st_passo', 'st_esito', 'st_msg', 'st_dove' ), $base );
		return add_query_arg( 'st_passo', $passo, $url ) . ( 'proposta' === $modo ? '#st-guida' : '#st-recensisci' );
	}

	public static function gestisci() {
		$in        = wp_unslash( $_POST );
		$modo      = isset( $in['st_modo'] ) && 'recensione' === $in['st_modo'] ? 'recensione' : 'proposta';
		$scheda_id = 'recensione' === $modo && isset( $in['st_scheda'] ) ? absint( $in['st_scheda'] ) : 0;
		$base      = 'recensione' === $modo ? ( $scheda_id ? get_permalink( $scheda_id ) : home_url( '/' ) ) : Vista::url_ritorno();
		$dove      = 'proposta' === $modo ? 'st-guida' : 'st-recensisci';
		$passi     = self::passi( $modo );
		$passo     = isset( $in['st_passo'] ) && in_array( $in['st_passo'], $passi, true ) ? $in['st_passo'] : $passi[0];
		$azione    = isset( $in['st_azione'] ) ? sanitize_key( $in['st_azione'] ) : 'avanti';

		if ( ! isset( $in['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $in['st_nonce'] ), 'st_guida' ) || ! empty( $in['st_sito_web'] ) ) {
			Vista::reindirizza( $base, 'errore', 'richiesta_non_valida', $dove );
		}
		$utente_id = get_current_user_id();
		$tipo      = 'recensione' === $modo ? (string) get_post_meta( $scheda_id, '_st_tipo', true ) : '';

		if ( 'recensione' === $modo ) {
			$esistente = Voti::recensione_utente( $scheda_id, $utente_id );
			if ( ! $scheda_id || get_post_type( $scheda_id ) !== Schede::TIPO ) {
				Vista::reindirizza( home_url( '/' ), 'errore', 'richiesta_non_valida', $dove );
			}
			if ( $esistente && 'annullato' === $esistente['stato'] ) {
				Vista::reindirizza( $base, 'errore', 'recensione_annullata', $dove );
			}
			list( $ammesso, $motivo ) = Regole::puo_recensire( Voti::fatti( $scheda_id, $utente_id, $esistente ), Impostazioni::tutte() );
			if ( ! $ammesso ) {
				self::cancella_stato( $modo, $scheda_id );
				Vista::reindirizza( $base, 'errore', $motivo, $dove );
			}
		}

		$stato = self::stato( $modo, $scheda_id );
		$stato['dati'] = array_merge( $stato['dati'], self::leggi_passo( $passo, $in ) );
		if ( 'proposta' === $modo ) {
			$tipo = isset( $stato['dati']['st_tipo'] ) ? $stato['dati']['st_tipo'] : '';
		}
		$i = array_search( $passo, $passi, true );

		if ( 'indietro' === $azione ) {
			$stato['errori'] = array();
			self::salva_stato( $modo, $scheda_id, $stato );
			wp_safe_redirect( self::url( $base, $passi[ max( 0, $i - 1 ) ], $modo ) );
			exit;
		}

		$errori = self::valida( $passo, $stato['dati'], $tipo, $modo );
		if ( $errori ) {
			$stato['errori'] = $errori;
			self::salva_stato( $modo, $scheda_id, $stato );
			wp_safe_redirect( add_query_arg( array( 'st_esito' => 'errore', 'st_msg' => 'modulo', 'st_dove' => $dove ), remove_query_arg( 'st_passo', $base ) ) . '&st_passo=' . rawurlencode( $passo ) . '#' . $dove );
			exit;
		}
		$stato['errori']          = array();
		$stato['fatti'][ $passo ] = true;

		// Un membro può aggiungere la scheda subito, senza recensione.
		if ( 'proposta' === $modo && 'chi' === $passo && 'aggiungi' === $azione && current_user_can( 'st_aggiungi' ) ) {
			$id = self::crea_scheda( $stato['dati'], true );
			self::cancella_stato( $modo, 0 );
			Vista::reindirizza( $id ? get_permalink( $id ) : $base, $id ? 'ok' : 'errore', $id ? 'scheda_aggiunta' : 'salvataggio', $id ? 'st-scheda' : $dove );
		}

		if ( $i < count( $passi ) - 1 ) {
			self::salva_stato( $modo, $scheda_id, $stato );
			wp_safe_redirect( self::url( $base, $passi[ $i + 1 ], $modo ) );
			exit;
		}

		// Ultimo passo: ricontrolla tutto e salva.
		foreach ( $passi as $p ) {
			$err = self::valida( $p, $stato['dati'], $tipo, $modo );
			if ( $err ) {
				$stato['errori'] = $err;
				self::salva_stato( $modo, $scheda_id, $stato );
				wp_safe_redirect( add_query_arg( array( 'st_esito' => 'errore', 'st_msg' => 'modulo', 'st_dove' => $dove ), remove_query_arg( 'st_passo', $base ) ) . '&st_passo=' . rawurlencode( $p ) . '#' . $dove );
				exit;
			}
		}
		$recensione = self::dati_recensione( $stato['dati'], $tipo );

		if ( 'proposta' === $modo ) {
			$membro = current_user_can( 'st_aggiungi' );
			if ( ! $membro && self::in_attesa( $utente_id ) >= Schede::MAX_IN_ATTESA ) {
				Vista::reindirizza( $base, 'errore', 'troppe_in_attesa', $dove );
			}
			$id = self::crea_scheda( $stato['dati'], $membro );
			if ( ! $id ) {
				Vista::reindirizza( $base, 'errore', 'salvataggio', $dove );
			}
			Voti::salva( $id, $utente_id, $recensione, 'attesa', true );
			self::cancella_stato( $modo, 0 );
			Vista::reindirizza( $membro ? get_permalink( $id ) : $base, 'ok', $membro ? 'scheda_aggiunta_recensita' : 'scheda_inviata', $membro ? 'st-scheda' : $dove );
		}

		$prima  = Voti::recensione_utente( $scheda_id, $utente_id );
		$finale = Voti::salva( $scheda_id, $utente_id, $recensione, $prima ? $prima['stato'] : Voti::stato_nuova( $scheda_id, $utente_id ) );
		self::cancella_stato( $modo, $scheda_id );
		if ( false === $finale ) {
			Vista::reindirizza( $base, 'errore', 'salvataggio', $dove );
		}
		$esito = $prima ? 'recensione_modificata' : ( 'sospeso' === $finale ? 'recensione_sospesa' : ( 'attesa' === $finale ? 'recensione_in_attesa' : 'recensione_salvata' ) );
		Vista::reindirizza( $base, 'ok', $esito, $dove );
	}

	private static function in_attesa( $utente_id ) {
		$q = new \WP_Query(
			array(
				'post_type'      => Schede::TIPO,
				'post_status'    => 'pending',
				'author'         => $utente_id,
				'fields'         => 'ids',
				'posts_per_page' => Schede::MAX_IN_ATTESA,
				'no_found_rows'  => true,
			)
		);
		return count( $q->posts );
	}

	/**
	 * Crea la scheda dal primo passo. Una categoria scritta a mano che coincide
	 * con un settore esistente lo assegna subito; altrimenti resta come
	 * suggerimento per la redazione.
	 */
	private static function crea_scheda( array $d, $pubblica ) {
		$utente_id = get_current_user_id();
		$id        = wp_insert_post(
			array(
				'post_type'    => Schede::TIPO,
				'post_status'  => $pubblica ? 'publish' : 'pending',
				'post_title'   => $d['st_nome'],
				'post_content' => $d['st_sintesi'],
				'post_author'  => $utente_id,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_st_tipo', $d['st_tipo'] );
		update_post_meta( $id, '_st_luogo', isset( $d['st_luogo'] ) ? $d['st_luogo'] : '' );
		update_post_meta( $id, '_st_sito', isset( $d['st_sito'] ) ? esc_url_raw( $d['st_sito'], array( 'http', 'https' ) ) : '' );
		update_post_meta( $id, '_st_fonti', self::fonti( $d ) );
		update_post_meta( $id, '_st_figura_pubblica', '1' === ( isset( $d['st_figura_pubblica'] ) ? $d['st_figura_pubblica'] : '' ) ? 1 : 0 );
		Schede::salva_aggregato( $id, Punteggi::aggrega( array(), Voti::media_generale(), (float) Impostazioni::valore( 'peso_bayes' ) ) );

		$categoria = isset( $d['st_categoria'] ) ? trim( $d['st_categoria'] ) : '';
		if ( '' !== $categoria ) {
			$termine = get_term_by( 'name', $categoria, Schede::SETTORE );
			if ( $termine ) {
				wp_set_object_terms( $id, array( (int) $termine->term_id ), Schede::SETTORE );
			} else {
				update_post_meta( $id, '_st_categoria_suggerita', $categoria );
			}
		}

		Schede::aggiungi_storico( $id, $pubblica ? 'Scheda aggiunta da un membro di statistiche.top.' : 'Scheda proposta dal sito con la recensione di chi la propone, in attesa di revisione.' );
		wp_mail(
			get_option( 'admin_email' ),
			'[statistiche.top] ' . ( $pubblica ? 'Nuova scheda aggiunta: ' : 'Nuova scheda da rivedere: ' ) . $d['st_nome'],
			( $pubblica ? "Un membro ha aggiunto una scheda.\n\n" : "È arrivata una nuova scheda da rivedere.\n\n" ) . admin_url( 'post.php?post=' . $id . '&action=edit' )
			. ( '' !== $categoria ? "\n\nCategoria suggerita: " . $categoria : '' )
		);
		return (int) $id;
	}

	/* ---------- Visualizzazione ---------- */

	public static function shortcode_proposta() {
		Vista::accoda_risorse();
		ob_start();
		echo '<div class="st-blocco" id="st-guida">';
		echo Vista::avviso( 'st-guida' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! is_user_logged_in() ) {
			printf(
				'<p>Per proporre una scheda serve un account con l\'email verificata: chi propone una scheda la recensisce anche, raccontando la propria esperienza. <a href="%1$s">Accedi</a>%2$s.</p></div>',
				esc_url( wp_login_url( get_permalink() ) ),
				get_option( 'users_can_register' ) ? ' oppure <a href="' . esc_url( wp_registration_url() ) . '">registrati</a>' : ''
			);
			return ob_get_clean();
		}
		echo self::modulo( 'proposta', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		return ob_get_clean();
	}

	/** Passo richiesto nell'indirizzo, solo se i passi precedenti sono già stati completati. */
	public static function passo_corrente( $modo, array $stato ) {
		$passi = self::passi( $modo );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$chiesto = isset( $_GET['st_passo'] ) ? sanitize_key( wp_unslash( $_GET['st_passo'] ) ) : $passi[0];
		if ( ! in_array( $chiesto, $passi, true ) ) {
			return $passi[0];
		}
		foreach ( $passi as $p ) {
			if ( $p === $chiesto ) {
				return $p;
			}
			if ( empty( $stato['fatti'][ $p ] ) ) {
				return $p;
			}
		}
		return $passi[0];
	}

	/** Il modulo del passo corrente. */
	public static function modulo( $modo, $scheda_id ) {
		$stato = self::stato( $modo, $scheda_id );
		if ( 'recensione' === $modo && ! $stato['dati'] ) {
			$esistente = Voti::recensione_utente( $scheda_id, get_current_user_id() );
			if ( $esistente && $esistente['stelle'] ) {
				$stato['dati']  = self::dati_da_recensione( $esistente );
				$stato['fatti'] = array_fill_keys( self::passi( $modo ), true );
				self::salva_stato( $modo, $scheda_id, $stato );
			}
		}
		$passi  = self::passi( $modo );
		$passo  = self::passo_corrente( $modo, $stato );
		$n      = array_search( $passo, $passi, true ) + 1;
		$tot    = count( $passi );
		$d      = $stato['dati'];
		$errori = $stato['errori'];
		$tipo   = 'recensione' === $modo ? (string) get_post_meta( $scheda_id, '_st_tipo', true ) : ( isset( $d['st_tipo'] ) ? $d['st_tipo'] : 'azienda' );
		$f      = new Campi( $d, $errori );

		ob_start();
		?>
		<nav class="st-passi" aria-label="Passi della <?php echo 'proposta' === $modo ? 'proposta' : 'recensione'; ?>">
			<ol>
				<?php foreach ( $passi as $k => $p ) : ?>
					<?php $corrente = $p === $passo; ?>
					<li<?php echo $corrente ? ' aria-current="step" class="st-passo-corrente"' : ''; ?>>
						<span class="st-passo-numero" aria-hidden="true"><?php echo esc_html( $k + 1 ); ?></span>
						<?php echo esc_html( self::PASSI[ $p ] ); ?>
						<?php if ( ! $corrente && ! empty( $stato['fatti'][ $p ] ) ) : ?>
							<span class="st-passo-fatto">(fatto)</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php $h = 'proposta' === $modo ? 'h2' : 'h3'; ?>
		<<?php echo esc_html( $h ); ?> class="st-passo-titolo" id="st-passo-titolo" tabindex="-1">Passo <?php echo esc_html( $n ); ?> di <?php echo esc_html( $tot ); ?>: <?php echo esc_html( self::PASSI[ $passo ] ); ?></<?php echo esc_html( $h ); ?>>
		<?php if ( $errori ) : ?>
			<div class="st-avviso st-avviso--errore" role="alert" tabindex="-1" data-st-focus>
				<p><strong>Attenzione:</strong> <?php echo esc_html( 1 === count( $errori ) ? 'un campo va corretto.' : count( $errori ) . ' campi vanno corretti.' ); ?></p>
				<ul>
					<?php foreach ( $errori as $campo => $testo ) : ?>
						<li><a href="#<?php echo esc_attr( Campi::id( $campo ) ); ?>"><?php echo esc_html( $testo ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<form class="st-modulo st-modulo-proposta" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate aria-labelledby="st-passo-titolo">
			<input type="hidden" name="action" value="st_guida">
			<input type="hidden" name="st_modo" value="<?php echo esc_attr( $modo ); ?>">
			<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $scheda_id ); ?>">
			<input type="hidden" name="st_passo" value="<?php echo esc_attr( $passo ); ?>">
			<?php wp_nonce_field( 'st_guida', 'st_nonce', false ); ?>
			<?php Vista::trappola(); ?>
			<p class="st-nota">I campi obbligatori sono indicati. Il pulsante <strong>?</strong> accanto a ogni parte apre una spiegazione.</p>
			<?php
			switch ( $passo ) {
				case 'chi':
					self::passo_chi( $f );
					break;
				case 'esperienza':
					self::passo_esperienza( $f );
					break;
				case 'radiografia':
					self::passo_radiografia( $f, $tipo );
					break;
				case 'racconto':
					self::passo_racconto( $f, $d, $tipo, $modo );
					break;
			}
			?>
			<div class="st-guida-pulsanti">
				<?php if ( $n < $tot ) : ?>
					<button type="submit" class="st-pulsante" name="st_azione" value="avanti">Avanti: <?php echo esc_html( self::PASSI[ $passi[ $n ] ] ); ?></button>
				<?php else : ?>
					<button type="submit" class="st-pulsante" name="st_azione" value="invia"><?php echo 'proposta' === $modo ? 'Invia la scheda e la recensione' : 'Pubblica la recensione'; ?></button>
				<?php endif; ?>
				<?php if ( $n > 1 ) : ?>
					<button type="submit" class="st-pulsante st-pulsante--secondario" name="st_azione" value="indietro" formnovalidate>Indietro</button>
				<?php endif; ?>
				<?php if ( 'proposta' === $modo && 'chi' === $passo && current_user_can( 'st_aggiungi' ) ) : ?>
					<button type="submit" class="st-pulsante st-pulsante--secondario" name="st_azione" value="aggiungi">Aggiungi la scheda senza recensione</button>
				<?php endif; ?>
			</div>
			<?php if ( 'proposta' === $modo && 'chi' === $passo && current_user_can( 'st_aggiungi' ) ) : ?>
				<p class="st-nota">Come membro di statistiche.top puoi aggiungere la scheda subito, senza recensione: sarà pubblicata direttamente.</p>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function passo_chi( Campi $f ) {
		$f->radio( 'st_tipo', 'La scheda riguarda', array( 'persona' => 'una persona con un ruolo pubblico', 'azienda' => 'un\'azienda o un\'organizzazione' ), 'tipo', true );
		$f->testo( 'st_nome', 'Nome', 'nome', true, array( 'maxlength' => 150 ) );
		$f->testo( 'st_sito', 'Sito ufficiale', 'sito', false, array( 'type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://' ) );
		$f->testo( 'st_luogo', 'Luogo (città, regione o paese)', 'luogo', false, array( 'maxlength' => 100 ) );
		$settori = get_terms( array( 'taxonomy' => Schede::SETTORE, 'hide_empty' => false ) );
		$f->testo( 'st_categoria', 'In che categoria la metteresti?', 'categoria', false, array( 'maxlength' => self::MAX_CATEGORIA, 'list' => 'st-settori' ) );
		echo '<datalist id="st-settori">';
		foreach ( is_array( $settori ) ? $settori : array() as $t ) {
			printf( '<option value="%s"></option>', esc_attr( $t->name ) );
		}
		echo '</datalist>';
		$f->area( 'st_sintesi', sprintf( 'Sintesi (da 30 a %d caratteri)', Schede::MAX_SINTESI ), 'sintesi', true, Schede::MAX_SINTESI, 5, true );
		$f->fonti();
		$f->spunta( 'st_figura_pubblica', 'Se è una persona: confermo che ha un ruolo pubblico e che la scheda riguarda solo quel ruolo.', 'figura', false );
	}

	private static function passo_esperienza( Campi $f ) {
		$relazioni = array();
		foreach ( Voti::RELAZIONI as $k => $r ) {
			$relazioni[ $k ] = $r[0] . ( $r[1] ? ' (conflitto di interesse: non puoi recensire)' : '' );
		}
		$f->radio( 'st_relazione', 'Che rapporto hai avuto con il soggetto?', $relazioni, 'relazione', true );
		$f->quando();
		$f->radio( 'st_frequenza', 'Quante volte?', Voti::FREQUENZE, 'frequenza', true, true );
		$f->radio( 'st_canale', 'Come?', Voti::CANALI, 'canale', true, true );
		$f->stelle();
		$f->testo( 'st_titolo', 'Titolo della recensione', 'titolo', true, array( 'maxlength' => Voti::MAX_TITOLO ) );
	}

	private static function passo_radiografia( Campi $f, $tipo ) {
		$f->aspetti( Punteggi::aspetti( $tipo ) );
		$scala = array();
		for ( $i = 0; $i <= 10; $i++ ) {
			$scala[ (string) $i ] = (string) $i;
		}
		$f->radio( 'st_consiglia', 'Quanto lo consiglieresti a un amico o a un collega? (0 = per niente, 10 = sicuramente)', $scala, 'consiglia', true, true, true );
		$f->radio( 'st_tornerebbe', 'Ti rivolgeresti di nuovo a lui?', array( 'si' => 'Sì', 'no' => 'No', 'nonso' => 'Non so' ), 'tornerebbe', true, true );
		$f->radio( 'st_problema', 'Ci sono stati problemi?', Voti::PROBLEMI, 'problema', true );
	}

	private static function passo_racconto( Campi $f, array $d, $tipo, $modo ) {
		$f->area( 'st_pro', 'Cosa ti è piaciuto', 'pro', false, Voti::MAX_BREVE, 3 );
		$f->area( 'st_contro', 'Cosa non ti è piaciuto', 'contro', false, Voti::MAX_BREVE, 3 );
		$f->area( 'st_commento', 'Racconta la tua esperienza', 'racconto', false, Voti::MAX_TESTO, 6 );
		self::riepilogo( $d, $tipo, $modo );
		echo '<fieldset><legend class="st-legenda-con-aiuto">Dichiarazioni <span class="st-obbligatorio">(obbligatorie)</span></legend><div class="st-campo-testa st-campo-testa--gruppo">';
		Vista::pulsante_aiuto( 'dichiarazioni', 'dichiarazioni' );
		echo '</div>';
		Vista::balloon( 'dichiarazioni', self::AIUTI['dichiarazioni'] );
		$f->spunta( 'st_nessun_conflitto', 'Non sono il soggetto della scheda, né un suo dipendente, socio, familiare o concorrente.', '', true );
		$f->spunta( 'st_veridicita', 'Racconto un\'esperienza reale e personale, senza compensi o vantaggi in cambio.', '', true );
		echo '</fieldset>';
		echo '<div class="st-campo"><div class="st-campo-testa"><p class="st-nota" id="st-aiuto-invio-nota">Che cosa succede dopo l\'invio?</p>';
		Vista::pulsante_aiuto( 'invio', 'che cosa succede dopo l\'invio' );
		echo '</div>';
		Vista::balloon( 'invio', self::AIUTI['invio'] );
		echo '</div>';
	}

	/** Riepilogo di quanto inserito, prima dell'invio. */
	private static function riepilogo( array $d, $tipo, $modo ) {
		$g = static function ( $k ) use ( $d ) {
			return isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? (string) $d[ $k ] : '';
		};
		$righe = array();
		if ( 'proposta' === $modo ) {
			$righe['Scheda']    = $g( 'st_nome' ) . ( $g( 'st_luogo' ) ? ' — ' . $g( 'st_luogo' ) : '' );
			$righe['Categoria'] = $g( 'st_categoria' ) ? $g( 'st_categoria' ) : 'decide la redazione';
			$righe['Fonti']     = (string) count( self::fonti( $d ) );
		}
		$rel                     = $g( 'st_relazione' );
		$righe['Rapporto']       = isset( Voti::RELAZIONI[ $rel ] ) ? Voti::RELAZIONI[ $rel ][0] : '—';
		$mese                    = (int) $g( 'st_mese' );
		$righe['Quando']         = ( $mese >= 1 && $mese <= 12 ? date_i18n( 'F', mktime( 0, 0, 0, $mese, 1 ) ) . ' ' : '' ) . $g( 'st_anno' );
		$righe['Valutazione']    = $g( 'st_stelle' ) . ' stelle su 5 — «' . $g( 'st_titolo' ) . '»';
		$valutati                = Punteggi::aspetti_valutati( (array) Punteggi::normalizza_aspetti( isset( $d['st_aspetto'] ) ? (array) $d['st_aspetto'] : array(), $tipo ) );
		$righe['Aspetti valutati'] = count( $valutati ) . ' su ' . count( Punteggi::aspetti( $tipo ) );
		$righe['Lo consiglieresti'] = '' !== $g( 'st_consiglia' ) ? $g( 'st_consiglia' ) . ' su 10' : '—';
		$h = 'proposta' === $modo ? 'h3' : 'h4';
		printf( '<section class="st-riepilogo" aria-labelledby="st-riepilogo-titolo"><%1$s id="st-riepilogo-titolo">Riepilogo</%1$s><dl>', esc_html( $h ) );
		foreach ( $righe as $etichetta => $valore ) {
			printf( '<div><dt>%1$s</dt><dd>%2$s</dd></div>', esc_html( $etichetta ), esc_html( $valore ) );
		}
		echo '</dl><p class="st-nota">Per correggere qualcosa usa «Indietro»: i dati restano salvati.</p></section>';
	}
}

/**
 * Generatore dei campi della guida: etichetta, aiuto a fumetto, errore e
 * collegamenti aria-describedby, sempre nello stesso modo.
 */
final class Campi {

	private $d;
	private $e;

	public function __construct( array $dati, array $errori ) {
		$this->d = $dati;
		$this->e = $errori;
	}

	public static function id( $campo ) {
		return 'st-campo-' . str_replace( '_', '-', preg_replace( '/^st_/', '', $campo ) );
	}

	private function v( $k ) {
		return isset( $this->d[ $k ] ) && is_scalar( $this->d[ $k ] ) ? (string) $this->d[ $k ] : '';
	}

	private function errore( $campo ) {
		if ( isset( $this->e[ $campo ] ) ) {
			printf( '<span class="st-errore" id="%1$s-errore"><strong>Errore:</strong> %2$s</span>', esc_attr( self::id( $campo ) ), esc_html( $this->e[ $campo ] ) );
		}
	}

	private function descritto( $campo, $aiuto, $gruppo = false ) {
		$id = array_filter( array( $aiuto ? 'st-aiuto-' . $aiuto : '', isset( $this->e[ $campo ] ) ? self::id( $campo ) . '-errore' : '' ) );
		$out = $id ? ' aria-describedby="' . esc_attr( implode( ' ', $id ) ) . '"' : '';
		if ( isset( $this->e[ $campo ] ) ) {
			$out .= $gruppo ? ' class="st-gruppo-errato"' : ' aria-invalid="true"';
		}
		return $out;
	}

	private function obbligatorio( $si ) {
		return $si ? ' <span class="st-obbligatorio">(obbligatorio)</span>' : '';
	}

	private function testa( $per, $etichetta, $aiuto, $obbligatorio, $nome_aiuto = '' ) {
		echo '<div class="st-campo-testa">';
		printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $per ), esc_html( $etichetta ), $this->obbligatorio( $obbligatorio ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $aiuto ) {
			Vista::pulsante_aiuto( $aiuto, $nome_aiuto ? $nome_aiuto : $etichetta );
		}
		echo '</div>';
		if ( $aiuto ) {
			Vista::balloon( $aiuto, Guida::AIUTI[ $aiuto ] );
		}
	}

	private function legenda( $etichetta, $aiuto, $obbligatorio ) {
		printf( '<legend class="st-legenda-con-aiuto">%1$s%2$s</legend>', esc_html( $etichetta ), $this->obbligatorio( $obbligatorio ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $aiuto ) {
			echo '<div class="st-campo-testa st-campo-testa--gruppo">';
			Vista::pulsante_aiuto( $aiuto, $etichetta );
			echo '</div>';
			Vista::balloon( $aiuto, Guida::AIUTI[ $aiuto ] );
		}
	}

	public function testo( $campo, $etichetta, $aiuto, $obbligatorio, array $attr = array() ) {
		$id   = self::id( $campo );
		$tipo = isset( $attr['type'] ) ? $attr['type'] : 'text';
		unset( $attr['type'] );
		echo '<div class="st-campo">';
		$this->testa( $id, $etichetta, $aiuto, $obbligatorio );
		$extra = '';
		foreach ( $attr as $k => $v ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
		}
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s>',
			esc_attr( $tipo ),
			esc_attr( $id ),
			esc_attr( $campo ),
			esc_attr( $this->v( $campo ) ),
			$obbligatorio ? ' required' : '',
			$extra, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->descritto( $campo, $aiuto ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		$this->errore( $campo );
		echo '</div>';
	}

	public function area( $campo, $etichetta, $aiuto, $obbligatorio, $max, $righe, $contatore = false ) {
		$id = self::id( $campo );
		echo '<div class="st-campo">';
		$this->testa( $id, $etichetta, $aiuto, $obbligatorio );
		$descr = $this->descritto( $campo, $aiuto );
		if ( $contatore ) {
			$descr = str_replace( 'aria-describedby="', 'aria-describedby="' . $id . '-contatore ', $descr ? $descr : ' aria-describedby=""' );
		}
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d" maxlength="%4$d"%5$s%6$s%7$s>%8$s</textarea>',
			esc_attr( $id ),
			esc_attr( $campo ),
			(int) $righe,
			(int) $max,
			$obbligatorio ? ' required' : '',
			$contatore ? ' data-st-contatore="' . esc_attr( $id . '-contatore' ) . '"' : '',
			$descr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_textarea( $this->v( $campo ) )
		);
		if ( $contatore ) {
			printf( '<span id="%s-contatore" class="st-nota" aria-live="polite"></span>', esc_attr( $id ) );
		}
		$this->errore( $campo );
		echo '</div>';
	}

	/**
	 * Gruppo di scelte (radio). $in_linea mette le scelte su una riga (scale brevi);
	 * $scala usa i riquadri da 44 px come per le stelle.
	 */
	public function radio( $campo, $etichetta, array $scelte, $aiuto, $obbligatorio, $in_linea = false, $scala = false ) {
		$id = self::id( $campo );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( $campo, $aiuto, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->legenda( $etichetta, $aiuto, $obbligatorio );
		$this->errore( $campo );
		echo $scala ? '<div class="st-voto-scelte st-scala">' : ( $in_linea ? '<div class="st-scelte-in-linea">' : '<div>' );
		foreach ( $scelte as $valore => $testo ) {
			$opz = $id . '-' . sanitize_html_class( (string) $valore );
			if ( $scala ) {
				printf(
					'<input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s><label for="%1$s">%5$s</label>',
					esc_attr( $opz ),
					esc_attr( $campo ),
					esc_attr( $valore ),
					checked( $this->v( $campo ), (string) $valore, false ),
					esc_html( $testo )
				);
			} else {
				printf(
					'<p class="st-spunta"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s><label for="%1$s">%5$s</label></p>',
					esc_attr( $opz ),
					esc_attr( $campo ),
					esc_attr( $valore ),
					checked( $this->v( $campo ), (string) $valore, false ),
					esc_html( $testo )
				);
			}
		}
		echo '</div></fieldset>';
	}

	public function spunta( $campo, $etichetta, $aiuto, $obbligatorio ) {
		$id = self::id( $campo );
		echo '<div class="st-campo"><p class="st-spunta">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s><label for="%1$s">%6$s%7$s</label>',
			esc_attr( $id ),
			esc_attr( $campo ),
			checked( $this->v( $campo ), '1', false ),
			$obbligatorio ? ' required' : '',
			$this->descritto( $campo, $aiuto ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $etichetta ),
			$this->obbligatorio( $obbligatorio ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		if ( $aiuto ) {
			Vista::pulsante_aiuto( $aiuto, $etichetta );
		}
		echo '</p>';
		if ( $aiuto ) {
			Vista::balloon( $aiuto, Guida::AIUTI[ $aiuto ] );
		}
		$this->errore( $campo );
		echo '</div>';
	}

	/** Mese e anno dell'esperienza. */
	public function quando() {
		$id = self::id( 'st_quando' );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( 'st_quando', 'quando', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->legenda( 'Quando?', 'quando', true );
		$this->errore( 'st_quando' );
		echo '<div class="st-scelte-in-linea">';
		echo '<p><label for="st-campo-mese">Mese</label><select id="st-campo-mese" name="st_mese"><option value="">Scegli…</option>';
		for ( $m = 1; $m <= 12; $m++ ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $m, selected( $this->v( 'st_mese' ), (string) $m, false ), esc_html( date_i18n( 'F', mktime( 0, 0, 0, $m, 1 ) ) ) );
		}
		echo '</select></p><p><label for="st-campo-anno">Anno</label><select id="st-campo-anno" name="st_anno"><option value="">Scegli…</option>';
		$oggi = (int) current_time( 'Y' );
		for ( $a = $oggi; $a >= $oggi - 30; $a-- ) {
			printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $a, selected( $this->v( 'st_anno' ), (string) $a, false ) );
		}
		echo '</select></p></div></fieldset>';
	}

	/** Stelle complessive da 1 a 5, con il significato scritto accanto al numero. */
	public function stelle() {
		$nomi = array( 1 => 'pessima', 2 => 'scarsa', 3 => 'nella media', 4 => 'buona', 5 => 'ottima' );
		$id   = self::id( 'st_stelle' );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( 'st_stelle', 'stelle', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->legenda( 'Valutazione complessiva', 'stelle', true );
		$this->errore( 'st_stelle' );
		echo '<div class="st-voto-scelte st-stelle-scelta">';
		foreach ( $nomi as $n => $nome ) {
			printf(
				'<input type="radio" id="%1$s-%2$d" name="st_stelle" value="%2$d"%3$s><label for="%1$s-%2$d"><span aria-hidden="true">%4$s</span> %2$d <span class="st-stelle-nome">%5$s</span></label>',
				esc_attr( $id ),
				(int) $n,
				checked( $this->v( 'st_stelle' ), (string) $n, false ),
				esc_html( str_repeat( '★', $n ) ),
				esc_html( $nome )
			);
		}
		echo '</div></fieldset>';
	}

	/** La radiografia: un gruppo per ogni aspetto, con "Non so" già scelto. */
	public function aspetti( array $aspetti ) {
		$valori = isset( $this->d['st_aspetto'] ) ? (array) $this->d['st_aspetto'] : array();
		$id     = self::id( 'st_aspetti' );
		printf( '<div class="st-campo st-aspetti" id="%1$s">', esc_attr( $id ) );
		echo '<div class="st-campo-testa"><p class="st-etichetta-gruppo"><strong>Valuta gli aspetti</strong> <span class="st-obbligatorio">(almeno ' . (int) Punteggi::MIN_ASPETTI . ')</span></p>';
		Vista::pulsante_aiuto( 'aspetti', 'aspetti' );
		echo '</div>';
		Vista::balloon( 'aspetti', Guida::AIUTI['aspetti'] );
		$this->errore( 'st_aspetti' );
		foreach ( $aspetti as $chiave => $nome ) {
			$attuale = isset( $valori[ $chiave ] ) ? (string) $valori[ $chiave ] : '0';
			printf( '<fieldset class="st-voto"%s><legend>%s</legend><div class="st-voto-scelte">', isset( $this->e['st_aspetti'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-errore"' : '', esc_html( $nome ) );
			foreach ( array( '0' => 'Non so', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ) as $valore => $testo ) {
				$opz = 'st-aspetto-' . $chiave . '-' . $valore;
				printf(
					'<input type="radio" id="%1$s" name="st_aspetto[%2$s]" value="%3$s"%4$s><label for="%1$s"%6$s>%5$s</label>',
					esc_attr( $opz ),
					esc_attr( $chiave ),
					esc_attr( $valore ),
					checked( $attuale, (string) $valore, false ),
					esc_html( $testo ),
					'0' === $valore ? ' class="st-non-so"' : ''
				);
			}
			echo '</div></fieldset>';
		}
		echo '</div>';
	}

	/** Le fonti (fino a tre), nel primo passo. */
	public function fonti() {
		$url  = isset( $this->d['st_fonte_url'] ) ? (array) $this->d['st_fonte_url'] : array();
		$desc = isset( $this->d['st_fonte_descrizione'] ) ? (array) $this->d['st_fonte_descrizione'] : array();
		$id   = self::id( 'st_fonte_url' );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( 'st_fonte_url', 'fonti', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<legend class="st-legenda-con-aiuto">Fonti verificabili <span class="st-obbligatorio">(almeno una)</span></legend><div class="st-campo-testa st-campo-testa--gruppo">' );
		Vista::pulsante_aiuto( 'fonti', 'fonti' );
		echo '</div>';
		Vista::balloon( 'fonti', Guida::AIUTI['fonti'] );
		$this->errore( 'st_fonte_url' );
		for ( $i = 0; $i < 3; $i++ ) {
			printf(
				'<div class="st-fonte"><p><label for="st-fonte-url-%1$d">Indirizzo della fonte %2$d</label><input type="url" id="st-fonte-url-%1$d" name="st_fonte_url[]" inputmode="url" placeholder="https://" value="%3$s"%4$s></p><p><label for="st-fonte-desc-%1$d">Che cosa conferma la fonte %2$d</label><input type="text" id="st-fonte-desc-%1$d" name="st_fonte_descrizione[]" maxlength="150" value="%5$s"></p></div>',
				(int) $i,
				(int) $i + 1,
				esc_attr( isset( $url[ $i ] ) ? $url[ $i ] : '' ),
				0 === $i ? ' required' : '',
				esc_attr( isset( $desc[ $i ] ) ? $desc[ $i ] : '' )
			);
		}
		echo '</fieldset>';
	}
}
