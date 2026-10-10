<?php
/**
 * Contests: daily import from the WA7BNM Contest Calendar (RSS, next 8 days)
 * and a live contest-mode scoreboard computed from the logbook.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------------
 * Import
 * ------------------------------------------------------------------------ */

add_action( 'init', function () {
	if ( hamshack_int( 'contest_import' ) && ! wp_next_scheduled( 'hamshack_contest_import' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'hamshack_contest_import' );
	}
} );
add_action( 'hamshack_contest_import', 'hamshack_contest_import' );

/**
 * Parse a WA7BNM time description into UTC start/end timestamps.
 *
 * "0000Z-0800Z, Oct 10 and 1600Z-2400Z, Oct 10"  or  "0600Z, Oct 10 to 0600Z, Oct 11"
 *
 * @return array|null [ start, end ]
 */
function hamshack_contest_parse_times( $desc, $now = null ) {
	$now   = $now ? $now : time();
	$year  = (int) gmdate( 'Y', $now );
	$mk    = function ( $hhmm, $mon, $day ) use ( $year, $now ) {
		$m = gmdate( 'n', strtotime( $mon . ' 1 2000' ) );
		$h = (int) substr( $hhmm, 0, 2 );
		$i = (int) substr( $hhmm, 2, 2 );
		$t = gmmktime( 0, 0, 0, (int) $m, (int) $day, $year ) + $h * 3600 + $i * 60; // 2400Z rolls over to the next day.
		if ( $t < $now - 180 * DAY_IN_SECONDS ) {
			$t = gmmktime( 0, 0, 0, (int) $m, (int) $day, $year + 1 ) + $h * 3600 + $i * 60; // Across New Year.
		}
		return $t;
	};
	$starts = array();
	$ends   = array();
	foreach ( preg_split( '/\s+and\s+/i', (string) $desc ) as $seg ) {
		if ( preg_match( '/(\d{4})Z,\s*([A-Z][a-z]{2})\s+(\d{1,2})\s+to\s+(\d{4})Z,\s*([A-Z][a-z]{2})\s+(\d{1,2})/', $seg, $m ) ) {
			$starts[] = $mk( $m[1], $m[2], $m[3] );
			$ends[]   = $mk( $m[4], $m[5], $m[6] );
		} elseif ( preg_match( '/(\d{4})Z\s*-\s*(\d{4})Z,\s*([A-Z][a-z]{2})\s+(\d{1,2})/', $seg, $m ) ) {
			$s        = $mk( $m[1], $m[3], $m[4] );
			$e        = $mk( $m[2], $m[3], $m[4] );
			$starts[] = $s;
			$ends[]   = $e > $s ? $e : $e + DAY_IN_SECONDS;
		}
	}
	if ( ! $starts ) {
		return null;
	}
	return array( min( $starts ), max( $ends ) );
}

/**
 * Guess the mode from the contest name.
 */
function hamshack_contest_mode( $title ) {
	foreach ( array( 'FT8' => 'FT8', 'FT4' => 'FT4', 'RTTY' => 'RTTY', 'PSK' => 'PSK31', 'SSB' => 'SSB', 'Phone' => 'SSB', 'CW' => 'CW', 'SSTV' => 'SSTV', 'FM' => 'FM' ) as $k => $m ) {
		if ( preg_match( '/\b' . preg_quote( $k, '/' ) . '\b/i', $title ) ) {
			return $m;
		}
	}
	return 'Mixed';
}

