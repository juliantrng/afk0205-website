<?php
/**
 * AFK Nachtschicht – Theme-Funktionen.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

define( 'AFK_NS_VERSION', '1.2.5' );
define( 'AFK_NS_DIR', get_template_directory() );
define( 'AFK_NS_URL', get_template_directory_uri() );

require_once AFK_NS_DIR . '/inc/helpers.php';
require_once AFK_NS_DIR . '/inc/customizer.php';
require_once AFK_NS_DIR . '/inc/query.php';
require_once AFK_NS_DIR . '/inc/meta.php';
require_once AFK_NS_DIR . '/inc/github-updater.php';

new AFK_GitHub_Updater(
	array(
		'typ'     => 'theme',
		'slug'    => 'afk-nachtschicht',
		'datei'   => 'afk-nachtschicht',
		'version' => AFK_NS_VERSION,
		'name'    => 'AFK Nachtschicht',
	)
);

/**
 * Grundeinstellungen.
 */
function afk_ns_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 440, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'align-wide' );
	add_editor_style( 'assets/editor.css' );

	add_image_size( 'afk-hero', 2000, 1200, false );
	add_image_size( 'afk-tile', 900, 640, true );

	register_nav_menus(
		array(
			'primary' => 'Hauptmenü',
			'footer'  => 'Fußzeile',
		)
	);
}
add_action( 'after_setup_theme', 'afk_ns_setup' );

/**
 * Inhaltsbreite für eingebettete Medien.
 */
function afk_ns_content_width() {
	$GLOBALS['content_width'] = 860;
}
add_action( 'after_setup_theme', 'afk_ns_content_width', 0 );

/**
 * Stylesheet und Skript (nach AFK Core, damit das Theme dessen Farben überschreibt).
 */
function afk_ns_assets() {
	wp_enqueue_style( 'afk-ns', get_stylesheet_uri(), array(), AFK_NS_VERSION );
	wp_enqueue_script( 'afk-ns', AFK_NS_URL . '/assets/theme.js', array(), AFK_NS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'afk_ns_assets', 20 );

/**
 * Schriften vorladen (lokal), JS-Klasse, Theme-Farbe.
 */
function afk_ns_head() {
	foreach ( array( 'oswald.woff2', 'source-sans-3.woff2' ) as $f ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( AFK_NS_URL . '/assets/fonts/' . $f ) );
	}
	echo "<script>document.documentElement.className+=' js';</script>\n";
	echo '<meta name="theme-color" content="#0E0F11">' . "\n";
}
add_action( 'wp_head', 'afk_ns_head', 1 );

/**
 * Kein „Generator“-Hinweis auf die WordPress-Version.
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Hinweis im Backend, wenn AFK Core fehlt.
 */
function afk_ns_admin_notice() {
	if ( current_user_can( 'activate_plugins' ) && ! function_exists( 'afk_get_feuerwehren' ) ) {
		echo '<div class="notice notice-warning"><p><strong>AFK Nachtschicht:</strong> Bitte das Plugin <em>AFK Core</em> aktivieren – ohne es fehlen Feuerwehr-Seiten, Aufmacher, Hinweisband, Alarmierungen und Sicherheitstipps.</p></div>';
	}
}
add_action( 'admin_notices', 'afk_ns_admin_notice' );

/**
 * Textauszug kürzer und ohne „[…]“.
 */
add_filter( 'excerpt_length', fn() => 32 );
add_filter( 'excerpt_more', fn() => ' …' );

/**
 * Seitenvorlagen in der Titelzeile erkennbar machen.
 *
 * @param array $classes Klassen.
 * @return array
 */
function afk_ns_body_class( $classes ) {
	if ( is_singular() && has_post_thumbnail() ) {
		$classes[] = 'has-hero';
	}
	return $classes;
}
add_filter( 'body_class', 'afk_ns_body_class' );
