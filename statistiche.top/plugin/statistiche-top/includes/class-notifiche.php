<?php
/**
 * Email di conferma a chi propone schede e scrive recensioni.
 *
 * Si scrive all'indirizzo email dell'account (verificato alla registrazione):
 * - quando una scheda proposta viene pubblicata dalla redazione;
 * - quando una recensione diventa valida e conta nelle statistiche
 *   (subito, dopo il controllo della redazione o dopo i 7 giorni dell'account).
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Notifiche {

	public static function avvia() {
		add_action( 'transition_post_status', array( __CLASS__, 'scheda_pubblicata' ), 30, 3 );
		add_action( 'st_recensione_valida', array( __CLASS__, 'recensione_valida' ) );
	}

	private static function invia( $utente_id, $oggetto, $testo ) {
		$u = get_userdata( (int) $utente_id );
		if ( ! $u || ! is_email( $u->user_email ) ) {
			return false;
		}
		$firma = "\n\n— La redazione di " . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . "\n" . home_url( '/' )
			. "\n\nHai ricevuto questa email perché hai un account su " . wp_parse_url( home_url(), PHP_URL_HOST ) . '.';
		return wp_mail( $u->user_email, '[' . wp_parse_url( home_url(), PHP_URL_HOST ) . '] ' . $oggetto, 'Ciao ' . $u->display_name . ",\n\n" . $testo . $firma );
	}

	/** La redazione ha pubblicato una scheda proposta da un utente. */
	public static function scheda_pubblicata( $nuovo, $vecchio, $post ) {
		if ( Schede::TIPO !== $post->post_type || 'publish' !== $nuovo || 'pending' !== $vecchio ) {
			return;
		}
		$autore = (int) $post->post_author;
		$voto   = Voti::recensione_utente( $post->ID, $autore );
		$testo  = 'la scheda «' . $post->post_title . "» che hai proposto è stata approvata ed è pubblicata:\n" . get_permalink( $post->ID );
		if ( $voto && 'attesa' === $voto['stato'] ) {
			$giorni = (int) Impostazioni::valore( 'eta_minima_giorni' );
			$testo .= "\n\nLa tua recensione conterà nelle statistiche quando il tuo account avrà almeno $giorni giorni: ti scriveremo quando succede.";
		}
		self::invia( $autore, 'La tua scheda è pubblicata', $testo );
	}

	/** Una recensione è diventata valida. */
	public static function recensione_valida( $voto_id ) {
		$voto = Voti::recensione( (int) $voto_id );
		if ( ! $voto || 'publish' !== get_post_status( (int) $voto['scheda_id'] ) ) {
			return;
		}
		$titolo = get_the_title( (int) $voto['scheda_id'] );
		self::invia(
			(int) $voto['utente_id'],
			'La tua recensione è pubblicata',
			'la tua recensione su «' . $titolo . "» è pubblicata e conta nelle statistiche di gradimento:\n"
			. get_permalink( (int) $voto['scheda_id'] ) . '#st-recensione-' . (int) $voto['id']
			. "\n\nPuoi modificarla entro " . (int) Impostazioni::valore( 'giorni_modifica' ) . ' giorni dalla pubblicazione.'
		);
	}
}
