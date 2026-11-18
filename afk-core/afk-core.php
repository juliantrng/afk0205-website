<?php
/**
 * Plugin Name:       AFK Core
 * Description:       Datenstruktur und Bausteine für die Website des Abschnittsfeuerwehrkommandos Millstatt – Radenthein: Taxonomie „Feuerwehr“, ACF-Felder, Aufmacher, Hinweisband, Sicherheitstipps, Alarmierungen (KLFV), Datenschutz-Helfer.
 * Version:           0.5.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            AFK Millstatt – Radenthein
 * License:           GPL-2.0-or-later
 * Text Domain:       afk-core
 * Update URI:        https://github.com/juliantrng/afk0205-website
 */

defined( 'ABSPATH' ) || exit;

define( 'AFK_CORE_VERSION', '0.5.0' );
define( 'AFK_CORE_FILE', __FILE__ );
define( 'AFK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'AFK_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once AFK_CORE_DIR . 'includes/taxonomy.php';
require_once AFK_CORE_DIR . 'includes/tipps.php';
require_once AFK_CORE_DIR . 'includes/acf.php';
require_once AFK_CORE_DIR . 'includes/hinweis-admin.php';
require_once AFK_CORE_DIR . 'includes/hinweisband.php';
require_once AFK_CORE_DIR . 'includes/shortcodes.php';
require_once AFK_CORE_DIR . 'includes/aufmacher.php';
require_once AFK_CORE_DIR . 'includes/datenschutz.php';
require_once AFK_CORE_DIR . 'includes/alarmierungen.php';
require_once AFK_CORE_DIR . 'includes/github-updater.php';

new AFK_GitHub_Updater(
	array(
		'typ'     => 'plugin',
		'slug'    => 'afk-core',
		'datei'   => plugin_basename( __FILE__ ),
		'version' => AFK_CORE_VERSION,
		'name'    => 'AFK Core',
	)
);

/**
 * Aktivierung: Taxonomie und Beitragstyp registrieren, Feuerwehren anlegen,
 * Permalinks neu aufbauen.
 */
function afk_core_activate() {
	afk_register_taxonomy();
	afk_register_tipps();
	afk_seed_feuerwehren();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'afk_core_activate' );

/**
 * Deaktivierung: Permalinks neu aufbauen, geplanten Abruf entfernen.
 */
function afk_core_deactivate() {
	wp_clear_scheduled_hook( 'afk_alarm_abruf' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'afk_core_deactivate' );

/**
 * Stylesheet mit den Bausteinen (für Shortcodes und Hinweisband).
 */
function afk_core_enqueue_assets() {
	wp_enqueue_style( 'afk-core', AFK_CORE_URL . 'assets/afk.css', array(), AFK_CORE_VERSION );
}
add_action( 'wp_enqueue_scripts', 'afk_core_enqueue_assets' );
