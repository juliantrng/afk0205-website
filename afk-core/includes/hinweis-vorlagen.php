<?php
/**
 * Hinweisband: Vorlagen.
 *
 * Fertige Vorlagen für wiederkehrende Hinweise (Waldbrand, Advent, Silvester, Probealarm …)
 * und eigene Vorlagen, die direkt auf der Hinweisband-Seite gespeichert werden. Ein Klick füllt
 * das Formular; Platzhalter in [eckigen Klammern] werden markiert, damit man sie ersetzt.
 *
 * @package afk-core
 */

defined( 'ABSPATH' ) || exit;

const AFK_HINWEIS_VORLAGEN_OPTION = 'afk_hinweis_vorlagen';

/**
 * Mitgelieferte Vorlagen. „monate“ = wann sie typischerweise passen (für „passt jetzt“).
 *
 * @return array
 */
function afk_hinweis_vorlagen_standard() {
	$tipps = home_url( '/sicherheit/' );
	return array(
		array(
			'id'        => 'waldbrand',
			'name'      => 'Waldbrandverordnung',
			'monate'    => array( 3, 4, 5, 6, 7, 8, 9 ),
			'art'       => 'waldbrand',
			'titel'     => 'Waldbrandverordnung',
			'text'      => 'Im Bezirk Spittal/Drau gilt seit [Datum] eine Waldbrandverordnung: Feuer entzünden und Rauchen im Wald und in Waldnähe sind verboten.',
			'link_url'  => '',
			'link_text' => '',
			'tage'      => 0,
		),
		array(
			'id'        => 'trockenheit',
			'name'      => 'Trockenheit / Grillen',
			'monate'    => array( 6, 7, 8 ),
			'art'       => 'waldbrand',
			'titel'     => 'Erhöhte Brandgefahr',
			'text'      => 'Wiesen und Wälder sind sehr trocken. Grillen nur an sicheren Plätzen, Glut nie unbeaufsichtigt lassen, keine Zigaretten wegwerfen.',
			'link_url'  => $tipps,
			'link_text' => 'Sicher durchs Jahr',
			'tage'      => 14,
		),
		array(
			'id'        => 'unwetter',
			'name'      => 'Unwetter (händisch)',
			'monate'    => array( 5, 6, 7, 8, 9 ),
			'art'       => 'unwetter',
			'titel'     => 'Unwetter',
			'text'      => 'Für [heute Abend] sind schwere Gewitter angekündigt. Lose Gegenstände sichern, Keller und Abflüsse prüfen. Im Notfall: 122.',
			'link_url'  => '',
			'link_text' => '',
			'tage'      => 1,
		),
		array(
			'id'        => 'sperre',
			'name'      => 'Straßensperre',
			'monate'    => array(),
			'art'       => 'sperre',
			'titel'     => 'Straßensperre',
			'text'      => 'Die [Straße] ist zwischen [Ort] und [Ort] wegen [Grund] bis voraussichtlich [Uhrzeit] gesperrt. Bitte die Umleitung beachten.',
			'link_url'  => '',
			'link_text' => '',
			'tage'      => 1,
		),
		array(
			'id'        => 'probealarm',
			'name'      => 'Zivilschutz-Probealarm',
			'monate'    => array( 9, 10 ),
			'art'       => 'info',
			'titel'     => 'Zivilschutz-Probealarm',
			'text'      => 'Am Samstag, [Datum], zwischen 12 und 12:45 Uhr werden österreichweit die Sirenen getestet – kein Grund zur Sorge, nicht 122 anrufen.',
			'link_url'  => '',
			'link_text' => '',
			'tage'      => 7,
		),
		array(
			'id'        => 'eis',
			'name'      => 'Eis auf dem See',
			'monate'    => array( 12, 1, 2 ),
			'art'       => 'info',
			'titel'     => 'Achtung Eis',
			'text'      => 'Eisflächen auf Seen und Teichen nur betreten, wenn sie von der Gemeinde freigegeben sind. Bricht jemand ein: sofort 122 anrufen.',
			'link_url'  => $tipps,
			'link_text' => 'Sicher durchs Jahr',
			'tage'      => 30,
		),
		array(
			'id'        => 'advent',
			'name'      => 'Advent',
			'monate'    => array( 11, 12 ),
			'art'       => 'info',
			'titel'     => 'Advent',
			'text'      => 'Adventkranz und Kerzen nie unbeaufsichtigt brennen lassen – ein trockener Kranz brennt in Sekunden.',
			'link_url'  => $tipps,
			'link_text' => 'Sicher durchs Jahr',
			'tage'      => 0,
		),
		array(
			'id'        => 'silvester',
			'name'      => 'Silvester',
			'monate'    => array( 12, 1 ),
			'art'       => 'info',
			'titel'     => 'Silvester',
			'text'      => 'Feuerwerk nur im Freien zünden, mit Abstand zu Gebäuden und Wald. Blindgänger nie ein zweites Mal anzünden.',
			'link_url'  => $tipps,
			'link_text' => 'Sicher durchs Jahr',
			'tage'      => 4,
		),
		array(
			'id'        => 'rauchmelder',
			'name'      => 'Rauchmelder',
			'monate'    => array( 10, 11, 12, 1, 2 ),
			'art'       => 'info',
			'titel'     => 'Rauchmelder retten Leben',
			'text'      => 'Jetzt kurz die Testtaste drücken: Funktioniert der Rauchmelder? Batterie im Zweifel tauschen.',
			'link_url'  => $tipps,
			'link_text' => 'Sicher durchs Jahr',
			'tage'      => 14,
		),
		array(
			'id'        => 'fest',
			'name'      => 'Einladung zum Fest',
			'monate'    => array(),
			'art'       => 'info',
			'titel'     => 'Herzliche Einladung',
			'text'      => '[Fest] der FF [Ort] am [Datum] ab [Uhrzeit] – wir freuen uns auf euren Besuch!',
			'link_url'  => home_url( '/termine/' ),
			'link_text' => 'Alle Termine',
			'tage'      => 0,
		),
		array(
			'id'        => 'mitglieder',
			'name'      => 'Mitglieder gesucht',
			'monate'    => array(),
			'art'       => 'info',
			'titel'     => 'Werde Teil davon',
			'text'      => 'Die Feuerwehren im Abschnitt suchen neue Mitglieder – für den Einsatzdienst, die Feuerwehrjugend und viele Aufgaben dazwischen.',
			'link_url'  => home_url( '/mitmachen/' ),
			'link_text' => 'Mitmachen',
			'tage'      => 0,
		),
	);
}

