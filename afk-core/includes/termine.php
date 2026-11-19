<?php
/**
 * Termine: eigener Beitragstyp statt eines Kalender-Plugins.
 *
 * - Beitragstyp „afk_termin“ (Beginn, Ende, ganztägig, Ort, Art, Link – ACF-Gruppe group_afk_termin)
 * - Veranstaltende Feuerwehr über die Taxonomie „Feuerwehr“
 * - Kalenderdatei (.ics) je Termin und als Abo für alle Termine: /?afk_ics=alle
 * - Werkzeuge → „Termine übernehmen“: übernimmt Termine aus Modern Events Calendar
 *
 * Für die Abfragen werden Beginn/Ende zusätzlich als Zeitstempel gespeichert
 * (_afk_termin_start, _afk_termin_ende), unabhängig vom Anzeigeformat.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Beitragstyp registrieren.
 */
function afk_register_termine() {
	register_post_type(
		'afk_termin',
		array(
			'labels'        => array(
				'name'               => 'Termine',
				'singular_name'      => 'Termin',
				'menu_name'          => 'Termine',
				'add_new'            => 'Neuer Termin',
				'add_new_item'       => 'Neuen Termin anlegen',
				'edit_item'          => 'Termin bearbeiten',
				'view_item'          => 'Termin ansehen',
				'all_items'          => 'Alle Termine',
				'search_items'       => 'Termine durchsuchen',
				'not_found'          => 'Keine Termine gefunden.',
				'not_found_in_trash' => 'Keine Termine im Papierkorb.',
			),
			'public'        => true,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 22,
			'has_archive'   => false, // Übersicht über eine Seite mit der Vorlage „Termine“
			'rewrite'       => array(
				'slug'       => 'termin',
				'with_front' => false,
			),
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'taxonomies'    => array( 'feuerwehr' ),
		)
	);
	register_taxonomy_for_object_type( 'feuerwehr', 'afk_termin' );
}
add_action( 'init', 'afk_register_termine', 11 );

/**
 * Nach einem Update mit neuen Adressen (z. B. /termin/…) die Regeln einmal neu einlesen –
 * sonst zeigen neue Seiten „nicht gefunden“, bis jemand die Permalinks speichert.
 */
function afk_rewrite_aktualisieren() {
	if ( get_option( 'afk_rewrite_stand' ) !== AFK_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'afk_rewrite_stand', AFK_CORE_VERSION, true );
	}
}
add_action( 'init', 'afk_rewrite_aktualisieren', 99 );

/**
 * Datumstext aus ACF („Y-m-d H:i:s“ oder „d.m.Y H:i“) in Zeitstempel (Zeitzone der Website).
 *
 * @param string $wert Wert.
 * @return int 0 wenn leer/ungültig.
 */
function afk_termin_zeit_aus_text( $wert ) {
	$wert = trim( (string) $wert );
	if ( '' === $wert ) {
		return 0;
	}
	foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'd.m.Y H:i', 'Y-m-d', 'Ymd' ) as $format ) {
		$d = DateTime::createFromFormat( '!' . $format, $wert, wp_timezone() );
		if ( $d ) {
			return $d->getTimestamp();
		}
	}
	return 0;
}

/**
 * Zeitstempel nach dem Speichern aktualisieren (ACF speichert die Felder vorher).
 *
 * @param int|string $post_id Beitrag.
 */
function afk_termin_zeitstempel( $post_id ) {
	if ( ! is_numeric( $post_id ) || 'afk_termin' !== get_post_type( $post_id ) ) {
		return;
	}
	$start = afk_termin_zeit_aus_text( get_post_meta( $post_id, 'afk_beginn', true ) );
	$ende  = afk_termin_zeit_aus_text( get_post_meta( $post_id, 'afk_ende', true ) );
	if ( $ende && $ende < $start ) {
		$ende = 0;
	}
	update_post_meta( $post_id, '_afk_termin_start', $start );
	// Für „kommende Termine“: bis wann der Termin läuft (Ende, sonst Tagesende bzw. Beginn + 3 h).
	$bis = $ende;
	if ( ! $bis && $start ) {
		$bis = get_post_meta( $post_id, 'afk_ganztaegig', true ) ? strtotime( 'tomorrow', $start ) - 1 : $start + 3 * HOUR_IN_SECONDS;
	}
	update_post_meta( $post_id, '_afk_termin_ende', $bis );
}
add_action( 'acf/save_post', 'afk_termin_zeitstempel', 20 );
add_action( 'save_post_afk_termin', 'afk_termin_zeitstempel', 20 );

