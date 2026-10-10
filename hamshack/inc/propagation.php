<?php
/**
 * Propagation & conditions: live MUF map (KC2G), best times from the log,
 * space weather (NOAA Kp) and local weather with thunderstorm warning (Open-Meteo).
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hamshack_http_json( $url, $ttl, $key ) {
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$res  = wp_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION . ' (' . home_url() . ')' ) );
	$data = ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
	if ( null === $data ) {
		$last = get_option( $key . '_last' );
		set_transient( $key, $last ? $last : array(), 5 * MINUTE_IN_SECONDS );
		return $last ? $last : array();
	}
	update_option( $key . '_last', $data, false );
	set_transient( $key, $data, $ttl );
	return $data;
}

/* --------------------------------------------------------------------------
 * MUF map (KC2G / GIRO ionosondes)
 * ------------------------------------------------------------------------ */

function hamshack_muf_stations() {
	$raw = hamshack_http_json( 'https://prop.kc2g.com/api/stations.json', 15 * MINUTE_IN_SECONDS, 'hamshack_kc2g' );
	$out = array();
	foreach ( (array) $raw as $s ) {
		if ( empty( $s['mufd'] ) || empty( $s['station'] ) || empty( $s['time'] ) ) {
			continue;
		}
		$age = time() - strtotime( $s['time'] . ' UTC' );
		if ( $age > 2 * HOUR_IN_SECONDS || ( isset( $s['cs'] ) && (float) $s['cs'] < 25 && -1 != $s['cs'] ) ) { // phpcs:ignore
			continue;
		}
		$lon = (float) $s['station']['longitude'];
		$out[] = array(
			'name' => sanitize_text_field( $s['station']['name'] ),
			'lat'  => round( (float) $s['station']['latitude'], 2 ),
			'lon'  => round( $lon > 180 ? $lon - 360 : $lon, 2 ),
			'muf'  => round( (float) $s['mufd'], 1 ),
			'fof2' => isset( $s['fof2'] ) ? round( (float) $s['fof2'], 1 ) : null,
			'age'  => (int) round( $age / 60 ),
		);
	}
	return $out;
}

/**
 * Bands and their lower edge in MHz (for "open / closed").
 */
function hamshack_hf_band_edges() {
	return array( '80m' => 3.5, '40m' => 7.0, '30m' => 10.1, '20m' => 14.0, '17m' => 18.068, '15m' => 21.0, '12m' => 24.89, '10m' => 28.0, '6m' => 50.0 );
}

function hamshack_enqueue_prop() {
	hamshack_enqueue_map();
	wp_enqueue_script( 'hamshack-prop', HAMSHACK_URI . '/assets/js/prop.js', array( 'hamshack-map' ), HAMSHACK_VERSION, true );
}

