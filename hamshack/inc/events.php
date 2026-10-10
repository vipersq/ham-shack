<?php
/**
 * Events & calendar: custom post type, meta box, recurrence, calendar, iCal feed.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'hamshack_register_events' );
function hamshack_register_events() {
	register_post_type(
		'hamshack_event',
		array(
			'labels'        => array(
				'name'               => __( 'Events', 'hamshack' ),
				'singular_name'      => __( 'Event', 'hamshack' ),
				'add_new'            => __( 'Add event', 'hamshack' ),
				'add_new_item'       => __( 'Add new event', 'hamshack' ),
				'edit_item'          => __( 'Edit event', 'hamshack' ),
				'new_item'           => __( 'New event', 'hamshack' ),
				'view_item'          => __( 'View event', 'hamshack' ),
				'search_items'       => __( 'Search events', 'hamshack' ),
				'not_found'          => __( 'No events found', 'hamshack' ),
				'all_items'          => __( 'Events', 'hamshack' ),
				'menu_name'          => __( 'Events', 'hamshack' ),
			),
			'public'        => true,
			'has_archive'   => 'events',
			'rewrite'       => array( 'slug' => 'events', 'with_front' => false ),
			'show_in_rest'  => true,
			'show_in_menu'  => 'hamshack',
			'menu_icon'     => 'dashicons-calendar-alt',
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'comments' ),
		)
	);

	register_taxonomy(
		'hamshack_event_type',
		'hamshack_event',
		array(
			'labels'            => array(
				'name'          => __( 'Event types', 'hamshack' ),
				'singular_name' => __( 'Event type', 'hamshack' ),
				'menu_name'     => __( 'Event types', 'hamshack' ),
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'event-type' ),
		)
	);

	foreach ( array( '_hs_start', '_hs_end', '_hs_location', '_hs_freq', '_hs_mode', '_hs_band', '_hs_url', '_hs_repeat', '_hs_repeat_until' ) as $key ) {
		register_post_meta(
			'hamshack_event',
			$key,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
	register_post_meta(
		'hamshack_event',
		'_hs_allday',
		array(
			'type'          => 'boolean',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

/* --------------------------------------------------------------------------
 * Meta box
 * ------------------------------------------------------------------------ */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_event_details', __( 'Event details', 'hamshack' ), 'hamshack_event_metabox', 'hamshack_event', 'normal', 'high' );
} );

