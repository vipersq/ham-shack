<?php
/**
 * Live data: WSJT-X decodes (from Rig Link), DX cluster spots, PSK Reporter
 * reception reports and the greyline / clock widget.
 *
 * REST (namespace hamshack/v1):
 *   POST /wsjtx  – from Rig Link (X-HamShack-Token), decodes + status + logged QSOs
 *   GET  /live   – public: current WSJT-X activity
 *   GET  /dx     – public: DX cluster spots (cached 90 s)
 *   GET  /psk    – public: who hears me on PSK Reporter (cached 10 min)
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------------
 * WSJT-X
 * ------------------------------------------------------------------------ */

function hamshack_wsjtx_state() {
	return wp_parse_args(
		(array) get_option( 'hamshack_wsjtx', array() ),
		array(
			'ts'      => 0,
			'status'  => array(),
			'decodes' => array(),
			'logged'  => 0,
		)
	);
}

/**
 * Is WSJT-X sending right now (data in the last 3 minutes)?
 */
function hamshack_wsjtx_active( $state = null ) {
	$state = $state ? $state : hamshack_wsjtx_state();
	return $state['ts'] && ( time() - (int) $state['ts'] ) < 3 * MINUTE_IN_SECONDS;
}

/**
 * Pull the sender, grid and kind out of an FT8/FT4 message.
 *
 * "CQ DX SP5ABC KO02" → [ call SP5ABC, grid KO02, cq true ]
 * "G4XYZ DL1ABC JO62" → [ call DL1ABC, to G4XYZ, grid JO62 ]
 * "G4XYZ DL1ABC -12"  → [ call DL1ABC, to G4XYZ, report -12 ]
 */
