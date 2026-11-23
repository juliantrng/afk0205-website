<?php
/**
 * Beiträge abgleichen: holt auf Knopfdruck neue und geänderte Beiträge einer anderen
 * WordPress-Seite (der bisherigen Website) auf diese Seite (die neue Website / Staging).
 *
 * Nur in eine Richtung, nur manuell (Werkzeuge → Beiträge abgleichen). Solange keine Quelle
 * eingetragen ist, tut das Modul nichts – auf der echten Website bleibt es also wirkungslos.
 *
 * Übernommen werden Titel, Text, Auszug (nur mit Anwendungspasswort), Datum, Kategorien,
 * Schlagwörter, Beitragsbild und die im Text verwendeten Bilder. Die Feuerwehr wird aus den
 * Schlagwörtern „FF …“ / „BtF …“ abgeleitet, solange der Beitrag hier noch keine hat.
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gespeicherte Einstellungen.
 *
 * @return array{quelle:string,benutzer:string,passwort:string,letzter:int}
 */
function afk_abg_einstellungen() {
	$o = get_option( 'afk_abgleich', array() );
	return array(
		'quelle'   => isset( $o['quelle'] ) ? untrailingslashit( (string) $o['quelle'] ) : '',
		'benutzer' => isset( $o['benutzer'] ) ? (string) $o['benutzer'] : '',
		'passwort' => isset( $o['passwort'] ) ? (string) $o['passwort'] : '',
		'letzter'  => isset( $o['letzter'] ) ? (int) $o['letzter'] : 0,
	);
}

/**
 * Einstellungen speichern (nur übergebene Schlüssel ändern sich).
 *
 * @param array $neu Neue Werte.
 */
function afk_abg_speichern( $neu ) {
	update_option( 'afk_abgleich', array_merge( afk_abg_einstellungen(), $neu ), false );
}

/**
 * Gibt es ein Anwendungspasswort? Dann kommen Beiträge im Original (Blöcke, Auszug).
 *
 * @return bool
 */
function afk_abg_mit_login() {
	$e = afk_abg_einstellungen();
	return '' !== $e['benutzer'] && '' !== $e['passwort'];
}

/**
 * Gründe, warum gerade nicht abgeglichen werden darf.
 *
 * @return string[]
 */
function afk_abg_sperren() {
	$e      = afk_abg_einstellungen();
	$gruende = array();
	if ( '' === $e['quelle'] ) {
		$gruende[] = 'Es ist noch keine Quelle eingetragen.';
	} elseif ( untrailingslashit( set_url_scheme( $e['quelle'], 'https' ) ) === untrailingslashit( set_url_scheme( home_url(), 'https' ) ) ) {
		$gruende[] = 'Die Quelle ist diese Website selbst. Eingetragen werden muss die bisherige Website.';
	}
	foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
		if ( false !== stripos( $plugin, 'fs-poster' ) ) {
			$gruende[] = 'FS Poster ist aktiv – übernommene Beiträge würden sonst ein zweites Mal auf Social Media gepostet. Bitte unter Plugins deaktivieren.';
		}
		if ( 0 === stripos( $plugin, 'mailpoet' ) ) {
			$gruende[] = 'MailPoet ist aktiv – übernommene Beiträge könnten sonst Newsletter-Mails auslösen. Bitte unter Plugins deaktivieren.';
		}
	}
	return $gruende;
}

/**
 * Anfrage an die REST-Schnittstelle der Quelle.
 *
 * @param string $pfad  z. B. „wp/v2/posts“.
 * @param array  $args  Abfrageparameter.
 * @param bool   $login Mit Anwendungspasswort anmelden (falls hinterlegt).
 * @return array|WP_Error array( 'daten' => mixed, 'gesamt' => int, 'seiten' => int )
 */
function afk_abg_get( $pfad, $args = array(), $login = true ) {
	$e   = afk_abg_einstellungen();
	$url = add_query_arg( $args, $e['quelle'] . '/wp-json/' . ltrim( $pfad, '/' ) );
	$req = array( 'timeout' => 30 );
	if ( $login && afk_abg_mit_login() ) {
		$req['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( $e['benutzer'] . ':' . $e['passwort'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
	$res = wp_remote_get( $url, $req );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code ) {
		$msg = is_array( $body ) && ! empty( $body['message'] ) ? wp_strip_all_tags( $body['message'] ) : 'HTTP ' . $code;
		if ( 401 === $code || 403 === $code ) {
			$msg .= ' (Benutzername oder Anwendungspasswort prüfen)';
		}
		return new WP_Error( 'afk_abg_http', $msg );
	}
	return array(
		'daten'  => $body,
		'gesamt' => (int) wp_remote_retrieve_header( $res, 'x-wp-total' ),
		'seiten' => max( 1, (int) wp_remote_retrieve_header( $res, 'x-wp-totalpages' ) ),
	);
}

/**
 * Lokaler Beitrag zu einem Beitrag der Quelle (zuerst über die gemerkte Quell-ID, sonst über
 * die Adresse – die Staging-Kopie hat dieselben Adressen wie das Original).
 *
 * @param int    $rid  ID in der Quelle.
 * @param string $slug Adresse.
 * @return WP_Post|null
 */
function afk_abg_lokal( $rid, $slug ) {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'meta_key'       => '_afk_quelle_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (int) $rid, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	if ( $ids ) {
		return get_post( $ids[0] );
	}
	if ( $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $p ) {
			return $p;
		}
	}
	return null;
}

