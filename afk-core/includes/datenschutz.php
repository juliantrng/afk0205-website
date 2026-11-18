<?php
/**
 * Datenschutz-Helfer – unabhängig vom Theme.
 *
 * 1. Fotos: Metadaten (GPS-Standort, Kamera, Seriennummer …) beim Hochladen aus dem
 *    Original entfernen. Werkzeuge → „Fotos bereinigen“ erledigt das für bereits
 *    hochgeladene Bilder.
 * 2. Eingebettete Inhalte (YouTube, Vimeo, Facebook, Instagram, Google Maps, X …):
 *    werden erst nach Klick geladen (2-Klick-Lösung); YouTube über youtube-nocookie.com.
 * 3. Kommentare und Pingbacks aus – die Website braucht sie nicht, sie würden IP-Adressen speichern.
 * 4. Emojis nicht von WordPress-Servern (s.w.org) nachladen.
 *
 * Abschalten einzelner Teile per Filter, z. B. add_filter( 'afk_ds_kommentare_aus', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * 1. Foto-Metadaten
 * =================================================================== */

/**
 * Metadaten aus einer JPEG-Datei entfernen (Farbprofil bleibt erhalten).
 *
 * @param string $file Pfad.
 * @return bool Erfolgreich bereinigt.
 */
function afk_ds_strip_jpeg( $file ) {
	if ( ! is_readable( $file ) || ! is_writable( $file ) ) {
		return false;
	}
	$type = wp_check_filetype( $file );
	if ( ! in_array( $type['type'], array( 'image/jpeg', 'image/jpg' ), true ) ) {
		return false;
	}

	if ( class_exists( 'Imagick' ) ) {
		try {
			$im      = new Imagick( $file );
			$profile = null;
			try {
				$profile = $im->getImageProfile( 'icc' );
			} catch ( Exception $e ) {
				$profile = null;
			}
			$quality = $im->getImageCompressionQuality();
			$im->autoOrient();
			$im->stripImage();
			if ( $profile ) {
				$im->profileImage( 'icc', $profile );
			}
			$im->setImageCompressionQuality( $quality > 0 ? $quality : 90 );
			$ok = $im->writeImage( $file );
			$im->clear();
			return (bool) $ok;
		} catch ( Exception $e ) {
			return false;
		}
	}

	// GD: speichert ohne Metadaten neu (Qualität 92). Vorher Ausrichtung übernehmen.
	if ( function_exists( 'imagecreatefromjpeg' ) ) {
		$img = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $img ) {
			return false;
		}
		if ( function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$o    = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
			$rot  = array( 3 => 180, 6 => -90, 8 => 90 );
			if ( isset( $rot[ $o ] ) ) {
				$r = imagerotate( $img, $rot[ $o ], 0 );
				if ( $r ) {
					imagedestroy( $img );
					$img = $r;
				}
			}
		}
		$ok = imagejpeg( $img, $file, 92 );
		imagedestroy( $img );
		return (bool) $ok;
	}
	return false;
}

/**
 * Hat die Datei GPS-Daten?
 *
 * @param string $file Pfad.
 * @return bool
 */