function hamshack_ft_parse( $msg ) {
	$t   = preg_split( '/\s+/', strtoupper( trim( (string) $msg ) ) );
	$out = array( 'call' => '', 'to' => '', 'grid' => '', 'cq' => false );
	if ( ! $t || '' === $t[0] ) {
		return $out;
	}
	$is_call = function ( $s ) {
		return (bool) preg_match( '#^<?[A-Z0-9/]*\d[A-Z0-9/]*>?$#', $s ) && preg_match( '/[A-Z]/', $s ) && strlen( trim( $s, '<>' ) ) >= 3;
	};
	$is_grid = function ( $s ) {
		return (bool) preg_match( '/^[A-R]{2}\d{2}$/', $s ) && 'RR73' !== $s;
	};
	if ( 'CQ' === $t[0] || 'QRZ' === $t[0] ) {
		$out['cq'] = true;
		$i         = 1;
		if ( isset( $t[1] ) && ! $is_call( $t[1] ) && isset( $t[2] ) ) {
			$i = 2; // CQ DX / CQ POTA / CQ EU …
		}
		$out['call'] = isset( $t[ $i ] ) ? trim( $t[ $i ], '<>' ) : '';
		if ( isset( $t[ $i + 1 ] ) && $is_grid( $t[ $i + 1 ] ) ) {
			$out['grid'] = $t[ $i + 1 ];
		}
	} elseif ( count( $t ) >= 2 ) {
		$out['to']   = trim( $t[0], '<>' );
		$out['call'] = trim( $t[1], '<>' );
		if ( isset( $t[2] ) && $is_grid( $t[2] ) ) {
			$out['grid'] = $t[2];
		}
	}
	if ( ! $is_call( $out['call'] ) ) {
		$out['call'] = '';
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'hamshack/v1',
		'/wsjtx',
		array(
			'methods'             => 'POST',
			'callback'            => 'hamshack_wsjtx_receive',
			'permission_callback' => 'hamshack_rig_auth',
		)
	);
	register_rest_route(
		'hamshack/v1',
		'/live',
		array(
			'methods'             => 'GET',
			'callback'            => 'hamshack_live_public',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'hamshack/v1',
		'/dx',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				$data = hamshack_dx_spots();
				if ( function_exists( 'hamshack_dx_mark' ) && ! empty( $data['spots'] ) ) {
					$data['spots'] = hamshack_dx_mark( $data['spots'] );
				}
				$res = rest_ensure_response( $data );
				$res->header( 'Cache-Control', 'public, max-age=60' );
				return $res;
			},
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'hamshack/v1',
		'/psk',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				$res = rest_ensure_response( hamshack_psk_data() );
				$res->header( 'Cache-Control', 'public, max-age=300' );
				return $res;
			},
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * Receive a batch from Rig Link.
 */
function hamshack_wsjtx_receive( WP_REST_Request $req ) {
	$rig   = hamshack_rig_state();
	$state = hamshack_wsjtx_state();
	$now   = time();
	if ( $req->get_param( 'closed' ) ) {
		$state['ts']     = 0;
		$state['status'] = array();
		update_option( 'hamshack_wsjtx', $state, false );
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$st = (array) $req->get_param( 'status' );
	if ( $st ) {
		$dial            = isset( $st['dial'] ) ? (int) $st['dial'] : 0;
		$state['status'] = array(
			'dial'     => $dial,
			'band'     => $dial ? hamshack_band_from_freq( $dial / 1000000 ) : '',
			'mode'     => isset( $st['mode'] ) ? substr( sanitize_text_field( $st['mode'] ), 0, 10 ) : '',
			'dx'       => isset( $st['dx'] ) ? substr( strtoupper( sanitize_text_field( $st['dx'] ) ), 0, 16 ) : '',
			'tx'       => ! empty( $st['tx'] ),
			'decoding' => ! empty( $st['decoding'] ),
		);
	}
	$decodes = (array) $req->get_param( 'decodes' );
	$home    = hamshack_home_latlon();
	$fresh   = array();
	foreach ( array_slice( $decodes, -60 ) as $d ) {
		if ( ! is_array( $d ) || empty( $d['msg'] ) ) {
			continue;
		}
		$msg  = substr( preg_replace( '/[^A-Za-z0-9 \/<>+\-.?]/', '', (string) $d['msg'] ), 0, 40 );
		$p    = hamshack_ft_parse( $msg );
		$row  = array(
			'ts'   => $now,
			't'    => isset( $d['t'] ) ? preg_replace( '/\D/', '', (string) $d['t'] ) : gmdate( 'His' ),
			'snr'  => isset( $d['snr'] ) ? max( -40, min( 50, (int) $d['snr'] ) ) : 0,
			'dt'   => isset( $d['dt'] ) ? round( (float) $d['dt'], 1 ) : 0,
			'df'   => isset( $d['df'] ) ? max( 0, min( 5000, (int) $d['df'] ) ) : 0,
			'mode' => isset( $d['mode'] ) ? substr( sanitize_text_field( $d['mode'] ), 0, 6 ) : '',
			'msg'  => $msg,
			'call' => $p['call'],
			'cq'   => $p['cq'],
			'me'   => $p['to'] && strtoupper( hamshack_opt( 'callsign' ) ) === $p['to'],
			'grid' => $p['grid'],
		);
		$ll = $p['grid'] ? hamshack_grid_to_latlon( $p['grid'] ) : null;
		if ( ! $ll && $p['call'] ) {
			$e  = hamshack_cty_lookup( $p['call'] );
			$ll = $e ? array( $e['lat'], $e['lon'] ) : null;
			$row['approx'] = 1;
		}
		if ( $ll ) {
			$row['lat'] = $ll[0];
			$row['lon'] = $ll[1];
			$row['km']  = (int) round( hamshack_distance_km( $home[0], $home[1], $ll[0], $ll[1] ) );
		}
		if ( $p['call'] ) {
			$e             = hamshack_cty_lookup( $p['call'] );
			$row['country'] = $e ? $e['name'] : '';
		}
		$fresh[] = $row;
	}
	$keep             = array_filter( array_merge( (array) $state['decodes'], $fresh ), function ( $d ) use ( $now ) {
		return isset( $d['ts'] ) && $now - $d['ts'] < 15 * MINUTE_IN_SECONDS;
	} );
	$state['decodes'] = array_slice( array_values( $keep ), -150 );
	$state['ts']      = $now;

	// QSOs logged in WSJT-X go straight into the logbook.
	$logged = 0;
	$adif   = (string) $req->get_param( 'logged' );
	if ( $adif && ! empty( $rig['wsjtx_autolog'] ) ) {
		foreach ( hamshack_parse_adif( "<eoh>\n" . $adif ) as $rec ) {
			$r = hamshack_qrz_upsert( $rec );
			if ( in_array( $r, array( 'new', 'updated' ), true ) ) {
				$logged++;
			}
		}
		$state['logged'] = $now;
	}
	update_option( 'hamshack_wsjtx', $state, false );
	return rest_ensure_response( array( 'ok' => true, 'decodes' => count( $fresh ), 'logged' => $logged ) );
}

/**
 * Public WSJT-X activity.
 */
function hamshack_live_public() {
	$rig    = hamshack_rig_state();
	$state  = hamshack_wsjtx_state();
	$active = ! empty( $rig['wsjtx_public'] ) && hamshack_wsjtx_active( $state );
	$out    = array(
		'active'  => $active,
		'age'     => $state['ts'] ? time() - (int) $state['ts'] : null,
		'status'  => $active ? $state['status'] : (object) array(),
		'decodes' => array(),
	);
	if ( ! empty( $rig['wsjtx_public'] ) ) {
		foreach ( array_reverse( (array) $state['decodes'] ) as $d ) {
			unset( $d['ts'] );
			$out['decodes'][] = $d;
		}
	}
	$res = rest_ensure_response( $out );
	$res->header( 'Cache-Control', 'no-store, max-age=0' );
	$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
	return $res;
}

/* --------------------------------------------------------------------------
 * DX cluster (HamQTH feed, DX Summit as fallback)
 * ------------------------------------------------------------------------ */

/**
 * Latest spots, normalised.
 */
function hamshack_dx_spots() {
	$cached = get_transient( 'hamshack_dx_spots' );
	if ( false !== $cached ) {
		return $cached;
	}
	$spots = array();
	$src   = 'HamQTH';
	$res   = wp_remote_get( 'https://www.hamqth.com/dxc_csv.php?limit=80', array( 'timeout' => 10, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		foreach ( preg_split( '/\r\n|\n/', wp_remote_retrieve_body( $res ) ) as $line ) {
			$f = explode( '^', $line );
			if ( count( $f ) < 10 ) {
				continue;
			}
			// spotter^freq kHz^DX^comment^HHMM YYYY-MM-DD^lotw^eqsl^cont^band^country^dxcc.
			$spots[] = array(
				'dx'      => strtoupper( sanitize_text_field( $f[2] ) ),
				'khz'     => (float) $f[1],
				'de'      => strtoupper( sanitize_text_field( $f[0] ) ),
				'info'    => sanitize_text_field( $f[3] ),
				'time'    => substr( $f[4], 0, 2 ) . ':' . substr( $f[4], 2, 2 ),
				'date'    => substr( $f[4], 5, 10 ),
				'cont'    => sanitize_text_field( $f[7] ),
				'band'    => strtolower( sanitize_text_field( $f[8] ) ),
				'country' => sanitize_text_field( $f[9] ),
			);
		}
	}
	if ( ! $spots ) {
		$src = 'DX Summit';
		$res = wp_remote_get( 'https://www.dxsummit.fi/api/v1/spots?limit=80', array( 'timeout' => 10, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
		$arr = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		foreach ( (array) $arr as $s ) {
			if ( empty( $s['dx_call'] ) ) {
				continue;
			}
			$khz     = (float) $s['frequency'];
			$spots[] = array(
				'dx'      => strtoupper( sanitize_text_field( $s['dx_call'] ) ),
				'khz'     => $khz,
				'de'      => strtoupper( sanitize_text_field( preg_replace( '/-.*$/', '', $s['de_call'] ) ) ),
				'info'    => sanitize_text_field( (string) $s['info'] ),
				'time'    => substr( (string) $s['time'], 11, 5 ),
				'date'    => substr( (string) $s['time'], 0, 10 ),
				'cont'    => '',
				'band'    => hamshack_band_from_freq( $khz / 1000 ),
				'country' => sanitize_text_field( (string) $s['dx_country'] ),
			);
		}
	}
	$me   = strtoupper( hamshack_opt( 'callsign' ) );
	$home = hamshack_home_latlon();
	foreach ( $spots as &$s ) {
		$s['band'] = str_replace( 'cm', 'cm', $s['band'] );
		$s['me']   = ( hamshack_call_base( $s['dx'] ) === $me );
		$e         = hamshack_cty_lookup( $s['dx'] );
		if ( $e ) {
			$s['km'] = (int) round( hamshack_distance_km( $home[0], $home[1], $e['lat'], $e['lon'] ) );
			if ( ! $s['cont'] ) {
				$s['cont'] = $e['cont'];
			}
			if ( ! $s['country'] ) {
				$s['country'] = $e['name'];
			}
		}
	}
	unset( $s );
	$data = array( 'spots' => $spots, 'source' => $spots ? $src : '', 'updated' => time() );
	set_transient( 'hamshack_dx_spots', $data, $spots ? 90 : 30 );
	if ( $spots ) {
		// Remember the last time somebody spotted me.
		foreach ( $spots as $s ) {
			if ( $s['me'] ) {
				$hist = (array) get_option( 'hamshack_spotted', array() );
				$key  = $s['date'] . $s['time'] . $s['de'];
				if ( ! isset( $hist[ $key ] ) && function_exists( 'hamshack_notify_throttled' ) && ! get_transient( 'hamshack_nt_spot_any' ) ) {
					set_transient( 'hamshack_nt_spot_any', 1, 15 * MINUTE_IN_SECONDS ); // At most one "spotted" message per 15 minutes.
					hamshack_notify_throttled( 'spot' . $key, DAY_IN_SECONDS, 'spotted', sprintf( __( '%1$s spotted by %2$s', 'hamshack' ), $s['dx'], $s['de'] ), trim( number_format( $s['khz'], 1, '.', '' ) . ' kHz ' . $s['info'] ), home_url( '/' ) );
				}
				$hist[ $key ] = $s;
				krsort( $hist );
				update_option( 'hamshack_spotted', array_slice( $hist, 0, 20, true ), false );
			}
		}
	}
	return $data;
}

/* --------------------------------------------------------------------------
 * PSK Reporter
 * ------------------------------------------------------------------------ */

/**
 * Who heard me in the last 6 hours (cached 10 minutes – PSK Reporter asks for gentle polling).
 */
function hamshack_psk_data() {
	$cached = get_transient( 'hamshack_psk' );
	if ( false !== $cached ) {
		return $cached;
	}
	$call = apply_filters( 'hamshack_psk_callsign', strtoupper( hamshack_opt( 'callsign' ) ) );
	$url  = add_query_arg(
		array(
			'senderCallsign'   => rawurlencode( $call ),
			'flowStartSeconds' => -21600,
			'rronly'           => 1,
		),
		'https://retrieve.pskreporter.info/query'
	);
	$res  = wp_remote_get( $url, array( 'timeout' => 20, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
	$data = array( 'spots' => array(), 'updated' => time(), 'message' => '' );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		$data['message'] = __( 'PSK Reporter is not answering right now.', 'hamshack' );
		set_transient( 'hamshack_psk', $data, 5 * MINUTE_IN_SECONDS );
		return $data;
	}
	$home = hamshack_home_latlon();
	$best = array();
	if ( preg_match_all( '/<receptionReport\s([^>]*)\/?>/i', wp_remote_retrieve_body( $res ), $m ) ) {
		foreach ( $m[1] as $attrs ) {
			preg_match_all( '/(\w+)="([^"]*)"/', $attrs, $a );
			$r    = array_combine( $a[1], $a[2] );
			$rx   = isset( $r['receiverCallsign'] ) ? strtoupper( sanitize_text_field( $r['receiverCallsign'] ) ) : '';
			$grid = isset( $r['receiverLocator'] ) ? sanitize_text_field( $r['receiverLocator'] ) : '';
			$ll   = hamshack_grid_to_latlon( substr( $grid, 0, 6 ) );
			if ( ! $rx || ! $ll ) {
				continue;
			}
			$hz   = isset( $r['frequency'] ) ? (int) $r['frequency'] : 0;
			$row  = array(
				'call' => $rx,
				'grid' => substr( $grid, 0, 6 ),
				'band' => $hz ? hamshack_band_from_freq( $hz / 1000000 ) : '',
				'mode' => isset( $r['mode'] ) ? sanitize_text_field( $r['mode'] ) : '',
				'snr'  => isset( $r['sNR'] ) ? (int) $r['sNR'] : null,
				'time' => isset( $r['flowStartSeconds'] ) ? gmdate( 'H:i', (int) $r['flowStartSeconds'] ) : '',
				'ts'   => isset( $r['flowStartSeconds'] ) ? (int) $r['flowStartSeconds'] : 0,
				'km'   => (int) round( hamshack_distance_km( $home[0], $home[1], $ll[0], $ll[1] ) ),
			);
			$key  = $rx . '|' . $row['band'];
			if ( ! isset( $best[ $key ] ) || $row['ts'] > $best[ $key ]['ts'] ) {
				$best[ $key ] = $row;
			}
		}
	}
	$data['spots'] = array_values( $best );
	set_transient( 'hamshack_psk', $data, 10 * MINUTE_IN_SECONDS );
	return $data;
}

/* --------------------------------------------------------------------------
 * Front-end widgets (shortcodes)
 * ------------------------------------------------------------------------ */

function hamshack_enqueue_live() {
	hamshack_enqueue_map();
	wp_enqueue_script( 'hamshack-live', HAMSHACK_URI . '/assets/js/live.js', array( 'hamshack-map' ), HAMSHACK_VERSION, true );
	wp_localize_script(
		'hamshack-live',
		'M6LIVE',
		array(
			'rest'  => esc_url_raw( rest_url( 'hamshack/v1/' ) ),
			'call'  => strtoupper( hamshack_opt( 'callsign' ) ),
			'bands' => array_values( (array) hamshack_opt( 'bands' ) ),
			'i18n'  => array(
				'idle'     => __( 'WSJT-X is not running at the moment – decodes appear here live whenever I am on FT8/FT4.', 'hamshack' ),
				'live'     => __( 'Live from the shack', 'hamshack' ),
				'tx'       => __( 'transmitting', 'hamshack' ),
				'rx'       => __( 'listening', 'hamshack' ),
				'calling'  => __( 'calling me!', 'hamshack' ),
				'none'     => __( 'No spots for these bands right now.', 'hamshack' ),
				'spotted'  => __( 'That is me – thanks for the spot!', 'hamshack' ),
				'ago'      => __( 'updated %s s ago', 'hamshack' ),
				'heard'    => __( 'stations heard', 'hamshack' ),
				'all'      => __( 'All', 'hamshack' ),
				'my'       => __( 'My bands', 'hamshack' ),
				'newone'   => __( 'NEW ONE', 'hamshack' ),
				'newband'  => __( 'new band', 'hamshack' ),
				'mw'       => __( 'Most Wanted #%s (Club Log)', 'hamshack' ),
			),
		)
	);
}

/**
 * [hamshack_greyline] – day/night map with UTC & local clocks and sunrise/sunset.
 */
add_shortcode( 'hamshack_greyline', function () {
	hamshack_enqueue_map();
	ob_start();
	?>
	<div class="hs-app greyline-app" data-hs-greyline>
		<div data-hs-map></div>
		<div class="clock-row">
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'UTC', 'hamshack' ); ?></span><span class="val big" data-clock-utc>--:--</span></div>
			<div class="clock-box"><span class="lbl"><?php echo esc_html( sprintf( __( 'Local – %s', 'hamshack' ), hamshack_opt( 'callsign' ) ) ); ?></span><span class="val" data-clock-local>--:--</span></div>
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'Sunrise', 'hamshack' ); ?></span><span class="val" data-sunrise>--</span></div>
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'Sunset', 'hamshack' ); ?></span><span class="val" data-sunset>--</span></div>
		</div>
		<p class="app-note"><?php esc_html_e( 'The soft band between day and night is the greyline – low-band DX (160–40 m) often opens along it around sunrise and sunset.', 'hamshack' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_ft8] – live WSJT-X decodes.
 */
add_shortcode( 'hamshack_ft8', function ( $atts ) {
	$atts = shortcode_atts( array( 'map' => '1', 'rows' => '18' ), $atts, 'hamshack_ft8' );
	hamshack_enqueue_live();
	ob_start();
	?>
	<div class="hs-app ft8-app" data-hs-ft8 data-rows="<?php echo (int) $atts['rows']; ?>">
		<div class="ft8-head">
			<span class="ldot" data-dot></span>
			<strong data-title><?php esc_html_e( 'FT8 / FT4 live', 'hamshack' ); ?></strong>
			<span class="mono muted" data-meta></span>
		</div>
		<?php if ( '1' === $atts['map'] ) : ?>
			<div data-hs-map class="ft8-map"></div>
		<?php endif; ?>
		<div class="table-scroll">
			<table class="data-table ft8-table">
				<thead><tr><th>UTC</th><th>dB</th><th>DT</th><th>Hz</th><th><?php esc_html_e( 'Message', 'hamshack' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Country', 'hamshack' ); ?></th><th class="hide-sm">km</th></tr></thead>
				<tbody data-body><tr><td colspan="7" class="muted"><?php esc_html_e( 'Loading…', 'hamshack' ); ?></td></tr></tbody>
			</table>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_dx_cluster limit="25"]
 */
add_shortcode( 'hamshack_dx_cluster', function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => '25' ), $atts, 'hamshack_dx_cluster' );
	hamshack_enqueue_live();
	$spotted = array_slice( (array) get_option( 'hamshack_spotted', array() ), 0, 5 );
	ob_start();
	?>
	<div class="hs-app dx-app" data-hs-dx data-limit="<?php echo (int) $atts['limit']; ?>">
		<div class="app-toolbar">
			<div class="chips" data-bands></div>
			<label class="map-check"><input type="checkbox" data-cw> CW</label>
			<label class="map-check"><input type="checkbox" data-ssb> SSB</label>
			<label class="map-check"><input type="checkbox" data-digi> <?php esc_html_e( 'Digital', 'hamshack' ); ?></label>
			<label class="map-check"><input type="checkbox" data-need> <?php esc_html_e( 'Only new for me', 'hamshack' ); ?></label>
			<span class="mono muted small" data-meta></span>
		</div>
		<div class="table-scroll">
			<table class="data-table dx-table">
				<thead><tr><th>UTC</th><th>kHz</th><th>DX</th><th class="hide-sm"><?php esc_html_e( 'Country', 'hamshack' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Comment', 'hamshack' ); ?></th><th><?php esc_html_e( 'Spotter', 'hamshack' ); ?></th></tr></thead>
				<tbody data-body><tr><td colspan="6" class="muted"><?php esc_html_e( 'Loading…', 'hamshack' ); ?></td></tr></tbody>
			</table>
		</div>
		<?php if ( $spotted ) : ?>
			<div class="spotted-me">
				<h3><?php printf( esc_html__( 'Recent spots of %s', 'hamshack' ), esc_html( hamshack_opt( 'callsign' ) ) ); ?></h3>
				<ul>
				<?php foreach ( $spotted as $s ) : ?>
					<li class="mono"><?php echo esc_html( $s['date'] . ' ' . $s['time'] . 'Z · ' . number_format( $s['khz'], 1, '.', '' ) . ' kHz · de ' . $s['de'] . ( $s['info'] ? ' · ' . $s['info'] : '' ) ); ?></li>
				<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<p class="app-note"><?php printf( esc_html__( 'Spots from the worldwide DX cluster via %s, refreshed every minute.', 'hamshack' ), '<a href="https://www.hamqth.com/dxcluster.php" target="_blank" rel="noopener">HamQTH</a>' ); // phpcs:ignore ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_psk] – where my signal is heard (PSK Reporter).
 */
add_shortcode( 'hamshack_psk', function () {
	hamshack_enqueue_map();
	$call = rawurlencode( strtoupper( hamshack_opt( 'callsign' ) ) );
	ob_start();
	?>
	<div class="hs-app psk-app" data-hs-psk>
		<div data-hs-map></div>
		<div class="map-foot"><span class="map-summary mono" data-summary></span><a class="small" href="https://pskreporter.info/pskmap.html?preset&amp;callsign=<?php echo esc_attr( $call ); ?>&amp;txrx=tx&amp;timerange=86400" target="_blank" rel="noopener"><?php esc_html_e( 'Full map on PSK Reporter ↗', 'hamshack' ); ?></a></div>
		<ol class="dx-list" data-psk-list></ol>
		<p class="app-note"><?php esc_html_e( 'Stations that decoded my digital signals (FT8, FT4, WSPR, PSK…) in the last 6 hours, reported automatically to PSK Reporter by Philip Gladstone.', 'hamshack' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_live] – the whole "Live" page.
 */
add_shortcode( 'hamshack_live', function () {
	ob_start();
	?>
	<div class="live-page">
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'live', 22 ); ?> <?php esc_html_e( 'On the air now', 'hamshack' ); ?></h2>
			<?php get_template_part( 'template-parts/status-card' ); ?>
			<?php echo do_shortcode( '[hamshack_ft8]' ); // phpcs:ignore ?>
		</section>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'globe', 22 ); ?> <?php esc_html_e( 'Day, night and the greyline', 'hamshack' ); ?></h2>
			<?php echo do_shortcode( '[hamshack_greyline]' ); // phpcs:ignore ?>
		</section>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'signal', 22 ); ?> <?php esc_html_e( 'Who hears me (PSK Reporter)', 'hamshack' ); ?></h2>
			<?php echo do_shortcode( '[hamshack_psk]' ); // phpcs:ignore ?>
		</section>
		<?php $aprs = do_shortcode( '[hamshack_aprs]' ); ?>
		<?php if ( $aprs ) : ?>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'pin', 22 ); ?> <?php esc_html_e( 'Where am I (APRS)', 'hamshack' ); ?></h2>
			<?php echo $aprs; // phpcs:ignore ?>
		</section>
		<?php endif; ?>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'antenna', 22 ); ?> <?php esc_html_e( 'DX cluster', 'hamshack' ); ?></h2>
			<?php echo do_shortcode( '[hamshack_dx_cluster]' ); // phpcs:ignore ?>
		</section>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'sun', 22 ); ?> <?php esc_html_e( 'Band conditions', 'hamshack' ); ?></h2>
			<?php get_template_part( 'template-parts/solar', null, array( 'banner' => hamshack_opt( 'solar_home' ) ) ); ?>
		</section>
		<?php if ( hamshack_int( 'sdr_url' ) ) : ?>
		<section class="live-sec">
			<h2 class="app-h2"><?php hamshack_the_icon( 'radio', 22 ); ?> <?php esc_html_e( 'Listen to the bands', 'hamshack' ); ?></h2>
			<?php echo do_shortcode( '[hamshack_sdr]' ); // phpcs:ignore ?>
		</section>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );
