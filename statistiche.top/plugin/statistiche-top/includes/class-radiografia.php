<?php
/**
 * La radiografia di una scheda: statistiche di gradimento e recensioni.
 *
 * Ogni dato è scritto anche in numeri e parole: le barre sono solo un aiuto
 * visivo (aria-hidden) e non portano informazioni che il testo non dica già.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

final class Radiografia {

	const PER_PAGINA = 10;

	private static function numero( $n, $decimali = 1 ) {
		return number_format_i18n( (float) $n, $decimali );
	}

	/** Stelle disegnate (nascoste ai lettori di schermo) più il testo. */
	public static function stelle( $valore, $max = 5 ) {
		$piene = (int) round( (float) $valore );
		return sprintf(
			'<span class="st-stelle" aria-hidden="true">%1$s<span class="st-stelle-vuote">%2$s</span></span><span class="screen-reader-text">%3$s</span>',
			esc_html( str_repeat( '★', max( 0, min( $max, $piene ) ) ) ),
			esc_html( str_repeat( '★', max( 0, $max - $piene ) ) ),
			esc_html( sprintf( '%s stelle su %d', self::numero( $valore ), $max ) )
		);
	}

	private static function barra( $percento ) {
		return sprintf( '<span class="st-barra" aria-hidden="true"><span style="width:%s%%"></span></span>', esc_attr( max( 0, min( 100, round( (float) $percento ) ) ) ) );
	}

	/** Filtri attivi letti dall'indirizzo. */
	public static function filtri() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lettura per la visualizzazione.
		$stelle    = isset( $_GET['st_stelle'] ) ? Punteggi::normalizza_stelle( sanitize_text_field( wp_unslash( $_GET['st_stelle'] ) ) ) : null;
		$relazione = isset( $_GET['st_relazione'] ) ? sanitize_key( wp_unslash( $_GET['st_relazione'] ) ) : '';
		$ordine    = isset( $_GET['st_ordine'] ) && 'recenti' === $_GET['st_ordine'] ? 'recenti' : 'utili';
		$pagina    = isset( $_GET['st_pagina'] ) ? max( 1, absint( $_GET['st_pagina'] ) ) : 1;
		// phpcs:enable
		return array(
			'stelle'    => $stelle,
			'relazione' => isset( Voti::RELAZIONI[ $relazione ] ) ? $relazione : '',
			'ordine'    => $ordine,
			'pagina'    => $pagina,
		);
	}

	private static function url_filtro( $id, array $cambia ) {
		$f   = array_merge( self::filtri(), array( 'pagina' => 1 ), $cambia );
		$arg = array_filter(
			array(
				'st_stelle'    => $f['stelle'],
				'st_relazione' => $f['relazione'],
				'st_ordine'    => 'recenti' === $f['ordine'] ? 'recenti' : '',
				'st_pagina'    => $f['pagina'] > 1 ? $f['pagina'] : '',
			)
		);
		return add_query_arg( $arg, get_permalink( $id ) ) . '#st-recensioni';
	}

	/* ---------- Statistiche di gradimento ---------- */

	public static function mostra( $id, $tipo ) {
		$a = Schede::aggregato( $id );
		ob_start();
		?>
		<section class="st-radiografia" aria-labelledby="st-radiografia-titolo">
			<h2 id="st-radiografia-titolo">Statistiche di gradimento</h2>
			<p class="st-nota">Questa pagina serve a capire quanto e perché il soggetto è apprezzato: ogni numero viene dalle recensioni valide di utenti con l'email verificata. <a href="<?php echo esc_url( Vista::url_metodologia() ); ?>">Come calcoliamo i punteggi</a>.</p>
			<?php if ( ! $a['n_recensioni'] ) : ?>
				<p>Ancora nessuna recensione valida. <a href="#st-recensisci">Scrivi la prima</a>.</p>
			<?php else : ?>
				<div class="st-punteggio-principale">
					<p class="st-cifra"><?php echo esc_html( self::numero( $a['media_bayes'], 2 ) ); ?></p>
					<div>
						<p><?php echo self::stelle( $a['media_bayes'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <strong>punteggio ponderato</strong> su 5</p>
						<p class="st-nota">
							Media semplice <?php echo esc_html( self::numero( $a['media'], 2 ) ); ?> ·
							<?php echo esc_html( sprintf( _n( '%s recensione', '%s recensioni', $a['n_recensioni'], 'statistiche-top' ), number_format_i18n( $a['n_recensioni'] ) ) ); ?>
							da <?php echo esc_html( sprintf( _n( '%s utente verificato', '%s utenti verificati', $a['n_recensori'], 'statistiche-top' ), number_format_i18n( $a['n_recensori'] ) ) ); ?>
						</p>
					</div>
				</div>

				<table class="st-tabella st-distribuzione">
					<caption>Distribuzione delle stelle (scegli una riga per leggere solo quelle recensioni)</caption>
					<thead><tr><th scope="col">Stelle</th><th scope="col">Recensioni</th><th scope="col">Percentuale</th><th scope="col"><span class="screen-reader-text">Barra</span></th></tr></thead>
					<tbody>
						<?php foreach ( $a['distribuzione'] as $stelle => $quante ) : ?>
							<?php $pct = $a['n_recensioni'] ? 100 * $quante / $a['n_recensioni'] : 0; ?>
							<tr>
								<th scope="row"><a href="<?php echo esc_url( self::url_filtro( $id, array( 'stelle' => $stelle ) ) ); ?>"><?php echo esc_html( $stelle . ( 1 === $stelle ? ' stella' : ' stelle' ) ); ?></a></th>
								<td><?php echo esc_html( number_format_i18n( $quante ) ); ?></td>
								<td><?php echo esc_html( self::numero( $pct ) ); ?>%</td>
								<td><?php echo self::barra( $pct ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php echo self::indicatori( $a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo self::aspetti( $a, $tipo ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo self::relazioni( $a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function indicatori( array $a ) {
		$voci = array();
		if ( $a['consiglia']['n'] ) {
			$voci[] = array(
				self::numero( $a['consiglia']['promotori'], 0 ) . '%',
				'lo consiglierebbe con convinzione (9 o 10 su 10)',
				sprintf( 'Indice di raccomandazione: %+d, su %s risposte (da −100 a +100).', $a['consiglia']['nps'], number_format_i18n( $a['consiglia']['n'] ) ),
			);
		}
		if ( $a['tornerebbe']['n'] ) {
			$voci[] = array( self::numero( $a['tornerebbe']['si'], 0 ) . '%', 'si rivolgerebbe di nuovo a lui', sprintf( 'Su %s risposte.', number_format_i18n( $a['tornerebbe']['n'] ) ) );
		}
		if ( $a['problemi']['n'] ) {
			$voci[] = array( self::numero( $a['problemi']['risolti'], 0 ) . '%', 'dei problemi segnalati è stato risolto', sprintf( '%s recensioni con un problema; %s senza problemi.', number_format_i18n( $a['problemi']['n'] ), number_format_i18n( $a['problemi']['nessuno'] ) ) );
		} elseif ( $a['problemi']['nessuno'] ) {
			$voci[] = array( '0', 'problemi segnalati', sprintf( 'In tutte le %s recensioni che rispondono non ci sono stati problemi.', number_format_i18n( $a['problemi']['nessuno'] ) ) );
		}
		if ( ! $voci ) {
			return '';
		}
		ob_start();
		echo '<h3>In sintesi</h3><ul class="st-indicatori">';
		foreach ( $voci as $v ) {
			printf( '<li><span class="st-indicatore-cifra">%1$s</span> <span class="st-indicatore-testo">%2$s</span><span class="st-nota">%3$s</span></li>', esc_html( $v[0] ), esc_html( $v[1] ), esc_html( $v[2] ) );
		}
		echo '</ul>';
		return ob_get_clean();
	}

	private static function aspetti( array $a, $tipo ) {
		if ( ! $a['aspetti'] ) {
			return '';
		}
		$ordine  = array_keys( Punteggi::aspetti( $tipo ) );
		$aspetti = $a['aspetti'];
		uksort(
			$aspetti,
			static function ( $x, $y ) use ( $ordine ) {
				$px = array_search( $x, $ordine, true );
				$py = array_search( $y, $ordine, true );
				return ( false === $px ? 99 : $px ) - ( false === $py ? 99 : $py );
			}
		);
		$fd = Punteggi::forza_debolezza( $aspetti );
		ob_start();
		?>
		<h3>La radiografia, aspetto per aspetto</h3>
		<?php if ( $fd['forza'] || $fd['debolezza'] ) : ?>
			<div class="st-forza-debolezza">
				<?php if ( $fd['forza'] ) : ?>
					<div><h4><span aria-hidden="true">▲</span> Punti di forza</h4><ul>
						<?php foreach ( $fd['forza'] as $k ) : ?>
							<li><?php echo esc_html( Punteggi::etichetta_aspetto( $k ) . ' — ' . self::numero( $aspetti[ $k ]['media'] ) . ' su 5' ); ?></li>
						<?php endforeach; ?>
					</ul></div>
				<?php endif; ?>
				<?php if ( $fd['debolezza'] ) : ?>
					<div><h4><span aria-hidden="true">▼</span> Punti deboli</h4><ul>
						<?php foreach ( $fd['debolezza'] as $k ) : ?>
							<li><?php echo esc_html( Punteggi::etichetta_aspetto( $k ) . ' — ' . self::numero( $aspetti[ $k ]['media'] ) . ' su 5' ); ?></li>
						<?php endforeach; ?>
					</ul></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="st-scorrevole" tabindex="0" role="region" aria-label="Media per aspetto">
			<table class="st-tabella">
				<caption>Media per aspetto, da 1 a 5 (le risposte «Non so» non contano)</caption>
				<thead><tr><th scope="col">Aspetto</th><th scope="col">Media</th><th scope="col">Risposte</th><th scope="col"><span class="screen-reader-text">Barra</span></th></tr></thead>
				<tbody>
					<?php foreach ( $aspetti as $k => $v ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( Punteggi::etichetta_aspetto( $k ) ); ?></th>
							<td><?php echo esc_html( self::numero( $v['media'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $v['n'] ) ); ?></td>
							<td><?php echo self::barra( $v['media'] / 5 * 100 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function relazioni( array $a ) {
		if ( ! $a['relazioni'] ) {
			return '';
		}
		ob_start();
		echo '<h3>Chi ha valutato</h3><ul class="st-relazioni">';
		foreach ( $a['relazioni'] as $k => $n ) {
			$nome = isset( Voti::RELAZIONI[ $k ] ) ? Voti::RELAZIONI[ $k ][0] : 'Altro';
			printf( '<li>%1$s: <strong>%2$s</strong></li>', esc_html( $nome ), esc_html( number_format_i18n( $n ) ) );
		}
		echo '</ul>';
		return ob_get_clean();
	}

	/* ---------- Le recensioni ---------- */

	public static function elenco( $id ) {
		$f = self::filtri();
		list( $righe, $totale ) = Voti::recensioni_pubbliche( $id, $f, self::PER_PAGINA, ( $f['pagina'] - 1 ) * self::PER_PAGINA );
		$a = Schede::aggregato( $id );
		if ( ! $a['n_recensioni'] ) {
			return '';
		}
		$utente = get_current_user_id();
		ob_start();
		?>
		<section aria-labelledby="st-recensioni-titolo" id="st-recensioni">
			<h2 id="st-recensioni-titolo">Le recensioni</h2>
			<?php echo Vista::avviso( 'st-recensioni' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<nav class="st-filtri" aria-label="Filtra e ordina le recensioni">
				<p><span class="st-filtri-nome">Ordina:</span>
					<?php echo self::link_filtro( $id, array( 'ordine' => 'utili' ), 'più utili', 'utili' === $f['ordine'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo self::link_filtro( $id, array( 'ordine' => 'recenti' ), 'più recenti', 'recenti' === $f['ordine'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
				<p><span class="st-filtri-nome">Stelle:</span>
					<?php echo self::link_filtro( $id, array( 'stelle' => null ), 'tutte', ! $f['stelle'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php for ( $s = 5; $s >= 1; $s-- ) : ?>
						<?php echo self::link_filtro( $id, array( 'stelle' => $s ), (string) $s, $f['stelle'] === $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endfor; ?>
				</p>
				<?php if ( count( $a['relazioni'] ) > 1 ) : ?>
					<p><span class="st-filtri-nome">Rapporto:</span>
						<?php echo self::link_filtro( $id, array( 'relazione' => '' ), 'tutti', ! $f['relazione'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php foreach ( array_keys( $a['relazioni'] ) as $k ) : ?>
							<?php if ( isset( Voti::RELAZIONI[ $k ] ) ) : ?>
								<?php echo self::link_filtro( $id, array( 'relazione' => $k ), strtok( Voti::RELAZIONI[ $k ][0], ' ' ), $f['relazione'] === $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</nav>
			<p class="st-nota" role="status"><?php echo esc_html( sprintf( _n( '%s recensione', '%s recensioni', $totale, 'statistiche-top' ), number_format_i18n( $totale ) ) . ( $f['stelle'] || $f['relazione'] ? ' con i filtri scelti.' : '.' ) ); ?></p>
			<?php if ( $righe ) : ?>
				<ol class="st-recensioni">
					<?php foreach ( $righe as $r ) : ?>
						<li><?php echo self::recensione( $r, $utente, $f ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<?php if ( $totale > $f['pagina'] * self::PER_PAGINA ) : ?>
				<p><a class="st-pulsante st-pulsante--secondario" href="<?php echo esc_url( self::url_filtro( $id, array( 'pagina' => $f['pagina'] + 1 ) ) ); ?>">Mostra le recensioni successive</a></p>
			<?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	/** Link di filtro: quello attivo è in grassetto e ha aria-current, non solo un colore diverso. */
	private static function link_filtro( $id, array $cambia, $testo, $attivo ) {
		return $attivo
			? sprintf( '<a class="st-filtro st-filtro--attivo" aria-current="true" href="%1$s">%2$s</a>', esc_url( self::url_filtro( $id, $cambia ) ), esc_html( $testo ) )
			: sprintf( '<a class="st-filtro" href="%1$s">%2$s</a>', esc_url( self::url_filtro( $id, $cambia ) ), esc_html( $testo ) );
	}

	private static function recensione( array $r, $utente, array $f ) {
		$autore = get_userdata( (int) $r['utente_id'] );
		$rel    = isset( Voti::RELAZIONI[ $r['relazione'] ] ) ? Voti::RELAZIONI[ $r['relazione'] ][0] : '';
		list( $anno, $mese ) = array_pad( explode( '-', (string) $r['esperienza'] ), 2, '' );
		$quando = ( (int) $mese ? date_i18n( 'F', mktime( 0, 0, 0, (int) $mese, 1 ) ) . ' ' : '' ) . $anno;
		ob_start();
		?>
		<article class="st-recensione" id="st-recensione-<?php echo esc_attr( $r['id'] ); ?>" aria-labelledby="st-recensione-<?php echo esc_attr( $r['id'] ); ?>-titolo">
			<p class="st-recensione-voto"><?php echo self::stelle( (int) $r['stelle'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<h3 id="st-recensione-<?php echo esc_attr( $r['id'] ); ?>-titolo"><?php echo esc_html( $r['titolo'] ? $r['titolo'] : 'Recensione' ); ?></h3>
			<p class="st-nota">
				<?php echo esc_html( $autore ? $autore->display_name : 'Utente' ); ?> · <strong>Utente verificato</strong> (email)
				<?php if ( $rel ) : ?> · <?php echo esc_html( $rel ); ?><?php endif; ?>
				<?php if ( trim( $quando ) ) : ?> · esperienza di <?php echo esc_html( $quando ); ?><?php endif; ?>
				<?php if ( (int) $r['da_proposta'] ) : ?> · <em>ha proposto la scheda</em><?php endif; ?>
			</p>
			<?php if ( $r['pro'] ) : ?>
				<p><strong>Cosa è piaciuto:</strong> <?php echo esc_html( $r['pro'] ); ?></p>
			<?php endif; ?>
			<?php if ( $r['contro'] ) : ?>
				<p><strong>Cosa non è piaciuto:</strong> <?php echo esc_html( $r['contro'] ); ?></p>
			<?php endif; ?>
			<?php if ( $r['commento'] ) : ?>
				<?php echo wpautop( esc_html( $r['commento'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php if ( $r['aspetti'] ) : ?>
				<details class="st-dettagli">
					<summary>Voti sugli aspetti (<?php echo esc_html( count( $r['aspetti'] ) ); ?>)</summary>
					<ul>
						<?php foreach ( $r['aspetti'] as $k => $v ) : ?>
							<li><?php echo esc_html( Punteggi::etichetta_aspetto( $k ) . ': ' . (int) $v . ' su 5' ); ?></li>
						<?php endforeach; ?>
						<?php if ( null !== $r['consiglia'] ) : ?>
							<li><?php echo esc_html( 'Lo consiglierebbe: ' . (int) $r['consiglia'] . ' su 10' ); ?></li>
						<?php endif; ?>
					</ul>
				</details>
			<?php endif; ?>
			<p class="st-recensione-data st-nota">Pubblicata il <time datetime="<?php echo esc_attr( mysql2date( 'c', $r['creato'] ) ); ?>"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $r['creato'] ) ); ?></time><?php echo $r['modificato'] !== $r['creato'] ? esc_html( ' (modificata)' ) : ''; ?></p>
			<div class="st-utile">
				<p class="st-nota"><?php echo esc_html( (int) $r['utili'] ? sprintf( _n( '%s persona l\'ha trovata utile.', '%s persone l\'hanno trovata utile.', (int) $r['utili'], 'statistiche-top' ), number_format_i18n( (int) $r['utili'] ) ) : 'Nessuno l\'ha ancora segnata come utile.' ); ?></p>
				<?php if ( $utente && (int) $r['utente_id'] !== $utente ) : ?>
					<?php $votata = Voti::ha_votato_utile( (int) $r['id'], $utente ); ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="st_utile">
						<input type="hidden" name="st_voto" value="<?php echo esc_attr( $r['id'] ); ?>">
						<input type="hidden" name="st_ordine" value="<?php echo esc_attr( $f['ordine'] ); ?>">
						<?php wp_nonce_field( 'st_utile_' . $r['id'], 'st_nonce', false ); ?>
						<button type="submit" class="st-pulsante st-pulsante--secondario" aria-pressed="<?php echo $votata ? 'true' : 'false'; ?>"><?php echo $votata ? '✓ Utile (togli)' : 'Utile'; ?><span class="screen-reader-text"><?php echo esc_html( ': ' . $r['titolo'] ); ?></span></button>
					</form>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
