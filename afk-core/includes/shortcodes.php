<?php
/**
 * Shortcodes – für den Einsatz in anderen Themes oder Seitenbaukästen.
 * Das Theme „AFK Nachtschicht“ braucht sie nicht, es bringt eigene Vorlagen mit.
 *
 *   [afk_byline] [afk_beteiligte_feuerwehren] [afk_bildquelle]
 *   [afk_feuerwehr_titelbild] [afk_feuerwehr_profil] [afk_feuerwehr_wechsler]
 *   [afk_feuerwehren_liste] [afk_tipps anzahl="4"] [afk_hinweis] [afk_aufmacher] [afk_alarmierungen]
 */

defined( 'ABSPATH' ) || exit;

/**
 * Die Feuerwehr, um die es gerade geht.
 *
 * @param array $atts Shortcode-Attribute.
 * @return WP_Term|null
 */
function afk_current_feuerwehr( $atts = array() ) {
	if ( ! empty( $atts['slug'] ) ) {
		$t = get_term_by( 'slug', sanitize_title( $atts['slug'] ), 'feuerwehr' );
		return $t ? $t : null;
	}
	if ( is_tax( 'feuerwehr' ) ) {
		$t = get_queried_object();
		return ( $t instanceof WP_Term ) ? $t : null;
	}
	return null;
}

/**
 * Textarea in Zeilen zerlegen (leere Zeilen weg).
 *
 * @param string $text Text.
 * @return string[]
 */
function afk_lines( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}

function afk_sc_byline() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	$verfasser = trim( (string) afk_field( 'afk_verfasser', $post_id ) );
	if ( '' === $verfasser ) {
		$verfasser = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) );
	}
	$einheit    = trim( (string) afk_field( 'afk_verfasser_einheit', $post_id ) );
	$bildquelle = trim( (string) afk_field( 'afk_bildquelle', $post_id ) );

	$out  = '<div class="afk-byline">';
	$out .= '<div class="afk-byline__item"><span class="afk-byline__label">Bericht</span><span class="afk-byline__value"><strong>' . esc_html( $verfasser ) . '</strong>';
	if ( '' !== $einheit ) {
		$out .= ', ' . esc_html( $einheit );
	}
	$out .= '</span></div>';
	if ( '' !== $bildquelle ) {
		$out .= '<div class="afk-byline__item"><span class="afk-byline__label">Fotos</span><span class="afk-byline__value"><strong>' . esc_html( $bildquelle ) . '</strong></span></div>';
	}
	$out .= '<div class="afk-byline__item"><span class="afk-byline__label">Veröffentlicht</span><span class="afk-byline__value">' . esc_html( get_the_date( 'd.m.Y', $post_id ) ) . '</span></div>';
	$out .= '</div>';
	return $out;
}
add_shortcode( 'afk_byline', 'afk_sc_byline' );

function afk_sc_bildquelle( $atts ) {
	$atts       = shortcode_atts( array( 'prefix' => 'Bildquelle: ' ), $atts, 'afk_bildquelle' );
	$bildquelle = trim( (string) afk_field( 'afk_bildquelle', get_the_ID() ) );
	if ( '' === $bildquelle ) {
		return '';
	}
	return '<p class="afk-bildquelle">' . esc_html( $atts['prefix'] . $bildquelle ) . '</p>';
}
add_shortcode( 'afk_bildquelle', 'afk_sc_bildquelle' );

function afk_sc_beteiligte( $atts ) {
	$atts   = shortcode_atts(
		array(
			'titel'        => 'Beteiligte Feuerwehren',
			'hinweis'      => 'Antippen für alle Berichte der jeweiligen Feuerwehr',
			'titel_extern' => 'Weitere Kräfte',
		),
		$atts,
		'afk_beteiligte_feuerwehren'
	);
	$wehren = afk_get_feuerwehren();
	$extern = afk_lines( afk_field( 'afk_weitere_kraefte', get_the_ID() ) );
	if ( ! $wehren && ! $extern ) {
		return '';
	}
	$out = '<div class="afk-beteiligte">';
	if ( '' !== $atts['titel'] ) {
		$out .= '<h2 class="afk-box-title">' . esc_html( $atts['titel'] ) . '</h2>';
	}
	if ( $wehren ) {
		if ( '' !== $atts['hinweis'] ) {
			$out .= '<p class="afk-beteiligte__hint">' . esc_html( $atts['hinweis'] ) . '</p>';
		}
		$out .= '<ul class="afk-chips">';
		foreach ( $wehren as $t ) {
			$out .= '<li><a class="afk-chip" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
		}
		$out .= '</ul>';
	}
	if ( $extern ) {
		$out .= '<p class="afk-beteiligte__sub">' . esc_html( $atts['titel_extern'] ) . '</p><ul class="afk-chips">';
		foreach ( $extern as $name ) {
			$out .= '<li><span class="afk-chip afk-chip--muted">' . esc_html( $name ) . '</span></li>';
		}
		$out .= '</ul>';
	}
	return $out . '</div>';
}
add_shortcode( 'afk_beteiligte_feuerwehren', 'afk_sc_beteiligte' );

