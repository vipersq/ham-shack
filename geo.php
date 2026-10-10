<?php
/**
 * Geography: callsign → DXCC entity (cty.dat by AD1C), Maidenhead locators,
 * distances, QSO enrichment and the public map data feed.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------------
 * Country file (cty.csv from country-files.com, compiled to JSON)
 * ------------------------------------------------------------------------ */

/**
 * Path of the newest country file: a downloaded update in uploads, or the bundled copy.
 */
function hamshack_cty_path() {
	$up = wp_upload_dir( null, false );
	$f  = trailingslashit( $up['basedir'] ) . 'hamshack/cty.json';
	return file_exists( $f ) ? $f : HAMSHACK_DIR . '/assets/data/cty.json';
}

/**
 * Loaded country data: [ 'e' => entities, 'p' => prefixes, 'x' => exact calls ].
 */
function hamshack_cty() {
	static $data = null;
	if ( null === $data ) {
		$raw  = file_get_contents( hamshack_cty_path() ); // phpcs:ignore
		$data = $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || empty( $data['e'] ) ) {
			$data = array( 'e' => array(), 'p' => array(), 'x' => array() );
		}
	}
	return $data;
}

/**
 * Strip portable / mobile designators and pick the part of a callsign that decides the DXCC entity.
 *
 * @param string $call Callsign.
 * @return string
 */
function hamshack_call_base( $call ) {
	$call  = strtoupper( trim( (string) $call ) );
	$parts = array_values( array_filter( explode( '/', $call ), 'strlen' ) );
	if ( count( $parts ) <= 1 ) {
		return $call;
	}
	$ignore = array( 'P', 'M', 'MM', 'AM', 'QRP', 'A', 'B', 'LH', 'R', 'J', 'T' );
	$parts  = array_values( array_filter( $parts, function ( $p ) use ( $ignore ) {
		return ! in_array( $p, $ignore, true ) && ! preg_match( '/^\d$/', $p );
	} ) );
	if ( 1 === count( $parts ) ) {
		return $parts[0];
	}
	if ( count( $parts ) >= 2 ) {
		// The shorter part is the prefix (EA8/G4ABC or G4ABC/EA8). Ties: the first one.
		return strlen( $parts[1] ) < strlen( $parts[0] ) ? $parts[1] : $parts[0];
	}
	return $call;
}

/**
 * Look up the DXCC entity of a callsign.
 *
 * @param string $call Callsign.
 * @return array|null [ name, dxcc, cont, cqz, ituz, lat, lon ]
 */
function hamshack_cty_lookup( $call ) {
	$call = strtoupper( trim( (string) $call ) );
	if ( '' === $call ) {
		return null;
	}
	static $cache = array();
	if ( isset( $cache[ $call ] ) ) {
		return $cache[ $call ];
	}
	$d   = hamshack_cty();
	$hit = null;
	if ( isset( $d['x'][ $call ] ) ) {
		$hit = $d['x'][ $call ];
	} else {
		$base = hamshack_call_base( $call );
		if ( isset( $d['x'][ $base ] ) ) {
			$hit = $d['x'][ $base ];
		} else {
			for ( $len = min( strlen( $base ), 8 ); $len > 0; $len-- ) {
				$pre = substr( $base, 0, $len );
				if ( isset( $d['p'][ $pre ] ) ) {
					$hit = $d['p'][ $pre ];
					break;
				}
			}
		}
	}
	if ( null === $hit ) {
		$cache[ $call ] = null;
		return null;
	}
	$idx = is_array( $hit ) ? $hit[0] : $hit;
	if ( ! isset( $d['e'][ $idx ] ) ) {
		return null;
	}
	$e = $d['e'][ $idx ];
	$r = array(
		'name' => $e[0],
		'dxcc' => (int) $e[1],
		'cont' => $e[2],
		'cqz'  => (int) $e[3],
		'ituz' => (int) $e[4],
		'lat'  => (float) $e[5],
		'lon'  => (float) $e[6],
	);
	if ( is_array( $hit ) ) {
		if ( ! empty( $hit[1] ) ) {
			$r['cqz'] = (int) $hit[1];
		}
		if ( ! empty( $hit[2] ) ) {
			$r['ituz'] = (int) $hit[2];
		}
		if ( ! empty( $hit[3] ) ) {
			$r['cont'] = $hit[3];
		}
		if ( isset( $hit[5] ) ) {
			$r['lat'] = (float) $hit[4];
			$r['lon'] = (float) $hit[5];
		}
	}
	$cache[ $call ] = $r;
	return $r;
}

