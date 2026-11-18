<?php
/**
 * Suche: Berichte, Seiten und Sicherheitstipps.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();
$afk_s = get_search_query();
?>
<header class="page-head">
	<div class="wrap wrap--narrow">
		<p class="eyebrow">Suche</p>
		<h1 class="page-head__title"><?php echo '' !== $afk_s ? '„' . esc_html( $afk_s ) . '“' : 'Was suchst du?'; ?></h1>
		<?php get_search_form(); ?>
	</div>
</header>
<div class="wrap wrap--narrow page-body">
	<?php if ( '' === $afk_s ) : ?>
		<p class="section-sub">Suchbegriff eingeben, z. B. „Waldbrand“, „Döbriach“ oder „Atemschutz“.</p>
	<?php elseif ( have_posts() ) : ?>
		<p class="result-count" style="margin:0 0 8px">
			<?php
			global $wp_query;
			/* translators: %d Anzahl */
			echo esc_html( sprintf( _n( '%d Treffer', '%d Treffer', (int) $wp_query->found_posts, 'afk-nachtschicht' ), (int) $wp_query->found_posts ) );
			?>
		</p>
		<div class="list">
			<?php
			while ( have_posts() ) :
				the_post();
				$afk_typ = get_post_type();
				if ( 'post' === $afk_typ ) {
					$afk_meta = trim( afk_ns_kat_label( get_the_ID() ) . ' · ' . get_the_date( 'd.m.Y' ), ' ·' );
				} elseif ( 'afk_tipp' === $afk_typ ) {
					$afk_meta = 'Sicherheitstipp';
				} else {
					$afk_meta = 'Seite';
				}
				?>
				<a class="search-hit" href="<?php the_permalink(); ?>">
					<span class="search-hit__meta"><?php echo esc_html( $afk_meta ); ?></span>
					<span class="search-hit__title"><?php the_title(); ?></span>
					<span class="search-hit__text"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, ' …' ) ); ?></span>
				</a>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<p class="empty">Keine Treffer für „<?php echo esc_html( $afk_s ); ?>“. Versuch es mit einem kürzeren Begriff oder einem Ortsnamen.</p>
	<?php endif; ?>
</div>
<?php
get_footer();
