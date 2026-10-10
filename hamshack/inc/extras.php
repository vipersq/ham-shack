<?php
/**
 * Satellites (pass predictions), POTA/SOTA activations and the photo gallery.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * Satellites
 * ======================================================================== */

/**
 * Amateur satellites shown by default: NORAD id => [ short name, what to listen for ].
 */
function hamshack_sat_list() {
	return apply_filters(
		'hamshack_satellites',
		array(
			25544 => array( 'ISS', __( 'FM voice repeater, APRS 145.825, SSTV events', 'hamshack' ) ),
			27607 => array( 'SO-50', __( 'FM repeater · up 145.850 (67 Hz) / down 436.795', 'hamshack' ) ),
			44909 => array( 'RS-44', __( 'Linear transponder · up 145.935–145.995 / down 435.610–435.670', 'hamshack' ) ),
			7530  => array( 'AO-7', __( 'Linear transponder (mode B) – the 1974 veteran', 'hamshack' ) ),
			24278 => array( 'FO-29', __( 'Linear transponder · down 435.800–435.900', 'hamshack' ) ),
			39444 => array( 'AO-73', __( 'FUNcube telemetry 145.935 BPSK, transponder at night', 'hamshack' ) ),
			53109 => array( 'IO-117', __( 'GreenCube digipeater 435.310', 'hamshack' ) ),
			61781 => array( 'AO-123', __( 'FM repeater · up 145.850 / down 435.400', 'hamshack' ) ),
			22825 => array( 'AO-27', __( 'FM repeater (part-time)', 'hamshack' ) ),
			50466 => array( 'CAS-9', __( 'Linear transponder · down 435.180', 'hamshack' ) ),
		)
	);
}

/**
 * Current TLEs (CelesTrak amateur group), refreshed every 12 h, last good copy kept.
 *
 * @return array [ norad => [ name, line1, line2 ] ]
 */
