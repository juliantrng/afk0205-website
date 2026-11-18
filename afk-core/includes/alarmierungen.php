<?php
/**
 * Alarmierungen des Kärntner Landesfeuerwehrverbands (alarmierungen.feuerwehr-ktn.at)
 * für die Feuerwehren des Abschnitts.
 *
 * Datenschutz-Prinzip:
 *  - Die Website holt die Daten SERVERSEITIG (alle 10 Minuten per WP-Cron) und speichert
 *    eine gekürzte Kopie. Besucher laden nichts von fremden Servern, ihre IP-Adresse
 *    geht nicht an den KLFV.
 *  - Gespeichert und angezeigt werden nur: Zeitpunkt, Ereignis, alarmierte Einheiten,
 *    Bezirk, Status – also nur, was der KLFV selbst öffentlich zeigt (laut dessen
 *    Nutzungshinweisen ohne personenbezogene Daten und ohne genaue Einsatzdetails).
 *  - Nur Alarmierungen mit Beteiligung einer Feuerwehr des Abschnitts, nur die letzten
 *    Tage (Einstellung), ältere werden verworfen.
 *
 * Einstellungen: Einstellungen → Alarmierungen.
 */

defined( 'ABSPATH' ) || exit;

const AFK_ALARM_OPTION = 'afk_alarm_einstellungen';
const AFK_ALARM_DATA   = 'afk_alarm_daten';
const AFK_ALARM_QUELLE = 'https://alarmierungen.feuerwehr-ktn.at/';

/**
 * Einstellungen mit Standardwerten.
 *
 * @return array
 */
function afk_alarm_einstellungen() {
	$d = array(
		'aktiv'   => 0,
		'quelle'  => AFK_ALARM_QUELLE,
		'tage'    => 7,
		'bezirk'  => 'Spittal',
		'zusatz'  => '',
	);
	$s = get_option( AFK_ALARM_OPTION, array() );
	$s = wp_parse_args( is_array( $s ) ? $s : array(), $d );
	if ( '' === trim( (string) $s['quelle'] ) ) {
		$s['quelle'] = AFK_ALARM_QUELLE;
	}
	return $s;
}

/* ---------------------------------------------------------------------
 * Abruf
 * ------------------------------------------------------------------- */

/**
 * Eigenes Cron-Intervall (10 Minuten – aktuell genug, schont den Server des KLFV).
 *
 * @param array $s Intervalle.
 * @return array
 */
function afk_alarm_cron_intervall( $s ) {
	$s['afk_10min'] = array( 'interval' => 10 * MINUTE_IN_SECONDS, 'display' => 'Alle 10 Minuten' );
	return $s;
}
add_filter( 'cron_schedules', 'afk_alarm_cron_intervall' );

/**
 * Cron-Ereignis passend zur Einstellung ein-/ausplanen.
 */
function afk_alarm_cron_pruefen() {
	$e    = afk_alarm_einstellungen();
	$next = wp_next_scheduled( 'afk_alarm_abruf' );
	if ( $next && 'afk_10min' !== wp_get_schedule( 'afk_alarm_abruf' ) ) {
		wp_clear_scheduled_hook( 'afk_alarm_abruf' ); // altes 5-Minuten-Intervall ersetzen
		$next = false;
	}
	if ( $e['aktiv'] && $e['quelle'] && ! $next ) {
		wp_schedule_event( time() + 30, 'afk_10min', 'afk_alarm_abruf' );
	} elseif ( ( ! $e['aktiv'] || ! $e['quelle'] ) && $next ) {
		wp_clear_scheduled_hook( 'afk_alarm_abruf' );
	}
}
add_action( 'init', 'afk_alarm_cron_pruefen' );
add_action( 'afk_alarm_abruf', 'afk_alarm_abrufen' );

/**
 * Namen, unter denen die Wehren des Abschnitts in den Alarmierungen vorkommen.
 *
 * @return array Kleinbuchstaben-Suchbegriff => Term-ID
 */
function afk_alarm_suchnamen() {
	$out = array();
	if ( ! taxonomy_exists( 'feuerwehr' ) ) {
		return $out;
	}
	$terms = get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false ) );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $t ) {
		if ( ! $t->parent ) {
			continue;
		}
		$namen = array( $t->name );
		$extra = (string) get_term_meta( $t->term_id, 'afk_alarmname', true );
		if ( '' !== trim( $extra ) ) {
			$namen = array_merge( $namen, array_map( 'trim', explode( ',', $extra ) ) );
		}
		foreach ( $namen as $n ) {
			$n = afk_alarm_norm( preg_replace( '/\s+–.*$/u', '', $n ) );
			if ( '' !== $n ) {
				$out[ $n ] = (int) $t->term_id;
			}
		}
	}
	$zusatz = afk_alarm_einstellungen()['zusatz'];
	foreach ( array_filter( array_map( 'trim', explode( ',', (string) $zusatz ) ) ) as $n ) {
		$out[ afk_alarm_norm( $n ) ] = 0;
	}
	return $out;
}

/**
 * Namen vergleichbar machen: Kleinbuchstaben, ohne „FF“/„Feuerwehr“, ohne Satzzeichen.
 *
 * @param string $s Name.
 * @return string
 */
function afk_alarm_norm( $s ) {
	$s = mb_strtolower( (string) $s );
	$s = strtr( $s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ) );
	$s = preg_replace( '/\b(freiwillige\s+feuerwehr|betriebsfeuerwehr|feuerwehr|ff|btf|bf)\b/u', ' ', $s );
	$s = preg_replace( '/[^a-z0-9]+/u', ' ', $s );
	return trim( preg_replace( '/\s+/', ' ', $s ) );
}

/**
 * Wert aus einem Datensatz anhand mehrerer möglicher Feldnamen holen.
 *
 * @param array $row   Datensatz.
 * @param array $keys  Mögliche Schlüssel.
 * @return mixed|null
 */
function afk_alarm_feld( $row, $keys ) {
	foreach ( $keys as $k ) {
		foreach ( $row as $rk => $v ) {
			if ( 0 === strcasecmp( (string) $rk, $k ) && null !== $v && '' !== $v ) {
				return $v;
			}
		}
	}
	return null;
}

/**
 * Antwort der Quelle in eine einheitliche Liste umwandeln.
 * Die Feldnamen werden mit gängigen Varianten erkannt; bei Bedarf über den Filter
 * „afk_alarm_normalisieren“ anpassbar.
 *
 * @param mixed $json Dekodierte Antwort.
 * @return array Liste von array( zeit (Unix), ereignis, einheiten[], bezirk, status, id )
 */
function afk_alarm_normalisieren( $json ) {
	$custom = apply_filters( 'afk_alarm_normalisieren', null, $json );
	if ( is_array( $custom ) ) {
		return $custom;
	}
	// Liste finden (direkt oder in data/items/einsaetze/…).
	$liste = $json;
	if ( is_array( $json ) && ! array_is_list_compat( $json ) ) {
		foreach ( array( 'data', 'items', 'einsaetze', 'Einsaetze', 'alarms', 'alarmierungen', 'rows', 'result', 'results' ) as $k ) {
			if ( isset( $json[ $k ] ) && is_array( $json[ $k ] ) ) {
				$liste = $json[ $k ];
				break;
			}
		}
	}
	if ( ! is_array( $liste ) ) {
		return array();
	}

	$out = array();
	foreach ( $liste as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$zeit = afk_alarm_feld( $row, array( 'datum', 'date', 'zeit', 'time', 'timestamp', 'alarmzeit', 'alarmierungszeit', 'start', 'beginn', 'created', 'alarmtime' ) );
		$ts   = is_numeric( $zeit ) ? (int) ( $zeit > 2000000000 ? $zeit / 1000 : $zeit ) : ( $zeit ? strtotime( (string) $zeit . ' Europe/Vienna' ) : 0 );
		if ( ! $ts && is_string( $zeit ) ) {
			$dt = date_create_from_format( 'd.m.Y H:i', $zeit, wp_timezone() );
			$ts = $dt ? $dt->getTimestamp() : 0;
		}
		$ereignis  = afk_alarm_feld( $row, array( 'ereignis', 'event', 'einsatzart', 'art', 'type', 'title', 'stichwort', 'bezeichnung', 'text' ) );
		$einheiten = afk_alarm_feld( $row, array( 'alarmierteeinheiten', 'alarmierte_einheiten', 'alarmierte einheiten', 'einheiten', 'units', 'feuerwehren', 'organisationen', 'fw' ) );
		if ( is_string( $einheiten ) ) {
			$einheiten = preg_split( '/\s*[,;\n|]\s*/u', $einheiten );
		} elseif ( is_array( $einheiten ) ) {
			$einheiten = array_map(
				function ( $u ) {
					return is_array( $u ) ? (string) ( afk_alarm_feld( $u, array( 'name', 'bezeichnung', 'title' ) ) ?? '' ) : (string) $u;
				},
				$einheiten
			);
		} else {
			$einheiten = array();
		}
		$out[] = array(
			'id'        => (string) ( afk_alarm_feld( $row, array( 'id', 'einsatznummer', 'nummer', 'nr', 'uuid' ) ) ?? md5( $ts . $ereignis . implode( ',', $einheiten ) ) ),
			'zeit'      => (int) $ts,
			'ereignis'  => sanitize_text_field( (string) $ereignis ),
			'einheiten' => array_values( array_filter( array_map( 'sanitize_text_field', $einheiten ) ) ),
			'bezirk'    => sanitize_text_field( (string) afk_alarm_feld( $row, array( 'bezirk', 'district', 'bfkdo', 'region' ) ) ),
			'status'    => sanitize_text_field( (string) afk_alarm_feld( $row, array( 'status', 'state', 'zustand' ) ) ),
		);
	}
	return $out;
}

