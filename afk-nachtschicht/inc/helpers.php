<?php
/**
 * Hilfsfunktionen für die Vorlagen.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;

/**
 * Feldwert aus AFK Core / ACF, mit Fallback auf Metadaten.
 *
 * @param string     $name    Feldname.
 * @param int|string $context Post-ID oder 'term_<id>'.
 * @return mixed
 */
function afk_ns_field( $name, $context = null ) {
	if ( function_exists( 'afk_field' ) ) {
		return afk_field( $name, $context );
	}
	if ( is_string( $context ) && 0 === strpos( $context, 'term_' ) ) {
		return get_term_meta( (int) substr( $context, 5 ), $name, true );
	}
	return get_post_meta( $context ? $context : get_the_ID(), $name, true );
}

/**
 * Textfeld in Zeilen zerlegen.
 *
 * @param string $text Text.
 * @return string[]
 */
function afk_ns_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ), 'strlen' ) );
}

/**
 * Die drei Bericht-Kategorien (Slugs im Customizer anpassbar).
 *
 * @return array key => array( slug, Mehrzahl, Einzahl )
 */
function afk_ns_kategorien() {
	return array(
		'einsatz'   => array( get_theme_mod( 'afk_kat_einsatz', 'einsaetze' ), 'Einsätze', 'Einsatz' ),
		'uebung'    => array( get_theme_mod( 'afk_kat_uebung', 'uebungen' ), 'Übungen', 'Übung' ),
		'allgemein' => array( get_theme_mod( 'afk_kat_allgemein', 'allgemein' ), 'Allgemein', 'Allgemein' ),
	);
}

/**
 * Anzeigename der Kategorie eines Berichts.
 *
 * @param int $post_id Beitrag.
 * @return string
 */
function afk_ns_kat_label( $post_id ) {
	$cats = get_the_category( $post_id );
	if ( ! $cats ) {
		return '';
	}
	foreach ( afk_ns_kategorien() as $k ) {
		foreach ( $cats as $c ) {
			if ( $c->slug === $k[0] ) {
				return $k[2];
			}
		}
	}
	foreach ( $cats as $c ) {
		if ( $c->parent ) {
			return $c->name;
		}
	}
	return $cats[0]->name;
}

/**
 * Ist der Beitrag ein Einsatzbericht?
 *
 * @param int $post_id Beitrag.
 * @return bool
 */
function afk_ns_ist_einsatz( $post_id ) {
	return has_category( get_theme_mod( 'afk_kat_einsatz', 'einsaetze' ), $post_id );
}

/**
 * Logo (weiß) – eigenes Logo aus dem Customizer oder das mitgelieferte.
 *
 * @param string $class CSS-Klasse.
 * @return string
 */
function afk_ns_logo_img( $class = '' ) {
	$alt = get_bloginfo( 'name' );
	$id  = (int) get_theme_mod( 'custom_logo' );
	if ( $id ) {
		return wp_get_attachment_image( $id, 'full', false, array( 'class' => $class, 'alt' => $alt ) );
	}
	return sprintf( '<img class="%s" src="%s" width="589" height="160" alt="%s">', esc_attr( $class ), esc_url( AFK_NS_URL . '/assets/img/logo-weiss.png' ), esc_attr( $alt ) );
}

/**
 * Einfaches Inline-Icon.
 *
 * @param string $name Name.
 * @param int    $size Größe.
 * @return string
 */