function hamshack_sat_tles() {
	$cached = get_transient( 'hamshack_tle' );
	if ( false !== $cached ) {
		return $cached;
	}
	$out  = array();
	$want = hamshack_sat_list();
	// CelesTrak first, AMSAT as a fallback (CelesTrak limits repeated downloads).
	foreach ( array( 'https://celestrak.org/NORAD/elements/gp.php?GROUP=amateur&FORMAT=tle', 'https://www.amsat.org/tle/current/nasabare.txt' ) as $src ) {
		if ( count( $out ) >= count( $want ) ) {
			break;
		}
		$res = wp_remote_get( $src, array( 'timeout' => 20, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			continue;
		}
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\n/', wp_remote_retrieve_body( $res ) ) ), 'strlen' ) );
		for ( $i = 0; $i + 2 < count( $lines ); $i++ ) {
			if ( ! isset( $lines[ $i + 2 ] ) || '1 ' !== substr( $lines[ $i + 1 ], 0, 2 ) || '2 ' !== substr( $lines[ $i + 2 ], 0, 2 ) ) {
				continue;
			}
			$id = (int) substr( $lines[ $i + 1 ], 2, 5 );
			if ( isset( $want[ $id ] ) && ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array( $want[ $id ][0], $lines[ $i + 1 ], $lines[ $i + 2 ] );
			}
		}
	}
	if ( $out ) {
		update_option( 'hamshack_tle_last', array( 'ts' => time(), 'tle' => $out ), false );
		set_transient( 'hamshack_tle', $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}
	$last = get_option( 'hamshack_tle_last' );
	set_transient( 'hamshack_tle', $last ? $last['tle'] : array(), 15 * MINUTE_IN_SECONDS );
	return $last ? $last['tle'] : array();
}

/**
 * [hamshack_satellites] – next passes over the station and live positions.
 */
add_shortcode( 'hamshack_satellites', function () {
	hamshack_enqueue_map();
	wp_enqueue_script( 'satellite-js', HAMSHACK_URI . '/assets/js/vendor/satellite.min.js', array(), '5.0.0', true );
	wp_enqueue_script( 'hamshack-sats', HAMSHACK_URI . '/assets/js/sats.js', array( 'hamshack-map', 'satellite-js' ), HAMSHACK_VERSION, true );
	$tles = hamshack_sat_tles();
	$info = hamshack_sat_list();
	$sats = array();
	foreach ( $info as $id => $i ) {
		if ( isset( $tles[ $id ] ) ) {
			$sats[] = array( 'id' => $id, 'name' => $i[0], 'info' => $i[1], 'l1' => $tles[ $id ][1], 'l2' => $tles[ $id ][2] );
		}
	}
	wp_localize_script(
		'hamshack-sats',
		'M6SATS',
		array(
			'sats' => $sats,
			'tz'   => wp_timezone_string(),
			'i18n' => array(
				'now'      => __( 'Overhead now!', 'hamshack' ),
				'in'       => __( 'in %s', 'hamshack' ),
				'h'        => __( 'h', 'hamshack' ),
				'm'        => __( 'min', 'hamshack' ),
				'none'     => __( 'No passes above the minimum elevation in the next 48 hours.', 'hamshack' ),
				'nodata'   => __( 'Orbit data is not available right now – try again later.', 'hamshack' ),
				'max'      => __( 'max', 'hamshack' ),
				'alt'      => __( 'altitude', 'hamshack' ),
				'el'       => __( 'elevation', 'hamshack' ),
				'az'       => __( 'azimuth', 'hamshack' ),
			),
		)
	);
	ob_start();
	?>
	<div class="hs-app sats-app" data-hs-sats>
		<div class="app-toolbar">
			<div class="chips" data-sats></div>
			<label class="map-select"><span><?php esc_html_e( 'Min. elevation', 'hamshack' ); ?></span>
				<select data-minel><option value="0">0°</option><option value="10" selected>10°</option><option value="20">20°</option><option value="30">30°</option></select>
			</label>
		</div>
		<div class="sats-grid">
			<div data-hs-map></div>
			<div class="panel sat-next" data-next></div>
		</div>
		<h3 class="mt"><?php printf( esc_html__( 'Passes over %1$s (%2$s) in the next 48 hours', 'hamshack' ), esc_html( hamshack_opt( 'callsign' ) ), esc_html( hamshack_opt( 'locator' ) ) ); ?></h3>
		<div class="table-scroll tall">
			<table class="data-table sat-table">
				<thead><tr><th><?php esc_html_e( 'Satellite', 'hamshack' ); ?></th><th><?php esc_html_e( 'Start (local)', 'hamshack' ); ?></th><th><?php esc_html_e( 'Max el.', 'hamshack' ); ?></th><th><?php esc_html_e( 'Duration', 'hamshack' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Rises / sets', 'hamshack' ); ?></th></tr></thead>
				<tbody data-body></tbody>
			</table>
		</div>
		<p class="app-note"><?php esc_html_e( 'Orbits from CelesTrak, calculated in your browser with satellite.js (SGP4). Times are local to the station; frequencies are the usual published ones – always check the latest status at AMSAT before a pass.', 'hamshack' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
 * Activations (POTA / SOTA / WWFF …)
 * ======================================================================== */

add_action( 'init', function () {
	register_post_type(
		'hamshack_activation',
		array(
			'labels'       => array(
				'name'          => __( 'Activations', 'hamshack' ),
				'singular_name' => __( 'Activation', 'hamshack' ),
				'add_new'       => __( 'Add activation', 'hamshack' ),
				'add_new_item'  => __( 'Add a new activation', 'hamshack' ),
				'edit_item'     => __( 'Edit activation', 'hamshack' ),
				'all_items'     => __( 'Activations', 'hamshack' ),
				'not_found'     => __( 'No activations yet', 'hamshack' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'activation' ),
			'show_in_menu' => 'hamshack',
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-location-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		)
	);
} );

/**
 * Programmes.
 */
function hamshack_programmes() {
	return array(
		'POTA'  => 'Parks on the Air',
		'SOTA'  => 'Summits on the Air',
		'WWFF'  => 'World Wide Flora & Fauna',
		'IOTA'  => 'Islands on the Air',
		'Other' => __( 'Other / portable', 'hamshack' ),
	);
}

function hamshack_act( $id ) {
	$g = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_a_' . $k, true );
	};
	$ref  = strtoupper( (string) $g( 'ref' ) );
	$ll   = null;
	if ( is_numeric( $g( 'lat' ) ) && is_numeric( $g( 'lon' ) ) && '' !== $g( 'lat' ) ) {
		$ll = array( (float) $g( 'lat' ), (float) $g( 'lon' ) );
	} elseif ( $g( 'grid' ) ) {
		$ll = hamshack_grid_to_latlon( $g( 'grid' ) );
	}
	return array(
		'prog' => $g( 'prog' ) ? $g( 'prog' ) : 'POTA',
		'ref'  => $ref,
		'date' => $g( 'date' ),
		'grid' => $g( 'grid' ),
		'll'   => $ll,
		'qsos' => $ref ? hamshack_ref_qso_count( $ref ) : 0,
	);
}

/**
 * QSOs logged with MY_POTA_REF / MY_SOTA_REF … = this reference.
 */
function hamshack_ref_qso_count( $ref ) {
	$counts = hamshack_ref_counts();
	return isset( $counts[ strtoupper( $ref ) ] ) ? (int) $counts[ strtoupper( $ref ) ]['n'] : 0;
}

/**
 * All "my references" in the logbook: ref => [ n, first, last ].
 */
function hamshack_ref_counts() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT UPPER(r.meta_value) AS ref, COUNT(*) AS n, MIN(d.meta_value) AS first, MAX(d.meta_value) AS last FROM {$wpdb->postmeta} r JOIN {$wpdb->posts} p ON p.ID = r.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish' JOIN {$wpdb->postmeta} d ON d.post_id = r.post_id AND d.meta_key = '_q_date' WHERE r.meta_key = '_q_my_ref' AND r.meta_value <> '' GROUP BY UPPER(r.meta_value)", ARRAY_A ); // phpcs:ignore
	$c    = array();
	foreach ( $rows as $r ) {
		$c[ $r['ref'] ] = $r;
	}
	return $c;
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_act_box', __( 'Activation details', 'hamshack' ), function ( $post ) {
		wp_nonce_field( 'hamshack_act_save', 'hamshack_act_nonce' );
		$g = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_a_' . $k, true );
		};
		?>
		<div class="hamshack-grid">
			<p><label><strong><?php esc_html_e( 'Programme', 'hamshack' ); ?></strong><br><select name="a[prog]">
				<?php foreach ( hamshack_programmes() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $g( 'prog' ), $k ); ?>><?php echo esc_html( $k . ' – ' . $l ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
			<p><label><strong><?php esc_html_e( 'Reference', 'hamshack' ); ?></strong><br><input type="text" name="a[ref]" value="<?php echo esc_attr( $g( 'ref' ) ); ?>" placeholder="GB-0123 / G/SP-004" style="text-transform:uppercase;font-family:monospace"></label></p>
			<p><label><strong><?php esc_html_e( 'Date', 'hamshack' ); ?></strong><br><input type="date" name="a[date]" value="<?php echo esc_attr( $g( 'date' ) ? $g( 'date' ) : gmdate( 'Y-m-d' ) ); ?>"></label></p>
			<p><label><strong><?php esc_html_e( 'Locator (6 characters)', 'hamshack' ); ?></strong><br><input type="text" name="a[grid]" value="<?php echo esc_attr( $g( 'grid' ) ); ?>" placeholder="IO91ab"></label></p>
			<p><label><strong><?php esc_html_e( 'Latitude', 'hamshack' ); ?></strong><br><input type="text" name="a[lat]" value="<?php echo esc_attr( $g( 'lat' ) ); ?>" placeholder="51.0688"></label></p>
			<p><label><strong><?php esc_html_e( 'Longitude', 'hamshack' ); ?></strong><br><input type="text" name="a[lon]" value="<?php echo esc_attr( $g( 'lon' ) ); ?>" placeholder="-1.7945"></label></p>
		</div>
		<p class="description"><?php esc_html_e( 'Give either the locator or exact coordinates (right-click a spot in Google Maps to copy them). QSOs are counted automatically from the logbook when they carry this reference as MY_POTA_REF / MY_SOTA_REF / MY_WWFF_REF (set it in your logging program or on QRZ.com).', 'hamshack' ); ?></p>
		<?php
	}, 'hamshack_activation', 'normal', 'high' );
} );

add_action( 'save_post_hamshack_activation', function ( $post_id ) {
	if ( ! isset( $_POST['hamshack_act_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_act_nonce'] ), 'hamshack_act_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$a = isset( $_POST['a'] ) ? wp_unslash( (array) $_POST['a'] ) : array(); // phpcs:ignore
	update_post_meta( $post_id, '_a_prog', isset( $a['prog'] ) && isset( hamshack_programmes()[ $a['prog'] ] ) ? $a['prog'] : 'POTA' );
	update_post_meta( $post_id, '_a_ref', strtoupper( sanitize_text_field( isset( $a['ref'] ) ? $a['ref'] : '' ) ) );
	update_post_meta( $post_id, '_a_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', isset( $a['date'] ) ? $a['date'] : '' ) ? $a['date'] : '' );
	$grid = isset( $a['grid'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', $a['grid'] ) : '';
	update_post_meta( $post_id, '_a_grid', hamshack_valid_grid( $grid ) ? strtoupper( substr( $grid, 0, 4 ) ) . strtolower( substr( $grid, 4 ) ) : '' );
	foreach ( array( 'lat' => 90, 'lon' => 180 ) as $k => $lim ) {
		$v = isset( $a[ $k ] ) ? str_replace( ',', '.', trim( $a[ $k ] ) ) : '';
		update_post_meta( $post_id, '_a_' . $k, is_numeric( $v ) && abs( (float) $v ) <= $lim ? (string) (float) $v : '' );
	}
} );

add_filter( 'manage_hamshack_activation_posts_columns', function ( $c ) {
	return array_merge( array_slice( $c, 0, 2 ), array( 'a_ref' => __( 'Reference', 'hamshack' ), 'a_date' => __( 'Date', 'hamshack' ), 'a_qsos' => __( 'QSOs', 'hamshack' ) ), array_slice( $c, 2 ) );
} );
add_action( 'manage_hamshack_activation_posts_custom_column', function ( $col, $id ) {
	$a = hamshack_act( $id );
	if ( 'a_ref' === $col ) {
		echo esc_html( $a['prog'] . ' ' . $a['ref'] );
	} elseif ( 'a_date' === $col ) {
		echo esc_html( $a['date'] );
	} elseif ( 'a_qsos' === $col ) {
		echo (int) $a['qsos'];
	}
}, 10, 2 );

/**
 * Details card above the activation's own text.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'hamshack_activation' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$id    = get_the_ID();
	$a     = hamshack_act( $id );
	$progs = hamshack_programmes();
	$link  = '';
	if ( 'POTA' === $a['prog'] && $a['ref'] ) {
		$link = 'https://pota.app/#/park/' . rawurlencode( $a['ref'] );
	} elseif ( 'SOTA' === $a['prog'] && $a['ref'] ) {
		$link = 'https://www.sotadata.org.uk/en/summit/' . str_replace( '%2F', '/', rawurlencode( $a['ref'] ) );
	} elseif ( 'WWFF' === $a['prog'] && $a['ref'] ) {
		$link = 'https://wwff.co/directory/?showRef=' . rawurlencode( $a['ref'] );
	}
	$card  = '<div class="hs-app act-card panel"><div class="act-meta">';
	$card .= '<span class="tag tag-alt mono">' . esc_html( $a['prog'] ) . '</span> <strong class="mono act-ref">' . esc_html( $a['ref'] ) . '</strong>';
	$card .= '<span class="muted">' . esc_html( isset( $progs[ $a['prog'] ] ) ? $progs[ $a['prog'] ] : '' ) . '</span>';
	$card .= '</div><ul class="act-facts">';
	if ( $a['date'] ) {
		$card .= '<li>' . hamshack_icon( 'calendar', 16 ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $a['date'] ) ) ) . '</li>';
	}
	if ( $a['grid'] ) {
		$card .= '<li>' . hamshack_icon( 'pin', 16 ) . ' <span class="mono">' . esc_html( $a['grid'] ) . '</span></li>';
	}
	$card .= '<li>' . hamshack_icon( 'radio', 16 ) . ' ' . esc_html( sprintf( _n( '%d QSO', '%d QSOs', $a['qsos'], 'hamshack' ), $a['qsos'] ) ) . '</li>';
	if ( $link ) {
		$card .= '<li><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html__( 'Reference page', 'hamshack' ) . ' ↗</a></li>';
	}
	$card .= '</ul></div>';
	if ( $a['ll'] ) {
		hamshack_enqueue_map();
		$items = array( array( 'lat' => $a['ll'][0], 'lon' => $a['ll'][1], 'ref' => $a['ref'], 'name' => get_the_title(), 'prog' => $a['prog'], 'date' => $a['date'], 'qsos' => $a['qsos'] ) );
		$card .= '<div class="hs-app act-map" data-hs-activations data-items="' . esc_attr( wp_json_encode( $items ) ) . '"><div data-hs-map></div></div>';
	}
	return $card . $content;
}, 5 );

/**
 * [hamshack_activations] – map + list of all activations.
 */
add_shortcode( 'hamshack_activations', function () {
	$posts  = get_posts( array( 'post_type' => 'hamshack_activation', 'posts_per_page' => 300, 'meta_key' => '_a_date', 'orderby' => 'meta_value', 'order' => 'DESC' ) ); // phpcs:ignore
	$items  = array();
	$known  = array();
	$total  = 0;
	foreach ( $posts as $p ) {
		$a                   = hamshack_act( $p->ID );
		$known[ $a['ref'] ]  = 1;
		$total              += $a['qsos'];
		if ( $a['ll'] ) {
			$items[] = array( 'lat' => $a['ll'][0], 'lon' => $a['ll'][1], 'ref' => $a['ref'], 'name' => $p->post_title, 'prog' => $a['prog'], 'date' => $a['date'], 'qsos' => $a['qsos'], 'url' => get_permalink( $p ) );
		}
	}
	$from_log = array_diff_key( hamshack_ref_counts(), $known );
	foreach ( $from_log as $r ) {
		$total += (int) $r['n'];
	}
	hamshack_enqueue_map();
	ob_start();
	?>
	<div class="hs-app acts-app">
		<div class="award-hero acts-stats">
			<div class="hero-stat"><span class="num mono"><?php echo (int) ( count( $posts ) + count( $from_log ) ); ?></span><span class="lbl"><?php esc_html_e( 'Activations', 'hamshack' ); ?></span></div>
			<div class="hero-stat"><span class="num mono"><?php echo (int) count( array_unique( array_merge( array_keys( $known ), array_keys( $from_log ) ) ) ); ?></span><span class="lbl"><?php esc_html_e( 'Different references', 'hamshack' ); ?></span></div>
			<div class="hero-stat"><span class="num mono"><?php echo esc_html( number_format_i18n( $total ) ); ?></span><span class="lbl"><?php esc_html_e( 'QSOs from the field', 'hamshack' ); ?></span></div>
		</div>
		<div class="act-map" data-hs-activations data-items="<?php echo esc_attr( wp_json_encode( $items ) ); ?>"><div data-hs-map></div></div>
		<?php if ( $posts ) : ?>
			<div class="act-grid">
				<?php foreach ( $posts as $p ) : $a = hamshack_act( $p->ID ); ?>
					<a class="act-item panel" href="<?php echo esc_url( get_permalink( $p ) ); ?>">
						<?php if ( has_post_thumbnail( $p ) ) : ?>
							<span class="act-thumb"><?php echo get_the_post_thumbnail( $p, 'medium_large', array( 'loading' => 'lazy' ) ); ?></span>
						<?php endif; ?>
						<span class="act-top"><span class="tag tag-alt mono"><?php echo esc_html( $a['prog'] ); ?></span> <span class="mono act-ref"><?php echo esc_html( $a['ref'] ); ?></span></span>
						<strong class="act-title"><?php echo esc_html( get_the_title( $p ) ); ?></strong>
						<span class="muted small"><?php echo esc_html( $a['date'] ? date_i18n( get_option( 'date_format' ), strtotime( $a['date'] ) ) : '' ); ?><?php echo $a['qsos'] ? ' · ' . esc_html( sprintf( _n( '%d QSO', '%d QSOs', $a['qsos'], 'hamshack' ), $a['qsos'] ) ) : ''; ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $from_log ) : ?>
			<h3 class="mt"><?php esc_html_e( 'More references from the logbook', 'hamshack' ); ?></h3>
			<div class="table-scroll">
				<table class="data-table">
					<thead><tr><th><?php esc_html_e( 'Reference', 'hamshack' ); ?></th><th><?php esc_html_e( 'QSOs', 'hamshack' ); ?></th><th><?php esc_html_e( 'Dates', 'hamshack' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $from_log as $r ) : ?>
						<tr><td class="mono call"><?php echo esc_html( $r['ref'] ); ?></td><td class="mono"><?php echo (int) $r['n']; ?></td><td class="mono muted"><?php echo esc_html( $r['first'] === $r['last'] ? $r['first'] : $r['first'] . ' – ' . $r['last'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if ( ! $posts && ! $from_log ) : ?>
			<div class="empty-state"><?php esc_html_e( 'No activations yet – the first park or summit is coming soon!', 'hamshack' ); ?></div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
 * Gallery
 * ======================================================================== */

add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	if ( ! wp_attachment_is_image( $post->ID ) ) {
		return $fields;
	}
	$on = (bool) get_post_meta( $post->ID, '_m6_gallery', true );
	$fields['m6_gallery'] = array(
		'label' => __( 'Site gallery', 'hamshack' ),
		'input' => 'html',
		'html'  => '<label><input type="checkbox" name="attachments[' . $post->ID . '][m6_gallery]" value="1" ' . checked( $on, true, false ) . '> ' . esc_html__( 'Show in the gallery', 'hamshack' ) . '</label>',
	);
	$fields['m6_album'] = array(
		'label' => __( 'Album', 'hamshack' ),
		'input' => 'text',
		'value' => get_post_meta( $post->ID, '_m6_album', true ),
		'helps' => __( 'e.g. Shack, Antennas, Portable, Events', 'hamshack' ),
	);
	return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', function ( $post, $att ) {
	update_post_meta( $post['ID'], '_m6_gallery', empty( $att['m6_gallery'] ) ? '' : '1' );
	if ( isset( $att['m6_album'] ) ) {
		update_post_meta( $post['ID'], '_m6_album', sanitize_text_field( $att['m6_album'] ) );
	}
	return $post;
}, 10, 2 );

/* Media library (list view): bulk "Add to gallery" / "Remove from gallery". */
add_filter( 'bulk_actions-upload', function ( $actions ) {
	$actions['m6_gallery_add']    = __( 'Add to site gallery', 'hamshack' );
	$actions['m6_gallery_remove'] = __( 'Remove from site gallery', 'hamshack' );
	return $actions;
} );
add_filter( 'handle_bulk_actions-upload', function ( $redirect, $action, $ids ) {
	if ( in_array( $action, array( 'm6_gallery_add', 'm6_gallery_remove' ), true ) ) {
		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				update_post_meta( $id, '_m6_gallery', 'm6_gallery_add' === $action ? '1' : '' );
			}
		}
		$redirect = add_query_arg( 'm6_gallery', count( $ids ), $redirect );
	}
	return $redirect;
}, 10, 3 );

/**
 * [hamshack_gallery album=""] – photos marked "Show in the gallery".
 */
add_shortcode( 'hamshack_gallery', function ( $atts ) {
	$atts = shortcode_atts( array( 'album' => '' ), $atts, 'hamshack_gallery' );
	$mq   = array( array( 'key' => '_m6_gallery', 'value' => '1' ) );
	if ( $atts['album'] ) {
		$mq[] = array( 'key' => '_m6_album', 'value' => $atts['album'] );
	}
	$imgs = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 500, 'meta_query' => $mq ) ); // phpcs:ignore
	if ( ! $imgs ) {
		$msg = current_user_can( 'upload_files' )
			? __( 'No photos in the gallery yet. In Media → Library open a photo and tick “Show in the gallery” (or select several in list view and use the bulk action “Add to site gallery”).', 'hamshack' )
			: __( 'Photos are coming soon.', 'hamshack' );
		return '<div class="empty-state">' . esc_html( $msg ) . '</div>';
	}
	$albums = array();
	foreach ( $imgs as $i ) {
		$al = get_post_meta( $i->ID, '_m6_album', true );
		if ( $al ) {
			$albums[ $al ] = isset( $albums[ $al ] ) ? $albums[ $al ] + 1 : 1;
		}
	}
	ksort( $albums );
	ob_start();
	?>
	<div class="hs-app gallery-app" data-hs-gallery>
		<?php if ( count( $albums ) > 1 && ! $atts['album'] ) : ?>
			<div class="chips gallery-filter">
				<button type="button" class="chip-btn is-on" data-album=""><?php esc_html_e( 'All', 'hamshack' ); ?> · <?php echo count( $imgs ); ?></button>
				<?php foreach ( $albums as $al => $n ) : ?>
					<button type="button" class="chip-btn" data-album="<?php echo esc_attr( $al ); ?>"><?php echo esc_html( $al ); ?> · <?php echo (int) $n; ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<div class="gallery-grid">
			<?php
			foreach ( $imgs as $i ) :
				$full = wp_get_attachment_image_src( $i->ID, 'full' );
				$cap  = wp_get_attachment_caption( $i->ID );
				?>
				<figure class="g-item" data-album="<?php echo esc_attr( get_post_meta( $i->ID, '_m6_album', true ) ); ?>">
					<a href="<?php echo esc_url( $full[0] ); ?>" data-lightbox="gallery" data-caption="<?php echo esc_attr( $cap ? $cap : $i->post_title ); ?>">
						<?php echo wp_get_attachment_image( $i->ID, 'medium_large', false, array( 'loading' => 'lazy' ) ); ?>
					</a>
					<?php if ( $cap ) : ?><figcaption><?php echo esc_html( $cap ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );
