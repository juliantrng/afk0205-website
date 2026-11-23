<?php
/**
 * Unwetterwarnung: Warnungen der GeoSphere Austria (früher ZAMG) für die Gemeinden des
 * Abschnitts automatisch im Hinweisband zeigen.
 *
 * Quelle: https://warnungen.zamg.at/wsapp/api/getWarningsForCoords – Daten unter CC BY 4.0,
 * Namensnennung „GeoSphere Austria“ erfolgt im Hinweisband.
 *
 * Abruf per WP-Cron alle 30 Minuten (serverseitig, Besucher verbinden sich nie mit der GeoSphere).
 * Ein händisch gesetzter Hinweis der Art „Allgemeine Information“ wird während einer Warnung
 * ersetzt; Hinweise zu Waldbrand, Unwetter oder Straßensperre bleiben stehen.
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

const AFK_UNWETTER_OPTION = 'afk_unwetter';
const AFK_UNWETTER_DATA   = 'afk_unwetter_daten';
const AFK_UNWETTER_API    = 'https://warnungen.zamg.at/wsapp/api/getWarningsForCoords';
const AFK_UNWETTER_INFO   = 'https://warnungen.zamg.at/';

/**
 * Gemeinden des Abschnitts (Ortsmitte) – über den Filter änderbar.
 *
 * @return array Name => array( lat, lon )
 */
function afk_unwetter_orte() {
	return apply_filters(
		'afk_unwetter_orte',
		array(
			'Millstatt am See'   => array( 46.8045, 13.5800 ),
			'Radenthein'         => array( 46.8003, 13.7106 ),
			'Bad Kleinkirchheim' => array( 46.8135, 13.7975 ),
		)
	);
}

/**
 * Einstellungen.
 *
 * @return array{aktiv:int,stufe:int,vorlauf:int}
 */
function afk_unwetter_einstellungen() {
	$o = get_option( AFK_UNWETTER_OPTION, array() );
	return wp_parse_args(
		is_array( $o ) ? $o : array(),
		array(
			'aktiv'   => 1,
			'stufe'   => 2,  // ab Orange
			'vorlauf' => 12, // Stunden vor Beginn anzeigen
		)
	);
}

/**
 * Warnarten.
 *
 * @return array id => array( Name, kurzer Rat )
 */
function afk_unwetter_typen() {
	return array(
		1 => array( 'Sturm', 'Lose Gegenstände sichern, Waldgebiete meiden.' ),
		2 => array( 'Starkregen', 'Keller und Abflüsse prüfen, Bachläufe und Unterführungen meiden.' ),
		3 => array( 'Schnee', 'Nur mit Winterausrüstung fahren, mehr Zeit einplanen.' ),
		4 => array( 'Glatteis', 'Fahrten wenn möglich verschieben, besonders vorsichtig gehen.' ),
		5 => array( 'Gewitter', 'Schutz in Gebäuden suchen, Gewässer und freies Gelände verlassen.' ),
		6 => array( 'Hitze', 'Viel trinken, Kinder und Tiere nie im Auto lassen.' ),
		7 => array( 'Kälte', 'Auf ältere Menschen achten, Wasserleitungen vor Frost schützen.' ),
	);
}

/**
 * Warnstufen.
 *
 * @return array id => Name
 */
function afk_unwetter_stufen() {
	return array(
		1 => 'Gelb',
		2 => 'Orange',
		3 => 'Rot',
	);
}

/* =====================================================================
 * Abruf
 * =================================================================== */

/**
 * Warnungen für alle Gemeinden holen und speichern.
 *
 * @return array|WP_Error Gespeicherte Daten.
 */
