<?php
/**
 * Post card used in loops.
 *
 * @package HamShack
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card' ); ?>>
	<a class="card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php hamshack_thumb(); ?>
		<?php
		$cats = get_the_category();
		if ( $cats ) :
			?>
			<span class="card-cat"><?php echo esc_html( $cats[0]->name ); ?></span>
		<?php endif; ?>
	</a>
	<div class="card-body">
		<h2 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<div class="card-excerpt"><?php the_excerpt(); ?></div>
		<?php hamshack_post_meta(); ?>
	</div>
</article>
