<?php
/**
 * Blog sidebar.
 *
 * @package HamShack
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="layout-side sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'hamshack' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