/**
 * Keep the country file fresh: download cty.csv from country-files.com once a month.
 */
add_action( 'hamshack_cty_update', 'hamshack_cty_update' );
function hamshack_cty_update() {
	$res = wp_remote_get( 'https://www.country-files.com/cty/cty.csv', array( 'timeout' => 30 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		return false;
	}
	$json = hamshack_cty_compile( wp_remote_retrieve_body( $res ) );
	if ( ! $json ) {
		return false;
	}
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'hamshack';
	wp_mkdir_p( $dir );
	if ( ! file_exists( $dir . '/index.html' ) ) {
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
	}
	file_put_contents( $dir . '/cty.json', $json ); // phpcs:ignore
	update_option( 'hamshack_cty_updated', time(), false );
	return true;
}

/**
 * Compile cty.csv text into the compact JSON used by hamshack_cty().
 */
function hamshack_cty_compile( $csv ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $csv );
	$rows  = array();
	$canon = array();
	foreach ( $lines as $line ) {
		$r = str_getcsv( $line, ',', '"', '\\' );
		if ( count( $r ) < 10 ) {
			continue;
		}
		$rows[] = $r;
		if ( '*' !== $r[0][0] ) {
			$canon[ (int) $r[2] ] = $r[1];
		}
	}
	if ( count( $rows ) < 300 ) {
		return false; // Something is wrong with the download.
	}
	$out = array( 'e' => array(), 'p' => array(), 'x' => array() );
	foreach ( $rows as $r ) {
		$dx    = (int) $r[2];
		$name  = '*' === $r[0][0] && isset( $canon[ $dx ] ) ? $canon[ $dx ] : $r[1];
		$idx   = count( $out['e'] );
		$out['e'][] = array( $name, $dx, $r[3], (int) $r[4], (int) $r[5], round( (float) $r[6], 2 ), round( -(float) $r[7], 2 ) );
		foreach ( preg_split( '/\s+/', trim( rtrim( trim( $r[9] ), ';' ) ) ) as $tok ) {
			if ( '' === $tok ) {
				continue;
			}
			$cq   = preg_match( '/\((\d+)\)/', $tok, $m ) ? (int) $m[1] : 0;
			$itu  = preg_match( '/\[(\d+)\]/', $tok, $m ) ? (int) $m[1] : 0;
			$cont = preg_match( '/\{(\w+)\}/', $tok, $m ) ? $m[1] : '';
			$ll   = preg_match( '#<([-\d.]+)/([-\d.]+)>#', $tok, $m ) ? array( round( (float) $m[1], 2 ), round( -(float) $m[2], 2 ) ) : null;
			$base = preg_replace( '/[\(\[\{<~].*$/', '', $tok );
			$val  = ( $cq || $itu || $cont || $ll ) ? array_merge( array( $idx, $cq, $itu, $cont ), $ll ? $ll : array() ) : $idx;
			if ( '=' === $base[0] ) {
				$out['x'][ substr( $base, 1 ) ] = $val;
			} else {
				$out['p'][ $base ] = $val;
			}
		}
	}
	return wp_json_encode( $out );
}

/* --------------------------------------------------------------------------
 * Maidenhead locators & distances
 * ------------------------------------------------------------------------ */

/**
 * Is this a valid Maidenhead locator (4, 6 or 8 characters)?
 */
function hamshack_valid_grid( $grid ) {
	return (bool) preg_match( '/^[A-R]{2}[0-9]{2}([A-X]{2}([0-9]{2})?)?$/i', trim( (string) $grid ) );
}

/**
 * Centre of a Maidenhead square.
 *
 * @return array|null [ lat, lon ]
 */
