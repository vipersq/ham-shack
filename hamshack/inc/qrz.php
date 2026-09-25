<?php
/**
 * QRZ.com Logbook sync – pulls QSOs from the QRZ Logbook API into the site logbook.
 *
 * API docs: https://www.qrz.com/docs/logbook/QRZLogbookAPI.html
 * Needs a QRZ subscription and the logbook API key (QRZ.com → Logbook → Settings).
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAMSHACK_QRZ_ENDPOINT', 'https://logbook.qrz.com/api' );
define( 'HAMSHACK_QRZ_PAGE', 250 );

/**
 * Sync settings + state (stored separately, not autoloaded).
 */
function hamshack_qrz_state() {
	return wp_parse_args(
		(array) get_option( 'hamshack_qrz', array() ),
		array(
			'key'          => '',
			'enabled'      => 0,
			'interval'     => 'hourly',
			'last_logid'   => 0,
			'last_sync'    => 0,
			'last_status'  => '',
			'last_message' => '',
			'last_new'     => 0,
			'last_updated' => 0,
			'book'         => '',
		)
	);
}

function hamshack_qrz_update_state( array $changes ) {
	$state = array_merge( hamshack_qrz_state(), $changes );
	if ( false === get_option( 'hamshack_qrz' ) ) {
		add_option( 'hamshack_qrz', $state, '', 'no' );
	} else {
		update_option( 'hamshack_qrz', $state, false );
	}
	return $state;
}

/**
 * Masked key for display.
 */
function hamshack_qrz_masked_key( $key ) {
	$key = (string) $key;
	return strlen( $key ) > 4 ? str_repeat( '•', 12 ) . substr( $key, -4 ) : '';
}

/* --------------------------------------------------------------------------
 * API client
 * ------------------------------------------------------------------------ */

/**
 * Call the QRZ Logbook API.
 *
 * @return array|WP_Error Parsed response: upper-case keys, plus 'ADIF' (decoded) when present.
 */
