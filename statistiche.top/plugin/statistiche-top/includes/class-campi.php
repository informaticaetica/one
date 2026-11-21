<?php
/**
 * Campi dei moduli pubblici.
 *
 * @package StatisticheTop
 */

namespace StatisticheTop;

defined( 'ABSPATH' ) || exit;

/**
 * Generatore dei campi dei moduli: etichetta, aiuto a fumetto, errore e
 * collegamenti aria-describedby, sempre nello stesso modo.
 */
final class Campi {

	private $d;
	private $e;
	private $aiuti;

	public function __construct( array $dati, array $errori, array $aiuti = Modulo::AIUTI ) {
		$this->d     = $dati;
		$this->e     = $errori;
		$this->aiuti = $aiuti;
	}

	public static function id( $campo ) {
		return 'st-campo-' . str_replace( '_', '-', preg_replace( '/^st_/', '', $campo ) );
	}

	private function v( $k ) {
		return isset( $this->d[ $k ] ) && is_scalar( $this->d[ $k ] ) ? (string) $this->d[ $k ] : '';
	}

	private function errore( $campo ) {
		if ( isset( $this->e[ $campo ] ) ) {
			printf( '<span class="st-errore" id="%1$s-errore"><strong>Errore:</strong> %2$s</span>', esc_attr( self::id( $campo ) ), esc_html( $this->e[ $campo ] ) );
		}
	}

	private function descritto( $campo, $aiuto, $gruppo = false ) {
		$id = array_filter( array( $aiuto ? 'st-aiuto-' . $aiuto : '', isset( $this->e[ $campo ] ) ? self::id( $campo ) . '-errore' : '' ) );
		$out = $id ? ' aria-describedby="' . esc_attr( implode( ' ', $id ) ) . '"' : '';
		if ( isset( $this->e[ $campo ] ) ) {
			$out .= $gruppo ? ' class="st-gruppo-errato"' : ' aria-invalid="true"';
		}
		return $out;
	}

	private function obbligatorio( $si ) {
		return $si ? ' <span class="st-obbligatorio">(obbligatorio)</span>' : '';
	}