function hamshack_event_metabox( $post ) {
	wp_nonce_field( 'hamshack_event_save', 'hamshack_event_nonce' );
	$get   = function ( $k ) use ( $post ) {
		return get_post_meta( $post->ID, $k, true );
	};
	$start = $get( '_hs_start' );
	$end   = $get( '_hs_end' );
	$start = $start ? str_replace( ' ', 'T', $start ) : wp_date( 'Y-m-d\T19:00', strtotime( '+1 day' ) );
	$end   = $end ? str_replace( ' ', 'T', $end ) : '';
	$rep   = $get( '_hs_repeat' ) ? $get( '_hs_repeat' ) : 'none';
	?>
	<div class="hamshack-grid">
		<p><label><strong><?php esc_html_e( 'Starts', 'hamshack' ); ?></strong><br><input type="datetime-local" name="hs_start" value="<?php echo esc_attr( $start ); ?>" required></label></p>
		<p><label><strong><?php esc_html_e( 'Ends', 'hamshack' ); ?></strong><br><input type="datetime-local" name="hs_end" value="<?php echo esc_attr( $end ); ?>"></label></p>
		<p><label><input type="checkbox" name="hs_allday" value="1" <?php checked( (bool) $get( '_hs_allday' ) ); ?>> <?php esc_html_e( 'All-day event', 'hamshack' ); ?></label></p>
		<p class="description" style="grid-column:1/-1"><?php printf( esc_html__( 'Times are in the site time zone (%s). Visitors also see the UTC time.', 'hamshack' ), esc_html( wp_timezone_string() ) ); ?></p>
		<p><label><strong><?php esc_html_e( 'Repeats', 'hamshack' ); ?></strong><br>
			<select name="hs_repeat">
				<option value="none" <?php selected( $rep, 'none' ); ?>><?php esc_html_e( 'Does not repeat', 'hamshack' ); ?></option>
				<option value="weekly" <?php selected( $rep, 'weekly' ); ?>><?php esc_html_e( 'Every week', 'hamshack' ); ?></option>
				<option value="fortnightly" <?php selected( $rep, 'fortnightly' ); ?>><?php esc_html_e( 'Every 2 weeks', 'hamshack' ); ?></option>
				<option value="monthly" <?php selected( $rep, 'monthly' ); ?>><?php esc_html_e( 'Every month (same date)', 'hamshack' ); ?></option>
			</select></label></p>
		<p><label><strong><?php esc_html_e( 'Repeat until', 'hamshack' ); ?></strong><br><input type="date" name="hs_repeat_until" value="<?php echo esc_attr( $get( '_hs_repeat_until' ) ); ?>"></label></p>
		<p><label><strong><?php esc_html_e( 'Location', 'hamshack' ); ?></strong><br><input type="text" class="widefat" name="hs_location" value="<?php echo esc_attr( $get( '_hs_location' ) ); ?>" placeholder="<?php esc_attr_e( 'Home QTH, park reference, club hall…', 'hamshack' ); ?>"></label></p>
		<p><label><strong><?php esc_html_e( 'Frequency (MHz)', 'hamshack' ); ?></strong><br><input type="text" name="hs_freq" value="<?php echo esc_attr( $get( '_hs_freq' ) ); ?>" placeholder="145.500"></label></p>
		<p><label><strong><?php esc_html_e( 'Band', 'hamshack' ); ?></strong><br>
			<select name="hs_band"><option value="">—</option>
				<?php foreach ( hamshack_all_bands() as $b ) : ?>
					<option value="<?php echo esc_attr( $b ); ?>" <?php selected( $get( '_hs_band' ), $b ); ?>><?php echo esc_html( $b ); ?></option>
				<?php endforeach; ?>
				<option value="Multi" <?php selected( $get( '_hs_band' ), 'Multi' ); ?>><?php esc_html_e( 'Multi-band', 'hamshack' ); ?></option>
			</select></label></p>
		<p><label><strong><?php esc_html_e( 'Mode', 'hamshack' ); ?></strong><br>
			<select name="hs_mode"><option value="">—</option>
				<?php foreach ( hamshack_all_modes() as $m ) : ?>
					<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $get( '_hs_mode' ), $m ); ?>><?php echo esc_html( $m ); ?></option>
				<?php endforeach; ?>
				<option value="Mixed" <?php selected( $get( '_hs_mode' ), 'Mixed' ); ?>><?php esc_html_e( 'Mixed', 'hamshack' ); ?></option>
			</select></label></p>
		<p style="grid-column:1/-1"><label><strong><?php esc_html_e( 'More info link', 'hamshack' ); ?></strong><br><input type="url" class="widefat" name="hs_url" value="<?php echo esc_attr( $get( '_hs_url' ) ); ?>" placeholder="https://"></label></p>
	</div>
	<?php
}

add_action( 'save_post_hamshack_event', 'hamshack_event_save' );
function hamshack_event_save( $post_id ) {
	if ( ! isset( $_POST['hamshack_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_event_nonce'] ), 'hamshack_event_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$dt = function ( $v ) {
		$v = str_replace( 'T', ' ', sanitize_text_field( wp_unslash( $v ) ) );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v ) ? $v : '';
	};
	$start = isset( $_POST['hs_start'] ) ? $dt( $_POST['hs_start'] ) : ''; // phpcs:ignore
	$end   = isset( $_POST['hs_end'] ) ? $dt( $_POST['hs_end'] ) : ''; // phpcs:ignore
	if ( $end && $start && $end < $start ) {
		$end = '';
	}
	update_post_meta( $post_id, '_hs_start', $start );
	update_post_meta( $post_id, '_hs_end', $end );
	update_post_meta( $post_id, '_hs_allday', empty( $_POST['hs_allday'] ) ? 0 : 1 );
	$rep = isset( $_POST['hs_repeat'] ) ? sanitize_key( $_POST['hs_repeat'] ) : 'none';
	update_post_meta( $post_id, '_hs_repeat', in_array( $rep, array( 'none', 'weekly', 'fortnightly', 'monthly' ), true ) ? $rep : 'none' );
	$until = isset( $_POST['hs_repeat_until'] ) ? sanitize_text_field( wp_unslash( $_POST['hs_repeat_until'] ) ) : '';
	update_post_meta( $post_id, '_hs_repeat_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '' );
	foreach ( array( 'location', 'freq', 'mode', 'band' ) as $k ) {
		update_post_meta( $post_id, '_hs_' . $k, isset( $_POST[ 'hs_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hs_' . $k ] ) ) : '' );
	}
	update_post_meta( $post_id, '_hs_url', isset( $_POST['hs_url'] ) ? esc_url_raw( wp_unslash( $_POST['hs_url'] ) ) : '' );
	delete_transient( 'hamshack_events_all' );
}

