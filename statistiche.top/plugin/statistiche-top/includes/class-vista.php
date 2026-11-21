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
		add_shortcode( 'st_metodologia', array( __CLASS__, 'shortcode_metodologia' ) );
		add_shortcode( 'st_classifica', array( __CLASS__, 'shortcode_classifica' ) );
	}

	public static function registra_risorse() {
		wp_register_style( 'statistiche-top', plugins_url( 'assets/statistiche-top.css', FILE ), array(), VERSIONE );
		wp_register_script( 'statistiche-top', plugins_url( 'assets/statistiche-top.js', FILE ), array(), VERSIONE, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		$post    = get_post();
		$codici  = array( 'st_recensisci', 'st_nuova_scheda', 'st_crea_scheda', 'st_metodologia', 'st_classifica' );
		$con_codice = $post && is_singular() && array_filter(
			$codici,
			static function ( $c ) use ( $post ) {
				return has_shortcode( $post->post_content, $c );
			}
		);
		if ( $con_codice || is_singular( Schede::TIPO ) || is_post_type_archive( Schede::TIPO ) || is_tax( Schede::SETTORE ) ) {
			self::accoda_risorse();
		}
	}

	public static function accoda_risorse() {
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
			'scheda_inviata'          => 'Grazie! La scheda e la tua recensione sono arrivate. La scheda sarà pubblicata dopo la revisione della redazione; la tua recensione conterà quando la scheda sarà pubblicata e il tuo account avrà almeno 7 giorni.',
			'scheda_aggiunta'         => 'La scheda è pubblicata. La categoria definitiva la decide la redazione.',
			'scheda_aggiunta_recensita' => 'La scheda è pubblicata con la tua recensione. La recensione conterà quando il tuo account avrà almeno 7 giorni.',
			'recensione_in_attesa'    => 'La recensione è salvata e conterà quando la scheda sarà pubblicata e il tuo account avrà almeno 7 giorni.',
			'utile_propria'           => 'Non puoi segnare come utile una tua recensione.',
			'controlla_email'         => 'Grazie! Ti abbiamo mandato un\'email: apri il link per confermare l\'indirizzo. Dopo la conferma la recensione passa alla redazione.',
			'scheda_in_revisione'     => 'Grazie! La scheda e la tua recensione sono arrivate alla redazione: ti scriveremo quando saranno pubblicate.',
			'in_moderazione'          => 'Grazie! La recensione è arrivata alla redazione: ti scriveremo quando sarà pubblicata.',
			'pubblicata_subito'       => 'La recensione è pubblicata.',
			'email_confermata'        => 'Email confermata, grazie! La recensione è passata alla redazione: ti scriveremo quando sarà pubblicata.',
			'conferma_non_valida'     => 'Il link di conferma non è valido o è già stato usato.',
			'conferma_scaduta'        => 'Il link di conferma è scaduto: scrivi di nuovo la recensione.',
			'troppi_invii'            => 'Hai inviato molti moduli nell\'ultima ora. Riprova più tardi.',
			'conflitto'               => 'Risulti legato al soggetto (referente o legame dichiarato): non puoi recensirlo.',
			'limite_giornaliero'      => 'Hai raggiunto il limite di recensioni per oggi. Riprova domani.',
			'richiesta_non_valida'    => 'La richiesta non è valida. Ricarica la pagina e riprova.',
			'richiesta_ricevuta'      => 'Grazie! La richiesta è arrivata alla redazione. Riceverai una ricevuta per email e poi l\'esito.',
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
		return isset( $testi[ $codice ] ) ? $testi[ $codice ] : 'Operazione non consentita.';
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

	/** Sezioni della voce wiki: ancora => titolo, nell'ordine della pagina. */
	private static function sezioni( $id ) {
		$sezioni = array(
			'st-sintesi-titolo'     => 'Sintesi',
			'st-radiografia-titolo' => 'Statistiche di gradimento',
			'st-recensisci-titolo'  => 'Scrivi una recensione',
		);
		if ( Schede::aggregato( $id )['n_recensioni'] ) {
			$sezioni = array_slice( $sezioni, 0, 2, true ) + array( 'st-recensioni-titolo' => 'Le recensioni' ) + array_slice( $sezioni, 2, null, true );
		}
		$sezioni['st-fonti-titolo']   = 'Note e fonti';
		$sezioni['st-storico-titolo'] = 'Cronologia delle modifiche';
		$sezioni['st-segnala-titolo'] = 'Proponi una correzione';
		return $sezioni;
	}

	/**
	 * La pagina della scheda come voce wiki: riquadro "In breve", indice,
	 * sintesi, statistiche, recensioni, fonti come note, cronologia e correzioni.
	 */
	public static function contenuto_scheda( $contenuto ) {
		if ( ! is_singular( Schede::TIPO ) || ! in_the_loop() || ! is_main_query() ) {
			return $contenuto;
		}
		$id  = get_the_ID();
		$d   = Schede::dati( $id );
		$a   = Schede::aggregato( $id );
		$ecc = Eccellenze::stato( $id );
		$termini = wp_get_post_terms( $id, Schede::SETTORE );

		ob_start();
		?>
		<div class="st-blocco st-scheda st-wiki" id="st-scheda">
			<?php echo self::avviso( 'st-scheda' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<aside class="st-infobox" aria-labelledby="st-infobox-titolo">
				<h2 id="st-infobox-titolo" class="st-infobox-titolo">In breve</h2>
				<?php if ( in_array( $ecc, array( 'pubblicata', 'revisione_uscita' ), true ) ) : ?>
					<p class="st-etichetta-eccellenza"><span aria-hidden="true">★</span> <strong>Presente su <a href="<?php echo esc_url( Impostazioni::valore( 'url_eccellenze' ) ); ?>">eccellenze.org</a></strong></p>
				<?php endif; ?>
				<?php if ( $d['dal_soggetto'] ) : ?>
					<p class="st-nota"><strong>Dati forniti dal soggetto</strong> e verificati dalla redazione.</p>
				<?php endif; ?>
				<dl>
					<div><dt>Tipo</dt><dd><?php echo esc_html( isset( Schede::TIPI_SOGGETTO[ $d['tipo'] ] ) ? Schede::TIPI_SOGGETTO[ $d['tipo'] ] : '—' ); ?></dd></div>
					<div><dt>Settore</dt><dd>
						<?php if ( $termini && ! is_wp_error( $termini ) ) : ?>
							<?php echo implode( ', ', array_map( static function ( $t ) { return '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>'; }, $termini ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							in attesa della redazione
						<?php endif; ?>
					</dd></div>
					<?php if ( $d['luogo'] ) : ?>
						<div><dt>Luogo</dt><dd><?php echo esc_html( $d['luogo'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $d['sito'] ) : ?>
						<div><dt>Sito ufficiale</dt><dd><a href="<?php echo esc_url( $d['sito'] ); ?>" rel="nofollow noopener"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $d['sito'] ) ) ); ?></a></dd></div>
					<?php endif; ?>
					<div><dt>Punteggio</dt><dd>
						<?php if ( $a['n_recensioni'] ) : ?>
							<?php echo esc_html( self::numero( $a['media_bayes'] ) ); ?> su 5 <?php echo Radiografia::stelle( $a['media_bayes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							nessuna recensione
						<?php endif; ?>
					</dd></div>
					<div><dt>Recensioni</dt><dd><?php echo esc_html( number_format_i18n( $a['n_recensioni'] ) ); ?></dd></div>
					<?php if ( $d['anno'] ) : ?>
						<div><dt>Fondazione</dt><dd><?php echo esc_html( $d['anno'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $d['piva'] ) : ?>
						<div><dt>Partita IVA</dt><dd><?php echo esc_html( $d['piva'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( $a['consiglia']['n'] ) : ?>
						<div><dt>Lo consiglierebbe</dt><dd><?php echo esc_html( self::numero( $a['consiglia']['promotori'], 0 ) ); ?>%</dd></div>
					<?php endif; ?>
					<div><dt>Ultima modifica</dt><dd><time datetime="<?php echo esc_attr( get_the_modified_date( 'c', $id ) ); ?>"><?php echo esc_html( get_the_modified_date( '', $id ) ); ?></time></dd></div>
				</dl>
			</aside>
			<nav class="st-indice" aria-labelledby="st-indice-titolo">
				<h2 id="st-indice-titolo" class="st-indice-titolo">Indice</h2>
				<ol>
					<?php foreach ( self::sezioni( $id ) as $ancora => $titolo ) : ?>
						<li><a href="#<?php echo esc_attr( $ancora ); ?>"><?php echo esc_html( $titolo ); ?></a></li>
					<?php endforeach; ?>
				</ol>
			</nav>
		</div>
		<?php
		$prima = ob_get_clean();

		ob_start();
		?>
		<div class="st-blocco st-scheda st-wiki">
			<?php echo Radiografia::mostra( $id, $d['tipo'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito con escape. ?>
			<?php echo Radiografia::elenco( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::sezione_recensisci( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<section aria-labelledby="st-fonti-titolo" id="st-fonti">
				<h2 id="st-fonti-titolo">Note e fonti</h2>
				<?php if ( $d['fonti'] ) : ?>
					<ol class="st-fonti">
						<?php foreach ( $d['fonti'] as $f ) : ?>
							<li><a href="<?php echo esc_url( $f['url'] ); ?>" rel="nofollow noopener"><?php echo esc_html( $f['descrizione'] ? $f['descrizione'] : wp_parse_url( $f['url'], PHP_URL_HOST ) ); ?></a> <span class="st-nota">(<?php echo esc_html( wp_parse_url( $f['url'], PHP_URL_HOST ) ); ?>)</span></li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<p>Nessuna fonte indicata. <a href="#st-segnala">Proponi una fonte</a>.</p>
				<?php endif; ?>
				<?php if ( has_post_thumbnail( $id ) && $d['licenza_immagine'] ) : ?>
					<p class="st-nota">Immagine: <?php echo esc_html( $d['licenza_immagine'] ); ?></p>
				<?php endif; ?>
			</section>

			<?php echo self::storico( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo self::modulo_segnalazione( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="st-nota st-wiki-piede">Questa voce è stata modificata l'ultima volta il <?php echo esc_html( get_the_modified_date( '', $id ) ); ?>. I contenuti vengono dalle fonti citate e dalle recensioni degli utenti verificati; la redazione controlla ogni modifica. <a href="<?php echo esc_url( self::url_metodologia() ); ?>">Come funziona</a>.</p>
		</div>
		<?php
		$dopo = ob_get_clean();

		// La sintesi è testo semplice: la mostriamo con i soli a capo.
		return $prima . '<section aria-labelledby="st-sintesi-titolo" class="st-blocco st-wiki"><h2 id="st-sintesi-titolo">Sintesi</h2>' . $contenuto . '</section>' . $dopo;
	}

	private static function numero( $n, $decimali = 2 ) {
		return number_format_i18n( (float) $n, $decimali );
	}

	public static function url_metodologia() {
		$pagina = get_page_by_path( 'metodologia' );
		return $pagina ? get_permalink( $pagina ) : home_url( '/metodologia/' );
	}

	/** La sezione "Scrivi una recensione": il modulo semplice, già legato a questa scheda. */
	private static function sezione_recensisci( $id ) {
		ob_start();
		echo '<section aria-labelledby="st-recensisci-titolo" id="st-recensisci">';
		echo '<h2 id="st-recensisci-titolo">Scrivi una recensione</h2>';
		echo '<div id="st-modulo">' . self::avviso( 'st-modulo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$utente_id = get_current_user_id();
		$esistente = $utente_id ? Voti::recensione_utente( $id, $utente_id ) : null;
		$conflitti = $utente_id ? array_map( 'intval', (array) get_user_meta( $utente_id, 'st_conflitti', true ) ) : array();
		$legami    = $utente_id ? Schede::legami_utente( $id, $utente_id ) : array( 'e_referente' => false );
		if ( $esistente && 'annullato' === $esistente['stato'] ) {
			echo '<p>' . esc_html( self::testo_esito( 'recensione_annullata' ) ) . '</p>';
		} elseif ( $legami['e_referente'] || in_array( $id, $conflitti, true ) ) {
			echo '<p>' . esc_html( self::testo_esito( 'conflitto' ) ) . '</p>';
		} else {
			if ( $esistente ) {
				printf( '<p class="st-nota">Hai già una recensione su questa scheda (%s): se ne invii un\'altra, sostituisce la precedente e torna in moderazione.</p>', esc_html( strtolower( Voti::NOMI_STATI[ $esistente['stato'] ] ?? $esistente['stato'] ) ) );
			}
			echo Modulo::modulo( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		if ( $utente_id && ! in_array( $id, $conflitti, true ) ) {
			?>
			<details class="st-dettagli">
				<summary>Hai un legame con questo soggetto?</summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="st_conflitto">
					<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $id ); ?>">
					<?php wp_nonce_field( 'st_conflitto_' . $id, 'st_nonce', false ); ?>
					<p>Se lavori o hai lavorato con questo soggetto, ne sei socio, parente o concorrente, dichiaralo: non potrai recensirlo e l'eventuale recensione che hai già lasciato non verrà più conteggiata.</p>
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
			<h2 id="st-segnala-titolo">Proponi una correzione</h2>
			<p>Hai notato un errore, una fonte mancante o un conflitto di interesse? Come in un wiki, chiunque può proporre una correzione: la redazione la valuta e risponde con una motivazione.</p>
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
			// L'indice punta sempre a questa sezione: c'è anche quando è vuota.
			return '<section aria-labelledby="st-storico-titolo"><h2 id="st-storico-titolo">Cronologia delle modifiche</h2><p>Nessuna modifica registrata dopo la pubblicazione.</p></section>';
		}
		ob_start();
		?>
		<section aria-labelledby="st-storico-titolo">
			<h2 id="st-storico-titolo">Cronologia delle modifiche</h2>
			<details class="st-dettagli">
				<summary>Mostra la cronologia (<?php echo esc_html( count( $storico ) ); ?> voci)</summary>
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

	/** Pulsante "?" che apre il balloon di aiuto. Nascosto finché lo script non lo attiva. */
	public static function pulsante_aiuto( $chiave, $nome ) {
		printf(
			'<button type="button" class="st-aiuto-pulsante" aria-expanded="false" aria-controls="st-aiuto-%1$s" hidden><span aria-hidden="true">?</span><span class="screen-reader-text">Aiuto: %2$s</span></button>',
			esc_attr( $chiave ),
			esc_html( $nome )
		);
	}

	/** Testo dell'aiuto: visibile senza JavaScript, diventa un balloon con lo script. */
	public static function balloon( $chiave, $html ) {
		printf( '<div class="st-aiuto" id="st-aiuto-%1$s">%2$s</div>', esc_attr( $chiave ), wp_kses_post( $html ) );
	}

	/** Campo trappola contro i programmi automatici, nascosto a persone e lettori di schermo. */
	public static function trappola() {
		echo '<div class="st-trappola" aria-hidden="true"><label for="st-sito-web">Non compilare questo campo</label><input type="text" id="st-sito-web" name="st_sito_web" tabindex="-1" autocomplete="off"></div>';
	}

	/* ---------- Shortcode: metodologia ---------- */

	public static function shortcode_metodologia() {
		self::accoda_risorse();
		$i = Impostazioni::tutte();
		$f = static function ( $n ) {
			return number_format_i18n( (float) $n, 1 );
		};
		ob_start();
		?>
		<div class="st-blocco st-metodologia">
			<h2>Come nasce una scheda</h2>
			<ul>
				<li>Chiunque può recensire una persona con un ruolo pubblico, un'azienda o un prodotto con il modulo in home page, anche senza account: bastano un'email e qualche riga scritta con parole proprie. Se la scheda non esiste ancora, nasce insieme alla recensione.</li>
				<li>Le aziende, i professionisti e i produttori possono proporre la propria scheda con i dati ufficiali dalla pagina «Crea la tua scheda». Su quelle schede compare l'indicazione «Dati forniti dal soggetto»; chi le rappresenta può rispondere, ma non recensirle.</li>
				<li>Ogni scheda passa dalla redazione prima della pubblicazione. La categoria la può suggerire chiunque con parole sue; quella definitiva la decide la redazione, come in un wiki.</li>
			</ul>

			<h2>Come viene controllata una recensione</h2>
			<ul>
				<li><strong>Conferma dell'email:</strong> chi scrive senza account riceve un link e la recensione va avanti solo dopo il clic. Così sappiamo che l'indirizzo esiste ed è di chi scrive.</li>
				<li><strong>Moderazione:</strong> ogni recensione viene letta da una persona della redazione prima della pubblicazione. Togliamo testi offensivi, copiati, scritti da programmi o pubblicitari, e quelli di chi ha un conflitto di interesse.</li>
				<li>Una sola recensione per persona su ogni scheda: una nuova recensione sostituisce la precedente e torna in moderazione. Al massimo <?php echo esc_html( $i['limite_giornaliero'] ); ?> recensioni nuove ogni 24 ore.</li>
				<li>Non può recensire chi ha un conflitto di interesse: il soggetto stesso, i suoi dipendenti ed ex dipendenti, soci, familiari e concorrenti diretti, e i referenti delle aziende.</li>
				<li>Per la sicurezza registriamo l'indirizzo di rete di ogni invio, visibile solo agli amministratori e cancellato dopo <?php echo esc_html( $i['giorni_registro'] ); ?> giorni.</li>
				<li>Il segno <strong>«Utente verificato»</strong> vuol dire che l'email è stata confermata. Non verifichiamo l'esperienza raccontata: per questo la moderazione legge ogni testo, e ogni recensione può dire che rapporto ha avuto chi scrive con il soggetto.</li>
			</ul>

			<h2>La radiografia</h2>
			<p>Ogni recensione contiene:</p>
			<ul>
				<li>il <strong>voto complessivo da 1 a 5 stelle</strong>, che entra nel punteggio;</li>
				<li>il racconto dell'esperienza, con parole proprie, fino a 2000 caratteri;</li>
				<li>se chi scrive lo indica, il rapporto con il soggetto e un voto da 1 a 5 sugli <strong>aspetti</strong>, oppure «Non so» (le risposte «Non so» non contano). Per le aziende: <?php echo esc_html( strtolower( implode( ', ', Punteggi::ASPETTI_AZIENDA ) ) ); ?>. Per le persone con un ruolo pubblico: <?php echo esc_html( strtolower( implode( ', ', Punteggi::ASPETTI_PERSONA ) ) ); ?>. Per i prodotti: <?php echo esc_html( strtolower( implode( ', ', Punteggi::ASPETTI_PRODOTTO ) ) ); ?>.</li>
			</ul>
			<p>Sulla scheda questi dati diventano la distribuzione delle stelle e, quando ci sono abbastanza risposte, la media per aspetto con i punti di forza e i punti deboli.</p>

			<h2>Come si calcola il punteggio</h2>
			<p>Il punteggio della scheda è una <strong>media bayesiana</strong> delle stelle: alla media delle recensioni aggiungiamo <?php echo esc_html( $f( $i['peso_bayes'] ) ); ?> "recensioni virtuali" con il voto medio di tutto il sito (ora <?php echo esc_html( number_format_i18n( Voti::media_generale(), 2 ) ); ?>). Così tre recensioni da 5 non bastano a fare un'eccellenza: il punteggio cresce solo quando i giudizi positivi sono tanti.</p>
			<p class="st-formula"><code>punteggio = (C × m + n × media) ÷ (C + n)</code><br>
				dove C = <?php echo esc_html( $f( $i['peso_bayes'] ) ); ?>, m = media del sito, n = numero di recensioni valide della scheda.</p>
			<p>I punteggi si ricalcolano a ogni recensione e, per intero, ogni notte.</p>

			<h2>Il passaggio a eccellenze.org</h2>
			<ul>
				<li>Una scheda diventa candidata quando ha almeno <?php echo esc_html( number_format_i18n( $i['min_recensori'] ) ); ?> recensioni valide di utenti verificati diversi e una valutazione globale (punteggio ponderato delle stelle) di almeno <?php echo esc_html( $f( $i['soglia_ingresso'] ) ); ?>.</li>
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
		self::accoda_risorse();
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
