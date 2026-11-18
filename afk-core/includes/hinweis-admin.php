<?php
/**
 * Einstellungsseite „Hinweisband“ – ohne ACF, mit der WordPress-Settings-API.
 * Alle Werte liegen in einer Option: afk_hinweis (Array).
 * Bearbeiten dürfen alle, die Beiträge bearbeiten dürfen (edit_posts).
 */

defined( 'ABSPATH' ) || exit;

const AFK_HINWEIS_OPTION = 'afk_hinweis';
const AFK_HINWEIS_CAP    = 'edit_posts';

/**
 * Arten des Hinweisbands.
 *
 * @return array
 */
function afk_hinweis_arten() {
	return array(
		'waldbrand' => 'Waldbrandgefahr',
		'unwetter'  => 'Unwetter',
		'sperre'    => 'Straßensperre',
		'info'      => 'Allgemeine Information',
	);
}

/**
 * Gespeicherte Werte mit Standardwerten.
 *
 * @return array
 */
function afk_hinweis_optionen() {
	$defaults = array(
		'aktiv'     => 0,
		'art'       => 'info',
		'titel'     => '',
		'text'      => '',
		'link_url'  => '',
		'link_text' => '',
		'ende'      => '',
	);
	$saved = get_option( AFK_HINWEIS_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * Eingaben prüfen und bereinigen.
 *
 * @param mixed $input Formularwerte.
 * @return array
 */
function afk_hinweis_sanitize( $input ) {
	$input = is_array( $input ) ? $input : array();
	$arten = afk_hinweis_arten();

	$out = array(
		'aktiv'     => empty( $input['aktiv'] ) ? 0 : 1,
		'art'       => isset( $input['art'], $arten[ $input['art'] ] ) ? $input['art'] : 'info',
		'titel'     => isset( $input['titel'] ) ? mb_substr( sanitize_text_field( $input['titel'] ), 0, 60 ) : '',
		'text'      => isset( $input['text'] ) ? mb_substr( sanitize_textarea_field( $input['text'] ), 0, 240 ) : '',
		'link_url'  => isset( $input['link_url'] ) ? esc_url_raw( trim( $input['link_url'] ) ) : '',
		'link_text' => isset( $input['link_text'] ) ? mb_substr( sanitize_text_field( $input['link_text'] ), 0, 40 ) : '',
		'ende'      => '',
	);

	// <input type="datetime-local"> liefert „2026-10-20T18:00“. Beim ersten Speichern
	// ruft WordPress die Bereinigung zweimal auf – daher auch das gespeicherte Format akzeptieren.
	if ( ! empty( $input['ende'] ) ) {
		$raw = str_replace( 'T', ' ', substr( (string) $input['ende'], 0, 16 ) );
		$dt  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $raw, wp_timezone() );
		if ( $dt ) {
			$out['ende'] = $dt->format( 'Y-m-d H:i' );
		}
	}

	if ( $out['aktiv'] && '' === $out['titel'] && '' === $out['text'] ) {
		add_settings_error( AFK_HINWEIS_OPTION, 'afk_leer', 'Das Hinweisband ist eingeschaltet, hat aber weder Überschrift noch Text – es wird nicht angezeigt.', 'warning' );
	}

	return $out;
}

/**
 * Option registrieren.
 */
function afk_hinweis_register_setting() {
	register_setting(
		'afk_hinweis_group',
		AFK_HINWEIS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'afk_hinweis_sanitize',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'afk_hinweis_register_setting' );

/**
 * options.php verlangt sonst manage_options (nur Admins).
 */
function afk_hinweis_option_capability() {
	return AFK_HINWEIS_CAP;
}
add_filter( 'option_page_capability_afk_hinweis_group', 'afk_hinweis_option_capability' );

/**
 * Menüpunkt.
 */
function afk_hinweis_admin_menu() {
	add_menu_page( 'Hinweisband', 'Hinweisband', AFK_HINWEIS_CAP, 'afk-hinweisband', 'afk_hinweis_admin_page', 'dashicons-warning', 3 );
}
add_action( 'admin_menu', 'afk_hinweis_admin_menu' );

/**
 * Stylesheet für die Vorschau auf der Einstellungsseite.
 *
 * @param string $hook Aktuelle Admin-Seite.
 */
function afk_hinweis_admin_assets( $hook ) {
	if ( 'toplevel_page_afk-hinweisband' === $hook ) {
		wp_enqueue_style( 'afk-core', AFK_CORE_URL . 'assets/afk.css', array(), AFK_CORE_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'afk_hinweis_admin_assets' );

/**
 * Einstellungsseite.
 */
function afk_hinweis_admin_page() {
	if ( ! current_user_can( AFK_HINWEIS_CAP ) ) {
		return;
	}
	$o    = afk_hinweis_optionen();
	$name = AFK_HINWEIS_OPTION;
	$ende = $o['ende'] ? str_replace( ' ', 'T', $o['ende'] ) : '';
	$live = afk_hinweis_get();
	?>
	<div class="wrap">
		<h1>Hinweisband</h1>
		<p>Ein kurzer Hinweis ganz oben auf jeder Seite – z. B. bei Waldbrandverordnung, Unwetterwarnung oder Straßensperre.</p>

		<?php settings_errors( AFK_HINWEIS_OPTION ); ?>

		<h2>Status</h2>
		<?php if ( $live ) : ?>
			<p><strong style="color:#1E7A35">● Wird derzeit angezeigt</strong><?php echo $o['ende'] ? ' – bis ' . esc_html( ( new DateTimeImmutable( $o['ende'], wp_timezone() ) )->format( 'd.m.Y, H:i' ) ) . ' Uhr' : ''; ?></p>
			<div style="max-width:900px;border:1px solid #dcdcde;margin:8px 0 24px"><?php echo afk_hinweis_render(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php elseif ( $o['aktiv'] && $o['ende'] ) : ?>
			<p><strong style="color:#996800">● Abgelaufen</strong> – das Ablaufdatum ist vorbei, das Band wird nicht mehr angezeigt.</p>
		<?php else : ?>
			<p><strong>● Ausgeschaltet</strong></p>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'afk_hinweis_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Anzeigen</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[aktiv]" value="1" <?php checked( 1, (int) $o['aktiv'] ); ?>> Hinweisband auf der Website anzeigen</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-h-art">Art</label></th>
					<td>
						<select id="afk-h-art" name="<?php echo esc_attr( $name ); ?>[art]">
							<?php foreach ( afk_hinweis_arten() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $o['art'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Bestimmt die Farbe: Waldbrand gelb, Unwetter blau, Sperre rot, Info grau.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-h-titel">Überschrift</label></th>
					<td><input type="text" id="afk-h-titel" class="regular-text" maxlength="60" name="<?php echo esc_attr( $name ); ?>[titel]" value="<?php echo esc_attr( $o['titel'] ); ?>" placeholder="z. B. Erhöhte Waldbrandgefahr"></td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-h-text">Text</label></th>
					<td>
						<textarea id="afk-h-text" class="large-text" rows="2" maxlength="240" name="<?php echo esc_attr( $name ); ?>[text]"><?php echo esc_textarea( $o['text'] ); ?></textarea>
						<p class="description">Ein bis zwei Sätze, max. 240 Zeichen.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-h-link">Link (optional)</label></th>
					<td>
						<input type="url" id="afk-h-link" class="regular-text" name="<?php echo esc_attr( $name ); ?>[link_url]" value="<?php echo esc_attr( $o['link_url'] ); ?>" placeholder="https://…">
						<p><label for="afk-h-linktext">Linktext</label><br>
						<input type="text" id="afk-h-linktext" class="regular-text" maxlength="40" name="<?php echo esc_attr( $name ); ?>[link_text]" value="<?php echo esc_attr( $o['link_text'] ); ?>" placeholder="Mehr Informationen"></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="afk-h-ende">Automatisch ausblenden ab</label></th>
					<td>
						<input type="datetime-local" id="afk-h-ende" name="<?php echo esc_attr( $name ); ?>[ende]" value="<?php echo esc_attr( $ende ); ?>">
						<p class="description">Optional. Danach verschwindet das Band von selbst. Leer lassen = bis zum Ausschalten.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Hinweis speichern' ); ?>
		</form>
	</div>
	<?php
}