/* Admin list columns */
add_filter( 'manage_hamshack_event_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['hs_start'] = __( 'When', 'hamshack' );
			$new['hs_freq']  = __( 'Band / mode', 'hamshack' );
		}
	}
	unset( $new['date'] );
	return $new;
} );
add_action( 'manage_hamshack_event_posts_custom_column', function ( $col, $post_id ) {
	if ( 'hs_start' === $col ) {
		$s = hamshack_event_dt( get_post_meta( $post_id, '_hs_start', true ) );
		echo $s ? esc_html( wp_date( 'D j M Y, H:i', $s->getTimestamp() ) ) : '—';
		$rep = get_post_meta( $post_id, '_hs_repeat', true );
		if ( $rep && 'none' !== $rep ) {
			echo '<br><small>↻ ' . esc_html( $rep ) . '</small>';
		}
	}
	if ( 'hs_freq' === $col ) {
		echo esc_html( implode( ' · ', array_filter( array( get_post_meta( $post_id, '_hs_freq', true ), get_post_meta( $post_id, '_hs_band', true ), get_post_meta( $post_id, '_hs_mode', true ) ) ) ) );
	}
}, 10, 2 );
add_filter( 'manage_edit-hamshack_event_sortable_columns', function ( $cols ) {
	$cols['hs_start'] = 'hs_start';
	return $cols;
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'hamshack_event' === $q->get( 'post_type' ) ) {
		if ( ! $q->get( 'orderby' ) || 'hs_start' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_hs_start' );
			$q->set( 'orderby', 'meta_value' );
			if ( ! $q->get( 'order' ) ) {
				$q->set( 'order', 'DESC' );
			}
		}
	}
	// Public archive: we render our own calendar/agenda, keep the main query light.
	if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( 'hamshack_event' ) ) {
		$q->set( 'posts_per_page', 1 );
	}
} );

/* --------------------------------------------------------------------------
 * Data helpers
 * ------------------------------------------------------------------------ */

/**
 * Parse a stored local datetime string.
 */
function hamshack_event_dt( $value ) {
	if ( ! $value ) {
		return null;
	}
	$d = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $value, wp_timezone() );
	return $d ? $d : null;
}

/**
 * All published events (cached).
 */
function hamshack_all_events() {
	$cached = get_transient( 'hamshack_events_all' );
	if ( false !== $cached ) {
		return $cached;
	}
	$ids    = get_posts(
		array(
			'post_type'      => 'hamshack_event',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'fields'         => 'ids',
			'meta_key'       => '_hs_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	$events = array();
	foreach ( $ids as $id ) {
		$terms    = get_the_terms( $id, 'hamshack_event_type' );
		$events[] = array(
			'id'       => $id,
			'title'    => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'start'    => get_post_meta( $id, '_hs_start', true ),
			'end'      => get_post_meta( $id, '_hs_end', true ),
			'allday'   => (bool) get_post_meta( $id, '_hs_allday', true ),
			'repeat'   => get_post_meta( $id, '_hs_repeat', true ) ? get_post_meta( $id, '_hs_repeat', true ) : 'none',
			'until'    => get_post_meta( $id, '_hs_repeat_until', true ),
			'location' => get_post_meta( $id, '_hs_location', true ),
			'freq'     => get_post_meta( $id, '_hs_freq', true ),
			'mode'     => get_post_meta( $id, '_hs_mode', true ),
			'band'     => get_post_meta( $id, '_hs_band', true ),
			'type'     => ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '',
			'type_name'=> ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '',
			'auto'     => (bool) get_post_meta( $id, '_hs_auto', true ),
			'entering' => (bool) get_post_meta( $id, '_hs_entering', true ),
		);
	}
	set_transient( 'hamshack_events_all', $events, HOUR_IN_SECONDS );
	return $events;
}

add_action( 'deleted_post', function () {
	delete_transient( 'hamshack_events_all' );
} );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'hamshack_event' === $post->post_type ) {
		delete_transient( 'hamshack_events_all' );
	}
}, 10, 3 );
add_action( 'set_object_terms', function () {
	delete_transient( 'hamshack_events_all' );
} );

