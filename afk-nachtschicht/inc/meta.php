<?php
/**
 * Vorschau beim Teilen (Facebook, WhatsApp, Telegram …): Open-Graph-Angaben.
 * Wird übersprungen, wenn ein SEO-Plugin (Yoast, Rank Math, SEOPress, AIOSEO) das übernimmt.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ist ein SEO-Plugin aktiv?
 *
 * @return bool
 */
function afk_ns_seo_plugin_aktiv() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
}

/**
 * Open-Graph- und Beschreibungs-Tags ausgeben.
 */
function afk_ns_open_graph() {
	if ( afk_ns_seo_plugin_aktiv() ) {
		return;
	}
	$titel = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$url   = home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	$bild  = '';
	$typ   = 'website';

	if ( is_singular() ) {
		$id    = get_queried_object_id();
		$titel = get_the_title( $id );
		$desc  = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $id ) ) ), 30, ' …' );
		$url   = get_permalink( $id );
		$typ   = 'article';
		if ( has_post_thumbnail( $id ) ) {
			$img  = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'large' );
			$bild = $img ? $img[0] : '';
		}
	} elseif ( is_tax( 'feuerwehr' ) ) {
		$t     = get_queried_object();
		$titel = $t->name;
		$desc  = $t->description ? wp_strip_all_tags( $t->description ) : 'Berichte und Infos der ' . $t->name . ' im Abschnitt Millstatt – Radenthein.';
		$bid   = (int) afk_ns_field( 'afk_titelbild', 'term_' . $t->term_id );
		if ( $bid ) {
			$img  = wp_get_attachment_image_src( $bid, 'large' );
			$bild = $img ? $img[0] : '';
		}
	}
	if ( ! $bild ) {
		$bild = get_theme_mod( 'afk_og_bild', '' );
	}

	$tags = array(
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:locale'      => 'de_AT',
		'og:type'        => $typ,
		'og:title'       => $titel,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $bild,
		'twitter:card'   => $bild ? 'summary_large_image' : 'summary',
	);
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
	}
	foreach ( $tags as $k => $v ) {
		if ( '' === (string) $v ) {
			continue;
		}
		$attr = 0 === strpos( $k, 'twitter:' ) ? 'name' : 'property';
		printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attr ), esc_attr( $k ), esc_attr( wp_strip_all_tags( $v ) ) );
	}
}
add_action( 'wp_head', 'afk_ns_open_graph', 5 );