function afk_ns_icon( $name, $size = 18 ) {
	$paths = array(
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'chevron' => '<path d="M9 6l6 6-6 6"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'camera'  => '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.5"/><path d="M8 6l1.5-2h5L16 6"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'bell'    => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		'ext'     => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf( '<svg aria-hidden="true" focusable="false" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>', (int) $size, $paths[ $name ] );
}

/**
 * Alle Wehren (ohne Gemeinde-Begriffe), sortiert nach Gemeinde und Name.
 *
 * @return array Liste von array( term, gemeinde )
 */
function afk_ns_wehren() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();
	if ( ! taxonomy_exists( 'feuerwehr' ) ) {
		return $cache;
	}
	$terms = get_terms( array( 'taxonomy' => 'feuerwehr', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $cache;
	}
	$by_id = array();
	foreach ( $terms as $t ) {
		$by_id[ $t->term_id ] = $t;
	}
	foreach ( $terms as $t ) {
		if ( ! $t->parent ) {
			continue;
		}
		$gem     = isset( $by_id[ $t->parent ] ) ? preg_replace( '/^Gemeinde\s+/u', '', $by_id[ $t->parent ]->name ) : '';
		$cache[] = array( 'term' => $t, 'gemeinde' => $gem );
	}
	usort(
		$cache,
		function ( $a, $b ) {
			return array( $a['gemeinde'], $a['term']->name ) <=> array( $b['gemeinde'], $b['term']->name );
		}
	);
	return $cache;
}

/**
 * Kurzname einer Wehr („FF Millstatt“ → „Millstatt“).
 *
 * @param string $name Name.
 * @return string
 */
function afk_ns_kurzname( $name ) {
	return trim( preg_replace( '/\s+–.*$/u', '', preg_replace( '/^(FF|BtF|Btf)\s+/u', '', $name ) ) );
}

/**
 * Aktuelle Filterwerte aus der Adresse (?kat=…&ff=…&jahr=…).
 *
 * @return array
 */
function afk_ns_filter_werte() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Filter.
	$kat  = isset( $_GET['kat'] ) ? sanitize_key( wp_unslash( $_GET['kat'] ) ) : '';
	$ff   = isset( $_GET['ff'] ) ? sanitize_title( wp_unslash( $_GET['ff'] ) ) : '';
	$jahr = isset( $_GET['jahr'] ) ? absint( $_GET['jahr'] ) : 0;
	// phpcs:enable
	$kats = afk_ns_kategorien();
	if ( ! isset( $kats[ $kat ] ) ) {
		$kat = '';
	}
	if ( $ff && ( ! taxonomy_exists( 'feuerwehr' ) || ! term_exists( $ff, 'feuerwehr' ) ) ) {
		$ff = '';
	}
	if ( $jahr < 1990 || $jahr > (int) gmdate( 'Y' ) + 1 ) {
		$jahr = 0;
	}
	return array(
		'kat'   => $kat,
		'ff'    => $ff,
		'jahr'  => $jahr,
		'aktiv' => ( '' !== $kat || '' !== $ff || $jahr ),
	);
}

/**
 * WP_Query-Argumente für die Filterwerte.
 *
 * @param array $f Filterwerte.
 * @return array
 */
function afk_ns_filter_query_args( $f ) {
	$args = array();
	if ( $f['kat'] ) {
		$args['category_name'] = afk_ns_kategorien()[ $f['kat'] ][0];
	}
	if ( $f['ff'] ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'feuerwehr',
				'field'    => 'slug',
				'terms'    => $f['ff'],
			),
		);
	}
	if ( $f['jahr'] ) {
		$args['year'] = $f['jahr'];
	}
	return $args;
}

/**
 * Jahre, aus denen es Beiträge gibt (neueste zuerst).
 *
 * @return int[]
 */