/**
 * Expand events into occurrences within [$from, $to).
 *
 * @param DateTimeImmutable $from Range start.
 * @param DateTimeImmutable $to   Range end.
 * @return array Sorted occurrences (each has 'ts' and 'end_ts').
 */
function hamshack_occurrences( DateTimeImmutable $from, DateTimeImmutable $to, $include_auto = false ) {
	$out = array();
	foreach ( hamshack_all_events() as $e ) {
		// Contests imported from the contest calendar only show up once you mark them "I'm taking part".
		if ( ! empty( $e['auto'] ) && empty( $e['entering'] ) && ! $include_auto ) {
			continue;
		}
		$start = hamshack_event_dt( $e['start'] );
		if ( ! $start ) {
			continue;
		}
		$end      = hamshack_event_dt( $e['end'] );
		$duration = $end ? max( 0, $end->getTimestamp() - $start->getTimestamp() ) : 0;
		$step     = array( 'weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month' );
		$limit    = $to;
		if ( $e['until'] ) {
			$u = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $e['until'] . ' 23:59', wp_timezone() );
			if ( $u && $u < $limit ) {
				$limit = $u;
			}
		}
		$cur   = $start;
		$guard = 0;
		while ( $cur < $limit && $guard < 800 ) {
			$guard++;
			$cur_end = $cur->getTimestamp() + $duration;
			if ( $cur_end >= $from->getTimestamp() || $cur >= $from ) {
				if ( $cur < $to ) {
					$out[] = array_merge( $e, array( 'ts' => $cur->getTimestamp(), 'end_ts' => $duration ? $cur_end : 0 ) );
				}
			}
			if ( 'none' === $e['repeat'] || ! isset( $step[ $e['repeat'] ] ) ) {
				break;
			}
			$cur = $cur->modify( $step[ $e['repeat'] ] );
		}
	}
	usort( $out, function ( $a, $b ) {
		return $a['ts'] <=> $b['ts'];
	} );
	return $out;
}

/**
 * Upcoming occurrences.
 */
function hamshack_upcoming_events( $limit = 5, $days = 120 ) {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	$occ = hamshack_occurrences( $now->modify( '-3 hours' ), $now->modify( '+' . (int) $days . ' days' ) );
	return array_slice( $occ, 0, $limit );
}

/**
 * Human "when" string: local + UTC.
 */
function hamshack_event_when( $occ, $long = false ) {
	$fmt_date = $long ? 'l j F Y' : 'D j M';
	$out      = wp_date( $fmt_date, $occ['ts'] );
	if ( $occ['allday'] ) {
		if ( $occ['end_ts'] && wp_date( 'Ymd', $occ['end_ts'] ) !== wp_date( 'Ymd', $occ['ts'] ) ) {
			$out .= ' – ' . wp_date( $fmt_date, $occ['end_ts'] );
		}
		return $out . ' · ' . __( 'all day', 'hamshack' );
	}
	$out .= ', ' . wp_date( 'H:i', $occ['ts'] );
	if ( $occ['end_ts'] ) {
		$same = wp_date( 'Ymd', $occ['end_ts'] ) === wp_date( 'Ymd', $occ['ts'] );
		$out .= '–' . ( $same ? wp_date( 'H:i', $occ['end_ts'] ) : wp_date( 'D j M H:i', $occ['end_ts'] ) );
	}
	$out .= ' ' . wp_date( 'T', $occ['ts'] );
	$utc  = gmdate( 'H:i', $occ['ts'] ) . ( $occ['end_ts'] ? '–' . gmdate( 'H:i', $occ['end_ts'] ) : '' ) . 'z';
	if ( 'UTC' !== wp_timezone_string() ) {
		$out .= ' (' . $utc . ')';
	}
	return $out;
}