/**
 * Format der Alarmierungsseite des KLFV: Die Seite enthält die Daten als
 * `var cfg = '<Base64>'`, darin JSON `{"alerts": {"<Feuerwehr>": [ {id, ts, type, district,
 * department, ended, …}, … ]}}` – je alarmierter Feuerwehr ein Eintrag, gleiche `id` = gleicher Einsatz.
 * Koordinaten (`location`) werden bewusst NICHT übernommen.
 *
 * @param string $body HTML der Seite.
 * @return array|null Einheitliche Liste (wie afk_alarm_normalisieren) oder null, wenn das Format nicht passt.
 */
function afk_alarm_klfv( $body ) {
	if ( ! preg_match( "/var\\s+cfg\\s*=\\s*'([A-Za-z0-9+\\/=]+)'/", (string) $body, $m ) ) {
		return null;
	}
	$daten = json_decode( (string) base64_decode( $m[1], true ), true );
	if ( ! is_array( $daten ) || ! isset( $daten['alerts'] ) || ! is_array( $daten['alerts'] ) ) {
		return null;
	}
	$einsaetze = array();
	foreach ( $daten['alerts'] as $feuerwehr => $liste ) {
		foreach ( (array) $liste as $a ) {
			if ( ! is_array( $a ) || empty( $a['id'] ) || empty( $a['ts'] ) ) {
				continue;
			}
			$id = (string) $a['id'];
			if ( ! isset( $einsaetze[ $id ] ) ) {
				$einsaetze[ $id ] = array(
					'id'        => $id,
					'zeit'      => (int) floor( (float) $a['ts'] / 1000 ),
					'ereignis'  => sanitize_text_field( (string) ( $a['type'] ?? '' ) ),
					'einheiten' => array(),
					'bezirk'    => sanitize_text_field( (string) ( $a['district'] ?? '' ) ),
					'beendet'   => true,
				);
			}
			// Ein Einsatz gilt als beendet, sobald alle Meldungen beendet sind.
			$einsaetze[ $id ]['beendet']    = $einsaetze[ $id ]['beendet'] && ! empty( $a['ended'] );
			$einsaetze[ $id ]['einheiten'][] = sanitize_text_field( (string) ( ! empty( $a['department'] ) ? $a['department'] : $feuerwehr ) );
		}
	}
	$out = array();
	foreach ( $einsaetze as $e ) {
		$e['einheiten'] = array_values( array_unique( array_filter( $e['einheiten'] ) ) );
		$e['status']    = $e['beendet'] ? 'beendet' : 'laufend';
		unset( $e['beendet'] );
		$out[] = $e;
	}
	return $out;
}

/**
 * Kompatibilität: array_is_list ab PHP 8.1.
 *
 * @param array $a Array.
 * @return bool
 */
