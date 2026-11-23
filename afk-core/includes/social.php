<?php
/**
 * Social Media: Beiträge einheitlich auf Telegram, Facebook und Instagram posten.
 *
 * - Bild: einheitliche Vorlage (social-bild.php), 1080 × 1350.
 * - Text: Vorlage mit Platzhaltern, je Beitrag anpassbar.
 * - Gepostet wird nur, wenn die Seite unter der eingetragenen Live-Adresse läuft – auf einer
 *   Staging-Kopie passiert also nie etwas.
 * - Jeder Kanal wird je Beitrag nur einmal bedient (Protokoll im Beitrag).
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/social-bild.php';

const AFK_SOCIAL_OPTION = 'afk_social';

/**
 * Standard-Textvorlage.
 *
 * @return string
 */
function afk_social_standard_vorlage() {
	return "{emoji} {art} · {datum}\n\n{titel}\n\n{auszug}\n\n{beteiligt}\n\n{link}\n\n{hashtags}";
}

/**
 * Einstellungen.
 *
 * @return array
 */
function afk_social_einstellungen() {
	$o = get_option( AFK_SOCIAL_OPTION, array() );
	return wp_parse_args(
		is_array( $o ) ? $o : array(),
		array(
			'live_url'    => '',
			'tg_token'    => '',
			'tg_chat'     => '',
			'fb_page'     => '',
			'fb_token'    => '',
			'ig_user'     => '',
			'graph'       => 'v26.0',
			'hashtags'    => '#feuerwehr #feuerwehrkärnten #millstättersee',
			'vorlage'     => afk_social_standard_vorlage(),
			'standard_an' => 1,
		)
	);
}

/**
 * Welche Kanäle sind eingerichtet?
 *
 * @return array kanal => Name
 */
function afk_social_kanaele() {
	$e = afk_social_einstellungen();
	$k = array();
	if ( $e['tg_token'] && $e['tg_chat'] ) {
		$k['telegram'] = 'Telegram';
	}
	if ( $e['fb_page'] && $e['fb_token'] ) {
		$k['facebook'] = 'Facebook';
		if ( $e['ig_user'] ) {
			$k['instagram'] = 'Instagram';
		}
	}
	return $k;
}

/**
 * Darf diese Seite posten? Nur unter der eingetragenen Live-Adresse.
 *
 * @return bool
 */
function afk_social_live() {
	$e = afk_social_einstellungen();
	if ( '' === $e['live_url'] ) {
		return false;
	}
	$norm = function ( $u ) {
		$p = wp_parse_url( $u );
		return strtolower( preg_replace( '/^www\./', '', $p['host'] ?? '' ) ) . untrailingslashit( $p['path'] ?? '' );
	};
	return $norm( $e['live_url'] ) === $norm( home_url() );
}

/* =====================================================================
 * Text
 * =================================================================== */

/**
 * Hashtag aus einem Namen („FF St. Peter ob Radenthein“ → „#ffstpeterobradenthein“).
 *
 * @param string $name Name.
 * @return string
 */
function afk_social_hashtag( $name ) {
	return '#' . preg_replace( '/[^\p{L}\p{N}]/u', '', mb_strtolower( $name, 'UTF-8' ) );
}

/**
 * Fertiger Text für einen Kanal.
 *
 * @param int    $post_id Beitrag.
 * @param string $kanal   telegram|facebook|instagram.
 * @param bool   $eigener Eigenen Text des Beitrags verwenden (falls vorhanden).
 * @return string
 */