/**
 * Chip classes by type slug.
 */
function hamshack_type_class( $slug ) {
	return 'type-' . sanitize_html_class( $slug ? $slug : 'general' );
}

/* --------------------------------------------------------------------------
 * Front-end rendering
 * ------------------------------------------------------------------------ */

/**
 * Month calendar HTML.
 *
 * @param array $atts month (YYYY-MM), nav (bool).
 */
function hamshack_calendar_html( $atts = array() ) {
	$atts  = shortcode_atts( array( 'month' => '', 'nav' => '1', 'agenda' => '1' ), $atts, 'hamshack_calendar' );
	$tz    = wp_timezone();
	$month = $atts['month'];
	if ( isset( $_GET['cal'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['cal'] ) ) ) ) { // phpcs:ignore
		$month = sanitize_text_field( wp_unslash( $_GET['cal'] ) ); // phpcs:ignore
	}
	$first = $month ? DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', $tz ) : null;
	if ( ! $first ) {
		$first = new DateTimeImmutable( 'first day of this month 00:00', $tz );
	}
	$next      = $first->modify( '+1 month' );
	$prev      = $first->modify( '-1 month' );
	$occ       = hamshack_occurrences( $first, $next );
	$by_day    = array();
	foreach ( $occ as $o ) {
		$by_day[ (int) wp_date( 'j', $o['ts'] ) ][] = $o;
		// Multi-day events: add to following days too.
		if ( $o['end_ts'] ) {
			$d = ( new DateTimeImmutable( '@' . $o['ts'] ) )->setTimezone( $tz )->setTime( 0, 0 )->modify( '+1 day' );
			while ( $d->getTimestamp() < $o['end_ts'] && $d < $next ) {
				$by_day[ (int) $d->format( 'j' ) ][] = array_merge( $o, array( 'continued' => true ) );
				$d = $d->modify( '+1 day' );
			}
		}
	}
	$today     = wp_date( 'Y-m-j' );
	$lead      = ( (int) $first->format( 'N' ) ) - 1; // Monday-first.
	$days      = (int) $first->format( 't' );
	$base      = remove_query_arg( 'cal' );
	$uid       = 'cal-' . $first->format( 'Ym' );
	ob_start();
	?>
	<div class="hs-calendar" id="<?php echo esc_attr( $uid ); ?>">
		<div class="cal-head">
			<?php if ( '1' === $atts['nav'] ) : ?>
				<a class="btn btn-ghost btn-icon" href="<?php echo esc_url( add_query_arg( 'cal', $prev->format( 'Y-m' ), $base ) . '#' . 'cal-' . $prev->format( 'Ym' ) ); ?>" aria-label="<?php esc_attr_e( 'Previous month', 'hamshack' ); ?>"><?php hamshack_the_icon( 'arrow-l', 18 ); ?></a>
			<?php endif; ?>
			<h2 class="cal-title"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></h2>
			<?php if ( '1' === $atts['nav'] ) : ?>
				<a class="btn btn-ghost btn-icon" href="<?php echo esc_url( add_query_arg( 'cal', $next->format( 'Y-m' ), $base ) . '#' . 'cal-' . $next->format( 'Ym' ) ); ?>" aria-label="<?php esc_attr_e( 'Next month', 'hamshack' ); ?>"><?php hamshack_the_icon( 'arrow-r', 18 ); ?></a>
				<a class="btn btn-ghost btn-sm cal-today" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Today', 'hamshack' ); ?></a>
			<?php endif; ?>
			<a class="btn btn-ghost btn-sm cal-ics" href="<?php echo esc_url( home_url( '/?hamshack_ics=all' ) ); ?>" title="<?php esc_attr_e( 'Subscribe in Google/Apple/Outlook calendar', 'hamshack' ); ?>"><?php hamshack_the_icon( 'download', 16 ); ?> iCal</a>
		</div>
		<div class="cal-grid" role="grid" aria-label="<?php echo esc_attr( wp_date( 'F Y', $first->getTimestamp() ) ); ?>">
			<?php
			$labels = array( __( 'Mon', 'hamshack' ), __( 'Tue', 'hamshack' ), __( 'Wed', 'hamshack' ), __( 'Thu', 'hamshack' ), __( 'Fri', 'hamshack' ), __( 'Sat', 'hamshack' ), __( 'Sun', 'hamshack' ) );
			foreach ( $labels as $l ) {
				echo '<div class="cal-dow" role="columnheader">' . esc_html( $l ) . '</div>';
			}
			for ( $i = 0; $i < $lead; $i++ ) {
				echo '<div class="cal-cell is-empty" aria-hidden="true"></div>';
			}
			for ( $d = 1; $d <= $days; $d++ ) {
				$key     = $first->format( 'Y-m-' ) . $d;
				$has     = ! empty( $by_day[ $d ] );
				$classes = 'cal-cell' . ( $key === $today ? ' is-today' : '' ) . ( $has ? ' has-events' : '' );
				echo '<div class="' . esc_attr( $classes ) . '" role="gridcell"><span class="cal-day">' . (int) $d . '</span>';
				if ( $has ) {
					echo '<ul class="cal-events">';
					foreach ( $by_day[ $d ] as $o ) {
						$time = ( $o['allday'] || ! empty( $o['continued'] ) ) ? '' : '<b>' . esc_html( wp_date( 'H:i', $o['ts'] ) ) . '</b> ';
						echo '<li><a class="chip ' . esc_attr( hamshack_type_class( $o['type'] ) . ( ! empty( $o['continued'] ) ? ' is-cont' : '' ) ) . '" href="' . esc_url( $o['url'] ) . '" title="' . esc_attr( $o['title'] . ' – ' . hamshack_event_when( $o ) ) . '">' . $time . esc_html( $o['title'] ) . '</a></li>'; // phpcs:ignore
					}
					echo '</ul>';
				}
				echo '</div>';
			}
			$trail = ( 7 - ( ( $lead + $days ) % 7 ) ) % 7;
			for ( $i = 0; $i < $trail; $i++ ) {
				echo '<div class="cal-cell is-empty" aria-hidden="true"></div>';
			}
			?>
		</div>
		<?php
		$types = get_terms( array( 'taxonomy' => 'hamshack_event_type', 'hide_empty' => true ) );
		if ( $types && ! is_wp_error( $types ) ) :
			?>
			<ul class="cal-legend">
				<?php foreach ( $types as $t ) : ?>
					<li><a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><span class="swatch <?php echo esc_attr( hamshack_type_class( $t->slug ) ); ?>"></span><?php echo esc_html( $t->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '1' === $atts['agenda'] ) : ?>
			<div class="cal-agenda">
				<h3><?php printf( esc_html__( 'Agenda – %s', 'hamshack' ), esc_html( wp_date( 'F Y', $first->getTimestamp() ) ) ); ?></h3>
				<?php if ( $occ ) : ?>
					<?php echo hamshack_event_list_html( $occ ); // phpcs:ignore ?>
				<?php else : ?>
					<p class="muted"><?php esc_html_e( 'Nothing scheduled this month yet – check back soon or listen out on the bands!', 'hamshack' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'hamshack_calendar', 'hamshack_calendar_html' );