function array_is_list_compat( $a ) {
	if ( function_exists( 'array_is_list' ) ) {
		return array_is_list( $a );
	}
	return array_keys( $a ) === range( 0, count( $a ) - 1 );
}

/**
 * Abrufen, filtern, speichern. Läuft per Cron alle 10 Minuten.
 *
 * @return true|WP_Error
 */
function afk_alarm_abrufen() {
	$e = afk_alarm_einstellungen();
	if ( ! $e['quelle'] ) {
		return new WP_Error( 'afk_alarm', 'Keine Quelle eingetragen.' );
	}
	$res = wp_safe_remote_get(
		$e['quelle'],
		array(
			'timeout' => 20,
			'headers' => array( 'Accept' => 'text/html,application/json;q=0.9' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		update_option( 'afk_alarm_fehler', $res->get_error_message(), false );
		return $res;
	}
	$code  = (int) wp_remote_retrieve_response_code( $res );
	$body  = wp_remote_retrieve_body( $res );
	$liste = 200 === $code ? afk_alarm_klfv( $body ) : null;
	if ( null === $liste && 200 === $code ) {
		$json  = json_decode( $body, true );
		$liste = null === $json ? null : afk_alarm_normalisieren( $json );
	}
	if ( null === $liste ) {
		$msg = 200 !== $code ? 'Antwort ' . $code : 'Datenblock nicht gefunden – hat sich die Alarmierungsseite geändert?';
		update_option( 'afk_alarm_fehler', $msg, false );
		return new WP_Error( 'afk_alarm', $msg );
	}

	$namen  = afk_alarm_suchnamen();
	$grenze = time() - max( 1, (int) $e['tage'] ) * DAY_IN_SECONDS;
	$alt    = get_option( AFK_ALARM_DATA, array() );
	$alle   = array();
	foreach ( is_array( $alt ) ? $alt : array() as $a ) {
		$alle[ $a['id'] ] = $a;
	}

	foreach ( $liste as $a ) {
		if ( ! $a['zeit'] || $a['zeit'] < $grenze ) {
			continue;
		}
		// Nur Alarmierungen mit Beteiligung des Abschnitts.
		$treffer = array();
		foreach ( $a['einheiten'] as $einheit ) {
			$klein = afk_alarm_norm( $einheit );
			$best  = null;
			foreach ( $namen as $name => $term_id ) {
				// Gleich, oder der Name kommt als ganzes Wort vor. Der längste Treffer gewinnt,
				// damit „St. Peter ob Radenthein“ nicht als „Radenthein“ zählt.
				if ( $klein === $name || preg_match( '/(^| )' . preg_quote( $name, '/' ) . '( |$)/u', $klein ) ) {
					if ( null === $best || mb_strlen( $name ) > mb_strlen( $best ) ) {
						$best = $name;
					}
				}
			}
			if ( null !== $best ) {
				if ( $namen[ $best ] ) {
					$treffer[] = $namen[ $best ];
				}
				$a['eigene'][] = $einheit;
			}
		}
		if ( empty( $a['eigene'] ) ) {
			continue;
		}
		$a['term_ids']      = array_values( array_unique( $treffer ) );
		$a['eigene']        = array_values( array_unique( $a['eigene'] ) );
		$alle[ $a['id'] ]   = $a;
	}

	// Alte verwerfen, neueste zuerst, höchstens 200.
	$alle = array_filter(
		$alle,
		function ( $a ) use ( $grenze ) {
			return $a['zeit'] >= $grenze;
		}
	);
	usort(
		$alle,
		function ( $x, $y ) {
			return $y['zeit'] <=> $x['zeit'];
		}
	);
	update_option( AFK_ALARM_DATA, array_slice( array_values( $alle ), 0, 200 ), false );
	update_option( 'afk_alarm_stand', time(), false );
	delete_option( 'afk_alarm_fehler' );
	return true;
}

/**
 * Gespeicherte Alarmierungen (neueste zuerst).
 *
 * @param int $anzahl  Höchstzahl.
 * @param int $term_id Nur diese Feuerwehr (0 = alle des Abschnitts).
 * @return array
 */
function afk_alarmierungen( $anzahl = 5, $term_id = 0 ) {
	$e = afk_alarm_einstellungen();
	if ( ! $e['aktiv'] ) {
		return array();
	}
	$daten = get_option( AFK_ALARM_DATA, array() );
	if ( ! is_array( $daten ) ) {
		return array();
	}
	$grenze = time() - max( 1, (int) $e['tage'] ) * DAY_IN_SECONDS;
	$out    = array();
	foreach ( $daten as $a ) {
		if ( $a['zeit'] < $grenze ) {
			continue;
		}
		if ( $term_id && ! in_array( (int) $term_id, (array) ( $a['term_ids'] ?? array() ), true ) ) {
			continue;
		}
		$out[] = $a;
		if ( count( $out ) >= $anzahl ) {
			break;
		}
	}
	return $out;
}

/**
 * Läuft der Einsatz noch? (Status-Text der Quelle)
 *
 * @param array $a Alarmierung.
 * @return bool
 */
function afk_alarm_laufend( $a ) {
	$s = mb_strtolower( (string) $a['status'] );
	return '' !== $s && ( false !== mb_strpos( $s, 'lauf' ) || false !== mb_strpos( $s, 'aktiv' ) || false !== mb_strpos( $s, 'offen' ) );
}

/**
 * Shortcode [afk_alarmierungen anzahl="5"].
 *
 * @param array $atts Attribute.
 * @return string
 */
function afk_sc_alarmierungen( $atts ) {
	$atts  = shortcode_atts( array( 'anzahl' => 5, 'feuerwehr' => '' ), $atts, 'afk_alarmierungen' );
	$tid   = 0;
	if ( $atts['feuerwehr'] ) {
		$t   = get_term_by( 'slug', sanitize_title( $atts['feuerwehr'] ), 'feuerwehr' );
		$tid = $t ? (int) $t->term_id : 0;
	}
	$liste = afk_alarmierungen( (int) $atts['anzahl'], $tid );
	if ( ! $liste ) {
		return '';
	}
	$out = '<ul class="afk-alarme">';
	foreach ( $liste as $a ) {
		$out .= '<li class="afk-alarm"><span class="afk-alarm__zeit">' . esc_html( wp_date( 'd.m. H:i', $a['zeit'] ) ) . '</span>';
		$out .= '<span class="afk-alarm__ereignis">' . esc_html( $a['ereignis'] ) . '</span>';
		$out .= '<span class="afk-alarm__status' . ( afk_alarm_laufend( $a ) ? ' afk-alarm__status--laufend' : '' ) . '">' . esc_html( $a['status'] ) . '</span>';
		$out .= '<span class="afk-alarm__einheiten">' . esc_html( implode( ', ', $a['eigene'] ) ) . '</span></li>';
	}
	$out .= '</ul><p class="afk-alarme__quelle">Quelle: <a href="' . esc_url( AFK_ALARM_QUELLE ) . '" rel="noopener" target="_blank">Kärntner Landesfeuerwehrverband</a></p>';
	return $out;
}
add_shortcode( 'afk_alarmierungen', 'afk_sc_alarmierungen' );

/* ---------------------------------------------------------------------
 * Einstellungsseite
 * ------------------------------------------------------------------- */

function afk_alarm_admin_menu() {
	add_options_page( 'Alarmierungen', 'Alarmierungen', 'manage_options', 'afk-alarmierungen', 'afk_alarm_admin_page' );
}
add_action( 'admin_menu', 'afk_alarm_admin_menu' );

function afk_alarm_register_setting() {
	register_setting(
		'afk_alarm_group',
		AFK_ALARM_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => function ( $in ) {
				$in = is_array( $in ) ? $in : array();
				return array(
					'aktiv'  => empty( $in['aktiv'] ) ? 0 : 1,
					'quelle' => isset( $in['quelle'] ) ? esc_url_raw( trim( $in['quelle'] ) ) : '',
					'tage'   => isset( $in['tage'] ) ? min( 30, max( 1, absint( $in['tage'] ) ) ) : 7,
					'bezirk' => isset( $in['bezirk'] ) ? sanitize_text_field( $in['bezirk'] ) : '',
					'zusatz' => isset( $in['zusatz'] ) ? sanitize_text_field( $in['zusatz'] ) : '',
				);
			},
		)
	);
}
add_action( 'admin_init', 'afk_alarm_register_setting' );

function afk_alarm_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$meldung = '';
	if ( isset( $_POST['afk_alarm_jetzt'] ) && check_admin_referer( 'afk_alarm_jetzt' ) ) {
		$r       = afk_alarm_abrufen();
		$meldung = is_wp_error( $r ) ? 'Fehler: ' . $r->get_error_message() : 'Abruf erfolgreich.';
	}
	$e     = afk_alarm_einstellungen();
	$stand = (int) get_option( 'afk_alarm_stand', 0 );
	$fehl  = (string) get_option( 'afk_alarm_fehler', '' );
	$daten = afk_alarmierungen( 10 );
	$n     = AFK_ALARM_OPTION;
	?>
	<div class="wrap">
		<h1>Alarmierungen (Kärntner Landesfeuerwehrverband)</h1>
		<p>Zeigt die öffentlichen Alarmierungen des KLFV für die Feuerwehren des Abschnitts auf der Website an. Die Website holt die Daten selbst alle 10 Minuten ab – Besucher verbinden sich dabei nicht mit dem KLFV. Angezeigt werden nur Zeitpunkt, Ereignis, alarmierte Einheiten und Status.</p>
		<?php if ( $meldung ) : ?><div class="notice notice-info"><p><?php echo esc_html( $meldung ); ?></p></div><?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'afk_alarm_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Anzeigen</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[aktiv]" value="1" <?php checked( 1, (int) $e['aktiv'] ); ?>> Alarmierungen auf der Website anzeigen</label></td></tr>
				<tr><th scope="row"><label for="afk-a-q">Datenquelle</label></th><td><input type="url" class="large-text" id="afk-a-q" name="<?php echo esc_attr( $n ); ?>[quelle]" value="<?php echo esc_attr( $e['quelle'] ); ?>" placeholder="<?php echo esc_attr( AFK_ALARM_QUELLE ); ?>"><p class="description">Standard: die öffentliche Alarmierungsseite des KLFV. Angezeigt werden nur Alarmierungen, bei denen eine Feuerwehr des Abschnitts dabei war; auf der Website steht immer die Quellenangabe.</p></td></tr>
				<tr><th scope="row"><label for="afk-a-t">Zeitraum</label></th><td><input type="number" min="1" max="30" id="afk-a-t" name="<?php echo esc_attr( $n ); ?>[tage]" value="<?php echo esc_attr( $e['tage'] ); ?>"> Tage <p class="description">Ältere Alarmierungen werden nicht gespeichert und nicht angezeigt.</p></td></tr>
				<tr><th scope="row"><label for="afk-a-z">Weitere Einheiten</label></th><td><input type="text" class="regular-text" id="afk-a-z" name="<?php echo esc_attr( $n ); ?>[zusatz]" value="<?php echo esc_attr( $e['zusatz'] ); ?>" placeholder="z. B. AFK Millstatt-Radenthein"><p class="description">Zusätzliche Namen (durch Komma getrennt), die als „unser Abschnitt“ zählen. Die 12 Feuerwehren werden automatisch erkannt; abweichende Schreibweisen je Feuerwehr im Feld „Name in den Alarmierungen“.</p></td></tr>
			</table>
			<?php submit_button( 'Speichern' ); ?>
		</form>
		<h2>Status</h2>
		<p>Letzter erfolgreicher Abruf: <?php echo $stand ? esc_html( wp_date( 'd.m.Y H:i:s', $stand ) ) : '–'; ?><?php echo $fehl ? ' · <strong style="color:#b32d2e">Letzter Fehler: ' . esc_html( $fehl ) . '</strong>' : ''; ?></p>
		<form method="post"><?php wp_nonce_field( 'afk_alarm_jetzt' ); ?><p><button class="button" name="afk_alarm_jetzt" value="1">Jetzt abrufen</button></p></form>
		<?php if ( $daten ) : ?>
			<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Zeit</th><th>Ereignis</th><th>Unsere Einheiten</th><th>Status</th></tr></thead><tbody>
			<?php foreach ( $daten as $a ) : ?>
				<tr><td><?php echo esc_html( wp_date( 'd.m.Y H:i', $a['zeit'] ) ); ?></td><td><?php echo esc_html( $a['ereignis'] ); ?></td><td><?php echo esc_html( implode( ', ', $a['eigene'] ) ); ?></td><td><?php echo esc_html( $a['status'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}