function hamshack_grid_to_latlon( $grid ) {
	$g = strtoupper( trim( (string) $grid ) );
	if ( ! hamshack_valid_grid( $g ) ) {
		return null;
	}
	$lon = ( ord( $g[0] ) - 65 ) * 20 - 180;
	$lat = ( ord( $g[1] ) - 65 ) * 10 - 90;
	$lon += (int) $g[2] * 2;
	$lat += (int) $g[3];
	$w = 2;
	$h = 1;
	if ( strlen( $g ) >= 6 ) {
		$lon += ( ord( $g[4] ) - 65 ) * ( 2 / 24 );
		$lat += ( ord( $g[5] ) - 65 ) * ( 1 / 24 );
		$w    = 2 / 24;
		$h    = 1 / 24;
	}
	if ( strlen( $g ) >= 8 ) {
		$lon += (int) $g[6] * ( $w / 10 );
		$lat += (int) $g[7] * ( $h / 10 );
		$w   /= 10;
		$h   /= 10;
	}
	return array( round( $lat + $h / 2, 4 ), round( $lon + $w / 2, 4 ) );
}

/**
 * Lat/lon → 6-character locator.
 */
function hamshack_latlon_to_grid( $lat, $lon ) {
	$lon = fmod( $lon + 180, 360 );
	$lat = max( 0, min( 179.9999, $lat + 90 ) );
	$a   = chr( 65 + (int) floor( $lon / 20 ) ) . chr( 65 + (int) floor( $lat / 10 ) );
	$a  .= (int) floor( fmod( $lon, 20 ) / 2 ) . (int) floor( fmod( $lat, 10 ) );
	$a  .= strtolower( chr( 65 + (int) floor( fmod( $lon, 2 ) * 12 ) ) . chr( 65 + (int) floor( fmod( $lat, 1 ) * 24 ) ) );
	return $a;
}

/**
 * ADIF LAT/LON ("N051 04.123") → decimal degrees.
 */
function hamshack_adif_coord( $v ) {
	if ( preg_match( '/^([NSEW])(\d{3}) (\d{1,2}(?:\.\d+)?)$/i', trim( (string) $v ), $m ) ) {
		$d = (int) $m[2] + (float) $m[3] / 60;
		return in_array( strtoupper( $m[1] ), array( 'S', 'W' ), true ) ? -$d : $d;
	}
	return null;
}

/**
 * Great-circle distance in km.
 */