/**
 * Event list markup.
 */
function hamshack_event_list_html( $occ, $compact = false ) {
	ob_start();
	echo '<ul class="event-list' . ( $compact ? ' is-compact' : '' ) . '">';
	foreach ( $occ as $o ) {
		?>
		<li class="event-item <?php echo esc_attr( hamshack_type_class( $o['type'] ) ); ?>">
			<a href="<?php echo esc_url( $o['url'] ); ?>" class="event-date" aria-hidden="true" tabindex="-1">
				<span class="m"><?php echo esc_html( wp_date( 'M', $o['ts'] ) ); ?></span>
				<span class="d"><?php echo esc_html( wp_date( 'j', $o['ts'] ) ); ?></span>
				<span class="w"><?php echo esc_html( wp_date( 'D', $o['ts'] ) ); ?></span>
			</a>
			<div class="event-body">
				<h3 class="event-title"><a href="<?php echo esc_url( $o['url'] ); ?>"><?php echo esc_html( $o['title'] ); ?></a></h3>
				<p class="event-when"><?php hamshack_the_icon( 'clock', 14 ); ?> <?php echo esc_html( hamshack_event_when( $o ) ); ?></p>
				<p class="event-tags">
					<?php if ( $o['type_name'] ) : ?><span class="chip <?php echo esc_attr( hamshack_type_class( $o['type'] ) ); ?>"><?php echo esc_html( $o['type_name'] ); ?></span><?php endif; ?>
					<?php if ( $o['freq'] ) : ?><span class="tag mono"><?php echo esc_html( $o['freq'] ); ?> MHz</span><?php endif; ?>
					<?php if ( $o['band'] ) : ?><span class="tag"><?php echo esc_html( $o['band'] ); ?></span><?php endif; ?>
					<?php if ( $o['mode'] ) : ?><span class="tag"><?php echo esc_html( $o['mode'] ); ?></span><?php endif; ?>
					<?php if ( ! $compact && $o['location'] ) : ?><span class="tag"><?php hamshack_the_icon( 'pin', 13 ); ?> <?php echo esc_html( $o['location'] ); ?></span><?php endif; ?>
				</p>
			</div>
		</li>
		<?php
	}
	echo '</ul>';
	return ob_get_clean();
}