function afk_sc_feuerwehr_titelbild( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '', 'size' => 'large' ), $atts, 'afk_feuerwehr_titelbild' );
	$term = afk_current_feuerwehr( $atts );
	if ( ! $term ) {
		return '';
	}
	$img_id = (int) afk_field( 'afk_titelbild', 'term_' . $term->term_id );
	if ( ! $img_id ) {
		return '<div class="afk-titelbild afk-titelbild--leer" aria-hidden="true"></div>';
	}
	return wp_get_attachment_image( $img_id, sanitize_key( $atts['size'] ), false, array( 'class' => 'afk-titelbild', 'alt' => 'Foto der ' . $term->name ) );
}
add_shortcode( 'afk_feuerwehr_titelbild', 'afk_sc_feuerwehr_titelbild' );

function afk_sc_feuerwehr_profil( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'afk_feuerwehr_profil' );
	$term = afk_current_feuerwehr( $atts );
	if ( ! $term ) {
		return '';
	}
	$ctx       = 'term_' . $term->term_id;
	$rows      = array(
		'Kommandant'     => (string) afk_field( 'afk_kommandant', $ctx ),
		'Stellvertreter' => (string) afk_field( 'afk_stellvertreter', $ctx ),
		'Mitglieder'     => (string) afk_field( 'afk_mitglieder', $ctx ),
	);
	$fahrzeuge = afk_lines( afk_field( 'afk_fahrzeuge', $ctx ) );
	$out       = '<div class="afk-profil"><dl class="afk-profil__daten">';
	foreach ( $rows as $label => $value ) {
		if ( '' !== trim( $value ) ) {
			$out .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
	}
	if ( $fahrzeuge ) {
		$out .= '<dt>Fahrzeuge</dt><dd>' . esc_html( implode( ', ', $fahrzeuge ) ) . '</dd>';
	}
	$out .= '</dl>';
	$web  = (string) afk_field( 'afk_website', $ctx );
	if ( $web ) {
		$out .= '<p><a class="afk-btn" href="' . esc_url( $web ) . '" rel="noopener">Website ↗</a></p>';
	}
	return $out . '</div>';
}
add_shortcode( 'afk_feuerwehr_profil', 'afk_sc_feuerwehr_profil' );

function afk_sc_feuerwehr_wechsler() {
	$current = afk_current_feuerwehr( array() );
	$wehren  = get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false, 'childless' => true, 'orderby' => 'name' ) );
	if ( ! $wehren || is_wp_error( $wehren ) ) {
		return '';
	}
	$out = '<nav class="afk-wechsler" aria-label="Feuerwehr wechseln"><ul class="afk-chips afk-chips--scroll">';
	foreach ( $wehren as $t ) {
		$on   = $current && (int) $current->term_id === (int) $t->term_id;
		$out .= '<li><a class="afk-chip' . ( $on ? ' afk-chip--aktiv' : ' afk-chip--muted' ) . '" href="' . esc_url( get_term_link( $t ) ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( preg_replace( '/^(FF|BtF)\s+/u', '', $t->name ) ) . '</a></li>';
	}
	return $out . '</ul></nav>';
}
add_shortcode( 'afk_feuerwehr_wechsler', 'afk_sc_feuerwehr_wechsler' );

function afk_sc_feuerwehren_liste() {
	$gemeinden = get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name' ) );
	if ( ! $gemeinden || is_wp_error( $gemeinden ) ) {
		return '';
	}
	$out = '<div class="afk-ff-liste">';
	foreach ( $gemeinden as $g ) {
		$wehren = get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false, 'parent' => $g->term_id, 'orderby' => 'name' ) );
		if ( ! $wehren || is_wp_error( $wehren ) ) {
			continue;
		}
		$out .= '<div class="afk-ff-liste__gemeinde"><h3>' . esc_html( $g->name ) . '</h3><ul>';
		foreach ( $wehren as $t ) {
			$out .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
		}
		$out .= '</ul></div>';
	}
	return $out . '</div>';
}
add_shortcode( 'afk_feuerwehren_liste', 'afk_sc_feuerwehren_liste' );

function afk_sc_tipps( $atts ) {
	$atts  = shortcode_atts( array( 'anzahl' => 4 ), $atts, 'afk_tipps' );
	$tipps = get_posts( array( 'post_type' => 'afk_tipp', 'posts_per_page' => 50, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ), 'no_found_rows' => true ) );
	if ( ! $tipps ) {
		return '';
	}
	usort(
		$tipps,
		function ( $a, $b ) {
			return (int) afk_tipp_ist_aktuell( $b->ID ) <=> (int) afk_tipp_ist_aktuell( $a->ID );
		}
	);
	$out = '<div class="afk-tipps">';
	foreach ( array_slice( $tipps, 0, max( 1, (int) $atts['anzahl'] ) ) as $p ) {
		$akt  = afk_tipp_ist_aktuell( $p->ID );
		$out .= '<a class="afk-tipp' . ( $akt ? ' afk-tipp--aktuell' : '' ) . '" href="' . esc_url( get_permalink( $p ) ) . '">';
		$out .= $akt ? '<span class="afk-tipp__badge">Jetzt aktuell</span>' : '';
		$out .= '<span class="afk-tipp__titel">' . esc_html( get_the_title( $p ) ) . '</span>';
		$out .= '<span class="afk-tipp__text">' . esc_html( get_the_excerpt( $p ) ) . '</span></a>';
	}
	return $out . '</div>';
}
add_shortcode( 'afk_tipps', 'afk_sc_tipps' );
