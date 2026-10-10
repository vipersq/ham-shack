<?php
/**
 * Blog index / fallback template.
 *
 * @package HamShack
 */

get_header();
?>
<header class="page-hero">
	<div class="container">
		<p class="eyebrow mono"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?> · <?php esc_html_e( 'blog', 'hamshack' ); ?></p>
		<h1 class="page-title">
			<?php
			if ( is_home() && get_option( 'page_for_posts' ) ) {
				echo esc_html( get_the_title( get_option( 'page_for_posts' ) ) );
			} elseif ( is_search() ) {
				/* translators: %s search term */
				printf( esc_html__( 'Search: %s', 'hamshack' ), '<span class="accent">' . esc_html( get_search_query() ) . '</span>' );
			} elseif ( is_archive() ) {
				echo wp_kses_post( get_the_archive_title() );
			} else {
				esc_html_e( 'Latest posts', 'hamshack' );
			}
			?>
		</h1>
		<?php if ( is_archive() && get_the_archive_description() ) : ?>
			<div class="page-desc"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
		<?php endif; ?>
		<?php
		if ( is_home() || is_category() ) :
			$cats = get_categories( array( 'hide_empty' => true ) );
			if ( $cats ) :
				?>
				<nav class="cat-filter" aria-label="<?php esc_attr_e( 'Categories', 'hamshack' ); ?>">
					<a class="chip-link <?php echo is_home() ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'All', 'hamshack' ); ?></a>
					<?php foreach ( $cats as $c ) : ?>
						<a class="chip-link <?php echo is_category( $c->term_id ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( get_category_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <span><?php echo (int) $c->count; ?></span></a>
					<?php endforeach; ?>
				</nav>
				<?php
			endif;
		endif;
		?>
	</div>
</header>

<div class="container layout">
	<div class="layout-main">
		<?php if ( have_posts() ) : ?>
			<div class="grid grid-2 posts-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content' );
				endwhile;
				?>
			</div>
			<?php hamshack_pagination(); ?>
		<?php else : ?>
			<div class="panel empty">
				<h2><?php esc_html_e( 'Nothing heard on this frequency…', 'hamshack' ); ?></h2>
				<p><?php esc_html_e( 'No posts match. Try another search:', 'hamshack' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
