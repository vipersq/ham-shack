<?php
/**
 * WP-Admin dashboard widget: the shack control panel.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'hamshack_shack', __( '📻 Shack control panel', 'hamshack' ), 'hamshack_dashboard_widget' );
	// Move to the top of the first column.
	global $wp_meta_boxes;
	$normal = $wp_meta_boxes['dashboard']['normal']['core'];
	$mine   = array( 'hamshack_shack' => $normal['hamshack_shack'] );
	unset( $normal['hamshack_shack'] );
	$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $mine, $normal ); // phpcs:ignore
} );

function hamshack_dashboard_widget() {
	$status = hamshack_opt( 'status', 'off' );
	$stats  = hamshack_log_stats();
	?>
	<div class="hamshack-dash">
		<?php if ( isset( $_GET['hsqso'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'QSO logged. 73!', 'hamshack' ); ?></p></div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'On-air status', 'hamshack' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hamshack-dash-status">
			<?php wp_nonce_field( 'hamshack_status' ); ?>
			<input type="hidden" name="action" value="hamshack_status">
			<div class="hamshack-seg">
				<?php foreach ( hamshack_status_choices() as $k => $label ) : ?>
					<label class="seg-<?php echo esc_attr( $k ); ?>"><input type="radio" name="s" value="<?php echo esc_attr( $k ); ?>" <?php checked( $status, $k ); ?>><span><?php echo esc_html( $label ); ?></span></label>
				<?php endforeach; ?>
			</div>
			<div class="hamshack-row">
				<input type="text" name="freq" value="<?php echo esc_attr( hamshack_opt( 'status_freq' ) ); ?>" placeholder="<?php esc_attr_e( 'MHz', 'hamshack' ); ?>">
				<input type="text" name="mode" value="<?php echo esc_attr( hamshack_opt( 'status_mode' ) ); ?>" placeholder="<?php esc_attr_e( 'Mode', 'hamshack' ); ?>" list="hamshack-dash-modes">
				<datalist id="hamshack-dash-modes"><?php foreach ( hamshack_all_modes() as $m ) { echo '<option value="' . esc_attr( $m ) . '">'; } ?></datalist>
			</div>
			<input type="text" class="widefat" name="note" value="<?php echo esc_attr( hamshack_opt( 'status_note' ) ); ?>" placeholder="<?php esc_attr_e( 'Status note (optional)', 'hamshack' ); ?>">
			<p><button class="button button-primary"><?php esc_html_e( 'Update status', 'hamshack' ); ?></button></p>
		</form>

		<h3><?php esc_html_e( 'Quick log', 'hamshack' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hamshack-quicklog">
			<?php wp_nonce_field( 'hamshack_quick_qso' ); ?>
			<input type="hidden" name="action" value="hamshack_quick_qso">
			<div class="hamshack-row">
				<input type="text" name="q[call]" placeholder="<?php esc_attr_e( 'Callsign', 'hamshack' ); ?>" required style="text-transform:uppercase;font-family:monospace">
				<input type="text" name="q[freq]" value="<?php echo esc_attr( hamshack_opt( 'status_freq' ) ); ?>" placeholder="MHz">
				<input type="text" name="q[mode]" value="<?php echo esc_attr( hamshack_opt( 'status_mode' ) ); ?>" placeholder="<?php esc_attr_e( 'Mode', 'hamshack' ); ?>" list="hamshack-dash-modes">
			</div>
			<div class="hamshack-row">
				<input type="text" name="q[rst_s]" value="59" placeholder="RST s" aria-label="RST sent">
				<input type="text" name="q[rst_r]" value="59" placeholder="RST r" aria-label="RST received">
				<input type="text" name="q[country]" placeholder="<?php esc_attr_e( 'Country', 'hamshack' ); ?>">
			</div>
			<p><button class="button"><?php esc_html_e( 'Log it (time = now UTC)', 'hamshack' ); ?></button></p>
		</form>

		<h3><?php esc_html_e( 'Log statistics', 'hamshack' ); ?></h3>
		<p class="hamshack-stats-line">
			<strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong> <?php esc_html_e( 'QSOs', 'hamshack' ); ?> ·
			<strong><?php echo esc_html( number_format_i18n( $stats['calls'] ) ); ?></strong> <?php esc_html_e( 'calls', 'hamshack' ); ?> ·
			<strong><?php echo esc_html( number_format_i18n( $stats['countries'] ) ); ?></strong> <?php esc_html_e( 'countries', 'hamshack' ); ?>
		</p>

		<?php if ( function_exists( 'hamshack_qrz_dashboard_line' ) ) { hamshack_qrz_dashboard_line(); } ?>

		<h3><?php esc_html_e( 'Coming up', 'hamshack' ); ?></h3>
		<ul class="hamshack-dash-events">
			<?php
			$occ = hamshack_upcoming_events( 4 );
			if ( ! $occ ) {
				echo '<li>' . esc_html__( 'Nothing scheduled.', 'hamshack' ) . '</li>';
			}
			foreach ( $occ as $o ) {
				echo '<li><a href="' . esc_url( get_edit_post_link( $o['id'] ) ) . '">' . esc_html( $o['title'] ) . '</a> <span>' . esc_html( hamshack_event_when( $o ) ) . '</span></li>';
			}
			?>
		</ul>
		<p class="hamshack-dash-links">
			<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write a post', 'hamshack' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=hamshack_event' ) ); ?>"><?php esc_html_e( 'Add event', 'hamshack' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=hamshack_qso' ) ); ?>"><?php esc_html_e( 'Logbook', 'hamshack' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=hamshack' ) ); ?>"><?php esc_html_e( 'Station settings', 'hamshack' ); ?></a>
		</p>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_quick_qso', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_quick_qso' );
	$data = isset( $_POST['q'] ) ? wp_unslash( (array) $_POST['q'] ) : array(); // phpcs:ignore
	hamshack_save_qso( 0, $data );
	wp_safe_redirect( admin_url( 'index.php?hsqso=1' ) );
	exit;
} );
