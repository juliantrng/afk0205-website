<?php
/**
 * Startseite.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

$afk_f       = afk_ns_filter_werte();
$afk_auf_id  = afk_ns_aufmacher_id();
$afk_wehren  = afk_ns_wehren();
$afk_einsatz = afk_ns_kategorien()['einsatz'][0];

/* ---------- Aufmacher ---------- */
if ( $afk_auf_id ) :
	$afk_kat  = afk_ns_kat_label( $afk_auf_id );
	$afk_lead = has_excerpt( $afk_auf_id ) ? get_the_excerpt( $afk_auf_id ) : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $afk_auf_id ) ), 30, ' …' );
	?>
	<section class="hero" aria-label="Aktueller Bericht">
		<?php
		if ( has_post_thumbnail( $afk_auf_id ) ) {
			echo get_the_post_thumbnail( $afk_auf_id, 'afk-hero', array( 'class' => 'hero__img', 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager' ) );
		}
		?>
		<div class="wrap hero__inner">
			<div class="hero__box">
				<p class="hero__meta">
					<?php if ( $afk_kat ) : ?><span class="hero__kat"><?php echo esc_html( $afk_kat ); ?></span><?php endif; ?>
					<span><?php echo esc_html( get_the_date( 'j. F Y', $afk_auf_id ) ); ?></span>
				</p>
				<h1 class="hero__title"><?php echo esc_html( get_the_title( $afk_auf_id ) ); ?></h1>
				<?php if ( $afk_lead ) : ?>
					<p class="hero__lead"><?php echo esc_html( $afk_lead ); ?></p>
				<?php endif; ?>
				<p class="hero__actions">
					<a class="btn btn--yellow" href="<?php echo esc_url( get_permalink( $afk_auf_id ) ); ?>">
						<?php echo afk_ns_ist_einsatz( $afk_auf_id ) ? 'Zum Einsatzbericht' : 'Zum Bericht'; ?>
						<?php echo afk_ns_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</p>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
/* ---------- Rote Leiste: KLFV-Alarmierungen, sonst letzte Einsatzberichte ---------- */
$afk_alarme = afk_ns_alarme( 3 );
if ( $afk_alarme ) :
	$afk_al_url = afk_ns_seite_mit_vorlage( 'page-alarmierungen.php' );
	$afk_live   = function_exists( 'afk_alarm_laufend' ) && afk_alarm_laufend( $afk_alarme[0] );
	?>
	<section class="ticker" aria-labelledby="afk-ticker-h">
		<div class="wrap ticker__inner">
			<h2 class="ticker__title" id="afk-ticker-h"><?php if ( $afk_live ) : ?><span class="ticker__live" aria-hidden="true"></span><?php endif; ?>Alarmierungen</h2>
			<ul class="ticker__list">
				<?php foreach ( $afk_alarme as $a ) : ?>
					<li><span class="ticker__item"><span class="ticker__date"><?php echo esc_html( wp_date( 'd.m. · H:i', (int) $a['zeit'] ) ); ?></span><span class="ticker__text"><?php echo esc_html( $a['ereignis'] ); ?></span><?php if ( $a['eigene'] ) : ?><span class="ticker__units"><?php echo esc_html( implode( ', ', array_map( 'afk_ns_kurzname', $a['eigene'] ) ) ); ?></span><?php endif; ?></span></li>
				<?php endforeach; ?>
			</ul>
			<div class="ticker__end">
				<?php if ( $afk_al_url ) : ?><a class="ticker__all" href="<?php echo esc_url( $afk_al_url ); ?>">Alle Alarmierungen</a><?php endif; ?>
				<span class="ticker__quelle">Quelle: <a href="https://alarmierungen.feuerwehr-ktn.at/" rel="noopener" target="_blank">KLFV</a></span>
			</div>
		</div>
	</section>
	<?php
else :
	$afk_ticker = get_posts(
		array(
			'numberposts'   => 3,
			'category_name' => $afk_einsatz,
		)
	);
	if ( $afk_ticker ) :
		?>
		<section class="ticker" aria-labelledby="afk-ticker-h">
			<div class="wrap ticker__inner">
				<h2 class="ticker__title" id="afk-ticker-h">Letzte Einsätze</h2>
				<ul class="ticker__list">
					<?php foreach ( $afk_ticker as $p ) : ?>
						<li><a class="ticker__item" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><span class="ticker__date"><?php echo esc_html( get_the_date( 'd.m.', $p ) ); ?></span><span class="ticker__text ticker__text--2"><?php echo esc_html( get_the_title( $p ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
				<a class="ticker__all" href="<?php echo esc_url( afk_ns_kat_url( 'einsatz' ) ); ?>">Alle Einsätze</a>
			</div>
		</section>
		<?php
	endif;
endif;
?>

<?php
/* ---------- Kennzahlen ---------- */
$afk_gemeinden = array_unique( wp_list_pluck( $afk_wehren, 'gemeinde' ) );
$afk_mitglieder = afk_ns_mitglieder();
$afk_stats      = array();
if ( $afk_wehren ) {
	$afk_stats[] = array( count( $afk_wehren ), 'Feuerwehren' );
	$afk_stats[] = array( count( $afk_gemeinden ), 'Gemeinden im Abschnitt' );
}
if ( '' !== $afk_mitglieder ) {
	$afk_stats[] = array( $afk_mitglieder, 'Mitglieder – freiwillig im Einsatz' );
} else {
	// Solange nicht alle Wehren ihre Mitgliederzahl eingetragen haben: Berichte des laufenden Jahres.
	$afk_jahr   = (int) wp_date( 'Y' );
	$afk_anzahl = ( new WP_Query( array( 'post_type' => 'post', 'year' => $afk_jahr, 'posts_per_page' => 1, 'fields' => 'ids' ) ) )->found_posts;
	if ( $afk_anzahl ) {
		$afk_stats[] = array( $afk_anzahl, 'Berichte im Jahr ' . $afk_jahr );
	}
}
if ( $afk_stats ) :
	?>
	<section class="wrap section section--stats" aria-label="Der Abschnitt in Zahlen">
		<div class="stats">
			<?php foreach ( $afk_stats as $s ) : ?>
				<div class="stat"><div class="stat__num"><?php echo esc_html( $s[0] ); ?></div><div class="stat__label"><?php echo esc_html( $s[1] ); ?></div></div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
/* ---------- Berichte mit Filter ---------- */
$afk_args = array_merge(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 9,
		'ignore_sticky_posts' => true,
	),
	afk_ns_filter_query_args( $afk_f )
);
if ( ! $afk_f['aktiv'] && $afk_auf_id ) {
	$afk_args['post__not_in'] = array( $afk_auf_id );
}
$afk_q = new WP_Query( $afk_args );
?>
<section class="section<?php echo $afk_stats ? ' section--tight-top' : ''; ?>" id="aktuelles" aria-labelledby="afk-news-h">
	<div class="wrap">
		<div class="section-head">
			<h2 class="section-title" id="afk-news-h">Aus dem Abschnitt</h2>
			<span class="result-count" role="status">
				<?php
				if ( $afk_f['aktiv'] ) {
					/* translators: %d Anzahl */
					echo esc_html( sprintf( _n( '%d Bericht gefunden', '%d Berichte gefunden', $afk_q->found_posts, 'afk-nachtschicht' ), $afk_q->found_posts ) );
				}
				?>
			</span>
		</div>

		<?php afk_ns_filterleiste( array( 'action' => home_url( '/' ), 'anchor' => 'aktuelles' ) ); ?>

		<?php if ( $afk_q->have_posts() ) : ?>
			<div class="bento">
				<?php
				// Die große Kachel lebt vom Foto: hat der neueste Bericht keines, rückt der neueste mit Foto nach vorne.
				$afk_ids = wp_list_pluck( $afk_q->posts, 'ID' );
				if ( $afk_ids && ! has_post_thumbnail( $afk_ids[0] ) ) {
					foreach ( $afk_ids as $afk_i => $afk_id ) {
						if ( has_post_thumbnail( $afk_id ) ) {
							array_splice( $afk_ids, $afk_i, 1 );
							array_unshift( $afk_ids, $afk_id );
							break;
						}
					}
				}
				foreach ( $afk_ids as $afk_i => $afk_id ) {
					afk_ns_kachel( $afk_id, 0 === $afk_i && has_post_thumbnail( $afk_id ) );
				}
				?>
			</div>
		<?php else : ?>
			<p class="empty">Für diese Auswahl gibt es keine Berichte. <a href="<?php echo esc_url( home_url( '/#aktuelles' ) ); ?>">Filter zurücksetzen</a></p>
		<?php endif; ?>

		<p class="more-row">
			<?php
			$afk_mehr = afk_ns_alle_berichte_url();
			if ( $afk_f['aktiv'] ) {
				$afk_mehr = add_query_arg( array_filter( array( 'kat' => $afk_f['kat'], 'ff' => $afk_f['ff'], 'jahr' => $afk_f['jahr'] ) ), $afk_mehr );
			}
			?>
			<a class="more-link" href="<?php echo esc_url( $afk_mehr ); ?>"><?php echo $afk_f['aktiv'] ? 'Alle Treffer anzeigen →' : 'Alle Berichte &amp; Archiv →'; ?></a>
		</p>
	</div>
</section>

<?php /* ---------- Feuerwehren ---------- */ ?>
<?php if ( $afk_wehren ) : ?>
	<section class="band section" id="feuerwehren" aria-labelledby="afk-ff-h">
		<div class="wrap">
			<div class="section-head">
				<div>
					<h2 class="section-title" id="afk-ff-h"><?php echo esc_html( count( $afk_wehren ) ); ?> Feuerwehren</h2>
					<p class="section-sub"><?php echo esc_html( implode( ' · ', $afk_gemeinden ) ); ?></p>
				</div>
				<?php if ( afk_ns_seite_mit_vorlage( 'page-feuerwehren.php' ) ) : ?>
					<a class="more-link" href="<?php echo esc_url( afk_ns_feuerwehren_url() ); ?>">Alle Profile →</a>
				<?php endif; ?>
			</div>
			<ul class="ff-grid">
				<?php foreach ( $afk_wehren as $w ) : ?>
					<li>
						<a class="ff-card" href="<?php echo esc_url( get_term_link( $w['term'] ) ); ?>">
							<span>
								<span class="ff-card__gem"><?php echo esc_html( $w['gemeinde'] ); ?></span>
								<span class="ff-card__name"><?php echo esc_html( $w['term']->name ); ?></span>
							</span>
							<?php echo afk_ns_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
<?php endif; ?>

<?php
/* ---------- Termine & Sicherheitstipps ---------- */
$afk_termine = afk_ns_hat_termine() ? afk_termine_naechste( 4 ) : array();
$afk_tipps   = array();
if ( get_theme_mod( 'afk_zeige_tipps', true ) && post_type_exists( 'afk_tipp' ) ) {
	$afk_alle = get_posts(
		array(
			'post_type'   => 'afk_tipp',
			'numberposts' => 20,
			'orderby'     => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	$afk_akt  = array();
	$afk_rest = array();
	foreach ( $afk_alle as $t ) {
		if ( function_exists( 'afk_tipp_ist_aktuell' ) && afk_tipp_ist_aktuell( $t->ID ) ) {
			$afk_akt[] = $t;
		} else {
			$afk_rest[] = $t;
		}
	}
	$afk_tipps = array_slice( array_merge( $afk_akt, $afk_rest ), 0, 4 );
}
if ( $afk_termine || $afk_tipps ) :
	?>
	<section class="section" id="termine">
		<div class="wrap split<?php echo ( $afk_termine && $afk_tipps ) ? '' : ' split--single'; ?>">
			<?php if ( $afk_termine ) : ?>
				<div>
					<div class="section-head">
						<h2 class="section-title section-title--sm">Termine</h2>
						<?php if ( afk_ns_termine_url() ) : ?>
							<a class="more-link" href="<?php echo esc_url( afk_ns_termine_url() ); ?>">Alle Termine</a>
						<?php endif; ?>
					</div>
					<ul class="list">
						<?php foreach ( $afk_termine as $afk_tid ) { afk_ns_termin_zeile( $afk_tid ); } ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $afk_tipps ) : ?>
				<div>
					<div class="section-head">
						<h2 class="section-title section-title--sm">Sicher durchs Jahr</h2>
						<a class="more-link" href="<?php echo esc_url( get_post_type_archive_link( 'afk_tipp' ) ); ?>">Alle Tipps</a>
					</div>
					<div class="tipps">
						<?php foreach ( $afk_tipps as $t ) : ?>
							<?php $afk_ist = function_exists( 'afk_tipp_ist_aktuell' ) && afk_tipp_ist_aktuell( $t->ID ); ?>
							<a class="tipp<?php echo $afk_ist ? ' tipp--aktuell' : ''; ?>" href="<?php echo esc_url( get_permalink( $t ) ); ?>">
								<?php if ( $afk_ist ) : ?><span class="tipp__badge">Jetzt aktuell</span><?php endif; ?>
								<span class="tipp__title"><?php echo esc_html( get_the_title( $t ) ); ?></span>
								<span class="tipp__text"><?php echo esc_html( has_excerpt( $t ) ? get_the_excerpt( $t ) : wp_trim_words( wp_strip_all_tags( $t->post_content ), 16, ' …' ) ); ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php /* ---------- Mitmachen ---------- */ ?>
<section class="cta-band" id="mitmachen">
	<div class="wrap cta-band__inner">
		<div class="cta-band__copy">
			<h2 class="cta-band__title">Werde Teil davon.</h2>
			<p class="cta-band__text">Jede unserer <?php echo $afk_wehren ? esc_html( count( $afk_wehren ) ) . ' ' : ''; ?>Feuerwehren sucht Verstärkung – in der Feuerwehrjugend und im aktiven Dienst.</p>
		</div>
		<?php $afk_mm = get_theme_mod( 'afk_mitmachen_url', '' ); ?>
		<a class="btn btn--white" href="<?php echo esc_url( $afk_mm ? $afk_mm : afk_ns_feuerwehren_url() ); ?>"><?php echo $afk_mm ? 'So wirst du Mitglied' : 'Feuerwehr in meiner Nähe finden'; ?></a>
	</div>
</section>

<?php /* ---------- Notruf ---------- */ ?>
<section class="section section--tight-top section--tight-bottom" aria-labelledby="afk-notruf-h">
	<div class="wrap">
		<h2 class="eyebrow" id="afk-notruf-h" style="margin-bottom:20px">Im Notfall</h2>
		<div class="notruf-grid">
			<a class="notruf-card notruf-card--fw" href="tel:122"><span class="notruf-card__nr">122</span><span class="notruf-card__label">Feuerwehr</span></a>
			<a class="notruf-card" href="tel:144"><span class="notruf-card__nr">144</span><span class="notruf-card__label">Rettung</span></a>
			<a class="notruf-card" href="tel:133"><span class="notruf-card__nr">133</span><span class="notruf-card__label">Polizei</span></a>
			<a class="notruf-card" href="tel:112"><span class="notruf-card__nr">112</span><span class="notruf-card__label">Euronotruf</span></a>
		</div>
	</div>
</section>

<?php
get_footer();