add_shortcode( 'hamshack_events', function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 5, 'days' => 120, 'compact' => '0' ), $atts, 'hamshack_events' );
	$occ  = hamshack_upcoming_events( (int) $atts['limit'], (int) $atts['days'] );
	if ( ! $occ ) {
		return '<p class="muted">' . esc_html__( 'No upcoming events scheduled.', 'hamshack' ) . '</p>';
	}
	return hamshack_event_list_html( $occ, '1' === $atts['compact'] );
} );

/**
 * Next occurrence for a single event (used on the single template).
 */
function hamshack_event_next_occurrence( $post_id ) {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	foreach ( hamshack_occurrences( $now->modify( '-6 hours' ), $now->modify( '+2 years' ) ) as $o ) {
		if ( (int) $o['id'] === (int) $post_id ) {
			return $o;
		}
	}
	// Past event: return its original occurrence.
	foreach ( hamshack_all_events() as $e ) {
		if ( (int) $e['id'] === (int) $post_id ) {
			$s = hamshack_event_dt( $e['start'] );
			$x = hamshack_event_dt( $e['end'] );
			if ( $s ) {
				return array_merge( $e, array( 'ts' => $s->getTimestamp(), 'end_ts' => $x ? $x->getTimestamp() : 0, 'past' => true ) );
			}
		}
	}
	return null;
}

/**
 * Google Calendar "add" link.
 */
function hamshack_gcal_link( $o ) {
	$start = $o['allday'] ? gmdate( 'Ymd', $o['ts'] ) : gmdate( 'Ymd\THis\Z', $o['ts'] );
	$end_t = $o['end_ts'] ? $o['end_ts'] : $o['ts'] + ( $o['allday'] ? DAY_IN_SECONDS : HOUR_IN_SECONDS );
	$end   = $o['allday'] ? gmdate( 'Ymd', $end_t + ( $o['end_ts'] ? DAY_IN_SECONDS : 0 ) ) : gmdate( 'Ymd\THis\Z', $end_t );
	return add_query_arg(
		array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $o['title'] ),
			'dates'    => $start . '/' . $end,
			'details'  => rawurlencode( trim( ( $o['freq'] ? $o['freq'] . ' MHz ' : '' ) . $o['mode'] . ' – ' . $o['url'] ) ),
			'location' => rawurlencode( $o['location'] ),
		),
		'https://calendar.google.com/calendar/render'
	);
}

/* --------------------------------------------------------------------------
 * iCal feed:  /?hamshack_ics=all   or   /?hamshack_ics=<post id>
 * ------------------------------------------------------------------------ */

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'hamshack_ics';
	return $vars;
} );

