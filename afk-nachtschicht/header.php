<?php
/**
 * Kopfbereich.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<a class="skip-link screen-reader-text" href="#inhalt">Zum Inhalt springen</a>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="wrap site-header__inner">
		<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>, zur Startseite">
			<?php echo afk_ns_logo_img(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
		<nav class="nav" aria-label="Hauptnavigation">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav__list',
					'menu_id'        => 'afk-menu',
					'depth'          => 1,
					'fallback_cb'    => 'afk_ns_menu_fallback',
				)
			);
			?>
			<div class="nav__actions">
				<a class="icon-btn" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>" aria-label="Suche öffnen"><?php echo afk_ns_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<a class="notruf-btn" href="tel:122"><?php echo afk_ns_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><span class="notruf-btn__label">Notruf&nbsp;</span>122</span></a>
				<button class="icon-btn nav-toggle" type="button" aria-controls="afk-menu" aria-expanded="false" aria-label="Menü öffnen"><?php echo afk_ns_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
		</nav>
	</div>
</header>

<main id="inhalt">
