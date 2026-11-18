<?php
/**
 * Aufmacher: der Bericht ganz oben auf der Startseite.
 *
 * Regel:
 *  1. Ein Bericht mit Häkchen „Oben auf der Startseite“ (Meta afk_aufmacher = 1),
 *     dessen Markierung höchstens 14 Tage alt ist (Filter afk_aufmacher_tage).
 *  2. Sonst der neueste veröffentlichte Bericht.
 * Es ist immer nur ein Bericht markiert: wer einen neuen markiert, nimmt dem alten das Häkchen.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wie viele Tage eine Markierung gilt.
 *
 * @return int
 */
function afk_aufmacher_tage() {
	return max( 1, (int) apply_filters( 'afk_aufmacher_tage', 14 ) );
}

/**
 * Markierungszeitpunkt merken und andere Markierungen entfernen.
 *
 * @param int    $meta_id    Meta-ID.
 * @param int    $post_id    Beitrag.
 * @param string $meta_key   Schlüssel.
 * @param mixed  $meta_value Wert.
 */
function afk_aufmacher_meta_changed( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( 'afk_aufmacher' !== $meta_key ) {
		return;
	}
	if ( ! (int) $meta_value ) {
		delete_post_meta( $post_id, 'afk_aufmacher_seit' );
		return;
	}
	update_post_meta( $post_id, 'afk_aufmacher_seit', time() );

	remove_action( 'updated_post_meta', 'afk_aufmacher_meta_changed', 10 );
	remove_action( 'added_post_meta', 'afk_aufmacher_meta_changed', 10 );
	$andere = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => array( (int) $post_id ),
			'meta_key'       => 'afk_aufmacher', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		)
	);
	foreach ( $andere as $id ) {
		update_post_meta( $id, 'afk_aufmacher', 0 );
		delete_post_meta( $id, 'afk_aufmacher_seit' );
	}
	add_action( 'updated_post_meta', 'afk_aufmacher_meta_changed', 10, 4 );
	add_action( 'added_post_meta', 'afk_aufmacher_meta_changed', 10, 4 );
}
add_action( 'updated_post_meta', 'afk_aufmacher_meta_changed', 10, 4 );
add_action( 'added_post_meta', 'afk_aufmacher_meta_changed', 10, 4 );

/**
 * ID des aktuellen Aufmachers (oder 0). Pro Seitenaufruf nur einmal berechnet.
 *
 * @return int
 */
function afk_get_aufmacher_id() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$markiert = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'afk_aufmacher', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		)
	);
	if ( $markiert ) {
		$seit = (int) get_post_meta( $markiert[0], 'afk_aufmacher_seit', true );
		if ( $seit && ( time() - $seit ) <= afk_aufmacher_tage() * DAY_IN_SECONDS ) {
			$cache = (int) $markiert[0];
			return $cache;
		}
	}
	$neuester = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
	$cache    = $neuester ? (int) $neuester[0] : 0;
	return $cache;
}

/**
 * Shortcode [afk_aufmacher button="Zum Bericht" bild="ja"] (für andere Themes).
 *
 * @param array $atts Attribute.
 * @return string
 */
function afk_sc_aufmacher( $atts ) {
	$atts = shortcode_atts( array( 'button' => '', 'bild' => 'ja' ), $atts, 'afk_aufmacher' );
	$id   = afk_get_aufmacher_id();
	if ( ! $id ) {
		return '';
	}
	$kats   = get_the_category( $id );
	$kat    = $kats ? $kats[0]->name : '';
	$button = '' !== $atts['button'] ? $atts['button'] : ( has_category( 'einsaetze', $id ) ? 'Zum Einsatzbericht' : 'Zum Bericht' );
	$text   = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 32 );

	$out = '<section class="afk-aufmacher">';
	if ( 'ja' === $atts['bild'] && has_post_thumbnail( $id ) ) {
		$out .= get_the_post_thumbnail( $id, 'full', array( 'class' => 'afk-aufmacher__bild', 'alt' => '' ) );
	}
	$out .= '<div class="afk-aufmacher__kasten"><p class="afk-aufmacher__meta">';
	$out .= $kat ? '<span class="afk-aufmacher__kat">' . esc_html( $kat ) . '</span>' : '';
	$out .= '<span>' . esc_html( get_the_date( 'j. F Y', $id ) ) . '</span></p>';
	$out .= '<h1 class="afk-aufmacher__titel">' . esc_html( get_the_title( $id ) ) . '</h1>';
	$out .= $text ? '<p class="afk-aufmacher__text">' . esc_html( $text ) . '</p>' : '';
	$out .= '<a class="afk-aufmacher__btn" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $button ) . ' →</a>';
	return $out . '</div></section>';
}
add_shortcode( 'afk_aufmacher', 'afk_sc_aufmacher' );

/**
 * Elementor: Abfrage-ID „afk_ohne_aufmacher“ blendet den Aufmacher aus.
 *
 * @param WP_Query $query Abfrage des Widgets.
 */
function afk_elementor_ohne_aufmacher( $query ) {
	$id = afk_get_aufmacher_id();
	if ( $id ) {
		$raus   = (array) $query->get( 'post__not_in' );
		$raus[] = $id;
		$query->set( 'post__not_in', array_values( array_unique( array_map( 'intval', $raus ) ) ) );
	}
}
add_action( 'elementor/query/afk_ohne_aufmacher', 'afk_elementor_ohne_aufmacher' );

/**
 * Spalte „Startseite“ in der Beitragsliste.
 *
 * @param array $cols Spalten.
 * @return array
 */
function afk_aufmacher_admin_column( $cols ) {
	$neu = array();
	foreach ( $cols as $key => $label ) {
		$neu[ $key ] = $label;
		if ( 'title' === $key ) {
			$neu['afk_aufmacher'] = 'Startseite';
		}
	}
	return $neu;
}
add_filter( 'manage_post_posts_columns', 'afk_aufmacher_admin_column' );

/**
 * Inhalt der Spalte.
 *
 * @param string $col     Spalte.
 * @param int    $post_id Beitrag.
 */
function afk_aufmacher_admin_column_content( $col, $post_id ) {
	if ( 'afk_aufmacher' === $col && (int) $post_id === afk_get_aufmacher_id() ) {
		echo (int) get_post_meta( $post_id, 'afk_aufmacher', true )
			? '<span title="Oben auf der Startseite (markiert)">★ oben</span>'
			: '<span title="Oben auf der Startseite (automatisch, neuester Beitrag)">☆ oben (auto)</span>';
	}
}
add_action( 'manage_post_posts_custom_column', 'afk_aufmacher_admin_column_content', 10, 2 );