add_action( 'template_redirect', 'hamshack_ics_output' );
function hamshack_ics_output() {
	$which = get_query_var( 'hamshack_ics' );
	if ( ! $which ) {
		return;
	}
	$events = hamshack_all_events();
	if ( 'all' !== $which ) {
		$events = array_filter( $events, function ( $e ) use ( $which ) {
			return (int) $e['id'] === (int) $which;
		} );
	}
	$esc   = function ( $s ) {
		return addcslashes( wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ), ",;\\\n" );
	};
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//' . $esc( hamshack_opt( 'callsign' ) ) . '//Shack calendar//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . $esc( hamshack_opt( 'callsign' ) . ' – ' . __( 'Radio events', 'hamshack' ) ),
		'X-WR-TIMEZONE:' . $esc( wp_timezone_string() ),
	);
	foreach ( $events as $e ) {
		$s = hamshack_event_dt( $e['start'] );
		if ( ! $s ) {
			continue;
		}
		$x = hamshack_event_dt( $e['end'] );
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:hamshack-event-' . $e['id'] . '@' . $host;
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
		if ( $e['allday'] ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . $s->format( 'Ymd' );
			$lines[] = 'DTEND;VALUE=DATE:' . ( $x ? $x->modify( '+1 day' )->format( 'Ymd' ) : $s->modify( '+1 day' )->format( 'Ymd' ) );
		} else {
			// Local wall-clock time with an IANA TZID keeps weekly nets correct across BST/GMT changes.
			$tzid    = wp_timezone_string();
			$tzid    = preg_match( '/^[A-Za-z_]+\/[A-Za-z_\/]+$/', $tzid ) ? $tzid : '';
			$fin     = $x ? $x : $s->modify( '+1 hour' );
			$lines[] = $tzid ? 'DTSTART;TZID=' . $tzid . ':' . $s->format( 'Ymd\THis' ) : 'DTSTART:' . gmdate( 'Ymd\THis\Z', $s->getTimestamp() );
			$lines[] = $tzid ? 'DTEND;TZID=' . $tzid . ':' . $fin->format( 'Ymd\THis' ) : 'DTEND:' . gmdate( 'Ymd\THis\Z', $fin->getTimestamp() );
		}
		if ( 'none' !== $e['repeat'] ) {
			$rule = array( 'weekly' => 'FREQ=WEEKLY', 'fortnightly' => 'FREQ=WEEKLY;INTERVAL=2', 'monthly' => 'FREQ=MONTHLY' );
			if ( isset( $rule[ $e['repeat'] ] ) ) {
				$r = 'RRULE:' . $rule[ $e['repeat'] ];
				if ( $e['until'] ) {
					$r .= ';UNTIL=' . str_replace( '-', '', $e['until'] ) . 'T235959Z';
				}
				$lines[] = $r;
			}
		}
		$lines[] = 'SUMMARY:' . $esc( $e['title'] );
		$desc    = trim( ( $e['freq'] ? $e['freq'] . ' MHz ' : '' ) . ( $e['band'] ? $e['band'] . ' ' : '' ) . $e['mode'] );
		$lines[] = 'DESCRIPTION:' . $esc( ( $desc ? $desc . ' – ' : '' ) . $e['url'] );
		if ( $e['location'] ) {
			$lines[] = 'LOCATION:' . $esc( $e['location'] );
		}
		$lines[] = 'URL:' . esc_url_raw( $e['url'] );
		$lines[] = 'END:VEVENT';
	}
	$lines[] = 'END:VCALENDAR';

	// Fold lines > 75 octets per RFC 5545.
	$folded = array();
	foreach ( $lines as $l ) {
		while ( strlen( $l ) > 75 ) {
			$cut = 75;
			while ( $cut > 1 && ( ord( $l[ $cut ] ) & 0xC0 ) === 0x80 ) {
				$cut--;
			}
			$folded[] = substr( $l, 0, $cut );
			$l        = ' ' . substr( $l, $cut );
		}
		$folded[] = $l;
	}
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( strtolower( hamshack_opt( 'callsign' ) ) . ( 'all' === $which ? '-events' : '-event-' . (int) $which ) ) . '.ics"' );
	echo implode( "\r\n", $folded ) . "\r\n"; // phpcs:ignore
	exit;
}
