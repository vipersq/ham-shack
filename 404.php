<?php
/**
 * 404.
 *
 * @package HamShack
 */

get_header();
?>
<section class="section error-404">
	<div class="container narrow center">
		<p class="big-code mono">404</p>
		<h1 class="page-title"><?php esc_html_e( 'QRT – signal lost', 'hamshack' ); ?></h1>
		<p class="muted"><?php esc_html_e( 'This page has faded into the noise. Try a search, or head back to the home page.', 'hamshack' ); ?></p>
		<?php get_search_form(); ?>
		<p><a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the shack', 'hamshack' ); ?></a></p>
	</div>
</section>
<?php
get_footer();
