<?php
/**
 * Live rig: the shack PC (HRD → Ham Shack Rig Link script) pushes the current
 * frequency/mode here; the website shows it live and switches ON AIR automatically.
 *
 * REST:
 *   POST /wp-json/hamshack/v1/rig   (header X-HamShack-Token) – from the shack PC
 *   GET  /wp-json/hamshack/v1/rig                         – public, used by the live display
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings + last reading (not autoloaded).
 */
function hamshack_rig_state() {
	return wp_parse_args(
		(array) get_option( 'hamshack_rig', array() ),
		array(
			'enabled'   => 0,
			'token'     => '',
			'auto_air'  => 1,
			'timeout'   => 5,      // minutes without data → off air.
			'freq_hz'   => 0,
			'mode'      => '',
			'rig'       => '',
			'ts'        => 0,      // last time data arrived.
			'changed'   => 0,      // last time frequency/mode changed.
			'auto_on'   => 0,      // we switched the status ON (so we may switch it OFF again).
			'wsjtx_public'  => 1,  // show WSJT-X decodes on the website.
			'wsjtx_autolog' => 1,  // QSOs logged in WSJT-X go straight into the logbook.
		)
	);
}

function hamshack_rig_save( array $changes ) {
	$state = array_merge( hamshack_rig_state(), $changes );
	if ( false === get_option( 'hamshack_rig' ) ) {
		add_option( 'hamshack_rig', $state, '', 'no' );
	} else {
		update_option( 'hamshack_rig', $state, false );
	}
	return $state;
}

/**
 * Is the rig link currently delivering data?
 */
function hamshack_rig_online( $state = null ) {
	$state = $state ? $state : hamshack_rig_state();
	return $state['enabled'] && $state['ts'] && ( time() - (int) $state['ts'] ) < max( 1, (int) $state['timeout'] ) * MINUTE_IN_SECONDS;
}

/**
 * 14074000 → "14.074.000"
 */
function hamshack_rig_format_freq( $hz ) {
	$hz = (int) $hz;
	if ( $hz <= 0 ) {
		return '';
	}
	$mhz = intdiv( $hz, 1000000 );
	$khz = intdiv( $hz % 1000000, 1000 );
	$h   = $hz % 1000;
	return sprintf( '%d.%03d.%03d', $mhz, $khz, $h );
}

/**
 * Tidy HRD / Yaesu mode names and guess the digital mode from well-known dial frequencies.
 */
function hamshack_rig_nice_mode( $mode, $hz ) {
	$m   = strtoupper( trim( (string) $mode ) );
	$map = array(
		'USER-U' => 'USB-D',
		'USER-L' => 'LSB-D',
		'DATA-U' => 'USB-D',
		'DATA-L' => 'LSB-D',
		'PKT-U'  => 'USB-D',
		'PKT-L'  => 'LSB-D',
		'PKT'    => 'USB-D',
		'DIG'    => 'USB-D',
		'DATA'   => 'USB-D',
		'CW-R'   => 'CW',
		'CWR'    => 'CW',
		'RTTY-R' => 'RTTY',
		'FM-N'   => 'FM',
		'AM-N'   => 'AM',
	);
	$m = isset( $map[ $m ] ) ? $map[ $m ] : $m;
	if ( in_array( $m, array( 'USB-D', 'LSB-D', 'USB', 'LSB' ), true ) && $hz ) {
		$khz  = $hz / 1000;
		$dial = array(
			'FT8' => array( 1840, 3573, 5357, 7074, 10136, 14074, 18100, 21074, 24915, 28074, 50313, 70154, 144174 ),
			'FT4' => array( 3575, 7047.5, 10140, 14080, 18104, 21140, 24919, 28180, 50318, 144170 ),
			'JS8' => array( 3578, 7078, 10130, 14078, 18104.5, 21078, 24922, 28078, 50318 ),
		);
		foreach ( $dial as $name => $list ) {
			foreach ( $list as $d ) {
				if ( $khz >= $d && $khz <= $d + 3 ) {
					return $name;
				}
			}
		}
	}
	return $m;
}

/* --------------------------------------------------------------------------
 * REST API
 * ------------------------------------------------------------------------ */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'hamshack/v1',
		'/rig',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'hamshack_rig_receive',
				'permission_callback' => 'hamshack_rig_auth',
			),
			array(
				'methods'             => 'GET',
				'callback'            => 'hamshack_rig_public',
				'permission_callback' => '__return_true',
			),
		)
	);
} );

