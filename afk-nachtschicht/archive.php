<?php
/**
 * Übersichten: alle Berichte, Kategorien, Datumsarchive, Schlagwörter, Sicherheitstipps.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

$afk_f    = afk_ns_filter_werte();
$afk_tipp = is_post_type_archive( 'afk_tipp' );

if ( is_home() ) {
	$afk_titel = 'Alle Berichte';
	$afk_desc  = '';
} elseif ( $afk_tipp ) {
	$afk_titel = 'Sicher durchs Jahr';
	$afk_desc  = 'Tipps unserer Feuerwehren, damit es gar nicht erst zum Einsatz kommt.';
} elseif ( is_category() && get_queried_object()->slug === get_theme_mod( 'afk_kat_alle', 'news' ) ) {
	$afk_titel = 'Alle Berichte';
	$afk_desc  = '';
} elseif ( is_category() || is_tag() ) {
	$afk_titel = single_term_title( '', false );
	$afk_desc  = wp_strip_all_tags( term_description() );
} elseif ( is_year() ) {
	$afk_titel = 'Berichte ' . get_query_var( 'year' );
	$afk_desc  = '';
} else {
	$afk_titel = wp_strip_all_tags( get_the_archive_title() );
	$afk_titel = preg_replace( '/^(Kategorie|Schlagwort|Archive|Jahr|Monat|Tag):\s*/u', '', $afk_titel );
	$afk_desc  = wp_strip_all_tags( get_the_archive_description() );
}

// Ist die Kategorie selbst Einsätze/Übungen/Allgemein? Dann keine Kategorie-Chips.
$afk_ist_unterkat = false;
if ( is_category() ) {
	$afk_slug = get_queried_object()->slug;
	foreach ( afk_ns_kategorien() as $k ) {
		if ( $k[0] === $afk_slug ) {
			$afk_ist_unterkat = true;
		}
	}
}
$afk_base = is_home() ? afk_ns_alle_berichte_url() : ( is_category() || is_tag() ? get_term_link( get_queried_object() ) : home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) ) );
$afk_base = preg_replace( '#/page/\d+/?$#', '/', (string) $afk_base );
?>

<?php afk_ns_seitenkopf( $afk_titel, $afk_tipp ? 'Sicherheit' : 'Berichte', $afk_desc ); ?>

<div class="wrap page-body" id="liste">
	<?php
	if ( ! $afk_tipp && ! is_date() ) {
		afk_ns_filterleiste(
			array(
				'action' => $afk_base,
				'anchor' => 'liste',
				'chips'  => ! $afk_ist_unterkat,
			)
		);
	}
	?>

	<?php if ( have_posts() ) : ?>
		<?php if ( ! $afk_tipp ) : ?>
			<p class="result-count" style="margin:0 0 16px">
				<?php
				global $wp_query;
				/* translators: %d Anzahl */
				echo esc_html( sprintf( _n( '%d Bericht', '%d Berichte', (int) $wp_query->found_posts, 'afk-nachtschicht' ), (int) $wp_query->found_posts ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $afk_tipp ) : ?>
			<div class="tipps">
				<?php
				while ( have_posts() ) :
					the_post();
					$afk_ist = function_exists( 'afk_tipp_ist_aktuell' ) && afk_tipp_ist_aktuell( get_the_ID() );
					?>
					<a class="tipp<?php echo $afk_ist ? ' tipp--aktuell' : ''; ?>" href="<?php the_permalink(); ?>">
						<?php if ( $afk_ist ) : ?><span class="tipp__badge">Jetzt aktuell</span><?php endif; ?>
						<span class="tipp__title"><?php the_title(); ?></span>
						<span class="tipp__text"><?php echo esc_html( get_the_excerpt() ); ?></span>
					</a>
				<?php endwhile; ?>
			</div>
		<?php else : ?>
			<div class="tiles">
				<?php
				while ( have_posts() ) {
					the_post();
					afk_ns_kachel( get_the_ID() );
				}
				?>
			</div>
		<?php endif; ?>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<p class="empty">Für diese Auswahl gibt es keine Beiträge.<?php echo $afk_f['aktiv'] ? ' <a href="' . esc_url( $afk_base ) . '">Filter zurücksetzen</a>' : ''; ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
