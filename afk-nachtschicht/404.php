<?php
/**
 * Seite nicht gefunden.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();
afk_ns_seitenkopf( 'Seite nicht gefunden', 'Fehler 404', 'Diese Adresse gibt es nicht (mehr). Vielleicht hilft die Suche oder einer der neuesten Berichte.', true );
$afk_neu = get_posts( array( 'numberposts' => 3 ) );
?>
<div class="wrap wrap--narrow page-body">
	<?php get_search_form(); ?>
	<p class="more-row"><a class="more-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Startseite →</a></p>
</div>
<?php if ( $afk_neu ) : ?>
	<section class="band section" aria-labelledby="afk-404-h">
		<div class="wrap">
			<div class="section-head"><h2 class="section-title section-title--sm" id="afk-404-h">Neueste Berichte</h2></div>
			<div class="tiles tiles--3">
				<?php foreach ( $afk_neu as $p ) { afk_ns_kachel( $p->ID ); } ?>
			</div>
		</div>
	</section>
<?php endif; ?>
<?php
get_footer();