/**
 * Eigene Vorlagen.
 *
 * @return array
 */
function afk_hinweis_vorlagen_eigene() {
	$v = get_option( AFK_HINWEIS_VORLAGEN_OPTION, array() );
	return is_array( $v ) ? array_values( $v ) : array();
}

/**
 * Vorlagen-Bereich auf der Hinweisband-Seite.
 */
function afk_hinweis_vorlagen_box() {
	$monat    = (int) wp_date( 'n' );
	$standard = afk_hinweis_vorlagen_standard();
	usort(
		$standard,
		function ( $a, $b ) use ( $monat ) {
			return (int) in_array( $monat, $b['monate'], true ) <=> (int) in_array( $monat, $a['monate'], true );
		}
	);
	$eigene = afk_hinweis_vorlagen_eigene();
	?>
	<h2>Vorlagen</h2>
	<p class="description">Ein Klick füllt das Formular unten. Teile in [eckigen Klammern] ersetzen, dann „Hinweis speichern“.</p>
	<div class="afk-vorlagen">
		<?php foreach ( $standard as $v ) : ?>
			<?php $jetzt = in_array( $monat, $v['monate'], true ); ?>
			<button type="button" class="afk-vorlage afk-vorlage--<?php echo esc_attr( $v['art'] ); ?>" data-vorlage="<?php echo esc_attr( wp_json_encode( $v ) ); ?>">
				<span class="afk-vorlage__name"><?php echo esc_html( $v['name'] ); ?></span>
				<?php if ( $jetzt ) : ?><span class="afk-vorlage__jetzt">passt jetzt</span><?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>
	<h3 style="margin:18px 0 8px">Eigene Vorlagen</h3>
	<div class="afk-vorlagen" id="afk-eigene-vorlagen">
		<?php if ( ! $eigene ) : ?><span class="description" id="afk-keine-vorlagen">Noch keine – unten „Als Vorlage speichern“ nutzen.</span><?php endif; ?>
		<?php foreach ( $eigene as $v ) : ?>
			<span class="afk-vorlage-eigen">
				<button type="button" class="afk-vorlage afk-vorlage--<?php echo esc_attr( $v['art'] ); ?>" data-vorlage="<?php echo esc_attr( wp_json_encode( $v ) ); ?>"><span class="afk-vorlage__name"><?php echo esc_html( $v['name'] ); ?></span></button>
				<button type="button" class="afk-vorlage-weg" data-weg="<?php echo esc_attr( $v['id'] ); ?>" aria-label="<?php echo esc_attr( 'Vorlage „' . $v['name'] . '“ löschen' ); ?>">×</button>
			</span>
		<?php endforeach; ?>
	</div>
	<div id="afk-vorlage-meldung" class="notice notice-info inline" style="display:none;margin:12px 0 0"><p></p></div>
	<style>
		.afk-vorlagen { display: flex; flex-wrap: wrap; gap: 8px; max-width: 1000px; }
		.afk-vorlage { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border: 1px solid #c3c4c7; border-left: 5px solid #8c8f94; border-radius: 4px; background: #fff; cursor: pointer; font-size: 13px; }
		.afk-vorlage:hover, .afk-vorlage:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
		.afk-vorlage--waldbrand { border-left-color: #D9C820; }
		.afk-vorlage--unwetter { border-left-color: #8DB0D6; }
		.afk-vorlage--sperre { border-left-color: #C10000; }
		.afk-vorlage__jetzt { font-size: 11px; padding: 1px 6px; border-radius: 10px; background: #e7f5ec; color: #1E7A35; }
		.afk-vorlage-eigen { display: inline-flex; align-items: stretch; }
		.afk-vorlage-eigen .afk-vorlage { border-radius: 4px 0 0 4px; }
		.afk-vorlage-weg { border: 1px solid #c3c4c7; border-left: 0; border-radius: 0 4px 4px 0; background: #fff; cursor: pointer; padding: 0 9px; color: #b32d2e; font-size: 16px; }
		.afk-vorlage-weg:hover { background: #fcf0f1; }
		.afk-vorlage-speichern { display: flex; gap: 8px; align-items: center; margin: -8px 0 24px; }
	</style>
	<?php
}

/**
 * „Als Vorlage speichern“ unter dem Formular + Skript.
 */
function afk_hinweis_vorlagen_skript() {
	$name = AFK_HINWEIS_OPTION;
	?>
	<div class="afk-vorlage-speichern">
		<label for="afk-vorlage-name" class="screen-reader-text">Name der Vorlage</label>
		<input type="text" id="afk-vorlage-name" class="regular-text" maxlength="40" placeholder="Name der neuen Vorlage, z. B. Kirtag Döbriach">
		<button type="button" class="button" id="afk-vorlage-speichern">Aktuellen Inhalt als Vorlage speichern</button>
	</div>
	<script>
	( function () {
		var n = <?php echo wp_json_encode( $name ); ?>;
		var feld = function ( k ) { return document.querySelector( '[name="' + n + '[' + k + ']"]' ); };
		var meldung = document.getElementById( 'afk-vorlage-meldung' );
		function melden( text ) { meldung.style.display = ''; meldung.querySelector( 'p' ).textContent = text; }
		function zweistellig( z ) { return ( z < 10 ? '0' : '' ) + z; }
		function einsetzen( v ) {
			feld( 'aktiv' ).checked = true;
			feld( 'art' ).value = v.art;
			feld( 'titel' ).value = v.titel;
			feld( 'text' ).value = v.text;
			feld( 'link_url' ).value = v.link_url || '';
			feld( 'link_text' ).value = v.link_text || '';
			var ende = feld( 'ende' );
			if ( v.tage && v.tage > 0 ) {
				var d = new Date( Date.now() + v.tage * 86400000 );
				ende.value = d.getFullYear() + '-' + zweistellig( d.getMonth() + 1 ) + '-' + zweistellig( d.getDate() ) + 'T23:59';
			} else {
				ende.value = '';
			}
			// Ersten Platzhalter markieren.
			var ziel = null;
			[ feld( 'titel' ), feld( 'text' ) ].some( function ( f ) {
				var i = f.value.indexOf( '[' );
				if ( i >= 0 ) { ziel = [ f, i, f.value.indexOf( ']', i ) + 1 ]; return true; }
				return false;
			} );
			feld( 'aktiv' ).closest( 'form' ).scrollIntoView( { behavior: 'smooth', block: 'start' } );
			if ( ziel ) {
				setTimeout( function () { ziel[0].focus(); ziel[0].setSelectionRange( ziel[1], ziel[2] ); }, 400 );
				melden( 'Vorlage „' + v.name + '“ eingefügt – bitte die Teile in [ ] ersetzen und dann „Hinweis speichern“.' );
			} else {
				melden( 'Vorlage „' + v.name + '“ eingefügt – prüfen und „Hinweis speichern“.' );
			}
		}
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-vorlage]' );
			if ( b ) { einsetzen( JSON.parse( b.getAttribute( 'data-vorlage' ) ) ); return; }
			var w = e.target.closest( '[data-weg]' );
			if ( w ) {
				var fd = new FormData();
				fd.append( 'action', 'afk_hinweis_vorlage' );
				fd.append( 'was', 'loeschen' );
				fd.append( 'id', w.getAttribute( 'data-weg' ) );
				fd.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'afk_hinweis_vorlage' ) ); ?> );
				fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
					if ( r.success ) { w.closest( '.afk-vorlage-eigen' ).remove(); melden( 'Vorlage gelöscht.' ); }
				} );
			}
		} );
		document.getElementById( 'afk-vorlage-speichern' ).addEventListener( 'click', function () {
			var name = document.getElementById( 'afk-vorlage-name' );
			if ( ! name.value.trim() ) { name.focus(); melden( 'Bitte zuerst einen Namen für die Vorlage eingeben.' ); return; }
			if ( ! feld( 'titel' ).value.trim() && ! feld( 'text' ).value.trim() ) { melden( 'Überschrift oder Text ausfüllen, dann als Vorlage speichern.' ); return; }
			var fd = new FormData();
			fd.append( 'action', 'afk_hinweis_vorlage' );
			fd.append( 'was', 'speichern' );
			fd.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'afk_hinweis_vorlage' ) ); ?> );
			[ 'art', 'titel', 'text', 'link_url', 'link_text' ].forEach( function ( k ) { fd.append( k, feld( k ).value ); } );
			fd.append( 'name', name.value );
			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
				if ( ! r.success ) { melden( r.data || 'Speichern fehlgeschlagen.' ); return; }
				var leer = document.getElementById( 'afk-keine-vorlagen' );
				if ( leer ) { leer.remove(); }
				document.getElementById( 'afk-eigene-vorlagen' ).insertAdjacentHTML( 'beforeend', r.data.html );
				name.value = '';
				melden( 'Als Vorlage „' + r.data.name + '“ gespeichert.' );
			} );
		} );
	}() );
	</script>
	<?php
}

