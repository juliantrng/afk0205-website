<?php
/**
 * Filter (?kat, ?ff, ?jahr) auf Übersichten und Feuerwehr-Seiten anwenden.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hauptabfrage anpassen.
 *
 * @param WP_Query $q Abfrage.
 */
function afk_ns_pre_get_posts( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_search() ) {
		$q->set( 'post_type', array( 'post', 'page', 'afk_tipp', 'afk_termin' ) );
		$q->set( 'posts_per_page', 12 );
		return;
	}
	if ( $q->is_post_type_archive( 'afk_tipp' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
		$q->set( 'posts_per_page', 24 );
		return;
	}
	if ( ! ( $q->is_home() || $q->is_category() || $q->is_tax( 'feuerwehr' ) || $q->is_date() || $q->is_tag() ) ) {
		return;
	}
	$q->set( 'posts_per_page', 12 );
	$f = afk_ns_filter_werte();
	if ( ! $f['aktiv'] ) {
		return;
	}
	$args = afk_ns_filter_query_args( $f );
	$tq   = (array) $q->get( 'tax_query' );
	if ( isset( $args['category_name'] ) ) {
		if ( $q->is_category() ) {
			$tq[] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $args['category_name'] );
		} else {
			$q->set( 'category_name', $args['category_name'] );
		}
	}
	if ( isset( $args['tax_query'] ) ) {
		$tq = array_merge( $tq, $args['tax_query'] );
	}
	if ( $tq ) {
		$q->set( 'tax_query', $tq );
	}
	if ( isset( $args['year'] ) ) {
		$q->set( 'year', $args['year'] );
	}
}
add_action( 'pre_get_posts', 'afk_ns_pre_get_posts' );
