<?php
/**
 * Statistik: eigene, datensparsame Zugriffszählung (ersetzt Matomo).
 *
 * - Keine Cookies, keine IP-Adressen, kein Fingerabdruck, kein externer Server.
 * - Gespeichert werden nur Tagessummen je Seite, grobe Herkunft (Suche, Facebook, …) und
 *   Geräteklasse (Handy/Tablet/Computer). Einzelne Besucher sind nicht erkennbar und werden
 *   deshalb auch nicht gezählt – nur Seitenaufrufe.
 * - Gezählt wird per kleinem Aufruf aus dem Browser (funktioniert auch mit Seiten-Cache /
 *   Cloudflare). Angemeldete Redakteure und erkennbare Bots zählen nicht.
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

const AFK_STAT_DB_VERSION = 1;

/**
 * Tabellenname.
 *
 * @return string
 */
function afk_stat_tabelle() {
	global $wpdb;
	return $wpdb->prefix . 'afk_statistik';
}

/**
 * Tabelle anlegen bzw. aktualisieren.
 */
function afk_stat_tabelle_anlegen() {
	if ( (int) get_option( 'afk_stat_db', 0 ) >= AFK_STAT_DB_VERSION ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = afk_stat_tabelle();
	dbDelta(
		"CREATE TABLE $t (
			tag date NOT NULL,
			pfad varchar(190) NOT NULL,
			objekt_id bigint(20) unsigned NOT NULL DEFAULT 0,
			typ varchar(12) NOT NULL DEFAULT '',
			quelle varchar(12) NOT NULL DEFAULT '',
			geraet varchar(8) NOT NULL DEFAULT '',
			aufrufe int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (tag,pfad,quelle,geraet),
			KEY objekt (objekt_id,tag)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( 'afk_stat_db', AFK_STAT_DB_VERSION );
}
add_action( 'admin_init', 'afk_stat_tabelle_anlegen' );

/* =====================================================================
 * Zählen
 * =================================================================== */

/**
 * Kleines Skript am Seitenende: meldet Pfad, Seitentyp, Herkunft und Bildschirmbreite.
 */
function afk_stat_skript() {
	if ( is_admin() || is_preview() || is_customize_preview() || is_404() || current_user_can( 'edit_posts' ) ) {
		return;
	}
	$typ = 'sonst';
	$id  = 0;
	if ( is_front_page() ) {
		$typ = 'start';
	} elseif ( is_singular( 'post' ) ) {
		$typ = 'bericht';
		$id  = get_queried_object_id();
	} elseif ( is_singular() ) {
		$typ = 'seite';
		$id  = get_queried_object_id();
	} elseif ( is_tax( 'feuerwehr' ) ) {
		$typ = 'feuerwehr';
		$id  = get_queried_object_id();
	} elseif ( is_archive() || is_home() ) {
		$typ = 'archiv';
	} elseif ( is_search() ) {
		$typ = 'suche';
	}
	$daten = array(
		'u' => esc_url_raw( rest_url( 'afk/v1/zaehler' ) ),
		't' => $typ,
		'i' => (int) $id,
	);
	?>
	<script>
	(function(d){try{if(navigator.webdriver||document.visibilityState==='prerender')return;var r='';try{var h=new URL(document.referrer).hostname;r=h===location.hostname?'intern':h;}catch(e){}
	var w=window.innerWidth||0,g=w<700?'handy':(w<1100?'tablet':'computer');
	var b=JSON.stringify({p:location.pathname,t:d.t,i:d.i,r:r,g:g});
	if(!(navigator.sendBeacon&&navigator.sendBeacon(d.u,new Blob([b],{type:'application/json'})))){fetch(d.u,{method:'POST',body:b,headers:{'Content-Type':'application/json'},keepalive:true});}}catch(e){}})(<?php echo wp_json_encode( $daten ); ?>);
	</script>
	<?php
}
add_action( 'wp_footer', 'afk_stat_skript', 99 );

/**
 * Herkunft aus dem Rechnernamen der verweisenden Seite.
 *
 * @param string $host Rechnername oder „intern“/leer.
 * @return string
 */
function afk_stat_quelle( $host ) {
	$host = strtolower( (string) $host );
	if ( '' === $host ) {
		return 'direkt';
	}
	if ( 'intern' === $host ) {
		return 'intern';
	}
	$muster = array(
		'suche'     => '/(^|\.)(google|bing|duckduckgo|ecosia|yahoo|qwant|startpage|yandex|brave)\./',
		'facebook'  => '/(^|\.)(facebook\.com|fb\.com|fb\.me|messenger\.com)$/',
		'instagram' => '/(^|\.)instagram\.com$/',
		'telegram'  => '/(^|\.)(t\.me|telegram\.org|telegram\.me)$/',
		'whatsapp'  => '/(^|\.)(whatsapp\.com|wa\.me)$/',
	);
	foreach ( $muster as $q => $re ) {
		if ( preg_match( $re, $host ) ) {
			return $q;
		}
	}
	return 'links';
}

/**
 * REST: einen Aufruf zählen.
 */
function afk_stat_route() {
	register_rest_route(
		'afk/v1',
		'/zaehler',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'afk_stat_zaehlen',
		)
	);
}
add_action( 'rest_api_init', 'afk_stat_route' );

/**
 * Aufruf zählen.
 *
 * @param WP_REST_Request $req Anfrage.
 * @return WP_REST_Response
 */
function afk_stat_zaehlen( WP_REST_Request $req ) {
	$antwort = new WP_REST_Response( null, 204 );
	$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse/i', $ua ) ) {
		return $antwort;
	}
	$j = json_decode( $req->get_body(), true );
	if ( ! is_array( $j ) || empty( $j['p'] ) ) {
		return $antwort;
	}
	$pfad = substr( '/' . ltrim( wp_parse_url( (string) $j['p'], PHP_URL_PATH ) ?? '', '/' ), 0, 190 );
	$typ  = in_array( $j['t'] ?? '', array( 'start', 'bericht', 'seite', 'feuerwehr', 'archiv', 'suche', 'sonst' ), true ) ? $j['t'] : 'sonst';
	$id   = absint( $j['i'] ?? 0 );
	// Nur echte Inhalte dieser Seite zählen (schützt vor Fantasie-Einträgen).
	if ( $id && in_array( $typ, array( 'bericht', 'seite' ), true ) && 'publish' !== get_post_status( $id ) ) {
		return $antwort;
	}
	$geraet = in_array( $j['g'] ?? '', array( 'handy', 'tablet', 'computer' ), true ) ? $j['g'] : 'computer';
	$quelle = afk_stat_quelle( substr( (string) ( $j['r'] ?? '' ), 0, 100 ) );

	global $wpdb;
	$t = afk_stat_tabelle();
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"INSERT INTO $t (tag, pfad, objekt_id, typ, quelle, geraet, aufrufe) VALUES (%s, %s, %d, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE aufrufe = aufrufe + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_date( 'Y-m-d' ),
			$pfad,
			$id,
			$typ,
			$quelle,
			$geraet
		)
	);
	return $antwort;
}