function hamshack_rig_auth( WP_REST_Request $req ) {
	$state = hamshack_rig_state();
	$given = (string) $req->get_header( 'x_hamshack_token' );
	if ( '' === $given ) {
		$given = (string) $req->get_param( 'token' ); // Fallback for hosts that strip custom headers.
	}
	if ( ! $state['enabled'] || ! $state['token'] ) {
		return new WP_Error( 'hamshack_rig_off', 'Live rig is switched off in the website settings.', array( 'status' => 403 ) );
	}
	if ( '' === $given || ! hash_equals( $state['token'], $given ) ) {
		return new WP_Error( 'hamshack_rig_token', 'Wrong token.', array( 'status' => 401 ) );
	}
	return true;
}

function hamshack_rig_receive( WP_REST_Request $req ) {
	$state   = hamshack_rig_state();
	$now     = time();
	$offline = (bool) $req->get_param( 'offline' );
	$hz      = (int) $req->get_param( 'freq_hz' );
	$mode    = substr( sanitize_text_field( (string) $req->get_param( 'mode' ) ), 0, 16 );
	$rig     = substr( sanitize_text_field( (string) $req->get_param( 'rig' ) ), 0, 40 );

	if ( $offline ) {
		// HRD closed / radio switched off: go QRT straight away.
		hamshack_rig_save( array( 'ts' => 0 ) );
		hamshack_rig_expire( true );
		return rest_ensure_response( array( 'ok' => true, 'online' => false ) );
	}
	if ( $hz < 100000 || $hz > 11000000000 ) {
		return new WP_Error( 'hamshack_rig_freq', 'Frequency missing or out of range.', array( 'status' => 400 ) );
	}

	$changed = ( $hz !== (int) $state['freq_hz'] || $mode !== $state['mode'] );
	$save    = array(
		'freq_hz' => $hz,
		'mode'    => $mode,
		'rig'     => $rig,
		'ts'      => $now,
	);
	if ( $changed ) {
		$save['changed'] = $now;
	}
	// Heartbeats without changes only touch the DB once a minute.
	if ( $changed || ( $now - (int) $state['ts'] ) >= 60 ) {
		$state = hamshack_rig_save( $save );
	}

	// Mirror into the station status (header pill, home page card, dashboard).
	$station = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
	$freq    = rtrim( rtrim( number_format( $hz / 1000000, 4, '.', '' ), '0' ), '.' );
	$nice    = hamshack_rig_nice_mode( $mode, $hz );
	$new     = $station['status'];
	$auto_on = (int) $state['auto_on'];
	if ( $state['auto_air'] && in_array( $station['status'], array( 'off', 'listen' ), true ) ) {
		$new     = 'on';
		$auto_on = 1;
	}
	if ( $new !== $station['status'] || $freq !== $station['status_freq'] || $nice !== $station['status_mode'] ) {
		hamshack_set_status( $new, $freq, $nice, null );
		hamshack_rig_purge_cache();
	}
	if ( $auto_on !== (int) $state['auto_on'] ) {
		hamshack_rig_save( array( 'auto_on' => $auto_on ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'online' => true, 'freq' => hamshack_rig_format_freq( $hz ), 'mode' => $nice ) );
}

/**
 * Public read-out for the live display (never cached).
 */
function hamshack_rig_public() {
	hamshack_rig_expire();
	$state  = hamshack_rig_state();
	$online = hamshack_rig_online( $state );
	ob_start();
	get_template_part( 'template-parts/status-card' );
	$card = ob_get_clean();
	$data = array(
		'online'  => $online,
		'status'  => hamshack_opt( 'status', 'off' ),
		'freq'    => $online ? hamshack_rig_format_freq( $state['freq_hz'] ) : '',
		'freq_hz' => $online ? (int) $state['freq_hz'] : 0,
		'mode'    => $online ? hamshack_rig_nice_mode( $state['mode'], $state['freq_hz'] ) : '',
		'band'    => $online ? hamshack_band_from_freq( $state['freq_hz'] / 1000000 ) : '',
		'rig'     => $online ? $state['rig'] : '',
		'age'     => $state['ts'] ? time() - (int) $state['ts'] : null,
		'pill'    => hamshack_status_pill(),
		'card'    => $card,
	);
	$res = rest_ensure_response( $data );
	$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $res;
}

/**
 * Switch back to OFF AIR when the rig link has gone quiet (only if we switched it on).
 */
function hamshack_rig_expire( $force = false ) {
	$state = hamshack_rig_state();
	if ( ! $state['auto_on'] ) {
		return;
	}
	if ( $force || ! hamshack_rig_online( $state ) ) {
		if ( 'on' === hamshack_opt( 'status', 'off' ) ) {
			hamshack_set_status( 'off' );
			hamshack_rig_purge_cache();
		}
		hamshack_rig_save( array( 'auto_on' => 0 ) );
	}
}
add_action( 'hamshack_qrz_cron', 'hamshack_rig_expire' );
add_action( 'hamshack_rig_cron', 'hamshack_rig_expire' );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'hamshack_rig_cron' ) && hamshack_rig_state()['enabled'] ) {
		wp_schedule_event( time() + 300, 'hamshack_15min', 'hamshack_rig_cron' );
	}
} );
add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'hamshack_rig_cron' );
} );

