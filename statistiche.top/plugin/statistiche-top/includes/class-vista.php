<?php
/**
 * Parte pubblica: pagina della scheda, moduli, metodologia, classifica.
 *
 * Accessibilità (WCAG 2.2 AA): ogni campo ha un'etichetta, i gruppi di voti sono
 * fieldset con legend, gli esiti sono annunciati (role="status"/"alert") e
 * ricevono il focus, le informazioni non dipendono mai dal solo colore.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Vista {

	public static function avvia() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'registra_risorse' ) );
		add_filter( 'the_content', array( __CLASS__, 'contenuto_scheda' ), 20 );
		add_filter( 'render_block', array( __CLASS__, 'pulisci_blocchi_tema' ), 10, 3 );
		add_shortcode( 'st_nuova_scheda', array( __CLASS__, 'shortcode_nuova_scheda' ) );
		add_shortcode( 'st_metodologia', array( __CLASS__, 'shortcode_metodologia' ) );
		add_shortcode( 'st_classifica', array( __CLASS__, 'shortcode_classifica' ) );
	}

	public static function registra_risorse() {
		wp_register_style( 'statistiche-top', plugins_url( 'assets/statistiche-top.css', FILE ), array(), VERSIONE );
		wp_register_script( 'statistiche-top', plugins_url( 'assets/statistiche-top.js', FILE ), array(), VERSIONE, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( is_singular( Schede::TIPO ) || is_post_type_archive( Schede::TIPO ) || is_tax( Schede::SETTORE ) ) {
			self::accoda();
		}
	}

	private static function accoda() {
		wp_enqueue_style( 'statistiche-top' );
		wp_enqueue_script( 'statistiche-top' );
	}

	/* ---------- Reindirizzamenti ed esiti ---------- */

	public static function url_ritorno() {
		$ref = wp_get_referer();
		$ref = $ref ? wp_validate_redirect( $ref, home_url( '/' ) ) : home_url( '/' );
		return remove_query_arg( array( 'st_esito', 'st_msg', 'st_dove' ), $ref );
	}

	public static function reindirizza( $url, $tipo, $codice, $dove = 'st-modulo' ) {
		$url = remove_query_arg( array( 'st_esito', 'st_msg', 'st_dove' ), $url );
		wp_safe_redirect(
			add_query_arg(
				array(
					'st_esito' => 'ok' === $tipo ? 'ok' : 'errore',
					'st_msg'   => sanitize_key( $codice ),
					'st_dove'  => sanitize_key( $dove ),
				),
				$url
			)
		);
		exit;
	}

	private static function testo_esito( $codice ) {
		$testi = array(
			'scheda_inviata'          => 'Grazie! La scheda è arrivata e sarà pubblicata dopo la revisione della redazione.',
			'troppe_in_attesa'        => sprintf( 'Hai già %d schede in attesa di revisione. Potrai proporne altre quando saranno state esaminate.', Schede::MAX_IN_ATTESA ),
			'modulo'                  => 'Alcuni campi vanno corretti: li trovi indicati qui sotto.',
			'salvataggio'             => 'Non è stato possibile salvare. Riprova fra qualche minuto.',
			'recensione_salvata'      => 'Grazie! La tua recensione è stata registrata.',
			'recensione_modificata'   => 'La tua recensione è stata aggiornata.',
			'recensione_sospesa'      => 'Grazie! La recensione è stata registrata e sarà conteggiata dopo un controllo della redazione.',
			'recensione_annullata'    => 'La tua recensione su questa scheda è stata annullata dalla redazione e non si può modificare.',
			'conflitto_registrato'    => 'Abbiamo registrato il tuo legame con questo soggetto. Non potrai recensirlo, e un\'eventuale recensione già lasciata non viene più conteggiata.',
			'segnalazione_inviata'    => 'Grazie, la segnalazione è arrivata. La redazione risponderà con una motivazione.',
			'segnalazione_incompleta' => 'Scegli il tipo di segnalazione e descrivi il problema (almeno 10 caratteri).',
			'email_non_valida'        => 'L\'indirizzo email non sembra valido.',
			'troppe_segnalazioni'     => 'Hai inviato molte segnalazioni nell\'ultima ora. Riprova più tardi.',
		);
		return isset( $testi[ $codice ] ) ? $testi[ $codice ] : Regole::messaggio( $codice, Impostazioni::tutte() );
	}

	/** Messaggio di esito da mostrare in un punto preciso della pagina. */
	public static function avviso( $dove ) {
		// Lettura di soli parametri di visualizzazione: non serve un nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['st_msg'] ) || empty( $_GET['st_dove'] ) || sanitize_key( $_GET['st_dove'] ) !== $dove ) {
			return '';
		}
		$ok     = isset( $_GET['st_esito'] ) && 'ok' === $_GET['st_esito'];
		$codice = sanitize_key( $_GET['st_msg'] );
		// phpcs:enable
		return sprintf(
			'<div class="st-avviso %1$s" role="%2$s" tabindex="-1" data-st-focus><p><strong>%3$s</strong> %4$s</p></div>',
			$ok ? 'st-avviso--ok' : 'st-avviso--errore',
			$ok ? 'status' : 'alert',
			$ok ? 'Fatto.' : 'Attenzione:',
			esc_html( self::testo_esito( $codice ) )
		);
	}

	/* ---------- Blocchi del tema sulle schede ---------- */

	/**
	 * Sulle schede toglie le parti del tema pensate per gli articoli del blog:
	 * - la riga "Scritto da … in …", che mostrerebbe il nome di chi ha proposto la scheda
	 *   (l'informativa privacy dice che non compare);
	 * - il blocco "Altri articoli" con gli articoli del blog;
	 * - i commenti, che sulle schede non si usano (ci sono recensioni e segnalazioni).
	 *
	 * @param string         $html     HTML del blocco.
	 * @param array          $blocco   blocco analizzato.
	 * @param \WP_Block|null $istanza  istanza del blocco, con il contesto.
	 */
	public static function pulisci_blocchi_tema( $html, $blocco, $istanza = null ) {
		$nome = isset( $blocco['blockName'] ) ? $blocco['blockName'] : '';
		if ( ! $nome ) {
			return $html;
		}
		$tipo = ( $istanza instanceof \WP_Block && isset( $istanza->context['postType'] ) ) ? $istanza->context['postType'] : '';
		$su_scheda = Schede::TIPO === $tipo || ( ! $tipo && is_singular( Schede::TIPO ) );

		// Autore: nessun blocco che mostri chi ha creato una scheda.
		if ( $su_scheda && in_array( $nome, array( 'core/post-author', 'core/post-author-name', 'core/post-author-biography' ), true ) ) {
			return '';
		}
		if ( ! is_singular( Schede::TIPO ) ) {
			return $html;
		}
		if ( in_array( $nome, array( 'core/comments', 'core/post-comments-form', 'core/comments-query-loop' ), true ) ) {
			return '';
		}
		if ( in_array( $nome, array( 'core/group', 'core/columns', 'core/column' ), true ) && ! empty( $blocco['innerBlocks'] ) ) {
			// Il gruppo della riga "Scritto da … in …".
			if ( self::contiene( $blocco['innerBlocks'], static function ( $b ) {
				return in_array( $b['blockName'], array( 'core/post-author', 'core/post-author-name' ), true );
			}, 1 ) ) {
				return '';
			}
			// Il gruppo "Altri articoli": un elenco di articoli del blog, non di schede.
			if ( self::contiene( $blocco['innerBlocks'], static function ( $b ) {
				$q = isset( $b['attrs']['query'] ) ? $b['attrs']['query'] : array();
				return 'core/query' === $b['blockName'] && empty( $q['inherit'] ) && ( empty( $q['postType'] ) || 'post' === $q['postType'] );
			}, 1 ) ) {
				return '';
			}
			// Il gruppo delle etichette del blog, vuoto sulle schede.
			$solo_etichette = true;
			foreach ( $blocco['innerBlocks'] as $b ) {
				if ( ! empty( $b['blockName'] ) && 'core/post-terms' !== $b['blockName'] ) {
					$solo_etichette = false;
				}
			}
			if ( $solo_etichette ) {
				return '';
			}
		}
		return $html;
	}

	/** Cerca fra i blocchi figli uno che soddisfi la condizione (profondità 1 = solo i figli diretti: più a fondo si rischia di togliere gruppi che contengono tutta la scheda). */
	private static function contiene( array $blocchi, callable $condizione, $profondita ) {
		foreach ( $blocchi as $b ) {
			if ( empty( $b['blockName'] ) ) {
				continue;
			}
			if ( $condizione( $b ) ) {
				return true;
			}
			if ( $profondita > 1 && ! empty( $b['innerBlocks'] ) && self::contiene( $b['innerBlocks'], $condizione, $profondita - 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------- Pagina della scheda ---------- */

	public static function contenuto_scheda( $contenuto ) {
		if ( ! is_singular( Schede::TIPO ) || ! in_the_loop() || ! is_main_query() ) {
			return $contenuto;
		}
		$id  = get_the_ID();
		$d   = Schede::dati( $id );
		$agg = Schede::aggregato( $id );
		$ecc = Eccellenze::stato( $id );

		ob_start();
		?>
		<div class="st-blocco st-scheda">
			<?php if ( in_array( $ecc, array( 'pubblicata', 'revisione_uscita' ), true ) ) : ?>
				<p class="st-etichetta-eccellenza">
					<span aria-hidden="true">★</span>
					<strong>Presente su <a href="<?php echo esc_url( Impostazioni::valore( 'url_eccellenze' ) ); ?>">eccellenze.org</a></strong>
				</p>
			<?php endif; ?>

			<dl class="st-dati">
				<div><dt>Tipo</dt><dd><?php echo esc_html( isset( Schede::TIPI_SOGGETTO[ $d['tipo'] ] ) ? Schede::TIPI_SOGGETTO[ $d['tipo'] ] : '—' ); ?></dd></div>
				<div><dt>Settore</dt><dd><?php echo esc_html( $d['settori'] ? implode( ', ', $d['settori'] ) : '—' ); ?></dd></div>
				<?php if ( $d['luogo'] ) : ?>
					<div><dt>Luogo</dt><dd><?php echo esc_html( $d['luogo'] ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</div>
		<?php
		$prima = ob_get_clean();

		ob_start();
		?>
		<div class="st-blocco st-scheda">
			<?php if ( has_post_thumbnail( $id ) && $d['licenza_immagine'] ) : ?>
				<p class="st-nota">Immagine: <?php echo esc_html( $d['licenza_immagine'] ); ?></p>
			<?php endif; ?>

			<section aria-labelledby="st-fonti-titolo">
				<h2 id="st-fonti-titolo">Fonti</h2>
				<?php if ( $d['fonti'] ) : ?>
					<ul class="st-fonti">
						<?php foreach ( $d['fonti'] as $f ) : ?>
							<li><a href="<?php echo esc_url( $f['url'] ); ?>" rel="nofollow noopener"><?php echo esc_html( $f['descrizione'] ? $f['descrizione'] : wp_parse_url( $f['url'], PHP_URL_HOST ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p>Nessuna fonte indicata. <a href="#st-segnala">Segnalalo</a>.</p>
				<?php endif; ?>
			</section>

			<?php echo self::punteggio( $agg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escape. ?>
			<?php echo self::elenco_recensioni( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::modulo_recensione( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::modulo_segnalazione( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::storico( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		$dopo = ob_get_clean();

		// La sintesi è testo semplice: la mostriamo con i soli a capo.
		return $prima . '<section aria-labelledby="st-sintesi-titolo" class="st-blocco"><h2 id="st-sintesi-titolo">Sintesi</h2>' . $contenuto . '</section>' . $dopo;
	}

	private static function numero( $n, $decimali = 2 ) {
		return number_format_i18n( (float) $n, $decimali );
	}

	private static function punteggio( array $agg ) {
		ob_start();
		?>
		<section aria-labelledby="st-punteggio-titolo" class="st-punteggio">
			<h2 id="st-punteggio-titolo">Punteggio</h2>
			<?php if ( ! $agg['n_recensioni'] ) : ?>
				<p>Ancora nessuna recensione. <a href="#st-recensisci">Scrivi la prima</a>.</p>
			<?php else : ?>
				<p class="st-punteggio-principale">
					<span class="st-cifra"><?php echo esc_html( self::numero( $agg['media_bayes'] ) ); ?></span>
					<span>su 5 · punteggio ponderato</span>
				</p>
				<p>
					Media semplice <?php echo esc_html( self::numero( $agg['media'] ) ); ?>
					· <?php echo esc_html( sprintf( _n( '%s recensione', '%s recensioni', $agg['n_recensioni'], 'statistiche-top' ), number_format_i18n( $agg['n_recensioni'] ) ) ); ?>
					da <?php echo esc_html( sprintf( _n( '%s recensore unico', '%s recensori unici', $agg['n_recensori'], 'statistiche-top' ), number_format_i18n( $agg['n_recensori'] ) ) ); ?>.
				</p>
				<table class="st-tabella">
					<caption>Media per criterio (da 1 a 5)</caption>
					<thead><tr><th scope="col">Criterio</th><th scope="col">Media</th><th scope="col"><span class="screen-reader-text">Barra</span></th></tr></thead>
					<tbody>
						<?php foreach ( Punteggi::CRITERI as $chiave => $nome ) : ?>
							<?php $v = isset( $agg['criteri'][ $chiave ] ) ? (float) $agg['criteri'][ $chiave ] : 0; ?>
							<tr>
								<th scope="row"><?php echo esc_html( $nome ); ?></th>
								<td><?php echo esc_html( self::numero( $v ) ); ?></td>
								<td><span class="st-barra" aria-hidden="true"><span style="width:<?php echo esc_attr( round( $v / 5 * 100 ) ); ?>%"></span></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p class="st-nota"><a href="<?php echo esc_url( self::url_metodologia() ); ?>">Come calcoliamo il punteggio</a></p>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function url_metodologia() {
		$pagina = get_page_by_path( 'metodologia' );
		return $pagina ? get_permalink( $pagina ) : home_url( '/metodologia/' );
	}

	private static function elenco_recensioni( $id ) {
		$righe = Voti::recensioni_pubbliche( $id );
		if ( ! $righe ) {
			return '';
		}
		ob_start();
		?>
		<section aria-labelledby="st-recensioni-titolo">
			<h2 id="st-recensioni-titolo">Recensioni con commento</h2>
			<ol class="st-recensioni">
				<?php foreach ( $righe as $r ) : ?>
					<?php
					$voti  = array_intersect_key( $r, Punteggi::CRITERI );
					$utente = get_userdata( (int) $r['utente_id'] );
					?>
					<li>
						<article class="st-recensione">
							<h3><?php echo esc_html( $utente ? $utente->display_name : 'Utente' ); ?></h3>
							<p class="st-nota">
								<?php echo esc_html( 'Media ' . self::numero( Punteggi::media_recensione( array_map( 'intval', $voti ) ) ) . ' su 5' ); ?>
								— <?php foreach ( Punteggi::CRITERI as $k => $nome ) { echo esc_html( $nome . ' ' . (int) $r[ $k ] . ( 'trasparenza' === $k ? '' : ', ' ) ); } ?>
								— <time datetime="<?php echo esc_attr( mysql2date( 'c', $r['modificato'] ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $r['modificato'] ) ); ?></time>
								<?php echo $r['modificato'] !== $r['creato'] ? esc_html( '(modificata)' ) : ''; ?>
							</p>
							<?php echo wpautop( esc_html( $r['commento'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</article>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function modulo_recensione( $id ) {
		ob_start();
		echo '<section aria-labelledby="st-recensisci-titolo" id="st-recensisci">';
		echo '<h2 id="st-recensisci-titolo">La tua recensione</h2>';
		echo self::avviso( 'st-recensisci' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! is_user_logged_in() ) {
			printf(
				'<p>Per recensire serve un account verificato via email. <a href="%1$s">Accedi</a>%2$s.</p>',
				esc_url( wp_login_url( get_permalink( $id ) . '#st-recensisci' ) ),
				get_option( 'users_can_register' ) ? ' oppure <a href="' . esc_url( wp_registration_url() ) . '">registrati</a>' : ''
			);
			echo '</section>';
			return ob_get_clean();
		}

		$utente_id  = get_current_user_id();
		$esistente  = Voti::recensione_utente( $id, $utente_id );
		$imp        = Impostazioni::tutte();
		list( $ammesso, $motivo ) = Regole::puo_recensire( Voti::fatti( $id, $utente_id, $esistente ), $imp );
		if ( $esistente && 'annullato' === $esistente['stato'] ) {
			$ammesso = false;
			$motivo  = 'recensione_annullata';
		}

		if ( $esistente && 'sospeso' === $esistente['stato'] ) {
			echo '<p class="st-nota">La tua recensione è in attesa di un controllo della redazione.</p>';
		}

		if ( ! $ammesso ) {
			echo '<p>' . esc_html( self::testo_esito( $motivo ) ) . '</p>';
		} else {
			$valori = $esistente ? $esistente : array();
			?>
			<form class="st-modulo" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="st_voto">
				<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'st_voto_' . $id, 'st_nonce', false ); ?>
				<p class="st-nota">Dai un voto da 1 (minimo) a 5 (massimo) a ciascun criterio. Tutti i criteri sono obbligatori.
					<?php if ( $esistente ) : ?>
						Puoi modificare la recensione fino a <?php echo esc_html( (int) $imp['giorni_modifica'] ); ?> giorni dalla prima pubblicazione.
					<?php endif; ?>
				</p>
				<?php foreach ( Punteggi::CRITERI as $chiave => $nome ) : ?>
					<fieldset class="st-voto">
						<legend><?php echo esc_html( $nome ); ?> <span class="st-obbligatorio">(obbligatorio)</span></legend>
						<div class="st-voto-scelte">
							<?php for ( $v = Punteggi::VOTO_MIN; $v <= Punteggi::VOTO_MAX; $v++ ) : ?>
								<?php $campo = 'st-' . $chiave . '-' . $v; ?>
								<input type="radio" id="<?php echo esc_attr( $campo ); ?>" name="st_voto[<?php echo esc_attr( $chiave ); ?>]" value="<?php echo esc_attr( $v ); ?>" required <?php checked( isset( $valori[ $chiave ] ) && (int) $valori[ $chiave ] === $v ); ?>>
								<label for="<?php echo esc_attr( $campo ); ?>"><?php echo esc_html( $v ); ?><span class="screen-reader-text"><?php echo esc_html( 1 === $v ? ' (minimo)' : ( 5 === $v ? ' (massimo)' : '' ) ); ?></span></label>
							<?php endfor; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
				<p>
					<label for="st-commento">Commento motivato (facoltativo, massimo <?php echo esc_html( Voti::MAX_COMMENTO ); ?> caratteri)</label>
					<textarea id="st-commento" name="st_commento" rows="5" maxlength="<?php echo esc_attr( Voti::MAX_COMMENTO ); ?>" aria-describedby="st-commento-aiuto"><?php echo esc_textarea( isset( $valori['commento'] ) ? $valori['commento'] : '' ); ?></textarea>
					<span id="st-commento-aiuto" class="st-nota">Spiega su quali fatti si basa il tuo giudizio. Niente insulti né dati personali di altri.</span>
				</p>
				<p class="st-spunta">
					<input type="checkbox" id="st-nessun-conflitto-voto" name="st_nessun_conflitto" value="1" required>
					<label for="st-nessun-conflitto-voto">Dichiaro di non essere il soggetto della scheda, né un suo collaboratore, familiare o concorrente diretto.</label>
				</p>
				<p><button type="submit" class="st-pulsante"><?php echo $esistente ? 'Aggiorna la recensione' : 'Pubblica la recensione'; ?></button></p>
			</form>
			<?php
		}

		$conflitti = array_map( 'intval', (array) get_user_meta( $utente_id, 'st_conflitti', true ) );
		if ( ! in_array( $id, $conflitti, true ) ) {
			?>
			<details class="st-dettagli">
				<summary>Hai un legame con questo soggetto?</summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="st_conflitto">
					<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $id ); ?>">
					<?php wp_nonce_field( 'st_conflitto_' . $id, 'st_nonce', false ); ?>
					<p>Se lavori o hai lavorato con questo soggetto, ne sei parente o concorrente, dichiaralo: non potrai recensirlo e l'eventuale recensione che hai già lasciato non verrà più conteggiata.</p>
					<p><button type="submit" class="st-pulsante st-pulsante--secondario">Dichiaro un legame</button></p>
				</form>
			</details>
			<?php
		}
		echo '</section>';
		return ob_get_clean();
	}

	private static function modulo_segnalazione( $id ) {
		ob_start();
		?>
		<section aria-labelledby="st-segnala-titolo" id="st-segnala">
			<h2 id="st-segnala-titolo">Segnala un problema</h2>
			<?php echo self::avviso( 'st-segnala' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<details class="st-dettagli" <?php echo self::avviso( 'st-segnala' ) ? 'open' : ''; ?>>
				<summary>Apri il modulo di segnalazione</summary>
				<form class="st-modulo" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="st_segnalazione">
					<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $id ); ?>">
					<?php wp_nonce_field( 'st_segnala_' . $id, 'st_nonce', false ); ?>
					<?php self::trappola(); ?>
					<fieldset>
						<legend>Di che cosa si tratta? <span class="st-obbligatorio">(obbligatorio)</span></legend>
						<?php foreach ( Segnalazioni::TIPI as $chiave => $testo ) : ?>
							<p class="st-spunta">
								<input type="radio" id="st-segn-<?php echo esc_attr( $chiave ); ?>" name="st_tipo_segnalazione" value="<?php echo esc_attr( $chiave ); ?>" required>
								<label for="st-segn-<?php echo esc_attr( $chiave ); ?>"><?php echo esc_html( $testo ); ?></label>
							</p>
						<?php endforeach; ?>
					</fieldset>
					<p>
						<label for="st-testo">Descrivi il problema <span class="st-obbligatorio">(obbligatorio)</span></label>
						<textarea id="st-testo" name="st_testo" rows="4" required minlength="10" maxlength="<?php echo esc_attr( Segnalazioni::MAX_TESTO ); ?>"></textarea>
					</p>
					<?php if ( ! is_user_logged_in() ) : ?>
						<p>
							<label for="st-email">Email per ricevere la risposta (facoltativa)</label>
							<input type="email" id="st-email" name="st_email" autocomplete="email">
						</p>
					<?php endif; ?>
					<p class="st-nota">Useremo questi dati solo per gestire la segnalazione. <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Informativa privacy</a>.</p>
					<p><button type="submit" class="st-pulsante">Invia la segnalazione</button></p>
				</form>
			</details>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function storico( $id ) {
		$storico = array_reverse( Schede::storico( $id ) );
		if ( ! $storico ) {
			return '';
		}
		ob_start();
		?>
		<section aria-labelledby="st-storico-titolo">
			<h2 id="st-storico-titolo">Storico delle modifiche</h2>
			<details class="st-dettagli">
				<summary>Mostra lo storico (<?php echo esc_html( count( $storico ) ); ?> voci)</summary>
				<ol class="st-storico">
					<?php foreach ( $storico as $voce ) : ?>
						<li><time datetime="<?php echo esc_attr( mysql2date( 'c', $voce['data'] ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $voce['data'] ) ); ?></time> — <?php echo esc_html( $voce['testo'] ); ?></li>
					<?php endforeach; ?>
				</ol>
			</details>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Testi degli aiuti a fumetto del modulo di proposta (HTML fisso, scritto qui). */
	const AIUTI = array(
		'tipo'      => '<p>Scegli <strong>persona</strong> solo se ha un ruolo pubblico: amministratore d\'azienda, professionista noto, figura istituzionale, artista, sportivo. Non si creano schede su privati cittadini.</p><p>Scegli <strong>azienda o organizzazione</strong> per imprese, associazioni, enti e fondazioni.</p>',
		'nome'      => '<p>Il nome completo, come compare nelle fonti ufficiali: per le persone nome e cognome, per le aziende la ragione sociale o il marchio più noto (per esempio «Rossi Costruzioni S.r.l.»).</p><p>Se esiste già una scheda con lo stesso nome, aggiungi un dettaglio per distinguerla, come la città.</p>',
		'settore'   => '<p>Il settore principale in cui il soggetto si distingue. Se ne vedi più d\'uno, scegli quello per cui merita la scheda: la redazione può aggiungerne altri.</p><p>Se non trovi il settore giusto, scegli il più vicino e spiegalo nella sintesi.</p>',
		'luogo'     => '<p>Facoltativo. Dove opera il soggetto: la città della sede o dell\'attività principale, oppure la regione o il paese se lavora su un territorio ampio.</p><p>Per le persone non indicare mai l\'indirizzo di casa.</p>',
		'sintesi'   => '<p>In poche righe: chi è, che cosa fa e <strong>perché si distingue</strong>.</p><p>Scrivi solo fatti che si trovano nelle fonti, senza giudizi personali: i giudizi vanno nelle recensioni. Per esempio: «Fondata nel 1987 a Bologna, produce macchine per l\'imballaggio; nel 2025 ha ricevuto un premio nazionale per l\'innovazione.»</p>',
		'fonti'     => '<p>Almeno una fonte, fino a tre. Valgono il sito ufficiale, articoli di giornali e riviste, bilanci, il registro delle imprese, atti pubblici.</p><p>Non valgono profili social personali, forum o pagine pubblicitarie. Accanto a ogni indirizzo scrivi che cosa conferma, per esempio «Bilancio 2025: fatturato e dipendenti».</p>',
		'figura'    => '<p>Serve solo per le schede su una persona. La legge sulla privacy consente di parlare pubblicamente di una persona per il ruolo pubblico che ricopre, non della sua vita privata: per questo la scheda deve riguardare solo quel ruolo.</p><p>Per le aziende puoi lasciare la casella vuota.</p>',
		'conflitto' => '<p>Non puoi proporre la scheda di te stesso, della tua azienda, del tuo datore di lavoro, di un cliente, di un socio, di un parente o di un concorrente diretto.</p><p>Serve a mantenere le schede imparziali. Se hai un legame con il soggetto puoi comunque segnalare errori dalla sua scheda, una volta pubblicata.</p>',
		'invio'     => '<p>La scheda non compare subito: la redazione controlla dati e fonti, poi la pubblica, la corregge o ti scrive. Puoi avere al massimo 3 schede in attesa di revisione.</p><p>Se qualcosa va corretto, il modulo ti indica quali campi e conserva per 10 minuti quello che hai scritto.</p>',
	);

	/** Pulsante "?" che apre il balloon di aiuto. Nascosto finché lo script non lo attiva. */
	private static function pulsante_aiuto( $chiave, $nome ) {
		printf(
			'<button type="button" class="st-aiuto-pulsante" aria-expanded="false" aria-controls="st-aiuto-%1$s" hidden><span aria-hidden="true">?</span><span class="screen-reader-text">Aiuto: %2$s</span></button>',
			esc_attr( $chiave ),
			esc_html( $nome )
		);
	}

	/** Testo dell'aiuto: visibile senza JavaScript, diventa un balloon con lo script. */
	private static function balloon( $chiave ) {
		printf( '<div class="st-aiuto" id="st-aiuto-%1$s">%2$s</div>', esc_attr( $chiave ), wp_kses_post( self::AIUTI[ $chiave ] ) );
	}

	/** Campo trappola contro i programmi automatici, nascosto a persone e lettori di schermo. */
	private static function trappola() {
		echo '<div class="st-trappola" aria-hidden="true"><label for="st-sito-web">Non compilare questo campo</label><input type="text" id="st-sito-web" name="st_sito_web" tabindex="-1" autocomplete="off"></div>';
	}

	/* ---------- Shortcode: nuova scheda ---------- */

	public static function shortcode_nuova_scheda() {
		self::accoda();
		ob_start();
		echo '<div class="st-blocco" id="st-modulo">';
		echo self::avviso( 'st-modulo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! is_user_logged_in() ) {
			printf(
				'<p>Per proporre una scheda serve un account verificato via email. <a href="%1$s">Accedi</a>%2$s.</p></div>',
				esc_url( wp_login_url( get_permalink() ) ),
				get_option( 'users_can_register' ) ? ' oppure <a href="' . esc_url( wp_registration_url() ) . '">registrati</a>' : ''
			);
			return ob_get_clean();
		}

		$bozza  = get_transient( 'st_bozza_' . get_current_user_id() );
		$v      = is_array( $bozza ) ? $bozza['dati'] : array();
		$errori = is_array( $bozza ) ? $bozza['errori'] : array();
		$val    = static function ( $k ) use ( $v ) {
			return isset( $v[ $k ] ) && ! is_array( $v[ $k ] ) ? (string) $v[ $k ] : '';
		};
		$errore = static function ( $campo ) use ( $errori ) {
			if ( isset( $errori[ $campo ] ) ) {
				printf( '<span class="st-errore" id="%1$s-errore"><strong>Errore:</strong> %2$s</span>', esc_attr( $campo ), esc_html( $errori[ $campo ] ) );
			}
		};
		// aria-invalid solo sui campi: per i gruppi (fieldset) basta il collegamento al messaggio di errore.
		$descritto = static function ( $campo, $aiuto = '', $gruppo = false ) use ( $errori ) {
			$id = array_filter( array( $aiuto, isset( $errori[ $campo ] ) ? $campo . '-errore' : '' ) );
			echo $id ? ' aria-describedby="' . esc_attr( implode( ' ', $id ) ) . '"' : '';
			if ( isset( $errori[ $campo ] ) ) {
				echo $gruppo ? ' class="st-gruppo-errato"' : ' aria-invalid="true"';
			}
		};
		$settori = get_terms( array( 'taxonomy' => Schede::SETTORE, 'hide_empty' => false ) );
		$fonti_u = isset( $v['st_fonte_url'] ) ? (array) $v['st_fonte_url'] : array();
		$fonti_d = isset( $v['st_fonte_descrizione'] ) ? (array) $v['st_fonte_descrizione'] : array();
		?>
		<form class="st-modulo st-modulo-proposta" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="st_nuova_scheda">
			<?php wp_nonce_field( 'st_nuova_scheda', 'st_nonce', false ); ?>
			<?php self::trappola(); ?>
			<p class="st-nota">I campi obbligatori sono indicati. Accanto a ogni parte del modulo, il pulsante <strong>?</strong> apre una spiegazione. La scheda sarà pubblicata dopo la revisione della redazione.</p>

			<fieldset<?php $descritto( 'st_tipo', 'st-aiuto-tipo', true ); ?>>
				<legend class="st-legenda-con-aiuto">La scheda riguarda <span class="st-obbligatorio">(obbligatorio)</span></legend>
				<div class="st-campo-testa st-campo-testa--gruppo"><?php self::pulsante_aiuto( 'tipo', 'tipo di soggetto' ); ?></div>
				<?php self::balloon( 'tipo' ); ?>
				<?php $errore( 'st_tipo' ); ?>
				<?php foreach ( Schede::TIPI_SOGGETTO as $k => $nome ) : ?>
					<p class="st-spunta">
						<input type="radio" id="st-tipo-<?php echo esc_attr( $k ); ?>" name="st_tipo" value="<?php echo esc_attr( $k ); ?>" required <?php checked( $val( 'st_tipo' ), $k ); ?>>
						<label for="st-tipo-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( 'persona' === $k ? 'una persona con un ruolo pubblico' : 'un\'azienda o un\'organizzazione' ); ?></label>
					</p>
				<?php endforeach; ?>
			</fieldset>

			<div class="st-campo">
				<div class="st-campo-testa">
					<label for="st-nome">Nome <span class="st-obbligatorio">(obbligatorio)</span></label>
					<?php self::pulsante_aiuto( 'nome', 'nome' ); ?>
				</div>
				<?php self::balloon( 'nome' ); ?>
				<input type="text" id="st-nome" name="st_nome" required maxlength="150" value="<?php echo esc_attr( $val( 'st_nome' ) ); ?>"<?php $descritto( 'st_nome', 'st-aiuto-nome' ); ?>>
				<?php $errore( 'st_nome' ); ?>
			</div>

			<div class="st-campo">
				<div class="st-campo-testa">
					<label for="st-settore">Settore <span class="st-obbligatorio">(obbligatorio)</span></label>
					<?php self::pulsante_aiuto( 'settore', 'settore' ); ?>
				</div>
				<?php self::balloon( 'settore' ); ?>
				<select id="st-settore" name="st_settore" required<?php $descritto( 'st_settore', 'st-aiuto-settore' ); ?>>
					<option value="">Scegli…</option>
					<?php foreach ( is_array( $settori ) ? $settori : array() as $t ) : ?>
						<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( $val( 'st_settore' ), (string) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php $errore( 'st_settore' ); ?>
			</div>

			<div class="st-campo">
				<div class="st-campo-testa">
					<label for="st-luogo">Luogo (città, regione o paese)</label>
					<?php self::pulsante_aiuto( 'luogo', 'luogo' ); ?>
				</div>
				<?php self::balloon( 'luogo' ); ?>
				<input type="text" id="st-luogo" name="st_luogo" maxlength="100" autocomplete="off" value="<?php echo esc_attr( $val( 'st_luogo' ) ); ?>"<?php $descritto( 'st_luogo', 'st-aiuto-luogo' ); ?>>
				<?php $errore( 'st_luogo' ); ?>
			</div>

			<div class="st-campo">
				<div class="st-campo-testa">
					<label for="st-sintesi">Sintesi <span class="st-obbligatorio">(obbligatoria, da 30 a <?php echo esc_html( Schede::MAX_SINTESI ); ?> caratteri)</span></label>
					<?php self::pulsante_aiuto( 'sintesi', 'sintesi' ); ?>
				</div>
				<?php self::balloon( 'sintesi' ); ?>
				<textarea id="st-sintesi" name="st_sintesi" rows="5" required maxlength="<?php echo esc_attr( Schede::MAX_SINTESI ); ?>" data-st-contatore="st-sintesi-contatore"<?php $descritto( 'st_sintesi', 'st-aiuto-sintesi st-sintesi-contatore' ); ?>><?php echo esc_textarea( $val( 'st_sintesi' ) ); ?></textarea>
				<span id="st-sintesi-contatore" class="st-nota" aria-live="polite"></span>
				<?php $errore( 'st_sintesi' ); ?>
			</div>

			<fieldset<?php $descritto( 'st_fonte_url', 'st-aiuto-fonti', true ); ?>>
				<legend class="st-legenda-con-aiuto">Fonti verificabili <span class="st-obbligatorio">(almeno una)</span></legend>
				<div class="st-campo-testa st-campo-testa--gruppo"><?php self::pulsante_aiuto( 'fonti', 'fonti' ); ?></div>
				<?php self::balloon( 'fonti' ); ?>
				<?php $errore( 'st_fonte_url' ); ?>
				<?php for ( $i = 0; $i < 3; $i++ ) : ?>
					<div class="st-fonte">
						<p>
							<label for="st-fonte-url-<?php echo esc_attr( $i ); ?>">Indirizzo della fonte <?php echo esc_html( $i + 1 ); ?></label>
							<input type="url" id="st-fonte-url-<?php echo esc_attr( $i ); ?>" name="st_fonte_url[]" inputmode="url" placeholder="https://" value="<?php echo esc_attr( isset( $fonti_u[ $i ] ) ? $fonti_u[ $i ] : '' ); ?>" <?php echo 0 === $i ? 'required' : ''; ?>>
						</p>
						<p>
							<label for="st-fonte-desc-<?php echo esc_attr( $i ); ?>">Che cosa conferma la fonte <?php echo esc_html( $i + 1 ); ?></label>
							<input type="text" id="st-fonte-desc-<?php echo esc_attr( $i ); ?>" name="st_fonte_descrizione[]" maxlength="150" value="<?php echo esc_attr( isset( $fonti_d[ $i ] ) ? $fonti_d[ $i ] : '' ); ?>">
						</p>
					</div>
				<?php endfor; ?>
			</fieldset>

			<div class="st-campo">
				<p class="st-spunta">
					<input type="checkbox" id="st-figura-pubblica" name="st_figura_pubblica" value="1" <?php checked( $val( 'st_figura_pubblica' ), '1' ); ?><?php $descritto( 'st_figura_pubblica', 'st-aiuto-figura' ); ?>>
					<label for="st-figura-pubblica">Se è una persona: confermo che ha un ruolo pubblico (per esempio amministratore di un'azienda, professionista noto, figura istituzionale) e che la scheda riguarda solo quel ruolo.</label>
					<?php self::pulsante_aiuto( 'figura', 'ruolo pubblico' ); ?>
				</p>
				<?php self::balloon( 'figura' ); ?>
				<?php $errore( 'st_figura_pubblica' ); ?>
			</div>

			<div class="st-campo">
				<p class="st-spunta">
					<input type="checkbox" id="st-nessun-conflitto" name="st_nessun_conflitto" value="1" required <?php checked( $val( 'st_nessun_conflitto' ), '1' ); ?><?php $descritto( 'st_nessun_conflitto', 'st-aiuto-conflitto' ); ?>>
					<label for="st-nessun-conflitto">Dichiaro di non essere il soggetto della scheda né un suo collaboratore. <span class="st-obbligatorio">(obbligatorio)</span></label>
					<?php self::pulsante_aiuto( 'conflitto', 'conflitto di interesse' ); ?>
				</p>
				<?php self::balloon( 'conflitto' ); ?>
				<?php $errore( 'st_nessun_conflitto' ); ?>
			</div>

			<div class="st-campo">
				<div class="st-campo-testa">
					<button type="submit" class="st-pulsante" aria-describedby="st-aiuto-invio">Invia la scheda alla redazione</button>
					<?php self::pulsante_aiuto( 'invio', 'che cosa succede dopo l\'invio' ); ?>
				</div>
				<?php self::balloon( 'invio' ); ?>
			</div>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------- Shortcode: metodologia ---------- */

	public static function shortcode_metodologia() {
		self::accoda();
		$i = Impostazioni::tutte();
		$f = static function ( $n ) {
			return number_format_i18n( (float) $n, 1 );
		};
		ob_start();
		?>
		<div class="st-blocco st-metodologia">
			<h2>Chi può recensire</h2>
			<ul>
				<li>Chi ha un account verificato via email da almeno <?php echo esc_html( $i['eta_minima_giorni'] ); ?> giorni e ha visitato il sito in almeno <?php echo esc_html( $i['giorni_attivita_minimi'] ); ?> giorni diversi.</li>
				<li>Ogni persona recensisce una scheda una sola volta e può modificare il voto entro <?php echo esc_html( $i['giorni_modifica'] ); ?> giorni.</li>
				<li>Al massimo <?php echo esc_html( $i['limite_giornaliero'] ); ?> recensioni nuove ogni 24 ore.</li>
				<li>Il soggetto della scheda, i suoi collaboratori e chi ha creato la scheda non possono recensirla. Le aziende possono avere un referente verificato che risponde, ma non vota.</li>
				<li>Le recensioni che arrivano da uno stesso indirizzo di rete per la stessa scheda vengono controllate dalla redazione prima di essere conteggiate. Non conserviamo l'indirizzo, ma solo un'impronta cifrata che non permette di risalire a esso.</li>
			</ul>

			<h2>Come si calcola il punteggio</h2>
			<p>Ogni recensione dà un voto da 1 a 5 a quattro criteri: <?php echo esc_html( implode( ', ', array_map( 'strtolower', Punteggi::CRITERI ) ) ); ?>. La media dei quattro voti è il voto della recensione.</p>
			<p>Il punteggio della scheda è una <strong>media bayesiana</strong>: alla media delle recensioni aggiungiamo <?php echo esc_html( $f( $i['peso_bayes'] ) ); ?> "recensioni virtuali" con il voto medio di tutto il sito (ora <?php echo esc_html( number_format_i18n( Voti::media_generale(), 2 ) ); ?>). Così tre recensioni da 5 non bastano a fare un'eccellenza: il punteggio cresce solo quando i giudizi positivi sono tanti.</p>
			<p class="st-formula"><code>punteggio = (C × m + n × media) ÷ (C + n)</code><br>
				dove C = <?php echo esc_html( $f( $i['peso_bayes'] ) ); ?>, m = media del sito, n = numero di recensioni valide della scheda.</p>
			<p>I punteggi si ricalcolano a ogni recensione e, per intero, ogni notte.</p>

			<h2>Il passaggio a eccellenze.org</h2>
			<ul>
				<li>Una scheda diventa candidata quando ha almeno <?php echo esc_html( number_format_i18n( $i['min_recensori'] ) ); ?> recensioni valide da recensori unici e un punteggio di almeno <?php echo esc_html( $f( $i['soglia_ingresso'] ) ); ?>.</li>
				<li>La redazione verifica a mano ogni candidatura prima del passaggio; la decisione si può contestare con il modulo di segnalazione della scheda.</li>
				<li>La scheda viene copiata su <a href="<?php echo esc_url( $i['url_eccellenze'] ); ?>">eccellenze.org</a> con un link a questa versione completa, che resta qui.</li>
				<li>Esce da eccellenze.org solo se il punteggio scende sotto <?php echo esc_html( $f( $i['soglia_uscita'] ) ); ?> e una nuova revisione lo conferma. La distanza fra le due soglie evita entrate e uscite continue.</li>
			</ul>

			<h2>Moderazione e segnalazioni</h2>
			<p>Le schede nuove passano da una revisione prima della pubblicazione e devono citare fonti verificabili. Chiunque può segnalare errori, fonti mancanti o conflitti di interesse; ogni segnalazione riceve una risposta motivata. Il soggetto di una scheda può chiedere la rettifica o la rimozione dei dati con lo stesso modulo.</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ---------- Shortcode: classifica ---------- */

	public static function shortcode_classifica( $atts ) {
		self::accoda();
		$a = shortcode_atts(
			array(
				'settore' => '',
				'numero'  => 20,
				'minimo'  => 10,
				'titolo'  => 'Classifica',
			),
			$atts,
			'st_classifica'
		);
		$args = array(
			'post_type'      => Schede::TIPO,
			'post_status'    => 'publish',
			'posts_per_page' => min( 100, max( 1, (int) $a['numero'] ) ),
			'meta_key'       => '_st_media_bayes',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_st_n_recensori',
					'value'   => max( 0, (int) $a['minimo'] ),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		);
		if ( $a['settore'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => Schede::SETTORE,
					'field'    => 'slug',
					'terms'    => sanitize_title( $a['settore'] ),
				),
			);
		}
		$schede = get_posts( $args );
		if ( ! $schede ) {
			return '<p>Non ci sono ancora schede con abbastanza recensioni per la classifica.</p>';
		}
		ob_start();
		?>
		<div class="st-blocco st-scorrevole" tabindex="0" role="region" aria-label="<?php echo esc_attr( $a['titolo'] ); ?>">
			<table class="st-tabella">
				<caption><?php echo esc_html( $a['titolo'] ); ?> — solo schede con almeno <?php echo esc_html( (int) $a['minimo'] ); ?> recensori</caption>
				<thead><tr><th scope="col">Posizione</th><th scope="col">Nome</th><th scope="col">Punteggio</th><th scope="col">Recensori</th></tr></thead>
				<tbody>
					<?php foreach ( $schede as $n => $p ) : ?>
						<?php $agg = Schede::aggregato( $p->ID ); ?>
						<tr>
							<td><?php echo esc_html( $n + 1 ); ?></td>
							<th scope="row"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a><?php echo in_array( Eccellenze::stato( $p->ID ), array( 'pubblicata', 'revisione_uscita' ), true ) ? ' <span class="st-etichetta">★ eccellenze.org</span>' : ''; ?></th>
							<td><?php echo esc_html( self::numero( $agg['media_bayes'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $agg['n_recensori'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}
