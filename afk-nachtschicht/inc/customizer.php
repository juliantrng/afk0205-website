<?php
/**
 * Customizer: Design → Customizer → „AFK Einstellungen“.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Einstellungen registrieren.
 *
 * @param WP_Customize_Manager $c Customizer.
 */
function afk_ns_customize( $c ) {
	$c->add_section( 'afk_ns', array( 'title' => 'AFK Einstellungen', 'priority' => 30 ) );

	$felder = array(
		'afk_mitglieder'    => array( 'Mitglieder im Abschnitt (Kennzahl)', 'Leer lassen = Summe aus den Feuerwehr-Profilen. Ist auch die leer, wird die Kennzahl ausgeblendet.', 'text', '' ),
		'afk_mitmachen_url' => array( 'Link „Werde Teil davon“', 'Seite mit Infos zum Mitmachen. Leer = Übersicht der Feuerwehren.', 'url', '' ),
		'afk_telegram'      => array( 'Telegram-Kanal (Link)', '', 'url', '' ),
		'afk_email'         => array( 'Kontakt-E-Mail (Fußzeile)', '', 'email', 'info@afk-millstatt-radenthein.at' ),
		'afk_og_bild'       => array( 'Vorschaubild beim Teilen (Adresse)', 'Wird verwendet, wenn eine Seite kein eigenes Bild hat. Querformat, ca. 1200 × 630 px.', 'url', '' ),
		'afk_kat_einsatz'   => array( 'Kategorie-Slug Einsätze', 'Nur ändern, wenn die Kategorie anders heißt.', 'text', 'einsaetze' ),
		'afk_kat_uebung'    => array( 'Kategorie-Slug Übungen', '', 'text', 'uebungen' ),
		'afk_kat_allgemein' => array( 'Kategorie-Slug Allgemein', '', 'text', 'allgemein' ),
		'afk_kat_alle'      => array( 'Kategorie-Slug „Alle Berichte“', 'Übergeordnete Kategorie für den Link „Alle Berichte“.', 'text', 'news' ),
	);

	foreach ( $felder as $id => $f ) {
		$sanitize = 'sanitize_text_field';
		if ( 'url' === $f[2] ) {
			$sanitize = 'esc_url_raw';
		} elseif ( 'email' === $f[2] ) {
			$sanitize = 'sanitize_email';
		} elseif ( 0 === strpos( $id, 'afk_kat_' ) ) {
			$sanitize = 'sanitize_title';
		}
		$c->add_setting( $id, array( 'default' => $f[3], 'sanitize_callback' => $sanitize ) );
		$c->add_control( $id, array( 'label' => $f[0], 'description' => $f[1], 'section' => 'afk_ns', 'type' => $f[2] ) );
	}

	$c->add_setting( 'afk_zeige_tipps', array( 'default' => true, 'sanitize_callback' => 'wp_validate_boolean' ) );
	$c->add_control( 'afk_zeige_tipps', array( 'label' => 'Bereich „Sicher durchs Jahr“ anzeigen', 'section' => 'afk_ns', 'type' => 'checkbox' ) );
}
add_action( 'customize_register', 'afk_ns_customize' );

/**
 * Mitgliederzahl: Customizer oder Summe aus den Profilen.
 *
 * @return string Leer, wenn unbekannt.
 */
function afk_ns_mitglieder() {
	$wert = trim( (string) get_theme_mod( 'afk_mitglieder', '' ) );
	if ( '' !== $wert ) {
		return $wert;
	}
	$summe = 0;
	foreach ( afk_ns_wehren() as $w ) {
		$summe += (int) afk_ns_field( 'afk_mitglieder', 'term_' . $w['term']->term_id );
	}
	return $summe > 0 ? number_format_i18n( $summe ) : '';
}
