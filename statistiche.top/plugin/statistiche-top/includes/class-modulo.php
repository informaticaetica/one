<?php
/**
 * Il modulo semplice per recensire, anche senza account.
 *
 * Campi: tipo (persona, azienda, prodotto) e nome — solo quando non si è già su
 * una scheda —, categoria libera, stelle da 1 a 5, recensione fino a 2000
 * caratteri, email (obbligatoria), telefono (facoltativo), dettagli facoltativi
 * (rapporto con il soggetto e voti sugli aspetti) e due dichiarazioni.
 *
 * Come funziona:
 * - se esiste già una scheda con quel nome, la recensione va in coda al moderatore
 *   per quella scheda; altrimenti nasce una scheda nuova "in revisione";
 * - chi non è collegato riceve un link per confermare l'email: solo dopo la
 *   conferma la recensione passa al moderatore. Dall'email il sistema crea da solo
 *   un account leggero, che serve a riconoscere la stessa persona in seguito;
 * - chi è collegato salta la conferma; i membri di statistiche.top pubblicano subito;
 * - ogni invio viene notificato alla redazione (email dell'amministratore e
 *   indirizzo delle notifiche nelle impostazioni).
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ST_TEST' ) || defined( 'ABSPATH' ) || exit;

final class Modulo {

	const MAX_TESTO     = 2000;
	const MIN_TESTO     = 30;
	const MAX_CATEGORIA = 60;
	const MAX_ORARI     = 5;

	/** Testi degli aiuti a fumetto (HTML fisso, scritto qui). */
	const AIUTI = array(
		'sito'       => '<p>Facoltativo. Se conosci il sito ufficiale, scrivilo: aiuta la redazione a verificare la scheda e chi legge a trovare il soggetto.</p>',
		'tipo'       => '<p>Che cosa stai recensendo: una <strong>persona</strong> con un ruolo pubblico (un professionista, un artigiano, una figura istituzionale), un\'<strong>azienda</strong> (un negozio, uno studio, un\'impresa, un ente) o un <strong>prodotto</strong>.</p><p>Non si recensiscono privati cittadini.</p>',
		'nome'       => '<p>Il nome come lo trovi sull\'insegna, sul sito ufficiale o sulla confezione: per esempio «Officina Rossi», «Mario Bianchi, idraulico» o «Caffè Moka Express».</p><p>Se la scheda esiste già, la tua recensione verrà aggiunta lì.</p>',
		'categoria'  => '<p>Facoltativo. Scrivi tu la categoria, con parole tue: non c\'è una lista da cui scegliere (per esempio «Ristorante di pesce» o «Elettrodomestici»).</p><p>Come in un wiki, la redazione poi decide la categoria definitiva.</p>',
		'stelle'     => '<p>La tua valutazione complessiva, da 1 stella (pessima) a 5 stelle (ottima). È il voto che entra nelle statistiche.</p>',
		'recensione' => '<p>Racconta la tua esperienza <strong>con parole tue</strong>, come la racconteresti a un amico: che cosa hai chiesto o comprato, che cosa è successo, com\'è finita.</p><p>Niente testi copiati o scritti da programmi di intelligenza artificiale, niente insulti e niente dati personali di altre persone. Al massimo 2000 caratteri.</p>',
		'email'      => '<p>Obbligatoria. Ti mandiamo un link per confermare che l\'indirizzo è tuo: senza conferma la recensione non viene pubblicata. Poi ti scriviamo quando la scheda o la recensione è pubblicata.</p><p>L\'email non compare mai sul sito.</p>',
		'telefono'   => '<p>Facoltativo. Se lo lasci, la redazione può avvisarti anche per telefono quando la scheda è pubblicata, o chiederti un chiarimento. Non compare sul sito e non lo usiamo per pubblicità.</p>',
		'relazione'  => '<p>Facoltativo, ma rende la recensione più utile: chi legge capisce se scrivi da cliente, da fornitore o da osservatore.</p><p>Se ci lavori, ne sei socio, parente o concorrente, non puoi recensirlo: è un conflitto di interesse.</p>',
		'aspetti'    => '<p>Facoltativo. Un voto da 1 a 5 sugli aspetti che conosci: diventano la «radiografia» della scheda. Lascia «Non so» per quelli che non conosci.</p>',
	);

	public static function avvia() {
		add_action( 'admin_post_st_recensione', array( __CLASS__, 'gestisci' ) );
		add_action( 'admin_post_nopriv_st_recensione', array( __CLASS__, 'gestisci' ) );
		add_action( 'init', array( __CLASS__, 'conferma' ), 20 );
		add_shortcode( 'st_recensisci', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'st_nuova_scheda', array( __CLASS__, 'shortcode' ) );
	}

	/* ---------- Bozza (dati ed errori dopo un invio non riuscito) ---------- */

	private static function bozza() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lettura di dati di visualizzazione.
		$chiave = isset( $_GET['st_bozza'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['st_bozza'] ) ) : '';
		$b      = $chiave ? get_transient( 'st_mod_' . $chiave ) : false;
		return is_array( $b ) ? $b : array( 'dati' => array(), 'errori' => array() );
	}

	/* ---------- Visualizzazione ---------- */

	public static function shortcode( $atts = array() ) {
		$a = shortcode_atts( array( 'scheda' => 0 ), $atts, 'st_recensisci' );
		Vista::accoda_risorse();
		return '<div class="st-blocco" id="st-modulo">' . Vista::avviso( 'st-modulo' ) . self::modulo( (int) $a['scheda'] ) . '</div>';
	}

	/** Il modulo. Con una scheda il tipo e il nome sono già decisi. */
	public static function modulo( $scheda_id = 0 ) {
		$b      = self::bozza();
		$d      = $b['dati'];
		$errori = $b['errori'];
		$f      = new Campi( $d, $errori );
		$utente = wp_get_current_user();
		$tipo   = $scheda_id ? (string) get_post_meta( $scheda_id, '_st_tipo', true ) : ( isset( $d['st_tipo'] ) ? $d['st_tipo'] : 'azienda' );

		ob_start();
		if ( $errori ) {
			?>
			<div class="st-avviso st-avviso--errore" role="alert" tabindex="-1" data-st-focus>
				<p><strong>Attenzione:</strong> <?php echo esc_html( 1 === count( $errori ) ? 'un campo va corretto.' : count( $errori ) . ' campi vanno corretti.' ); ?></p>
				<ul>
					<?php foreach ( $errori as $campo => $testo ) : ?>
						<li><a href="#<?php echo esc_attr( Campi::id( $campo ) ); ?>"><?php echo esc_html( $testo ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
		?>
		<form class="st-modulo st-modulo-semplice" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="st_recensione">
			<input type="hidden" name="st_scheda" value="<?php echo esc_attr( $scheda_id ); ?>">
			<?php wp_nonce_field( 'st_recensione', 'st_nonce', false ); ?>
			<?php Vista::trappola(); ?>
			<p class="st-nota">I campi obbligatori sono indicati. Il pulsante <strong>?</strong> accanto a ogni campo apre una spiegazione.</p>
			<?php
			if ( ! $scheda_id ) {
				$f->radio( 'st_tipo', 'Che cosa vuoi recensire?', array( 'persona' => 'Una persona', 'azienda' => 'Un\'azienda', 'prodotto' => 'Un prodotto' ), 'tipo', true, true );
				$f->testo( 'st_nome', 'Nome della persona, dell\'azienda o del prodotto', 'nome', true, array( 'maxlength' => 150, 'list' => 'st-schede-esistenti', 'autocomplete' => 'off' ) );
				echo '<datalist id="st-schede-esistenti">';
				foreach ( get_posts( array( 'post_type' => Schede::TIPO, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ) ) as $id ) {
					printf( '<option value="%s"></option>', esc_attr( get_the_title( $id ) ) );
				}
				echo '</datalist>';
				$f->testo( 'st_sito', 'Sito ufficiale (se lo conosci)', 'sito', false, array( 'type' => 'url', 'inputmode' => 'url', 'placeholder' => 'https://', 'maxlength' => 200 ) );
				$f->testo( 'st_categoria', 'Categoria (scrivila come vuoi, facoltativa)', 'categoria', false, array( 'maxlength' => self::MAX_CATEGORIA, 'placeholder' => 'es. Ristorante di pesce' ) );
			}
			echo '<fieldset class="st-parliamo"><legend>Parliamo di…</legend>';
			$f->stelle();
			$f->area( 'st_recensione', sprintf( 'Quello che voglio dire è… (da %d a %d caratteri)', self::MIN_TESTO, self::MAX_TESTO ), 'recensione', true, self::MAX_TESTO, 7, true );
			echo '</fieldset>';
			if ( $utente->exists() ) {
				printf( '<p class="st-nota">Invii la recensione come <strong>%s</strong>: non serve confermare l\'email.</p>', esc_html( $utente->display_name ) );
			} else {
				$f->testo( 'st_email', 'Email', 'email', true, array( 'type' => 'email', 'autocomplete' => 'email', 'maxlength' => 190 ) );
			}
			$f->testo( 'st_telefono', 'Telefono, per essere avvisato quando la scheda sarà pubblicata', 'telefono', false, array( 'type' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 20 ) );
			?>
			<details class="st-dettagli st-dettagli-facoltativi"<?php echo isset( $errori['st_relazione'] ) || isset( $errori['st_aspetti'] ) ? ' open' : ''; ?>>
				<summary>Vuoi aggiungere qualche dettaglio? (facoltativo)</summary>
				<?php
				$relazioni = array( '' => 'Preferisco non dirlo' );
				foreach ( Voti::RELAZIONI as $k => $r ) {
					$relazioni[ $k ] = $r[0] . ( $r[1] ? ' (conflitto di interesse: non puoi recensire)' : '' );
				}
				$f->radio( 'st_relazione', 'Che rapporto hai avuto?', $relazioni, 'relazione', false );
				if ( $scheda_id ) {
					$f->aspetti( Punteggi::aspetti( $tipo ), 0 );
				} else {
					echo '<p class="st-nota">Dopo la pubblicazione della scheda potrai anche votare i singoli aspetti.</p>';
				}
				?>
			</details>
			<fieldset>
				<legend>Dichiarazioni <span class="st-obbligatorio">(obbligatorie)</span></legend>
				<?php
				$f->spunta( 'st_veridicita', 'Racconto un\'esperienza reale e personale, con parole mie, e non ho legami di lavoro, parentela o concorrenza con chi recensisco.', '', true );
				$f->spunta( 'st_privacy', 'Ho letto l\'informativa sulla privacy.', '', true );
				?>
				<p class="st-nota"><a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy/' ) ); ?>">Informativa sulla privacy</a> · <a href="<?php echo esc_url( Vista::url_metodologia() ); ?>">Come funzionano recensioni e moderazione</a></p>
			</fieldset>
			<p><button type="submit" class="st-pulsante">Invia la recensione</button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ---------- Validazione ---------- */

	/**
	 * Controlla i dati. Restituisce gli errori indicizzati per nome del campo.
	 *
	 * @param bool $con_scheda  true se si recensisce una scheda già esistente (tipo e nome non servono).
	 * @param bool $con_account true se chi scrive è collegato (l'email non serve).
	 */
	public static function valida( array $d, $con_scheda, $con_account, $tipo ) {
		$e = array();
		$g = static function ( $k ) use ( $d ) {
			return isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) ? trim( (string) $d[ $k ] ) : '';
		};
		$l = static function ( $s ) {
			return mb_strlen( $s );
		};
		if ( '' !== $g( 'st_sito' ) && ! wp_http_validate_url( esc_url_raw( $g( 'st_sito' ), array( 'http', 'https' ) ) ) ) {
			$e['st_sito'] = 'L\'indirizzo del sito deve iniziare con https:// o http://.';
		}
		if ( ! $con_scheda ) {
			if ( ! isset( Schede::TIPI_SOGGETTO[ $g( 'st_tipo' ) ] ) ) {
				$e['st_tipo'] = 'Scegli se recensisci una persona, un\'azienda o un prodotto.';
			}
			if ( $l( $g( 'st_nome' ) ) < 2 || $l( $g( 'st_nome' ) ) > 150 ) {
				$e['st_nome'] = 'Scrivi il nome (da 2 a 150 caratteri).';
			}
			if ( $l( $g( 'st_categoria' ) ) > self::MAX_CATEGORIA ) {
				$e['st_categoria'] = sprintf( 'La categoria può avere al massimo %d caratteri.', self::MAX_CATEGORIA );
			}
		}
		if ( null === Punteggi::normalizza_stelle( $g( 'st_stelle' ) ) ) {
			$e['st_stelle'] = 'Scegli da 1 a 5 stelle.';
		}
		$n = $l( $g( 'st_recensione' ) );
		if ( $n < self::MIN_TESTO || $n > self::MAX_TESTO ) {
			$e['st_recensione'] = sprintf( 'La recensione deve avere da %d a %d caratteri (ora: %d).', self::MIN_TESTO, self::MAX_TESTO, $n );
		}
		if ( ! $con_account && ! is_email( $g( 'st_email' ) ) ) {
			$e['st_email'] = 'Scrivi un indirizzo email valido: serve per confermare la recensione.';
		}
		if ( '' !== $g( 'st_telefono' ) && ! preg_match( '/^\+?[0-9 ().\/-]{6,20}$/', $g( 'st_telefono' ) ) ) {
			$e['st_telefono'] = 'Il telefono può contenere solo numeri, spazi e il segno + iniziale.';
		}
		$rel = $g( 'st_relazione' );
		if ( '' !== $rel && ( ! isset( Voti::RELAZIONI[ $rel ] ) || Voti::RELAZIONI[ $rel ][1] ) ) {
			$e['st_relazione'] = 'Con questo rapporto c\'è un conflitto di interesse: non puoi recensire. Puoi comunque proporre una correzione dalla scheda.';
		}
		if ( isset( $d['st_aspetto'] ) && null === Punteggi::normalizza_aspetti( (array) $d['st_aspetto'], $tipo ) ) {
			$e['st_aspetti'] = 'Controlla i voti sugli aspetti: vanno da 1 a 5, oppure «Non so».';
		}
		if ( '1' !== $g( 'st_veridicita' ) ) {
			$e['st_veridicita'] = 'Conferma che racconti un\'esperienza reale e personale.';
		}
		if ( '1' !== $g( 'st_privacy' ) ) {
			$e['st_privacy'] = 'Conferma di aver letto l\'informativa sulla privacy.';
		}
		return $e;
	}

	/** Titolo automatico: la prima frase della recensione, accorciata. */
	public static function titolo( $testo ) {
		$frase = preg_split( '/(?<=[.!?])\s/u', trim( $testo ), 2 );
		$frase = trim( (string) $frase[0], " \t\n\r.!?" );
		return mb_strlen( $frase ) > 80 ? rtrim( mb_substr( $frase, 0, 77 ) ) . '…' : $frase;
	}

	/* ---------- Invio ---------- */

	private static function torna( $base, array $dati, array $errori ) {
		$chiave = wp_generate_password( 16, false );
		set_transient( 'st_mod_' . $chiave, array( 'dati' => $dati, 'errori' => $errori ), 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( array( 'st_bozza' => $chiave ), remove_query_arg( array( 'st_bozza', 'st_esito', 'st_msg', 'st_dove' ), $base ) ) . '#st-modulo' );
		exit;
	}

	public static function gestisci() {
		$in        = wp_unslash( $_POST );
		$preset    = isset( $in['st_scheda'] ) ? absint( $in['st_scheda'] ) : 0;
		$preset    = ( $preset && Schede::TIPO === get_post_type( $preset ) && 'publish' === get_post_status( $preset ) ) ? $preset : 0;
		$base      = $preset ? get_permalink( $preset ) : Vista::url_ritorno();
		$utente_id = get_current_user_id();

		if ( ! isset( $in['st_nonce'] ) || ! wp_verify_nonce( sanitize_key( $in['st_nonce'] ), 'st_recensione' ) || ! empty( $in['st_sito_web'] ) ) {
			Vista::reindirizza( $base, 'errore', 'richiesta_non_valida', 'st-modulo' );
		}
		$chiave_ip = 'st_inv_' . substr( Voti::impronta_ip(), 0, 32 );
		$invii     = (int) get_transient( $chiave_ip );
		if ( ! current_user_can( 'st_aggiungi' ) && $invii >= self::MAX_ORARI ) {
			Vista::reindirizza( $base, 'errore', 'troppi_invii', 'st-modulo' );
		}

		$d = array();
		foreach ( array( 'st_tipo', 'st_nome', 'st_sito', 'st_categoria', 'st_stelle', 'st_email', 'st_telefono', 'st_relazione', 'st_veridicita', 'st_privacy' ) as $k ) {
			$d[ $k ] = isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		$d['st_tipo']       = sanitize_key( $d['st_tipo'] );
		$d['st_relazione']  = sanitize_key( $d['st_relazione'] );
		$d['st_recensione'] = isset( $in['st_recensione'] ) && is_scalar( $in['st_recensione'] ) ? sanitize_textarea_field( $in['st_recensione'] ) : '';
		if ( $preset && isset( $in['st_aspetto'] ) && is_array( $in['st_aspetto'] ) ) {
			$d['st_aspetto'] = array_map( 'sanitize_text_field', array_filter( $in['st_aspetto'], 'is_scalar' ) );
		}
		$tipo   = $preset ? (string) get_post_meta( $preset, '_st_tipo', true ) : $d['st_tipo'];
		$errori = self::valida( $d, (bool) $preset, (bool) $utente_id, $tipo );
		if ( $errori ) {
			self::torna( $base, $d, $errori );
		}

		// Chi scrive: l'account collegato, oppure quello legato all'email (creato se non esiste).
		$anonimo = ! $utente_id;
		if ( $anonimo ) {
			$utente_id = self::utente_da_email( $d['st_email'] );
			if ( ! $utente_id ) {
				Vista::reindirizza( $base, 'errore', 'salvataggio', 'st-modulo' );
			}
		}
		$membro = current_user_can( 'st_aggiungi' );

		// La scheda: quella della pagina, quella con lo stesso nome, oppure una nuova.
		$scheda_id = $preset ? $preset : Schede::cerca_doppione( $d['st_nome'] );
		$nuova     = ! $scheda_id;
		if ( $nuova ) {
			$scheda_id = self::crea_scheda( $d, $utente_id, $membro );
			if ( ! $scheda_id ) {
				Vista::reindirizza( $base, 'errore', 'salvataggio', 'st-modulo' );
			}
		} elseif ( ! $preset && '' !== $d['st_categoria'] && ! wp_get_post_terms( $scheda_id, Schede::SETTORE, array( 'fields' => 'ids' ) ) && ! get_post_meta( $scheda_id, '_st_categoria_suggerita', true ) ) {
			update_post_meta( $scheda_id, '_st_categoria_suggerita', $d['st_categoria'] );
		}

		// Regole: legami con il soggetto, recensioni annullate, limite giornaliero.
		$legami = Schede::legami_utente( $scheda_id, $utente_id );
		if ( $legami['e_referente'] || $legami['conflitto_dichiarato'] ) {
			Vista::reindirizza( $base, 'errore', 'conflitto', 'st-modulo' );
		}
		$esistente = Voti::recensione_utente( $scheda_id, $utente_id );
		if ( $esistente && 'annullato' === $esistente['stato'] ) {
			Vista::reindirizza( $base, 'errore', 'recensione_annullata', 'st-modulo' );
		}
		if ( ! $membro && ! $esistente && self::recensioni_24h( $utente_id ) >= (int) Impostazioni::valore( 'limite_giornaliero' ) ) {
			Vista::reindirizza( $base, 'errore', 'limite_giornaliero', 'st-modulo' );
		}

		$token = '';
		if ( $membro ) {
			$stato = 'valido';
		} elseif ( $anonimo ) {
			$stato = 'conferma';
			$token = bin2hex( random_bytes( 16 ) );
		} else {
			$stato = 'moderazione';
		}
		$aspetti = isset( $d['st_aspetto'] ) ? (array) Punteggi::normalizza_aspetti( $d['st_aspetto'], $tipo ) : array();
		$dati    = array(
			'stelle'     => Punteggi::normalizza_stelle( $d['st_stelle'] ),
			'titolo'     => self::titolo( $d['st_recensione'] ),
			'aspetti'    => $aspetti,
			'relazione'  => $d['st_relazione'],
			'esperienza' => '',
			'frequenza'  => '',
			'canale'     => '',
			'consiglia'  => null,
			'tornerebbe' => null,
			'problema'   => '',
			'pro'        => '',
			'contro'     => '',
			'commento'   => $d['st_recensione'],
		);
		$salvato = Voti::salva( $scheda_id, $utente_id, $dati, $stato, $nuova, array( 'telefono' => $d['st_telefono'], 'token' => $token ), true );
		if ( false === $salvato ) {
			Vista::reindirizza( $base, 'errore', 'salvataggio', 'st-modulo' );
		}
		set_transient( $chiave_ip, $invii + 1, HOUR_IN_SECONDS );

		if ( $token ) {
			self::email_conferma( $utente_id, $token, get_the_title( $scheda_id ) );
		}
		Notifiche::redazione_nuovo_invio( $scheda_id, $nuova, $utente_id, $d, $stato );

		$esito = $membro ? 'pubblicata_subito' : ( $token ? 'controlla_email' : ( $nuova ? 'scheda_in_revisione' : 'in_moderazione' ) );
		Vista::reindirizza( $membro ? get_permalink( $scheda_id ) : $base, 'ok', $esito, 'st-modulo' );
	}

	private static function recensioni_24h( $utente_id ) {
		global $wpdb;
		$t = Installazione::tabella_voti();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE utente_id = %d AND creato >= %s", $utente_id, wp_date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - DAY_IN_SECONDS ) ) );
	}

	/**
	 * L'account legato a un'email. Se non esiste, lo crea (ruolo Sottoscrittore,
	 * nessuna email di WordPress: la conferma la mandiamo noi). Il nome
	 * visualizzato è neutro: l'email non compare mai sul sito.
	 */
	public static function utente_da_email( $email ) {
		$email  = sanitize_email( $email );
		$utente = get_user_by( 'email', $email );
		if ( $utente ) {
			return (int) $utente->ID;
		}
		$login = 'recensore' . strtolower( wp_generate_password( 8, false ) );
		$id    = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'role'         => get_option( 'default_role', 'subscriber' ),
				'display_name' => 'Recensore',
			)
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		wp_update_user( array( 'ID' => $id, 'display_name' => 'Recensore ' . $id, 'nickname' => 'Recensore ' . $id ) );
		update_user_meta( $id, 'st_creato_dal_modulo', 1 );
		return (int) $id;
	}

	/** Crea una scheda "in revisione" (pubblicata subito se la crea un membro). */
	private static function crea_scheda( array $d, $utente_id, $pubblica ) {
		$id = wp_insert_post(
			array(
				'post_type'    => Schede::TIPO,
				'post_status'  => $pubblica ? 'publish' : 'pending',
				'post_title'   => $d['st_nome'],
				'post_content' => '',
				'post_author'  => $utente_id,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_st_tipo', $d['st_tipo'] );
		update_post_meta( $id, '_st_sito', '' !== $d['st_sito'] ? esc_url_raw( $d['st_sito'], array( 'http', 'https' ) ) : '' );
		Schede::salva_aggregato( $id, Punteggi::aggrega( array(), Voti::media_generale(), (float) Impostazioni::valore( 'peso_bayes' ) ) );
		$categoria = trim( $d['st_categoria'] );
		if ( '' !== $categoria ) {
			$termine = get_term_by( 'name', $categoria, Schede::SETTORE );
			if ( $termine ) {
				wp_set_object_terms( $id, array( (int) $termine->term_id ), Schede::SETTORE );
			} else {
				update_post_meta( $id, '_st_categoria_suggerita', $categoria );
			}
		}
		Registro::scrivi( $pubblica ? 'scheda_membro' : 'proposta_scheda', $id, 0, $utente_id );
		Schede::aggiungi_storico( $id, $pubblica ? 'Scheda aggiunta da un membro di statistiche.top.' : 'Scheda proposta insieme a una recensione, in attesa della redazione.' );
		return (int) $id;
	}

	private static function email_conferma( $utente_id, $token, $titolo ) {
		$u = get_userdata( $utente_id );
		if ( ! $u ) {
			return;
		}
		$sito = wp_parse_url( home_url(), PHP_URL_HOST );
		wp_mail(
			$u->user_email,
			'[' . $sito . '] Conferma la tua recensione',
			"Ciao,\n\nhai scritto una recensione su «" . $titolo . "». Per confermare che l'indirizzo email è tuo, apri questo link:\n\n"
			. add_query_arg( 'st_conferma', $token, home_url( '/' ) )
			. "\n\nIl link vale " . (int) Impostazioni::valore( 'giorni_conferma' ) . " giorni. Dopo la conferma la recensione passa alla redazione; ti scriveremo quando sarà pubblicata.\n\nSe non sei stato tu, ignora questa email: senza conferma la recensione viene cancellata.\n\n— La redazione di " . $sito
		);
	}

	/** Il link di conferma: la recensione passa al moderatore. */
	public static function conferma() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- il token stesso è il segreto.
		$token = isset( $_GET['st_conferma'] ) ? strtolower( preg_replace( '/[^a-f0-9]/i', '', wp_unslash( $_GET['st_conferma'] ) ) ) : '';
		if ( 32 !== strlen( $token ) ) {
			return;
		}
		global $wpdb;
		$t    = Installazione::tabella_voti();
		$voto = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE token = %s AND stato = 'conferma'", $token ), ARRAY_A );
		$home = remove_query_arg( 'st_conferma', home_url( '/' ) );
		if ( ! $voto ) {
			Vista::reindirizza( $home, 'errore', 'conferma_non_valida', 'st-modulo' );
		}
		$scadenza = strtotime( $voto['creato'] ) + (int) Impostazioni::valore( 'giorni_conferma' ) * DAY_IN_SECONDS;
		if ( current_time( 'timestamp' ) > $scadenza ) {
			Vista::reindirizza( $home, 'errore', 'conferma_scaduta', 'st-modulo' );
		}
		$wpdb->update( $t, array( 'stato' => 'moderazione', 'token' => '' ), array( 'id' => (int) $voto['id'] ), array( '%s', '%s' ), array( '%d' ) );
		update_user_meta( (int) $voto['utente_id'], 'st_email_confermata', current_time( 'mysql' ) );
		Notifiche::redazione_conferma( (int) $voto['scheda_id'], (int) $voto['id'] );
		$scheda = (int) $voto['scheda_id'];
		Vista::reindirizza( 'publish' === get_post_status( $scheda ) ? get_permalink( $scheda ) : $home, 'ok', 'email_confermata', 'st-modulo' );
	}

	/** Cancella le recensioni mai confermate dopo il periodo di validità del link, e le schede nate solo da loro. */
	public static function pulisci_non_confermate() {
		global $wpdb;
		$t      = Installazione::tabella_voti();
		$limite = wp_date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - (int) Impostazioni::valore( 'giorni_conferma' ) * DAY_IN_SECONDS );
		$righe  = $wpdb->get_results( $wpdb->prepare( "SELECT id, scheda_id FROM $t WHERE stato = 'conferma' AND creato < %s", $limite ), ARRAY_A );
		foreach ( $righe as $r ) {
			$wpdb->delete( $t, array( 'id' => (int) $r['id'] ), array( '%d' ) );
			$scheda = (int) $r['scheda_id'];
			$altre  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE scheda_id = %d", $scheda ) );
			if ( ! $altre && 'pending' === get_post_status( $scheda ) ) {
				wp_trash_post( $scheda );
			}
		}
		return count( $righe );
	}
}