function hamshack_distance_km( $lat1, $lon1, $lat2, $lon2 ) {
	$r  = 6371.0;
	$p1 = deg2rad( $lat1 );
	$p2 = deg2rad( $lat2 );
	$dp = deg2rad( $lat2 - $lat1 );
	$dl = deg2rad( $lon2 - $lon1 );
	$a  = sin( $dp / 2 ) ** 2 + cos( $p1 ) * cos( $p2 ) * sin( $dl / 2 ) ** 2;
	return 2 * $r * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

/**
 * Initial bearing in degrees.
 */
function hamshack_bearing( $lat1, $lon1, $lat2, $lon2 ) {
	$p1 = deg2rad( $lat1 );
	$p2 = deg2rad( $lat2 );
	$dl = deg2rad( $lon2 - $lon1 );
	$y  = sin( $dl ) * cos( $p2 );
	$x  = cos( $p1 ) * sin( $p2 ) - sin( $p1 ) * cos( $p2 ) * cos( $dl );
	return fmod( rad2deg( atan2( $y, $x ) ) + 360, 360 );
}

/**
 * The station's own position (exact coordinates, else the centre of the locator square).
 *
 * @return array [ lat, lon ]
 */
function hamshack_home_latlon() {
	$lat = hamshack_opt( 'lat' );
	$lon = hamshack_opt( 'lon' );
	if ( '' !== (string) $lat && '' !== (string) $lon && is_numeric( $lat ) && is_numeric( $lon ) ) {
		return array( (float) $lat, (float) $lon );
	}
	$ll = hamshack_grid_to_latlon( hamshack_opt( 'locator' ) );
	return $ll ? $ll : array( 0.0, 0.0 );
}

/* --------------------------------------------------------------------------
 * QSO enrichment
 * ------------------------------------------------------------------------ */

/**
 * Extra (mostly automatic) QSO fields.
 */
function hamshack_qso_extra_keys() {
	return array( 'dxcc', 'cont', 'cqz', 'ituz', 'state', 'lat', 'lon', 'my_ref' );
}

/**
 * Pull the extra fields out of an ADIF record.
 */
function hamshack_adif_extras( array $r ) {
	$x = array();
	foreach ( array( 'dxcc' => 'dxcc', 'cont' => 'cont', 'cqz' => 'cqz', 'ituz' => 'ituz', 'state' => 'state' ) as $k => $a ) {
		if ( isset( $r[ $a ] ) && '' !== trim( $r[ $a ] ) ) {
			$x[ $k ] = trim( $r[ $a ] );
		}
	}
	$lat = isset( $r['lat'] ) ? hamshack_adif_coord( $r['lat'] ) : null;
	$lon = isset( $r['lon'] ) ? hamshack_adif_coord( $r['lon'] ) : null;
	if ( null !== $lat && null !== $lon ) {
		$x['lat'] = $lat;
		$x['lon'] = $lon;
	}
	foreach ( array( 'my_pota_ref' => 'POTA', 'my_sota_ref' => 'SOTA', 'my_wwff_ref' => 'WWFF', 'my_sig_info' => 'SIG' ) as $k => $type ) {
		if ( ! empty( $r[ $k ] ) ) {
			$x['my_ref']      = strtoupper( trim( $r[ $k ] ) );
			$x['my_ref_type'] = $type;
			break;
		}
	}
	return $x;
}

/**
 * Fill in DXCC, continent, zones and coordinates for a QSO.
 *
 * Precedence for position: 6/8-char locator → ADIF lat/lon → 4-char locator → DXCC centre.
 *
 * @param int   $post_id QSO post ID.
 * @param array $x       Known extras (from ADIF).
 */
function hamshack_qso_enrich( $post_id, array $x = array() ) {
	$call = get_post_meta( $post_id, '_q_call', true );
	$grid = get_post_meta( $post_id, '_q_grid', true );
	$e    = hamshack_cty_lookup( $call );
	$out  = array(
		'dxcc'   => isset( $x['dxcc'] ) ? (int) $x['dxcc'] : ( $e ? $e['dxcc'] : 0 ),
		'cont'   => isset( $x['cont'] ) ? strtoupper( $x['cont'] ) : ( $e ? $e['cont'] : '' ),
		'cqz'    => isset( $x['cqz'] ) ? (int) $x['cqz'] : ( $e ? $e['cqz'] : 0 ),
		'ituz'   => isset( $x['ituz'] ) ? (int) $x['ituz'] : ( $e ? $e['ituz'] : 0 ),
		'state'  => isset( $x['state'] ) ? strtoupper( sanitize_text_field( $x['state'] ) ) : get_post_meta( $post_id, '_q_state', true ),
		'my_ref' => isset( $x['my_ref'] ) ? sanitize_text_field( $x['my_ref'] ) : get_post_meta( $post_id, '_q_my_ref', true ),
		'my_ref_type' => isset( $x['my_ref_type'] ) ? sanitize_key( $x['my_ref_type'] ) : get_post_meta( $post_id, '_q_my_ref_type', true ),
	);
	$src = '';
	$ll  = null;
	if ( $grid && strlen( $grid ) >= 6 && hamshack_valid_grid( $grid ) ) {
		$ll  = hamshack_grid_to_latlon( $grid );
		$src = 'grid';
	} elseif ( isset( $x['lat'], $x['lon'] ) ) {
		$ll  = array( (float) $x['lat'], (float) $x['lon'] );
		$src = 'adif';
	} elseif ( $grid && hamshack_valid_grid( $grid ) ) {
		$ll  = hamshack_grid_to_latlon( $grid );
		$src = 'grid';
	} elseif ( $e ) {
		$ll  = array( $e['lat'], $e['lon'] );
		$src = 'dxcc';
	}
	if ( $ll ) {
		$out['lat'] = round( $ll[0], 4 );
		$out['lon'] = round( $ll[1], 4 );
		list( $hl, $ho ) = hamshack_home_latlon();
		update_post_meta( $post_id, '_q_km', (int) round( hamshack_distance_km( $hl, $ho, $ll[0], $ll[1] ) ) );
	}
	foreach ( $out as $k => $v ) {
		update_post_meta( $post_id, '_q_' . $k, $v );
	}
	update_post_meta( $post_id, '_q_geo', $src ? $src : 'none' );
	if ( $e && '' === get_post_meta( $post_id, '_q_country', true ) ) {
		update_post_meta( $post_id, '_q_country', $e['name'] );
	}
	delete_transient( 'hamshack_map_data' );
	delete_transient( 'hamshack_awards' );
}

/**
 * Background back-fill for QSOs logged before enrichment existed.
 */
add_action( 'hamshack_geo_backfill', 'hamshack_geo_backfill' );
function hamshack_geo_backfill( $limit = 400 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'hamshack_qso',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => '_q_geo', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore
		)
	);
	foreach ( $ids as $id ) {
		hamshack_qso_enrich( $id );
	}
	if ( count( $ids ) >= $limit && ! wp_next_scheduled( 'hamshack_geo_backfill' ) ) {
		wp_schedule_single_event( time() + 30, 'hamshack_geo_backfill' );
	}
	return count( $ids );
}