/* =====================================================================
 * Auswertung
 * =================================================================== */

/**
 * Bezeichnungen.
 *
 * @return array
 */
function afk_stat_namen() {
	return array(
		'quelle' => array(
			'direkt'    => 'Direkt / Lesezeichen',
			'suche'     => 'Suchmaschinen',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'telegram'  => 'Telegram',
			'whatsapp'  => 'WhatsApp',
			'links'     => 'Andere Websites',
			'intern'    => 'Innerhalb der Website',
		),
		'geraet' => array(
			'handy'    => 'Handy',
			'tablet'   => 'Tablet',
			'computer' => 'Computer',
		),
	);
}

/**
 * Auswertung für einen Zeitraum.
 *
 * @param int $tage Anzahl Tage bis heute.
 * @return array
 */
function afk_stat_auswertung( $tage ) {
	global $wpdb;
	$t   = afk_stat_tabelle();
	$ab  = wp_date( 'Y-m-d', time() - ( $tage - 1 ) * DAY_IN_SECONDS );
	$sql = function ( $q ) use ( $wpdb, $ab ) {
		return $wpdb->get_results( $wpdb->prepare( $q, $ab ), ARRAY_A ); // phpcs:ignore WordPress.DB
	};
	$verlauf = array();
	for ( $i = $tage - 1; $i >= 0; $i-- ) {
		$verlauf[ wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = 0;
	}
	foreach ( $sql( "SELECT tag, SUM(aufrufe) n FROM $t WHERE tag >= %s GROUP BY tag" ) as $z ) {
		$verlauf[ $z['tag'] ] = (int) $z['n'];
	}
	$quellen = array();
	foreach ( $sql( "SELECT quelle, SUM(aufrufe) n FROM $t WHERE tag >= %s AND quelle <> 'intern' GROUP BY quelle ORDER BY n DESC" ) as $z ) {
		$quellen[ $z['quelle'] ] = (int) $z['n'];
	}
	$geraete = array();
	foreach ( $sql( "SELECT geraet, SUM(aufrufe) n FROM $t WHERE tag >= %s GROUP BY geraet ORDER BY n DESC" ) as $z ) {
		$geraete[ $z['geraet'] ] = (int) $z['n'];
	}
	$berichte = $sql( "SELECT objekt_id, SUM(aufrufe) n FROM $t WHERE tag >= %s AND typ = 'bericht' AND objekt_id > 0 GROUP BY objekt_id ORDER BY n DESC LIMIT 15" );
	$seiten   = $sql( "SELECT typ, pfad, objekt_id, SUM(aufrufe) n FROM $t WHERE tag >= %s AND typ <> 'bericht' GROUP BY typ, pfad, objekt_id ORDER BY n DESC LIMIT 10" );

	// Berichte je Feuerwehr (über die Zuordnung der Berichte).
	$wehren = array();
	foreach ( $sql( "SELECT objekt_id, SUM(aufrufe) n FROM $t WHERE tag >= %s AND typ = 'bericht' AND objekt_id > 0 GROUP BY objekt_id" ) as $z ) {
		$terms = get_the_terms( (int) $z['objekt_id'], 'feuerwehr' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( 0 === (int) $term->parent ) {
				continue;
			}
			$wehren[ $term->name ] = ( $wehren[ $term->name ] ?? 0 ) + (int) $z['n'];
		}
	}
	arsort( $wehren );

	return array(
		'gesamt'   => array_sum( $verlauf ),
		'verlauf'  => $verlauf,
		'quellen'  => $quellen,
		'geraete'  => $geraete,
		'berichte' => $berichte,
		'seiten'   => $seiten,
		'wehren'   => $wehren,
	);
}

/**
 * Balkendiagramm (SVG) für den Verlauf.
 *
 * @param array $verlauf Tag => Aufrufe.
 * @return string
 */
function afk_stat_diagramm( $verlauf ) {
	$max = max( 1, max( $verlauf ) );
	$n   = count( $verlauf );
	$b   = 900;
	$h   = 180;
	$w   = $b / $n;
	$svg = '<svg viewBox="0 0 ' . $b . ' ' . ( $h + 22 ) . '" style="width:100%;height:auto;display:block" role="img" aria-label="Aufrufe pro Tag">';
	$svg .= '<line x1="0" y1="' . $h . '" x2="' . $b . '" y2="' . $h . '" stroke="#c3c4c7"/>';
	$i = 0;
	foreach ( $verlauf as $tag => $v ) {
		$bh   = $v ? max( 2, round( $v / $max * ( $h - 16 ) ) ) : 0;
		$x    = $i * $w;
		$svg .= '<rect x="' . round( $x + $w * 0.15, 1 ) . '" y="' . ( $h - $bh ) . '" width="' . round( $w * 0.7, 1 ) . '" height="' . $bh . '" rx="2" fill="#C10000"><title>' . esc_html( wp_date( 'D j.n.', strtotime( $tag ) ) . ': ' . number_format_i18n( $v ) ) . '</title></rect>';
		$step = $n > 60 ? 30 : ( $n > 14 ? 7 : 1 );
		if ( 0 === ( $n - 1 - $i ) % $step ) {
			$svg .= '<text x="' . round( $x + $w / 2, 1 ) . '" y="' . ( $h + 16 ) . '" font-size="11" text-anchor="middle" fill="#646970">' . esc_html( wp_date( 'j.n.', strtotime( $tag ) ) ) . '</text>';
		}
		++$i;
	}
	$svg .= '<text x="4" y="12" font-size="11" fill="#646970">' . esc_html( 'max. ' . number_format_i18n( $max ) ) . '</text>';
	return $svg . '</svg>';
}

/**
 * Anteile als Balkenliste.
 *
 * @param array $werte Schlüssel => Anzahl.
 * @param array $namen Schlüssel => Bezeichnung.
 * @return string
 */
function afk_stat_anteile( $werte, $namen = array() ) {
	$summe = max( 1, array_sum( $werte ) );
	$html  = '<table class="afk-stat-anteile">';
	foreach ( $werte as $k => $v ) {
		$p     = round( $v / $summe * 100 );
		$html .= '<tr><td>' . esc_html( $namen[ $k ] ?? $k ) . '</td><td class="num">' . esc_html( number_format_i18n( $v ) ) . '</td><td class="balken"><span style="width:' . (int) $p . '%"></span></td><td class="num">' . (int) $p . ' %</td></tr>';
	}
	return $html . '</table>';
}

/**
 * Menüpunkt unter Dashboard.
 */
function afk_stat_menue() {
	add_dashboard_page( 'Statistik', 'Statistik', 'edit_others_posts', 'afk-statistik', 'afk_stat_seite' );
}
add_action( 'admin_menu', 'afk_stat_menue' );

/**
 * Seite.
 */
function afk_stat_seite() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$zeitraeume = array(
		7   => '7 Tage',
		30  => '30 Tage',
		90  => '90 Tage',
		365 => '12 Monate',
	);
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige.
	$tage  = isset( $_GET['tage'], $zeitraeume[ (int) $_GET['tage'] ] ) ? (int) $_GET['tage'] : 30;
	$a     = afk_stat_auswertung( $tage );
	$namen = afk_stat_namen();
	$seit  = get_option( 'afk_stat_seit' );
	?>
	<div class="wrap afk-stat">
		<h1>Statistik</h1>
		<p class="afk-stat-zeit">
			<?php foreach ( $zeitraeume as $t => $name ) : ?>
				<a class="button<?php echo $t === $tage ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tage', $t ) ); ?>"><?php echo esc_html( $name ); ?></a>
			<?php endforeach; ?>
		</p>

		<div class="afk-stat-karte afk-stat-gross">
			<div class="afk-stat-zahl"><?php echo esc_html( number_format_i18n( $a['gesamt'] ) ); ?></div>
			<div class="afk-stat-label">Seitenaufrufe in den letzten <?php echo esc_html( array( 7 => '7 Tagen', 30 => '30 Tagen', 90 => '90 Tagen', 365 => '12 Monaten' )[ $tage ] ); ?><?php echo $seit ? esc_html( ' (gezählt seit ' . wp_date( 'j.n.Y', (int) $seit ) . ')' ) : ''; ?></div>
			<?php echo afk_stat_diagramm( $a['verlauf'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- bereits escaped. ?>
		</div>

		<div class="afk-stat-raster">
			<div class="afk-stat-karte">
				<h2>Meistgelesene Berichte</h2>
				<?php if ( ! $a['berichte'] ) : ?><p>Noch keine Daten.</p><?php else : ?>
				<table class="afk-stat-liste">
					<?php foreach ( $a['berichte'] as $z ) : ?>
						<tr><td><a href="<?php echo esc_url( get_permalink( (int) $z['objekt_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( (int) $z['objekt_id'] ) ); ?></a><br><small><?php echo esc_html( get_the_date( 'j.n.Y', (int) $z['objekt_id'] ) ); ?></small></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $z['n'] ) ); ?></td></tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
			</div>
			<div class="afk-stat-karte">
				<h2>Woher die Besucher kommen</h2>
				<?php echo $a['quellen'] ? afk_stat_anteile( $a['quellen'], $namen['quelle'] ) : '<p>Noch keine Daten.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2>Geräte</h2>
				<?php echo $a['geraete'] ? afk_stat_anteile( $a['geraete'], $namen['geraet'] ) : '<p>Noch keine Daten.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
			<div class="afk-stat-karte">
				<h2>Berichte nach Feuerwehr</h2>
				<?php echo $a['wehren'] ? afk_stat_anteile( $a['wehren'] ) : '<p>Noch keine Daten.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p><small>Aufrufe der Berichte, an denen die Feuerwehr beteiligt war (ein Bericht kann mehreren zählen).</small></p>
			</div>
			<div class="afk-stat-karte">
				<h2>Weitere Seiten</h2>
				<?php if ( ! $a['seiten'] ) : ?><p>Noch keine Daten.</p><?php else : ?>
				<table class="afk-stat-liste">
					<?php foreach ( $a['seiten'] as $z ) : ?>
						<?php
						$titel = 'start' === $z['typ'] ? 'Startseite' : ( $z['objekt_id'] && 'feuerwehr' === $z['typ'] ? ( get_term( (int) $z['objekt_id'] )->name ?? $z['pfad'] ) : ( $z['objekt_id'] ? get_the_title( (int) $z['objekt_id'] ) : $z['pfad'] ) );
						?>
						<tr><td><?php echo esc_html( $titel ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $z['n'] ) ); ?></td></tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
			</div>
		</div>
		<p class="description">Gezählt werden nur Seitenaufrufe – ohne Cookies, ohne IP-Adressen, ohne Weitergabe an Dritte. Angemeldete Redakteure und Suchmaschinen-Roboter zählen nicht. Einzelne Besucher sind bewusst nicht unterscheidbar.</p>
	</div>
	<style>
		.afk-stat-zeit .button { margin-right: 4px; }
		.afk-stat-karte { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 16px 20px; margin: 0 0 16px; }
		.afk-stat-karte h2 { margin: 0 0 10px; font-size: 15px; }
		.afk-stat-karte h2:not(:first-child) { margin-top: 18px; }
		.afk-stat-zahl { font-size: 40px; font-weight: 700; line-height: 1.1; }
		.afk-stat-label { color: #646970; margin: 2px 0 14px; }
		.afk-stat-raster { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; }
		.afk-stat-raster .afk-stat-karte { margin: 0; }
		.afk-stat-liste, .afk-stat-anteile { width: 100%; border-collapse: collapse; }
		.afk-stat-liste td, .afk-stat-anteile td { padding: 6px 4px; border-top: 1px solid #f0f0f1; vertical-align: top; }
		.afk-stat-liste tr:first-child td, .afk-stat-anteile tr:first-child td { border-top: 0; }
		.afk-stat .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
		.afk-stat-anteile .balken { width: 40%; }
		.afk-stat-anteile .balken span { display: block; height: 8px; margin-top: 6px; background: #C10000; border-radius: 4px; min-width: 2px; }
	</style>
	<?php
}

/**
 * Erster Zähltag merken.
 */
function afk_stat_start() {
	if ( ! get_option( 'afk_stat_seit' ) ) {
		update_option( 'afk_stat_seit', time(), false );
	}
}
add_action( 'admin_init', 'afk_stat_start' );

/**
 * Dashboard-Kasten: letzte 7 Tage.
 */
function afk_stat_widget() {
	if ( current_user_can( 'edit_others_posts' ) ) {
		wp_add_dashboard_widget( 'afk_stat', 'Statistik – letzte 7 Tage', 'afk_stat_widget_inhalt' );
	}
}
add_action( 'wp_dashboard_setup', 'afk_stat_widget' );

/**
 * Inhalt des Dashboard-Kastens.
 */
function afk_stat_widget_inhalt() {
	$a = afk_stat_auswertung( 7 );
	echo '<p style="font-size:28px;font-weight:700;margin:0">' . esc_html( number_format_i18n( $a['gesamt'] ) ) . '</p><p style="margin:0 0 10px;color:#646970">Seitenaufrufe</p>';
	echo afk_stat_diagramm( $a['verlauf'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $a['berichte'] ) {
		echo '<p style="margin:12px 0 4px"><strong>Meistgelesen</strong></p><ol style="margin:0 0 0 18px">';
		foreach ( array_slice( $a['berichte'], 0, 5 ) as $z ) {
			echo '<li>' . esc_html( get_the_title( (int) $z['objekt_id'] ) ) . ' <span style="color:#646970">(' . esc_html( number_format_i18n( (int) $z['n'] ) ) . ')</span></li>';
		}
		echo '</ol>';
	}
	echo '<p style="margin:10px 0 0"><a href="' . esc_url( admin_url( 'index.php?page=afk-statistik' ) ) . '">Ganze Statistik</a></p>';
}

/**
 * Spalte „Aufrufe (30 Tage)“ in der Beitragsliste.
 *
 * @param array $spalten Spalten.
 * @return array
 */
function afk_stat_spalte( $spalten ) {
	$spalten['afk_aufrufe'] = 'Aufrufe (30 T.)';
	return $spalten;
}
add_filter( 'manage_post_posts_columns', 'afk_stat_spalte' );

/**
 * Inhalt der Spalte.
 *
 * @param string $spalte  Spalte.
 * @param int    $post_id Beitrag.
 */
function afk_stat_spalte_inhalt( $spalte, $post_id ) {
	if ( 'afk_aufrufe' !== $spalte ) {
		return;
	}
	static $werte = null;
	if ( null === $werte ) {
		global $wpdb;
		$t     = afk_stat_tabelle();
		$werte = array();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT objekt_id, SUM(aufrufe) n FROM $t WHERE tag >= %s AND typ = 'bericht' GROUP BY objekt_id", wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$werte[ (int) $r['objekt_id'] ] = (int) $r['n'];
		}
	}
	echo esc_html( isset( $werte[ $post_id ] ) ? number_format_i18n( $werte[ $post_id ] ) : '–' );
}
add_action( 'manage_post_posts_custom_column', 'afk_stat_spalte_inhalt', 10, 2 );
