<?php
/**
 * Notifications to Discord, Telegram and a generic JSON web hook.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send a notification to every configured channel.
 *
 * @param string $event  Event key (see hamshack_notify_event_labels()).
 * @param string $title  Short title.
 * @param string $text   Message body (plain text).
 * @param string $url    Link.
 * @param bool   $force  Ignore the event switches (test messages).
 * @return string[] Result per channel.
 */
function hamshack_notify( $event, $title, $text, $url = '', $force = false ) {
	if ( ! $force && ! in_array( $event, (array) hamshack_int( 'notify_events' ), true ) ) {
		return array();
	}
	$out  = array();
	$call = hamshack_opt( 'callsign' );
	$args = array( 'timeout' => 8, 'headers' => array( 'Content-Type' => 'application/json' ), 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION );
	$ok   = function ( $res ) {
		return ! is_wp_error( $res ) && wp_remote_retrieve_response_code( $res ) >= 200 && wp_remote_retrieve_response_code( $res ) < 300;
	};

	$discord = (string) hamshack_int( 'notify_discord' );
	if ( $discord && preg_match( '#^https://(discord|discordapp)\.com/api/webhooks/#', $discord ) ) {
		$body = array(
			'username'         => $call,
			'content'          => mb_substr( '**' . $title . "**\n" . $text . ( $url ? "\n" . $url : '' ), 0, 1900 ),
			'allowed_mentions' => array( 'parse' => array() ), // Text from DX spots must never ping @everyone.
		);
		$res   = wp_remote_post( $discord, array_merge( $args, array( 'body' => wp_json_encode( $body ) ) ) );
		$out[] = 'Discord: ' . ( $ok( $res ) ? 'OK' : hamshack_notify_err( $res ) );
	}

	$tg_token = (string) hamshack_int( 'notify_telegram_token' );
	$tg_chat  = (string) hamshack_int( 'notify_telegram_chat' );
	if ( $tg_token && $tg_chat && preg_match( '/^\d+:[A-Za-z0-9_\-]+$/', $tg_token ) ) {
		$body = array(
			'chat_id'    => $tg_chat,
			'text'       => $title . "\n" . $text . ( $url ? "\n" . $url : '' ),
		);
		$res   = wp_remote_post( 'https://api.telegram.org/bot' . $tg_token . '/sendMessage', array_merge( $args, array( 'body' => wp_json_encode( $body ) ) ) );
		$out[] = 'Telegram: ' . ( $ok( $res ) ? 'OK' : hamshack_notify_err( $res ) );
	}

	$hook = (string) hamshack_int( 'notify_webhook' );
	if ( $hook && 0 === strpos( $hook, 'https://' ) ) {
		$body  = array( 'event' => $event, 'callsign' => $call, 'title' => $title, 'text' => $text, 'url' => $url, 'site' => home_url( '/' ), 'time' => gmdate( 'c' ) );
		$res   = wp_safe_remote_post( $hook, array_merge( $args, array( 'body' => wp_json_encode( $body ) ) ) );
		$out[] = 'Web hook: ' . ( $ok( $res ) ? 'OK' : hamshack_notify_err( $res ) );
	}
	return $out;
}

function hamshack_notify_err( $res ) {
	return is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res );
}

/**
 * Notify at most once per $seconds for the same key.
 */
function hamshack_notify_throttled( $key, $seconds, $event, $title, $text, $url = '' ) {
	$t = 'hamshack_nt_' . md5( $key );
	if ( get_transient( $t ) ) {
		return;
	}
	set_transient( $t, 1, $seconds );
	hamshack_notify_later( $event, $title, $text, $url );
}

/**
 * Send in the background (WP-Cron), so a visitor's page never waits for Discord or Telegram.
 */
function hamshack_notify_later( $event, $title, $text, $url = '' ) {
	if ( ! in_array( $event, (array) hamshack_int( 'notify_events' ), true ) ) {
		return;
	}
	wp_schedule_single_event( time(), 'hamshack_notify_async', array( $event, $title, $text, $url, wp_generate_password( 6, false ) ) );
}
add_action( 'hamshack_notify_async', function ( $event, $title, $text, $url ) {
	hamshack_notify( $event, $title, $text, $url );
}, 10, 4 );

/* ON AIR / portable. */
add_action( 'update_option_hamshack_station', function ( $old, $new ) {
	$was = isset( $old['status'] ) ? $old['status'] : 'off';
	$now = isset( $new['status'] ) ? $new['status'] : 'off';
	if ( in_array( $now, array( 'on', 'portable' ), true ) && ! in_array( $was, array( 'on', 'portable' ), true ) ) {
		$freq = isset( $new['status_freq'] ) && $new['status_freq'] ? $new['status_freq'] . ' MHz ' : '';
		$mode = isset( $new['status_mode'] ) ? $new['status_mode'] : '';
		$note = isset( $new['status_note'] ) ? $new['status_note'] : '';
		$call = isset( $new['callsign'] ) ? $new['callsign'] : hamshack_opt( 'callsign' );
		$live = get_page_by_path( 'live' );
		hamshack_notify_throttled(
			'onair',
			30 * MINUTE_IN_SECONDS,
			'on_air',
			sprintf( 'portable' === $now ? __( '%s is portable and on the air', 'hamshack' ) : __( '%s is on the air', 'hamshack' ), $call ),
			trim( $freq . $mode . ( $note ? ' – ' . $note : '' ) ),
			$live ? get_permalink( $live ) : home_url( '/' )
		);
	}
}, 20, 2 );

/* New blog post. */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type || strtotime( $post->post_date_gmt . ' UTC' ) < time() - DAY_IN_SECONDS ) {
		return;
	}
	$text = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 );
	hamshack_notify_later( 'new_post', html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ), $text, get_permalink( $post ) );
}, 20, 3 );

/* New DXCC entity in the log (only for recent QSOs, so imports and back-fills stay quiet). */
add_action( 'hamshack_qso_saved', function ( $post_id ) {
	$dxcc = (int) get_post_meta( $post_id, '_q_dxcc', true );
	$date = get_post_meta( $post_id, '_q_date', true );
	if ( ! $dxcc || ! $date || strtotime( $date . ' UTC' ) < time() - 3 * DAY_IN_SECONDS ) {
		return;
	}
	global $wpdb;
	$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish' WHERE pm.meta_key = '_q_dxcc' AND pm.meta_value = %s AND pm.post_id <> %d", (string) $dxcc, $post_id ) );
	if ( $other ) {
		return;
	}
	$e     = hamshack_cty_entity( $dxcc );
	$call  = get_post_meta( $post_id, '_q_call', true );
	$map   = get_page_by_path( 'map' );
	hamshack_notify_throttled(
		'dxcc' . $dxcc,
		DAY_IN_SECONDS,
		'new_dxcc',
		sprintf( __( 'New one! %s', 'hamshack' ), $e ? $e['name'] : $call ),
		sprintf( __( 'First QSO with %1$s: %2$s on %3$s %4$s.', 'hamshack' ), $e ? $e['name'] : '?', $call, get_post_meta( $post_id, '_q_band', true ), get_post_meta( $post_id, '_q_mode', true ) ),
		$map ? get_permalink( $map ) : home_url( '/' )
	);
} );