function hamshack_contest_wanted( $title ) {
	$inc = array_filter( array_map( 'trim', explode( ',', (string) hamshack_int( 'contest_include' ) ) ) );
	$exc = array_filter( array_map( 'trim', explode( ',', (string) hamshack_int( 'contest_exclude' ) ) ) );
	foreach ( $exc as $w ) {
		if ( false !== stripos( $title, $w ) ) {
			return false;
		}
	}
	if ( ! $inc ) {
		return true;
	}
	foreach ( $inc as $w ) {
		if ( false !== stripos( $title, $w ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Run the import. Returns [ added, updated ].
 */
function hamshack_contest_import() {
	if ( ! hamshack_int( 'contest_import' ) ) {
		return array( 0, 0 );
	}
	$res = wp_remote_get( 'https://www.contestcalendar.com/calendar.rss', array( 'timeout' => 20, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION . ' (' . home_url() . ')' ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return array( 0, 0 );
	}
	$body = wp_remote_retrieve_body( $res );
	if ( ! preg_match_all( '#<item>(.*?)</item>#s', $body, $items ) ) {
		return array( 0, 0 );
	}
	$term = term_exists( 'contest', 'hamshack_event_type' );
	if ( ! $term ) {
		$term = wp_insert_term( 'Contest', 'hamshack_event_type', array( 'slug' => 'contest' ) );
	}
	$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	$added   = 0;
	$updated = 0;
	foreach ( $items[1] as $it ) {
		$get   = function ( $tag ) use ( $it ) {
			return preg_match( '#<' . $tag . '>(.*?)</' . $tag . '>#s', $it, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) : '';
		};
		$title = sanitize_text_field( $get( 'title' ) );
		$guid  = esc_url_raw( $get( 'guid' ) );
		$link  = esc_url_raw( $get( 'link' ) );
		$when  = hamshack_contest_parse_times( $get( 'description' ) );
		if ( ! $title || ! $guid || ! $when || ! hamshack_contest_wanted( $title ) ) {
			continue;
		}
		$local = function ( $ts ) {
			return wp_date( 'Y-m-d H:i', $ts );
		};
		if ( in_array( $guid, (array) get_option( 'hamshack_contest_skip', array() ), true ) ) {
			continue; // Deleted by hand – don't bring it back.
		}
		$found = get_posts( array( 'post_type' => 'hamshack_event', 'post_status' => array_keys( get_post_stati() ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_hs_src', 'meta_value' => $guid ) ); // phpcs:ignore
		if ( $found ) {
			$id = (int) $found[0];
			if ( get_post_meta( $id, '_hs_start', true ) !== $local( $when[0] ) || get_post_meta( $id, '_hs_end', true ) !== $local( $when[1] ) ) {
				update_post_meta( $id, '_hs_start', $local( $when[0] ) );
				update_post_meta( $id, '_hs_end', $local( $when[1] ) );
				$updated++;
			}
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'hamshack_event',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $get( 'description' ) ) . ' (UTC). ' . sprintf( 'Rules, exchange and log submission: <a href="%s">WA7BNM Contest Calendar</a>.', esc_url( $link ) ) . '</p><!-- /wp:paragraph -->',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_hs_start', $local( $when[0] ) );
		update_post_meta( $id, '_hs_end', $local( $when[1] ) );
		update_post_meta( $id, '_hs_allday', 0 );
		update_post_meta( $id, '_hs_repeat', 'none' );
		update_post_meta( $id, '_hs_band', 'Multi' );
		update_post_meta( $id, '_hs_mode', hamshack_contest_mode( $title ) );
		update_post_meta( $id, '_hs_url', $link );
		update_post_meta( $id, '_hs_src', $guid );
		update_post_meta( $id, '_hs_auto', 1 );
		if ( $term_id ) {
			wp_set_object_terms( $id, array( $term_id ), 'hamshack_event_type' );
		}
		$added++;
	}
	// Tidy up: old imported contests you did not enter.
	$old = get_posts(
		array(
			'post_type'      => 'hamshack_event',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore
				array( 'key' => '_hs_auto', 'value' => '1' ),
				array( 'key' => '_hs_end', 'value' => wp_date( 'Y-m-d H:i', time() - 30 * DAY_IN_SECONDS ), 'compare' => '<' ),
				array( 'key' => '_hs_entering', 'compare' => 'NOT EXISTS' ),
			),
		)
	);
	foreach ( $old as $id ) {
		wp_delete_post( $id, true );
	}
	delete_transient( 'hamshack_events_all' );
	update_option( 'hamshack_contest_last', array( 'ts' => time(), 'added' => $added, 'updated' => $updated ), false );
	return array( $added, $updated );
}

/* "I'm taking part" switch on every event. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_entering', __( 'Contest mode', 'hamshack' ), function ( $post ) {
		wp_nonce_field( 'hamshack_entering', 'hamshack_entering_nonce' );
		echo '<label><input type="checkbox" name="hs_entering" value="1" ' . checked( (bool) get_post_meta( $post->ID, '_hs_entering', true ), true, false ) . '> ' . esc_html__( 'I’m taking part', 'hamshack' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Shows it in my calendar and turns on the live contest scoreboard while it runs.', 'hamshack' ) . '</p>';
		if ( get_post_meta( $post->ID, '_hs_auto', true ) ) {
			echo '<p class="description">' . esc_html__( 'Imported from the WA7BNM Contest Calendar.', 'hamshack' ) . '</p>';
		}
	}, 'hamshack_event', 'side', 'high' );
} );
add_action( 'save_post_hamshack_event', function ( $post_id ) {
	if ( ! isset( $_POST['hamshack_entering_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_entering_nonce'] ), 'hamshack_entering' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( empty( $_POST['hs_entering'] ) ) {
		delete_post_meta( $post_id, '_hs_entering' );
	} else {
		update_post_meta( $post_id, '_hs_entering', 1 );
	}
	delete_transient( 'hamshack_events_all' );
}, 20 );

/* Admin: quick "Enter / leave" action in the event list. */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'hamshack_event' === $post->post_type && get_post_meta( $post->ID, '_hs_auto', true ) ) {
		$on                     = (bool) get_post_meta( $post->ID, '_hs_entering', true );
		$actions['hs_entering'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_entering&id=' . $post->ID ), 'hamshack_entering_' . $post->ID ) ) . '">' . ( $on ? esc_html__( 'Not taking part', 'hamshack' ) : esc_html__( 'I’m taking part', 'hamshack' ) ) . '</a>';
	}
	return $actions;
}, 10, 2 );
add_action( 'admin_post_hamshack_entering', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $id || 'hamshack_event' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_entering_' . $id );
	if ( get_post_meta( $id, '_hs_entering', true ) ) {
		delete_post_meta( $id, '_hs_entering' );
	} else {
		update_post_meta( $id, '_hs_entering', 1 );
	}
	delete_transient( 'hamshack_events_all' );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=hamshack_event' ) );
	exit;
} );

/* --------------------------------------------------------------------------
 * Contest mode
 * ------------------------------------------------------------------------ */

/**
 * The contest I'm entering that runs now (or starts within $ahead seconds).
 *
 * @return array|null Occurrence with 'ts', 'end_ts'.
 */
function hamshack_active_contest( $ahead = 0 ) {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	foreach ( hamshack_occurrences( $now->modify( '-4 days' ), $now->modify( '+' . max( 1, (int) ceil( $ahead / DAY_IN_SECONDS ) + 1 ) . ' days' ), true ) as $o ) {
		if ( empty( $o['entering'] ) ) {
			continue;
		}
		$end = $o['end_ts'] ? $o['end_ts'] : $o['ts'] + 4 * HOUR_IN_SECONDS;
		if ( $o['ts'] - $ahead <= time() && time() < $end ) {
			$o['end_ts'] = $end;
			return $o;
		}
	}
	return null;
}

/**
 * Scoreboard numbers for a time window (UTC timestamps).
 */
function hamshack_contest_stats( $start, $end ) {
	global $wpdb;
	$from = gmdate( 'Y-m-d H:i', $start );
	$to   = gmdate( 'Y-m-d H:i', $end );
	$ck   = 'hamshack_cstats_' . md5( $from . $to );
	$hit  = get_transient( $ck );
	if ( false !== $hit ) {
		return $hit;
	}
	$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish' WHERE pm.meta_key = '_q_dt' AND pm.meta_value >= %s AND pm.meta_value <= %s ORDER BY pm.meta_value DESC", $from, $to ) ); // phpcs:ignore
	if ( $ids ) {
		update_meta_cache( 'post', array_map( 'intval', $ids ) );
	}
	$s    = array( 'qsos' => count( $ids ), 'bands' => array(), 'dxcc' => array(), 'cqz' => array(), 'r10' => 0, 'r60' => 0, 'last' => array() );
	$now  = time();
	foreach ( $ids as $i => $id ) {
		$b  = get_post_meta( $id, '_q_band', true );
		$dt = strtotime( get_post_meta( $id, '_q_dt', true ) . ' UTC' );
		if ( $b ) {
			$s['bands'][ $b ] = isset( $s['bands'][ $b ] ) ? $s['bands'][ $b ] + 1 : 1;
		}
		$dx = (int) get_post_meta( $id, '_q_dxcc', true );
		if ( $dx ) {
			$s['dxcc'][ $dx ] = 1;
		}
		$z = (int) get_post_meta( $id, '_q_cqz', true );
		if ( $z ) {
			$s['cqz'][ $z ] = 1;
		}
		if ( $now - $dt <= 600 ) {
			$s['r10']++;
		}
		if ( $now - $dt <= 3600 ) {
			$s['r60']++;
		}
		if ( $i < 10 ) {
			$s['last'][] = array( 'call' => get_post_meta( $id, '_q_call', true ), 'time' => get_post_meta( $id, '_q_time', true ), 'band' => $b, 'mode' => get_post_meta( $id, '_q_mode', true ), 'country' => get_post_meta( $id, '_q_country', true ) );
		}
	}
	$s['dxcc'] = count( $s['dxcc'] );
	$s['cqz']  = count( $s['cqz'] );
	arsort( $s['bands'] );
	set_transient( $ck, $s, MINUTE_IN_SECONDS );
	return $s;
}

/**
 * Scoreboard markup.
 */
function hamshack_contest_board( $o, $compact = false ) {
	$s    = hamshack_contest_stats( $o['ts'], $o['end_ts'] );
	$left = max( 0, $o['end_ts'] - time() );
	$run  = time() >= $o['ts'];
	ob_start();
	?>
	<div class="contest-board<?php echo $compact ? ' is-compact' : ''; ?>" data-hs-contest>
		<div class="cb-head">
			<span class="cb-live"><span class="ldot is-live"></span><?php echo $run ? esc_html__( 'CONTEST', 'hamshack' ) : esc_html__( 'SOON', 'hamshack' ); ?></span>
			<a class="cb-title" href="<?php echo esc_url( $o['url'] ); ?>"><?php echo esc_html( $o['title'] ); ?></a>
			<span class="cb-time mono muted"><?php echo $run ? esc_html( sprintf( __( '%s left', 'hamshack' ), human_time_diff( time(), $o['end_ts'] ) ) ) : esc_html( sprintf( __( 'starts in %s', 'hamshack' ), human_time_diff( time(), $o['ts'] ) ) ); ?></span>
		</div>
		<div class="cb-nums">
			<div><span class="v mono"><?php echo (int) $s['qsos']; ?></span><span class="l"><?php esc_html_e( 'QSOs', 'hamshack' ); ?></span></div>
			<div><span class="v mono"><?php echo (int) ( $s['r10'] * 6 ); ?></span><span class="l"><?php esc_html_e( 'rate /h (10 min)', 'hamshack' ); ?></span></div>
			<div><span class="v mono"><?php echo (int) $s['r60']; ?></span><span class="l"><?php esc_html_e( 'last hour', 'hamshack' ); ?></span></div>
			<div><span class="v mono"><?php echo (int) $s['dxcc']; ?></span><span class="l">DXCC</span></div>
			<div><span class="v mono"><?php echo (int) $s['cqz']; ?></span><span class="l"><?php esc_html_e( 'CQ zones', 'hamshack' ); ?></span></div>
		</div>
		<?php if ( ! $compact ) : ?>
			<?php if ( $s['bands'] ) : ?>
				<?php echo hamshack_hbars( $s['bands'], hamshack_band_colors() ); // phpcs:ignore ?>
			<?php endif; ?>
			<?php if ( $s['last'] ) : ?>
				<div class="table-scroll cb-last"><table class="data-table"><thead><tr><th>UTC</th><th><?php esc_html_e( 'Call', 'hamshack' ); ?></th><th><?php esc_html_e( 'Band', 'hamshack' ); ?></th><th><?php esc_html_e( 'Mode', 'hamshack' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Country', 'hamshack' ); ?></th></tr></thead><tbody>
				<?php foreach ( $s['last'] as $q ) : ?>
					<tr><td class="mono"><?php echo esc_html( $q['time'] ); ?></td><td class="mono call"><?php echo esc_html( $q['call'] ); ?></td><td><?php echo esc_html( $q['band'] ); ?></td><td><?php echo esc_html( $q['mode'] ); ?></td><td class="hide-sm"><?php echo esc_html( $q['country'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
			<p class="app-note"><?php esc_html_e( 'Counted live from the logbook (WSJT-X QSOs arrive instantly, QRZ.com sync every few minutes). Not an official score.', 'hamshack' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * [hamshack_contest] – scoreboard of the running contest, or what is coming up.
 */
add_shortcode( 'hamshack_contest', function () {
	$o = hamshack_active_contest( 6 * HOUR_IN_SECONDS );
	if ( $o ) {
		return '<div class="hs-app">' . hamshack_contest_board( $o ) . '</div>';
	}
	return '<div class="empty-state">' . esc_html__( 'I am not in a contest right now – see the contests coming up below.', 'hamshack' ) . '</div>';
} );

/**
 * [hamshack_contests] – the Contests page: scoreboard + next 8 days from the contest calendar.
 */
add_shortcode( 'hamshack_contests', function () {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	$occ = array_filter( hamshack_occurrences( $now->modify( '-2 hours' ), $now->modify( '+9 days' ), true ), function ( $o ) {
		return 'contest' === $o['type'];
	} );
	$last = get_option( 'hamshack_contest_last' );
	ob_start();
	?>
	<div class="hs-app contests-app">
		<?php echo do_shortcode( '[hamshack_contest]' ); // phpcs:ignore ?>
		<h2 class="app-h2"><?php esc_html_e( 'Contests in the next days', 'hamshack' ); ?></h2>
		<?php if ( $occ ) : ?>
			<div class="table-scroll"><table class="data-table contest-table"><thead><tr><th><?php esc_html_e( 'Contest', 'hamshack' ); ?></th><th><?php esc_html_e( 'Starts (UTC)', 'hamshack' ); ?></th><th><?php esc_html_e( 'Ends (UTC)', 'hamshack' ); ?></th><th><?php esc_html_e( 'Mode', 'hamshack' ); ?></th></tr></thead><tbody>
			<?php foreach ( $occ as $o ) : $running = $o['ts'] <= time() && time() < $o['end_ts']; ?>
				<tr class="<?php echo $o['entering'] ? 'is-me' : ''; ?>">
					<td><a href="<?php echo esc_url( $o['url'] ); ?>"><?php echo esc_html( $o['title'] ); ?></a><?php echo $o['entering'] ? ' <span class="tag tag-alt">' . esc_html__( 'I’m in!', 'hamshack' ) . '</span>' : ''; ?><?php echo $running ? ' <span class="tag">' . esc_html__( 'running', 'hamshack' ) . '</span>' : ''; ?></td>
					<td class="mono"><?php echo esc_html( gmdate( 'D d M H:i', $o['ts'] ) ); ?></td>
					<td class="mono"><?php echo esc_html( $o['end_ts'] ? gmdate( 'D d M H:i', $o['end_ts'] ) : '' ); ?></td>
					<td><?php echo esc_html( $o['mode'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php else : ?>
			<div class="empty-state"><?php esc_html_e( 'No contests imported yet.', 'hamshack' ); ?></div>
		<?php endif; ?>
		<p class="app-note"><?php printf( esc_html__( 'Contest list from the %s (Bruce Horn, WA7BNM) – always check the rules there before the contest.', 'hamshack' ), '<a href="https://www.contestcalendar.com/" target="_blank" rel="noopener">WA7BNM Contest Calendar</a>' ); // phpcs:ignore ?><?php echo $last ? ' ' . esc_html( sprintf( __( 'Updated %s ago.', 'hamshack' ), human_time_diff( $last['ts'] ) ) ) : ''; ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/* Banner at the top of the home page while I'm in a contest. */
add_action( 'hamshack_home_top', function () {
	$o = hamshack_active_contest();
	if ( $o ) {
		echo '<section class="section contest-strip"><div class="container">' . hamshack_contest_board( $o, true ) . '</div></section>'; // phpcs:ignore
	}
} );

/* Remember contests you deleted, so the daily import does not add them again. */
add_action( 'before_delete_post', function ( $id ) {
	if ( 'hamshack_event' !== get_post_type( $id ) || ! get_post_meta( $id, '_hs_auto', true ) ) {
		return;
	}
	$src = get_post_meta( $id, '_hs_src', true );
	if ( $src && get_post_meta( $id, '_hs_end', true ) > wp_date( 'Y-m-d H:i' ) ) {
		$skip   = (array) get_option( 'hamshack_contest_skip', array() );
		$skip[] = $src;
		update_option( 'hamshack_contest_skip', array_slice( array_unique( $skip ), -200 ), false );
	}
} );