/**
 * AJAX: eigene Vorlage speichern / löschen.
 */
function afk_hinweis_vorlage_ajax() {
	check_ajax_referer( 'afk_hinweis_vorlage', 'nonce' );
	if ( ! current_user_can( AFK_HINWEIS_CAP ) ) {
		wp_send_json_error( 'Keine Berechtigung.' );
	}
	$eigene = afk_hinweis_vorlagen_eigene();
	$was    = isset( $_POST['was'] ) ? sanitize_key( $_POST['was'] ) : '';
	if ( 'loeschen' === $was ) {
		$id     = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';
		$eigene = array_values(
			array_filter(
				$eigene,
				function ( $v ) use ( $id ) {
					return $v['id'] !== $id;
				}
			)
		);
		update_option( AFK_HINWEIS_VORLAGEN_OPTION, $eigene, false );
		wp_send_json_success();
	}
	$arten = afk_hinweis_arten();
	$in    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$v     = array(
		'id'        => 'eigen-' . strtolower( wp_generate_password( 8, false ) ),
		'name'      => mb_substr( sanitize_text_field( (string) ( $in['name'] ?? '' ) ), 0, 40 ),
		'monate'    => array(),
		'art'       => isset( $arten[ $in['art'] ?? '' ] ) ? $in['art'] : 'info',
		'titel'     => mb_substr( sanitize_text_field( (string) ( $in['titel'] ?? '' ) ), 0, 60 ),
		'text'      => mb_substr( sanitize_textarea_field( (string) ( $in['text'] ?? '' ) ), 0, 240 ),
		'link_url'  => esc_url_raw( (string) ( $in['link_url'] ?? '' ) ),
		'link_text' => mb_substr( sanitize_text_field( (string) ( $in['link_text'] ?? '' ) ), 0, 40 ),
		'tage'      => 0,
	);
	if ( '' === $v['name'] ) {
		wp_send_json_error( 'Bitte einen Namen eingeben.' );
	}
	if ( count( $eigene ) >= 30 ) {
		wp_send_json_error( 'Höchstens 30 eigene Vorlagen – bitte zuerst eine löschen.' );
	}
	$eigene[] = $v;
	update_option( AFK_HINWEIS_VORLAGEN_OPTION, $eigene, false );
	$html = '<span class="afk-vorlage-eigen"><button type="button" class="afk-vorlage afk-vorlage--' . esc_attr( $v['art'] ) . '" data-vorlage="' . esc_attr( wp_json_encode( $v ) ) . '"><span class="afk-vorlage__name">' . esc_html( $v['name'] ) . '</span></button>'
		. '<button type="button" class="afk-vorlage-weg" data-weg="' . esc_attr( $v['id'] ) . '" aria-label="' . esc_attr( 'Vorlage „' . $v['name'] . '“ löschen' ) . '">×</button></span>';
	wp_send_json_success(
		array(
			'html' => $html,
			'name' => $v['name'],
		)
	);
}
add_action( 'wp_ajax_afk_hinweis_vorlage', 'afk_hinweis_vorlage_ajax' );
