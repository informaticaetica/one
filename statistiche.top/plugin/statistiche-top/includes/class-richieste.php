<?php
/**
 * "Crea la tua scheda": aziende, professionisti e produttori propongono la
 * propria scheda, o l'aggiornamento di quella esistente, con i dati ufficiali.
 *
 * Ogni richiesta resta nel database (tipo di contenuto privato st_richiesta),
 * arriva per email alla redazione e una ricevuta arriva a chi la invia. In
 * bacheca l'amministratore la applica con un clic (crea la scheda "in revisione"
 * o aggiorna quella esistente) oppure la scarta. Chi rappresenta il soggetto
 * diventa referente: può rispondere ma non recensire.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Richieste {

	const TIPO     = 'st_richiesta';
	const MAX_DESC = 1000;

	/** Campi del modulo: nome => [etichetta, pulizia]. */
	const CAMPI = array(
		'st_r_tipo'        => array( 'Tipo', 'chiave' ),
		'st_r_nome'        => array( 'Nome', 'testo' ),
		'st_r_categoria'   => array( 'Categoria', 'testo' ),
		'st_r_descrizione' => array( 'Descrizione', 'area' ),
		'st_r_sito'        => array( 'Sito ufficiale', 'testo' ),
		'st_r_luogo'       => array( 'Sede', 'testo' ),
		'st_r_piva'        => array( 'Partita IVA', 'testo' ),
		'st_r_anno'        => array( 'Anno di fondazione', 'testo' ),
		'st_r_referente'   => array( 'Referente', 'testo' ),
		'st_r_ruolo'       => array( 'Ruolo del referente', 'testo' ),
		'st_r_email'       => array( 'Email', 'testo' ),
		'st_r_telefono'    => array( 'Telefono', 'testo' ),
		'st_r_autorizzato' => array( 'Autorizzato a rappresentare il soggetto', 'spunta' ),
		'st_r_privacy'     => array( 'Informativa privacy letta', 'spunta' ),
	);

	const AIUTI = array(
		'r_tipo'        => '<p>Scegli <strong>azienda</strong> per imprese, negozi, studi ed enti; <strong>persona</strong> se sei un professionista che opera con il proprio nome; <strong>prodotto</strong> se proponi la scheda di un tuo prodotto.</p>',
		'r_nome'        => '<p>Il nome ufficiale, come compare sull\'insegna, sul sito o nel registro delle imprese. Se la scheda esiste già, la tua richiesta servirà ad aggiornarla.</p>',
		'r_categoria'   => '<p>Facoltativo. La categoria con parole tue (per esempio «Officina meccanica»); la redazione decide quella definitiva.</p>',
		'r_descrizione' => '<p>Chi siete e che cosa fate, in modo semplice e verificabile: niente slogan pubblicitari. La redazione può accorciare o correggere il testo. Al massimo 1000 caratteri.</p>',
		'r_sito'        => '<p>Facoltativo. L\'indirizzo del sito ufficiale: aiuta la redazione a verificare che la richiesta venga davvero da voi.</p>',
		'r_luogo'       => '<p>Facoltativo. La città o la zona in cui operate.</p>',
		'r_piva'        => '<p>Facoltativo. La partita IVA (11 cifre) permette alla redazione di verificare i dati nel registro delle imprese. Compare nella scheda.</p>',
		'r_anno'        => '<p>Facoltativo. L\'anno di fondazione o di inizio dell\'attività.</p>',
		'r_referente'   => '<p>Nome e cognome di chi invia la richiesta. Il referente può rispondere alle segnalazioni sulla scheda, ma non può recensirla.</p>',
		'r_email'       => '<p>Obbligatoria. Riceverete una ricevuta e l\'esito della richiesta. Meglio un indirizzo con il dominio del vostro sito: rende più veloce la verifica. Non compare sul sito.</p>',
		'r_telefono'    => '<p>Facoltativo. Per essere contattati dalla redazione se servono chiarimenti. Non compare sul sito.</p>',
	);

	public static function avvia() {
		add_action( 'init', array( __CLASS__, 'registra_tipo' ) );
		add_action( 'admin_post_st_richiesta', array( __CLASS__, 'gestisci' ) );
		add_action( 'admin_post_nopriv_st_richiesta', array( __CLASS__, 'gestisci' ) );
		add_action( 'admin_post_st_richiesta_azione', array( __CLASS__, 'azione' ) );
		add_action( 'add_meta_boxes_' . self::TIPO, array( __CLASS__, 'meta_box' ) );
		add_shortcode( 'st_crea_scheda', array( __CLASS__, 'shortcode' ) );
	}

	public static function registra_tipo() {
		register_post_type(
			self::TIPO,
			array(
				'labels'       => array(
					'name'          => 'Richieste dei soggetti',
					'singular_name' => 'Richiesta',
					'all_items'     => 'Richieste «Crea la tua scheda»',
					'edit_item'     => 'Richiesta',
					'not_found'     => 'Nessuna richiesta',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . Schede::TIPO,
				'supports'     => array( 'title' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}

	/* ---------- Modulo pubblico ---------- */

	private static function bozza() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lettura di dati di visualizzazione.
		$chiave = isset( $_GET['st_bozza'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['st_bozza'] ) ) : '';
		$b      = $chiave ? get_transient( 'st_rich_' . $chiave ) : false;
		return is_array( $b ) ? $b : array( 'dati' => array(), 'errori' => array() );
	}

	public static function shortcode() {
		Vista::accoda_risorse();
		$b = self::bozza();
		$f = new Campi( $b['dati'], $b['errori'], self::AIUTI + Modulo::AIUTI );
		ob_start();
		echo '<div class="st-blocco" id="st-richiesta">' . Vista::avviso( 'st-richiesta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $b['errori'] ) {
			echo '<div class="st-avviso st-avviso--errore" role="alert" tabindex="-1" data-st-focus><p><strong>Attenzione:</strong> alcuni campi vanno corretti.</p><ul>';
			foreach ( $b['errori'] as $campo => $testo ) {
				printf( '<li><a href="#%1$s">%2$s</a></li>', esc_attr( Campi::id( $campo ) ), esc_html( $testo ) );
			}
			echo '</ul></div>';
		}
		?>
		<form class="st-modulo" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="st_richiesta">
			<?php wp_nonce_field( 'st_richiesta', 'st_nonce', false ); ?>
			<?php Vista::trappola(); ?>
			<p class="st-nota">Questo modulo è per chi rappresenta un'azienda, un professionista o un produttore. I campi obbligatori sono indicati; la redazione verifica ogni richiesta prima di pubblicarla.</p>
			<?php
			$f->radio( 'st_r_tipo', 'La scheda riguarda', array( 'azienda' => 'Un\'azienda o un\'organizzazione', 'persona' => 'Un professionista (persona)', 'prodotto' => 'Un prodotto' ), 'r_tipo', true, true );
			$f->testo( 'st_r_nome', 'Nome ufficiale', 'r_nome', true, array( 'maxlength' => 150 ) );
			$f->testo( 'st_r_categoria', 'Categoria', 'r_categoria', false, array( 'maxlength' => Modulo::MAX_CATEGORIA, 'list' => 'st-categorie-r' ) );
			echo '<datalist id="st-categorie-r">';
			$settori = get_terms( array( 'taxonomy' => Schede::SETTORE, 'hide_empty' => false ) );
			foreach ( is_array( $settori ) ? $settori : array() as $t ) {
				printf( '<option value="%s"></option>', esc_attr( $t->name ) );
			}
			echo '</datalist>';
			$f->area( 'st_r_descrizione', sprintf( 'Descrizione (fino a %d caratteri)', self::MAX_DESC ), 'r_descrizione', true, self::MAX_DESC, 6, true );
			$f->testo( 'st_r_sito', 'Sito ufficiale', 'r_sito', false, array( 'type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://' ) );
			$f->testo( 'st_r_luogo', 'Sede (città o zona)', 'r_luogo', false, array( 'maxlength' => 100 ) );
			$f->testo( 'st_r_piva', 'Partita IVA', 'r_piva', false, array( 'inputmode' => 'numeric', 'maxlength' => 13 ) );
			$f->testo( 'st_r_anno', 'Anno di fondazione', 'r_anno', false, array( 'inputmode' => 'numeric', 'maxlength' => 4 ) );
			$f->testo( 'st_r_referente', 'Nome e cognome del referente', 'r_referente', true, array( 'maxlength' => 100, 'autocomplete' => 'name' ) );
			$f->testo( 'st_r_ruolo', 'Ruolo del referente', '', false, array( 'maxlength' => 100, 'autocomplete' => 'organization-title' ) );
			$f->testo( 'st_r_email', 'Email', 'r_email', true, array( 'type' => 'email', 'autocomplete' => 'email', 'maxlength' => 190 ) );
			$f->testo( 'st_r_telefono', 'Telefono', 'r_telefono', false, array( 'type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 20 ) );
			?>
			<fieldset>
				<legend>Dichiarazioni <span class="st-obbligatorio">(obbligatorie)</span></legend>
				<?php
				$f->spunta( 'st_r_autorizzato', 'Sono autorizzato a rappresentare il soggetto e i dati sono veri e aggiornati.', '', true );
				$f->spunta( 'st_r_privacy', 'Ho letto l\'informativa sulla privacy.', '', true );
				?>
				<p class="st-nota"><a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy/' ) ); ?>">Informativa sulla privacy</a> · Il soggetto della scheda e i suoi collaboratori non possono recensirla.</p>
			</fieldset>
			<p><button type="submit" class="st-pulsante">Invia la richiesta alla redazione</button></p>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Controlla i dati della richiesta. */
	public static function valida( array $d ) {
		$e = array();
		$g = static function ( $k ) use ( $d ) {
			return isset( $d[ $k ] ) ? trim( (string) $d[ $k ] ) : '';
		};
		if ( ! isset( Schede::TIPI_SOGGETTO[ $g( 'st_r_tipo' ) ] ) ) {
			$e['st_r_tipo'] = 'Scegli il tipo di scheda.';
		}
		if ( mb_strlen( $g( 'st_r_nome' ) ) < 2 || mb_strlen( $g( 'st_r_nome' ) ) > 150 ) {
			$e['st_r_nome'] = 'Scrivi il nome ufficiale (da 2 a 150 caratteri).';
		}
		$n = mb_strlen( $g( 'st_r_descrizione' ) );
		if ( $n < 30 || $n > self::MAX_DESC ) {
			$e['st_r_descrizione'] = sprintf( 'La descrizione deve avere da 30 a %d caratteri (ora: %d).', self::MAX_DESC, $n );
		}
		if ( '' !== $g( 'st_r_sito' ) && ! wp_http_validate_url( esc_url_raw( $g( 'st_r_sito' ), array( 'http', 'https' ) ) ) ) {
			$e['st_r_sito'] = 'L\'indirizzo del sito deve iniziare con https:// o http://.';
		}
		$piva = preg_replace( '/^IT/i', '', str_replace( ' ', '', $g( 'st_r_piva' ) ) );
		if ( '' !== $piva && ! preg_match( '/^\d{11}$/', $piva ) ) {
			$e['st_r_piva'] = 'La partita IVA italiana ha 11 cifre.';
		}
		if ( '' !== $g( 'st_r_anno' ) && null === Punteggi::intero( $g( 'st_r_anno' ), 1800, (int) current_time( 'Y' ) ) ) {
			$e['st_r_anno'] = 'Scrivi l\'anno con 4 cifre.';
		}
		if ( mb_strlen( $g( 'st_r_referente' ) ) < 3 ) {
			$e['st_r_referente'] = 'Scrivi il nome e il cognome del referente.';
		}
		if ( ! is_email( $g( 'st_r_email' ) ) ) {
			$e['st_r_email'] = 'Scrivi un indirizzo email valido.';
		}
		if ( '' !== $g( 'st_r_telefono' ) && ! preg_match( '/^\+?[0-9 ().\/-]{6,20}$/', $g( 'st_r_telefono' ) ) ) {
			$e['st_r_telefono'] = 'Il telefono può contenere solo numeri, spazi e il segno + iniziale.';
		}
		if ( '1' !== $g( 'st_r_autorizzato' ) ) {
			$e['st_r_autorizzato'] = 'Conferma di essere autorizzato a rappresentare il soggetto.';
		}
		if ( '1' !== $g( 'st_r_privacy' ) ) {
			$e['st_r_privacy'] = 'Conferma di aver letto l\'informativa sulla privacy.';
		}
		return $e;
	}

	public static function gestisci() {
		$in   = wp_unslash( $_POST );
		$base = Vista::url_ritorno();
		if ( ! isset( $in['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $in['st_nonce'] ), 'st_richiesta' ) || ! empty( $in['st_sito_web'] ) ) {
			Vista::reindirizza( $base, 'errore', 'richiesta_non_valida', 'st-richiesta' );
		}
		$chiave_ip = 'st_rich_ip_' . substr( Voti::impronta_ip(), 0, 32 );
		$invii     = (int) get_transient( $chiave_ip );
		if ( $invii >= 3 ) {
			Vista::reindirizza( $base, 'errore', 'troppi_invii', 'st-richiesta' );
		}
		$d = array();
		foreach ( self::CAMPI as $k => $c ) {
			$v = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? $in[ $k ] : '';
			$d[ $k ] = 'area' === $c[1] ? sanitize_textarea_field( $v ) : ( 'spunta' === $c[1] ? ( $v ? '1' : '' ) : ( 'chiave' === $c[1] ? sanitize_key( $v ) : sanitize_text_field( $v ) ) );
		}
		$errori = self::valida( $d );
		if ( $errori ) {
			$chiave = wp_generate_password( 16, false );
			set_transient( 'st_rich_' . $chiave, array( 'dati' => $d, 'errori' => $errori ), 30 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'st_bozza', $chiave, remove_query_arg( array( 'st_bozza', 'st_esito', 'st_msg', 'st_dove' ), $base ) ) . '#st-richiesta' );
			exit;
		}
		$esistente = Schede::cerca_doppione( $d['st_r_nome'] );
		$id        = wp_insert_post(
			array(
				'post_type'   => self::TIPO,
				'post_status' => 'private',
				'post_title'  => $d['st_r_nome'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			Vista::reindirizza( $base, 'errore', 'salvataggio', 'st-richiesta' );
		}
		foreach ( $d as $k => $v ) {
			update_post_meta( $id, '_' . $k, $v );
		}
		update_post_meta( $id, '_st_r_scheda', (int) $esistente );
		update_post_meta( $id, '_st_r_stato', 'nuova' );
		update_post_meta( $id, '_st_r_ip', Registro::ip() );
		set_transient( $chiave_ip, $invii + 1, HOUR_IN_SECONDS );

		$righe = array();
		foreach ( self::CAMPI as $k => $c ) {
			$righe[] = $c[0] . ': ' . ( '' !== $d[ $k ] ? ( 'spunta' === $c[1] ? 'sì' : $d[ $k ] ) : '—' );
		}
		Notifiche::redazione(
			'Crea la tua scheda: ' . $d['st_r_nome'] . ( $esistente ? ' (aggiornamento di una scheda esistente)' : '' ),
			implode( "\n", $righe ) . "\nIP: " . Registro::ip() . "\n\nApplica o scarta la richiesta: " . admin_url( 'post.php?post=' . (int) $id . '&action=edit' )
		);
		wp_mail(
			$d['st_r_email'],
			'[' . wp_parse_url( home_url(), PHP_URL_HOST ) . '] Abbiamo ricevuto la richiesta per «' . $d['st_r_nome'] . '»',
			'Gentile ' . $d['st_r_referente'] . ",\n\nabbiamo ricevuto la richiesta per la scheda «" . $d['st_r_nome'] . "». La redazione verificherà i dati e vi scriverà con l'esito.\n\n— La redazione di " . wp_parse_url( home_url(), PHP_URL_HOST )
		);
		Vista::reindirizza( $base, 'ok', 'richiesta_ricevuta', 'st-richiesta' );
	}

	/* ---------- Bacheca ---------- */

	public static function meta_box() {
		add_meta_box( 'st-richiesta', 'Dati della richiesta', array( __CLASS__, 'mostra_meta_box' ), self::TIPO, 'normal', 'high' );
	}

	public static function mostra_meta_box( $post ) {
		$stato   = (string) get_post_meta( $post->ID, '_st_r_stato', true );
		$scheda  = (int) get_post_meta( $post->ID, '_st_r_scheda', true );
		$stati   = array( 'nuova' => 'Da decidere', 'applicata' => 'Applicata', 'scartata' => 'Scartata' );
		printf( '<p><strong>Stato:</strong> %s</p>', esc_html( isset( $stati[ $stato ] ) ? $stati[ $stato ] : $stato ) );
		echo '<table class="form-table" role="presentation">';
		foreach ( self::CAMPI as $k => $c ) {
			$v = (string) get_post_meta( $post->ID, '_' . $k, true );
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $c[0] ), esc_html( '' !== $v ? ( 'spunta' === $c[1] ? 'sì' : $v ) : '—' ) );
		}
		printf( '<tr><th scope="row">IP</th><td>%s</td></tr></table>', esc_html( (string) get_post_meta( $post->ID, '_st_r_ip', true ) ) );
		if ( $scheda && get_post( $scheda ) ) {
			printf( '<p>Esiste già la scheda <a href="%1$s">%2$s</a> (%3$s): applicando la richiesta la aggiorni.</p>', esc_url( get_edit_post_link( $scheda ) ), esc_html( get_the_title( $scheda ) ), esc_html( get_post_status( $scheda ) ) );
		}
		echo '<p class="description">Prima di applicare, verifica che chi scrive rappresenti davvero il soggetto: per esempio che l\'email abbia il dominio del sito ufficiale, o con la partita IVA nel registro delle imprese.</p>';
		if ( 'nuova' === $stato ) {
			printf( '<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">Scarta la richiesta</a></p>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=st_richiesta_azione&azione=applica&richiesta=' . $post->ID ), 'st_richiesta_' . $post->ID ) ), $scheda ? 'Aggiorna la scheda esistente' : 'Crea la scheda (in revisione)', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=st_richiesta_azione&azione=scarta&richiesta=' . $post->ID ), 'st_richiesta_' . $post->ID ) ) );
		}
	}

	public static function azione() {
		$id = isset( $_GET['richiesta'] ) ? absint( $_GET['richiesta'] ) : 0;
		if ( ! current_user_can( 'st_modera' ) || ! $id || self::TIPO !== get_post_type( $id ) ) {
			wp_die( 'Operazione non consentita.', 403 );
		}
		check_admin_referer( 'st_richiesta_' . $id );
		$azione = isset( $_GET['azione'] ) ? sanitize_key( $_GET['azione'] ) : '';
		$m      = static function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, '_' . $k, true );
		};
		$scheda = 0;
		if ( 'applica' === $azione && 'nuova' === $m( 'st_r_stato' ) ) {
			$scheda = self::applica( $id, $m );
			update_post_meta( $id, '_st_r_stato', $scheda ? 'applicata' : 'nuova' );
			if ( $scheda ) {
				wp_mail(
					$m( 'st_r_email' ),
					'[' . wp_parse_url( home_url(), PHP_URL_HOST ) . '] Richiesta approvata: «' . $m( 'st_r_nome' ) . '»',
					'Gentile ' . $m( 'st_r_referente' ) . ",\n\nla redazione ha approvato la vostra richiesta. "
					. ( 'publish' === get_post_status( $scheda ) ? "La scheda è aggiornata:\n" . get_permalink( $scheda ) : 'La scheda sarà visibile appena la redazione la pubblica: vi scriveremo.' )
					. "\n\n— La redazione di " . wp_parse_url( home_url(), PHP_URL_HOST )
				);
			}
		} elseif ( 'scarta' === $azione ) {
			update_post_meta( $id, '_st_r_stato', 'scartata' );
		}
		wp_safe_redirect( $scheda ? get_edit_post_link( $scheda, 'url' ) : get_edit_post_link( $id, 'url' ) );
		exit;
	}

	/** Crea la scheda (in revisione) o aggiorna quella esistente con i dati della richiesta. */
	private static function applica( $id, callable $m ) {
		$scheda = (int) get_post_meta( $id, '_st_r_scheda', true );
		$scheda = $scheda && get_post( $scheda ) ? $scheda : Schede::cerca_doppione( $m( 'st_r_nome' ) );
		$sintesi = mb_substr( $m( 'st_r_descrizione' ), 0, Schede::MAX_SINTESI );
		if ( ! $scheda ) {
			$scheda = wp_insert_post( array( 'post_type' => Schede::TIPO, 'post_status' => 'pending', 'post_title' => $m( 'st_r_nome' ), 'post_content' => $sintesi ), true );
			if ( is_wp_error( $scheda ) ) {
				return 0;
			}
			update_post_meta( $scheda, '_st_tipo', $m( 'st_r_tipo' ) );
			Schede::salva_aggregato( $scheda, Punteggi::aggrega( array(), Voti::media_generale(), (float) Impostazioni::valore( 'peso_bayes' ) ) );
			Schede::aggiungi_storico( $scheda, 'Scheda creata su richiesta del soggetto, con i dati da lui forniti.' );
		} else {
			wp_update_post( array( 'ID' => $scheda, 'post_content' => $sintesi ) );
			Schede::aggiungi_storico( $scheda, 'Dati aggiornati su richiesta del soggetto.' );
		}
		foreach ( array( 'st_r_sito' => '_st_sito', 'st_r_luogo' => '_st_luogo', 'st_r_piva' => '_st_piva', 'st_r_anno' => '_st_anno' ) as $da => $a ) {
			if ( '' !== $m( $da ) ) {
				update_post_meta( $scheda, $a, '_st_sito' === $a ? esc_url_raw( $m( $da ), array( 'http', 'https' ) ) : $m( $da ) );
			}
		}
		if ( '' !== $m( 'st_r_categoria' ) && ! wp_get_post_terms( $scheda, Schede::SETTORE, array( 'fields' => 'ids' ) ) ) {
			$t = get_term_by( 'name', $m( 'st_r_categoria' ), Schede::SETTORE );
			if ( $t ) {
				wp_set_object_terms( $scheda, array( (int) $t->term_id ), Schede::SETTORE );
			} else {
				update_post_meta( $scheda, '_st_categoria_suggerita', $m( 'st_r_categoria' ) );
			}
		}
		update_post_meta( $scheda, '_st_dal_soggetto', current_time( 'mysql' ) );
		// Chi rappresenta il soggetto diventa referente: risponde, ma non recensisce.
		$referente = Modulo::utente_da_email( $m( 'st_r_email' ) );
		if ( $referente ) {
			$referenti   = array_map( 'intval', (array) get_post_meta( $scheda, '_st_referenti', true ) );
			$referenti[] = $referente;
			update_post_meta( $scheda, '_st_referenti', array_values( array_unique( array_filter( $referenti ) ) ) );
		}
		return (int) $scheda;
	}
}
