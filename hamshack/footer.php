<?php
/**
 * Site footer.
 *
 * @package HamShack
 */
?>
</main>

<footer class="site-footer">
	<div class="footer-wave" aria-hidden="true"></div>
	<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
		<div class="container footer-widgets">
			<div class="footer-col footer-about">
				<?php echo hamshack_logo_svg( 'footer-logo' ); // phpcs:ignore ?>
				<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
				<ul class="station-facts mono">
					<?php echo hamshack_facts( array( __( 'QTH', 'hamshack' ) => hamshack_opt( 'qth' ), __( 'Loc', 'hamshack' ) => hamshack_opt( 'locator' ), 'ITU/CQ' => hamshack_join( array( hamshack_opt( 'itu_zone' ), hamshack_opt( 'cq_zone' ) ), ' / ' ) ) ); // phpcs:ignore ?>
				</ul>
			</div>
			<?php foreach ( array( 'footer-1', 'footer-2', 'footer-3' ) as $area ) : ?>
				<?php if ( is_active_sidebar( $area ) ) : ?>
					<div class="footer-col"><?php dynamic_sidebar( $area ); ?></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="footer-bottom">
		<div class="container footer-bottom-inner">
			<p class="footer-text">
				<span class="morse" aria-hidden="true"><?php echo esc_html( str_replace( '-', '−', hamshack_morse_text( hamshack_opt( 'callsign' ) ) ) ); ?></span>
				<?php echo esc_html( hamshack_opt( 'footer_text' ) ); ?> · © <?php echo esc_html( gmdate( 'Y' ) ); ?>
			</p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => 'nav',
					'container_class' => 'footer-nav',
					'menu_class'     => 'menu',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
			<?php $social = hamshack_social_links(); ?>
			<?php if ( $social ) : ?>
				<ul class="social">
					<?php foreach ( $social as $s ) : ?>
						<li><a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener me" aria-label="<?php echo esc_attr( $s['label'] ); ?>" title="<?php echo esc_attr( $s['label'] ); ?>"><?php hamshack_the_icon( $s['icon'], 18 ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<a class="to-top" href="#top" aria-label="<?php esc_attr_e( 'Back to top', 'hamshack' ); ?>">↑</a>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
