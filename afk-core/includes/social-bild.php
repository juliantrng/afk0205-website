<?php
/**
 * Social Media: einheitliches Beitragsbild (1080 × 1350, 4:5) im Stil der Website.
 *
 * Foto vollflächig, unten der Titelkasten mit roter Kante (wie der Aufmacher der Website),
 * oben das Logo, ganz unten die Adresse der Website auf rotem Balken.
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

const AFK_SOCIAL_B = 1080;
const AFK_SOCIAL_H = 1350;

/**
 * Pfad zu einer mitgelieferten Datei (Schriften, Logo).
 *
 * @param string $datei Dateiname.
 * @return string
 */
function afk_social_asset( $datei ) {
	return AFK_CORE_DIR . 'assets/social/' . $datei;
}

/**
 * Kann dieser Server die Bilder erzeugen?
 *
 * @return true|WP_Error
 */
function afk_social_bild_moeglich() {
	if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagettftext' ) ) {
		return new WP_Error( 'afk_social_gd', 'Auf dem Server fehlt die PHP-Erweiterung GD mit FreeType – ohne sie können keine Bilder erzeugt werden.' );
	}
	return true;
}

/**
 * Art des Beitrags für Bild und Text.
 *
 * @param int $post_id Beitrag.
 * @return array{art:string,emoji:string,slug:string}
 */
function afk_social_art( $post_id ) {
	$slugs = wp_get_post_categories( $post_id, array( 'fields' => 'slugs' ) );
	if ( in_array( 'einsaetze', $slugs, true ) ) {
		return array( 'art' => 'Einsatz', 'emoji' => '🚒', 'slug' => 'einsatz' );
	}
	if ( in_array( 'uebungen', $slugs, true ) ) {
		return array( 'art' => 'Übung', 'emoji' => '🧯', 'slug' => 'uebung' );
	}
	return array( 'art' => 'Aus dem Abschnitt', 'emoji' => '📣', 'slug' => 'allgemein' );
}

/**
 * Text mit Laufweite zeichnen (GD kennt keine Laufweite).
 *
 * @param resource|GdImage $im     Bild.
 * @param float            $size   Schriftgröße (px).
 * @param int              $x      Links.
 * @param int              $y      Grundlinie.
 * @param int              $farbe  Farbe.
 * @param string           $font   Schriftdatei.
 * @param string           $text   Text.
 * @param float            $track  Zusätzlicher Abstand je Zeichen (px).
 * @return int Breite.
 */
function afk_social_text_gesperrt( $im, $size, $x, $y, $farbe, $font, $text, $track = 0 ) {
	$start = $x;
	foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $z ) {
		$box = imagettftext( $im, $size, 0, (int) round( $x ), $y, $farbe, $font, $z );
		$x  += ( $box[2] - $box[0] ) + $track;
		if ( ' ' === $z ) {
			$b  = imagettfbbox( $size, 0, $font, 'a a' );
			$b2 = imagettfbbox( $size, 0, $font, 'aa' );
			$x += ( $b[2] - $b[0] ) - ( $b2[2] - $b2[0] );
		}
	}
	return (int) round( $x - $start - $track );
}

/**
 * Breite eines Texts.
 *
 * @param float  $size Größe.
 * @param string $font Schrift.
 * @param string $text Text.
 * @return int
 */
function afk_social_breite( $size, $font, $text ) {
	$b = imagettfbbox( $size, 0, $font, $text );
	return (int) ( $b[2] - $b[0] );
}

/**
 * Text auf eine Breite umbrechen.
 *
 * @param string $text  Text.
 * @param float  $size  Größe.
 * @param string $font  Schrift.
 * @param int    $breite Maximale Breite.
 * @return string[] Zeilen.
 */
function afk_social_umbrechen( $text, $size, $font, $breite ) {
	$zeilen = array();
	$zeile  = '';
	foreach ( preg_split( '/\s+/u', trim( $text ) ) as $wort ) {
		$probe = '' === $zeile ? $wort : $zeile . ' ' . $wort;
		if ( '' !== $zeile && afk_social_breite( $size, $font, $probe ) > $breite ) {
			$zeilen[] = $zeile;
			$zeile    = $wort;
		} else {
			$zeile = $probe;
		}
	}
	if ( '' !== $zeile ) {
		$zeilen[] = $zeile;
	}
	return $zeilen;
}

/**
 * Bilddatei laden (JPEG, PNG, WebP).
 *
 * @param string $datei Pfad.
 * @return resource|GdImage|null
 */