function afk_social_text( $post_id, $kanal = 'facebook', $eigener = true ) {
	$e      = afk_social_einstellungen();
	$post   = get_post( $post_id );
	$art    = afk_social_art( $post_id );
	$wehren = function_exists( 'afk_get_feuerwehren' ) ? wp_list_pluck( afk_get_feuerwehren( $post_id ), 'name' ) : array();
	$vorl   = $eigener ? trim( (string) get_post_meta( $post_id, '_afk_social_text', true ) ) : '';
	$vorl   = '' !== $vorl ? $vorl : ( trim( $e['vorlage'] ) ? $e['vorlage'] : afk_social_standard_vorlage() );

	$auszug = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 45, ' …' );
	$auszug = html_entity_decode( wp_strip_all_tags( $auszug ), ENT_QUOTES, 'UTF-8' );
	// Telegram erlaubt bei Bildern nur 1.024 Zeichen Text.
	if ( 'telegram' === $kanal && mb_strlen( $auszug ) > 520 ) {
		$auszug = rtrim( mb_substr( $auszug, 0, 500 ) ) . ' …';
	}

	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$link = 'instagram' === $kanal ? 'Ganzer Bericht auf ' . $host : 'Zum Bericht: ' . get_permalink( $post );

	$tags = preg_split( '/\s+/', trim( $e['hashtags'] ) );
	foreach ( $wehren as $w ) {
		$tags[] = afk_social_hashtag( $w );
	}
	$tags = array_values( array_unique( array_filter( $tags ) ) );

	$beteiligt = '';
	if ( $wehren ) {
		$beteiligt = ( 'einsatz' === $art['slug'] ? 'Im Einsatz: ' : 'Mit dabei: ' ) . implode( ', ', $wehren );
	}

	$text = strtr(
		$vorl,
		array(
			'{emoji}'     => $art['emoji'],
			'{art}'       => $art['art'],
			'{datum}'     => wp_date( 'j. F Y', get_post_timestamp( $post ) ),
			'{titel}'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'{auszug}'    => $auszug,
			'{beteiligt}' => $beteiligt,
			'{feuerwehren}' => implode( ', ', $wehren ),
			'{link}'      => $link,
			'{hashtags}'  => implode( ' ', $tags ),
		)
	);
	// Leere Platzhalter hinterlassen keine doppelten Leerzeilen.
	$text = preg_replace( "/\n{3,}/", "\n\n", trim( str_replace( "\r", '', $text ) ) );
	if ( 'telegram' === $kanal && mb_strlen( $text ) > 1024 ) {
		$text = rtrim( mb_substr( $text, 0, 1020 ) ) . ' …';
	}
	if ( 'instagram' === $kanal && mb_strlen( $text ) > 2200 ) {
		$text = rtrim( mb_substr( $text, 0, 2196 ) ) . ' …';
	}
	return $text;
}

/* =====================================================================
 * Versand
 * =================================================================== */

/**
 * Anfrage an die Graph-API (Facebook/Instagram).
 *
 * @param string $methode GET|POST.
 * @param string $pfad    z. B. „123/photos“.
 * @param array  $daten   Parameter.
 * @return array|WP_Error
 */
function afk_social_graph( $methode, $pfad, $daten = array() ) {
	$e   = afk_social_einstellungen();
	$url = 'https://graph.facebook.com/' . rawurlencode( $e['graph'] ) . '/' . ltrim( $pfad, '/' );
	$daten['access_token'] = $e['fb_token'];
	$res = 'GET' === $methode
		? wp_remote_get( add_query_arg( array_map( 'rawurlencode', $daten ), $url ), array( 'timeout' => 30 ) )
		: wp_remote_post( $url, array( 'timeout' => 60, 'body' => $daten ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $json ) ) {
		return new WP_Error( 'afk_social_graph', 'Unerwartete Antwort von Meta (HTTP ' . wp_remote_retrieve_response_code( $res ) . ').' );
	}
	if ( ! empty( $json['error'] ) ) {
		$msg = (string) ( $json['error']['message'] ?? 'Fehler' );
		if ( 190 === (int) ( $json['error']['code'] ?? 0 ) ) {
			$msg .= ' – der Seiten-Token ist abgelaufen oder ungültig.';
		}
		return new WP_Error( 'afk_social_graph', $msg );
	}
	return $json;
}

/**
 * Anfrage an Telegram.
 *
 * @param string     $methode z. B. sendPhoto.
 * @param array      $daten   Felder.
 * @param string|null $datei  Datei für das Feld „photo“ (wird hochgeladen).
 * @return array|WP_Error
 */
function afk_social_telegram( $methode, $daten = array(), $datei = null ) {
	$e    = afk_social_einstellungen();
	$url  = 'https://api.telegram.org/bot' . $e['tg_token'] . '/' . $methode;
	$args = array( 'timeout' => 60 );
	if ( $datei ) {
		$grenze = wp_generate_password( 24, false );
		$body   = '';
		foreach ( $daten as $k => $v ) {
			$body .= "--$grenze\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
		}
		$body .= "--$grenze\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"" . basename( $datei ) . "\"\r\nContent-Type: image/jpeg\r\n\r\n";
		$body .= file_get_contents( $datei ) . "\r\n--$grenze--\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$args['headers'] = array( 'Content-Type' => 'multipart/form-data; boundary=' . $grenze );
		$args['body']    = $body;
	} else {
		$args['body'] = $daten;
	}
	$res = wp_remote_post( $url, $args );
	if ( is_wp_error( $res ) ) {
		// Token nicht in Fehlermeldungen zeigen.
		return new WP_Error( 'afk_social_tg', str_replace( $e['tg_token'], '…', $res->get_error_message() ) );
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $json['ok'] ) ) {
		return new WP_Error( 'afk_social_tg', 'Telegram: ' . ( $json['description'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $res ) ) );
	}
	return $json['result'];
}

