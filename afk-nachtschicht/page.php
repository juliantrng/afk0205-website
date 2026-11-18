<?php
/**
 * Seiten (Impressum, Datenschutz, Kontakt, Mitmachen …) und Sicherheitstipps.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$afk_tipp    = 'afk_tipp' === get_post_type();
	$afk_eyebrow = '';
	if ( $afk_tipp ) {
		$afk_eyebrow = '<a href="' . esc_url( get_post_type_archive_link( 'afk_tipp' ) ) . '">Sicher durchs Jahr</a>';
	} elseif ( wp_get_post_parent_id( get_the_ID() ) ) {
		$afk_parent  = wp_get_post_parent_id( get_the_ID() );
		$afk_eyebrow = '<a href="' . esc_url( get_permalink( $afk_parent ) ) . '">' . esc_html( get_the_title( $afk_parent ) ) . '</a>';
	}
	afk_ns_seitenkopf( get_the_title(), $afk_eyebrow, has_excerpt() ? get_the_excerpt() : '', true );
	?>
	<div class="wrap wrap--narrow page-body">
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="page-figure"><?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?></figure>
		<?php endif; ?>
		<div class="entry"><?php the_content(); ?></div>
		<?php
		wp_link_pages(
			array(
				'before' => '<nav class="pagination" aria-label="Seiten"><div class="nav-links">',
				'after'  => '</div></nav>',
			)
		);
		?>
		<?php if ( $afk_tipp ) : ?>
			<p class="more-row"><a class="more-link" href="<?php echo esc_url( get_post_type_archive_link( 'afk_tipp' ) ); ?>">← Alle Sicherheitstipps</a></p>
		<?php endif; ?>
	</div>
<?php endwhile; ?>

<?php
get_footer();
