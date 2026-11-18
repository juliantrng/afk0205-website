<?php
/**
 * ACF-Anbindung: Feldgruppen aus acf-json/ laden,
 * Hinweis im Backend, falls ACF fehlt.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Feldgruppen aus dem Plugin-Ordner laden (Local JSON).
 */
function afk_acf_load_json( $paths ) {
	$paths[] = AFK_CORE_DIR . 'acf-json';
	return $paths;
}
add_filter( 'acf/settings/load_json', 'afk_acf_load_json' );

/**
 * Backend-Hinweis, wenn ACF fehlt (die kostenlose Version reicht).
 */
function afk_acf_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'ACF' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>AFK Core:</strong> Bitte das kostenlose Plugin <em>Advanced Custom Fields</em> installieren und aktivieren. Ohne ACF fehlen die Eingabefelder für Verfasser, Bildquelle, Feuerwehr-Profil und Sicherheitstipps; Taxonomie „Feuerwehr“ und Hinweisband funktionieren trotzdem.</p></div>';
}
add_action( 'admin_notices', 'afk_acf_admin_notice' );

/**
 * Hilfsfunktion: ACF-Wert lesen, mit Fallback auf Metadaten, falls ACF fehlt.
 *
 * @param string     $name    Feldname.
 * @param int|string $context Post-ID, 'term_<id>' oder 'option'.
 * @return mixed
 */
function afk_field( $name, $context = null ) {
	if ( function_exists( 'get_field' ) ) {
		return get_field( $name, $context );
	}
	if ( is_string( $context ) && 0 === strpos( $context, 'term_' ) ) {
		return get_term_meta( (int) substr( $context, 5 ), $name, true );
	}
	if ( 'option' === $context || 'options' === $context ) {
		return get_option( 'options_' . $name );
	}
	return get_post_meta( $context ? $context : get_the_ID(), $name, true );
}