/**
 * Wann wurde der lokale Beitrag zuletzt aus der Quelle übernommen bzw. bearbeitet (GMT)?
 *
 * @param WP_Post $p Lokaler Beitrag.
 * @return int Zeitstempel.
 */
function afk_abg_lokal_stand( $p ) {
	$q = get_post_meta( $p->ID, '_afk_quelle_geaendert', true );
	return $q ? (int) strtotime( $q . ' UTC' ) : (int) strtotime( $p->post_modified_gmt . ' UTC' );
}

/**
 * Liste der Beiträge der Quelle, die seit einem Datum geändert wurden, mit Vergleich.
 *
 * @param string $seit Datum (Y-m-d).
 * @return array|WP_Error Liste von array( rid, titel, link, datum, geaendert, status, lokal_id ).
 */
function afk_abg_liste( $seit ) {
	$liste = array();
	$seite = 1;
	do {
		$r = afk_abg_get(
			'wp/v2/posts',
			array(
				'modified_after' => gmdate( 'Y-m-d\TH:i:s', strtotime( $seit . ' 00:00:00' ) ),
				'per_page'       => 100,
				'page'           => $seite,
				'orderby'        => 'modified',
				'order'          => 'desc',
				'_fields'        => 'id,slug,title,link,date,modified_gmt',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		foreach ( (array) $r['daten'] as $p ) {
			$lokal  = afk_abg_lokal( (int) $p['id'], (string) $p['slug'] );
			$status = 'neu';
			if ( $lokal ) {
				$status = strtotime( $p['modified_gmt'] . ' UTC' ) > afk_abg_lokal_stand( $lokal ) ? 'geaendert' : 'aktuell';
			}
			$liste[] = array(
				'rid'       => (int) $p['id'],
				'titel'     => html_entity_decode( wp_strip_all_tags( $p['title']['rendered'] ), ENT_QUOTES, 'UTF-8' ),
				'link'      => (string) $p['link'],
				'datum'     => (string) $p['date'],
				'geaendert' => (string) $p['modified_gmt'],
				'status'    => $status,
				'lokal_id'  => $lokal ? (int) $lokal->ID : 0,
			);
		}
		++$seite;
	} while ( $seite <= $r['seiten'] && $seite <= 10 );
	return $liste;
}

/* =====================================================================
 * Bilder
 * =================================================================== */

/**
 * Lokales Bild zu einem Bild der Quelle, ohne etwas herunterzuladen.
 *
 * @param int        $rmid  Medien-ID in der Quelle.
 * @param array|null $media Medien-Daten der Quelle (falls schon geladen).
 * @return int Lokale ID oder 0.
 */
function afk_abg_anhang_lokal( $rmid, $media = null ) {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_afk_quelle_id' AND meta_value = %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment') LIMIT 1", (string) $rmid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $id ) {
		return $id;
	}
	// Bilder aus der Zeit vor der Staging-Kopie liegen mit gleichem Pfad schon hier.
	if ( $media && ! empty( $media['media_details']['file'] ) ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1", $media['media_details']['file'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $id ) {
			update_post_meta( $id, '_afk_quelle_id', (int) $rmid );
			return $id;
		}
	}
	return 0;
}

/**
 * Medien-Daten der Quelle (für die Dauer eines Abgleichs zwischengespeichert).
 *
 * @param int $rmid Medien-ID.
 * @return array|null
 */
function afk_abg_media( $rmid ) {
	$key = 'afk_abg_m_' . (int) $rmid;
	$m   = get_transient( $key );
	if ( false === $m ) {
		$r = afk_abg_get( 'wp/v2/media/' . (int) $rmid, array( '_fields' => 'id,source_url,media_details,alt_text,title,caption' ), false );
		$m = is_wp_error( $r ) ? array() : (array) $r['daten'];
		set_transient( $key, $m, HOUR_IN_SECONDS );
	}
	return $m ? $m : null;
}