function afk_unwetter_abrufen() {
	$warnungen = array();
	$fehler    = array();
	foreach ( afk_unwetter_orte() as $ort => $koord ) {
		$res = wp_remote_get(
			add_query_arg(
				array(
					'lat'  => $koord[0],
					'lon'  => $koord[1],
					'lang' => 'de',
				),
				AFK_UNWETTER_API
			),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			$fehler[] = $ort . ': ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
			continue;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! isset( $json['properties'] ) ) {
			$fehler[] = $ort . ': unerwartete Antwort';
			continue;
		}
		$name = $json['properties']['location']['properties']['name'] ?? $ort;
		foreach ( (array) ( $json['properties']['warnings'] ?? array() ) as $w ) {
			$p = $w['properties'] ?? array();
			if ( empty( $p['warnid'] ) ) {
				continue;
			}
			$id    = (int) $p['warnid'];
			$start = (int) ( $p['rawinfo']['start'] ?? 0 );
			$ende  = (int) ( $p['rawinfo']['end'] ?? 0 );
			if ( ! $start || ! $ende ) {
				$tz    = new DateTimeZone( 'Europe/Vienna' );
				$s     = DateTime::createFromFormat( 'd.m.Y H:i', (string) ( $p['begin'] ?? '' ), $tz );
				$e     = DateTime::createFromFormat( 'd.m.Y H:i', (string) ( $p['end'] ?? '' ), $tz );
				$start = $s ? $s->getTimestamp() : 0;
				$ende  = $e ? $e->getTimestamp() : 0;
			}
			if ( ! isset( $warnungen[ $id ] ) ) {
				$warnungen[ $id ] = array(
					'typ'          => (int) ( $p['warntypid'] ?? 0 ),
					'stufe'        => (int) ( $p['warnstufeid'] ?? 0 ),
					'start'        => $start,
					'ende'         => $ende,
					'text'         => sanitize_text_field( (string) ( $p['text'] ?? '' ) ),
					'meteo'        => sanitize_text_field( (string) ( $p['meteotext'] ?? '' ) ),
					'empfehlungen' => sanitize_textarea_field( (string) ( $p['empfehlungen'] ?? '' ) ),
					'gemeinden'    => array(),
				);
			}
			$warnungen[ $id ]['gemeinden'][] = $name;
		}
	}
	// Bei Teilfehlern alte Warnungen der nicht erreichten Gemeinden nicht verwerfen: lieber
	// eine Warnung zu lange zeigen als eine verschlucken.
	$alt = get_option( AFK_UNWETTER_DATA, array() );
	if ( $fehler && ! empty( $alt['warnungen'] ) ) {
		foreach ( $alt['warnungen'] as $id => $w ) {
			if ( ! isset( $warnungen[ $id ] ) && (int) $w['ende'] > time() ) {
				$warnungen[ $id ] = $w;
			}
		}
	}
	$daten = array(
		'stand'     => time(),
		'fehler'    => implode( '; ', $fehler ),
		'warnungen' => $warnungen,
	);
	update_option( AFK_UNWETTER_DATA, $daten, false );
	return count( $fehler ) === count( afk_unwetter_orte() ) ? new WP_Error( 'afk_unwetter', $daten['fehler'] ) : $daten;
}
add_action( 'afk_unwetter_abruf', 'afk_unwetter_abrufen' );

/**
 * Cron einplanen bzw. entfernen.
 */
function afk_unwetter_cron() {
	$e    = afk_unwetter_einstellungen();
	$next = wp_next_scheduled( 'afk_unwetter_abruf' );
	if ( $e['aktiv'] && ! $next ) {
		$takte = wp_get_schedules();
		wp_schedule_event( time() + 60, isset( $takte['afk_30min'] ) ? 'afk_30min' : 'hourly', 'afk_unwetter_abruf' );
	} elseif ( ! $e['aktiv'] && $next ) {
		wp_clear_scheduled_hook( 'afk_unwetter_abruf' );
	}
}
add_action( 'init', 'afk_unwetter_cron' );

/**
 * Die Warnung, die jetzt gezeigt werden soll (höchste Stufe, dann früheste), oder null.
 *
 * @return array|null Warnung + 'weitere' (Anzahl weiterer relevanter Warnungen).
 */
function afk_unwetter_aktuell() {
	$e = afk_unwetter_einstellungen();
	if ( ! $e['aktiv'] ) {
		return null;
	}
	$d = get_option( AFK_UNWETTER_DATA, array() );
	if ( empty( $d['warnungen'] ) ) {
		return null;
	}
	$jetzt    = time();
	$relevant = array_filter(
		$d['warnungen'],
		function ( $w ) use ( $e, $jetzt ) {
			return (int) $w['stufe'] >= (int) $e['stufe'] && (int) $w['ende'] > $jetzt && (int) $w['start'] - $jetzt <= (int) $e['vorlauf'] * HOUR_IN_SECONDS;
		}
	);
	if ( ! $relevant ) {
		return null;
	}
	usort(
		$relevant,
		function ( $a, $b ) {
			return $b['stufe'] <=> $a['stufe'] ?: $a['start'] <=> $b['start'];
		}
	);
	$w            = $relevant[0];
	$w['weitere'] = count( $relevant ) - 1;
	return $w;
}