/**
 * Tell LiteSpeed Cache (if installed) that the status changed.
 */
function hamshack_rig_purge_cache() {
	do_action( 'litespeed_purge_all' );
}

/* --------------------------------------------------------------------------
 * Front end: live polling
 * ------------------------------------------------------------------------ */

add_action( 'wp_enqueue_scripts', function () {
	$state = hamshack_rig_state();
	if ( ! $state['enabled'] ) {
		return;
	}
	wp_add_inline_script(
		'hamshack',
		'window.HAMSHACK_LIVE=' . wp_json_encode(
			array(
				'url'      => esc_url_raw( rest_url( 'hamshack/v1/rig' ) ),
				'interval' => 10,
			)
		) . ';',
		'before'
	);
}, 20 );

/* --------------------------------------------------------------------------
 * Admin page
 * ------------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'Live rig (HRD + WSJT-X)', 'hamshack' ), __( 'Live rig', 'hamshack' ), 'manage_options', 'hamshack-rig', 'hamshack_rig_page' );
}, 25 );

function hamshack_rig_page() {
	$state  = hamshack_rig_state();
	$online = hamshack_rig_online( $state );
	$fresh  = get_transient( 'hamshack_rig_new_token' );
	if ( $fresh ) {
		delete_transient( 'hamshack_rig_new_token' );
	}
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'Live rig – frequency from Ham Radio Deluxe, decodes from WSJT-X', 'hamshack' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'hamshack' ); ?></p></div>
		<?php endif; ?>

		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'radio' ); ?> <?php esc_html_e( 'Status', 'hamshack' ); ?></h2>
			<div class="hamshack-qrz-status <?php echo $online ? 'is-ok' : ''; ?>">
				<?php if ( ! $state['enabled'] ) : ?>
					<strong><?php esc_html_e( 'Live rig is OFF.', 'hamshack' ); ?></strong> <?php esc_html_e( 'Tick “Enable” below and save.', 'hamshack' ); ?>
				<?php elseif ( $online ) : ?>
					<strong><?php esc_html_e( 'Receiving from the shack PC', 'hamshack' ); ?></strong> ·
					<span style="font-family:monospace;font-size:1.15em"><?php echo esc_html( hamshack_rig_format_freq( $state['freq_hz'] ) ); ?> MHz <?php echo esc_html( hamshack_rig_nice_mode( $state['mode'], $state['freq_hz'] ) ); ?></span>
					<?php if ( $state['rig'] ) : ?> · <?php echo esc_html( $state['rig'] ); ?><?php endif; ?>
					· <?php printf( esc_html__( 'last update %s ago', 'hamshack' ), esc_html( human_time_diff( $state['ts'] ) ) ); ?>
				<?php elseif ( $state['ts'] ) : ?>
					<strong><?php esc_html_e( 'No data right now.', 'hamshack' ); ?></strong> <?php printf( esc_html__( 'Last reading %s ago.', 'hamshack' ), esc_html( human_time_diff( $state['ts'] ) ) ); ?>
				<?php else : ?>
					<strong><?php esc_html_e( 'Waiting for the first reading from the shack PC.', 'hamshack' ); ?></strong>
				<?php endif; ?>
			</div>
		</div>

		<form class="hamshack-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hamshack_rig_save' ); ?>
			<input type="hidden" name="action" value="hamshack_rig_save">
			<h2><?php hamshack_the_icon( 'antenna' ); ?> <?php esc_html_e( 'Settings', 'hamshack' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Live rig', 'hamshack' ); ?></th><td>
					<label><input type="checkbox" name="enabled" value="1" <?php checked( $state['enabled'] ); ?>> <?php esc_html_e( 'Enable – accept frequency updates from my shack PC and show them live on the website', 'hamshack' ); ?></label>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Automatic ON AIR', 'hamshack' ); ?></th><td>
					<label><input type="checkbox" name="auto_air" value="1" <?php checked( $state['auto_air'] ); ?>> <?php esc_html_e( 'Switch to ON AIR when data arrives, back to OFF AIR when it stops', 'hamshack' ); ?></label>
					<p class="description"><?php esc_html_e( 'If you set “Portable” by hand it is left alone – only the frequency is updated.', 'hamshack' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="hs-rig-timeout"><?php esc_html_e( 'Go OFF AIR after', 'hamshack' ); ?></label></th><td>
					<input id="hs-rig-timeout" type="number" min="1" max="120" name="timeout" value="<?php echo (int) $state['timeout']; ?>" style="width:6em"> <?php esc_html_e( 'minutes without data', 'hamshack' ); ?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'WSJT-X', 'hamshack' ); ?></th><td>
					<label><input type="checkbox" name="wsjtx_public" value="1" <?php checked( $state['wsjtx_public'] ); ?>> <?php esc_html_e( 'Show live FT8/FT4 decodes on the website (Live page and home page)', 'hamshack' ); ?></label><br>
					<label><input type="checkbox" name="wsjtx_autolog" value="1" <?php checked( $state['wsjtx_autolog'] ); ?>> <?php esc_html_e( 'Add QSOs logged in WSJT-X to the logbook straight away (duplicates from QRZ sync are skipped)', 'hamshack' ); ?></label>
					<?php
					$ws = hamshack_wsjtx_state();
					if ( $ws['ts'] ) {
						echo '<p class="description">' . esc_html( sprintf( __( 'Last data from WSJT-X: %s ago.', 'hamshack' ), human_time_diff( $ws['ts'] ) ) ) . '</p>';
					}
					?>
				</td></tr>
			</table>
			<p><button class="button button-primary" name="do" value="save"><?php esc_html_e( 'Save', 'hamshack' ); ?></button></p>
		</form>

		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'mail' ); ?> <?php esc_html_e( 'Values for the Ham Shack Rig Link script', 'hamshack' ); ?></h2>
			<p><?php esc_html_e( 'Copy these two values into config.txt next to the script on your shack PC.', 'hamshack' ); ?></p>
			<table class="form-table" role="presentation">
				<tr><th scope="row">SiteUrl</th><td><code><?php echo esc_html( untrailingslashit( home_url() ) ); ?></code></td></tr>
				<tr><th scope="row">Token</th><td>
					<?php if ( $fresh ) : ?>
						<input type="text" class="large-text code" readonly value="<?php echo esc_attr( $fresh ); ?>" onclick="this.select()">
						<p class="description" style="color:#b32d2e"><strong><?php esc_html_e( 'Copy it now – for safety it is shown only once.', 'hamshack' ); ?></strong></p>
					<?php elseif ( $state['token'] ) : ?>
						<code><?php echo esc_html( str_repeat( '•', 16 ) . substr( $state['token'], -4 ) ); ?></code>
						<p class="description"><?php esc_html_e( 'Lost it? Generate a new one and update config.txt.', 'hamshack' ); ?></p>
					<?php else : ?>
						<em><?php esc_html_e( 'No token yet.', 'hamshack' ); ?></em>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
						<?php wp_nonce_field( 'hamshack_rig_save' ); ?>
						<input type="hidden" name="action" value="hamshack_rig_save">
						<button class="button" name="do" value="token"<?php echo $state['token'] ? ' onclick="return confirm(\'' . esc_js( __( 'Make a new token? The old one stops working immediately.', 'hamshack' ) ) . '\')"' : ''; ?>><?php echo $state['token'] ? esc_html__( 'Generate new token', 'hamshack' ) : esc_html__( 'Generate token', 'hamshack' ); ?></button>
					</form>
				</td></tr>
			</table>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_rig_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_rig_save' );
	$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : 'save';
	if ( 'token' === $do ) {
		$token = wp_generate_password( 40, false, false );
		hamshack_rig_save( array( 'token' => $token ) );
		set_transient( 'hamshack_rig_new_token', $token, 10 * MINUTE_IN_SECONDS );
	} else {
		$state = hamshack_rig_save(
			array(
				'enabled'  => empty( $_POST['enabled'] ) ? 0 : 1,
				'auto_air' => empty( $_POST['auto_air'] ) ? 0 : 1,
				'timeout'  => isset( $_POST['timeout'] ) ? max( 1, min( 120, (int) $_POST['timeout'] ) ) : 5,
				'wsjtx_public'  => empty( $_POST['wsjtx_public'] ) ? 0 : 1,
				'wsjtx_autolog' => empty( $_POST['wsjtx_autolog'] ) ? 0 : 1,
			)
		);
		if ( $state['enabled'] && ! $state['token'] ) {
			$token = wp_generate_password( 40, false, false );
			hamshack_rig_save( array( 'token' => $token ) );
			set_transient( 'hamshack_rig_new_token', $token, 10 * MINUTE_IN_SECONDS );
		}
		if ( ! $state['enabled'] ) {
			wp_clear_scheduled_hook( 'hamshack_rig_cron' );
			hamshack_rig_expire( true );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-rig&saved=1' ) );
	exit;
} );
