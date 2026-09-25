<?php
/**
 * Comments.
 *
 * @package HamShack
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="section-title small-title">
			<?php
			/* translators: %d: comment count */
			printf( esc_html( _n( '%d comment', '%d comments', get_comments_number(), 'hamshack' ) ), (int) get_comments_number() );
			?>
		</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'callback'    => 'hamshack_comment',
					'style'       => 'ol',
					'avatar_size' => 44,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'        => __( 'Leave a comment', 'hamshack' ),
			'class_submit'       => 'btn btn-primary',
			'comment_notes_before' => '<p class="muted small">' . esc_html__( 'Your email address will not be published. Callsigns welcome!', 'hamshack' ) . '</p>',
		)
	);
	?>
</section>