	private function testa( $per, $etichetta, $aiuto, $obbligatorio, $nome_aiuto = '' ) {
		echo '<div class="st-campo-testa">';
		printf( '<label for="%1$s">%2$s%3$s</label>', esc_attr( $per ), esc_html( $etichetta ), $this->obbligatorio( $obbligatorio ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $aiuto ) {
			Vista::pulsante_aiuto( $aiuto, $nome_aiuto ? $nome_aiuto : $etichetta );
		}
		echo '</div>';
		if ( $aiuto ) {
			Vista::balloon( $aiuto, $this->aiuti[ $aiuto ] );
		}
	}

	private function legenda( $etichetta, $aiuto, $obbligatorio ) {
		printf( '<legend class="st-legenda-con-aiuto">%1$s%2$s</legend>', esc_html( $etichetta ), $this->obbligatorio( $obbligatorio ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $aiuto ) {
			echo '<div class="st-campo-testa st-campo-testa--gruppo">';
			Vista::pulsante_aiuto( $aiuto, $etichetta );
			echo '</div>';
			Vista::balloon( $aiuto, $this->aiuti[ $aiuto ] );
		}
	}

	public function testo( $campo, $etichetta, $aiuto, $obbligatorio, array $attr = array() ) {
		$id   = self::id( $campo );
		$tipo = isset( $attr['type'] ) ? $attr['type'] : 'text';
		unset( $attr['type'] );
		echo '<div class="st-campo">';
		$this->testa( $id, $etichetta, $aiuto, $obbligatorio );
		$extra = '';
		foreach ( $attr as $k => $v ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
		}
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s>',
			esc_attr( $tipo ),
			esc_attr( $id ),
			esc_attr( $campo ),
			esc_attr( $this->v( $campo ) ),
			$obbligatorio ? ' required' : '',
			$extra, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$this->descritto( $campo, $aiuto ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		$this->errore( $campo );
		echo '</div>';
	}

	public function area( $campo, $etichetta, $aiuto, $obbligatorio, $max, $righe, $contatore = false ) {
		$id = self::id( $campo );
		echo '<div class="st-campo">';
		$this->testa( $id, $etichetta, $aiuto, $obbligatorio );
		$descr = $this->descritto( $campo, $aiuto );
		if ( $contatore ) {
			$descr = str_replace( 'aria-describedby="', 'aria-describedby="' . $id . '-contatore ', $descr ? $descr : ' aria-describedby=""' );
		}
		printf(
			'<textarea id="%1$s" name="%2$s" rows="%3$d" maxlength="%4$d"%5$s%6$s%7$s>%8$s</textarea>',
			esc_attr( $id ),
			esc_attr( $campo ),
			(int) $righe,
			(int) $max,
			$obbligatorio ? ' required' : '',
			$contatore ? ' data-st-contatore="' . esc_attr( $id . '-contatore' ) . '"' : '',
			$descr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_textarea( $this->v( $campo ) )
		);
		if ( $contatore ) {
			printf( '<span id="%s-contatore" class="st-nota" aria-live="polite"></span>', esc_attr( $id ) );
		}
		$this->errore( $campo );
		echo '</div>';
	}

	/**
	 * Gruppo di scelte (radio). $in_linea mette le scelte su una riga (scale brevi);
	 * $scala usa i riquadri da 44 px come per le stelle.
	 */
	public function radio( $campo, $etichetta, array $scelte, $aiuto, $obbligatorio, $in_linea = false, $scala = false ) {
		$id = self::id( $campo );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( $campo, $aiuto, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->legenda( $etichetta, $aiuto, $obbligatorio );
		$this->errore( $campo );
		echo $scala ? '<div class="st-voto-scelte st-scala">' : ( $in_linea ? '<div class="st-scelte-in-linea">' : '<div>' );
		foreach ( $scelte as $valore => $testo ) {
			$opz = $id . '-' . sanitize_html_class( (string) $valore );
			if ( $scala ) {
				printf(
					'<input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s><label for="%1$s">%5$s</label>',
					esc_attr( $opz ),
					esc_attr( $campo ),
					esc_attr( $valore ),
					checked( $this->v( $campo ), (string) $valore, false ),
					esc_html( $testo )
				);
			} else {
				printf(
					'<p class="st-spunta"><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s><label for="%1$s">%5$s</label></p>',
					esc_attr( $opz ),
					esc_attr( $campo ),
					esc_attr( $valore ),
					checked( $this->v( $campo ), (string) $valore, false ),
					esc_html( $testo )
				);
			}
		}
		echo '</div></fieldset>';
	}

	public function spunta( $campo, $etichetta, $aiuto, $obbligatorio ) {
		$id = self::id( $campo );
		echo '<div class="st-campo"><p class="st-spunta">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s%5$s><label for="%1$s">%6$s%7$s</label>',
			esc_attr( $id ),
			esc_attr( $campo ),
			checked( $this->v( $campo ), '1', false ),
			$obbligatorio ? ' required' : '',
			$this->descritto( $campo, $aiuto ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $etichetta ),
			$this->obbligatorio( $obbligatorio ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		if ( $aiuto ) {
			Vista::pulsante_aiuto( $aiuto, $etichetta );
		}
		echo '</p>';
		if ( $aiuto ) {
			Vista::balloon( $aiuto, $this->aiuti[ $aiuto ] );
		}
		$this->errore( $campo );
		echo '</div>';
	}

	/** Stelle complessive da 1 a 5, con il significato scritto accanto al numero. */
	public function stelle() {
		$nomi = array( 1 => 'pessima', 2 => 'scarsa', 3 => 'nella media', 4 => 'buona', 5 => 'ottima' );
		$id   = self::id( 'st_stelle' );
		printf( '<fieldset id="%1$s"%2$s>', esc_attr( $id ), $this->descritto( 'st_stelle', 'stelle', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$this->legenda( 'Valutazione complessiva', 'stelle', true );
		$this->errore( 'st_stelle' );
		echo '<div class="st-voto-scelte st-stelle-scelta">';
		foreach ( $nomi as $n => $nome ) {
			printf(
				'<input type="radio" id="%1$s-%2$d" name="st_stelle" value="%2$d"%3$s><label for="%1$s-%2$d"><span aria-hidden="true">%4$s</span> %2$d <span class="st-stelle-nome">%5$s</span></label>',
				esc_attr( $id ),
				(int) $n,
				checked( $this->v( 'st_stelle' ), (string) $n, false ),
				esc_html( str_repeat( '★', $n ) ),
				esc_html( $nome )
			);
		}
		echo '</div></fieldset>';
	}

	/** La radiografia: un gruppo per ogni aspetto, con "Non so" già scelto. */
	public function aspetti( array $aspetti, $minimo = Punteggi::MIN_ASPETTI ) {
		$valori = isset( $this->d['st_aspetto'] ) ? (array) $this->d['st_aspetto'] : array();
		$id     = self::id( 'st_aspetti' );
		printf( '<div class="st-campo st-aspetti" id="%1$s">', esc_attr( $id ) );
		echo '<div class="st-campo-testa"><p class="st-etichetta-gruppo"><strong>Valuta gli aspetti</strong> <span class="st-obbligatorio">' . ( $minimo ? '(almeno ' . (int) $minimo . ')' : '(facoltativo)' ) . '</span></p>';
		Vista::pulsante_aiuto( 'aspetti', 'aspetti' );
		echo '</div>';
		Vista::balloon( 'aspetti', $this->aiuti['aspetti'] );
		$this->errore( 'st_aspetti' );
		foreach ( $aspetti as $chiave => $nome ) {
			$attuale = isset( $valori[ $chiave ] ) ? (string) $valori[ $chiave ] : '0';
			printf( '<fieldset class="st-voto"%s><legend>%s</legend><div class="st-voto-scelte">', isset( $this->e['st_aspetti'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-errore"' : '', esc_html( $nome ) );
			foreach ( array( '0' => 'Non so', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ) as $valore => $testo ) {
				$opz = 'st-aspetto-' . $chiave . '-' . $valore;
				printf(
					'<input type="radio" id="%1$s" name="st_aspetto[%2$s]" value="%3$s"%4$s><label for="%1$s"%6$s>%5$s</label>',
					esc_attr( $opz ),
					esc_attr( $chiave ),
					esc_attr( $valore ),
					checked( $attuale, (string) $valore, false ),
					esc_html( $testo ),
					'0' === $valore ? ' class="st-non-so"' : ''
				);
			}
			echo '</div></fieldset>';
		}
		echo '</div>';
	}
}
