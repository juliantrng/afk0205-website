<?php
/**
 * Hinweisband (Waldbrand, Unwetter, Straßensperre, Info).
 * Wird automatisch direkt nach <body> ausgegeben. Wer es an anderer Stelle haben will,
 * schaltet die automatische Ausgabe ab:
 *   add_filter( 'afk_hinweis_auto', '__return_false' );
 * und setzt den Shortcode [afk_hinweis] in die Vorlage.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aktueller Hinweis oder null.
 *
 * @return array|null
 */
function afk_hinweis_get() {
	$o = afk_hinweis_optionen();
	if ( empty( $o['aktiv'] ) ) {
		return null;
	}

	if ( $o['ende'] ) {
		try {
			$ende_dt = new DateTimeImmutable( $o['ende'], wp_timezone() );
			if ( $ende_dt->getTimestamp() < time() ) {
				return null;
			}
		} catch ( Exception $e ) {
			unset( $e );
		}
	}

	$titel = (string) $o['titel'];
	$text  = (string) $o['text'];
	if ( '' === $titel && '' === $text ) {
		return null;
	}

	$art = (string) $o['art'];
	if ( ! array_key_exists( $art, afk_hinweis_arten() ) ) {
		$art = 'info';
	}

	$link = null;
	if ( '' !== $o['link_url'] ) {
		$link = array(
			'url'   => $o['link_url'],
			'title' => $o['link_text'],
		);
	}

	return array(
		'art'   => $art,
		'titel' => $titel,
		'text'  => $text,
		'link'  => $link,
	);
}

/**
 * HTML des Hinweisbands.
 *
 * @return string
 */
function afk_hinweis_render() {
	$h = afk_hinweis_get();
	if ( ! $h ) {
		return '';
	}

	// Kennung ändert sich mit dem Inhalt: ein neuer Hinweis erscheint wieder,
	// auch wenn jemand den alten ausgeblendet hat.
	$id = 'afk-h-' . substr( md5( $h['art'] . $h['titel'] . $h['text'] ), 0, 10 );

	wp_enqueue_script( 'afk-hinweis', AFK_CORE_URL . 'assets/hinweis.js', array(), AFK_CORE_VERSION, true );

	$link_html = '';
	if ( is_array( $h['link'] ) && ! empty( $h['link']['url'] ) ) {
		$label     = ! empty( $h['link']['title'] ) ? $h['link']['title'] : 'Mehr Informationen';
		$link_html = '<a class="afk-hinweis__link" href="' . esc_url( $h['link']['url'] ) . '">' . esc_html( $label ) . '</a>';
	}

	$icon = '<svg class="afk-hinweis__icon" aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17.5v.5"/></svg>';

	$html  = '<div class="afk-hinweis afk-hinweis--' . esc_attr( $h['art'] ) . '" role="status" data-afk-hinweis="' . esc_attr( $id ) . '">';
	$html .= '<div class="afk-hinweis__inner">';
	$html .= $icon;
	$html .= '<p class="afk-hinweis__text">';
	if ( '' !== $h['titel'] ) {
		$html .= '<strong>' . esc_html( $h['titel'] ) . ':</strong> ';
	}
	$html .= esc_html( $h['text'] ) . '</p>';
	$html .= $link_html;
	$html .= '<button type="button" class="afk-hinweis__close" aria-label="Hinweis ausblenden"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';
	$html .= '</div></div>';

	return $html;
}

add_shortcode( 'afk_hinweis', 'afk_hinweis_render' );

/**
 * Automatische Ausgabe nach <body>.
 */
function afk_hinweis_auto_output() {
	if ( is_admin() || ! apply_filters( 'afk_hinweis_auto', true ) ) {
		return;
	}
	echo afk_hinweis_render(); // phpcs:ignore WordPress.Security.EscapeOutput -- bereits escaped.
}
add_action( 'wp_body_open', 'afk_hinweis_auto_output', 5 );
