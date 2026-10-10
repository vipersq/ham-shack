<?php
/**
 * Generic page.
 *
 * @package HamShack
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="page-hero">
			<div class="container">
				<p class="eyebrow mono"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?></p>
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="container post-feature"><?php the_post_thumbnail( 'hamshack-hero' ); ?></figure>
		<?php endif; ?>
		<div class="container <?php echo hamshack_is_wide_content( get_the_content() ) ? '' : 'narrow'; ?> entry-content">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
		<?php if ( comments_open() || get_comments_number() ) : ?>
			<div class="container narrow"><?php comments_template(); ?></div>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