/**
 * Alle Angaben eines Termins.
 *
 * @param int $id Termin.
 * @return array start, ende (0 = keins), bis, ganztaegig, ort, art, art_text, link, mehrtaegig
 */
function afk_termin_daten( $id ) {
	$start = (int) get_post_meta( $id, '_afk_termin_start', true );
	if ( ! $start ) {
		$start = afk_termin_zeit_aus_text( get_post_meta( $id, 'afk_beginn', true ) );
	}
	$ende  = afk_termin_zeit_aus_text( get_post_meta( $id, 'afk_ende', true ) );
	$arten = afk_termin_arten();
	$art   = (string) get_post_meta( $id, 'afk_art', true );
	return array(
		'start'      => $start,
		'ende'       => $ende > $start ? $ende : 0,
		'bis'        => (int) get_post_meta( $id, '_afk_termin_ende', true ),
		'ganztaegig' => (bool) get_post_meta( $id, 'afk_ganztaegig', true ),
		'ort'        => (string) get_post_meta( $id, 'afk_ort', true ),
		'art'        => $art,
		'art_text'   => $arten[ $art ] ?? '',
		'link'       => (string) get_post_meta( $id, 'afk_link', true ),
		'mehrtaegig' => $ende > $start && wp_date( 'Ymd', $ende ) !== wp_date( 'Ymd', $start ),
	);
}

/**
 * Arten von Terminen (Schlüssel => Anzeige).
 *
 * @return array
 */
function afk_termin_arten() {
	return array(
		'veranstaltung' => 'Veranstaltung',
		'uebung'        => 'Öffentliche Übung',
		'bewerb'        => 'Bewerb',
		'versammlung'   => 'Versammlung',
		'ausbildung'    => 'Ausbildung',
		'sonstiges'     => 'Termin',
	);
}

/**
 * Lesbare Zeitangabe, z. B. „Sa, 12. Dezember 2026 · 19:00–22:00 Uhr“.
 *
 * @param int  $id   Termin.
 * @param bool $kurz Ohne Wochentag und Jahr (für Listen mit Datumsblock).
 * @return string
 */
function afk_termin_zeit_text( $id, $kurz = false ) {
	$d = afk_termin_daten( $id );
	if ( ! $d['start'] ) {
		return '';
	}
	$datum = $kurz ? '' : wp_date( 'D, j. F Y', $d['start'] );
	if ( $d['mehrtaegig'] ) {
		$von   = wp_date( $kurz ? 'j. M' : 'D, j. F', $d['start'] );
		$bis   = wp_date( $kurz ? 'j. M' : 'D, j. F Y', $d['ende'] );
		$datum = $von . ' – ' . $bis;
		if ( $d['ganztaegig'] ) {
			return $datum;
		}
		return $datum . ' · ab ' . wp_date( 'H:i', $d['start'] ) . ' Uhr';
	}
	if ( $d['ganztaegig'] ) {
		return trim( $datum . ( $datum ? ' · ' : '' ) . 'ganztägig' );
	}
	$zeit = wp_date( 'H:i', $d['start'] ) . ( $d['ende'] ? '–' . wp_date( 'H:i', $d['ende'] ) : '' ) . ' Uhr';
	return trim( $datum . ( $datum ? ' · ' : '' ) . $zeit );
}

/**
 * Termine abfragen.
 *
 * @param array $args anzahl (int, -1 = alle), zeitraum ('kommend'|'vergangen'), feuerwehr (Slug, auch Gemeinde), seite (int).
 * @return WP_Query
 */
function afk_termine_query( $args = array() ) {
	$args  = wp_parse_args(
		$args,
		array(
			'anzahl'    => 10,
			'zeitraum'  => 'kommend',
			'feuerwehr' => '',
			'seite'     => 1,
		)
	);
	$jetzt = time();
	$q     = array(
		'post_type'           => 'afk_termin',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $args['anzahl'],
		'paged'               => max( 1, (int) $args['seite'] ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => (int) $args['anzahl'] < 0,
		'meta_key'            => '_afk_termin_start', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'             => 'meta_value_num',
		'order'               => 'vergangen' === $args['zeitraum'] ? 'DESC' : 'ASC',
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_afk_termin_ende',
				'value'   => $jetzt,
				'compare' => 'vergangen' === $args['zeitraum'] ? '<' : '>=',
				'type'    => 'NUMERIC',
			),
		),
	);
	if ( $args['feuerwehr'] ) {
		$q['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy'         => 'feuerwehr',
				'field'            => 'slug',
				'terms'            => sanitize_title( $args['feuerwehr'] ),
				'include_children' => true,
			),
		);
	}
	return new WP_Query( $q );
}

