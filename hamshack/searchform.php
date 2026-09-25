<?php
/**
 * Search form.
 *
 * @package HamShack
 */

$uid = wp_unique_id( 's-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Search for:', 'hamshack' ); ?></label>
	<span class="input-icon"><?php hamshack_the_icon( 'search', 18 ); ?><input type="search" id="<?php echo esc_attr( $uid ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search the blog…', 'hamshack' ); ?>"></span>
	<button type="submit" class="btn"><?php esc_html_e( 'Search', 'hamshack' ); ?></button>
</form>
