<?php
/**
 * Anmeldeseite im Stil der Website (ersetzt LoginPress).
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stile laden.
 */
function afk_ns_login_stil() {
	wp_enqueue_style( 'afk-ns-login', AFK_NS_URL . '/assets/login.css', array( 'login' ), AFK_NS_VERSION );
}
add_action( 'login_enqueue_scripts', 'afk_ns_login_stil', 20 );

/**
 * Logo führt zur Website statt zu wordpress.org.
 *
 * @return string
 */
function afk_ns_login_link() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'afk_ns_login_link' );

/**
 * Text hinter dem Logo (für Screenreader).
 *
 * @return string
 */
function afk_ns_login_text() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'afk_ns_login_text' );