/**
 * Kurzform: IDs der nächsten Termine.
 *
 * @param int    $anzahl    Anzahl.
 * @param string $feuerwehr Slug (optional).
 * @return int[]
 */
function afk_termine_naechste( $anzahl = 3, $feuerwehr = '' ) {
	return wp_list_pluck( afk_termine_query( array( 'anzahl' => $anzahl, 'feuerwehr' => $feuerwehr ) )->posts, 'ID' );
}

/**
 * Adresse der Kalenderdatei.
 *
 * @param int|string $was Termin-ID oder 'alle'.
 * @return string
 */
function afk_termin_ics_url( $was = 'alle' ) {
	return add_query_arg( 'afk_ics', is_numeric( $was ) ? (int) $was : 'alle', home_url( '/' ) );
}

/**
 * Text für iCalendar maskieren.
 *
 * @param string $t Text.
 * @return string
 */
function afk_ics_text( $t ) {
	$t = trim( wp_strip_all_tags( html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) ) );
	$t = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $t );
	return preg_replace( '/\r\n|\r|\n/', '\\n', $t );
}

/**
 * Zeile nach RFC 5545 auf 75 Bytes falten.
 *
 * @param string $zeile Zeile.
 * @return string
 */
function afk_ics_falten( $zeile ) {
	$out = '';
	while ( strlen( $zeile ) > 75 ) {
		$teil = mb_strcut( $zeile, 0, 75, 'UTF-8' );
		$out .= $teil . "\r\n ";
		$zeile = substr( $zeile, strlen( $teil ) );
	}
	return $out . $zeile . "\r\n";
}

/**
 * Ein VEVENT.
 *
 * @param int $id Termin.
 * @return string
 */
