<?php
/**
 * Email della redazione e di chi scrive.
 *
 * Alla redazione (email dell'amministratore e indirizzo "email_notifiche" delle
 * impostazioni) arriva ogni invio: scheda nuova, recensione, conferma dell'email,
 * richiesta "Crea la tua scheda".
 *
 * A chi scrive, all'indirizzo dell'account: conferma quando la scheda proposta è
 * pubblicata e quando la recensione conta nelle statistiche.
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

	private static function sito() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/** Indirizzi della redazione, senza doppioni. */
	public static function destinatari() {
		$indirizzi = array( get_option( 'admin_email' ), (string) Impostazioni::valore( 'email_notifiche' ) );
		return array_values( array_unique( array_filter( array_map( 'sanitize_email', $indirizzi ), 'is_email' ) ) );
	}

	public static function redazione( $oggetto, $testo ) {
		return wp_mail( self::destinatari(), '[' . self::sito() . '] ' . $oggetto, $testo . "\n\n— " . self::sito() );
	}

	private static function invia( $utente_id, $oggetto, $testo ) {
		$u = get_userdata( (int) $utente_id );
		if ( ! $u || ! is_email( $u->user_email ) ) {
			return false;
		}
		$firma = "\n\n— La redazione di " . self::sito() . "\n" . home_url( '/' )
			. "\n\nHai ricevuto questa email perché hai scritto una recensione su " . self::sito() . '.';
		return wp_mail( $u->user_email, '[' . self::sito() . '] ' . $oggetto, "Ciao,\n\n" . $testo . $firma );
	}

	/** Ogni invio del modulo arriva alla redazione, con quello che serve per decidere. */
	public static function redazione_nuovo_invio( $scheda_id, $nuova, $utente_id, array $d, $stato ) {
		$u     = get_userdata( (int) $utente_id );
		$tipo  = (string) get_post_meta( $scheda_id, '_st_tipo', true );
		$righe = array(
			( $nuova ? 'NUOVA SCHEDA' : 'Recensione su una scheda esistente' ) . ': ' . get_the_title( $scheda_id ) . ' (' . ( isset( Schede::TIPI_SOGGETTO[ $tipo ] ) ? Schede::TIPI_SOGGETTO[ $tipo ] : $tipo ) . ')',
			'Stato: ' . ( isset( Voti::NOMI_STATI[ $stato ] ) ? Voti::NOMI_STATI[ $stato ] : $stato ),
			'Stelle: ' . (int) $d['st_stelle'] . ' su 5',
			'Categoria indicata: ' . ( '' !== $d['st_categoria'] ? $d['st_categoria'] : '—' ),
			'Email: ' . ( $u ? $u->user_email : '—' ),
			'Telefono: ' . ( '' !== $d['st_telefono'] ? $d['st_telefono'] : '—' ),
			'Rapporto: ' . ( isset( Voti::RELAZIONI[ $d['st_relazione'] ] ) ? Voti::RELAZIONI[ $d['st_relazione'] ][0] : 'non indicato' ),
			'IP: ' . Registro::ip(),
			'',
			'Recensione:',
			$d['st_recensione'],
			'',
			'Moderazione: ' . admin_url( 'admin.php?page=st-recensioni' ),
		);
		if ( $nuova ) {
			$righe[] = 'Scheda: ' . admin_url( 'post.php?post=' . (int) $scheda_id . '&action=edit' );
		}
		if ( 'conferma' === $stato ) {
			$righe[] = '';
			$righe[] = 'Chi ha scritto non ha ancora confermato l\'email: la recensione compare in moderazione dopo la conferma.';
		}
		self::redazione( ( $nuova ? 'Nuova scheda: ' : 'Nuova recensione: ' ) . get_the_title( $scheda_id ), implode( "\n", $righe ) );
	}

	public static function redazione_conferma( $scheda_id, $voto_id ) {
		self::redazione(
			'Email confermata, recensione da moderare: ' . get_the_title( $scheda_id ),
			'Chi ha scritto la recensione su «' . get_the_title( $scheda_id ) . "» ha confermato l'email. La recensione è pronta per la moderazione:\n" . admin_url( 'admin.php?page=st-recensioni' )
		);
	}

	/** La redazione ha pubblicato una scheda proposta. */
	public static function scheda_pubblicata( $nuovo, $vecchio, $post ) {
		if ( Schede::TIPO !== $post->post_type || 'publish' !== $nuovo || 'pending' !== $vecchio ) {
			return;
		}
		$autore = (int) $post->post_author;
		$voto   = Voti::recensione_utente( $post->ID, $autore );
		$testo  = 'la scheda «' . $post->post_title . "» che hai proposto è stata approvata ed è pubblicata:\n" . get_permalink( $post->ID );
		if ( $voto && 'valido' !== $voto['stato'] ) {
			$testo .= "\n\nLa tua recensione è ancora in moderazione: ti scriveremo quando sarà pubblicata.";
		} elseif ( $voto ) {
			$testo .= "\n\nAnche la tua recensione è pubblicata insieme alla scheda.";
		}
		self::invia( $autore, 'La scheda che hai proposto è pubblicata', $testo );
	}

	/** Una recensione è diventata valida (approvata dal moderatore). */
	public static function recensione_valida( $voto_id ) {
		$voto = Voti::recensione( (int) $voto_id );
		if ( ! $voto || 'publish' !== get_post_status( (int) $voto['scheda_id'] ) ) {
			return;
		}
		self::invia(
			(int) $voto['utente_id'],
			'La tua recensione è pubblicata',
			'la tua recensione su «' . get_the_title( (int) $voto['scheda_id'] ) . "» è stata approvata dalla redazione ed è pubblicata:\n"
			. get_permalink( (int) $voto['scheda_id'] ) . '#st-recensione-' . (int) $voto['id']
		);
	}
}
