<?php
/**
 * Registro delle richieste: chi ha proposto una scheda, scritto o modificato una
 * recensione, inviato una segnalazione; quando, da quale indirizzo IP e con quale
 * browser. Serve alla sicurezza e a ricostruire gli abusi (account multipli,
 * recensioni false, contestazioni).
 *
 * L'indirizzo IP è un dato personale: il registro è visibile solo agli
 * amministratori e le righe più vecchie di "giorni_registro" (90 per impostazione
 * predefinita) vengono cancellate ogni notte. L'informativa privacy lo dichiara.
 *
 * Per le email di conferma non serve l'IP: si usa l'indirizzo email dell'account.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Registro {

	const EVENTI = array(
		'proposta_scheda'      => 'Proposta di una scheda',
		'scheda_membro'        => 'Scheda aggiunta da un membro',
		'recensione'           => 'Nuova recensione',
		'recensione_modificata' => 'Recensione modificata',
		'segnalazione'         => 'Segnalazione',
	);

	public static function tabella() {
		global $wpdb;
		return $wpdb->prefix . 'st_registro';
	}

	/** Indirizzo IP della richiesta. Si usa solo REMOTE_ADDR: le intestazioni dei proxy si possono falsificare. */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	public static function scrivi( $evento, $scheda_id = 0, $voto_id = 0, $utente_id = null ) {
		if ( ! isset( self::EVENTI[ $evento ] ) ) {
			return;
		}
		global $wpdb;
		$agente = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$wpdb->insert(
			self::tabella(),
			array(
				'evento'     => $evento,
				'utente_id'  => null === $utente_id ? get_current_user_id() : (int) $utente_id,
				'scheda_id'  => (int) $scheda_id,
				'voto_id'    => (int) $voto_id,
				'ip'         => self::ip(),
				'browser'    => mb_substr( $agente, 0, 255 ),
				'creato'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/** Cancella le righe più vecchie del periodo di conservazione. */
	public static function pulisci() {
		global $wpdb;
		$giorni = max( 1, (int) Impostazioni::valore( 'giorni_registro' ) );
		$limite = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $giorni * DAY_IN_SECONDS );
		$t      = self::tabella();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE creato < %s", $limite ) ); // phpcs:ignore WordPress.DB
	}

	/** Cancellazione di un utente: spariscono anche le sue righe nel registro. */
	public static function utente_cancellato( $utente_id ) {
		global $wpdb;
		$wpdb->delete( self::tabella(), array( 'utente_id' => (int) $utente_id ), array( '%d' ) );
	}

	/**
	 * Righe del registro, le più recenti prima.
	 *
	 * @param array $filtri utente (id), scheda (id), ip (testo esatto).
	 */
	public static function elenco( array $filtri = array(), $limite = 200 ) {
		global $wpdb;
		$t    = self::tabella();
		$dove = '1=1';
		if ( ! empty( $filtri['utente'] ) ) {
			$dove .= $wpdb->prepare( ' AND utente_id = %d', (int) $filtri['utente'] );
		}
		if ( ! empty( $filtri['scheda'] ) ) {
			$dove .= $wpdb->prepare( ' AND scheda_id = %d', (int) $filtri['scheda'] );
		}
		if ( ! empty( $filtri['ip'] ) ) {
			$dove .= $wpdb->prepare( ' AND ip = %s', $filtri['ip'] );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $dove ORDER BY creato DESC LIMIT %d", $limite ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/** Quanti utenti diversi hanno usato lo stesso IP nel periodo conservato. */
	public static function utenti_per_ip( $ip ) {
		global $wpdb;
		$t = self::tabella();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT utente_id) FROM $t WHERE ip = %s", $ip ) );
	}

	/* ---------- Pagina in bacheca (solo amministratori) ---------- */

	public static function pagina() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Il registro è visibile solo agli amministratori.', 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo filtri di visualizzazione.
		$filtri = array(
			'utente' => isset( $_GET['utente'] ) ? absint( $_GET['utente'] ) : 0,
			'scheda' => isset( $_GET['scheda'] ) ? absint( $_GET['scheda'] ) : 0,
			'ip'     => isset( $_GET['ip'] ) && filter_var( wp_unslash( $_GET['ip'] ), FILTER_VALIDATE_IP ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '',
		);
		// phpcs:enable
		$base  = admin_url( 'admin.php?page=st-registro' );
		$righe = self::elenco( $filtri );
		echo '<div class="wrap"><h1>Registro delle richieste</h1>';
		printf(
			'<p>Chi ha proposto schede, scritto recensioni o inviato segnalazioni, con l\'indirizzo IP e il browser. Le righe più vecchie di %d giorni vengono cancellate ogni notte (si cambia in Impostazioni). Usa questi dati solo per sicurezza e contro gli abusi, come dice l\'informativa privacy.</p>',
			(int) Impostazioni::valore( 'giorni_registro' )
		);
		if ( array_filter( $filtri ) ) {
			printf( '<p>Filtro attivo. <a href="%s">Mostra tutto</a></p>', esc_url( $base ) );
		}
		if ( ! $righe ) {
			echo '<p><strong>Nessuna richiesta registrata.</strong></p></div>';
			return;
		}
		echo '<table class="widefat striped"><caption class="screen-reader-text">Richieste registrate</caption><thead><tr><th scope="col">Quando</th><th scope="col">Evento</th><th scope="col">Utente ed email</th><th scope="col">Scheda</th><th scope="col">IP</th><th scope="col">Browser</th></tr></thead><tbody>';
		foreach ( $righe as $r ) {
			$u      = get_userdata( (int) $r['utente_id'] );
			$stessi = $r['ip'] ? self::utenti_per_ip( $r['ip'] ) : 0;
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $r['creato'] ) );
			printf( '<td>%s</td>', esc_html( isset( self::EVENTI[ $r['evento'] ] ) ? self::EVENTI[ $r['evento'] ] : $r['evento'] ) );
			if ( $u ) {
				printf(
					'<td><a href="%1$s">%2$s</a><br><a href="mailto:%3$s">%3$s</a><br><a href="%4$s">tutte le sue richieste</a></td>',
					esc_url( get_edit_user_link( $u->ID ) ),
					esc_html( $u->user_login ),
					esc_html( $u->user_email ),
					esc_url( add_query_arg( 'utente', $u->ID, $base ) )
				);
			} else {
				echo '<td>' . ( (int) $r['utente_id'] ? 'utente cancellato' : 'visitatore senza account' ) . '</td>';
			}
			if ( (int) $r['scheda_id'] && get_post( (int) $r['scheda_id'] ) ) {
				printf( '<td><a href="%1$s">%2$s</a></td>', esc_url( get_edit_post_link( (int) $r['scheda_id'] ) ), esc_html( get_the_title( (int) $r['scheda_id'] ) ) );
			} else {
				echo '<td>—</td>';
			}
			if ( $r['ip'] ) {
				printf(
					'<td><a href="%1$s">%2$s</a>%3$s</td>',
					esc_url( add_query_arg( 'ip', $r['ip'], $base ) ),
					esc_html( $r['ip'] ),
					$stessi > 1 ? '<br><strong>' . esc_html( sprintf( 'usato da %d utenti', $stessi ) ) . '</strong>' : ''
				);
			} else {
				echo '<td>—</td>';
			}
			printf( '<td><small>%s</small></td>', esc_html( $r['browser'] ) );
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}
}