/**
 * Re-enrich everything (e.g. after the locator changed).
 */
function hamshack_geo_reset() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_q_geo'" ); // phpcs:ignore
	wp_clear_scheduled_hook( 'hamshack_geo_backfill' );
	wp_schedule_single_event( time() + 5, 'hamshack_geo_backfill' );
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'hamshack_cty_update' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hamshack_monthly', 'hamshack_cty_update' );
	}
} );
add_filter( 'cron_schedules', function ( $s ) {
	$s['hamshack_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => __( 'Monthly', 'hamshack' ) );
	return $s;
} );

/* Locator changed → recompute distances. */
add_action( 'update_option_hamshack_station', function ( $old, $new ) {
	$key = function ( $a ) {
		return strtoupper( isset( $a['locator'] ) ? $a['locator'] : '' ) . '|' . ( isset( $a['lat'] ) ? $a['lat'] : '' ) . '|' . ( isset( $a['lon'] ) ? $a['lon'] : '' );
	};
	if ( $key( (array) $old ) !== $key( (array) $new ) ) {
		hamshack_geo_reset();
	}
}, 10, 2 );

/* --------------------------------------------------------------------------
 * Public data for the maps
 * ------------------------------------------------------------------------ */

/**
 * Rows for the QSO map, one per worked callsign.
 *
 * @return array
 */
function hamshack_map_data() {
	$cached = get_transient( 'hamshack_map_data' );
	if ( false !== $cached ) {
		return $cached;
	}
	global $wpdb;
	$keys = array( '_q_call', '_q_date', '_q_band', '_q_mode', '_q_lat', '_q_lon', '_q_country', '_q_grid', '_q_km', '_q_geo', '_q_qsl_r', '_q_cont' );
	$in   = "'" . implode( "','", $keys ) . "'";
	$rows = $wpdb->get_results( "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish' WHERE pm.meta_key IN ($in)", ARRAY_A ); // phpcs:ignore
	$qsos = array();
	foreach ( $rows as $r ) {
		$qsos[ $r['post_id'] ][ substr( $r['meta_key'], 3 ) ] = $r['meta_value'];
	}
	$by = array();
	foreach ( $qsos as $q ) {
		if ( empty( $q['call'] ) || ! isset( $q['lat'], $q['lon'] ) || '' === $q['lat'] ) {
			continue;
		}
		$c = $q['call'];
		if ( ! isset( $by[ $c ] ) ) {
			$by[ $c ] = array(
				'c'  => $c,
				'la' => (float) $q['lat'],
				'lo' => (float) $q['lon'],
				'co' => isset( $q['country'] ) ? $q['country'] : '',
				'ct' => isset( $q['cont'] ) ? $q['cont'] : '',
				'g'  => isset( $q['grid'] ) ? $q['grid'] : '',
				'km' => isset( $q['km'] ) ? (int) $q['km'] : 0,
				'p'  => isset( $q['geo'] ) && 'dxcc' === $q['geo'] ? 0 : 1,
				'b'  => array(),
				'm'  => array(),
				'n'  => 0,
				'd'  => '',
				'q'  => 0,
			);
		}
		$row = &$by[ $c ];
		$row['n']++;
		if ( ! empty( $q['band'] ) && ! in_array( $q['band'], $row['b'], true ) ) {
			$row['b'][] = $q['band'];
		}
		$mode = isset( $q['mode'] ) ? $q['mode'] : '';
		if ( $mode && ! in_array( $mode, $row['m'], true ) ) {
			$row['m'][] = $mode;
		}
		if ( isset( $q['date'] ) && $q['date'] > $row['d'] ) {
			$row['d'] = $q['date'];
			// Newest QSO decides the position (people move; grids get refined).
			$row['la'] = (float) $q['lat'];
			$row['lo'] = (float) $q['lon'];
			$row['g']  = isset( $q['grid'] ) ? $q['grid'] : $row['g'];
			$row['p']  = isset( $q['geo'] ) && 'dxcc' === $q['geo'] ? 0 : 1;
			$row['km'] = isset( $q['km'] ) ? (int) $q['km'] : $row['km'];
		}
		if ( isset( $q['qsl_r'] ) && 'Y' === $q['qsl_r'] ) {
			$row['q'] = 1;
		}
		unset( $row );
	}
	$data = array_values( $by );
	set_transient( 'hamshack_map_data', $data, 12 * HOUR_IN_SECONDS );
	return $data;
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'hamshack/v1',
		'/map',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				list( $lat, $lon ) = hamshack_home_latlon();
				$res = new WP_REST_Response(
					array(
						'home' => array( 'lat' => $lat, 'lon' => $lon, 'call' => hamshack_opt( 'callsign' ), 'grid' => hamshack_opt( 'locator' ) ),
						'qsos' => hamshack_map_data(),
					)
				);
				$res->header( 'Cache-Control', 'public, max-age=300' );
				return $res;
			},
		)
	);
} );