function afk_social_laden( $datei ) {
	if ( ! $datei || ! is_readable( $datei ) ) {
		return null;
	}
	$im = @imagecreatefromstring( (string) file_get_contents( $datei ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	if ( ! $im ) {
		return null;
	}
	// Ausrichtung von Handyfotos beachten.
	if ( function_exists( 'exif_read_data' ) && preg_match( '/\.jpe?g$/i', $datei ) ) {
		$exif = @exif_read_data( $datei ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$o    = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
		if ( 3 === $o ) {
			$im = imagerotate( $im, 180, 0 );
		} elseif ( 6 === $o ) {
			$im = imagerotate( $im, -90, 0 );
		} elseif ( 8 === $o ) {
			$im = imagerotate( $im, 90, 0 );
		}
	}
	return $im;
}

/**
 * Bild für einen Beitrag erzeugen (oder das bereits erzeugte zurückgeben).
 *
 * @param int  $post_id Beitrag.
 * @param bool $neu     Neu erzeugen, auch wenn es schon eines gibt.
 * @return array{datei:string,url:string}|WP_Error
 */
function afk_social_bild( $post_id, $neu = false ) {
	$ok = afk_social_bild_moeglich();
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'afk_social_post', 'Beitrag nicht gefunden.' );
	}
	$foto_id = (int) get_post_thumbnail_id( $post );
	if ( ! $foto_id && preg_match( '/wp-image-(\d+)/', $post->post_content, $m ) && wp_attachment_is_image( (int) $m[1] ) ) {
		$foto_id = (int) $m[1]; // Kein Beitragsbild: erstes Foto aus dem Text.
	}
	$wehren  = function_exists( 'afk_get_feuerwehren' ) ? wp_list_pluck( afk_get_feuerwehren( $post->ID ), 'name' ) : array();
	$art     = afk_social_art( $post->ID );
	$titel   = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	$datum   = wp_date( 'j. F Y', get_post_timestamp( $post ) );

	$hash    = substr( md5( implode( '|', array( $titel, $foto_id, implode( ',', $wehren ), $art['slug'], $datum, AFK_CORE_VERSION ) ) ), 0, 10 );
	$uploads = wp_upload_dir();
	$ordner  = trailingslashit( $uploads['basedir'] ) . 'afk-social';
	$name    = $post->ID . '-' . $hash . '.jpg';
	$datei   = $ordner . '/' . $name;
	$url     = trailingslashit( $uploads['baseurl'] ) . 'afk-social/' . $name;
	if ( ! $neu && file_exists( $datei ) ) {
		return array( 'datei' => $datei, 'url' => $url );
	}
	wp_mkdir_p( $ordner );
	// Ältere Fassungen dieses Beitrags entfernen.
	foreach ( (array) glob( $ordner . '/' . $post->ID . '-*.jpg' ) as $alt ) {
		wp_delete_file( $alt );
	}

	$w  = AFK_SOCIAL_B;
	$h  = AFK_SOCIAL_H;
	$im = imagecreatetruecolor( $w, $h );
	imagealphablending( $im, true );
	$c_bg     = imagecolorallocate( $im, 14, 15, 17 );
	$c_surf   = imagecolorallocate( $im, 23, 25, 28 );
	$c_rot    = imagecolorallocate( $im, 193, 0, 0 );
	$c_gelb   = imagecolorallocate( $im, 249, 234, 55 );
	$c_weiss  = imagecolorallocate( $im, 255, 255, 255 );
	$c_grau   = imagecolorallocate( $im, 216, 213, 206 );
	imagefill( $im, 0, 0, $c_bg );

	// 1. Foto vollflächig (zugeschnitten), sonst dunkle Fläche mit roter Ecke.
	$foto = $foto_id ? afk_social_laden( wp_get_original_image_path( $foto_id ) ? wp_get_original_image_path( $foto_id ) : get_attached_file( $foto_id ) ) : null;
	if ( $foto ) {
		$fw    = imagesx( $foto );
		$fh    = imagesy( $foto );
		$scale = max( $w / $fw, $h / $fh );
		$sw    = (int) round( $w / $scale );
		$sh    = (int) round( $h / $scale );
		$sx    = (int) round( ( $fw - $sw ) / 2 );
		$sy    = (int) round( ( $fh - $sh ) / 3 ); // Eher oberes Drittel – Köpfe und Fahrzeuge bleiben drauf.
		imagecopyresampled( $im, $foto, 0, 0, $sx, $sy, $w, $h, $sw, $sh );
		imagedestroy( $foto );
	} else {
		imagefilledrectangle( $im, 0, 0, $w, $h, $c_surf );
		imagefilledpolygon( $im, array( $w - 420, 0, $w, 0, $w, 420 ), $c_rot );
	}

	// 2. Verläufe: oben leicht (Logo lesbar), unten kräftig (Textkasten).
	for ( $y = 0; $y < 260; $y++ ) {
		$a = (int) round( 127 - ( 70 * ( 1 - $y / 260 ) ) );
		imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 14, 15, 17, $a ) );
	}
	$ab = (int) ( $h * 0.38 );
	for ( $y = $ab; $y < $h; $y++ ) {
		$t = ( $y - $ab ) / ( $h - $ab );
		$a = (int) round( 127 - 127 * min( 1, $t * 1.35 ) * 0.9 );
		imageline( $im, 0, $y, $w, $y, imagecolorallocatealpha( $im, 14, 15, 17, $a ) );
	}

	// 3. Logo.
	$logo = afk_social_laden( afk_social_asset( 'logo-weiss.png' ) );
	if ( $logo ) {
		imagesavealpha( $logo, true );
		$lw = 400;
		$lh = (int) round( imagesy( $logo ) * $lw / imagesx( $logo ) );
		imagecopyresampled( $im, $logo, 64, 60, 0, 0, $lw, $lh, imagesx( $logo ), imagesy( $logo ) );
		imagedestroy( $logo );
	}

	// 4. Textblock von unten nach oben aufbauen. Größen in Pixeln (GD rechnet in Punkt: × 0,75).
	$f_titel = afk_social_asset( 'Oswald-Bold.ttf' );
	$f_label = afk_social_asset( 'Oswald-Medium.ttf' );
	$f_text  = afk_social_asset( 'SourceSans3-Semibold.ttf' );
	$pt      = function ( $px ) {
		return $px * 0.75;
	};
	$links  = 104;
	$breite = $w - $links - 64;
	$leiste = 92;

	// Titelgröße: höchstens vier Zeilen, keine Zeile breiter als der Kasten.
	$gross = mb_strtoupper( $titel, 'UTF-8' );
	foreach ( array( 112, 100, 90, 80, 72, 64, 56, 50, 44 ) as $px ) {
		$zeilen = afk_social_umbrechen( $gross, $pt( $px ), $f_titel, $breite );
		$passt  = true;
		foreach ( $zeilen as $z ) {
			if ( afk_social_breite( $pt( $px ), $f_titel, $z ) > $breite ) {
				$passt = false;
			}
		}
		if ( $passt && count( $zeilen ) <= 4 ) {
			break;
		}
	}
	if ( count( $zeilen ) > 5 ) {
		$zeilen    = array_slice( $zeilen, 0, 5 );
		$zeilen[4] = rtrim( $zeilen[4], ' ,.;:–-' ) . ' …';
	}
	$cap = imagettfbbox( $pt( $px ), 0, $f_titel, 'H' );
	$cap = abs( $cap[7] - $cap[1] ); // Höhe der Großbuchstaben in px.
	$zh  = (int) round( $cap * 1.32 );

	$wehr_text = '';
	if ( $wehren ) {
		$wehr_text = count( $wehren ) > 3 ? implode( ' · ', array_slice( $wehren, 0, 3 ) ) . ' · +' . ( count( $wehren ) - 3 ) : implode( ' · ', $wehren );
	}

	$y_unten       = $h - $leiste - 60;                       // Grundlinie der letzten Zeile im Kasten.
	$y_titel_ende  = $wehr_text ? $y_unten - 62 : $y_unten;   // Grundlinie der letzten Titelzeile.
	$y_titel_start = $y_titel_ende - ( count( $zeilen ) - 1 ) * $zh;
	$y_meta        = $y_titel_start - $cap - 30;

	// Rote Kante links über die ganze Höhe des Textblocks.
	imagefilledrectangle( $im, 64, $y_meta - 30, 64 + 9, $y_unten + 10, $c_rot );

	$label = mb_strtoupper( $art['art'], 'UTF-8' );
	$lb    = afk_social_text_gesperrt( $im, $pt( 34 ), $links, $y_meta, $c_gelb, $f_label, $label, 3 );
	afk_social_text_gesperrt( $im, $pt( 34 ), $links + $lb + 24, $y_meta, $c_grau, $f_label, mb_strtoupper( $datum, 'UTF-8' ), 3 );

	foreach ( $zeilen as $i => $z ) {
		imagettftext( $im, $pt( $px ), 0, $links, $y_titel_start + $i * $zh, $c_weiss, $f_titel, $z );
	}
	if ( $wehr_text ) {
		imagettftext( $im, $pt( 36 ), 0, $links, $y_unten, $c_grau, $f_text, $wehr_text );
	}

	// 5. Rote Leiste mit Adresse.
	imagefilledrectangle( $im, 0, $h - $leiste, $w, $h, $c_rot );
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$y_leiste = $h - (int) ( $leiste / 2 ) + 14;
	afk_social_text_gesperrt( $im, $pt( 36 ), 64, $y_leiste, $c_weiss, $f_label, mb_strtoupper( $host, 'UTF-8' ), 3 );
	$notruf = 'NOTRUF 122';
	$nw     = afk_social_text_gesperrt( $im, $pt( 36 ), -2000, -2000, $c_gelb, $f_label, $notruf, 3 ); // nur messen
	afk_social_text_gesperrt( $im, $pt( 36 ), $w - 64 - $nw, $y_leiste, $c_gelb, $f_label, $notruf, 3 );

	imageinterlace( $im, true );
	$ok = imagejpeg( $im, $datei, 88 );
	imagedestroy( $im );
	if ( ! $ok ) {
		return new WP_Error( 'afk_social_schreiben', 'Bild konnte nicht gespeichert werden (Schreibrechte im Upload-Ordner?).' );
	}
	return array( 'datei' => $datei, 'url' => $url );
}
