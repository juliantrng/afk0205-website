<?php
/**
 * Fußbereich.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
$afk_mail = get_theme_mod( 'afk_email', 'info@afk-millstatt-radenthein.at' );
$afk_tg   = get_theme_mod( 'afk_telegram', '' );
?>
</main>

<footer class="site-footer">
	<div class="wrap site-footer__inner">
		<div>
			<a class="site-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Zur Startseite"><?php echo afk_ns_logo_img(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<p class="site-footer__claim">Abschnittsfeuerwehrkommando Millstatt – Radenthein im Bezirk Spittal an der Drau. Freiwillig. Rund um die Uhr.</p>
		</div>
		<div class="site-footer__cols">
			<div>
				<h2 class="site-footer__h">Abschnitt</h2>
				<ul class="footer-nav">
					<li><a href="<?php echo esc_url( afk_ns_alle_berichte_url() ); ?>">Alle Berichte</a></li>
					<li><a href="<?php echo esc_url( afk_ns_feuerwehren_url() ); ?>">Feuerwehren</a></li>
					<?php $afk_al = afk_ns_seite_mit_vorlage( 'page-alarmierungen.php' ); ?>
					<?php if ( $afk_al ) : ?><li><a href="<?php echo esc_url( $afk_al ); ?>">Alarmierungen</a></li><?php endif; ?>
					<?php $afk_te = afk_ns_termine_url(); ?>
					<?php if ( $afk_te ) : ?><li><a href="<?php echo esc_url( $afk_te ); ?>">Termine</a></li><?php endif; ?>
				</ul>
			</div>
			<div>
				<h2 class="site-footer__h">Kontakt &amp; Rechtliches</h2>
				<?php if ( $afk_mail ) : ?>
					<p style="margin:0 0 8px"><a class="site-footer__mail" href="mailto:<?php echo esc_attr( antispambot( $afk_mail ) ); ?>"><?php echo esc_html( antispambot( $afk_mail ) ); ?></a></p>
				<?php endif; ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-nav',
						'depth'          => 1,
						'fallback_cb'    => 'afk_ns_footer_fallback',
					)
				);
				?>
			</div>
		</div>
	</div>
	<div class="wrap">
		<p class="site-footer__copy">© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> · Notruf Feuerwehr <a href="tel:122">122</a> · Euronotruf <a href="tel:112">112</a></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