/**
 * Data passed to every map on the page.
 */
function hamshack_map_config() {
	list( $lat, $lon ) = hamshack_home_latlon();
	return array(
		'world'  => HAMSHACK_URI . '/assets/data/world.svg?v=' . HAMSHACK_VERSION,
		'rest'   => esc_url_raw( rest_url( 'hamshack/v1/' ) ),
		'home'   => array( 'lat' => $lat, 'lon' => $lon, 'call' => hamshack_opt( 'callsign' ), 'grid' => hamshack_opt( 'locator' ) ),
		'bands'  => hamshack_all_bands(),
		'colors' => hamshack_band_colors(),
		'i18n'   => array(
			'qsos'     => __( 'QSOs', 'hamshack' ),
			'km'       => __( 'km', 'hamshack' ),
			'approx'   => __( 'approximate position (country centre)', 'hamshack' ),
			'confirmed'=> __( 'confirmed', 'hamshack' ),
			'last'     => __( 'last QSO', 'hamshack' ),
			'zoomIn'   => __( 'Zoom in', 'hamshack' ),
			'zoomOut'  => __( 'Zoom out', 'hamshack' ),
			'reset'    => __( 'Whole world', 'hamshack' ),
			'europe'   => __( 'Europe', 'hamshack' ),
			'loading'  => __( 'Loading…', 'hamshack' ),
			'empty'    => __( 'Nothing to show yet.', 'hamshack' ),
			'ago'      => __( '%s ago', 'hamshack' ),
			'min'      => __( 'min', 'hamshack' ),
			'utc'      => __( 'UTC', 'hamshack' ),
			'local'    => __( 'Local', 'hamshack' ),
			'sun'      => __( 'Sun overhead', 'hamshack' ),
			'sunrise'  => __( 'Sunrise', 'hamshack' ),
			'sunset'   => __( 'Sunset', 'hamshack' ),
			'heard'    => __( 'heard by', 'hamshack' ),
			'snr'      => __( 'SNR', 'hamshack' ),
			'stations' => __( 'stations', 'hamshack' ),
			'farthest' => __( 'farthest', 'hamshack' ),
		),
	);
}

/**
 * One colour per band (shared by maps & charts).
 */
