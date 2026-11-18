<?php
/**
 * Taxonomie „Feuerwehr“: Gemeinden als übergeordnete Begriffe, darunter die Wehren.
 * Archiv-URL einer Wehr: /feuerwehr/<slug>/ – das ist gleichzeitig die Feuerwehr-Seite.
 */

defined( 'ABSPATH' ) || exit;

function afk_register_taxonomy() {
	$labels = array(
		'name'              => 'Feuerwehren',
		'singular_name'     => 'Feuerwehr',
		'menu_name'         => 'Feuerwehren',
		'all_items'         => 'Alle Feuerwehren',
		'edit_item'         => 'Feuerwehr bearbeiten',
		'view_item'         => 'Feuerwehr ansehen',
		'update_item'       => 'Feuerwehr aktualisieren',
		'add_new_item'      => 'Neue Feuerwehr hinzufügen',
		'new_item_name'     => 'Name der Feuerwehr',
		'parent_item'       => 'Gemeinde',
		'parent_item_colon' => 'Gemeinde:',
		'search_items'      => 'Feuerwehren durchsuchen',
		'not_found'         => 'Keine Feuerwehr gefunden',
		'back_to_items'     => '← Zurück zu den Feuerwehren',
	);

	register_taxonomy(
		'feuerwehr',
		array( 'post', 'tribe_events' ),
		array(
			'labels'            => $labels,
			'description'       => 'Beteiligte Feuerwehren eines Berichts. Gemeinden sind übergeordnete Begriffe.',
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'show_tagcloud'     => false,
			'rewrite'           => array(
				'slug'         => 'feuerwehr',
				'with_front'   => false,
				'hierarchical' => false,
			),
		)
	);
}
add_action( 'init', 'afk_register_taxonomy' );

/**
 * Die 12 Feuerwehren des Abschnitts, nach Gemeinde.
 * Format: array( Name, Slug, Website oder '' ).
 */
function afk_feuerwehr_seed_data() {
	return array(
		'Bad Kleinkirchheim' => array(
			array( 'FF Bad Kleinkirchheim', 'ff-bad-kleinkirchheim', 'https://www.ff-badkleinkirchheim.at' ),
		),
		'Radenthein'         => array(
			array( 'FF Radenthein', 'ff-radenthein', '' ),
			array( 'FF Döbriach', 'ff-doebriach', '' ),
			array( 'FF Kaning', 'ff-kaning', 'https://www.feuerwehr-kaning.at' ),
			array( 'FF St. Peter ob Radenthein', 'ff-st-peter-ob-radenthein', '' ),
			array( 'FF Untertweng', 'ff-untertweng', 'https://www.ff-untertweng.at' ),
			array( 'BtF RHI Magnesita', 'btf-rhi-magnesita', '' ),
		),
		'Millstatt'          => array(
			array( 'FF Millstatt', 'ff-millstatt', 'https://www.ff-millstatt.at' ),
			array( 'FF Obermillstatt', 'ff-obermillstatt', '' ),
			array( 'FF Laubendorf', 'ff-laubendorf', 'https://www.ff-laubendorf.at' ),
			array( 'FF Lammersdorf', 'ff-lammersdorf', '' ),
			array( 'FF Matzelsdorf', 'ff-matzelsdorf', '' ),
		),
	);
}

/**
 * Legt Gemeinden und Wehren an. Bestehende Begriffe (gleicher Slug) bleiben unverändert,
 * die Funktion kann also gefahrlos mehrfach laufen.
 */
function afk_seed_feuerwehren() {
	foreach ( afk_feuerwehr_seed_data() as $gemeinde => $wehren ) {
		$g_slug = 'gemeinde-' . sanitize_title( $gemeinde );
		$parent = get_term_by( 'slug', $g_slug, 'feuerwehr' );
		if ( $parent ) {
			$parent_id = (int) $parent->term_id;
		} else {
			$created = wp_insert_term( 'Gemeinde ' . $gemeinde, 'feuerwehr', array( 'slug' => $g_slug ) );
			if ( is_wp_error( $created ) ) {
				continue;
			}
			$parent_id = (int) $created['term_id'];
		}

		foreach ( $wehren as $wehr ) {
			list( $name, $slug, $website ) = $wehr;
			if ( get_term_by( 'slug', $slug, 'feuerwehr' ) ) {
				continue;
			}
			$created = wp_insert_term(
				$name,
				'feuerwehr',
				array(
					'slug'   => $slug,
					'parent' => $parent_id,
				)
			);
			if ( is_wp_error( $created ) ) {
				continue;
			}
			if ( $website ) {
				update_term_meta( $created['term_id'], 'afk_website', esc_url_raw( $website ) );
				update_term_meta( $created['term_id'], '_afk_website', 'field_afk_ff_website' );
			}
		}
	}
}

/**
 * Einmalige Korrekturen an bestehenden Einträgen nach einem Update.
 * Ändert nur Namen, die noch genau dem früheren Vorschlag entsprechen – händisch
 * vergebene Namen bleiben unberührt.
 */
function afk_feuerwehren_migrieren() {
	$stand = (int) get_option( 'afk_feuerwehren_stand', 0 );
	if ( $stand >= 2 ) {
		return;
	}
	$btf = get_term_by( 'slug', 'btf-rhi-magnesita', 'feuerwehr' );
	if ( $btf && $stand < 1 && 'BtF RHI Magnesita – Werk Radenthein' === $btf->name ) {
		wp_update_term( $btf->term_id, 'feuerwehr', array( 'name' => 'BtF RHI Magnesita' ) );
	}
	// In den KLFV-Alarmierungen heißt die Betriebsfeuerwehr „BtF Veitsch-Radex“.
	if ( $btf && '' === trim( (string) get_term_meta( $btf->term_id, 'afk_alarmname', true ) ) ) {
		update_term_meta( $btf->term_id, 'afk_alarmname', 'BtF Veitsch-Radex' );
		update_term_meta( $btf->term_id, '_afk_alarmname', 'field_afk_ff_alarmname' );
	}
	update_option( 'afk_feuerwehren_stand', 2 );
}
add_action( 'init', 'afk_feuerwehren_migrieren', 20 );

/**
 * Die Wehren eines Beitrags (ohne Gemeinde-Begriffe; wurde nur eine Gemeinde
 * angehakt, wird diese zurückgegeben).
 *
 * @param int|null $post_id Beitrag, Standard: aktueller Beitrag.
 * @return WP_Term[]
 */
function afk_get_feuerwehren( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$terms   = get_the_terms( $post_id, 'feuerwehr' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	$wehren = array_filter(
		$terms,
		function ( $t ) {
			return 0 !== (int) $t->parent;
		}
	);
	$list   = $wehren ? array_values( $wehren ) : array_values( $terms );
	usort(
		$list,
		function ( $a, $b ) {
			return strcmp( $a->name, $b->name );
		}
	);
	return $list;
}