/**
 * Auf einem Kanal posten.
 *
 * @param string $kanal   telegram|facebook|instagram.
 * @param int    $post_id Beitrag.
 * @param array  $bild    array( datei, url ).
 * @return array|WP_Error array( id, link )
 */
function afk_social_senden( $kanal, $post_id, $bild ) {
	$e    = afk_social_einstellungen();
	$text = afk_social_text( $post_id, $kanal );

	if ( 'telegram' === $kanal ) {
		$r = afk_social_telegram(
			'sendPhoto',
			array(
				'chat_id' => $e['tg_chat'],
				'caption' => $text,
			),
			$bild['datei']
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$id   = (int) ( $r['message_id'] ?? 0 );
		$name = $r['chat']['username'] ?? ( 0 === strpos( $e['tg_chat'], '@' ) ? substr( $e['tg_chat'], 1 ) : '' );
		return array(
			'id'   => (string) $id,
			'link' => $name ? 'https://t.me/' . $name . '/' . $id : '',
		);
	}

	if ( 'facebook' === $kanal ) {
		$r = afk_social_graph(
			'POST',
			rawurlencode( $e['fb_page'] ) . '/photos',
			array(
				'url'     => $bild['url'],
				'message' => $text,
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$id = (string) ( $r['post_id'] ?? $r['id'] ?? '' );
		return array(
			'id'   => $id,
			'link' => $id ? 'https://www.facebook.com/' . $id : '',
		);
	}

	if ( 'instagram' === $kanal ) {
		$c = afk_social_graph(
			'POST',
			rawurlencode( $e['ig_user'] ) . '/media',
			array(
				'image_url' => $bild['url'],
				'caption'   => $text,
			)
		);
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		// Instagram lädt das Bild selbst herunter – kurz warten, bis es verarbeitet ist.
		for ( $i = 0; $i < 10; $i++ ) {
			$s = afk_social_graph( 'GET', rawurlencode( $c['id'] ), array( 'fields' => 'status_code' ) );
			if ( is_wp_error( $s ) || 'IN_PROGRESS' !== ( $s['status_code'] ?? '' ) ) {
				break;
			}
			sleep( 2 );
		}
		if ( ! is_wp_error( $s ) && 'ERROR' === ( $s['status_code'] ?? '' ) ) {
			return new WP_Error( 'afk_social_ig', 'Instagram konnte das Bild nicht verarbeiten.' );
		}
		$p = afk_social_graph( 'POST', rawurlencode( $e['ig_user'] ) . '/media_publish', array( 'creation_id' => $c['id'] ) );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		$l = afk_social_graph( 'GET', rawurlencode( $p['id'] ), array( 'fields' => 'permalink' ) );
		return array(
			'id'   => (string) $p['id'],
			'link' => is_wp_error( $l ) ? '' : (string) ( $l['permalink'] ?? '' ),
		);
	}
	return new WP_Error( 'afk_social_kanal', 'Unbekannter Kanal.' );
}

/**
 * Protokoll eines Beitrags.
 *
 * @param int $post_id Beitrag.
 * @return array kanal => array( zeit, ok, id, link, fehler )
 */
function afk_social_protokoll( $post_id ) {
	$p = get_post_meta( $post_id, '_afk_social_log', true );
	return is_array( $p ) ? $p : array();
}

/**
 * Beitrag auf den gewählten Kanälen posten.
 *
 * @param int   $post_id Beitrag.
 * @param array $kanaele Kanäle; leer = die im Beitrag gewählten.
 * @param bool  $erneut  Auch Kanäle, auf denen schon gepostet wurde.
 * @return array|WP_Error Protokoll.
 */
function afk_social_posten( $post_id, $kanaele = array(), $erneut = false ) {
	if ( ! afk_social_live() ) {
		return new WP_Error( 'afk_social_live', 'Auf dieser Seite wird nicht gepostet – nur unter der eingetragenen Live-Adresse.' );
	}
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || 'post' !== $post->post_type ) {
		return new WP_Error( 'afk_social_status', 'Nur veröffentlichte Beiträge können gepostet werden.' );
	}
	if ( ! empty( $post->post_password ) ) {
		return new WP_Error( 'afk_social_pw', 'Passwortgeschützte Beiträge werden nicht gepostet.' );
	}
	$moeglich = afk_social_kanaele();
	if ( ! $kanaele ) {
		$kanaele = (array) get_post_meta( $post_id, '_afk_social_kanaele', true );
	}
	$kanaele = array_intersect( (array) $kanaele, array_keys( $moeglich ) );
	if ( ! $kanaele ) {
		return afk_social_protokoll( $post_id );
	}

	// Sperre gegen doppeltes Posten (z. B. Cron und Knopf gleichzeitig).
	$sperre = 'afk_social_sperre_' . $post_id;
	if ( get_transient( $sperre ) ) {
		return new WP_Error( 'afk_social_sperre', 'Wird gerade gepostet – bitte kurz warten.' );
	}
	set_transient( $sperre, 1, 5 * MINUTE_IN_SECONDS );
	@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$bild = afk_social_bild( $post_id );
	$log  = afk_social_protokoll( $post_id );
	foreach ( $kanaele as $k ) {
		if ( ! $erneut && ! empty( $log[ $k ]['ok'] ) ) {
			continue;
		}
		$r         = is_wp_error( $bild ) ? $bild : afk_social_senden( $k, $post_id, $bild );
		$log[ $k ] = array(
			'zeit'   => time(),
			'ok'     => ! is_wp_error( $r ),
			'id'     => is_wp_error( $r ) ? '' : $r['id'],
			'link'   => is_wp_error( $r ) ? '' : $r['link'],
			'fehler' => is_wp_error( $r ) ? $r->get_error_message() : '',
		);
		update_post_meta( $post_id, '_afk_social_log', $log );
	}
	delete_transient( $sperre );
	return $log;
}

/* =====================================================================
 * Automatisch beim Veröffentlichen
 * =================================================================== */

/**
 * Beim ersten Veröffentlichen einen Auftrag einplanen. Der Block-Editor speichert den Kasten
 * erst nach dem Beitrag – deshalb mit etwas Abstand.
 *
 * @param string  $neu  Neuer Status.
 * @param string  $alt  Alter Status.
 * @param WP_Post $post Beitrag.
 */
function afk_social_status( $neu, $alt, $post ) {
	if ( 'publish' !== $neu || 'publish' === $alt || 'post' !== $post->post_type ) {
		return;
	}
	if ( ! afk_social_live() || ! afk_social_kanaele() ) {
		return;
	}
	if ( ! wp_next_scheduled( 'afk_social_auftrag', array( (int) $post->ID ) ) ) {
		wp_schedule_single_event( time() + 60, 'afk_social_auftrag', array( (int) $post->ID ) );
	}
}
add_action( 'transition_post_status', 'afk_social_status', 10, 3 );

/**
 * Geplanter Auftrag.
 *
 * @param int $post_id Beitrag.
 */
function afk_social_auftrag( $post_id ) {
	if ( '1' !== (string) get_post_meta( $post_id, '_afk_social_auto', true ) ) {
		return;
	}
	afk_social_posten( (int) $post_id );
}
add_action( 'afk_social_auftrag', 'afk_social_auftrag' );

/* =====================================================================
 * Kasten im Beitragseditor
 * =================================================================== */

/**
 * Kasten anmelden.
 */
function afk_social_metabox() {
	add_meta_box( 'afk-social', 'Social Media', 'afk_social_metabox_inhalt', 'post', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'afk_social_metabox' );

/**
 * Inhalt des Kastens.
 *
 * @param WP_Post $post Beitrag.
 */
function afk_social_metabox_inhalt( $post ) {
	$kanaele = afk_social_kanaele();
	if ( ! $kanaele ) {
		echo '<p>Noch kein Kanal eingerichtet. <a href="' . esc_url( admin_url( 'options-general.php?page=afk-social' ) ) . '">Einstellungen → Social Media</a></p>';
		return;
	}
	$e       = afk_social_einstellungen();
	$neu     = 'auto-draft' === $post->post_status || '' === get_post_meta( $post->ID, '_afk_social_auto', true );
	$auto    = $neu ? (int) $e['standard_an'] : (int) get_post_meta( $post->ID, '_afk_social_auto', true );
	$gewaehlt = $neu ? array_keys( $kanaele ) : (array) get_post_meta( $post->ID, '_afk_social_kanaele', true );
	$log     = afk_social_protokoll( $post->ID );
	wp_nonce_field( 'afk_social_box', 'afk_social_nonce' );
	?>
	<?php if ( ! afk_social_live() ) : ?>
		<p style="background:#fcf0c3;padding:6px 8px"><strong>Testseite:</strong> Hier wird nicht gepostet. Vorschau funktioniert trotzdem.</p>
	<?php endif; ?>
	<?php if ( 'publish' === $post->post_status ) : ?>
		<input type="hidden" name="afk_social_auto" value="<?php echo (int) $auto; ?>">
		<p>Bereits veröffentlicht – auf den gewählten Kanälen mit „Jetzt posten“ nachholen.</p>
	<?php else : ?>
		<p><label><input type="checkbox" name="afk_social_auto" value="1" <?php checked( 1, $auto ); ?>> Beim Veröffentlichen posten</label><br><small>etwa eine Minute nach dem Veröffentlichen</small></p>
	<?php endif; ?>
	<p style="margin:0 0 8px">
		<?php foreach ( $kanaele as $k => $name ) : ?>
			<label style="display:block"><input type="checkbox" name="afk_social_kanaele[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $gewaehlt, true ) ); ?>> <?php echo esc_html( $name ); ?>
			<?php if ( ! empty( $log[ $k ] ) ) : ?>
				<br><small style="margin-left:24px;color:<?php echo $log[ $k ]['ok'] ? '#1a7f37' : '#b32d2e'; ?>">
					<?php echo esc_html( ( $log[ $k ]['ok'] ? '✓ gepostet ' : '✗ Fehler ' ) . wp_date( 'j.n. H:i', $log[ $k ]['zeit'] ) ); ?>
					<?php if ( $log[ $k ]['link'] ) : ?> · <a href="<?php echo esc_url( $log[ $k ]['link'] ); ?>" target="_blank" rel="noopener">ansehen</a><?php endif; ?>
					<?php if ( $log[ $k ]['fehler'] ) : ?><br><?php echo esc_html( $log[ $k ]['fehler'] ); ?><?php endif; ?>
				</small>
			<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</p>
	<p><label for="afk-social-text"><strong>Eigener Text</strong> <small>(leer = Vorlage)</small></label>
		<textarea id="afk-social-text" name="afk_social_text" rows="6" style="width:100%" placeholder="<?php echo esc_attr( 'auto-draft' === $post->post_status ? 'Text entsteht aus Titel, Auszug und Feuerwehren.' : afk_social_text( $post->ID, 'facebook', false ) ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_afk_social_text', true ) ); ?></textarea>
		<small>Platzhalter: {titel} {auszug} {link} {beteiligt} {hashtags} {art} {datum} {emoji}</small></p>
	<p>
		<button type="button" class="button" id="afk-social-vorschau">Vorschau</button>
		<?php if ( 'publish' === $post->post_status && afk_social_live() ) : ?>
			<button type="button" class="button button-primary" id="afk-social-jetzt">Jetzt posten</button>
		<?php endif; ?>
	</p>
	<div id="afk-social-ausgabe"></div>
	<script>
	( function () {
		var box = document.getElementById( 'afk-social' );
		var aus = document.getElementById( 'afk-social-ausgabe' );
		function anfrage( was ) {
			var fd = new FormData();
			fd.append( 'action', 'afk_social' );
			fd.append( 'was', was );
			fd.append( 'post', <?php echo (int) $post->ID; ?> );
			fd.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'afk_social_ajax' ) ); ?> );
			fd.append( 'text', document.getElementById( 'afk-social-text' ).value );
			box.querySelectorAll( 'input[name="afk_social_kanaele[]"]:checked' ).forEach( function ( c ) { fd.append( 'kanaele[]', c.value ); } );
			aus.textContent = was === 'jetzt' ? 'Wird gepostet …' : 'Vorschau wird erstellt …';
			return fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
				if ( ! r.success ) { aus.textContent = '✗ ' + r.data; return; }
				aus.innerHTML = r.data.html;
			} ).catch( function ( e ) { aus.textContent = '✗ ' + e.message; } );
		}
		document.getElementById( 'afk-social-vorschau' ).addEventListener( 'click', function () { anfrage( 'vorschau' ); } );
		var jetzt = document.getElementById( 'afk-social-jetzt' );
		if ( jetzt ) {
			jetzt.addEventListener( 'click', function () {
				jetzt.disabled = true;
				anfrage( 'jetzt' ).then( function () { jetzt.disabled = false; } );
			} );
		}
	}() );
	</script>
	<?php
}

