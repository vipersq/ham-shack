<?php
/**
 * Club Log: Most Wanted ranks, "needed" marks on DX cluster spots and
 * optional real-time upload of new QSOs.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DXCC number => Most Wanted rank (1 = most wanted). Public list, cached for a week.
 */
function hamshack_most_wanted() {
	$cached = get_transient( 'hamshack_mostwanted' );
	if ( false !== $cached ) {
		return $cached;
	}
	$res = wp_remote_get( 'https://clublog.org/mostwanted.php?api=1', array( 'timeout' => 15, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
	$raw = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
	$out = array();
	foreach ( (array) $raw as $rank => $dxcc ) {
		$out[ (int) $dxcc ] = (int) $rank;
	}
	if ( ! $out ) {
		$out = (array) get_option( 'hamshack_mostwanted_last', array() );
		set_transient( 'hamshack_mostwanted', $out, HOUR_IN_SECONDS );
		return $out;
	}
	update_option( 'hamshack_mostwanted_last', $out, false );
	set_transient( 'hamshack_mostwanted', $out, WEEK_IN_SECONDS );
	return $out;
}

/**
 * Mark DX spots: new DXCC, new band, Most Wanted rank.
 *
 * @param array $spots Spots from hamshack_dx_spots().
 */
function hamshack_dx_mark( array $spots ) {
	$aw = hamshack_awards_data();
	$mw = hamshack_most_wanted();
	foreach ( $spots as &$s ) {
		$e = hamshack_cty_lookup( $s['dx'] );
		if ( ! $e ) {
			continue;
		}
		$dx        = $e['dxcc'];
		$s['dxcc'] = $dx;
		if ( ! isset( $aw['dxcc'][ $dx ] ) ) {
			$s['need'] = 'dxcc';
		} elseif ( $s['band'] && ! isset( $aw['dxcc'][ $dx ]['bands'][ $s['band'] ] ) ) {
			$s['need'] = 'band';
		}
		if ( isset( $mw[ $dx ] ) && $mw[ $dx ] <= 100 ) {
			$s['mw'] = $mw[ $dx ];
		}
	}
	unset( $s );
	return $spots;
}

/* --------------------------------------------------------------------------
 * Real-time upload
 * ------------------------------------------------------------------------ */

add_action( 'hamshack_qso_saved', function ( $post_id ) {
	if ( ! hamshack_int( 'clublog_upload' ) || ! hamshack_int( 'clublog_email' ) || ! hamshack_int( 'clublog_password' ) || ! hamshack_int( 'clublog_api' ) ) {
		return;
	}
	if ( get_post_meta( $post_id, '_q_clublog', true ) || get_transient( 'hamshack_clublog_block' ) ) {
		return; // Already uploaded, or the login failed recently.
	}
	$date = get_post_meta( $post_id, '_q_date', true );
	if ( ! $date || strtotime( $date . ' UTC' ) < time() - 7 * DAY_IN_SECONDS ) {
		return; // Only new QSOs – upload your older log once on clublog.org.
	}
	// Do it after the request so saving stays fast.
	if ( ! wp_next_scheduled( 'hamshack_clublog_push', array( (int) $post_id ) ) ) {
		wp_schedule_single_event( time() + 20, 'hamshack_clublog_push', array( (int) $post_id ) );
	}
}, 30 );

add_action( 'hamshack_clublog_push', 'hamshack_clublog_push' );
function hamshack_clublog_push( $post_id ) {
	if ( 'hamshack_qso' !== get_post_type( $post_id ) || get_post_meta( $post_id, '_q_clublog', true ) ) {
		return;
	}
	$res  = wp_remote_post(
		'https://clublog.org/realtime.php',
		array(
			'timeout'    => 20,
			'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION,
			'body'       => array(
				'email'    => hamshack_int( 'clublog_email' ),
				'password' => hamshack_int( 'clublog_password' ),
				'callsign' => strtoupper( hamshack_opt( 'callsign' ) ),
				'api'      => hamshack_int( 'clublog_api' ),
				'adif'     => hamshack_adif_record( $post_id ),
			),
		)
	);
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$msg  = is_wp_error( $res ) ? $res->get_error_message() : trim( wp_strip_all_tags( wp_remote_retrieve_body( $res ) ) );
	update_option( 'hamshack_clublog_last', array( 'ts' => time(), 'code' => $code, 'msg' => substr( $msg, 0, 200 ), 'call' => get_post_meta( $post_id, '_q_call', true ) ), false );
	if ( 200 === $code || ( 400 === $code && false !== stripos( $msg, 'dupe' ) ) ) {
		update_post_meta( $post_id, '_q_clublog', time() );
	} elseif ( in_array( $code, array( 401, 403 ), true ) ) {
		// Wrong e-mail / app password / API key: stop for a day (Club Log blocks addresses that keep failing).
		set_transient( 'hamshack_clublog_block', 1, DAY_IN_SECONDS );
	}
}

/* Status line on the Integrations page. */
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( (string) $screen->id, 'hamshack-int' ) ) {
		return;
	}
	$l = get_option( 'hamshack_clublog_last' );
	if ( $l ) {
		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			200 === (int) $l['code'] ? 'notice-success' : 'notice-warning',
			esc_html( sprintf( __( 'Club Log: last upload (%1$s, %2$s ago): %3$s', 'hamshack' ), $l['call'], human_time_diff( $l['ts'] ), 200 === (int) $l['code'] ? 'OK' : 'HTTP ' . $l['code'] . ' – ' . $l['msg'] ) )
		);
	}
} );