function hamshack_band_colors() {
	return array(
		'160m' => '#a78bfa', '80m' => '#818cf8', '60m' => '#60a5fa', '40m' => '#22d3ee', '30m' => '#2dd4bf',
		'20m' => '#39ff88', '17m' => '#a3e635', '15m' => '#facc15', '12m' => '#ffb020', '10m' => '#fb923c',
		'6m' => '#f87171', '4m' => '#f472b6', '2m' => '#e879f9', '70cm' => '#c084fc', '23cm' => '#94a3b8', 'QO-100' => '#f1f6fb',
	);
}

/**
 * Enqueue the map engine once.
 */
function hamshack_enqueue_map() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_enqueue_script( 'hamshack-map', HAMSHACK_URI . '/assets/js/map.js', array(), HAMSHACK_VERSION, true );
	wp_localize_script( 'hamshack-map', 'M6MAP', hamshack_map_config() );
}

/**
 * [hamshack_qso_map height="520"] – world map of everybody in the log.
 */
add_shortcode( 'hamshack_qso_map', function ( $atts ) {
	$atts = shortcode_atts( array( 'grids' => '1' ), $atts, 'hamshack_qso_map' );
	hamshack_enqueue_map();
	$s = hamshack_log_stats();
	ob_start();
	?>
	<div class="hs-app qso-map-app" data-hs-qsomap data-grids="<?php echo esc_attr( $atts['grids'] ); ?>">
		<div class="map-toolbar">
			<div class="seg" role="group" aria-label="<?php esc_attr_e( 'View', 'hamshack' ); ?>">
				<button type="button" class="is-on" data-view="points"><?php esc_html_e( 'Stations', 'hamshack' ); ?></button>
				<button type="button" data-view="grids"><?php esc_html_e( 'Grid squares', 'hamshack' ); ?></button>
			</div>
			<label class="map-select"><span><?php esc_html_e( 'Band', 'hamshack' ); ?></span>
				<select data-filter="band"><option value=""><?php esc_html_e( 'All bands', 'hamshack' ); ?></option>
				<?php foreach ( wp_list_pluck( $s['bands'], 'k' ) as $b ) : ?>
					<option><?php echo esc_html( $b ); ?></option>
				<?php endforeach; ?>
				</select>
			</label>
			<label class="map-select"><span><?php esc_html_e( 'Mode', 'hamshack' ); ?></span>
				<select data-filter="mode"><option value=""><?php esc_html_e( 'All modes', 'hamshack' ); ?></option>
				<?php foreach ( wp_list_pluck( $s['modes'], 'k' ) as $m ) : ?>
					<option><?php echo esc_html( $m ); ?></option>
				<?php endforeach; ?>
				</select>
			</label>
			<label class="map-check"><input type="checkbox" data-filter="lines" checked> <?php esc_html_e( 'Paths', 'hamshack' ); ?></label>
			<label class="map-check"><input type="checkbox" data-filter="night"> <?php esc_html_e( 'Day / night', 'hamshack' ); ?></label>
			<label class="map-search"><span class="screen-reader-text"><?php esc_html_e( 'Find callsign', 'hamshack' ); ?></span><input type="search" data-filter="call" placeholder="<?php esc_attr_e( 'Find callsign…', 'hamshack' ); ?>" spellcheck="false" autocomplete="off"></label>
		</div>
		<div class="hs-map" data-hs-map></div>
		<div class="map-foot">
			<div class="map-legend" data-legend></div>
			<div class="map-summary mono" data-summary></div>
		</div>
		<div class="map-top" data-top hidden>
			<h3><?php esc_html_e( 'Longest contacts', 'hamshack' ); ?></h3>
			<ol class="dx-list" data-dx></ol>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

/* Deleted / unpublished QSOs must disappear from the public map and awards straight away. */
function hamshack_geo_flush_caches( $post_id ) {
	if ( 'hamshack_qso' === get_post_type( $post_id ) ) {
		delete_transient( 'hamshack_map_data' );
		delete_transient( 'hamshack_awards' );
		delete_transient( 'hamshack_log_stats' );
	}
}
add_action( 'trashed_post', 'hamshack_geo_flush_caches' );
add_action( 'before_delete_post', 'hamshack_geo_flush_caches' );
add_action( 'untrashed_post', 'hamshack_geo_flush_caches' );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $new !== $old && 'hamshack_qso' === $post->post_type ) {
		hamshack_geo_flush_caches( $post->ID );
	}
}, 10, 3 );
