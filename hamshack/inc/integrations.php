<?php
/**
 * Integrations (2.1): one settings page for notifications, Club Log, APRS, SDR,
 * contest import, repeaters, app (PWA) and performance options.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults for every integration setting.
 */
function hamshack_int_defaults() {
	return array(
		// Notifications.
		'notify_discord'        => '',
		'notify_telegram_token' => '',
		'notify_telegram_chat'  => '',
		'notify_webhook'        => '',
		'notify_events'         => array( 'on_air', 'new_post', 'new_dxcc', 'spotted', 'kp' ),
		// Club Log.
		'clublog_email'         => '',
		'clublog_password'      => '',
		'clublog_api'           => '',
		'clublog_upload'        => 0,
		// APRS.
		'aprs_key'              => '',
		'aprs_call'             => '',
		// SDR.
		'sdr_url'               => '',
		'sdr_name'              => '',
		// Contests.
		'contest_import'        => 1,
		'contest_include'       => '',
		'contest_exclude'       => '',
		// Repeaters.
		'repeater_radius'       => 60,
		'repeater_source'       => 'auto',
		'repeater_extra'        => '',
		// App & speed.
		'pwa'                   => 1,
		'theme_toggle'          => 1,
		'webp'                  => 1,
		'defer'                 => 1,
		'no_emoji'              => 1,
	);
}

/**
 * Read one integration setting.
 */
function hamshack_int( $key ) {
	$o = wp_parse_args( (array) get_option( 'hamshack_int', array() ), hamshack_int_defaults() );
	return isset( $o[ $key ] ) ? $o[ $key ] : null;
}

/**
 * Notification events the user can switch on.
 */
function hamshack_notify_event_labels() {
	return array(
		'on_air'   => __( 'I go on the air (status ON AIR / Portable)', 'hamshack' ),
		'new_post' => __( 'A new blog post is published', 'hamshack' ),
		'new_dxcc' => __( 'A new DXCC entity appears in the log', 'hamshack' ),
		'spotted'  => __( 'Somebody spots me on the DX cluster', 'hamshack' ),
		'kp'       => __( 'Geomagnetic storm (Kp 5 or more) – aurora possible', 'hamshack' ),
		'storm'    => __( 'Thunderstorm risk at the QTH – disconnect the antennas', 'hamshack' ),
		'sked'     => __( 'A new sked request arrives', 'hamshack' ),
	);
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'Integrations', 'hamshack' ), __( 'Integrations', 'hamshack' ), 'manage_options', 'hamshack-int', 'hamshack_int_page' );
}, 35 );

/**
 * Secret fields are never printed back – only "saved ••••1234".
 */
function hamshack_int_secret_keys() {
	return array( 'notify_discord', 'notify_telegram_token', 'notify_webhook', 'clublog_password', 'clublog_api', 'aprs_key' );
}

