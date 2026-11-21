<?php
/**
 * Bacheca: dati della scheda, moderazione delle recensioni, segnalazioni,
 * coda di eccellenze.org e impostazioni.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Amministrazione {

	const MENU = 'edit.php?post_type=' . Schede::TIPO;

	public static function avvia() {
		add_action( 'add_meta_boxes_' . Schede::TIPO, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . Schede::TIPO, array( __CLASS__, 'salva_meta' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_st_modera_voto', array( __CLASS__, 'azione_voto' ) );
		add_action( 'admin_post_st_rispondi', array( __CLASS__, 'azione_risposta' ) );
		add_action( 'admin_post_st_eccellenze', array( __CLASS__, 'azione_eccellenze' ) );
		add_action( 'admin_post_st_ricalcola', array( __CLASS__, 'azione_ricalcola' ) );
		add_action( 'admin_post_st_categoria', array( __CLASS__, 'azione_categoria' ) );
		add_filter( 'manage_' . Schede::TIPO . '_posts_columns', array( __CLASS__, 'colonne' ) );
		add_action( 'manage_' . Schede::TIPO . '_posts_custom_column', array( __CLASS__, 'colonna' ), 10, 2 );
	}

	/* ---------- Dati della scheda ---------- */

	public static function meta_box() {
		add_meta_box( 'st-dati', 'Dati della scheda', array( __CLASS__, 'mostra_meta_box' ), Schede::TIPO, 'normal', 'high' );
		add_meta_box( 'st-punteggio', 'Punteggio ed eccellenze.org', array( __CLASS__, 'mostra_punteggio' ), Schede::TIPO, 'side' );
	}

	public static function mostra_meta_box( $post ) {
		$d = Schede::dati( $post->ID );
		wp_nonce_field( 'st_meta_' . $post->ID, 'st_meta_nonce' );
		$fonti = $d['fonti'];
		while ( count( $fonti ) < Schede::MAX_FONTI ) {
			$fonti[] = array( 'url' => '', 'descrizione' => '' );
		}
		$referenti = array_filter( array_map( 'get_userdata', $d['referenti'] ) );
		?>
		<p>La <strong>sintesi</strong> è il testo principale della scheda (massimo <?php echo esc_html( Schede::MAX_SINTESI ); ?> caratteri, testo semplice).</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Tipo</th>
				<td>
					<fieldset><legend class="screen-reader-text">Tipo</legend>
					<?php foreach ( Schede::TIPI_SOGGETTO as $k => $nome ) : ?>
						<label><input type="radio" name="st_tipo" value="<?php echo esc_attr( $k ); ?>" <?php checked( $d['tipo'], $k ); ?>> <?php echo esc_html( $nome ); ?></label><br>
					<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="st-luogo">Luogo</label></th>
				<td><input type="text" class="regular-text" id="st-luogo" name="st_luogo" value="<?php echo esc_attr( $d['luogo'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="st-sito">Sito ufficiale</label></th>
				<td><input type="url" class="regular-text" id="st-sito" name="st_sito" value="<?php echo esc_attr( $d['sito'] ); ?>" placeholder="https://"></td>
			</tr>
			<?php if ( $d['categoria'] ) : ?>
			<tr>
				<th scope="row">Categoria suggerita</th>
				<td><strong><?php echo esc_html( $d['categoria'] ); ?></strong> — scegli il settore nel riquadro «Settori» o dalla <a href="<?php echo esc_url( admin_url( 'admin.php?page=st-categorie' ) ); ?>">coda delle categorie</a>.</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row">Persona con ruolo pubblico</th>
				<td><label><input type="checkbox" name="st_figura_pubblica" value="1" <?php checked( $d['figura_pubblica'] ); ?>> Sì, verificato</label></td>
			</tr>
			<tr>
				<th scope="row">Fonti</th>
				<td>
					<?php foreach ( $fonti as $i => $f ) : ?>
						<p>
							<label class="screen-reader-text" for="st-fu-<?php echo esc_attr( $i ); ?>">Indirizzo della fonte <?php echo esc_html( $i + 1 ); ?></label>
							<input type="url" class="regular-text" id="st-fu-<?php echo esc_attr( $i ); ?>" name="st_fonte_url[]" placeholder="https://" value="<?php echo esc_attr( $f['url'] ); ?>">
							<label class="screen-reader-text" for="st-fd-<?php echo esc_attr( $i ); ?>">Descrizione della fonte <?php echo esc_html( $i + 1 ); ?></label>
							<input type="text" id="st-fd-<?php echo esc_attr( $i ); ?>" name="st_fonte_descrizione[]" placeholder="Descrizione" value="<?php echo esc_attr( $f['descrizione'] ); ?>">
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="st-licenza">Licenza dell'immagine in evidenza</label></th>
				<td><input type="text" class="regular-text" id="st-licenza" name="st_licenza_immagine" value="<?php echo esc_attr( $d['licenza_immagine'] ); ?>" placeholder="es. CC BY-SA 4.0, autore, link"></td>
			</tr>
			<tr>
				<th scope="row"><label for="st-referenti">Referenti verificati</label></th>
				<td>
					<input type="text" class="regular-text" id="st-referenti" name="st_referenti" value="<?php echo esc_attr( implode( ', ', wp_list_pluck( $referenti, 'user_login' ) ) ); ?>" aria-describedby="st-referenti-aiuto">
					<p class="description" id="st-referenti-aiuto">Nomi utente separati da virgola. I referenti rappresentano il soggetto: possono rispondere, ma non votare.</p>
				</td>
			</tr>
		</table>
		<?php
		$storico = array_reverse( Schede::storico( $post->ID ) );
		if ( $storico ) {
			echo '<h3>Storico</h3><ol>';
			foreach ( array_slice( $storico, 0, 30 ) as $v ) {
				$u = get_userdata( (int) $v['utente'] );
				printf( '<li>%1$s — %2$s%3$s</li>', esc_html( $v['data'] ), esc_html( $v['testo'] ), $u ? ' (' . esc_html( $u->user_login ) . ')' : '' );
			}
			echo '</ol>';
		}
	}

	public static function mostra_punteggio( $post ) {
		$agg   = Schede::aggregato( $post->ID );
		$stato = Eccellenze::stato( $post->ID );
		printf(
			'<p>Punteggio ponderato: <strong>%1$s</strong><br>Media semplice: %2$s<br>Recensioni valide: %3$d<br>Recensori unici: %4$d</p><p>eccellenze.org: <strong>%5$s</strong></p>',
			esc_html( number_format_i18n( $agg['media_bayes'], 2 ) ),
			esc_html( number_format_i18n( $agg['media'], 2 ) ),
			(int) $agg['n_recensioni'],
			(int) $agg['n_recensori'],
			esc_html( Eccellenze::STATI[ $stato ] ?? $stato )
		);
		global $wpdb;
		$attesa = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Installazione::tabella_voti() . " WHERE scheda_id = %d AND stato = 'attesa'", $post->ID ) ); // phpcs:ignore WordPress.DB
		if ( $attesa ) {
			printf( '<p>Recensioni in attesa (di chi ha proposto la scheda): %d. Contano dopo la pubblicazione e i %d giorni dell\'account.</p>', $attesa, (int) Impostazioni::valore( 'eta_minima_giorni' ) );
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=st-eccellenze' ) ) . '">Coda di eccellenze.org</a></p>';
	}

	public static function salva_meta( $post_id, $post ) {
		if ( ! isset( $_POST['st_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['st_meta_nonce'] ), 'st_meta_' . $post_id )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = wp_unslash( $_POST );

		$tipo = isset( $in['st_tipo'] ) ? sanitize_key( $in['st_tipo'] ) : '';
		if ( isset( Schede::TIPI_SOGGETTO[ $tipo ] ) ) {
			update_post_meta( $post_id, '_st_tipo', $tipo );
		}
		update_post_meta( $post_id, '_st_luogo', isset( $in['st_luogo'] ) ? sanitize_text_field( $in['st_luogo'] ) : '' );
		update_post_meta( $post_id, '_st_sito', isset( $in['st_sito'] ) ? esc_url_raw( trim( $in['st_sito'] ), array( 'http', 'https' ) ) : '' );
		update_post_meta( $post_id, '_st_figura_pubblica', empty( $in['st_figura_pubblica'] ) ? 0 : 1 );
		update_post_meta( $post_id, '_st_licenza_immagine', isset( $in['st_licenza_immagine'] ) ? sanitize_text_field( $in['st_licenza_immagine'] ) : '' );

		$fonti = array();
		$url   = isset( $in['st_fonte_url'] ) ? (array) $in['st_fonte_url'] : array();
		$desc  = isset( $in['st_fonte_descrizione'] ) ? (array) $in['st_fonte_descrizione'] : array();
		foreach ( array_slice( $url, 0, Schede::MAX_FONTI, true ) as $i => $u ) {
			$u = esc_url_raw( trim( (string) $u ), array( 'http', 'https' ) );
			if ( $u ) {
				$fonti[] = array( 'url' => $u, 'descrizione' => isset( $desc[ $i ] ) ? sanitize_text_field( $desc[ $i ] ) : '' );
			}
		}
		$prima = Schede::dati( $post_id )['fonti'];
		update_post_meta( $post_id, '_st_fonti', $fonti );

		$referenti = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', isset( $in['st_referenti'] ) ? (string) $in['st_referenti'] : '' ) ) ) as $login ) {
			$u = get_user_by( 'login', sanitize_user( $login ) );
			if ( $u ) {
				$referenti[] = (int) $u->ID;
			}
		}
		update_post_meta( $post_id, '_st_referenti', array_values( array_unique( $referenti ) ) );

		// Le recensioni già lasciate dai referenti smettono di contare.
		if ( $referenti ) {
			foreach ( $referenti as $r ) {
				$voto = Voti::recensione_utente( $post_id, $r );
				if ( $voto && 'valido' === $voto['stato'] ) {
					Voti::cambia_stato( (int) $voto['id'], 'annullato' );
				}
			}
		}

		if ( $prima !== $fonti && 'publish' === $post->post_status ) {
			Schede::aggiungi_storico( $post_id, 'Fonti aggiornate dalla redazione.' );
		}
	}

	public static function colonne( $colonne ) {
		$colonne['st_punteggio'] = 'Punteggio';
		$colonne['st_recensori'] = 'Recensori';
		$colonne['st_eccellenze'] = 'eccellenze.org';
		return $colonne;
	}

	public static function colonna( $colonna, $post_id ) {
		$agg = Schede::aggregato( $post_id );
		if ( 'st_punteggio' === $colonna ) {
			echo esc_html( number_format_i18n( $agg['media_bayes'], 2 ) );
		} elseif ( 'st_recensori' === $colonna ) {
			echo (int) $agg['n_recensori'];
		} elseif ( 'st_eccellenze' === $colonna ) {
			$s = Eccellenze::stato( $post_id );
			echo esc_html( $s ? Eccellenze::STATI[ $s ] : '—' );
		}
	}

	/* ---------- Menu ---------- */

	public static function menu() {
		$segn   = Segnalazioni::aperte_conteggio();
		$bolla  = $segn ? ' <span class="awaiting-mod">' . (int) $segn . '</span>' : '';
		add_submenu_page( self::MENU, 'Recensioni da controllare', 'Recensioni da controllare', 'st_modera', 'st-recensioni', array( __CLASS__, 'pagina_recensioni' ) );
		add_submenu_page( self::MENU, 'Segnalazioni', 'Segnalazioni' . $bolla, 'st_modera', 'st-segnalazioni', array( __CLASS__, 'pagina_segnalazioni' ) );
		add_submenu_page( self::MENU, 'Categorie da decidere', 'Categorie da decidere' . ( self::categorie_conteggio() ? ' <span class="awaiting-mod">' . (int) self::categorie_conteggio() . '</span>' : '' ), 'st_modera', 'st-categorie', array( __CLASS__, 'pagina_categorie' ) );
		add_submenu_page( self::MENU, 'eccellenze.org', 'eccellenze.org', 'st_modera', 'st-eccellenze', array( __CLASS__, 'pagina_eccellenze' ) );
		add_submenu_page( self::MENU, 'Impostazioni di statistiche.top', 'Impostazioni', 'manage_options', 'st-impostazioni', array( __CLASS__, 'pagina_impostazioni' ) );
	}

	private static function esito() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo visualizzazione.
		if ( isset( $_GET['st_fatto'] ) ) {
			$ok = '1' === $_GET['st_fatto']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', $ok ? 'success' : 'error', $ok ? 'Operazione eseguita.' : 'Operazione non eseguita: controlla lo stato e riprova.' );
		}
	}

	private static function torna( $pagina, $ok ) {
		wp_safe_redirect( add_query_arg( array( 'page' => $pagina, 'st_fatto' => $ok ? '1' : '0' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function controlla( $azione ) {
		if ( ! current_user_can( 'st_modera' ) ) {
			wp_die( 'Non hai i permessi per questa operazione.', 403 );
		}
		check_admin_referer( $azione );
	}

	/* ---------- Recensioni sospese ---------- */

	public static function pagina_recensioni() {
		$righe = Voti::sospese();
		echo '<div class="wrap"><h1>Recensioni da controllare</h1>';
		self::esito();
		echo '<p>Qui arrivano le recensioni lasciate da uno stesso indirizzo di rete già usato da un altro utente sulla stessa scheda. Non contano finché non le convalidi.</p>';
		if ( ! $righe ) {
			echo '<p><strong>Nessuna recensione in attesa.</strong></p></div>';
			return;
		}
		echo '<table class="widefat striped"><caption class="screen-reader-text">Recensioni in attesa</caption><thead><tr><th scope="col">Scheda</th><th scope="col">Utente</th><th scope="col">Voto</th><th scope="col">Recensione</th><th scope="col">Data</th><th scope="col">Decisione</th></tr></thead><tbody>';
		foreach ( $righe as $r ) {
			$u = get_userdata( (int) $r['utente_id'] );
			echo '<tr>';
			printf( '<td><a href="%1$s">%2$s</a></td>', esc_url( get_edit_post_link( (int) $r['scheda_id'] ) ), esc_html( get_the_title( (int) $r['scheda_id'] ) ) );
			printf( '<td>%s</td>', esc_html( $u ? $u->user_login . ' (dal ' . mysql2date( 'd/m/Y', $u->user_registered ) . ')' : '—' ) );
			printf( '<td>%s</td>', esc_html( (int) $r['stelle'] . ' stelle su 5 — ' . count( $r['aspetti'] ) . ' aspetti valutati' ) );
			printf( '<td><strong>%1$s</strong><br>%2$s</td>', esc_html( $r['titolo'] ), esc_html( wp_trim_words( trim( $r['pro'] . ' ' . $r['contro'] . ' ' . $r['commento'] ), 40 ) ) );
			printf( '<td>%s</td>', esc_html( $r['creato'] ) );
			echo '<td>';
			foreach ( array( 'valido' => 'Convalida', 'annullato' => 'Annulla' ) as $stato => $etichetta ) {
				printf(
					'<form method="post" action="%1$s" style="display:inline"><input type="hidden" name="action" value="st_modera_voto"><input type="hidden" name="voto" value="%2$d"><input type="hidden" name="stato" value="%3$s">%4$s<button class="button" type="submit">%5$s</button></form> ',
					esc_url( admin_url( 'admin-post.php' ) ),
					(int) $r['id'],
					esc_attr( $stato ),
					wp_nonce_field( 'st_modera_voto', '_wpnonce', true, false ),
					esc_html( $etichetta )
				);
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function azione_voto() {
		self::controlla( 'st_modera_voto' );
		$ok = Voti::cambia_stato( isset( $_POST['voto'] ) ? absint( $_POST['voto'] ) : 0, isset( $_POST['stato'] ) ? sanitize_key( $_POST['stato'] ) : '' );
		self::torna( 'st-recensioni', $ok );
	}

	/* ---------- Segnalazioni ---------- */

	public static function pagina_segnalazioni() {
		$righe = Segnalazioni::elenco();
		echo '<div class="wrap"><h1>Segnalazioni aperte</h1>';
		self::esito();
		echo '<p>Ogni segnalazione si chiude con una risposta motivata. Se chi ha segnalato ha lasciato un indirizzo email, la risposta gli arriva per posta.</p>';
		if ( ! $righe ) {
			echo '<p><strong>Nessuna segnalazione aperta.</strong></p></div>';
			return;
		}
		foreach ( $righe as $r ) {
			$id = (int) $r['id'];
			echo '<div class="card" style="max-width:none">';
			printf( '<h2>%1$s — <a href="%2$s">%3$s</a></h2>', esc_html( Segnalazioni::TIPI[ $r['tipo'] ] ?? $r['tipo'] ), esc_url( get_edit_post_link( (int) $r['scheda_id'] ) ), esc_html( get_the_title( (int) $r['scheda_id'] ) ) );
			printf( '<p><strong>Ricevuta:</strong> %1$s · <strong>Email:</strong> %2$s</p>', esc_html( $r['creato'] ), esc_html( $r['email'] ? $r['email'] : 'non indicata' ) );
			echo wpautop( esc_html( $r['testo'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf(
				'<form method="post" action="%1$s"><input type="hidden" name="action" value="st_rispondi"><input type="hidden" name="segnalazione" value="%2$d">%3$s<p><label for="st-risp-%2$d"><strong>Risposta motivata</strong></label><br><textarea id="st-risp-%2$d" name="risposta" rows="4" class="large-text" required></textarea></p><p><button class="button button-primary" type="submit">Rispondi e chiudi</button></p></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				$id,
				wp_nonce_field( 'st_rispondi', '_wpnonce', true, false )
			);
			echo '</div>';
		}
		echo '</div>';
	}

	public static function azione_risposta() {
		self::controlla( 'st_rispondi' );
		$ok = Segnalazioni::rispondi(
			isset( $_POST['segnalazione'] ) ? absint( $_POST['segnalazione'] ) : 0,
			isset( $_POST['risposta'] ) ? sanitize_textarea_field( wp_unslash( $_POST['risposta'] ) ) : ''
		);
		self::torna( 'st-segnalazioni', $ok );
	}

	/* ---------- eccellenze.org ---------- */

	public static function pagina_eccellenze() {
		echo '<div class="wrap"><h1>Coda di eccellenze.org</h1>';
		self::esito();
		$s = Impostazioni::soglie();
		printf(
			'<p>Candidatura: almeno %1$d recensori unici e punteggio ponderato di almeno %2$s. Revisione di uscita sotto %3$s. Ultimo calcolo notturno: %4$s.</p>',
			(int) $s['min_recensori'],
			esc_html( number_format_i18n( $s['soglia_ingresso'], 1 ) ),
			esc_html( number_format_i18n( $s['soglia_uscita'], 1 ) ),
			esc_html( get_option( 'st_ultimo_calcolo', 'mai' ) )
		);
		printf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="st_ricalcola">%2$s<p><button class="button" type="submit">Ricalcola adesso tutti i punteggi</button></p></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			wp_nonce_field( 'st_ricalcola', '_wpnonce', true, false )
		);
		printf( '<p>API per eccellenze.org: <code>%s</code></p>', esc_html( rest_url( 'statistiche-top/v1/eccellenze' ) ) );

		$gruppi = array(
			'candidata'        => array( 'Candidate: da verificare', array( 'approva' => 'Approva', 'respingi' => 'Respingi' ) ),
			'revisione_uscita' => array( 'Sotto la soglia di uscita: da rivedere', array( 'mantieni' => 'Mantieni', 'esci' => 'Togli da eccellenze.org' ) ),
			'pubblicata'       => array( 'Su eccellenze.org', array() ),
			'rifiutata'        => array( 'Respinte', array( 'riammetti' => 'Rendi di nuovo candidabile' ) ),
		);
		foreach ( $gruppi as $stato => $g ) {
			$schede = Eccellenze::in_stato( array( $stato ) );
			printf( '<h2>%1$s (%2$d)</h2>', esc_html( $g[0] ), count( $schede ) );
			if ( ! $schede ) {
				echo '<p>Nessuna.</p>';
				continue;
			}
			echo '<table class="widefat striped"><thead><tr><th scope="col">Scheda</th><th scope="col">Punteggio</th><th scope="col">Recensori</th><th scope="col">Decisione</th></tr></thead><tbody>';
			foreach ( $schede as $p ) {
				$agg = Schede::aggregato( $p->ID );
				echo '<tr>';
				printf( '<td><a href="%1$s">%2$s</a></td>', esc_url( get_edit_post_link( $p->ID ) ), esc_html( get_the_title( $p ) ) );
				printf( '<td>%s</td><td>%d</td><td>', esc_html( number_format_i18n( $agg['media_bayes'], 2 ) ), (int) $agg['n_recensori'] );
				if ( $g[1] ) {
					printf(
						'<form method="post" action="%1$s"><input type="hidden" name="action" value="st_eccellenze"><input type="hidden" name="scheda" value="%2$d">%3$s<label class="screen-reader-text" for="st-nota-%2$d">Motivazione</label><input type="text" id="st-nota-%2$d" name="nota" placeholder="Motivazione (va nello storico pubblico)" class="regular-text"> ',
						esc_url( admin_url( 'admin-post.php' ) ),
						(int) $p->ID,
						wp_nonce_field( 'st_eccellenze', '_wpnonce', true, false )
					);
					foreach ( $g[1] as $azione => $etichetta ) {
						printf( '<button class="button" type="submit" name="decisione" value="%1$s">%2$s</button> ', esc_attr( $azione ), esc_html( $etichetta ) );
					}
					echo '</form>';
				} else {
					echo '—';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	public static function azione_eccellenze() {
		self::controlla( 'st_eccellenze' );
		$ok = Eccellenze::decidi(
			isset( $_POST['scheda'] ) ? absint( $_POST['scheda'] ) : 0,
			isset( $_POST['decisione'] ) ? sanitize_key( $_POST['decisione'] ) : '',
			isset( $_POST['nota'] ) ? sanitize_text_field( wp_unslash( $_POST['nota'] ) ) : ''
		);
		self::torna( 'st-eccellenze', $ok );
	}

	public static function azione_ricalcola() {
		self::controlla( 'st_ricalcola' );
		Eccellenze::calcolo_notturno();
		self::torna( 'st-eccellenze', true );
	}

	/* ---------- Categorie suggerite (stile wiki) ---------- */

	private static function schede_con_categoria( $limite = 100 ) {
		return get_posts(
			array(
				'post_type'      => Schede::TIPO,
				'post_status'    => array( 'publish', 'pending' ),
				'posts_per_page' => $limite,
				'meta_query'     => array(
					array(
						'key'     => '_st_categoria_suggerita',
						'value'   => '',
						'compare' => '!=',
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
	}

	private static function categorie_conteggio() {
		static $n = null;
		if ( null === $n ) {
			$n = count( self::schede_con_categoria( 200 ) );
		}
		return $n;
	}

	public static function pagina_categorie() {
		echo '<div class="wrap"><h1>Categorie da decidere</h1>';
		self::esito();
		echo '<p>Chi propone una scheda può scrivere la categoria con parole sue. Come in un wiki, qui decidi: collegarla a un settore esistente, creare un settore nuovo con quel nome, oppure scartarla. Ogni decisione resta nello storico pubblico della scheda.</p>';
		$schede = self::schede_con_categoria();
		if ( ! $schede ) {
			echo '<p><strong>Nessuna categoria da decidere.</strong></p></div>';
			return;
		}
		$settori = get_terms( array( 'taxonomy' => Schede::SETTORE, 'hide_empty' => false ) );
		echo '<table class="widefat striped"><thead><tr><th scope="col">Scheda</th><th scope="col">Categoria suggerita</th><th scope="col">Settore attuale</th><th scope="col">Decisione</th></tr></thead><tbody>';
		foreach ( $schede as $p ) {
			$sugg  = (string) get_post_meta( $p->ID, '_st_categoria_suggerita', true );
			$ora   = wp_get_post_terms( $p->ID, Schede::SETTORE, array( 'fields' => 'names' ) );
			$campo = 'st-settore-' . $p->ID;
			echo '<tr>';
			printf( '<td><a href="%1$s">%2$s</a> (%3$s)</td>', esc_url( get_edit_post_link( $p->ID ) ), esc_html( get_the_title( $p ) ), 'publish' === $p->post_status ? 'pubblicata' : 'in revisione' );
			printf( '<td><strong>%s</strong></td>', esc_html( $sugg ) );
			printf( '<td>%s</td>', esc_html( $ora ? implode( ', ', $ora ) : '—' ) );
			printf( '<td><form method="post" action="%1$s"><input type="hidden" name="action" value="st_categoria"><input type="hidden" name="scheda" value="%2$d">%3$s', esc_url( admin_url( 'admin-post.php' ) ), (int) $p->ID, wp_nonce_field( 'st_categoria', '_wpnonce', true, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<label for="%1$s">Settore esistente</label> <select id="%1$s" name="settore"><option value="">Scegli…</option>', esc_attr( $campo ) );
			foreach ( is_array( $settori ) ? $settori : array() as $t ) {
				printf( '<option value="%1$d">%2$s</option>', (int) $t->term_id, esc_html( $t->name ) );
			}
			echo '</select> <button class="button" type="submit" name="decisione" value="collega">Collega</button> ';
			printf( '<button class="button" type="submit" name="decisione" value="crea">Crea il settore «%s»</button> ', esc_html( $sugg ) );
			echo '<button class="button" type="submit" name="decisione" value="scarta">Scarta</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function azione_categoria() {
		self::controlla( 'st_categoria' );
		$id   = isset( $_POST['scheda'] ) ? absint( $_POST['scheda'] ) : 0;
		$dec  = isset( $_POST['decisione'] ) ? sanitize_key( $_POST['decisione'] ) : '';
		$sugg = (string) get_post_meta( $id, '_st_categoria_suggerita', true );
		$ok   = false;
		if ( $id && get_post_type( $id ) === Schede::TIPO && '' !== $sugg ) {
			if ( 'collega' === $dec && ! empty( $_POST['settore'] ) ) {
				$t = get_term( absint( $_POST['settore'] ), Schede::SETTORE );
				if ( $t && ! is_wp_error( $t ) ) {
					wp_set_object_terms( $id, array( (int) $t->term_id ), Schede::SETTORE, true );
					Schede::aggiungi_storico( $id, 'Categoria suggerita «' . $sugg . '»: collegata al settore «' . $t->name . '».' );
					$ok = true;
				}
			} elseif ( 'crea' === $dec ) {
				$t = term_exists( $sugg, Schede::SETTORE );
				$t = $t ? $t : wp_insert_term( $sugg, Schede::SETTORE );
				if ( ! is_wp_error( $t ) ) {
					wp_set_object_terms( $id, array( (int) $t['term_id'] ), Schede::SETTORE, true );
					Schede::aggiungi_storico( $id, 'Categoria suggerita «' . $sugg . '»: creato il nuovo settore.' );
					$ok = true;
				}
			} elseif ( 'scarta' === $dec ) {
				Schede::aggiungi_storico( $id, 'Categoria suggerita «' . $sugg . '»: non usata dalla redazione.' );
				$ok = true;
			}
			if ( $ok ) {
				delete_post_meta( $id, '_st_categoria_suggerita' );
			}
		}
		self::torna( 'st-categorie', $ok );
	}

	/* ---------- Impostazioni ---------- */

	public static function pagina_impostazioni() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$valori = Impostazioni::tutte();
		echo '<div class="wrap"><h1>Impostazioni di statistiche.top</h1>';
		settings_errors( Impostazioni::OPZIONE );
		echo '<p>I valori sono pubblicati nella pagina della metodologia (shortcode <code>[st_metodologia]</code>): ogni modifica è visibile a tutti.</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'st_impostazioni' );
		echo '<table class="form-table" role="presentation">';
		foreach ( Impostazioni::campi() as $chiave => $campo ) {
			list( , $etichetta, $tipo, $min, $max ) = $campo;
			$attr = 'url' === $tipo ? 'type="url" class="regular-text"' : sprintf( 'type="number" class="small-text" min="%1$s" max="%2$s" step="%3$s"', esc_attr( $min ), esc_attr( $max ), 'int' === $tipo ? '1' : '0.1' );
			printf(
				'<tr><th scope="row"><label for="st-%1$s">%2$s</label></th><td><input id="st-%1$s" name="%3$s[%1$s]" value="%4$s" %5$s></td></tr>',
				esc_attr( $chiave ),
				esc_html( $etichetta ),
				esc_attr( Impostazioni::OPZIONE ),
				esc_attr( $valori[ $chiave ] ),
				$attr // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escape.
			);
		}
		echo '</table>';
		submit_button( 'Salva le impostazioni' );
		echo '</form></div>';
	}
}
