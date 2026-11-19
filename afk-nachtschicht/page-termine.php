<?php
/**
 * Template Name: Termine
 *
 * Übersicht der Termine aus AFK Core: kommende (nach Monaten gruppiert) oder vergangene,
 * filterbar nach Feuerwehr, mit Kalender-Abo.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Filter.
$afk_ff       = isset( $_GET['ff'] ) ? sanitize_title( wp_unslash( $_GET['ff'] ) ) : '';
$afk_vergang  = isset( $_GET['zeitraum'] ) && 'vergangen' === $_GET['zeitraum'];
$afk_seite    = isset( $_GET['seite'] ) ? max( 1, absint( $_GET['seite'] ) ) : 1;
// phpcs:enable
$afk_term     = $afk_ff ? get_term_by( 'slug', $afk_ff, 'feuerwehr' ) : null;
$afk_q        = afk_ns_hat_termine() ? afk_termine_query(
	array(
		'anzahl'    => $afk_vergang ? 20 : 60,
		'zeitraum'  => $afk_vergang ? 'vergangen' : 'kommend',
		'feuerwehr' => $afk_term ? $afk_term->slug : '',
		'seite'     => $afk_seite,
	)
) : null;
$afk_basis    = get_permalink();
$afk_link     = function ( $args ) use ( $afk_basis, $afk_ff ) {
	return add_query_arg( array_filter( array_merge( array( 'ff' => $afk_ff ), $args ) ), $afk_basis );
};

while ( have_posts() ) :
	the_post();
	afk_ns_seitenkopf(
		get_the_title(),
		'Abschnitt Millstatt – Radenthein',
		has_excerpt() ? get_the_excerpt() : 'Feste, Bewerbe und öffentliche Übungen der Feuerwehren im Abschnitt – zum Vorbeischauen und Mitfeiern.',
		true
	);
	?>
	<div class="wrap wrap--narrow page-body">
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="entry termine-intro"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php if ( ! $afk_q ) : ?>
			<p class="empty">Für die Termine wird das Plugin AFK Core ab Version 0.7 benötigt.</p>
		<?php else : ?>
			<form class="filters" method="get" action="<?php echo esc_url( $afk_basis ); ?>" aria-label="Termine filtern">
				<div class="filters__group">
					<span class="filters__label" id="afk-te-zr">Zeitraum</span>
					<div class="chips" role="group" aria-labelledby="afk-te-zr">
						<a class="chip<?php echo $afk_vergang ? '' : ' is-active'; ?>" href="<?php echo esc_url( $afk_link( array() ) ); ?>"<?php echo $afk_vergang ? '' : ' aria-current="page"'; ?>>Kommende</a>
						<a class="chip<?php echo $afk_vergang ? ' is-active' : ''; ?>" href="<?php echo esc_url( $afk_link( array( 'zeitraum' => 'vergangen' ) ) ); ?>"<?php echo $afk_vergang ? ' aria-current="page"' : ''; ?>>Vergangene</a>
					</div>
				</div>
				<div class="filters__selects">
					<div class="filters__group filters__group--select">
						<label class="filters__label" for="afk-te-ff">Feuerwehr</label>
						<select class="select" id="afk-te-ff" name="ff" data-autosubmit>
							<option value="">Alle Feuerwehren</option>
							<?php foreach ( afk_ns_wehren() as $w ) : ?>
								<option value="<?php echo esc_attr( $w['term']->slug ); ?>" <?php selected( $afk_ff, $w['term']->slug ); ?>><?php echo esc_html( $w['term']->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<?php if ( $afk_vergang ) : ?><input type="hidden" name="zeitraum" value="vergangen"><?php endif; ?>
				<div class="filters__end">
					<button class="btn btn--ghost btn--sm filters__apply" type="submit">Anzeigen</button>
					<span class="result-count" role="status"><?php echo esc_html( sprintf( _n( '%d Termin', '%d Termine', (int) $afk_q->found_posts, 'afk-nachtschicht' ), (int) $afk_q->found_posts ) ); ?></span>
				</div>
			</form>

			<?php if ( $afk_q->have_posts() ) : ?>
				<?php
				$afk_monat = '';
				$afk_offen = false;
				foreach ( $afk_q->posts as $afk_p ) {
					$afk_d = afk_termin_daten( $afk_p->ID );
					$afk_m = wp_date( 'F Y', $afk_d['start'] );
					if ( $afk_m !== $afk_monat ) {
						if ( $afk_offen ) {
							echo '</ul>';
						}
						echo '<h2 class="termine-monat">' . esc_html( $afk_m ) . '</h2><ul class="list termine-liste">';
						$afk_monat = $afk_m;
						$afk_offen = true;
					}
					afk_ns_termin_zeile( $afk_p->ID, true );
				}
				if ( $afk_offen ) {
					echo '</ul>';
				}
				?>
				<?php if ( $afk_q->max_num_pages > 1 ) : ?>
					<nav class="pagination" aria-label="Weitere Termine">
						<div class="nav-links">
							<?php for ( $i = 1; $i <= $afk_q->max_num_pages; $i++ ) : ?>
								<?php if ( $i === $afk_seite ) : ?>
									<span class="page-numbers current" aria-current="page"><?php echo (int) $i; ?></span>
								<?php else : ?>
									<a class="page-numbers" href="<?php echo esc_url( $afk_link( array( 'zeitraum' => $afk_vergang ? 'vergangen' : '', 'seite' => $i > 1 ? $i : '' ) ) ); ?>"><?php echo (int) $i; ?></a>
								<?php endif; ?>
							<?php endfor; ?>
						</div>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<p class="empty">
					<?php
					if ( $afk_vergang ) {
						echo 'Keine vergangenen Termine' . ( $afk_term ? ' der ' . esc_html( $afk_term->name ) : '' ) . '.';
					} else {
						echo 'Derzeit sind keine Termine' . ( $afk_term ? ' der ' . esc_html( $afk_term->name ) : '' ) . ' eingetragen.';
					}
					?>
				</p>
			<?php endif; ?>

			<?php $afk_abo = afk_termin_ics_url( 'alle' ); ?>
			<aside class="termine-abo">
				<div>
					<h2 class="termine-abo__title">Termine im eigenen Kalender</h2>
					<p class="termine-abo__text">Abonniere alle Termine des Abschnitts – neue und geänderte Termine erscheinen dann automatisch in deinem Handy- oder Outlook-Kalender.</p>
				</div>
				<div class="termine-abo__aktionen">
					<a class="btn btn--yellow btn--sm" href="<?php echo esc_url( preg_replace( '#^https?://#', 'webcal://', $afk_abo ), array( 'webcal' ) ); ?>">Kalender abonnieren</a>
					<button class="btn btn--ghost btn--sm" type="button" data-copy="<?php echo esc_attr( $afk_abo ); ?>">Link kopieren</button>
				</div>
			</aside>
		<?php endif; ?>
	</div>
<?php endwhile; ?>

<?php
get_footer();