function afk_ics_event( $id ) {
	$d = afk_termin_daten( $id );
	if ( ! $d['start'] ) {
		return '';
	}
	$z   = array();
	$z[] = 'BEGIN:VEVENT';
	$z[] = 'UID:termin-' . $id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
	$z[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', (int) get_post_modified_time( 'U', true, $id ) );
	if ( $d['ganztaegig'] ) {
		$ende_tag = $d['ende'] ? $d['ende'] : $d['start'];
		$z[]      = 'DTSTART;VALUE=DATE:' . wp_date( 'Ymd', $d['start'] );
		$z[]      = 'DTEND;VALUE=DATE:' . wp_date( 'Ymd', strtotime( '+1 day', $ende_tag ) );
	} else {
		$z[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $d['start'] );
		$z[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', $d['ende'] ? $d['ende'] : $d['start'] + 2 * HOUR_IN_SECONDS );
	}
	$z[] = 'SUMMARY:' . afk_ics_text( get_the_title( $id ) );
	if ( $d['ort'] ) {
		$z[] = 'LOCATION:' . afk_ics_text( $d['ort'] );
	}
	$text = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_trim_words( get_post_field( 'post_content', $id ), 60 );
	if ( $text ) {
		$z[] = 'DESCRIPTION:' . afk_ics_text( $text );
	}
	$z[] = 'URL:' . get_permalink( $id );
	$z[] = 'END:VEVENT';
	return implode( '', array_map( 'afk_ics_falten', $z ) );
}

/**
 * Kalenderdatei ausliefern: ?afk_ics=123 oder ?afk_ics=alle (Abo, ab 30 Tage zurück).
 */
function afk_ics_ausliefern() {
	if ( ! isset( $_GET['afk_ics'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$was = sanitize_key( wp_unslash( $_GET['afk_ics'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$ids = array();
	if ( 'alle' === $was ) {
		$q   = new WP_Query(
			array(
				'post_type'      => 'afk_termin',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_afk_termin_ende',
						'value'   => time() - 30 * DAY_IN_SECONDS,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$ids = $q->posts;
		$name = 'termine';
	} elseif ( ctype_digit( $was ) && 'afk_termin' === get_post_type( (int) $was ) && 'publish' === get_post_status( (int) $was ) ) {
		$ids  = array( (int) $was );
		$name = sanitize_title( get_the_title( (int) $was ) );
	} else {
		status_header( 404 );
		exit;
	}
	$out  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//AFK Millstatt-Radenthein//Termine//DE\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
	$out .= afk_ics_falten( 'X-WR-CALNAME:' . afk_ics_text( get_bloginfo( 'name' ) . ' – Termine' ) );
	$out .= "X-WR-TIMEZONE:Europe/Vienna\r\n";
	foreach ( $ids as $id ) {
		$out .= afk_ics_event( $id );
	}
	$out .= "END:VCALENDAR\r\n";
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: ' . ( 'alle' === $was ? 'inline' : 'attachment' ) . '; filename="' . $name . '.ics"' );
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- iCalendar, oben maskiert
	exit;
}
add_action( 'template_redirect', 'afk_ics_ausliefern', 1 );

/* ---------- Backend: Spalten und Sortierung ---------- */

/**
 * Spalten der Terminliste.
 *
 * @param array $c Spalten.
 * @return array
 */
function afk_termin_spalten( $c ) {
	$neu = array();
	foreach ( $c as $k => $v ) {
		$neu[ $k ] = $v;
		if ( 'title' === $k ) {
			$neu['afk_wann'] = 'Wann';
			$neu['afk_ort']  = 'Ort';
		}
	}
	unset( $neu['date'] );
	return $neu;
}
add_filter( 'manage_afk_termin_posts_columns', 'afk_termin_spalten' );

/**
 * Inhalt der Spalten.
 *
 * @param string $spalte Spalte.
 * @param int    $id     Termin.
 */
function afk_termin_spalte_inhalt( $spalte, $id ) {
	if ( 'afk_wann' === $spalte ) {
		$d = afk_termin_daten( $id );
		echo esc_html( $d['start'] ? afk_termin_zeit_text( $id ) : '—' );
		if ( $d['bis'] && $d['bis'] < time() ) {
			echo ' <span style="color:#787c82">(vorbei)</span>';
		}
	} elseif ( 'afk_ort' === $spalte ) {
		echo esc_html( (string) get_post_meta( $id, 'afk_ort', true ) );
	}
}
add_action( 'manage_afk_termin_posts_custom_column', 'afk_termin_spalte_inhalt', 10, 2 );

/**
 * Spalte „Wann“ sortierbar, Standard: neueste zuerst.
 *
 * @param array $c Sortierbare Spalten.
 * @return array
 */
function afk_termin_sortierbar( $c ) {
	$c['afk_wann'] = 'afk_wann';
	return $c;
}
add_filter( 'manage_edit-afk_termin_sortable_columns', 'afk_termin_sortierbar' );

/**
 * Sortierung der Backend-Liste nach Beginn.
 *
 * @param WP_Query $q Abfrage.
 */
function afk_termin_admin_sortierung( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'afk_termin' !== $q->get( 'post_type' ) ) {
		return;
	}
	$orderby = $q->get( 'orderby' );
	if ( ! $orderby || 'afk_wann' === $orderby ) {
		$q->set( 'meta_key', '_afk_termin_start' );
		$q->set( 'orderby', 'meta_value_num' );
		if ( ! $q->get( 'order' ) || ! isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$q->set( 'order', 'DESC' );
		}
	}
}
add_action( 'pre_get_posts', 'afk_termin_admin_sortierung' );

/* ---------- Übernahme aus Modern Events Calendar ---------- */

/**
 * Menüpunkt Werkzeuge → Termine übernehmen (nur, wenn es MEC-Termine gibt).
 */
function afk_mec_menue() {
	global $wpdb;
	$anzahl = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='mec-events' AND post_status IN ('publish','future','draft','private')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $anzahl ) {
		add_management_page( 'Termine übernehmen', 'Termine übernehmen', 'manage_options', 'afk-termine-uebernehmen', 'afk_mec_seite' );
	}
}
add_action( 'admin_menu', 'afk_mec_menue' );

/**
 * Zeitstempel aus MEC-Metadaten.
 *
 * @param int    $id    MEC-Termin.
 * @param string $teil  'start' oder 'end'.
 * @return int
 */
function afk_mec_zeit( $id, $teil ) {
	$datum = (string) get_post_meta( $id, 'mec_' . $teil . '_date', true );
	if ( '' === $datum ) {
		return 0;
	}
	$sek = get_post_meta( $id, 'mec_' . $teil . '_day_seconds', true );
	if ( '' === $sek || null === $sek ) {
		$h   = (int) get_post_meta( $id, 'mec_' . $teil . '_time_hour', true );
		$m   = (int) get_post_meta( $id, 'mec_' . $teil . '_time_minutes', true );
		$ap  = strtoupper( (string) get_post_meta( $id, 'mec_' . $teil . '_time_ampm', true ) );
		if ( 'PM' === $ap && $h < 12 ) {
			$h += 12;
		} elseif ( 'AM' === $ap && 12 === $h ) {
			$h = 0;
		}
		$sek = $h * 3600 + $m * 60;
	}
	$tag = DateTime::createFromFormat( '!Y-m-d', $datum, wp_timezone() );
	return $tag ? $tag->getTimestamp() + (int) $sek : 0;
}

/**
 * Übernahme durchführen.
 *
 * @return array angelegt, uebersprungen
 */
function afk_mec_uebernehmen() {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='mec-events' AND post_status IN ('publish','future','draft','private') ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$neu = 0;
	$alt = 0;
	foreach ( $ids as $mec_id ) {
		$mec_id = (int) $mec_id;
		if ( get_post_meta( $mec_id, '_afk_termin_id', true ) ) {
			++$alt;
			continue;
		}
		$p     = get_post( $mec_id );
		$start = afk_mec_zeit( $mec_id, 'start' );
		$ende  = afk_mec_zeit( $mec_id, 'end' );
		$ganz  = (bool) get_post_meta( $mec_id, 'mec_allday', true );
		$ort   = '';
		$loc   = (int) get_post_meta( $mec_id, 'mec_location_id', true );
		if ( $loc > 1 ) {
			$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", $loc ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$adr  = get_term_meta( $loc, 'address', true );
			if ( preg_match( '/^(Name des Veranstaltungsorts|Venue Name|Location Name)$/i', trim( (string) $name ) ) ) {
				$name = ''; // Platzhalter aus MEC
			}
			$ort = trim( (string) $name . ( $adr && false === strpos( (string) $name, (string) $adr ) ? ', ' . $adr : '' ), ', ' );
		}
		$neu_id = wp_insert_post(
			array(
				'post_type'    => 'afk_termin',
				'post_status'  => $p->post_status,
				'post_title'   => $p->post_title,
				'post_content' => $p->post_content,
				'post_excerpt' => $p->post_excerpt,
				'post_author'  => $p->post_author,
				'post_date'    => $p->post_date,
			),
			true
		);
		if ( is_wp_error( $neu_id ) ) {
			continue;
		}
		$fmt = 'Y-m-d H:i:s';
		update_post_meta( $neu_id, 'afk_beginn', $start ? wp_date( $fmt, $start ) : '' );
		update_post_meta( $neu_id, '_afk_beginn', 'field_afk_termin_beginn' );
		update_post_meta( $neu_id, 'afk_ende', $ende && $ende > $start ? wp_date( $fmt, $ende ) : '' );
		update_post_meta( $neu_id, '_afk_ende', 'field_afk_termin_ende' );
		update_post_meta( $neu_id, 'afk_ganztaegig', $ganz ? 1 : 0 );
		update_post_meta( $neu_id, '_afk_ganztaegig', 'field_afk_termin_ganztaegig' );
		update_post_meta( $neu_id, 'afk_ort', $ort );
		update_post_meta( $neu_id, '_afk_ort', 'field_afk_termin_ort' );
		$art = preg_match( '/bewerb|meisterschaft|wettbewerb/i', $p->post_title ) ? 'bewerb' : ( preg_match( '/übung|uebung/i', $p->post_title ) ? 'uebung' : ( preg_match( '/versammlung|sitzung/i', $p->post_title ) ? 'versammlung' : 'veranstaltung' ) );
		update_post_meta( $neu_id, 'afk_art', $art );
		update_post_meta( $neu_id, '_afk_art', 'field_afk_termin_art' );
		$link = (string) get_post_meta( $mec_id, 'mec_read_more', true );
		if ( '' === $link ) {
			$link = (string) get_post_meta( $mec_id, 'mec_more_info', true );
		}
		update_post_meta( $neu_id, 'afk_link', esc_url_raw( $link ) );
		update_post_meta( $neu_id, '_afk_link', 'field_afk_termin_link' );
		$bild = (int) get_post_thumbnail_id( $mec_id );
		if ( $bild ) {
			set_post_thumbnail( $neu_id, $bild );
		}
		// Feuerwehr aus dem Titel erkennen (z. B. „Jubiläumsfest der FF Obermillstatt“).
		$wehren = array();
		foreach ( get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false, 'childless' => true ) ) as $t ) {
			$kern = trim( preg_replace( '/^(FF|BtF)\s+/i', '', $t->name ) );
			if ( $kern && preg_match( '/(FF|Feuerwehr)\s+' . preg_quote( $kern, '/' ) . '(?![a-zäöü])/iu', $p->post_title . ' ' . $p->post_content ) ) {
				$wehren[] = (int) $t->term_id;
			}
		}
		if ( $wehren ) {
			wp_set_object_terms( $neu_id, $wehren, 'feuerwehr' );
		}
		afk_termin_zeitstempel( $neu_id );
		update_post_meta( $neu_id, '_afk_mec_id', $mec_id );
		update_post_meta( $mec_id, '_afk_termin_id', $neu_id );
		++$neu;
	}
	return array( 'angelegt' => $neu, 'uebersprungen' => $alt );
}

/**
 * Seite Werkzeuge → Termine übernehmen.
 */
function afk_mec_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ergebnis = null;
	if ( isset( $_POST['afk_mec_start'] ) && check_admin_referer( 'afk_mec' ) ) {
		$ergebnis = afk_mec_uebernehmen();
	}
	global $wpdb;
	$gesamt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='mec-events' AND post_status IN ('publish','future','draft','private')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$fertig = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_afk_termin_id' WHERE p.post_type='mec-events'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	?>
	<div class="wrap">
		<h1>Termine übernehmen</h1>
		<?php if ( $ergebnis ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf( '%d Termine übernommen, %d waren schon übernommen.', $ergebnis['angelegt'], $ergebnis['uebersprungen'] ) ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=afk_termin' ) ); ?>">Zu den Terminen</a></p></div>
		<?php endif; ?>
		<p>Übernimmt die Termine aus <strong>Modern Events Calendar</strong> in die eigenen Termine der Website: Titel, Text, Bild, Beginn, Ende, ganztägig, Ort und Link. Die veranstaltende Feuerwehr wird aus dem Titel erkannt, wenn er sie nennt (z. B. „Jubiläumsfest der FF Obermillstatt“).</p>
		<p>Die Termine im Kalender-Plugin bleiben unverändert. Mehrfach ausführen ist unschädlich – bereits übernommene werden übersprungen. Wiederkehrende Termine werden mit dem ersten Datum übernommen.</p>
		<p><strong><?php echo esc_html( $fertig . ' von ' . $gesamt ); ?></strong> Terminen sind übernommen.</p>
		<form method="post">
			<?php wp_nonce_field( 'afk_mec' ); ?>
			<p><button class="button button-primary" name="afk_mec_start" value="1">Termine jetzt übernehmen</button></p>
		</form>
		<p>Danach kann <em>Modern Events Calendar</em> deaktiviert werden. Die Seite „Termine“ bekommt die Vorlage <strong>Termine</strong> (rechts im Editor unter „Vorlage“).</p>
	</div>
	<?php
}

/* ---------- Shortcode für andere Themes ---------- */

/**
 * [afk_termine anzahl="5" feuerwehr="ff-millstatt"]
 *
 * @param array $atts Attribute.
 * @return string
 */
function afk_sc_termine( $atts ) {
	$a   = shortcode_atts( array( 'anzahl' => 5, 'feuerwehr' => '' ), $atts );
	$ids = afk_termine_naechste( (int) $a['anzahl'], $a['feuerwehr'] );
	if ( ! $ids ) {
		return '<p class="afk-termine afk-termine--leer">Derzeit sind keine Termine eingetragen.</p>';
	}
	$out = '<ul class="afk-termine">';
	foreach ( $ids as $id ) {
		$d    = afk_termin_daten( $id );
		$out .= '<li><strong>' . esc_html( wp_date( 'd.m.Y', $d['start'] ) ) . '</strong> <a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' . ( $d['ort'] ? ' – ' . esc_html( $d['ort'] ) : '' ) . '</li>';
	}
	return $out . '</ul>';
}
add_shortcode( 'afk_termine', 'afk_sc_termine' );