function hamshack_qrz_request( $key, $action, $option = '' ) {
	$body = array(
		'KEY'    => $key,
		'ACTION' => $action,
	);
	if ( $option ) {
		$body['OPTION'] = $option;
	}
	$res = wp_remote_post(
		HAMSHACK_QRZ_ENDPOINT,
		array(
			'timeout'    => 45,
			'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION . ' (' . wp_parse_url( home_url(), PHP_URL_HOST ) . ')',
			'body'       => $body,
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( 200 !== (int) $code ) {
		return new WP_Error( 'hamshack_qrz_http', sprintf( __( 'QRZ.com returned HTTP %d.', 'hamshack' ), $code ) );
	}
	return hamshack_qrz_parse( wp_remote_retrieve_body( $res ) );
}

/**
 * Parse the name=value&name=value response. The ADIF block is HTML-escaped and may itself
 * contain "&", so it is cut out before the rest is parsed.
 */
function hamshack_qrz_parse( $raw ) {
	$raw  = trim( (string) $raw );
	$adif = '';
	if ( preg_match( '/(?:^|&)ADIF=/', $raw, $m, PREG_OFFSET_CAPTURE ) ) {
		$start = $m[0][1] + strlen( $m[0][0] );
		$rest  = substr( $raw, $start );
		if ( preg_match( '/&(RESULT|COUNT|LOGIDS|LOGID|STATUS|REASON|EXTENDED|DATA)=/', $rest, $m2, PREG_OFFSET_CAPTURE ) ) {
			$adif = substr( $rest, 0, $m2[0][1] );
			$raw  = substr( $raw, 0, $m[0][1] ) . substr( $rest, $m2[0][1] );
		} else {
			$adif = $rest;
			$raw  = substr( $raw, 0, $m[0][1] );
		}
	}
	$out = array();
	parse_str( ltrim( $raw, '&' ), $out );
	$out = array_change_key_case( $out, CASE_UPPER );
	if ( '' !== $adif ) {
		if ( false !== stripos( $adif, '%3C' ) ) {
			$adif = rawurldecode( $adif );
		}
		$out['ADIF'] = html_entity_decode( $adif, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return $out;
}

/**
 * Is this a "nothing (more) to fetch" answer rather than a real error?
 */
function hamshack_qrz_is_empty( $r ) {
	if ( isset( $r['COUNT'] ) && 0 === (int) $r['COUNT'] ) {
		return true;
	}
	$reason = isset( $r['REASON'] ) ? strtolower( $r['REASON'] ) : '';
	return (bool) preg_match( '/no (log )?(entries|records|qsos?)|not found|nothing/', $reason );
}

/**
 * Map one ADIF record (lower-case keys) to our QSO fields.
 */
function hamshack_adif_to_qso( array $r ) {
	$date = isset( $r['qso_date'] ) && preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $r['qso_date'], $d ) ? "$d[1]-$d[2]-$d[3]" : '';
	$time = isset( $r['time_on'] ) && strlen( $r['time_on'] ) >= 4 ? substr( $r['time_on'], 0, 2 ) . ':' . substr( $r['time_on'], 2, 2 ) : '';
	$band = isset( $r['band'] ) ? strtolower( $r['band'] ) : '';
	$y    = function ( $k ) use ( $r ) {
		return isset( $r[ $k ] ) && 'Y' === strtoupper( $r[ $k ] );
	};
	$qsl_r = ( $y( 'qsl_rcvd' ) || $y( 'lotw_qsl_rcvd' ) || $y( 'eqsl_qsl_rcvd' ) || ( isset( $r['app_qrzlog_status'] ) && 'C' === strtoupper( $r['app_qrzlog_status'] ) ) ) ? 'Y' : 'N';
	$qsl_s = ( $y( 'qsl_sent' ) || $y( 'lotw_qsl_sent' ) || $y( 'eqsl_qsl_sent' ) ) ? 'Y' : 'N';
	$ref   = '';
	foreach ( array( 'pota_ref', 'sota_ref', 'wwff_ref', 'sig_info' ) as $k ) {
		if ( ! empty( $r[ $k ] ) ) {
			$ref = $r[ $k ];
			break;
		}
	}
	return array(
		'call'    => isset( $r['call'] ) ? $r['call'] : '',
		'date'    => $date,
		'time'    => $time,
		'freq'    => isset( $r['freq'] ) ? $r['freq'] : '',
		'band'    => in_array( $band, array_map( 'strtolower', hamshack_all_bands() ), true ) ? $band : '',
		'mode'    => ! empty( $r['submode'] ) ? $r['submode'] : ( isset( $r['mode'] ) ? $r['mode'] : '' ),
		'rst_s'   => isset( $r['rst_sent'] ) ? $r['rst_sent'] : '',
		'rst_r'   => isset( $r['rst_rcvd'] ) ? $r['rst_rcvd'] : '',
		'name'    => isset( $r['name'] ) ? $r['name'] : '',
		'qth'     => isset( $r['qth'] ) ? $r['qth'] : '',
		'grid'    => isset( $r['gridsquare'] ) ? $r['gridsquare'] : '',
		'country' => isset( $r['country'] ) ? ucwords( strtolower( $r['country'] ) ) : '',
		'ref'     => $ref,
		'qsl_s'   => $qsl_s,
		'qsl_r'   => $qsl_r,
		'comment' => isset( $r['comment'] ) ? $r['comment'] : ( isset( $r['notes'] ) ? $r['notes'] : '' ),
	);
}

/**
 * Insert or update one QRZ record.
 *
 * @return string 'new' | 'updated' | 'same' | 'skip'
 */
function hamshack_qrz_upsert( array $r ) {
	$data = hamshack_adif_to_qso( $r );
	if ( '' === $data['call'] || '' === $data['date'] ) {
		return 'skip';
	}
	$logid = isset( $r['app_qrzlog_logid'] ) ? (int) $r['app_qrzlog_logid'] : 0;
	$hash  = md5( wp_json_encode( $data ) );
	$id    = 0;
	if ( $logid ) {
		$found = get_posts( array( 'post_type' => 'hamshack_qso', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_key' => '_q_qrz_logid', 'meta_value' => $logid ) ); // phpcs:ignore
		$id    = $found ? (int) $found[0] : 0;
	}
	if ( ! $id ) {
		// Same QSO already logged by hand or via ADIF import?
		$found = get_posts(
			array(
				'post_type'      => 'hamshack_qso',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore
					array( 'key' => '_q_call', 'value' => strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', $data['call'] ) ) ),
					array( 'key' => '_q_dt', 'value' => $data['date'] . ' ' . $data['time'] ),
				),
			)
		);
		$id = $found ? (int) $found[0] : 0;
	}
	if ( $id && get_post_meta( $id, '_q_qrz_hash', true ) === $hash ) {
		return 'same';
	}
	$saved = hamshack_save_qso( $id, $data );
	if ( is_wp_error( $saved ) ) {
		return 'skip';
	}
	update_post_meta( $saved, '_q_qrz_hash', $hash );
	if ( $logid ) {
		update_post_meta( $saved, '_q_qrz_logid', $logid );
	}
	return $id ? 'updated' : 'new';
}

/**
 * Fetch pages of records for a base OPTION string, paging with AFTERLOGID.
 *
 * @return array|WP_Error [ 'new'=>, 'updated'=>, 'max_logid'=> ]
 */
function hamshack_qrz_fetch_loop( $key, $base_option, $after, $max_pages, &$stats ) {
	$after     = (int) $after;
	$max_logid = $after;
	for ( $page = 0; $page < $max_pages; $page++ ) {
		// AFTERLOGID is inclusive (>=), so ask for the id after the last one we have.
		$option = ltrim( $base_option . ',AFTERLOGID:' . ( $after > 0 ? $after + 1 : 0 ) . ',MAX:' . HAMSHACK_QRZ_PAGE, ',' );
		$r      = hamshack_qrz_request( $key, 'FETCH', $option );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$result = isset( $r['RESULT'] ) ? strtoupper( $r['RESULT'] ) : '';
		if ( 'OK' !== $result ) {
			if ( hamshack_qrz_is_empty( $r ) ) {
				break;
			}
			return new WP_Error( 'hamshack_qrz_' . strtolower( $result ? $result : 'fail' ), isset( $r['REASON'] ) ? $r['REASON'] : __( 'Unknown error from QRZ.com.', 'hamshack' ) );
		}
		$records = empty( $r['ADIF'] ) ? array() : hamshack_parse_adif( $r['ADIF'] );
		if ( ! $records ) {
			break;
		}
		foreach ( $records as $rec ) {
			$res = hamshack_qrz_upsert( $rec );
			if ( isset( $stats[ $res ] ) ) {
				$stats[ $res ]++;
			}
			if ( ! empty( $rec['app_qrzlog_logid'] ) ) {
				$max_logid = max( $max_logid, (int) $rec['app_qrzlog_logid'] );
			}
		}
		if ( count( $records ) < HAMSHACK_QRZ_PAGE || $max_logid <= $after ) {
			break; // Last page, or no progress (avoid looping forever).
		}
		$after = $max_logid;
	}
	return array( 'max_logid' => $max_logid );
}

/**
 * Run a sync.
 *
 * @param bool $full Re-read the whole QRZ logbook instead of only new/changed QSOs.
 * @return array|WP_Error
 */
function hamshack_qrz_sync( $full = false ) {
	$state = hamshack_qrz_state();
	if ( ! $state['key'] ) {
		return new WP_Error( 'hamshack_qrz_nokey', __( 'No QRZ.com API key saved yet.', 'hamshack' ) );
	}
	if ( get_transient( 'hamshack_qrz_lock' ) ) {
		return new WP_Error( 'hamshack_qrz_busy', __( 'A sync is already running – try again in a few minutes.', 'hamshack' ) );
	}
	set_transient( 'hamshack_qrz_lock', 1, 10 * MINUTE_IN_SECONDS );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	wp_defer_term_counting( true );

	$stats   = array( 'new' => 0, 'updated' => 0, 'same' => 0, 'skip' => 0 );
	$started = time();
	$after   = $full ? 0 : (int) $state['last_logid'];

	// 1) New QSOs (logid above the last one we have).
	$res = hamshack_qrz_fetch_loop( $state['key'], '', $after, $full ? 40 : 20, $stats );

	// 2) QSOs changed since the last sync (e.g. new QSL confirmations, edits).
	if ( ! is_wp_error( $res ) && ! $full && $state['last_sync'] ) {
		$since = gmdate( 'Y-m-d', $state['last_sync'] - DAY_IN_SECONDS );
		$res2  = hamshack_qrz_fetch_loop( $state['key'], 'MODSINCE:' . $since, 0, 10, $stats );
		if ( is_wp_error( $res2 ) ) {
			$res = $res2;
		}
	}

	wp_defer_term_counting( false );
	delete_transient( 'hamshack_qrz_lock' );
	delete_transient( 'hamshack_log_stats' );

	if ( is_wp_error( $res ) ) {
		hamshack_qrz_update_state(
			array(
				'last_status'  => 'error',
				'last_message' => $res->get_error_message(),
				'last_run'     => $started,
			)
		);
		return $res;
	}
	hamshack_qrz_update_state(
		array(
			'last_logid'   => max( $full ? 0 : (int) $state['last_logid'], (int) $res['max_logid'] ),
			'last_sync'    => $started,
			'last_run'     => $started,
			'last_status'  => 'ok',
			'last_new'     => $stats['new'],
			'last_updated' => $stats['updated'],
			'last_message' => sprintf( __( '%1$d new, %2$d updated, %3$d unchanged.', 'hamshack' ), $stats['new'], $stats['updated'], $stats['same'] ),
		)
	);
	return $stats;
}

/* --------------------------------------------------------------------------
 * Scheduling (WP-Cron)
 * ------------------------------------------------------------------------ */

/**
 * Extra, shorter schedules for the QRZ sync (WordPress only ships hourly/twice daily/daily).
 */
add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['hamshack_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => __( 'Every 15 minutes', 'hamshack' ) );
	$schedules['hamshack_30min'] = array( 'interval' => 30 * MINUTE_IN_SECONDS, 'display' => __( 'Every 30 minutes', 'hamshack' ) );
	return $schedules;
} );

/**
 * Allowed sync intervals.
 */
function hamshack_qrz_intervals() {
	return array(
		'hamshack_15min' => __( 'every 15 minutes', 'hamshack' ),
		'hamshack_30min' => __( 'every 30 minutes', 'hamshack' ),
		'hourly'      => __( 'every hour', 'hamshack' ),
		'twicedaily'  => __( 'twice a day', 'hamshack' ),
		'daily'       => __( 'once a day', 'hamshack' ),
	);
}

add_action( 'hamshack_qrz_cron', function () {
	$state = hamshack_qrz_state();
	if ( $state['enabled'] && $state['key'] ) {
		hamshack_qrz_sync( false );
	}
} );

function hamshack_qrz_reschedule() {
	wp_clear_scheduled_hook( 'hamshack_qrz_cron' );
	$state = hamshack_qrz_state();
	if ( $state['enabled'] && $state['key'] ) {
		$interval = array_key_exists( $state['interval'], hamshack_qrz_intervals() ) ? $state['interval'] : 'hourly';
		wp_schedule_event( time() + 60, $interval, 'hamshack_qrz_cron' );
	}
}

// Make sure the schedule exists (e.g. after a theme update) and is removed when the theme is switched off.
add_action( 'init', function () {
	$state = hamshack_qrz_state();
	if ( $state['enabled'] && $state['key'] ) {
		$event = wp_get_scheduled_event( 'hamshack_qrz_cron' );
		if ( ! $event || $event->schedule !== $state['interval'] ) {
			hamshack_qrz_reschedule();
		}
	}
} );
add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'hamshack_qrz_cron' );
} );

/* --------------------------------------------------------------------------
 * Admin UI
 * ------------------------------------------------------------------------ */

add_action( 'hamshack_logbook_sync_panel', 'hamshack_qrz_panel' );
function hamshack_qrz_panel() {
	$state = hamshack_qrz_state();
	$msgs  = array(
		'saved'    => array( 'success', __( 'QRZ.com settings saved.', 'hamshack' ) ),
		'synced'   => array( 'success', __( 'Sync finished:', 'hamshack' ) . ' ' . $state['last_message'] ),
		'syncerr'  => array( 'error', __( 'Sync failed:', 'hamshack' ) . ' ' . $state['last_message'] ),
		'testok'   => array( 'success', __( 'Connected to QRZ.com – the API key works.', 'hamshack' ) . ( $state['book'] ? ' ' . $state['book'] : '' ) ),
		'testerr'  => array( 'error', __( 'QRZ.com rejected the connection:', 'hamshack' ) . ' ' . get_transient( 'hamshack_qrz_test_msg' ) ),
		'removed'  => array( 'success', __( 'API key removed and automatic sync switched off.', 'hamshack' ) ),
	);
	$q = isset( $_GET['qrz'] ) ? sanitize_key( $_GET['qrz'] ) : ''; // phpcs:ignore
	if ( isset( $msgs[ $q ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $msgs[ $q ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $q ][1] ) . '</p></div>';
	}
	$next = wp_next_scheduled( 'hamshack_qrz_cron' );
	?>
	<div class="hamshack-card hamshack-qrz">
		<h2><?php hamshack_the_icon( 'globe' ); ?> <?php esc_html_e( 'QRZ.com Logbook – automatic sync', 'hamshack' ); ?></h2>
		<p><?php esc_html_e( 'Pulls your QSOs from your QRZ.com logbook into this site automatically – new contacts and QSL confirmations included. Requires a QRZ subscription with Logbook API access.', 'hamshack' ); ?></p>

		<div class="hamshack-qrz-status <?php echo esc_attr( $state['last_status'] ? 'is-' . $state['last_status'] : '' ); ?>">
			<?php if ( ! $state['key'] ) : ?>
				<strong><?php esc_html_e( 'Not connected yet.', 'hamshack' ); ?></strong> <?php esc_html_e( 'Paste your QRZ Logbook API key below.', 'hamshack' ); ?>
			<?php else : ?>
				<strong><?php echo $state['enabled'] ? esc_html__( 'Automatic sync is ON', 'hamshack' ) : esc_html__( 'Automatic sync is OFF', 'hamshack' ); ?></strong>
				<?php if ( ! empty( $state['last_run'] ) ) : ?>
					· <?php printf( esc_html__( 'last run %s ago', 'hamshack' ), esc_html( human_time_diff( $state['last_run'] ) ) ); ?>
					(<?php echo esc_html( 'error' === $state['last_status'] ? __( 'error: ', 'hamshack' ) . $state['last_message'] : $state['last_message'] ); ?>)
				<?php endif; ?>
				<?php if ( $next && $state['enabled'] ) : ?>
					· <?php printf( esc_html__( 'next run in %s', 'hamshack' ), esc_html( human_time_diff( $next ) ) ); ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hamshack_qrz_save' ); ?>
			<input type="hidden" name="action" value="hamshack_qrz_save">
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="hs-qrz-key"><?php esc_html_e( 'Logbook API key', 'hamshack' ); ?></label></th><td>
					<input type="password" id="hs-qrz-key" name="qrz_key" class="regular-text code" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( $state['key'] ? hamshack_qrz_masked_key( $state['key'] ) : 'XXXX-XXXX-XXXX-XXXX' ); ?>">
					<p class="description"><?php echo wp_kses_post( __( 'Find it on QRZ.com in your <em>Logbook settings</em>, in the API section. Leave empty to keep the saved key. It is stored only in this site\'s database and never shown on the website.', 'hamshack' ) ); ?></p>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Automatic sync', 'hamshack' ); ?></th><td>
					<label><input type="checkbox" name="qrz_enabled" value="1" <?php checked( $state['enabled'] ); ?>> <?php esc_html_e( 'Download new QSOs from QRZ.com automatically', 'hamshack' ); ?></label>
					<select name="qrz_interval" style="margin-left:8px">
						<?php foreach ( hamshack_qrz_intervals() as $ik => $il ) : ?>
							<option value="<?php echo esc_attr( $ik ); ?>" <?php selected( $state['interval'], $ik ); ?>><?php echo esc_html( $il ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Tip: for 15-minute syncing set up a real cron job in your hosting panel (every 15 minutes), otherwise WordPress only runs scheduled tasks when someone visits the site.', 'hamshack' ); ?></p>
				</td></tr>
			</table>
			<p class="hamshack-qrz-actions">
				<button class="button button-primary" name="do" value="save"><?php esc_html_e( 'Save', 'hamshack' ); ?></button>
				<?php if ( $state['key'] ) : ?>
					<button class="button" name="do" value="test"><?php esc_html_e( 'Test connection', 'hamshack' ); ?></button>
					<button class="button" name="do" value="sync"><?php esc_html_e( 'Sync now', 'hamshack' ); ?></button>
					<button class="button" name="do" value="full" onclick="return confirm('<?php echo esc_js( __( 'Download the whole QRZ logbook again? Existing QSOs are updated, not duplicated.', 'hamshack' ) ); ?>')"><?php esc_html_e( 'Full re-sync', 'hamshack' ); ?></button>
					<button class="button button-link-delete" name="do" value="remove"><?php esc_html_e( 'Remove key', 'hamshack' ); ?></button>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_qrz_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_qrz_save' );
	$do    = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : 'save';
	$state = hamshack_qrz_state();
	$back  = admin_url( 'admin.php?page=hamshack-adif' );

	if ( 'remove' === $do ) {
		hamshack_qrz_update_state( array( 'key' => '', 'enabled' => 0, 'book' => '' ) );
		hamshack_qrz_reschedule();
		wp_safe_redirect( add_query_arg( 'qrz', 'removed', $back ) );
		exit;
	}

	$new_key = isset( $_POST['qrz_key'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', wp_unslash( $_POST['qrz_key'] ) ) : ''; // phpcs:ignore
	$changes = array(
		'enabled'  => empty( $_POST['qrz_enabled'] ) ? 0 : 1,
		'interval' => isset( $_POST['qrz_interval'] ) && array_key_exists( sanitize_key( $_POST['qrz_interval'] ), hamshack_qrz_intervals() ) ? sanitize_key( $_POST['qrz_interval'] ) : 'hourly',
	);
	if ( $new_key ) {
		$changes['key'] = strtoupper( $new_key );
		if ( $new_key !== $state['key'] ) {
			// A different logbook: start from scratch.
			$changes['last_logid'] = 0;
			$changes['last_sync']  = 0;
		}
	}
	$state = hamshack_qrz_update_state( $changes );
	hamshack_qrz_reschedule();

	if ( 'test' === $do || ( 'save' === $do && $new_key ) ) {
		$r = hamshack_qrz_request( $state['key'], 'STATUS' );
		if ( is_wp_error( $r ) || ! isset( $r['RESULT'] ) || 'OK' !== strtoupper( $r['RESULT'] ) ) {
			set_transient( 'hamshack_qrz_test_msg', is_wp_error( $r ) ? $r->get_error_message() : ( isset( $r['REASON'] ) ? $r['REASON'] : __( 'unknown error', 'hamshack' ) ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'qrz', 'testerr', $back ) );
			exit;
		}
		$book = array();
		if ( ! empty( $r['DATA'] ) ) {
			parse_str( str_replace( array( '&amp;' ), '&', $r['DATA'] ), $data );
			$data = array_change_key_case( (array) $data, CASE_UPPER );
			if ( ! empty( $data['BOOK_NAME'] ) ) {
				$book[] = sprintf( __( 'Logbook: %s', 'hamshack' ), $data['BOOK_NAME'] );
			}
			if ( ! empty( $data['CALLSIGN'] ) ) {
				$book[] = sprintf( __( 'Callsign: %s', 'hamshack' ), $data['CALLSIGN'] );
			}
			if ( isset( $data['COUNT'] ) ) {
				$book[] = sprintf( __( 'QSOs on QRZ: %s', 'hamshack' ), $data['COUNT'] );
			}
		}
		hamshack_qrz_update_state( array( 'book' => sanitize_text_field( implode( ' · ', $book ) ) ) );
		if ( 'test' === $do ) {
			wp_safe_redirect( add_query_arg( 'qrz', 'testok', $back ) );
			exit;
		}
	}

	if ( 'sync' === $do || 'full' === $do ) {
		$res = hamshack_qrz_sync( 'full' === $do );
		wp_safe_redirect( add_query_arg( 'qrz', is_wp_error( $res ) ? 'syncerr' : 'synced', $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'qrz', 'saved', $back ) );
	exit;
} );

/**
 * Show last sync on the dashboard widget.
 */
function hamshack_qrz_dashboard_line() {
	$state = hamshack_qrz_state();
	if ( ! $state['key'] ) {
		return;
	}
	echo '<p class="hamshack-qrz-line">QRZ.com: ';
	if ( ! empty( $state['last_run'] ) ) {
		printf( esc_html__( 'last sync %1$s ago – %2$s', 'hamshack' ), esc_html( human_time_diff( $state['last_run'] ) ), esc_html( $state['last_message'] ) );
	} else {
		esc_html_e( 'not synced yet', 'hamshack' );
	}
	echo ' <a href="' . esc_url( admin_url( 'admin.php?page=hamshack-adif' ) ) . '">' . esc_html__( 'manage', 'hamshack' ) . '</a></p>';
}
