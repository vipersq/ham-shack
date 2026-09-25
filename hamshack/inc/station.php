<?php
/**
 * Station control panel: admin menu, settings and on-air status switching.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu icon (antenna) as a data URI so it matches the brand.
 */
function hamshack_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round"><path d="M12 9 8 21M12 9l4 12M9.3 17h5.4M10.3 13.5h3.4"/><circle cx="12" cy="7" r="1.6"/><path d="M8 3.5a5.5 5.5 0 0 0 0 7M16 3.5a5.5 5.5 0 0 1 0 7"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

add_action( 'admin_menu', 'hamshack_admin_menu', 9 );
function hamshack_admin_menu() {
	add_menu_page(
		__( 'Ham Shack Station', 'hamshack' ),
		__( 'Ham Shack', 'hamshack' ),
		'edit_posts',
		'hamshack',
		'hamshack_station_page',
		hamshack_menu_icon(),
		3
	);
	add_submenu_page( 'hamshack', __( 'Station settings', 'hamshack' ), __( 'Station settings', 'hamshack' ), 'manage_options', 'hamshack', 'hamshack_station_page' );
}

add_action( 'admin_init', 'hamshack_register_settings' );
function hamshack_register_settings() {
	register_setting(
		'hamshack_station_group',
		'hamshack_station',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'hamshack_sanitize_station',
			'default'           => hamshack_defaults(),
		)
	);
}

/**
 * Sanitize the station settings array.
 */
function hamshack_sanitize_station( $in ) {
	$in       = (array) $in;
	$current  = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
	$out      = $current;
	$text     = array( 'callsign', 'op_name', 'qth', 'locator', 'licence', 'itu_zone', 'cq_zone', 'status_freq', 'status_mode', 'status_note', 'hero_title', 'footer_text', 'qsl_info' );
	$textarea = array( 'rigs', 'antennas', 'hero_text' );
	$urls     = array( 'qrz_url', 'youtube', 'facebook', 'x', 'mastodon', 'instagram' );

	foreach ( $text as $k ) {
		if ( isset( $in[ $k ] ) ) {
			$out[ $k ] = sanitize_text_field( wp_unslash( $in[ $k ] ) );
		}
	}
	foreach ( $textarea as $k ) {
		if ( isset( $in[ $k ] ) ) {
			$out[ $k ] = sanitize_textarea_field( wp_unslash( $in[ $k ] ) );
		}
	}
	foreach ( $urls as $k ) {
		if ( isset( $in[ $k ] ) ) {
			$out[ $k ] = esc_url_raw( trim( wp_unslash( $in[ $k ] ) ) );
		}
	}
	if ( isset( $in['email'] ) ) {
		$out['email'] = sanitize_email( wp_unslash( $in['email'] ) );
	}
	$out['callsign'] = strtoupper( $out['callsign'] );
	$out['locator']  = strtoupper( substr( $out['locator'], 0, 4 ) ) . strtolower( substr( $out['locator'], 4 ) );

	// Checkbox groups are only present when the full form was submitted.
	if ( isset( $in['_full'] ) ) {
		$out['bands']        = array_values( array_intersect( hamshack_all_bands(), isset( $in['bands'] ) ? (array) $in['bands'] : array() ) );
		$out['modes']        = array_values( array_intersect( hamshack_all_modes(), isset( $in['modes'] ) ? (array) $in['modes'] : array() ) );
		$out['show_solar']   = empty( $in['show_solar'] ) ? 0 : 1;
		$out['solar_banner'] = hamshack_solar_key( isset( $in['solar_banner'] ) ? sanitize_key( $in['solar_banner'] ) : '' );
		$out['solar_home']   = hamshack_solar_key( isset( $in['solar_home'] ) ? sanitize_key( $in['solar_home'] ) : '' );
		$out['show_logbook'] = empty( $in['show_logbook'] ) ? 0 : 1;
	}
	if ( isset( $in['status'] ) && array_key_exists( $in['status'], hamshack_status_choices() ) ) {
		if ( $in['status'] !== $current['status'] ) {
			$out['status_time'] = time();
		}
		$out['status'] = $in['status'];
	}
	return $out;
}

