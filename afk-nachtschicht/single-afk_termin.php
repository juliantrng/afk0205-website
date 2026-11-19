<?php
/**
 * Einzelner Termin (AFK Core, Beitragstyp afk_termin).
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$afk_id     = get_the_ID();
	$afk_d      = afk_ns_hat_termine() ? afk_termin_daten( $afk_id ) : array( 'start' => 0, 'ende' => 0, 'bis' => 0, 'ort' => '', 'art_text' => '', 'link' => '', 'ganztaegig' => false, 'mehrtaegig' => false );
	$afk_vorbei = $afk_d['bis'] && $afk_d['bis'] < time();
	$afk_wehren = get_the_terms( $afk_id, 'feuerwehr' );
	$afk_wehren = ( $afk_wehren && ! is_wp_error( $afk_wehren ) ) ? $afk_wehren : array();
	$afk_liste  = afk_ns_termine_url();
	?>
	<header class="page-head termin-kopf">
		<div class="wrap wrap--narrow">
			<nav class="crumbs" aria-label="Brotkrumen">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a><span aria-hidden="true">/</span>
				<?php if ( $afk_liste ) : ?><a href="<?php echo esc_url( $afk_liste ); ?>">Termine</a><span aria-hidden="true">/</span><?php endif; ?>
				<span aria-current="page"><?php echo esc_html( wp_trim_words( get_the_title(), 5, ' …' ) ); ?></span>
			</nav>
			<p class="eyebrow"><?php echo esc_html( $afk_d['art_text'] ? $afk_d['art_text'] : 'Termin' ); ?><?php echo $afk_vorbei ? ' · vorbei' : ''; ?></p>
			<h1 class="page-head__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="page-head__desc"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</div>
	</header>

	<div class="wrap wrap--narrow page-body">
		<dl class="termin-fakten">
			<?php if ( $afk_d['start'] ) : ?>
				<div class="termin-fakten__zeile">
					<dt>Wann</dt>
					<dd><?php echo esc_html( afk_termin_zeit_text( $afk_id ) ); ?></dd>
				</div>
			<?php endif; ?>
			<?php if ( $afk_d['ort'] ) : ?>
				<div class="termin-fakten__zeile">
					<dt>Wo</dt>
					<dd><?php echo esc_html( $afk_d['ort'] ); ?> <a class="termin-fakten__karte" href="<?php echo esc_url( 'https://www.openstreetmap.org/search?query=' . rawurlencode( $afk_d['ort'] ) ); ?>" rel="noopener" target="_blank">Karte</a></dd>
				</div>
			<?php endif; ?>
			<?php if ( $afk_wehren ) : ?>
				<div class="termin-fakten__zeile">
					<dt><?php echo count( $afk_wehren ) > 1 ? 'Feuerwehren' : 'Feuerwehr'; ?></dt>
					<dd class="termin-fakten__ff">
						<?php foreach ( $afk_wehren as $w ) : ?>
							<a class="chip chip--outline" href="<?php echo esc_url( get_term_link( $w ) ); ?>"><?php echo esc_html( $w->name ); ?></a>
						<?php endforeach; ?>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<div class="termin-aktionen">
			<?php if ( ! $afk_vorbei && afk_ns_hat_termine() ) : ?>
				<a class="btn btn--yellow" href="<?php echo esc_url( afk_termin_ics_url( $afk_id ) ); ?>" rel="nofollow">In den Kalender</a>
			<?php endif; ?>
			<?php if ( $afk_d['link'] ) : ?>
				<a class="btn btn--ghost" href="<?php echo esc_url( $afk_d['link'] ); ?>" rel="noopener" target="_blank">Weitere Infos</a>
			<?php endif; ?>
			<button class="btn btn--ghost" type="button" data-share data-title="<?php echo esc_attr( get_the_title() ); ?>">Teilen</button>
		</div>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="termin-bild"><?php the_post_thumbnail( 'large', array( 'alt' => '' ) ); ?></figure>
		<?php endif; ?>

		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="entry"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php
		$afk_weitere = afk_ns_hat_termine() ? array_diff( afk_termine_naechste( 4 ), array( $afk_id ) ) : array();
		$afk_weitere = array_slice( $afk_weitere, 0, 3 );
		if ( $afk_weitere ) :
			?>
			<section class="termin-weitere" aria-labelledby="afk-te-weitere">
				<div class="section-head">
					<h2 class="section-title section-title--sm" id="afk-te-weitere">Weitere Termine</h2>
					<?php if ( $afk_liste ) : ?><a class="more-link" href="<?php echo esc_url( $afk_liste ); ?>">Alle Termine</a><?php endif; ?>
				</div>
				<ul class="list">
					<?php foreach ( $afk_weitere as $afk_w ) { afk_ns_termin_zeile( $afk_w ); } ?>
				</ul>
			</section>
		<?php endif; ?>
	</div>
<?php endwhile; ?>

<?php
get_footer();
