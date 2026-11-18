<?php
/**
 * Template Name: Alarmierungen
 *
 * Aktuelle Alarmierungen der Feuerwehren im Abschnitt – Daten vom Kärntner
 * Landesfeuerwehrverband, abgerufen vom Plugin AFK Core (Einstellungen → Alarmierungen).
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reiner Anzeige-Filter.
$afk_ff   = isset( $_GET['ff'] ) ? sanitize_title( wp_unslash( $_GET['ff'] ) ) : '';
$afk_term = $afk_ff ? get_term_by( 'slug', $afk_ff, 'feuerwehr' ) : null;
$afk_tid  = $afk_term ? (int) $afk_term->term_id : 0;
$afk_alle = afk_ns_alarme( 200, $afk_tid );
$afk_cfg  = function_exists( 'afk_alarm_einstellungen' ) ? afk_alarm_einstellungen() : array( 'aktiv' => false, 'tage' => 7 );

while ( have_posts() ) :
	the_post();
	afk_ns_seitenkopf(
		get_the_title(),
		'Live vom Landesfeuerwehrverband',
		has_excerpt() ? get_the_excerpt() : 'Alarmierungen der Feuerwehren im Abschnitt aus den letzten ' . (int) $afk_cfg['tage'] . ' Tagen. Ausführliche Berichte folgen, sobald der Einsatz abgeschlossen ist.',
		true
	);
	?>
	<div class="wrap wrap--narrow page-body">
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="entry" style="margin-bottom:32px"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php if ( empty( $afk_cfg['aktiv'] ) ) : ?>
			<p class="empty">Die Anzeige der Alarmierungen ist noch nicht eingerichtet<?php echo current_user_can( 'manage_options' ) ? ' – siehe Einstellungen → Alarmierungen' : ''; ?>.</p>
		<?php else : ?>
			<form class="filters" method="get" action="<?php echo esc_url( get_permalink() ); ?>" aria-label="Alarmierungen filtern">
				<div class="filters__selects">
					<div class="filters__group filters__group--select">
						<label class="filters__label" for="afk-al-ff">Feuerwehr</label>
						<select class="select" id="afk-al-ff" name="ff" data-autosubmit>
							<option value="">Alle Feuerwehren</option>
							<?php foreach ( afk_ns_wehren() as $w ) : ?>
								<option value="<?php echo esc_attr( $w['term']->slug ); ?>" <?php selected( $afk_ff, $w['term']->slug ); ?>><?php echo esc_html( $w['term']->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="filters__end">
					<button class="btn btn--ghost btn--sm filters__apply" type="submit">Anzeigen</button>
					<span class="result-count" role="status"><?php echo esc_html( sprintf( _n( '%d Alarmierung', '%d Alarmierungen', count( $afk_alle ), 'afk-nachtschicht' ), count( $afk_alle ) ) ); ?></span>
				</div>
			</form>

			<?php if ( $afk_alle ) : ?>
				<ul class="list">
					<?php foreach ( $afk_alle as $a ) { afk_ns_alarm_zeile( $a, true ); } ?>
				</ul>
			<?php else : ?>
				<p class="empty">Keine Alarmierungen in den letzten <?php echo (int) $afk_cfg['tage']; ?> Tagen<?php echo $afk_term ? ' für die ' . esc_html( $afk_term->name ) : ''; ?>. Gut so.</p>
			<?php endif; ?>
			<?php afk_ns_alarm_quelle(); ?>
		<?php endif; ?>
	</div>
<?php endwhile; ?>

<?php
get_footer();