function afk_ns_jahre() {
	$cached = get_transient( 'afk_ns_jahre' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$jahre = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY 1 DESC" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	set_transient( 'afk_ns_jahre', $jahre, DAY_IN_SECONDS );
	return $jahre;
}
add_action( 'save_post_post', fn() => delete_transient( 'afk_ns_jahre' ) );

/**
 * Filterleiste ausgeben.
 *
 * @param array $opts action (URL), anchor, chips (bool), ff (bool).
 */
function afk_ns_filterleiste( $opts = array() ) {
	$opts   = wp_parse_args( $opts, array( 'action' => home_url( '/' ), 'anchor' => '', 'chips' => true, 'ff' => true ) );
	$f      = afk_ns_filter_werte();
	$action = $opts['action'] . ( $opts['anchor'] ? '#' . $opts['anchor'] : '' );
	?>
	<form class="filters" method="get" action="<?php echo esc_url( $action ); ?>" aria-label="Berichte filtern">
		<?php if ( $opts['chips'] ) : ?>
			<div class="filters__group filters__group--chips">
				<span class="filters__label" id="afk-lbl-kat">Kategorie</span>
				<div class="chips chips--scroll" role="group" aria-labelledby="afk-lbl-kat">
					<button class="chip<?php echo '' === $f['kat'] ? ' is-active' : ''; ?>" type="submit" name="kat" value="" aria-pressed="<?php echo '' === $f['kat'] ? 'true' : 'false'; ?>">Alle</button>
					<?php foreach ( afk_ns_kategorien() as $key => $kat ) : ?>
						<button class="chip<?php echo $key === $f['kat'] ? ' is-active' : ''; ?>" type="submit" name="kat" value="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo $key === $f['kat'] ? 'true' : 'false'; ?>"><?php echo esc_html( $kat[1] ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
		<?php elseif ( $f['kat'] ) : ?>
			<input type="hidden" name="kat" value="<?php echo esc_attr( $f['kat'] ); ?>">
		<?php endif; ?>

		<div class="filters__selects">
			<?php if ( $opts['ff'] && taxonomy_exists( 'feuerwehr' ) ) : ?>
				<div class="filters__group filters__group--select">
					<label class="filters__label" for="afk-f-ff">Feuerwehr</label>
					<select class="select" id="afk-f-ff" name="ff" data-autosubmit>
						<option value="">Alle Feuerwehren</option>
						<?php foreach ( afk_ns_wehren() as $w ) : ?>
							<option value="<?php echo esc_attr( $w['term']->slug ); ?>" <?php selected( $f['ff'], $w['term']->slug ); ?>><?php echo esc_html( $w['term']->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>
			<div class="filters__group filters__group--year">
				<label class="filters__label" for="afk-f-jahr">Jahr</label>
				<select class="select" id="afk-f-jahr" name="jahr" data-autosubmit>
					<option value="">Alle Jahre</option>
					<?php foreach ( afk_ns_jahre() as $j ) : ?>
						<option value="<?php echo esc_attr( $j ); ?>" <?php selected( $f['jahr'], $j ); ?>><?php echo esc_html( $j ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="filters__end">
			<button class="btn btn--ghost btn--sm filters__apply" type="submit">Anzeigen</button>
			<?php if ( $f['aktiv'] ) : ?>
				<a class="filters__reset" href="<?php echo esc_url( $action ); ?>">Zurücksetzen</a>
			<?php endif; ?>
		</div>
	</form>
	<?php
}

/**
 * Kachel für einen Bericht.
 *
 * @param int  $post_id Beitrag.
 * @param bool $gross   Große Kachel (mit Anrisstext).
 */
function afk_ns_kachel( $post_id, $gross = false ) {
	$hat_bild = has_post_thumbnail( $post_id );
	$kat      = afk_ns_kat_label( $post_id );
	$meta     = trim( $kat . ( $kat ? ' · ' : '' ) . get_the_date( 'd.m.Y', $post_id ) );
	?>
	<a class="tile<?php echo $hat_bild ? '' : ' tile--noimg'; ?>" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php
		if ( $hat_bild ) {
			echo get_the_post_thumbnail( $post_id, $gross ? 'large' : 'afk-tile', array( 'class' => 'tile__img', 'alt' => '', 'loading' => 'lazy', 'sizes' => $gross ? '(max-width: 700px) 100vw, 640px' : '(max-width: 700px) 100vw, 320px' ) );
		}
		?>
		<span class="tile__body">
			<span class="tile__meta"><?php echo esc_html( $meta ); ?></span>
			<span class="tile__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></span>
			<?php if ( $gross || ! $hat_bild ) : ?>
				<span class="tile__text"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), $gross ? 26 : 16, ' …' ) ); ?></span>
			<?php endif; ?>
		</span>
	</a>
	<?php
}

/**
 * Seite mit einer bestimmten Seitenvorlage finden (erste veröffentlichte).
 *
 * @param string $vorlage Dateiname der Vorlage.
 * @return string URL oder ''.
 */
function afk_ns_seite_mit_vorlage( $vorlage ) {
	$p = get_posts(
		array(
			'post_type'   => 'page',
			'numberposts' => 1,
			'meta_key'    => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $vorlage, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
		)
	);
	return $p ? get_permalink( $p[0] ) : '';
}

/**
 * Link zur Übersicht aller Berichte.
 *
 * @return string
 */
function afk_ns_alle_berichte_url() {
	$page = (int) get_option( 'page_for_posts' );
	if ( $page ) {
		return get_permalink( $page );
	}
	$news = get_category_by_slug( get_theme_mod( 'afk_kat_alle', 'news' ) );
	return $news ? get_category_link( $news ) : home_url( '/?s=' );
}

/**
 * Link zu einer Kategorie per Schlüssel.
 *
 * @param string $key einsatz|uebung|allgemein.
 * @return string
 */
function afk_ns_kat_url( $key ) {
	$kats = afk_ns_kategorien();
	$cat  = isset( $kats[ $key ] ) ? get_category_by_slug( $kats[ $key ][0] ) : null;
	return $cat ? get_category_link( $cat ) : afk_ns_alle_berichte_url();
}

/**
 * Link zur Übersicht der Feuerwehren (Seite mit Vorlage, sonst Startseite).
 *
 * @return string
 */
function afk_ns_feuerwehren_url() {
	$u = afk_ns_seite_mit_vorlage( 'page-feuerwehren.php' );
	return $u ? $u : home_url( '/#feuerwehren' );
}

/**
 * ID des Aufmachers: aus AFK Core, sonst der neueste Bericht.
 *
 * @return int
 */
function afk_ns_aufmacher_id() {
	if ( function_exists( 'afk_get_aufmacher_id' ) ) {
		return (int) afk_get_aufmacher_id();
	}
	$p = get_posts( array( 'numberposts' => 1, 'fields' => 'ids' ) );
	return $p ? (int) $p[0] : 0;
}

/**
 * Alarmierungen aus AFK Core (leer, wenn nicht eingerichtet).
 *
 * @param int $anzahl  Anzahl.
 * @param int $term_id Feuerwehr.
 * @return array
 */
function afk_ns_alarme( $anzahl = 4, $term_id = 0 ) {
	return function_exists( 'afk_alarmierungen' ) ? afk_alarmierungen( $anzahl, $term_id ) : array();
}

/**
 * Fallback-Hauptmenü, solange kein Menü zugewiesen ist.
 */
function afk_ns_menu_fallback() {
	$items = array(
		home_url( '/#aktuelles' )    => 'Aktuelles',
		afk_ns_kat_url( 'einsatz' )  => 'Einsätze',
		afk_ns_feuerwehren_url()     => 'Feuerwehren',
	);
	if ( function_exists( 'tribe_get_events_link' ) ) {
		$items[ tribe_get_events_link() ] = 'Termine';
	}
	$items[ home_url( '/#mitmachen' ) ] = 'Mitmachen';
	echo '<ul class="nav__list" id="afk-menu">';
	foreach ( $items as $url => $label ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Fallback-Fußzeilenmenü.
 */
function afk_ns_footer_fallback() {
	$items = array();
	foreach ( array( 'impressum' => 'Impressum', 'datenschutzerklaerung' => 'Datenschutz', 'kontakt' => 'Kontakt' ) as $slug => $label ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			$items[ get_permalink( $p ) ] = $label;
		}
	}
	$priv = get_privacy_policy_url();
	if ( $priv && ! in_array( 'Datenschutz', $items, true ) ) {
		$items[ $priv ] = 'Datenschutz';
	}
	$tg = get_theme_mod( 'afk_telegram', '' );
	if ( $tg ) {
		$items[ $tg ] = 'Telegram-Kanal';
	}
	echo '<ul class="footer-nav">';
	foreach ( $items as $url => $label ) {
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Seitenkopf für einfache Seiten (Übersichten, Seiten, Suche).
 *
 * @param string $titel   Titel.
 * @param string $eyebrow Kleine Zeile darüber.
 * @param string $text    Beschreibung.
 * @param bool   $narrow  Schmale Spalte.
 */
function afk_ns_seitenkopf( $titel, $eyebrow = '', $text = '', $narrow = false ) {
	?>
	<header class="page-head">
		<div class="wrap<?php echo $narrow ? ' wrap--narrow' : ''; ?>">
			<?php if ( $eyebrow ) : ?><p class="eyebrow"><?php echo wp_kses( $eyebrow, array( 'a' => array( 'href' => array() ) ) ); ?></p><?php endif; ?>
			<h1 class="page-head__title"><?php echo esc_html( $titel ); ?></h1>
			<?php if ( $text ) : ?><p class="page-head__desc"><?php echo esc_html( $text ); ?></p><?php endif; ?>
		</div>
	</header>
	<?php
}

/**
 * Eine Alarmierung als Listeneintrag ausgeben.
 *
 * @param array $a      Alarmierung aus AFK Core.
 * @param bool  $datum  Datum mit Jahr zeigen.
 */
function afk_ns_alarm_zeile( $a, $datum = false ) {
	$laufend = function_exists( 'afk_alarm_laufend' ) && afk_alarm_laufend( $a );
	$eigene  = isset( $a['eigene'] ) ? (array) $a['eigene'] : array();
	$andere  = array_values( array_diff( (array) ( $a['einheiten'] ?? array() ), $eigene ) );
	?>
	<li class="alarm">
		<span class="alarm__zeit"><?php echo esc_html( wp_date( $datum ? 'd.m.Y, H:i' : 'd.m., H:i', (int) $a['zeit'] ) ); ?></span>
		<span class="alarm__ereignis"><?php echo esc_html( $a['ereignis'] ? $a['ereignis'] : 'Alarmierung' ); ?></span>
		<span class="alarm__status<?php echo $laufend ? ' alarm__status--laufend' : ''; ?>"><?php echo esc_html( $a['status'] ); ?></span>
		<span class="alarm__einheiten">
			<?php if ( $eigene ) : ?><strong><?php echo esc_html( implode( ', ', $eigene ) ); ?></strong><?php endif; ?>
			<?php if ( $andere ) : ?><?php echo $eigene ? ' · ' : ''; ?><?php echo esc_html( implode( ', ', $andere ) ); ?><?php endif; ?>
		</span>
	</li>
	<?php
}

/**
 * Quellenhinweis unter Alarmierungslisten.
 */
function afk_ns_alarm_quelle() {
	$stand = (int) get_option( 'afk_alarm_stand', 0 );
	echo '<p class="quelle">Quelle: <a href="https://alarmierungen.feuerwehr-ktn.at/" rel="noopener" target="_blank">Kärntner Landesfeuerwehrverband</a>';
	if ( $stand ) {
		echo ' · Stand ' . esc_html( wp_date( 'd.m.Y, H:i', $stand ) ) . ' Uhr';
	}
	echo '. Alarmierungen sind keine Einsatzberichte; es werden keine Einsatzadressen veröffentlicht.</p>';
}

/**
 * Die Wehr, von der ein Bericht stammt: Abgleich von Bildquelle und
 * Funktion des Verfassers mit den Namen der beteiligten Wehren.
 *
 * @param WP_Term[] $wehren Beteiligte Wehren.
 * @param string    $quelle Bildquelle + Funktion.
 * @return WP_Term|null
 */
function afk_ns_berichtende_wehr( $wehren, $quelle ) {
	if ( ! $wehren ) {
		return null;
	}
	$norm = function ( $s ) {
		$s = mb_strtolower( (string) $s );
		$s = strtr( $s, array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' ) );
		return ' ' . trim( preg_replace( '/[^a-z0-9]+/', ' ', $s ) ) . ' ';
	};
	$q     = $norm( $quelle );
	$best  = null;
	$laeng = 0;
	foreach ( $wehren as $w ) {
		$n = trim( $norm( afk_ns_kurzname( $w->name ) ) );
		if ( '' !== $n && false !== strpos( $q, ' ' . $n . ' ' ) && strlen( $n ) > $laeng ) {
			$best  = $w;
			$laeng = strlen( $n );
		}
	}
	return $best ? $best : $wehren[0];
}