function hamshack_int_page() {
	$o   = wp_parse_args( (array) get_option( 'hamshack_int', array() ), hamshack_int_defaults() );
	$sec = hamshack_int_secret_keys();
	$txt = function ( $k, $label, $help = '', $type = 'text', $ph = '' ) use ( $o, $sec ) {
		$is_secret = in_array( $k, $sec, true );
		$val       = $is_secret ? '' : (string) $o[ $k ];
		echo '<tr><th scope="row"><label for="m6i-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="m6i-' . esc_attr( $k ) . '" class="regular-text" type="' . esc_attr( $is_secret ? 'password' : $type ) . '" name="int[' . esc_attr( $k ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '" autocomplete="off">';
		if ( $is_secret && '' !== (string) $o[ $k ] ) {
			echo ' <span class="description">' . esc_html( sprintf( __( 'saved (…%s) – leave empty to keep', 'hamshack' ), substr( (string) $o[ $k ], -4 ) ) ) . '</span>';
			echo ' <label><input type="checkbox" name="clear[]" value="' . esc_attr( $k ) . '"> ' . esc_html__( 'remove', 'hamshack' ) . '</label>';
		}
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	$chk = function ( $k, $label, $help = '' ) use ( $o ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="int[' . esc_attr( $k ) . ']" value="1" ' . checked( ! empty( $o[ $k ] ), true, false ) . '> ' . esc_html__( 'On', 'hamshack' ) . '</label>';
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	};
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'Integrations', 'hamshack' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'hamshack' ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['tested'] ) ) : // phpcs:ignore ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html( (string) get_transient( 'hamshack_int_test' ) ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hamshack_int_save' ); ?>
			<input type="hidden" name="action" value="hamshack_int_save">

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'bell' ); ?> <?php esc_html_e( 'Notifications', 'hamshack' ); ?></h2>
				<p><?php esc_html_e( 'Automatic posts to your Discord server, a Telegram channel or any web hook (Zapier / Make / IFTTT can pass them on to Facebook, X or e-mail).', 'hamshack' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$txt( 'notify_discord', __( 'Discord web hook URL', 'hamshack' ), __( 'Discord: Server settings → Integrations → Webhooks → New webhook → Copy URL.', 'hamshack' ), 'url', 'https://discord.com/api/webhooks/…' );
					$txt( 'notify_telegram_token', __( 'Telegram bot token', 'hamshack' ), __( 'Create a bot with @BotFather and add it to your channel as an administrator.', 'hamshack' ), 'text', '123456:ABC…' );
					$txt( 'notify_telegram_chat', __( 'Telegram chat / channel', 'hamshack' ), __( 'e.g. @my_channel or the numeric chat id.', 'hamshack' ), 'text', '@my_shack' );
					$txt( 'notify_webhook', __( 'Other web hook (JSON)', 'hamshack' ), __( 'Receives {"event", "title", "text", "url"} as JSON – use it with Zapier, Make or IFTTT to post on Facebook.', 'hamshack' ), 'url', 'https://hooks.zapier.com/…' );
					?>
					<tr><th scope="row"><?php esc_html_e( 'Send a message when…', 'hamshack' ); ?></th><td>
						<?php foreach ( hamshack_notify_event_labels() as $k => $label ) : ?>
							<label style="display:block;margin:3px 0"><input type="checkbox" name="int[notify_events][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $o['notify_events'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</td></tr>
				</table>
				<p><button class="button" name="do" value="test_notify"><?php esc_html_e( 'Save and send a test message', 'hamshack' ); ?></button></p>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'globe' ); ?> Club Log</h2>
				<p><?php echo wp_kses_post( __( 'The DX cluster always marks spots that would be a <strong>new DXCC or new band</strong> for you and shows Club Log’s <em>Most Wanted</em> rank – no account needed. Fill in the fields below only if you also want new QSOs uploaded to Club Log automatically.', 'hamshack' ) ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$chk( 'clublog_upload', __( 'Upload new QSOs', 'hamshack' ), __( 'Each QSO added to the log (QRZ sync, WSJT-X, by hand) is sent to Club Log in real time.', 'hamshack' ) );
					$txt( 'clublog_email', __( 'Club Log e-mail', 'hamshack' ), '', 'email' );
					$txt( 'clublog_password', __( 'Club Log application password', 'hamshack' ), __( 'Club Log → Settings → App Passwords. Not your main password.', 'hamshack' ) );
					$txt( 'clublog_api', __( 'Club Log API key', 'hamshack' ), __( 'Request a free API key from the Club Log helpdesk (clublog.freshdesk.com).', 'hamshack' ) );
					?>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'pin' ); ?> <?php esc_html_e( 'APRS & web SDR', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$txt( 'aprs_call', __( 'APRS callsign-SSID', 'hamshack' ), __( 'e.g. N0CALL-9 (car) or N0CALL-7 (handheld). Your last position shows on the Live page while you are portable.', 'hamshack' ), 'text', 'N0CALL-9' );
					$txt( 'aprs_key', __( 'aprs.fi API key', 'hamshack' ), __( 'Free: log in at aprs.fi → Account settings → API key.', 'hamshack' ) );
					$txt( 'sdr_url', __( 'Web SDR address', 'hamshack' ), __( 'Your own KiwiSDR / OpenWebRX, or a public receiver near you (e.g. a WebSDR). Shown on the Live page so visitors can listen.', 'hamshack' ), 'url', 'http://…:8073/' );
					$txt( 'sdr_name', __( 'Web SDR name', 'hamshack' ), '', 'text', 'My KiwiSDR' );
					?>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'trophy' ); ?> <?php esc_html_e( 'Contests & repeaters', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$chk( 'contest_import', __( 'Import contests', 'hamshack' ), __( 'Adds the next 8 days of contests from the WA7BNM Contest Calendar to your calendar once a day.', 'hamshack' ) );
					$txt( 'contest_include', __( 'Only contests containing', 'hamshack' ), __( 'Comma-separated words, e.g. <code>RSGB, CQ WW, IARU, UKEI, 2m, FT</code>. Empty = all contests.', 'hamshack' ) );
					$txt( 'contest_exclude', __( 'Skip contests containing', 'hamshack' ), __( 'e.g. <code>QSO Party, RTTY</code>', 'hamshack' ) );
					$txt( 'repeater_radius', __( 'Repeaters within', 'hamshack' ), __( 'kilometres of your QTH. In the UK the list comes from ukrepeater.net automatically – elsewhere add your repeaters below.', 'hamshack' ), 'number' );
					?>
					<tr><th scope="row"><label for="m6i-rx"><?php esc_html_e( 'Extra repeaters', 'hamshack' ); ?></label></th><td>
						<textarea id="m6i-rx" name="int[repeater_extra]" rows="4" class="large-text code" placeholder="W1XX, 146.940, 146.340, 100.0, FN31pr, Hartford"><?php echo esc_textarea( $o['repeater_extra'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line: callsign, output MHz, input MHz, CTCSS, locator, place.', 'hamshack' ); ?></p>
					</td></tr>
				</table>
			</div>

			<div class="hamshack-card">
				<h2><?php hamshack_the_icon( 'signal' ); ?> <?php esc_html_e( 'App & speed', 'hamshack' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$chk( 'pwa', __( 'Installable app (PWA)', 'hamshack' ), __( 'Visitors can add the site to their phone’s home screen; it opens like an app and works offline with the last pages viewed.', 'hamshack' ) );
					$chk( 'theme_toggle', __( 'Light / dark switch', 'hamshack' ), __( 'Adds a sun/moon button to the top bar. The site stays dark by default.', 'hamshack' ) );
					$chk( 'webp', __( 'WebP images', 'hamshack' ), __( 'New JPEG and PNG uploads are saved as WebP (much smaller files) if your server supports it; the original file is kept.', 'hamshack' ) );
					$chk( 'defer', __( 'Deferred scripts', 'hamshack' ), __( 'Theme scripts load without blocking the page.', 'hamshack' ) );
					$chk( 'no_emoji', __( 'No emoji script', 'hamshack' ), __( 'Removes WordPress’ emoji helper script (browsers show emoji themselves).', 'hamshack' ) );
					?>
				</table>
			</div>
			<p><button class="button button-primary" name="do" value="save"><?php esc_html_e( 'Save all', 'hamshack' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_int_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_int_save' );
	$old = wp_parse_args( (array) get_option( 'hamshack_int', array() ), hamshack_int_defaults() );
	$in  = isset( $_POST['int'] ) ? wp_unslash( (array) $_POST['int'] ) : array(); // phpcs:ignore
	$out = $old;
	$urls = array( 'notify_discord', 'notify_webhook', 'sdr_url' );
	foreach ( array( 'notify_discord', 'notify_telegram_token', 'notify_telegram_chat', 'notify_webhook', 'clublog_email', 'clublog_password', 'clublog_api', 'aprs_key', 'aprs_call', 'sdr_url', 'sdr_name', 'contest_include', 'contest_exclude' ) as $k ) {
		if ( ! isset( $in[ $k ] ) ) {
			continue;
		}
		$v = trim( (string) $in[ $k ] );
		if ( '' === $v && in_array( $k, hamshack_int_secret_keys(), true ) ) {
			continue; // Keep the saved secret.
		}
		$out[ $k ] = in_array( $k, $urls, true ) ? esc_url_raw( $v, array( 'http', 'https' ) ) : sanitize_text_field( $v );
	}
	foreach ( isset( $_POST['clear'] ) ? array_map( 'sanitize_key', (array) $_POST['clear'] ) : array() as $k ) {
		if ( in_array( $k, hamshack_int_secret_keys(), true ) ) {
			$out[ $k ] = '';
		}
	}
	$out['aprs_call']        = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', $out['aprs_call'] ) );
	$out['clublog_email']    = sanitize_email( $out['clublog_email'] );
	$out['notify_events']    = array_values( array_intersect( array_keys( hamshack_notify_event_labels() ), isset( $in['notify_events'] ) ? (array) $in['notify_events'] : array() ) );
	$out['repeater_radius']  = isset( $in['repeater_radius'] ) ? max( 5, min( 500, (int) $in['repeater_radius'] ) ) : 60;
	$out['repeater_extra']   = isset( $in['repeater_extra'] ) ? sanitize_textarea_field( $in['repeater_extra'] ) : '';
	foreach ( array( 'clublog_upload', 'contest_import', 'pwa', 'theme_toggle', 'webp', 'defer', 'no_emoji' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	update_option( 'hamshack_int', $out, false );
	delete_transient( 'hamshack_repeaters' );
	delete_transient( 'hamshack_clublog_block' );
	$args = array( 'saved' => 1 );
	if ( isset( $_POST['do'] ) && 'test_notify' === sanitize_key( $_POST['do'] ) ) {
		$res = hamshack_notify( 'test', __( 'Test message', 'hamshack' ), sprintf( __( 'Notifications from %s work. 73!', 'hamshack' ), home_url( '/' ) ), home_url( '/' ), true );
		set_transient( 'hamshack_int_test', $res ? implode( ' · ', $res ) : __( 'No notification channel is filled in.', 'hamshack' ), MINUTE_IN_SECONDS * 5 );
		$args['tested'] = 1;
	}
	if ( isset( $_POST['do'] ) && 'save' === sanitize_key( $_POST['do'] ) && ! empty( $out['contest_import'] ) ) {
		wp_schedule_single_event( time() + 5, 'hamshack_contest_import' );
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=hamshack-int' ) ) );
	exit;
} );
