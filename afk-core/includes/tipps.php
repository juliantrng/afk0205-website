<?php
/**
 * Beitragstyp „Sicherheitstipp“ für den Bereich „Sicher durchs Jahr“.
 * Jeder Tipp hat die Monate, in denen er „Jetzt aktuell“ ist (ACF-Feld afk_monate).
 */

defined( 'ABSPATH' ) || exit;

function afk_register_tipps() {
	register_post_type(
		'afk_tipp',
		array(
			'labels'        => array(
				'name'          => 'Sicherheitstipps',
				'singular_name' => 'Sicherheitstipp',
				'menu_name'     => 'Sicherheitstipps',
				'add_new'       => 'Neuer Tipp',
				'add_new_item'  => 'Neuen Sicherheitstipp anlegen',
				'edit_item'     => 'Sicherheitstipp bearbeiten',
				'all_items'     => 'Alle Sicherheitstipps',
			),
			'public'        => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-shield',
			'menu_position' => 21,
			'has_archive'   => true,
			'rewrite'       => array(
				'slug'       => 'sicherheit',
				'with_front' => false,
			),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
		)
	);
}
add_action( 'init', 'afk_register_tipps' );

/**
 * Ist der Tipp im aktuellen Monat aktuell?
 *
 * @param int $post_id Tipp.
 * @return bool
 */
function afk_tipp_ist_aktuell( $post_id ) {
	$monate = function_exists( 'get_field' ) ? get_field( 'afk_monate', $post_id ) : get_post_meta( $post_id, 'afk_monate', true );
	if ( ! is_array( $monate ) || ! $monate ) {
		return false;
	}
	$jetzt = (int) wp_date( 'n' );
	return in_array( $jetzt, array_map( 'intval', $monate ), true );
}
