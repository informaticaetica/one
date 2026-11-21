<?php
/**
 * Regole di ammissione delle recensioni (difesa dalle manipolazioni).
 *
 * Classe senza dipendenze da WordPress: riceve i fatti già raccolti e decide.
 * Il collaudo è in tests/test-logica.php.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ST_TEST' ) || defined( 'ABSPATH' ) || exit;

final class Regole {

	/**
	 * Decide se un utente può lasciare (o modificare) una recensione.
	 *
	 * @param array $fatti {
	 *     @type int  $eta_account_giorni   giorni dalla registrazione.
	 *     @type int  $giorni_attivita      giorni distinti in cui l'utente ha fatto accesso.
	 *     @type int  $recensioni_24h       recensioni nuove nelle ultime 24 ore.
	 *     @type bool $e_autore_scheda      ha creato lui la scheda.
	 *     @type bool $e_referente          è il referente verificato del soggetto.
	 *     @type bool $conflitto_dichiarato ha dichiarato un legame con il soggetto.
	 *     @type bool $scheda_pubblicata    la scheda è pubblicata.
	 *     @type int|null $giorni_dalla_recensione null se è una recensione nuova.
	 * }
	 * @param array $impostazioni eta_minima_giorni, giorni_attivita_minimi, limite_giornaliero, giorni_modifica.
	 * @return array{0: bool, 1: string} esito e motivo (codice) del rifiuto.
	 */
	public static function puo_recensire( array $fatti, array $impostazioni ) {
		if ( empty( $fatti['scheda_pubblicata'] ) ) {
			return array( false, 'scheda_non_pubblicata' );
		}
		$modifica = isset( $fatti['giorni_dalla_recensione'] ) && null !== $fatti['giorni_dalla_recensione'];
		// Chi ha proposto la scheda l'ha già recensita nella proposta: può modificare
		// quella recensione, non aggiungerne un'altra.
		if ( ! empty( $fatti['e_autore_scheda'] ) && ! $modifica ) {
			return array( false, 'autore_scheda' );
		}
		if ( ! empty( $fatti['e_referente'] ) ) {
			return array( false, 'referente' );
		}
		if ( ! empty( $fatti['conflitto_dichiarato'] ) ) {
			return array( false, 'conflitto' );
		}
		if ( (int) $fatti['eta_account_giorni'] < (int) $impostazioni['eta_minima_giorni'] ) {
			return array( false, 'account_recente' );
		}
		if ( (int) $fatti['giorni_attivita'] < (int) $impostazioni['giorni_attivita_minimi'] ) {
			return array( false, 'attivita_insufficiente' );
		}

		if ( $modifica ) {
			if ( (int) $fatti['giorni_dalla_recensione'] >= (int) $impostazioni['giorni_modifica'] ) {
				return array( false, 'modifica_scaduta' );
			}
			return array( true, '' );
		}

		if ( (int) $fatti['recensioni_24h'] >= (int) $impostazioni['limite_giornaliero'] ) {
			return array( false, 'limite_giornaliero' );
		}
		return array( true, '' );
	}

	/**
	 * Una recensione da un indirizzo IP già usato da un altro utente sulla stessa
	 * scheda non viene rifiutata, ma sospesa finché un moderatore non la guarda.
	 *
	 * @param int $altri_utenti_stesso_ip utenti diversi che hanno già recensito la scheda dallo stesso IP.
	 * @return string 'valido' o 'sospeso'.
	 */
	public static function stato_iniziale( $altri_utenti_stesso_ip ) {
		return (int) $altri_utenti_stesso_ip > 0 ? 'sospeso' : 'valido';
	}

	/** Messaggi per l'utente, in italiano semplice. */
	public static function messaggio( $codice, array $impostazioni = array() ) {
		$eta   = isset( $impostazioni['eta_minima_giorni'] ) ? (int) $impostazioni['eta_minima_giorni'] : 7;
		$att   = isset( $impostazioni['giorni_attivita_minimi'] ) ? (int) $impostazioni['giorni_attivita_minimi'] : 2;
		$lim   = isset( $impostazioni['limite_giornaliero'] ) ? (int) $impostazioni['limite_giornaliero'] : 10;
		$mod   = isset( $impostazioni['giorni_modifica'] ) ? (int) $impostazioni['giorni_modifica'] : 30;
		$testi = array(
			'scheda_non_pubblicata'  => 'Questa scheda non è ancora pubblicata.',
			'autore_scheda'          => 'Hai aggiunto tu questa scheda: se l\'hai proposta con la tua recensione, puoi modificarla; altrimenti non puoi recensirla.',
			'referente'              => 'Il referente del soggetto può rispondere, ma non votare.',
			'conflitto'              => 'Hai dichiarato un legame con questo soggetto: non puoi recensirlo.',
			'account_recente'        => sprintf( 'Puoi recensire quando il tuo account avrà almeno %d giorni.', $eta ),
			'attivita_insufficiente' => sprintf( 'Puoi recensire dopo aver fatto accesso al sito in almeno %d giorni diversi.', $att ),
			'modifica_scaduta'       => sprintf( 'La recensione si può modificare solo entro %d giorni.', $mod ),
			'limite_giornaliero'     => sprintf( 'Hai raggiunto il limite di %d recensioni in 24 ore. Riprova domani.', $lim ),
			'voti_non_validi'        => 'Controlla i voti: le stelle vanno da 1 a 5.',
			'dichiarazione'          => 'Conferma di non avere legami con il soggetto.',
			'non_autenticato'        => 'Accedi per lasciare una recensione.',
			'richiesta_non_valida'   => 'La richiesta non è valida. Ricarica la pagina e riprova.',
		);
		return isset( $testi[ $codice ] ) ? $testi[ $codice ] : 'Operazione non consentita.';
	}
}
