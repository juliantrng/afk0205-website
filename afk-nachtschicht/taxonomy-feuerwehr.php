<?php
/**
 * Feuerwehr-Seite (Archiv der Taxonomie „Feuerwehr“): Profil + alle Berichte.
 * Für Gemeinde-Begriffe: Übersicht der Wehren der Gemeinde + deren Berichte.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
get_header();

$afk_term     = get_queried_object();
$afk_ist_wehr = $afk_term instanceof WP_Term && (int) $afk_term->parent > 0;
$afk_ctx      = 'term_' . $afk_term->term_id;
$afk_f        = afk_ns_filter_werte();
$afk_wehren   = afk_ns_wehren();
$afk_base     = get_term_link( $afk_term );

// Profilfelder (nur Wehren).
$afk_bild_id  = $afk_ist_wehr ? (int) afk_ns_field( 'afk_titelbild', $afk_ctx ) : 0;
$afk_kdt      = $afk_ist_wehr ? trim( (string) afk_ns_field( 'afk_kommandant', $afk_ctx ) ) : '';
$afk_stv      = $afk_ist_wehr ? trim( (string) afk_ns_field( 'afk_stellvertreter', $afk_ctx ) ) : '';
$afk_mitgl    = $afk_ist_wehr ? trim( (string) afk_ns_field( 'afk_mitglieder', $afk_ctx ) ) : '';
$afk_fzg      = $afk_ist_wehr ? afk_ns_lines( afk_ns_field( 'afk_fahrzeuge', $afk_ctx ) ) : array();
$afk_adresse  = $afk_ist_wehr ? trim( wp_strip_all_tags( (string) afk_ns_field( 'afk_adresse', $afk_ctx ) ) ) : '';
$afk_web      = $afk_ist_wehr ? (string) afk_ns_field( 'afk_website', $afk_ctx ) : '';
$afk_fb       = $afk_ist_wehr ? (string) afk_ns_field( 'afk_facebook', $afk_ctx ) : '';
$afk_ig       = $afk_ist_wehr ? (string) afk_ns_field( 'afk_instagram', $afk_ctx ) : '';
$afk_mail     = $afk_ist_wehr ? (string) afk_ns_field( 'afk_mitmachen_kontakt', $afk_ctx ) : '';
$afk_gruend  = $afk_ist_wehr ? trim( (string) afk_ns_field( 'afk_gruendung', $afk_ctx ) ) : '';
$afk_gemeinde = $afk_ist_wehr ? get_term( $afk_term->parent, 'feuerwehr' ) : null;
?>

<nav class="ff-switch" aria-label="Feuerwehr wechseln">
	<div class="wrap ff-switch__inner">
		<span class="eyebrow">Feuerwehr</span>
		<?php foreach ( $afk_wehren as $w ) : ?>
			<?php $afk_on = (int) $w['term']->term_id === (int) $afk_term->term_id; ?>
			<a class="chip<?php echo $afk_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_term_link( $w['term'] ) ); ?>"<?php echo $afk_on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( afk_ns_kurzname( $w['term']->name ) ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>

<section class="hero hero--ff<?php echo $afk_bild_id ? '' : ' hero--noimg'; ?>">
	<?php
	if ( $afk_bild_id ) {
		echo wp_get_attachment_image( $afk_bild_id, 'afk-hero', false, array( 'class' => 'hero__img', 'alt' => 'Foto der ' . $afk_term->name, 'fetchpriority' => 'high' ) );
	}
	?>
	<div class="wrap hero__inner">
		<div class="hero__box">
			<p class="hero__meta"><span class="hero__kat">
				<?php
				if ( $afk_ist_wehr ) {
					echo esc_html( $afk_gemeinde && ! is_wp_error( $afk_gemeinde ) ? $afk_gemeinde->name : '' );
				} else {
					echo 'Gemeinde';
				}
				?>
			</span></p>
			<h1 class="hero__title"><?php echo esc_html( $afk_ist_wehr ? $afk_term->name : preg_replace( '/^Gemeinde\s+/u', '', $afk_term->name ) ); ?></h1>
			<?php if ( $afk_term->description ) : ?>
				<p class="hero__lead"><?php echo esc_html( wp_strip_all_tags( $afk_term->description ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ( $afk_ist_wehr ) : ?>
	<section class="ff-facts" aria-label="Daten der Feuerwehr">
		<div class="wrap ff-facts__inner">
			<?php if ( $afk_kdt || $afk_stv || $afk_mitgl || $afk_gruend || $afk_fzg || $afk_adresse ) : ?>
				<dl>
					<?php if ( $afk_kdt ) : ?><div><dt>Kommandant</dt><dd><?php echo esc_html( $afk_kdt ); ?></dd></div><?php endif; ?>
					<?php if ( $afk_stv ) : ?><div><dt>Stellvertreter</dt><dd><?php echo esc_html( $afk_stv ); ?></dd></div><?php endif; ?>
					<?php if ( $afk_mitgl ) : ?><div><dt>Mitglieder</dt><dd class="is-num"><?php echo esc_html( $afk_mitgl ); ?></dd></div><?php endif; ?>
					<?php if ( $afk_gruend ) : ?><div><dt>Gegründet</dt><dd class="is-num"><?php echo esc_html( $afk_gruend ); ?></dd></div><?php endif; ?>
					<?php if ( $afk_fzg ) : ?>
						<div><dt>Fahrzeuge</dt><dd><ul><?php foreach ( $afk_fzg as $fz ) : ?><li><?php echo esc_html( $fz ); ?></li><?php endforeach; ?></ul></dd></div>
					<?php endif; ?>
					<?php if ( $afk_adresse ) : ?><div><dt>Rüsthaus</dt><dd><?php echo esc_html( $afk_adresse ); ?></dd></div><?php endif; ?>
				</dl>
			<?php else : ?>
				<p class="ff-facts__empty">Profil folgt – Kommandant, Mitglieder und Fahrzeuge werden hier ergänzt.</p>
			<?php endif; ?>
			<div class="ff-facts__actions">
				<?php if ( $afk_web ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $afk_web ); ?>" rel="noopener" target="_blank">Website ↗</a><?php endif; ?>
				<?php if ( $afk_fb ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $afk_fb ); ?>" rel="noopener" target="_blank">Facebook ↗</a><?php endif; ?>
				<?php if ( $afk_ig ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( $afk_ig ); ?>" rel="noopener" target="_blank">Instagram ↗</a><?php endif; ?>
				<?php if ( $afk_mail && is_email( $afk_mail ) ) : ?><a class="btn btn--red" href="mailto:<?php echo esc_attr( antispambot( $afk_mail ) ); ?>">Mitglied werden</a><?php endif; ?>
			</div>
		</div>
	</section>
<?php else : ?>
	<?php
	$afk_kinder = get_terms(
		array(
			'taxonomy'   => 'feuerwehr',
			'parent'     => $afk_term->term_id,
			'hide_empty' => false,
		)
	);
	if ( $afk_kinder && ! is_wp_error( $afk_kinder ) ) :
		?>
		<section class="band section section--tight-top section--tight-bottom" aria-label="Feuerwehren der Gemeinde">
			<div class="wrap">
				<ul class="ff-grid">
					<?php foreach ( $afk_kinder as $k ) : ?>
						<li><a class="ff-card" href="<?php echo esc_url( get_term_link( $k ) ); ?>"><span class="ff-card__name"><?php echo esc_html( $k->name ); ?></span><?php echo afk_ns_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>
<?php endif; ?>

<?php
$afk_alarme = afk_ns_alarme( 5, $afk_ist_wehr ? (int) $afk_term->term_id : 0 );
if ( $afk_alarme && $afk_ist_wehr ) :
	?>
	<section class="section section--tight-bottom" aria-labelledby="afk-alarm-h">
		<div class="wrap">
			<div class="section-head">
				<h2 class="section-title section-title--sm" id="afk-alarm-h">Letzte Alarmierungen</h2>
				<?php $afk_al_url = afk_ns_seite_mit_vorlage( 'page-alarmierungen.php' ); ?>
				<?php if ( $afk_al_url ) : ?><a class="more-link" href="<?php echo esc_url( add_query_arg( 'ff', $afk_term->slug, $afk_al_url ) ); ?>">Alle anzeigen →</a><?php endif; ?>
			</div>
			<ul class="list">
				<?php foreach ( $afk_alarme as $a ) { afk_ns_alarm_zeile( $a ); } ?>
			</ul>
			<?php afk_ns_alarm_quelle(); ?>
		</div>
	</section>
<?php endif; ?>

<section class="section<?php echo ( $afk_alarme && $afk_ist_wehr ) ? ' section--tight-top' : ''; ?>" id="berichte" aria-labelledby="afk-ber-h">
	<div class="wrap">
		<div class="section-head">
			<div>
				<h2 class="section-title" id="afk-ber-h">Berichte</h2>
				<p class="section-sub">
					<?php
					global $wp_query;
					$afk_n = (int) $wp_query->found_posts;
					/* translators: 1: Anzahl, 2: Name */
					echo esc_html( sprintf( _n( '%1$d Bericht mit Beteiligung der %2$s', '%1$d Berichte mit Beteiligung der %2$s', $afk_n, 'afk-nachtschicht' ), $afk_n, $afk_ist_wehr ? $afk_term->name : 'Feuerwehren dieser Gemeinde' ) );
					?>
				</p>
			</div>
		</div>

		<?php afk_ns_filterleiste( array( 'action' => $afk_base, 'anchor' => 'berichte', 'ff' => false ) ); ?>

		<?php if ( have_posts() ) : ?>
			<div class="tiles">
				<?php
				while ( have_posts() ) {
					the_post();
					afk_ns_kachel( get_the_ID() );
				}
				?>
			</div>
			<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '←', 'next_text' => '→' ) ); ?>
		<?php else : ?>
			<p class="empty">Noch keine Berichte zugeordnet<?php echo $afk_f['aktiv'] ? ' – für diese Auswahl' : ''; ?>.</p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
