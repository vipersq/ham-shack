<?php
/**
 * Single blog post.
 *
 * @package HamShack
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>
		<header class="page-hero post-hero">
			<div class="container narrow">
				<?php
				$cats = get_the_category();
				if ( $cats ) :
					?>
					<p class="eyebrow mono"><a href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a></p>
				<?php endif; ?>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<?php hamshack_post_meta(); ?>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="container post-feature"><?php the_post_thumbnail( 'hamshack-hero' ); ?></figure>
		<?php endif; ?>

		<div class="container layout">
			<div class="layout-main">
				<div class="entry-content">
					<?php
					the_content();
					wp_link_pages( array( 'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'hamshack' ), 'after' => '</nav>' ) );
					?>
				</div>

				<footer class="entry-footer">
					<?php the_tags( '<div class="tags">' . hamshack_icon( 'tag', 16 ) . ' ', ' ', '</div>' ); ?>
					<div class="signoff panel">
						<img src="<?php echo hamshack_img( 'icon.svg' ); // phpcs:ignore ?>" alt="" width="56" height="56">
						<div>
							<strong class="mono"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?></strong>
							<p><?php echo esc_html( hamshack_join( array( hamshack_opt( 'qth' ), hamshack_opt( 'locator' ), __( 'thanks for reading, 73!', 'hamshack' ) ) ) ); ?></p>
						</div>
					</div>
				</footer>

				<nav class="post-nav">
					<?php
					$prev = get_previous_post();
					$next = get_next_post();
					if ( $prev ) {
						echo '<a class="post-nav-prev panel" href="' . esc_url( get_permalink( $prev ) ) . '"><span>' . hamshack_icon( 'arrow-l', 16 ) . ' ' . esc_html__( 'Previous', 'hamshack' ) . '</span><strong>' . esc_html( get_the_title( $prev ) ) . '</strong></a>'; // phpcs:ignore
					}
					if ( $next ) {
						echo '<a class="post-nav-next panel" href="' . esc_url( get_permalink( $next ) ) . '"><span>' . esc_html__( 'Next', 'hamshack' ) . ' ' . hamshack_icon( 'arrow-r', 16 ) . '</span><strong>' . esc_html( get_the_title( $next ) ) . '</strong></a>'; // phpcs:ignore
					}
					?>
				</nav>

				<?php
				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			</div>
			<?php get_sidebar(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