/**
 * Change status (used by admin bar, dashboard widget).
 */
function hamshack_set_status( $status, $freq = null, $mode = null, $note = null ) {
	$opts = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
	if ( ! array_key_exists( $status, hamshack_status_choices() ) ) {
		return;
	}
	$opts['status']      = $status;
	$opts['status_time'] = time();
	if ( null !== $freq ) {
		$opts['status_freq'] = sanitize_text_field( $freq );
	}
	if ( null !== $mode ) {
		$opts['status_mode'] = sanitize_text_field( $mode );
	}
	if ( null !== $note ) {
		$opts['status_note'] = sanitize_text_field( $note );
	}
	remove_filter( 'sanitize_option_hamshack_station', 'hamshack_sanitize_station' );
	update_option( 'hamshack_station', $opts );
}

add_action( 'admin_post_hamshack_status', 'hamshack_handle_status' );
function hamshack_handle_status() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_status' );
	$status = isset( $_REQUEST['s'] ) ? sanitize_key( $_REQUEST['s'] ) : 'off';
	$freq   = isset( $_POST['freq'] ) ? wp_unslash( $_POST['freq'] ) : null; // phpcs:ignore
	$mode   = isset( $_POST['mode'] ) ? wp_unslash( $_POST['mode'] ) : null; // phpcs:ignore
	$note   = isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : null; // phpcs:ignore
	hamshack_set_status( $status, $freq, $mode, $note );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}

/**
 * Admin bar: status indicator with quick switch.
 */