/**
 * Zeitraum lesbar: „ab Mi 14:00 bis Do 06:00“ bzw. „bis Do 06:00“, wenn schon begonnen.
 *
 * @param int $start Beginn.
 * @param int $ende  Ende.
 * @return string
 */
function afk_unwetter_zeitraum( $start, $ende ) {
	$tage  = array( 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' );
	$fmt   = function ( $ts ) use ( $tage ) {
		$heute  = wp_date( 'Y-m-d' );
		$morgen = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
		$tag    = wp_date( 'Y-m-d', $ts );
		$wort   = $tag === $heute ? 'heute' : ( $tag === $morgen ? 'morgen' : $tage[ (int) wp_date( 'w', $ts ) ] . ' ' . wp_date( 'j.n.', $ts ) );
		return $wort . ' ' . wp_date( 'G:i', $ts );
	};
	return ( $start > time() ? 'ab ' . $fmt( $start ) . ' ' : '' ) . 'bis ' . $fmt( $ende );
}

/**
 * Hinweis für das Hinweisband aus einer Warnung.
 *
 * @param array $w Warnung.
 * @return array
 */
function afk_unwetter_hinweis( $w ) {
	$typen  = afk_unwetter_typen();
	$stufen = afk_unwetter_stufen();
	$typ    = $typen[ $w['typ'] ] ?? array( 'Unwetter', '' );
	$alle   = count( array_unique( $w['gemeinden'] ) ) >= count( afk_unwetter_orte() );
	$wo     = $alle ? '' : ' (' . implode( ', ', array_unique( $w['gemeinden'] ) ) . ')';
	$text   = $typ[0] . ' ' . afk_unwetter_zeitraum( (int) $w['start'], (int) $w['ende'] ) . $wo . '. ' . $typ[1];
	if ( ! empty( $w['weitere'] ) ) {
		$text .= ' (+' . (int) $w['weitere'] . ' weitere Warnung' . ( $w['weitere'] > 1 ? 'en' : '' ) . ')';
	}
	return array(
		'art'    => 'unwetter',
		'stufe'  => (int) $w['stufe'],
		'titel'  => 'Unwetterwarnung ' . ( $stufen[ $w['stufe'] ] ?? '' ),
		'text'   => $text,
		'link'   => array(
			'url'   => AFK_UNWETTER_INFO,
			'title' => 'Details: GeoSphere Austria',
		),
		'quelle' => 'geosphere',
	);
}

/**
 * Ins Hinweisband einklinken.
 *
 * @param array|null $hinweis Händischer Hinweis.
 * @return array|null
 */
function afk_unwetter_ins_band( $hinweis ) {
	$w = afk_unwetter_aktuell();
	if ( ! $w ) {
		return $hinweis;
	}
	// Händische Hinweise mit eigener Gefahrenlage bleiben stehen.
	if ( $hinweis && 'info' !== $hinweis['art'] ) {
		return $hinweis;
	}
	return afk_unwetter_hinweis( $w );
}
add_filter( 'afk_hinweis', 'afk_unwetter_ins_band' );

/* =====================================================================
 * Hinweisband → Unwetterwarnung
 * =================================================================== */

/**
 * Menüpunkt unter „Hinweisband“.
 */
function afk_unwetter_menue() {
	add_submenu_page( 'afk-hinweisband', 'Unwetterwarnung', 'Unwetterwarnung', 'manage_options', 'afk-unwetter', 'afk_unwetter_seite' );
}
add_action( 'admin_menu', 'afk_unwetter_menue', 20 );

/**
 * Seite.
 */
function afk_unwetter_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$meldung = '';
	if ( isset( $_POST['afk_unwetter_speichern'] ) && check_admin_referer( 'afk_unwetter' ) ) {
		update_option(
			AFK_UNWETTER_OPTION,
			array(
				'aktiv'   => empty( $_POST['aktiv'] ) ? 0 : 1,
				'stufe'   => isset( $_POST['stufe'] ) ? min( 3, max( 1, absint( $_POST['stufe'] ) ) ) : 2,
				'vorlauf' => isset( $_POST['vorlauf'] ) ? min( 72, absint( $_POST['vorlauf'] ) ) : 12,
			),
			false
		);
		afk_unwetter_cron();
		$meldung = 'Gespeichert.';
	}
	if ( isset( $_POST['afk_unwetter_abrufen'] ) && check_admin_referer( 'afk_unwetter' ) ) {
		$r       = afk_unwetter_abrufen();
		$meldung = is_wp_error( $r ) ? 'Abruf fehlgeschlagen: ' . $r->get_error_message() : 'Warnungen abgerufen.';
	}
	$e      = afk_unwetter_einstellungen();
	$d      = get_option( AFK_UNWETTER_DATA, array() );
	$typen  = afk_unwetter_typen();
	$stufen = afk_unwetter_stufen();
	$jetzt  = afk_unwetter_aktuell();
	?>
	<div class="wrap">
		<h1>Unwetterwarnung</h1>
		<p>Gibt die GeoSphere Austria (früher ZAMG) für <?php echo esc_html( implode( ', ', array_keys( afk_unwetter_orte() ) ) ); ?> eine Warnung heraus, erscheint sie automatisch im Hinweisband – mit Zeitraum und einem kurzen Rat. Endet die Warnung, verschwindet das Band wieder. Abruf alle 30 Minuten.</p>
		<p>Ein eigener Hinweis der Art „Allgemeine Information“ wird während einer Warnung ersetzt. Eigene Hinweise zu Waldbrand, Unwetter oder Straßensperre bleiben stehen.</p>
		<?php if ( $meldung ) : ?><div class="notice notice-success"><p><?php echo esc_html( $meldung ); ?></p></div><?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'afk_unwetter' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Automatisch anzeigen</th>
					<td><label><input type="checkbox" name="aktiv" value="1" <?php checked( 1, (int) $e['aktiv'] ); ?>> Warnungen im Hinweisband zeigen</label></td></tr>
				<tr><th scope="row"><label for="afk-uw-stufe">Ab Stufe</label></th>
					<td><select id="afk-uw-stufe" name="stufe">
						<?php foreach ( $stufen as $id => $name ) : ?>
							<option value="<?php echo (int) $id; ?>" <?php selected( (int) $e['stufe'], $id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Gelb gibt es oft (z. B. jeder Schneefall). Empfohlen: ab Orange.</p></td></tr>
				<tr><th scope="row"><label for="afk-uw-vor">Vorlauf</label></th>
					<td><input class="small-text" type="number" min="0" max="72" id="afk-uw-vor" name="vorlauf" value="<?php echo (int) $e['vorlauf']; ?>"> Stunden vor Beginn anzeigen</td></tr>
			</table>
			<p><button class="button button-primary" name="afk_unwetter_speichern" value="1">Speichern</button>
				<button class="button" name="afk_unwetter_abrufen" value="1">Jetzt abrufen</button></p>
		</form>

		<h2>Aktuell</h2>
		<p>
			<?php
			if ( empty( $d['stand'] ) ) {
				echo 'Noch nicht abgerufen.';
			} else {
				echo esc_html( 'Zuletzt abgerufen: ' . wp_date( 'j.n.Y H:i', (int) $d['stand'] ) . ( $d['fehler'] ? ' – Fehler: ' . $d['fehler'] : '' ) );
			}
			?>
		</p>
		<?php if ( $jetzt ) : $h = afk_unwetter_hinweis( $jetzt ); ?>
			<p><strong>Im Hinweisband steht gerade:</strong> <?php echo esc_html( $h['titel'] . ': ' . $h['text'] ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $d['warnungen'] ) ) : ?>
			<p>Keine Warnungen für die Gemeinden des Abschnitts.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Art</th><th>Stufe</th><th>Zeitraum</th><th>Gemeinden</th><th>Im Band?</th></tr></thead>
				<tbody>
				<?php foreach ( $d['warnungen'] as $w ) : ?>
					<tr>
						<td><?php echo esc_html( $typen[ $w['typ'] ][0] ?? '–' ); ?></td>
						<td><?php echo esc_html( $stufen[ $w['stufe'] ] ?? '–' ); ?></td>
						<td><?php echo esc_html( wp_date( 'j.n. H:i', (int) $w['start'] ) . ' – ' . wp_date( 'j.n. H:i', (int) $w['ende'] ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', array_unique( $w['gemeinden'] ) ) ); ?></td>
						<td><?php echo (int) $w['stufe'] >= (int) $e['stufe'] ? ( (int) $w['start'] - time() <= (int) $e['vorlauf'] * HOUR_IN_SECONDS ? 'ja' : 'später' ) : 'nein (Stufe)'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p class="description">Daten: GeoSphere Austria, CC BY 4.0.</p>
	</div>
	<?php
}