/**
 * Bild aus der Quelle herunterladen und in die Mediathek legen (die GPS-Bereinigung beim
 * Hochladen greift dabei automatisch).
 *
 * @param int $rmid    Medien-ID in der Quelle.
 * @param int $post_id Beitrag, dem das Bild zugeordnet wird.
 * @return int|WP_Error Lokale ID.
 */
function afk_abg_anhang_holen( $rmid, $post_id = 0 ) {
	$media = afk_abg_media( $rmid );
	if ( ! $media || empty( $media['source_url'] ) ) {
		return new WP_Error( 'afk_abg_media', 'Bild ' . (int) $rmid . ' ist in der Quelle nicht abrufbar.' );
	}
	$id = afk_abg_anhang_lokal( $rmid, $media );
	if ( $id ) {
		return $id;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $media['source_url'], 60 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$titel = isset( $media['title']['rendered'] ) ? html_entity_decode( wp_strip_all_tags( $media['title']['rendered'] ), ENT_QUOTES, 'UTF-8' ) : '';
	$id    = media_handle_sideload(
		array(
			'name'     => wp_basename( wp_parse_url( $media['source_url'], PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		),
		(int) $post_id,
		$titel
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $id;
	}
	update_post_meta( $id, '_afk_quelle_id', (int) $rmid );
	if ( ! empty( $media['alt_text'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $media['alt_text'] ) );
	}
	if ( ! empty( $media['caption']['rendered'] ) ) {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => trim( wp_strip_all_tags( $media['caption']['rendered'] ) ),
			)
		);
	}
	return (int) $id;
}

/**
 * Bild-IDs der Quelle, die ein Beitragstext verwendet.
 *
 * @param string $html Text.
 * @return int[]
 */
function afk_abg_bild_ids( $html ) {
	$ids = array();
	if ( preg_match_all( '/wp-image-(\d+)|data-id="(\d+)"/', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $t ) {
			$ids[] = (int) ( '' !== $t[1] ? $t[1] : $t[2] );
		}
	}
	if ( preg_match_all( '/<!-- wp:[a-z0-9\/-]+ (\{.*?\}) \/?-->/', $html, $m ) ) {
		foreach ( $m[1] as $json ) {
			$a = json_decode( $json, true );
			if ( ! is_array( $a ) ) {
				continue;
			}
			if ( ! empty( $a['id'] ) && is_numeric( $a['id'] ) ) {
				$ids[] = (int) $a['id'];
			}
			if ( ! empty( $a['ids'] ) && is_array( $a['ids'] ) ) {
				$ids = array_merge( $ids, array_map( 'intval', $a['ids'] ) );
			}
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Bild-Adressen und -IDs im Text auf die lokalen Bilder umschreiben.
 *
 * @param string $html Text.
 * @param array  $map  Quell-ID => lokale ID.
 * @return string
 */
function afk_abg_bilder_umschreiben( $html, $map ) {
	if ( ! $map ) {
		return $html;
	}
	$urls = array();
	foreach ( $map as $rmid => $lid ) {
		$media = afk_abg_media( $rmid );
		if ( ! $media ) {
			continue;
		}
		$voll = wp_get_attachment_url( $lid );
		if ( ! empty( $media['source_url'] ) && $voll ) {
			$urls[ $media['source_url'] ] = $voll;
		}
		if ( ! empty( $media['media_details']['sizes'] ) ) {
			foreach ( $media['media_details']['sizes'] as $name => $s ) {
				if ( empty( $s['source_url'] ) || isset( $urls[ $s['source_url'] ] ) ) {
					continue;
				}
				$lokal                    = wp_get_attachment_image_url( $lid, $name );
				$urls[ $s['source_url'] ] = $lokal ? $lokal : $voll;
			}
		}
	}
	$html = strtr( $html, $urls );

	$html = preg_replace_callback(
		'/(wp-image-|data-id=")(\d+)/',
		function ( $m ) use ( $map ) {
			return $m[1] . ( isset( $map[ (int) $m[2] ] ) ? $map[ (int) $m[2] ] : $m[2] );
		},
		$html
	);
	// IDs in den Block-Angaben (<!-- wp:image {"id":123} -->, Galerien mit "ids").
	return preg_replace_callback(
		'/<!-- wp:[a-z0-9\/-]+ \{.*?\} \/?-->/',
		function ( $m ) use ( $map ) {
			$s = preg_replace_callback(
				'/"id":(\d+)/',
				function ( $n ) use ( $map ) {
					return '"id":' . ( isset( $map[ (int) $n[1] ] ) ? $map[ (int) $n[1] ] : $n[1] );
				},
				$m[0]
			);
			return preg_replace_callback(
				'/"ids":\[([\d,]*)\]/',
				function ( $n ) use ( $map ) {
					$ids = array_map(
						function ( $i ) use ( $map ) {
							return isset( $map[ (int) $i ] ) ? $map[ (int) $i ] : (int) $i;
						},
						array_filter( explode( ',', $n[1] ), 'strlen' )
					);
					return '"ids":[' . implode( ',', $ids ) . ']';
				},
				$s
			);
		},
		$html
	);
}

/* =====================================================================
 * Begriffe
 * =================================================================== */

/**
 * Kategorien/Schlagwörter der Quelle lokal finden oder anlegen.
 *
 * @param array  $terms    Eingebettete Begriffe der Quelle.
 * @param string $taxonomy category|post_tag.
 * @return int[]
 */
function afk_abg_begriffe( $terms, $taxonomy ) {
	$ids = array();
	foreach ( $terms as $t ) {
		if ( empty( $t['taxonomy'] ) || $taxonomy !== $t['taxonomy'] || empty( $t['slug'] ) ) {
			continue;
		}
		$lokal = get_term_by( 'slug', $t['slug'], $taxonomy );
		if ( ! $lokal ) {
			$neu = wp_insert_term( html_entity_decode( $t['name'], ENT_QUOTES, 'UTF-8' ), $taxonomy, array( 'slug' => $t['slug'] ) );
			if ( is_wp_error( $neu ) ) {
				continue;
			}
			$ids[] = (int) $neu['term_id'];
		} else {
			$ids[] = (int) $lokal->term_id;
		}
	}
	return $ids;
}

/**
 * Wehr-Name für den Vergleich: ohne „FF“/„BtF“, Akzente, Leer- und Satzzeichen.
 *
 * @param string $s Name.
 * @return string
 */
function afk_abg_norm( $s ) {
	$s = strtolower( remove_accents( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ) );
	$s = preg_replace( '/^\s*(ff|btf|freiwillige feuerwehr|betriebsfeuerwehr)\b\.?\s*/', '', $s );
	return preg_replace( '/[^a-z0-9]/', '', $s );
}

/**
 * Feuerwehren aus den Schlagwörtern „FF …“ / „BtF …“ ableiten.
 *
 * @param string[] $namen Schlagwort-Namen.
 * @return int[] Begriffs-IDs der Taxonomie „feuerwehr“.
 */
function afk_abg_wehren_aus_schlagwoertern( $namen ) {
	$wehren = get_terms(
		array(
			'taxonomy'   => 'feuerwehr',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $wehren ) ) {
		return array();
	}
	$vergleich = array();
	foreach ( $wehren as $w ) {
		if ( 0 === (int) $w->parent ) {
			continue;
		}
		$vergleich[ (int) $w->term_id ][] = afk_abg_norm( $w->name );
		$alarm                            = (string) get_term_meta( $w->term_id, 'afk_alarmname', true );
		if ( '' !== trim( $alarm ) ) {
			$vergleich[ (int) $w->term_id ][] = afk_abg_norm( $alarm );
		}
	}
	$treffer = array();
	foreach ( $namen as $name ) {
		if ( ! preg_match( '/^\s*(ff|btf)\b/i', $name ) ) {
			continue;
		}
		$n = afk_abg_norm( $name );
		if ( strlen( $n ) < 4 ) {
			continue;
		}
		foreach ( $vergleich as $tid => $varianten ) {
			foreach ( $varianten as $v ) {
				// Gleich, Kurzform („St. Peter“ → „St. Peter ob Radenthein“) oder Tippfehler.
				if ( $n === $v || 0 === strpos( $v, $n ) || ( strlen( $n ) > 7 && levenshtein( $n, $v ) <= 2 ) ) {
					$treffer[] = $tid;
					break 2;
				}
			}
		}
	}
	return array_values( array_unique( $treffer ) );
}

/* =====================================================================
 * Einen Beitrag übernehmen (in Schritten, damit große Galerien nicht ins Zeitlimit laufen)
 * =================================================================== */

/**
 * Ein Schritt für einen Beitrag: lädt höchstens ein paar Bilder; sind alle da, wird der
 * Beitrag geschrieben.
 *
 * @param int $rid ID in der Quelle.
 * @return array|WP_Error array( 'fertig' => bool, 'text' => string, 'lokal_id' => int )
 */
function afk_abg_schritt( $rid ) {
	$key = 'afk_abg_p_' . (int) $rid;
	$p   = get_transient( $key );
	if ( false === $p ) {
		$r = afk_abg_get(
			'wp/v2/posts/' . (int) $rid,
			array(
				'context' => afk_abg_mit_login() ? 'edit' : 'view',
				'_embed'  => 'author,wp:term',
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$p = (array) $r['daten'];
		set_transient( $key, $p, 30 * MINUTE_IN_SECONDS );
	}
	$roh     = afk_abg_mit_login() && isset( $p['content']['raw'] );
	$inhalt  = $roh ? (string) $p['content']['raw'] : (string) $p['content']['rendered'];
	$lokal   = afk_abg_lokal( (int) $p['id'], (string) $p['slug'] );
	$bild_id = (int) $p['featured_media'];

	// 1. Bilder: Beitragsbild + Bilder im Text.
	$benoetigt = afk_abg_bild_ids( $inhalt );
	if ( $bild_id ) {
		array_unshift( $benoetigt, $bild_id );
	}
	$benoetigt = array_values( array_unique( $benoetigt ) );
	$map       = array();
	$geladen   = 0;
	$fehler    = array();
	@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	foreach ( $benoetigt as $rmid ) {
		$lid = afk_abg_anhang_lokal( $rmid, afk_abg_media( $rmid ) );
		if ( ! $lid && get_transient( 'afk_abg_mf_' . (int) $rmid ) ) {
			continue; // Schon gescheitert – Text behält die Adresse der Quelle.
		}
		if ( ! $lid ) {
			if ( $geladen >= 3 ) {
				continue; // Rest im nächsten Schritt.
			}
			$lid = afk_abg_anhang_holen( $rmid, $lokal ? $lokal->ID : 0 );
			++$geladen;
			if ( is_wp_error( $lid ) ) {
				$fehler[] = $lid->get_error_message();
				// Merken, damit es nicht endlos versucht wird; der Text behält dann die alte Adresse.
				set_transient( 'afk_abg_mf_' . (int) $rmid, 1, HOUR_IN_SECONDS );
				continue;
			}
		}
		$map[ (int) $rmid ] = (int) $lid;
	}
	$offen = array_filter(
		$benoetigt,
		function ( $rmid ) use ( $map ) {
			return ! isset( $map[ $rmid ] ) && ! get_transient( 'afk_abg_mf_' . (int) $rmid );
		}
	);
	if ( $offen ) {
		return array(
			'fertig'   => false,
			'text'     => sprintf( 'Bilder: %d von %d', count( $map ), count( $benoetigt ) ),
			'lokal_id' => $lokal ? (int) $lokal->ID : 0,
		);
	}

	// 2. Beitrag schreiben.
	$embedded = isset( $p['_embedded'] ) ? $p['_embedded'] : array();
	$terms    = array();
	if ( ! empty( $embedded['wp:term'] ) ) {
		foreach ( $embedded['wp:term'] as $gruppe ) {
			$terms = array_merge( $terms, (array) $gruppe );
		}
	}
	$autor = get_current_user_id();
	if ( ! empty( $embedded['author'][0]['slug'] ) ) {
		$u = get_user_by( 'slug', $embedded['author'][0]['slug'] );
		if ( $u ) {
			$autor = (int) $u->ID;
		}
	}
	$titel = $roh && isset( $p['title']['raw'] ) ? (string) $p['title']['raw'] : html_entity_decode( (string) $p['title']['rendered'], ENT_QUOTES, 'UTF-8' );
	$daten = array(
		'post_type'      => 'post',
		'post_title'     => $titel,
		'post_content'   => afk_abg_bilder_umschreiben( $inhalt, $map ),
		'post_status'    => in_array( $p['status'] ?? 'publish', array( 'publish', 'future' ), true ) ? $p['status'] : 'publish',
		'post_date'      => str_replace( 'T', ' ', (string) $p['date'] ),
		'post_date_gmt'  => str_replace( 'T', ' ', (string) $p['date_gmt'] ),
		'post_name'      => (string) $p['slug'],
		'post_author'    => $autor,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);
	if ( $roh && isset( $p['excerpt']['raw'] ) ) {
		$daten['post_excerpt'] = (string) $p['excerpt']['raw'];
	}
	if ( $lokal ) {
		$daten['ID'] = (int) $lokal->ID;
	}

	// Keine Pingbacks/Trackbacks an die Quelle oder andere Seiten auslösen.
	remove_action( 'publish_post', '_publish_post_hook', 5 );
	$id = $lokal ? wp_update_post( wp_slash( $daten ), true ) : wp_insert_post( wp_slash( $daten ), true );
	add_action( 'publish_post', '_publish_post_hook', 5, 1 );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	wp_set_post_terms( $id, afk_abg_begriffe( $terms, 'category' ), 'category' );
	$tag_ids = afk_abg_begriffe( $terms, 'post_tag' );
	wp_set_post_terms( $id, $tag_ids, 'post_tag' );
	$wehr_text = '';
	if ( taxonomy_exists( 'feuerwehr' ) && ! wp_get_object_terms( $id, 'feuerwehr', array( 'fields' => 'ids' ) ) ) {
		$namen = array();
		foreach ( $terms as $t ) {
			if ( isset( $t['taxonomy'] ) && 'post_tag' === $t['taxonomy'] ) {
				$namen[] = html_entity_decode( $t['name'], ENT_QUOTES, 'UTF-8' );
			}
		}
		$wehren = afk_abg_wehren_aus_schlagwoertern( $namen );
		if ( $wehren ) {
			wp_set_object_terms( $id, $wehren, 'feuerwehr' );
			$wehr_text = ', Feuerwehr: ' . implode( ', ', wp_list_pluck( array_map( 'get_term', $wehren ), 'name' ) );
		} else {
			$wehr_text = ', keine Feuerwehr erkannt';
		}
	}
	if ( $bild_id && isset( $map[ $bild_id ] ) ) {
		set_post_thumbnail( $id, $map[ $bild_id ] );
		wp_update_post(
			array(
				'ID'          => $map[ $bild_id ],
				'post_parent' => $id,
			)
		);
	}
	update_post_meta( $id, '_afk_quelle_id', (int) $p['id'] );
	update_post_meta( $id, '_afk_quelle_geaendert', str_replace( 'T', ' ', (string) $p['modified_gmt'] ) );
	delete_transient( $key );

	return array(
		'fertig'   => true,
		'text'     => ( $lokal ? 'aktualisiert' : 'neu angelegt' ) . ( $map ? ', ' . count( $map ) . ' Bilder' : '' ) . $wehr_text . ( $fehler ? ' – ' . implode( '; ', $fehler ) : '' ),
		'lokal_id' => (int) $id,
	);
}

/**
 * AJAX: ein Schritt.
 */
function afk_abg_ajax() {
	check_ajax_referer( 'afk_abgleich', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Keine Berechtigung.' );
	}
	$sperren = afk_abg_sperren();
	if ( $sperren ) {
		wp_send_json_error( implode( ' ', $sperren ) );
	}
	$rid = isset( $_POST['rid'] ) ? absint( $_POST['rid'] ) : 0;
	$r   = afk_abg_schritt( $rid );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( $r->get_error_message() );
	}
	if ( $r['fertig'] && $r['lokal_id'] ) {
		$r['link'] = get_permalink( $r['lokal_id'] );
		$r['edit'] = get_edit_post_link( $r['lokal_id'], 'raw' );
	}
	wp_send_json_success( $r );
}
add_action( 'wp_ajax_afk_abgleich', 'afk_abg_ajax' );

/**
 * AJAX: Abgleich abgeschlossen → Zeitpunkt merken.
 */
function afk_abg_ajax_ende() {
	check_ajax_referer( 'afk_abgleich', 'nonce' );
	if ( current_user_can( 'manage_options' ) ) {
		afk_abg_speichern( array( 'letzter' => time() ) );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_afk_abgleich_ende', 'afk_abg_ajax_ende' );

/* =====================================================================
 * Werkzeuge → Beiträge abgleichen
 * =================================================================== */

/**
 * Menüpunkt.
 */
function afk_abg_menue() {
	add_management_page( 'Beiträge abgleichen', 'Beiträge abgleichen', 'manage_options', 'afk-abgleich', 'afk_abg_seite' );
}
add_action( 'admin_menu', 'afk_abg_menue' );

/**
 * Seite.
 */
function afk_abg_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$meldung = '';
	$fehler  = '';
	$liste   = null;

	if ( isset( $_POST['afk_abg_speichern'] ) && check_admin_referer( 'afk_abg_einst' ) ) {
		$quelle = isset( $_POST['quelle'] ) ? esc_url_raw( trim( wp_unslash( $_POST['quelle'] ) ) ) : '';
		$neu    = array(
			'quelle'   => untrailingslashit( $quelle ),
			'benutzer' => isset( $_POST['benutzer'] ) ? sanitize_user( wp_unslash( $_POST['benutzer'] ) ) : '',
		);
		$pw     = isset( $_POST['passwort'] ) ? preg_replace( '/\s+/', '', wp_unslash( $_POST['passwort'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $pw ) {
			$neu['passwort'] = $pw;
		}
		if ( ! empty( $_POST['passwort_loeschen'] ) || '' === $neu['benutzer'] ) {
			$neu['passwort'] = '';
		}
		afk_abg_speichern( $neu );
		$meldung = 'Gespeichert.';
		if ( afk_abg_mit_login() && ! afk_abg_sperren() ) {
			$t = afk_abg_get( 'wp/v2/users/me', array( 'context' => 'edit', '_fields' => 'id,name' ) );
			$fehler  = is_wp_error( $t ) ? 'Anmeldung an der Quelle fehlgeschlagen: ' . $t->get_error_message() : '';
			$meldung = is_wp_error( $t ) ? '' : 'Gespeichert – Anmeldung an der Quelle als „' . $t['daten']['name'] . '“ klappt.';
		}
	}

	$e       = afk_abg_einstellungen();
	$sperren = afk_abg_sperren();
	$seit    = $e['letzter'] ? wp_date( 'Y-m-d', $e['letzter'] - DAY_IN_SECONDS ) : wp_date( 'Y-m-d', time() - 14 * DAY_IN_SECONDS );
	if ( isset( $_POST['afk_abg_suchen'] ) && check_admin_referer( 'afk_abg_suchen' ) ) {
		$seit = isset( $_POST['seit'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', wp_unslash( $_POST['seit'] ) ) ? wp_unslash( $_POST['seit'] ) : $seit; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $sperren ) {
			$liste = afk_abg_liste( $seit );
			if ( is_wp_error( $liste ) ) {
				$fehler = 'Abruf fehlgeschlagen: ' . $liste->get_error_message();
				$liste  = null;
			}
		}
	}
	$labels = array(
		'neu'       => 'Neu',
		'geaendert' => 'Geändert',
		'aktuell'   => 'Aktuell',
	);
	?>
	<div class="wrap afk-abg">
		<h1>Beiträge abgleichen</h1>
		<p>Holt neue und geänderte Beiträge von der bisherigen Website auf diese Seite – nur in diese Richtung und nur, wenn du hier auf „Übernehmen“ klickst. Übernommen werden Titel, Text, Datum, Kategorien, Schlagwörter und Bilder; die Feuerwehr wird aus den Schlagwörtern „FF …“ erkannt, solange der Beitrag hier noch keine hat.</p>
		<p><strong>Achtung:</strong> Bei geänderten Beiträgen wird der Text hier mit dem der Quelle überschrieben. Änderungen, die nur hier gemacht wurden, gehen dabei verloren (außer der Feuerwehr-Zuordnung). Gelöschte Beiträge werden nicht mitgelöscht.</p>

		<?php if ( $meldung ) : ?><div class="notice notice-success"><p><?php echo esc_html( $meldung ); ?></p></div><?php endif; ?>
		<?php if ( $fehler ) : ?><div class="notice notice-error"><p><?php echo esc_html( $fehler ); ?></p></div><?php endif; ?>
		<?php if ( $sperren && '' !== $e['quelle'] ) : ?>
			<div class="notice notice-warning"><p><strong>Abgleich gesperrt:</strong></p><ul style="list-style:disc;margin-left:20px"><?php foreach ( $sperren as $g ) : ?><li><?php echo esc_html( $g ); ?></li><?php endforeach; ?></ul></div>
		<?php endif; ?>

		<h2>Quelle</h2>
		<form method="post">
			<?php wp_nonce_field( 'afk_abg_einst' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="afk-abg-quelle">Adresse der bisherigen Website</label></th>
					<td><input class="regular-text" type="url" id="afk-abg-quelle" name="quelle" value="<?php echo esc_attr( $e['quelle'] ); ?>" placeholder="https://www.afk-millstatt-radenthein.at"></td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-abg-benutzer">Benutzername dort</label></th>
					<td><input class="regular-text" type="text" id="afk-abg-benutzer" name="benutzer" value="<?php echo esc_attr( $e['benutzer'] ); ?>" autocomplete="off">
						<p class="description">Optional, aber empfohlen: Mit Anmeldung kommen die Beiträge im Original (bearbeitbare Blöcke, eigener Auszug). Ohne Anmeldung wird der fertig dargestellte Text übernommen.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-abg-pw">Anwendungspasswort</label></th>
					<td><input class="regular-text" type="password" id="afk-abg-pw" name="passwort" value="" autocomplete="new-password" placeholder="<?php echo $e['passwort'] ? esc_attr( '•••• gespeichert' ) : ''; ?>">
						<?php if ( $e['passwort'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="passwort_loeschen" value="1"> löschen</label><?php endif; ?>
						<p class="description">Nicht dein normales Passwort: Auf der bisherigen Website unter <em>Benutzer → Profil → Anwendungspasswörter</em> eines mit dem Namen „Abgleich neue Website“ erstellen und hier einfügen. Es lässt sich dort jederzeit widerrufen.</p></td>
				</tr>
			</table>
			<p><button class="button" name="afk_abg_speichern" value="1">Speichern</button></p>
		</form>

		<?php if ( ! $sperren ) : ?>
			<h2>Änderungen suchen</h2>
			<form method="post">
				<?php wp_nonce_field( 'afk_abg_suchen' ); ?>
				<p><label>Beiträge, die in der Quelle geändert wurden seit <input type="date" name="seit" value="<?php echo esc_attr( $seit ); ?>"></label>
					<button class="button button-primary" name="afk_abg_suchen" value="1">Suchen</button>
					<?php if ( $e['letzter'] ) : ?><span class="description" style="margin-left:10px">Letzter Abgleich: <?php echo esc_html( wp_date( 'j. F Y, H:i', $e['letzter'] ) ); ?></span><?php endif; ?></p>
			</form>
		<?php endif; ?>

		<?php if ( is_array( $liste ) ) : ?>
			<?php if ( ! $liste ) : ?>
				<p>Seit <?php echo esc_html( wp_date( 'j. F Y', strtotime( $seit ) ) ); ?> wurde in der Quelle kein Beitrag geändert.</p>
			<?php else : ?>
				<form id="afk-abg-liste">
					<table class="widefat striped">
						<thead><tr><td class="check-column"><input type="checkbox" id="afk-abg-alle"></td><th>Beitrag</th><th>Datum</th><th>In der Quelle geändert</th><th>Status hier</th><th>Ergebnis</th></tr></thead>
						<tbody>
						<?php foreach ( $liste as $z ) : ?>
							<tr data-rid="<?php echo (int) $z['rid']; ?>">
								<th class="check-column"><input type="checkbox" name="rid[]" value="<?php echo (int) $z['rid']; ?>" <?php checked( 'aktuell' !== $z['status'] ); ?>></th>
								<td><a href="<?php echo esc_url( $z['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $z['titel'] ); ?></a>
									<?php if ( $z['lokal_id'] ) : ?> · <a href="<?php echo esc_url( get_permalink( $z['lokal_id'] ) ); ?>" target="_blank" rel="noopener">hier ansehen</a><?php endif; ?></td>
								<td><?php echo esc_html( wp_date( 'j.n.Y', strtotime( $z['datum'] ) ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'j.n.Y H:i', strtotime( $z['geaendert'] . ' UTC' ) ) ); ?></td>
								<td><strong><?php echo esc_html( $labels[ $z['status'] ] ); ?></strong></td>
								<td class="afk-abg-ergebnis">–</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p><button type="submit" class="button button-primary" id="afk-abg-los">Ausgewählte übernehmen</button> <span id="afk-abg-status" class="description"></span></p>
				</form>
				<script>
				( function () {
					var form = document.getElementById( 'afk-abg-liste' );
					var btn = document.getElementById( 'afk-abg-los' );
					var status = document.getElementById( 'afk-abg-status' );
					var nonce = <?php echo wp_json_encode( wp_create_nonce( 'afk_abgleich' ) ); ?>;
					var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
					document.getElementById( 'afk-abg-alle' ).addEventListener( 'change', function () {
						form.querySelectorAll( 'input[name="rid[]"]' ).forEach( function ( c ) { c.checked = this.checked; }, this );
					} );
					function post( data ) {
						var fd = new FormData();
						Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
						fd.append( 'nonce', nonce );
						return fetch( ajax, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } );
					}
					form.addEventListener( 'submit', async function ( ev ) {
						ev.preventDefault();
						var ids = Array.prototype.map.call( form.querySelectorAll( 'input[name="rid[]"]:checked' ), function ( c ) { return c.value; } );
						if ( ! ids.length ) { status.textContent = 'Nichts ausgewählt.'; return; }
						btn.disabled = true;
						var ok = 0, fail = 0;
						for ( var i = 0; i < ids.length; i++ ) {
							var zelle = form.querySelector( 'tr[data-rid="' + ids[ i ] + '"] .afk-abg-ergebnis' );
							status.textContent = 'Beitrag ' + ( i + 1 ) + ' von ' + ids.length + ' …';
							for ( var versuch = 0; versuch < 60; versuch++ ) {
								var r;
								try { r = await post( { action: 'afk_abgleich', rid: ids[ i ] } ); } catch ( e ) { r = { success: false, data: 'Server antwortet nicht (' + e.message + ')' }; }
								if ( ! r.success ) { zelle.textContent = '✗ ' + r.data; fail++; break; }
								if ( r.data.fertig ) {
									zelle.innerHTML = '';
									zelle.appendChild( document.createTextNode( '✓ ' + r.data.text + ' ' ) );
									if ( r.data.link ) { var a = document.createElement( 'a' ); a.href = r.data.link; a.target = '_blank'; a.textContent = 'ansehen'; zelle.appendChild( a ); }
									ok++; break;
								}
								zelle.textContent = '… ' + r.data.text;
							}
						}
						await post( { action: 'afk_abgleich_ende' } );
						status.textContent = 'Fertig: ' + ok + ' übernommen' + ( fail ? ', ' + fail + ' mit Fehler' : '' ) + '.';
						btn.disabled = false;
					} );
				}() );
				</script>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
