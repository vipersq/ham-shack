<?php
/**
 * Events archive (/events/) – calendar view.
 *
 * @package HamShack
 */

get_header();
?>
<header class="page-hero">
	<div class="container">
		<p class="eyebrow mono"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?></p>
		<h1 class="page-title"><?php esc_html_e( 'Events calendar', 'hamshack' ); ?></h1>
	</div>
</header>
<div class="container entry-content">
	<?php echo hamshack_calendar_html(); // phpcs:ignore ?>
</div>
<?php
get_footer();