function afk_ds_hat_gps( $file ) {
	if ( ! function_exists( 'exif_read_data' ) || ! is_readable( $file ) ) {
		return false;
	}
	$exif = @exif_read_data( $file, 'GPS' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return ! empty( $exif['GPSLatitude'] ) || ! empty( $exif['GPSLongitude'] );
}

/**
 * Beim Hochladen bereinigen.
 *
 * @param array $upload Upload-Daten.
 * @return array
 */
function afk_ds_on_upload( $upload ) {
	if ( apply_filters( 'afk_ds_fotos_bereinigen', true ) && ! empty( $upload['file'] ) && empty( $upload['error'] ) ) {
		afk_ds_strip_jpeg( $upload['file'] );
	}
	return $upload;
}
add_filter( 'wp_handle_upload', 'afk_ds_on_upload' );
add_filter( 'wp_handle_sideload', 'afk_ds_on_upload' );

/**
 * Werkzeuge → Fotos bereinigen (für bereits hochgeladene Bilder).
 */
function afk_ds_tools_menu() {
	add_management_page( 'Fotos bereinigen', 'Fotos bereinigen', 'manage_options', 'afk-fotos', 'afk_ds_tools_page' );
}
add_action( 'admin_menu', 'afk_ds_tools_menu' );

/**
 * Seite „Fotos bereinigen“: verarbeitet je Klick 25 Bilder, merkt sich den Fortschritt.
 */
function afk_ds_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$letzte = (int) get_option( 'afk_ds_letzte_id', 0 );
	$meldung = '';

	if ( isset( $_POST['afk_ds_start'] ) && check_admin_referer( 'afk_ds_fotos' ) ) {
		if ( ! empty( $_POST['afk_ds_neu'] ) ) {
			$letzte = 0;
		}
		// Je Schritt 25 Bilder, fortlaufend nach ID.
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type='image/jpeg' AND ID > %d ORDER BY ID ASC LIMIT 25", $letzte ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n_gps = 0;
		$n_ok  = 0;
		foreach ( $ids as $id ) {
			$dateien = array();
			$orig    = wp_get_original_image_path( (int) $id );
			if ( $orig ) {
				$dateien[] = $orig;
			}
			$haupt = get_attached_file( (int) $id );
			if ( $haupt && $haupt !== $orig ) {
				$dateien[] = $haupt;
			}
			foreach ( $dateien as $f ) {
				if ( afk_ds_hat_gps( $f ) ) {
					++$n_gps;
				}
				if ( afk_ds_strip_jpeg( $f ) ) {
					++$n_ok;
				}
			}
			$letzte = (int) $id;
		}
		update_option( 'afk_ds_letzte_id', $letzte, false );
		$meldung = $ids
			? sprintf( '%d Bilder verarbeitet (%d Dateien bereinigt, davon %d mit GPS-Standort). Weiter mit dem nächsten Schritt.', count( $ids ), $n_ok, $n_gps )
			: 'Fertig – alle Fotos sind bereinigt.';
	}

	global $wpdb;
	$rest = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type='image/jpeg' AND ID > %d", $letzte ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	?>
	<div class="wrap">
		<h1>Fotos bereinigen</h1>
		<p>Handyfotos enthalten oft den <strong>GPS-Standort</strong>, an dem sie aufgenommen wurden, dazu Kameramodell und Seriennummer. In der Originaldatei bleiben diese Daten erhalten und sind für jeden abrufbar, der das Bild herunterlädt.</p>
		<p>Neue Fotos werden beim Hochladen automatisch bereinigt. Hier lassen sich die bereits vorhandenen Fotos nachträglich bereinigen. Das Bild selbst bleibt unverändert, entfernt werden nur die versteckten Zusatzdaten. Vorher ein Backup machen.</p>
		<?php if ( $meldung ) : ?><div class="notice notice-success"><p><?php echo esc_html( $meldung ); ?></p></div><?php endif; ?>
		<p><strong>Noch zu prüfen: <?php echo esc_html( number_format_i18n( $rest ) ); ?> Fotos.</strong></p>
		<form method="post">
			<?php wp_nonce_field( 'afk_ds_fotos' ); ?>
			<p><button class="button button-primary" name="afk_ds_start" value="1" <?php disabled( 0, $rest ); ?>>Nächste 25 Fotos bereinigen</button>
			<label style="margin-left:12px"><input type="checkbox" name="afk_ds_neu" value="1"> von vorne beginnen</label></p>
		</form>
	</div>
	<?php
}

/* =====================================================================
 * 2. Eingebettete Inhalte erst nach Klick
 * =================================================================== */

/**
 * Anbieter anhand der Adresse erkennen.
 *
 * @param string $html HTML mit iframe/Embed.
 * @return array|null array( name, url ) oder null.
 */
function afk_ds_anbieter( $html ) {
	$liste = array(
		'youtube'   => array( 'YouTube', '#(youtube\.com|youtube-nocookie\.com|youtu\.be)#i' ),
		'vimeo'     => array( 'Vimeo', '#vimeo\.com#i' ),
		'facebook'  => array( 'Facebook', '#facebook\.com|fb\.watch#i' ),
		'instagram' => array( 'Instagram', '#instagram\.com#i' ),
		'twitter'   => array( 'X (Twitter)', '#(twitter\.com|x\.com)#i' ),
		'maps'      => array( 'Google Maps', '#google\.[a-z.]+/maps|maps\.google#i' ),
		'spotify'   => array( 'Spotify', '#spotify\.com#i' ),
		'tiktok'    => array( 'TikTok', '#tiktok\.com#i' ),
	);
	foreach ( $liste as $key => $a ) {
		if ( preg_match( $a[1], $html ) ) {
			$url = '';
			if ( preg_match( '#(?:src|href)="(https?://[^"]+)"#i', $html, $m ) ) {
				$url = html_entity_decode( $m[1] );
			}
			return array( 'key' => $key, 'name' => $a[0], 'url' => $url );
		}
	}
	if ( false !== stripos( $html, '<iframe' ) ) {
		$host = '';
		if ( preg_match( '#src="https?://([^/"]+)#i', $html, $m ) ) {
			$host = $m[1];
		}
		if ( $host && false === stripos( $host, wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return array( 'key' => 'extern', 'name' => $host, 'url' => '' );
		}
	}
	return null;
}

/**
 * Embed-HTML in einen Platzhalter mit Klick zum Laden verpacken.
 *
 * @param string $html Original.
 * @return string
 */
function afk_ds_platzhalter( $html ) {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! apply_filters( 'afk_ds_zwei_klick', true ) ) {
		return $html;
	}
	if ( false !== strpos( $html, 'afk-embed' ) ) {
		return $html;
	}
	$a = afk_ds_anbieter( $html );
	if ( ! $a ) {
		return $html;
	}
	if ( 'youtube' === $a['key'] ) {
		$html = preg_replace( '#https?://(www\.)?youtube\.com/embed/#i', 'https://www.youtube-nocookie.com/embed/', $html );
	}
	// Skripte der Anbieter (Instagram, X, Facebook) erst nach Klick ausführen.
	$html = str_replace( array( '<script', '</script>' ), array( '<afk-script', '</afk-script>' ), $html );

	wp_enqueue_script( 'afk-embed', AFK_CORE_URL . 'assets/embed.js', array(), AFK_CORE_VERSION, true );

	$out  = '<div class="afk-embed afk-embed--' . esc_attr( $a['key'] ) . '">';
	$out .= '<div class="afk-embed__hinweis">';
	$out .= '<p class="afk-embed__titel">Inhalt von ' . esc_html( $a['name'] ) . '</p>';
	$out .= '<p class="afk-embed__text">Beim Laden werden Daten, darunter deine IP-Adresse, an ' . esc_html( $a['name'] ) . ' übertragen. Mehr dazu in der <a href="' . esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/datenschutzerklaerung/' ) ) . '">Datenschutzerklärung</a>.</p>';
	$out .= '<p class="afk-embed__aktionen"><button type="button" class="afk-embed__btn">Inhalt laden</button>';
	if ( $a['url'] ) {
		$out .= ' <a class="afk-embed__link" href="' . esc_url( preg_replace( '#youtube-nocookie\.com/embed/#', 'youtube.com/watch?v=', $a['url'] ) ) . '" rel="noopener" target="_blank">Direkt bei ' . esc_html( $a['name'] ) . ' öffnen</a>';
	}
	$out .= '</p></div>';
	$out .= '<template class="afk-embed__inhalt">' . $html . '</template>';
	$out .= '</div>';
	return $out;
}
add_filter( 'embed_oembed_html', 'afk_ds_platzhalter', 20 );

