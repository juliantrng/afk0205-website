<?php
/**
 * Suchformular.
 *
 * @package afk-nachtschicht
 */

defined( 'ABSPATH' ) || exit;
$afk_id = wp_unique_id( 'afk-s-' );
?>
<form class="search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $afk_id ); ?>">Suchbegriff</label>
	<input type="search" id="<?php echo esc_attr( $afk_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Bericht, Ort, Feuerwehr …">
	<button class="btn btn--yellow" type="submit"><?php echo afk_ns_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Suchen</button>
</form>