add_shortcode( 'hamshack_muf', function () {
	hamshack_enqueue_prop();
	$st = hamshack_muf_stations();
	list( $hl, $ho ) = hamshack_home_latlon();
	// Nearest stations to the QTH give the local picture (MUF for a ~3000 km hop).
	$near = $st;
	foreach ( $near as &$n ) {
		$n['km'] = (int) round( hamshack_distance_km( $hl, $ho, $n['lat'], $n['lon'] ) );
	}
	unset( $n );
	usort( $near, function ( $a, $b ) {
		return $a['km'] - $b['km'];
	} );
	$local = array_slice( array_filter( $near, function ( $n ) {
		return $n['km'] < 1800;
	} ), 0, 3 );
	$muf   = $local ? max( wp_list_pluck( $local, 'muf' ) ) : null;
	ob_start();
	?>
	<div class="hs-app muf-app" data-hs-muf data-items="<?php echo esc_attr( wp_json_encode( $st ) ); ?>">
		<div class="muf-head">
			<?php if ( null !== $muf ) : ?>
				<div class="muf-now"><span class="lbl"><?php esc_html_e( 'MUF (3000 km) near me', 'hamshack' ); ?></span><span class="val mono"><?php echo esc_html( number_format_i18n( $muf, 1 ) ); ?> MHz</span>
				<span class="muted small"><?php echo esc_html( implode( ', ', wp_list_pluck( $local, 'name' ) ) ); ?></span></div>
				<div class="band-open">
					<?php foreach ( hamshack_hf_band_edges() as $b => $f ) : ?>
						<span class="bo <?php echo $muf >= $f ? 'is-open' : ( $muf >= $f * 0.85 ? 'is-maybe' : '' ); ?>"><b class="mono"><?php echo esc_html( $b ); ?></b><i><?php echo $muf >= $f ? esc_html__( 'open', 'hamshack' ) : ( $muf >= $f * 0.85 ? esc_html__( 'marginal', 'hamshack' ) : esc_html__( 'closed', 'hamshack' ) ); ?></i></span>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="muted"><?php esc_html_e( 'No fresh ionosonde data near the station right now.', 'hamshack' ); ?></p>
			<?php endif; ?>
		</div>
		<div data-hs-map></div>
		<div class="map-foot"><div class="map-legend muf-legend" data-legend></div><span class="map-summary mono"><?php printf( esc_html__( '%d ionosondes', 'hamshack' ), count( $st ) ); ?></span></div>
		<p class="app-note"><?php printf( esc_html__( 'MUF(D) = the highest frequency that reflects over a 3000 km hop at each ionosonde. Data: %s (GIRO and other ionosonde networks), refreshed every 15 minutes. “Open” is a guide, not a promise – listen on the band!', 'hamshack' ), '<a href="https://prop.kc2g.com/" target="_blank" rel="noopener">KC2G</a>' ); // phpcs:ignore ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/* --------------------------------------------------------------------------
 * Best times – continent × UTC hour from the log
 * ------------------------------------------------------------------------ */

function hamshack_best_times_data() {
	$cached = get_transient( 'hamshack_best_times' );
	if ( false !== $cached ) {
		return $cached;
	}
	$rows  = hamshack_awards_rows();
	$conts = array( 'EU', 'NA', 'SA', 'AS', 'AF', 'OC' );
	$blank = array_fill_keys( $conts, array_fill( 0, 24, 0 ) );
	$data  = array( '' => $blank );
	foreach ( $rows as $q ) {
		$c = isset( $q['cont'] ) ? strtoupper( $q['cont'] ) : '';
		if ( ! in_array( $c, $conts, true ) || empty( $q['time'] ) ) {
			continue;
		}
		$h = (int) substr( $q['time'], 0, 2 ) % 24;
		$b = isset( $q['band'] ) ? $q['band'] : '';
		$data[''][ $c ][ $h ]++;
		if ( $b ) {
			if ( ! isset( $data[ $b ] ) ) {
				$data[ $b ] = $blank;
			}
			$data[ $b ][ $c ][ $h ]++;
		}
	}
	set_transient( 'hamshack_best_times', $data, 6 * HOUR_IN_SECONDS );
	return $data;
}

add_shortcode( 'hamshack_best_times', function () {
	hamshack_enqueue_prop();
	$d     = hamshack_best_times_data();
	$bands = array_values( array_intersect( hamshack_all_bands(), array_keys( $d ) ) );
	$names = array( 'EU' => __( 'Europe', 'hamshack' ), 'NA' => __( 'North America', 'hamshack' ), 'SA' => __( 'South America', 'hamshack' ), 'AS' => __( 'Asia', 'hamshack' ), 'AF' => __( 'Africa', 'hamshack' ), 'OC' => __( 'Oceania', 'hamshack' ) );
	ob_start();
	?>
	<div class="hs-app best-app" data-hs-best data-items="<?php echo esc_attr( wp_json_encode( $d ) ); ?>" data-names="<?php echo esc_attr( wp_json_encode( $names ) ); ?>">
		<div class="app-toolbar">
			<div class="chips">
				<button type="button" class="chip-btn is-on" data-band=""><?php esc_html_e( 'All bands', 'hamshack' ); ?></button>
				<?php foreach ( $bands as $b ) : ?>
					<button type="button" class="chip-btn" data-band="<?php echo esc_attr( $b ); ?>"><?php echo esc_html( $b ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="table-scroll"><div class="heat" data-grid></div></div>
		<p class="app-note"><?php esc_html_e( 'When my QSOs with each continent happen, hour by hour (UTC). Each row is scaled on its own: the brightest squares are the best times for that continent from my QTH.', 'hamshack' ); ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/* --------------------------------------------------------------------------
 * Space weather (NOAA SWPC planetary K index)
 * ------------------------------------------------------------------------ */

function hamshack_kp_data() {
	$raw = hamshack_http_json( 'https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json', 30 * MINUTE_IN_SECONDS, 'hamshack_kp' );
	$out = array();
	foreach ( (array) $raw as $i => $r ) {
		if ( isset( $r['time_tag'], $r['Kp'] ) ) {
			$out[] = array( 't' => $r['time_tag'], 'kp' => (float) $r['Kp'] );
		} elseif ( is_array( $r ) && isset( $r[0], $r[1] ) && 0 !== $i && is_numeric( $r[1] ) ) {
			$out[] = array( 't' => $r[0], 'kp' => (float) $r[1] ); // Older array-of-arrays format.
		}
	}
	$out = array_slice( $out, -24 ); // Last 3 days (3-hour values).
	if ( $out ) {
		$last = end( $out );
		if ( $last['kp'] >= 5 && function_exists( 'hamshack_notify_throttled' ) ) {
			hamshack_notify_throttled( 'kp' . substr( $last['t'], 0, 10 ), 12 * HOUR_IN_SECONDS, 'kp', sprintf( __( 'Geomagnetic storm: Kp %s', 'hamshack' ), number_format( $last['kp'], 1 ) ), __( 'Aurora possible – listen on 6 m and 2 m to the north (CW/SSB sound rough). HF may be disturbed.', 'hamshack' ), home_url( '/' ) );
		}
	}
	return $out;
}

function hamshack_kp_label( $kp ) {
	if ( $kp >= 9 ) {
		return array( 'g5', __( 'Extreme storm (G5)', 'hamshack' ) );
	} elseif ( $kp >= 8 ) {
		return array( 'g4', __( 'Severe storm (G4)', 'hamshack' ) );
	} elseif ( $kp >= 7 ) {
		return array( 'g3', __( 'Strong storm (G3)', 'hamshack' ) );
	} elseif ( $kp >= 6 ) {
		return array( 'g2', __( 'Moderate storm (G2)', 'hamshack' ) );
	} elseif ( $kp >= 5 ) {
		return array( 'g1', __( 'Minor storm (G1)', 'hamshack' ) );
	} elseif ( $kp >= 4 ) {
		return array( 'act', __( 'Active', 'hamshack' ) );
	} elseif ( $kp >= 3 ) {
		return array( 'uns', __( 'Unsettled', 'hamshack' ) );
	}
	return array( 'quiet', __( 'Quiet', 'hamshack' ) );
}

add_shortcode( 'hamshack_space_weather', function () {
	$kp = hamshack_kp_data();
	if ( ! $kp ) {
		return '<div class="empty-state">' . esc_html__( 'Space weather data is not available right now.', 'hamshack' ) . '</div>';
	}
	$last           = end( $kp );
	list( $cls, $lab ) = hamshack_kp_label( $last['kp'] );
	$w   = 720;
	$h   = 150;
	$bw  = $w / count( $kp );
	$svg = '<svg class="chart kp-chart" viewBox="0 0 ' . $w . ' ' . ( $h + 22 ) . '" role="img" aria-label="' . esc_attr__( 'Planetary K index, last 3 days', 'hamshack' ) . '">';
	foreach ( array( 3, 5, 7 ) as $l ) {
		$y    = $h - $h * $l / 9;
		$svg .= '<line class="gl" x1="0" x2="' . $w . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"/><text class="gv" x="2" y="' . round( $y - 3, 1 ) . '">Kp ' . $l . '</text>';
	}
	foreach ( $kp as $i => $p ) {
		$bh   = max( 2, $h * $p['kp'] / 9 );
		$col  = $p['kp'] >= 5 ? '#ff3b3b' : ( $p['kp'] >= 4 ? '#ffb020' : '#39ff88' );
		$svg .= '<g><title>' . esc_html( str_replace( 'T', ' ', substr( $p['t'], 0, 16 ) ) . 'Z · Kp ' . $p['kp'] ) . '</title><rect x="' . round( $i * $bw + 2, 1 ) . '" y="' . round( $h - $bh, 1 ) . '" width="' . round( $bw - 4, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="2" fill="' . $col . '"/></g>';
		if ( 0 === $i % 8 ) {
			$svg .= '<text class="gx" x="' . round( $i * $bw + 2, 1 ) . '" y="' . ( $h + 16 ) . '">' . esc_html( gmdate( 'D d M', strtotime( $p['t'] . ' UTC' ) ) ) . '</text>';
		}
	}
	$svg .= '</svg>';
	ob_start();
	?>
	<div class="hs-app space-app">
		<div class="space-head">
			<div class="kp-now kp-<?php echo esc_attr( $cls ); ?>"><span class="lbl"><?php esc_html_e( 'Kp index now', 'hamshack' ); ?></span><span class="val mono"><?php echo esc_html( number_format( $last['kp'], 1 ) ); ?></span><span class="st"><?php echo esc_html( $lab ); ?></span></div>
			<div class="kp-text">
				<?php if ( $last['kp'] >= 5 ) : ?>
					<p><strong><?php esc_html_e( 'Aurora alert!', 'hamshack' ); ?></strong> <?php esc_html_e( 'Point the beam north and listen on 6 m and 2 m – aurora signals sound rough and buzzy. HF, especially high-latitude paths, may be poor.', 'hamshack' ); ?></p>
				<?php elseif ( $last['kp'] >= 4 ) : ?>
					<p><?php esc_html_e( 'The geomagnetic field is active – polar HF paths can be noisy; worth keeping an eye on 6 m for aurora.', 'hamshack' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'The geomagnetic field is calm – good news for HF, especially the low bands and polar paths.', 'hamshack' ); ?></p>
				<?php endif; ?>
				<p class="app-note"><?php printf( esc_html__( 'Data: %s, updated every 3 hours.', 'hamshack' ), '<a href="https://www.swpc.noaa.gov/" target="_blank" rel="noopener">NOAA SWPC</a>' ); // phpcs:ignore ?></p>
			</div>
		</div>
		<?php echo $svg; // phpcs:ignore ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* --------------------------------------------------------------------------
 * Local weather & thunderstorm warning (Open-Meteo)
 * ------------------------------------------------------------------------ */

function hamshack_weather_data() {
	list( $lat, $lon ) = hamshack_home_latlon();
	$url = add_query_arg(
		array(
			'latitude'       => round( $lat, 2 ),
			'longitude'      => round( $lon, 2 ),
			'current'        => 'temperature_2m,relative_humidity_2m,wind_speed_10m,wind_gusts_10m,wind_direction_10m,weather_code,pressure_msl',
			'hourly'         => 'weather_code,lightning_potential,cape,wind_gusts_10m,precipitation_probability',
			'forecast_hours' => 12,
			'timezone'       => 'UTC',
		),
		'https://api.open-meteo.com/v1/forecast'
	);
	$d = hamshack_http_json( $url, 20 * MINUTE_IN_SECONDS, 'hamshack_wx' );
	if ( ! $d || empty( $d['current'] ) ) {
		return null;
	}
	$storm = false;
	$gust  = isset( $d['current']['wind_gusts_10m'] ) ? (float) $d['current']['wind_gusts_10m'] : 0;
	$h     = isset( $d['hourly'] ) ? $d['hourly'] : array();
	for ( $i = 0; $i < 6; $i++ ) {
		$code = isset( $h['weather_code'][ $i ] ) ? (int) $h['weather_code'][ $i ] : 0;
		$lpi  = isset( $h['lightning_potential'][ $i ] ) ? (float) $h['lightning_potential'][ $i ] : 0;
		$cape = isset( $h['cape'][ $i ] ) ? (float) $h['cape'][ $i ] : 0;
		$pp   = isset( $h['precipitation_probability'][ $i ] ) ? (float) $h['precipitation_probability'][ $i ] : 0;
		// Thunderstorm in the forecast, lightning potential, or lots of instability with showers.
		if ( $code >= 95 || $lpi > 0.5 || ( $cape >= 1200 && $pp >= 50 ) ) {
			$storm = true;
		}
		$gust = max( $gust, isset( $h['wind_gusts_10m'][ $i ] ) ? (float) $h['wind_gusts_10m'][ $i ] : 0 );
	}
	if ( $storm && function_exists( 'hamshack_notify_throttled' ) ) {
		hamshack_notify_throttled( 'storm' . gmdate( 'Ymd' ), 6 * HOUR_IN_SECONDS, 'storm', sprintf( __( 'Thunderstorm risk at %s', 'hamshack' ), hamshack_opt( 'callsign' ) ), __( 'Thunderstorms possible in the next hours – disconnect and ground the antennas.', 'hamshack' ), home_url( '/' ) );
	}
	return array( 'cur' => $d['current'], 'storm' => $storm, 'gust' => $gust, 'lat' => $lat, 'lon' => $lon );
}

function hamshack_wmo_text( $code ) {
	$c = (int) $code;
	if ( 0 === $c ) {
		return __( 'Clear sky', 'hamshack' );
	} elseif ( $c <= 2 ) {
		return __( 'Partly cloudy', 'hamshack' );
	} elseif ( 3 === $c ) {
		return __( 'Overcast', 'hamshack' );
	} elseif ( $c <= 48 ) {
		return __( 'Fog', 'hamshack' );
	} elseif ( $c <= 57 ) {
		return __( 'Drizzle', 'hamshack' );
	} elseif ( $c <= 67 ) {
		return __( 'Rain', 'hamshack' );
	} elseif ( $c <= 77 ) {
		return __( 'Snow', 'hamshack' );
	} elseif ( $c <= 82 ) {
		return __( 'Rain showers', 'hamshack' );
	} elseif ( $c <= 86 ) {
		return __( 'Snow showers', 'hamshack' );
	}
	return __( 'Thunderstorm', 'hamshack' );
}

add_shortcode( 'hamshack_weather', function () {
	$w = hamshack_weather_data();
	if ( ! $w ) {
		return '<div class="empty-state">' . esc_html__( 'Weather data is not available right now.', 'hamshack' ) . '</div>';
	}
	$c    = $w['cur'];
	$dirs = array( 'N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW' );
	$dir  = isset( $c['wind_direction_10m'] ) ? $dirs[ (int) round( (float) $c['wind_direction_10m'] / 45 ) % 8 ] : '';
	$blitz = sprintf( 'https://map.blitzortung.org/#7/%s/%s', round( $w['lat'], 2 ), round( $w['lon'], 2 ) );
	ob_start();
	?>
	<div class="hs-app wx-app">
		<?php if ( $w['storm'] ) : ?>
			<div class="wx-alert is-storm"><?php hamshack_the_icon( 'bell', 20 ); ?> <div><strong><?php esc_html_e( 'Thunderstorm risk in the next hours', 'hamshack' ); ?></strong> <?php esc_html_e( 'Disconnect the antennas from the radios and ground the feeders. Stay safe!', 'hamshack' ); ?></div></div>
		<?php elseif ( $w['gust'] >= 60 ) : ?>
			<div class="wx-alert is-wind"><?php hamshack_the_icon( 'antenna', 20 ); ?> <div><strong><?php printf( esc_html__( 'Gusts up to %d km/h', 'hamshack' ), (int) $w['gust'] ); ?></strong> <?php esc_html_e( 'Lower the mast or check the guy lines.', 'hamshack' ); ?></div></div>
		<?php endif; ?>
		<div class="wx-grid">
			<div class="clock-box"><span class="lbl"><?php echo esc_html( hamshack_wmo_text( $c['weather_code'] ) ); ?></span><span class="val big"><?php echo esc_html( round( (float) $c['temperature_2m'] ) ); ?>°C</span></div>
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'Wind', 'hamshack' ); ?></span><span class="val"><?php echo esc_html( round( (float) $c['wind_speed_10m'] ) . ' km/h ' . $dir ); ?></span></div>
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'Gusts', 'hamshack' ); ?></span><span class="val"><?php echo esc_html( round( (float) $c['wind_gusts_10m'] ) ); ?> km/h</span></div>
			<div class="clock-box"><span class="lbl"><?php esc_html_e( 'Pressure', 'hamshack' ); ?></span><span class="val"><?php echo esc_html( round( (float) $c['pressure_msl'] ) ); ?> hPa</span></div>
		</div>
		<p class="app-note"><?php printf( esc_html__( 'Weather at the QTH from %1$s. Live lightning: %2$s.', 'hamshack' ), '<a href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a>', '<a href="' . esc_url( $blitz ) . '" target="_blank" rel="noopener">Blitzortung.org ↗</a>' ); // phpcs:ignore ?></p>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * [hamshack_propagation] – the whole page.
 */
add_shortcode( 'hamshack_propagation', function () {
	ob_start();
	echo '<div class="live-page">';
	foreach ( array(
		array( 'signal', __( 'Where the bands are open (MUF)', 'hamshack' ), '[hamshack_muf]' ),
		array( 'clock', __( 'Best times from my QTH', 'hamshack' ), '[hamshack_best_times]' ),
		array( 'sun', __( 'Space weather', 'hamshack' ), '[hamshack_space_weather]' ),
		array( 'antenna', __( 'Weather at the shack', 'hamshack' ), '[hamshack_weather]' ),
	) as $s ) {
		echo '<section class="live-sec"><h2 class="app-h2">' . hamshack_icon( $s[0], 22 ) . ' ' . esc_html( $s[1] ) . '</h2>' . do_shortcode( $s[2] ) . '</section>'; // phpcs:ignore
	}
	echo '<section class="live-sec"><h2 class="app-h2">' . hamshack_icon( 'sun', 22 ) . ' ' . esc_html__( 'Band conditions', 'hamshack' ) . '</h2>';
	get_template_part( 'template-parts/solar', null, array( 'banner' => hamshack_opt( 'solar_home' ) ) );
	echo '</section></div>';
	return ob_get_clean();
} );