/**
 * Direkt in den Inhalt kopierte iframes (z. B. Google Maps) ebenfalls verpacken.
 *
 * @param string $content Inhalt.
 * @return string
 */
function afk_ds_content_iframes( $content ) {
	if ( false === stripos( $content, '<iframe' ) ) {
		return $content;
	}
	// Bereits verpackte Inhalte vorübergehend herausnehmen.
	$geparkt = array();
	$content = preg_replace_callback(
		'#<template class="afk-embed__inhalt">.*?</template>#is',
		function ( $m ) use ( &$geparkt ) {
			$geparkt[] = $m[0];
			return '%%AFKTPL' . ( count( $geparkt ) - 1 ) . '%%';
		},
		$content
	);
	$content = preg_replace_callback(
		'#<iframe\b[^>]*>.*?</iframe>#is',
		function ( $m ) {
			return afk_ds_platzhalter( $m[0] );
		},
		$content
	);
	foreach ( $geparkt as $i => $tpl ) {
		$content = str_replace( '%%AFKTPL' . $i . '%%', $tpl, $content );
	}
	return $content;
}
add_filter( 'the_content', 'afk_ds_content_iframes', 20 );

/* =====================================================================
 * 3. Kommentare und Pingbacks aus
 * =================================================================== */

if ( apply_filters( 'afk_ds_kommentare_aus', true ) ) {
	add_filter( 'comments_open', '__return_false', 20 );
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'comments_array', '__return_empty_array', 10 );
	add_filter( 'feed_links_show_comments_feed', '__return_false' );
	add_filter( 'xmlrpc_methods', function ( $m ) {
		unset( $m['pingback.ping'], $m['pingback.extensions.getPingbacks'] );
		return $m;
	} );
	add_filter( 'wp_headers', function ( $h ) {
		unset( $h['X-Pingback'] );
		return $h;
	} );
	add_action( 'admin_menu', function () {
		remove_menu_page( 'edit-comments.php' );
	} );
	add_action( 'init', function () {
		remove_post_type_support( 'post', 'comments' );
		remove_post_type_support( 'post', 'trackbacks' );
		remove_post_type_support( 'page', 'comments' );
	}, 100 );
	add_action( 'wp_before_admin_bar_render', function () {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'comments' );
	} );
}

/* =====================================================================
 * 4. Emojis lokal (Browser-Schrift statt Bilder von s.w.org)
 * =================================================================== */

function afk_ds_emojis_aus() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	add_filter( 'tiny_mce_plugins', function ( $p ) {
		return is_array( $p ) ? array_diff( $p, array( 'wpemoji' ) ) : array();
	} );
	add_filter( 'wp_resource_hints', function ( $urls, $rel ) {
		if ( 'dns-prefetch' === $rel ) {
			$urls = array_filter( $urls, function ( $u ) {
				return false === strpos( is_array( $u ) ? ( $u['href'] ?? '' ) : $u, 's.w.org' );
			} );
		}
		return $urls;
	}, 10, 2 );
}
if ( apply_filters( 'afk_ds_emojis_aus', true ) ) {
	add_action( 'init', 'afk_ds_emojis_aus' );
}
