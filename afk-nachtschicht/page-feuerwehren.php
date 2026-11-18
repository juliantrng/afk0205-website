<?php
/**
 * Template Name: Feuerwehren-Übersicht
 *
 * Alle Wehren des Abschnitts nach Gemeinde, mit Titelbild und Kurzdaten aus dem Profil.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

$afk_wehren = afk_ns_wehren();
$afk_gruppe = array();
foreach ( $afk_wehren as $w ) {
	$afk_gruppe[ $w['gemeinde'] ][] = $w['term'];
}

while ( have_posts() ) :
	the_post();
	afk_ns_seitenkopf( get_the_title(), 'Abschnitt Millstatt – Radenthein', has_excerpt() ? get_the_excerpt() : count( $afk_wehren ) . ' Feuerwehren in ' . count( $afk_gruppe ) . ' Gemeinden – jede mit eigener Seite, Profil und allen Berichten.' );
	?>
	<div class="wrap page-body">
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="entry" style="max-width:var(--wrap-narrow);margin-bottom:48px"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php foreach ( $afk_gruppe as $afk_gem => $afk_terms ) : ?>
			<section class="ff-gemeinde" aria-label="<?php echo esc_attr( 'Gemeinde ' . $afk_gem ); ?>">
				<h2 class="ff-gemeinde__title">Gemeinde <?php echo esc_html( $afk_gem ); ?></h2>
				<div class="ff-profile-grid">
					<?php foreach ( $afk_terms as $t ) : ?>
						<?php
						$afk_ctx   = 'term_' . $t->term_id;
						$afk_bild  = (int) afk_ns_field( 'afk_titelbild', $afk_ctx );
						$afk_mitgl = trim( (string) afk_ns_field( 'afk_mitglieder', $afk_ctx ) );
						$afk_gr    = trim( (string) afk_ns_field( 'afk_gruendung', $afk_ctx ) );
						$afk_facts = array();
						if ( $afk_mitgl ) {
							$afk_facts[] = $afk_mitgl . ' Mitglieder';
						}
						if ( $afk_gr ) {
							$afk_facts[] = 'gegründet ' . $afk_gr;
						}
						$afk_facts[] = sprintf( _n( '%d Bericht', '%d Berichte', (int) $t->count, 'afk-nachtschicht' ), (int) $t->count );
						?>
						<a class="ff-profile" href="<?php echo esc_url( get_term_link( $t ) ); ?>">
							<?php if ( $afk_bild ) : ?>
								<?php echo wp_get_attachment_image( $afk_bild, 'afk-tile', false, array( 'class' => 'ff-profile__img', 'alt' => '', 'loading' => 'lazy' ) ); ?>
							<?php else : ?>
								<span class="ff-profile__noimg" aria-hidden="true"><?php echo afk_ns_logo_img(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							<?php endif; ?>
							<span class="ff-profile__body">
								<span class="ff-profile__name"><?php echo esc_html( $t->name ); ?></span>
								<span class="ff-profile__facts"><?php echo esc_html( implode( ' · ', $afk_facts ) ); ?></span>
								<span class="ff-profile__more">Zur Feuerwehr →</span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
<?php endwhile; ?>

<?php
get_footer();