/**
 * Kasten speichern.
 *
 * @param int $post_id Beitrag.
 */
function afk_social_speichern( $post_id ) {
	if ( ! isset( $_POST['afk_social_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['afk_social_nonce'] ), 'afk_social_box' ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_afk_social_auto', empty( $_POST['afk_social_auto'] ) ? '0' : '1' );
	$k = isset( $_POST['afk_social_kanaele'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['afk_social_kanaele'] ) ) : array();
	update_post_meta( $post_id, '_afk_social_kanaele', array_values( array_intersect( $k, array( 'telegram', 'facebook', 'instagram' ) ) ) );
	$t = isset( $_POST['afk_social_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['afk_social_text'] ) ) : '';
	update_post_meta( $post_id, '_afk_social_text', $t );
}
add_action( 'save_post_post', 'afk_social_speichern' );

/**
 * AJAX: Vorschau / jetzt posten.
 */
function afk_social_ajax() {
	check_ajax_referer( 'afk_social_ajax', 'nonce' );
	$post_id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Keine Berechtigung.' );
	}
	$was     = isset( $_POST['was'] ) ? sanitize_key( $_POST['was'] ) : '';
	$kanaele = isset( $_POST['kanaele'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['kanaele'] ) ) : array();
	if ( isset( $_POST['text'] ) ) {
		update_post_meta( $post_id, '_afk_social_text', sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) );
	}

	if ( 'jetzt' === $was ) {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( 'Keine Berechtigung zum Veröffentlichen.' );
		}
		update_post_meta( $post_id, '_afk_social_kanaele', $kanaele );
		$log = afk_social_posten( $post_id, $kanaele );
		if ( is_wp_error( $log ) ) {
			wp_send_json_error( $log->get_error_message() );
		}
		$html = '';
		foreach ( $kanaele as $k ) {
			if ( empty( $log[ $k ] ) ) {
				continue;
			}
			$l     = $log[ $k ];
			$html .= '<p style="color:' . ( $l['ok'] ? '#1a7f37' : '#b32d2e' ) . '">' . esc_html( ( $l['ok'] ? '✓ ' : '✗ ' ) . ucfirst( $k ) . ( $l['fehler'] ? ': ' . $l['fehler'] : '' ) ) . ( $l['link'] ? ' · <a href="' . esc_url( $l['link'] ) . '" target="_blank" rel="noopener">ansehen</a>' : '' ) . '</p>';
		}
		wp_send_json_success( array( 'html' => $html ? $html : '<p>Auf den gewählten Kanälen wurde schon gepostet.</p>' ) );
	}

	$bild = afk_social_bild( $post_id );
	if ( is_wp_error( $bild ) ) {
		wp_send_json_error( $bild->get_error_message() );
	}
	$k    = $kanaele ? $kanaele[0] : 'facebook';
	$html = '<img src="' . esc_url( $bild['url'] . '?v=' . filemtime( $bild['datei'] ) ) . '" alt="" style="width:100%;height:auto;display:block;margin:4px 0 8px">'
		. '<div style="white-space:pre-wrap;font-size:12px;line-height:1.45;background:#f6f7f7;padding:8px;border-radius:4px">' . esc_html( afk_social_text( $post_id, $k ) ) . '</div>';
	if ( in_array( 'instagram', $kanaele, true ) && 'instagram' !== $k ) {
		$html .= '<p><small>Auf Instagram steht statt des Links „Ganzer Bericht auf …“ (Links sind dort nicht anklickbar).</small></p>';
	}
	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_afk_social', 'afk_social_ajax' );

/* =====================================================================
 * Einstellungen → Social Media
 * =================================================================== */

/**
 * Menüpunkt.
 */
function afk_social_menue() {
	add_options_page( 'Social Media', 'Social Media', 'manage_options', 'afk-social', 'afk_social_seite' );
}
add_action( 'admin_menu', 'afk_social_menue' );

/**
 * Seite.
 */
function afk_social_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$e       = afk_social_einstellungen();
	$meldung = array();

	if ( isset( $_POST['afk_social_speichern'] ) && check_admin_referer( 'afk_social_einst' ) ) {
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$neu = array(
			'live_url'    => esc_url_raw( trim( (string) ( $in['live_url'] ?? '' ) ) ),
			'tg_chat'     => sanitize_text_field( (string) ( $in['tg_chat'] ?? '' ) ),
			'fb_page'     => preg_replace( '/\D/', '', (string) ( $in['fb_page'] ?? '' ) ),
			'ig_user'     => preg_replace( '/\D/', '', (string) ( $in['ig_user'] ?? '' ) ),
			'graph'       => preg_match( '/^v\d+\.\d+$/', (string) ( $in['graph'] ?? '' ) ) ? $in['graph'] : 'v26.0',
			'hashtags'    => sanitize_text_field( (string) ( $in['hashtags'] ?? '' ) ),
			'vorlage'     => sanitize_textarea_field( (string) ( $in['vorlage'] ?? '' ) ),
			'standard_an' => empty( $in['standard_an'] ) ? 0 : 1,
		);
		foreach ( array( 'tg_token', 'fb_token' ) as $geheim ) {
			$wert = trim( (string) ( $in[ $geheim ] ?? '' ) );
			if ( ! empty( $in[ $geheim . '_loeschen' ] ) ) {
				$neu[ $geheim ] = '';
			} elseif ( '' !== $wert ) {
				$neu[ $geheim ] = preg_replace( '/\s+/', '', $wert );
			}
		}
		update_option( AFK_SOCIAL_OPTION, array_merge( $e, $neu ), false );
		$e         = afk_social_einstellungen();
		$meldung[] = array( 'success', 'Gespeichert.' );
	}

	if ( isset( $_POST['afk_social_testen'] ) && check_admin_referer( 'afk_social_einst' ) ) {
		if ( $e['tg_token'] ) {
			$me = afk_social_telegram( 'getMe' );
			$ch = $e['tg_chat'] ? afk_social_telegram( 'getChat', array( 'chat_id' => $e['tg_chat'] ) ) : new WP_Error( 'x', 'kein Kanal eingetragen' );
			$meldung[] = is_wp_error( $me )
				? array( 'error', 'Telegram: ' . $me->get_error_message() )
				: array( is_wp_error( $ch ) ? 'warning' : 'success', 'Telegram: Bot „' . $me['first_name'] . '“ (@' . $me['username'] . ') – Kanal: ' . ( is_wp_error( $ch ) ? $ch->get_error_message() : ( $ch['title'] ?? $e['tg_chat'] ) ) );
		}
		if ( $e['fb_token'] && $e['fb_page'] ) {
			$p = afk_social_graph( 'GET', rawurlencode( $e['fb_page'] ), array( 'fields' => 'name,instagram_business_account{id,username}' ) );
			if ( is_wp_error( $p ) ) {
				$meldung[] = array( 'error', 'Facebook: ' . $p->get_error_message() );
			} else {
				$ig        = $p['instagram_business_account'] ?? null;
				$meldung[] = array( 'success', 'Facebook: Seite „' . $p['name'] . '“' . ( $ig ? ' – verbundenes Instagram-Konto: @' . $ig['username'] : ' – kein Instagram-Konto verbunden' ) );
				if ( $ig && $ig['id'] !== $e['ig_user'] ) {
					update_option( AFK_SOCIAL_OPTION, array_merge( $e, array( 'ig_user' => $ig['id'] ) ), false );
					$e         = afk_social_einstellungen();
					$meldung[] = array( 'success', 'Instagram-Konto-ID wurde eingetragen.' );
				}
			}
		}
		if ( ! $e['tg_token'] && ! $e['fb_token'] ) {
			$meldung[] = array( 'warning', 'Noch keine Zugangsdaten eingetragen.' );
		}
	}

	$gd = afk_social_bild_moeglich();
	?>
	<div class="wrap">
		<h1>Social Media</h1>
		<p>Beiträge werden einheitlich gepostet: gleiches Bild-Layout, gleicher Textaufbau. Im Beitragseditor gibt es dafür rechts den Kasten „Social Media“ mit Vorschau.</p>
		<?php foreach ( $meldung as $m ) : ?>
			<div class="notice notice-<?php echo esc_attr( $m[0] ); ?>"><p><?php echo esc_html( $m[1] ); ?></p></div>
		<?php endforeach; ?>
		<?php if ( is_wp_error( $gd ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $gd->get_error_message() ); ?></p></div>
		<?php endif; ?>
		<div class="notice notice-<?php echo afk_social_live() ? 'success' : 'warning'; ?> inline"><p>
			<?php echo afk_social_live() ? 'Diese Seite ist die Live-Seite – hier wird gepostet.' : 'Auf dieser Seite wird nicht gepostet (Adresse ' . esc_html( home_url() ) . ' ist nicht die eingetragene Live-Adresse). Vorschau funktioniert.'; ?>
		</p></div>

		<form method="post">
			<?php wp_nonce_field( 'afk_social_einst' ); ?>
			<h2>Allgemein</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="afk-s-live">Live-Adresse</label></th>
					<td><input class="regular-text" type="url" id="afk-s-live" name="live_url" value="<?php echo esc_attr( $e['live_url'] ); ?>" placeholder="https://www.afk-millstatt-radenthein.at">
					<p class="description">Nur wenn die Website unter genau dieser Adresse läuft, wird gepostet. Eine Staging-Kopie (z. B. /neu/) postet so nie versehentlich.</p></td></tr>
				<tr><th scope="row">Standard</th>
					<td><label><input type="checkbox" name="standard_an" value="1" <?php checked( 1, (int) $e['standard_an'] ); ?>> Bei neuen Beiträgen „Beim Veröffentlichen posten“ vorab anhaken</label></td></tr>
				<tr><th scope="row"><label for="afk-s-tags">Feste Hashtags</label></th>
					<td><input class="large-text" type="text" id="afk-s-tags" name="hashtags" value="<?php echo esc_attr( $e['hashtags'] ); ?>">
					<p class="description">Dazu kommen automatisch die beteiligten Feuerwehren (#ffmillstatt …).</p></td></tr>
				<tr><th scope="row"><label for="afk-s-vorlage">Textvorlage</label></th>
					<td><textarea class="large-text code" rows="9" id="afk-s-vorlage" name="vorlage"><?php echo esc_textarea( $e['vorlage'] ); ?></textarea>
					<p class="description">Platzhalter: {emoji} {art} {datum} {titel} {auszug} {beteiligt} {feuerwehren} {link} {hashtags}. Auf Instagram wird {link} zu „Ganzer Bericht auf …“.</p></td></tr>
			</table>

			<h2>Telegram</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="afk-s-tgt">Bot-Token</label></th>
					<td><input class="regular-text" type="password" id="afk-s-tgt" name="tg_token" value="" autocomplete="new-password" placeholder="<?php echo $e['tg_token'] ? '•••• gespeichert' : '123456:ABC…'; ?>">
					<?php if ( $e['tg_token'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="tg_token_loeschen" value="1"> löschen</label><?php endif; ?>
					<p class="description">Von @BotFather in Telegram. Den Bot anschließend als Administrator mit Recht „Nachrichten senden“ in den Kanal aufnehmen.</p></td></tr>
				<tr><th scope="row"><label for="afk-s-tgc">Kanal</label></th>
					<td><input class="regular-text" type="text" id="afk-s-tgc" name="tg_chat" value="<?php echo esc_attr( $e['tg_chat'] ); ?>" placeholder="@kanalname">
					<p class="description">Öffentlicher Kanal: @name. Privater Kanal: die Zahl, die mit -100 beginnt.</p></td></tr>
			</table>

			<h2>Facebook &amp; Instagram</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="afk-s-fbp">Facebook-Seiten-ID</label></th>
					<td><input class="regular-text" type="text" id="afk-s-fbp" name="fb_page" value="<?php echo esc_attr( $e['fb_page'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="afk-s-fbt">Seiten-Token</label></th>
					<td><input class="regular-text" type="password" id="afk-s-fbt" name="fb_token" value="" autocomplete="new-password" placeholder="<?php echo $e['fb_token'] ? '•••• gespeichert' : ''; ?>">
					<?php if ( $e['fb_token'] ) : ?><label style="margin-left:10px"><input type="checkbox" name="fb_token_loeschen" value="1"> löschen</label><?php endif; ?>
					<p class="description">Dauerhafter Seiten-Zugriffsschlüssel aus der eigenen Meta-App (siehe Anleitung in LIESMICH.md). Gilt auch für das verbundene Instagram-Konto.</p></td></tr>
				<tr><th scope="row"><label for="afk-s-ig">Instagram-Konto-ID</label></th>
					<td><input class="regular-text" type="text" id="afk-s-ig" name="ig_user" value="<?php echo esc_attr( $e['ig_user'] ); ?>">
					<p class="description">Wird bei „Verbindung prüfen“ automatisch eingetragen, wenn das Instagram-Konto mit der Seite verbunden ist. Leer = nicht auf Instagram posten.</p></td></tr>
				<tr><th scope="row"><label for="afk-s-gv">API-Version</label></th>
					<td><input class="small-text" type="text" id="afk-s-gv" name="graph" value="<?php echo esc_attr( $e['graph'] ); ?>"> <span class="description">Nur ändern, wenn Meta eine Version abschaltet.</span></td></tr>
			</table>
			<p>
				<button class="button button-primary" name="afk_social_speichern" value="1">Speichern</button>
				<button class="button" name="afk_social_testen" value="1">Verbindung prüfen</button>
			</p>
		</form>
	</div>
	<?php
}
