<?php
/**
 * Einzelner Bericht.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$afk_id      = get_the_ID();
	$afk_kat     = afk_ns_kat_label( $afk_id );
	$afk_cats    = get_the_category();
	$afk_verf    = trim( (string) afk_ns_field( 'afk_verfasser', $afk_id ) );
	$afk_verf    = '' !== $afk_verf ? $afk_verf : get_the_author();
	$afk_einheit = trim( (string) afk_ns_field( 'afk_verfasser_einheit', $afk_id ) );
	$afk_bild    = trim( (string) afk_ns_field( 'afk_bildquelle', $afk_id ) );
	$afk_extern  = afk_ns_lines( afk_ns_field( 'afk_weitere_kraefte', $afk_id ) );
	$afk_wehren  = function_exists( 'afk_get_feuerwehren' ) ? afk_get_feuerwehren( $afk_id ) : array();
	$afk_init    = '';
	foreach ( preg_split( '/\s+/u', $afk_verf ) as $afk_teil ) {
		$afk_init .= mb_substr( $afk_teil, 0, 1 );
	}
	$afk_init = mb_strtoupper( mb_substr( $afk_init, 0, 2 ) );
	$afk_hat_bild = has_post_thumbnail();
	?>

	<section class="hero hero--post<?php echo $afk_hat_bild ? '' : ' hero--noimg'; ?>">
		<?php
		if ( has_post_thumbnail() ) {
			the_post_thumbnail( 'afk-hero', array( 'class' => 'hero__img', 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager' ) );
		}
		?>
		<div class="wrap hero__inner">
			<div class="hero__box">
				<nav class="crumbs" aria-label="Brotkrumen">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a><span aria-hidden="true">/</span>
					<?php
					$afk_crumb_cat = null;
					foreach ( $afk_cats as $c ) {
						if ( $c->parent ) {
							$afk_crumb_cat = $c;
							break;
						}
					}
					$afk_crumb_cat = $afk_crumb_cat ? $afk_crumb_cat : ( $afk_cats ? $afk_cats[0] : null );
					if ( $afk_crumb_cat ) :
						?>
						<a href="<?php echo esc_url( get_category_link( $afk_crumb_cat ) ); ?>"><?php echo esc_html( $afk_crumb_cat->name ); ?></a><span aria-hidden="true">/</span>
					<?php endif; ?>
					<span aria-current="page"><?php echo esc_html( wp_trim_words( get_the_title(), 5, ' …' ) ); ?></span>
				</nav>
				<p class="hero__meta">
					<?php if ( $afk_kat ) : ?><span class="hero__kat"><?php echo esc_html( $afk_kat ); ?></span><?php endif; ?>
					<span><?php echo esc_html( get_the_date( 'l, j. F Y' ) ); ?></span>
				</p>
				<h1 class="hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="hero__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<section class="byline" aria-label="Angaben zum Bericht">
		<div class="wrap byline__inner">
			<div class="byline__item">
				<span class="byline__icon" aria-hidden="true"><?php echo esc_html( $afk_init ); ?></span>
				<span class="byline__text"><span class="byline__label">Bericht</span><span><strong><?php echo esc_html( $afk_verf ); ?></strong><?php echo $afk_einheit ? ', ' . esc_html( $afk_einheit ) : ''; ?></span></span>
			</div>
			<?php if ( $afk_bild ) : ?>
				<div class="byline__item">
					<span class="byline__icon" aria-hidden="true"><?php echo afk_ns_icon( 'camera', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="byline__text"><span class="byline__label">Fotos</span><strong><?php echo esc_html( $afk_bild ); ?></strong></span>
				</div>
			<?php endif; ?>
			<span class="byline__date">Veröffentlicht <?php echo esc_html( get_the_date( 'd.m.Y' ) ); ?></span>
		</div>
	</section>

	<div class="wrap post-layout">
		<article <?php post_class( 'post-layout__main' ); ?>>
			<div class="entry">
				<?php the_content(); ?>
			</div>
			<?php if ( $afk_bild ) : ?>
				<p class="bildquelle">Bildquelle: <?php echo esc_html( $afk_bild ); ?></p>
			<?php endif; ?>
			<?php
			wp_link_pages(
				array(
					'before' => '<nav class="pagination" aria-label="Seiten des Berichts"><div class="nav-links">',
					'after'  => '</div></nav>',
				)
			);
			?>
		</article>

		<aside class="post-layout__aside" aria-label="Zusatzinformationen">
			<?php if ( $afk_wehren || $afk_extern ) : ?>
				<div class="box">
					<?php if ( $afk_wehren ) : ?>
						<h2 class="box__title">Beteiligte Feuerwehren</h2>
						<p class="box__hint">Antippen für alle Berichte der jeweiligen Feuerwehr</p>
						<ul class="chips">
							<?php foreach ( $afk_wehren as $t ) : ?>
								<li><a class="chip chip--outline" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $afk_extern ) : ?>
						<<?php echo $afk_wehren ? 'p class="box__sub"' : 'h2 class="box__title"'; ?>>Weitere Kräfte</<?php echo $afk_wehren ? 'p' : 'h2'; ?>>
						<ul class="chips">
							<?php foreach ( $afk_extern as $name ) : ?>
								<li><span class="chip chip--muted"><?php echo esc_html( $name ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="share">
				<button class="btn btn--ghost" type="button" data-share data-title="<?php echo esc_attr( get_the_title() ); ?>">Teilen</button>
				<a class="btn btn--ghost" href="https://wa.me/?text=<?php echo rawurlencode( get_the_title() . ' ' . get_permalink() ); ?>" rel="noopener" target="_blank">WhatsApp</a>
				<?php $afk_tg = get_theme_mod( 'afk_telegram', '' ); ?>
				<?php if ( $afk_tg ) : ?>
					<a class="btn btn--ghost" href="<?php echo esc_url( $afk_tg ); ?>">Telegram-Kanal</a>
				<?php endif; ?>
			</div>
		</aside>
	</div>

	<?php
	// Weitere Berichte: von der berichtenden Wehr (Bildquelle/Funktion), sonst aus derselben Kategorie.
	$afk_rel_titel = 'Weitere Berichte';
	$afk_rel_link  = '';
	$afk_rel_args  = array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $afk_id ),
		'ignore_sticky_posts' => true,
	);
	if ( $afk_wehren ) {
		$afk_haupt                  = afk_ns_berichtende_wehr( $afk_wehren, $afk_bild . ' ' . $afk_einheit );
		$afk_rel_titel              = 'Mehr von der ' . $afk_haupt->name;
		$afk_rel_link               = get_term_link( $afk_haupt );
		$afk_rel_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'feuerwehr',
				'terms'    => $afk_haupt->term_id,
			),
		);
	} elseif ( $afk_crumb_cat ) {
		$afk_rel_args['cat'] = $afk_crumb_cat->term_id;
		$afk_rel_link        = get_category_link( $afk_crumb_cat );
	}
	$afk_rel = new WP_Query( $afk_rel_args );
	if ( $afk_rel->have_posts() ) :
		?>
		<section class="band section" aria-labelledby="afk-rel-h">
			<div class="wrap">
				<div class="section-head">
					<h2 class="section-title section-title--sm" id="afk-rel-h"><?php echo esc_html( $afk_rel_titel ); ?></h2>
					<?php if ( $afk_rel_link && ! is_wp_error( $afk_rel_link ) ) : ?>
						<a class="more-link" href="<?php echo esc_url( $afk_rel_link ); ?>">Alle anzeigen →</a>
					<?php endif; ?>
				</div>
				<div class="tiles tiles--3">
					<?php
					while ( $afk_rel->have_posts() ) {
						$afk_rel->the_post();
						afk_ns_kachel( get_the_ID() );
					}
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

<?php endwhile; ?>

<?php
get_footer();