add_action( 'admin_bar_menu', 'hamshack_admin_bar', 90 );
function hamshack_admin_bar( $bar ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$status = hamshack_opt( 'status', 'off' );
	$colors = array( 'on' => '#ff3b3b', 'portable' => '#ffb020', 'listen' => '#22d3ee', 'off' => '#6b7c8c' );
	$bar->add_node(
		array(
			'id'    => 'hamshack-status',
			'title' => '<span style="display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;background:' . esc_attr( $colors[ $status ] ) . ';box-shadow:0 0 8px ' . esc_attr( $colors[ $status ] ) . '"></span>' . esc_html( hamshack_opt( 'callsign' ) . ' · ' . hamshack_status_label( $status ) ),
			'href'  => admin_url( 'admin.php?page=hamshack' ),
		)
	);
	foreach ( hamshack_status_choices() as $key => $label ) {
		$bar->add_node(
			array(
				'parent' => 'hamshack-status',
				'id'     => 'hamshack-status-' . $key,
				'title'  => ( $key === $status ? '✓ ' : '' ) . esc_html( $label ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_status&s=' . $key ), 'hamshack_status' ),
			)
		);
	}
	$bar->add_node( array( 'parent' => 'hamshack-status', 'id' => 'hamshack-new-qso', 'title' => esc_html__( '+ Log a QSO', 'hamshack' ), 'href' => admin_url( 'post-new.php?post_type=hamshack_qso' ) ) );
	$bar->add_node( array( 'parent' => 'hamshack-status', 'id' => 'hamshack-new-event', 'title' => esc_html__( '+ Add event', 'hamshack' ), 'href' => admin_url( 'post-new.php?post_type=hamshack_event' ) ) );
}

/**
 * Admin styles for the shack pages.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	wp_enqueue_style( 'hamshack-admin', HAMSHACK_URI . '/assets/css/admin.css', array(), HAMSHACK_VERSION );
} );

/**
 * Settings page.
 */
function hamshack_station_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Ham Shack', 'hamshack' ) . '</h1><p>' . esc_html__( 'Use the Dashboard to change your on-air status and the Logbook / Events menus to add entries.', 'hamshack' ) . '</p></div>';
		return;
	}
	$o    = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
	$name = 'hamshack_station';
	$f    = function ( $key, $label, $type = 'text', $help = '' ) use ( $o, $name ) {
		echo '<tr><th scope="row"><label for="hs-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea class="large-text" rows="4" id="hs-' . esc_attr( $key ) . '" name="' . esc_attr( $name . '[' . $key . ']' ) . '">' . esc_textarea( $o[ $key ] ) . '</textarea>';
		} else {
			echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="hs-' . esc_attr( $key ) . '" name="' . esc_attr( $name . '[' . $key . ']' ) . '" value="' . esc_attr( $o[ $key ] ) . '">';
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<div class="wrap hamshack-admin">
		<div class="hamshack-admin-head">
			<?php echo hamshack_logo_svg( 'hamshack-admin-logo' ); // phpcs:ignore ?>
			<div class="hamshack-admin-status"><?php echo hamshack_status_pill(); // phpcs:ignore ?></div>
		</div>
		<h1><?php esc_html_e( 'Station settings', 'hamshack' ); ?></h1>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'hamshack_station_group' ); ?>
			<input type="hidden" name="hamshack_station[_full]" value="1">

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'radio' ); ?> <?php esc_html_e( 'On-air status', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Status', 'hamshack' ); ?></th><td>
						<?php foreach ( hamshack_status_choices() as $k => $label ) : ?>
							<label class="hamshack-radio"><input type="radio" name="hamshack_station[status]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $o['status'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Shown in the site header and on the home page. Tip: you can also switch it from the admin bar at the top of the screen.', 'hamshack' ); ?></p>
					</td></tr>
					<?php
					$f( 'status_freq', __( 'Frequency (MHz)', 'hamshack' ), 'text', __( 'e.g. 14.074 or 145.500', 'hamshack' ) );
					$f( 'status_mode', __( 'Mode', 'hamshack' ), 'text', __( 'e.g. FT8, SSB, FM', 'hamshack' ) );
					$f( 'status_note', __( 'Status note', 'hamshack' ), 'text', __( 'e.g. "Portable from the hills – calling CQ"', 'hamshack' ) );
					?>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'antenna' ); ?> <?php esc_html_e( 'Station identity', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$f( 'callsign', __( 'Callsign', 'hamshack' ) );
					$f( 'op_name', __( 'Operator name', 'hamshack' ), 'text', __( 'Optional – shown on the Station page.', 'hamshack' ) );
					$f( 'qth', __( 'QTH (location)', 'hamshack' ) );
					$f( 'locator', __( 'Maidenhead locator', 'hamshack' ), 'text', __( '4 or 6 characters, e.g. JO62 or JO62qm.', 'hamshack' ) );
					$f( 'licence', __( 'Licence', 'hamshack' ) );
					$f( 'itu_zone', __( 'ITU zone', 'hamshack' ) );
					$f( 'cq_zone', __( 'CQ zone', 'hamshack' ) );
					$f( 'qsl_info', __( 'QSL info', 'hamshack' ) );
					?>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'signal' ); ?> <?php esc_html_e( 'Equipment, bands & modes', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$f( 'rigs', __( 'Radios (one per line)', 'hamshack' ), 'textarea', __( 'Format: "Name – details". Example: Yaesu FT-891 – HF/6 m, 100 W', 'hamshack' ) );
					$f( 'antennas', __( 'Antennas (one per line)', 'hamshack' ), 'textarea' );
					?>
					<tr><th scope="row"><?php esc_html_e( 'Bands', 'hamshack' ); ?></th><td class="hamshack-checks">
						<?php foreach ( hamshack_all_bands() as $b ) : ?>
							<label><input type="checkbox" name="hamshack_station[bands][]" value="<?php echo esc_attr( $b ); ?>" <?php checked( in_array( $b, (array) $o['bands'], true ) ); ?>> <?php echo esc_html( $b ); ?></label>
						<?php endforeach; ?>
					</td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Modes', 'hamshack' ); ?></th><td class="hamshack-checks">
						<?php foreach ( hamshack_all_modes() as $m ) : ?>
							<label><input type="checkbox" name="hamshack_station[modes][]" value="<?php echo esc_attr( $m ); ?>" <?php checked( in_array( $m, (array) $o['modes'], true ) ); ?>> <?php echo esc_html( $m ); ?></label>
						<?php endforeach; ?>
					</td></tr>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'sun' ); ?> <?php esc_html_e( 'Home page & display', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$f( 'hero_title', __( 'Hero headline', 'hamshack' ) );
					$f( 'hero_text', __( 'Hero intro text', 'hamshack' ), 'textarea' );
					$f( 'footer_text', __( 'Footer text', 'hamshack' ) );
					?>
					<tr><th scope="row"><?php esc_html_e( 'Sections', 'hamshack' ); ?></th><td>
						<label><input type="checkbox" name="hamshack_station[show_solar]" value="1" <?php checked( $o['show_solar'] ); ?>> <?php esc_html_e( 'Show N0NBH solar-terrestrial / band conditions on the home page', 'hamshack' ); ?></label><br>
						<label><input type="checkbox" name="hamshack_station[show_logbook]" value="1" <?php checked( $o['show_logbook'] ); ?>> <?php esc_html_e( 'Show recent QSOs on the home page', 'hamshack' ); ?></label>
					</td></tr>
					<?php foreach ( array( 'solar_home' => __( 'N0NBH banner – home page', 'hamshack' ), 'solar_banner' => __( 'N0NBH banner – sidebar widget', 'hamshack' ) ) as $sk => $slabel ) : ?>
						<tr><th scope="row"><label for="hs-<?php echo esc_attr( $sk ); ?>"><?php echo esc_html( $slabel ); ?></label></th><td>
							<select id="hs-<?php echo esc_attr( $sk ); ?>" name="hamshack_station[<?php echo esc_attr( $sk ); ?>]" class="hamshack-solar-select">
								<?php foreach ( hamshack_solar_banners() as $bk => $bl ) : ?>
									<option value="<?php echo esc_attr( $bk ); ?>" <?php selected( $o[ $sk ], $bk ); ?>><?php echo esc_html( $bl ); ?></option>
								<?php endforeach; ?>
							</select>
							<p><img class="hamshack-solar-preview" src="<?php echo esc_url( 'https://www.hamqsl.com/' . hamshack_solar_key( $o[ $sk ] ) . '.php' ); ?>" alt="" loading="lazy" style="max-width:100%;height:auto;margin-top:8px;border-radius:6px"></p>
						</td></tr>
					<?php endforeach; ?>
					<tr><td colspan="2"><p class="description"><?php printf( esc_html__( 'Live solar-terrestrial data by Paul L Herrman, N0NBH (%s). The credit link is always kept, as N0NBH asks. Data updates hourly.', 'hamshack' ), '<a href="https://www.hamqsl.com/solar.html" target="_blank" rel="noopener">hamqsl.com</a>' ); // phpcs:ignore ?></p></td></tr>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'globe' ); ?> <?php esc_html_e( 'Links & contact', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$f( 'email', __( 'Contact form email', 'hamshack' ), 'email', __( 'Messages from the contact form go here. Leave empty to use the site admin email.', 'hamshack' ) );
					$f( 'qrz_url', __( 'QRZ.com page', 'hamshack' ), 'url' );
					$f( 'youtube', 'YouTube', 'url' );
					$f( 'facebook', 'Facebook', 'url' );
					$f( 'x', 'X / Twitter', 'url' );
					$f( 'mastodon', 'Mastodon', 'url' );
					$f( 'instagram', 'Instagram', 'url' );
					?>
				</table>
			</div>
			<?php submit_button( __( 'Save station settings', 'hamshack' ) ); ?>
		</form>
	</div>
	<script>
	document.querySelectorAll('.hamshack-solar-select').forEach(function (sel) {
		sel.addEventListener('change', function () {
			var img = sel.closest('td').querySelector('.hamshack-solar-preview');
			if (img) { img.src = 'https://www.hamqsl.com/' + sel.value + '.php'; }
		});
	});
	</script>
	<?php
}
