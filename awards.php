<?php
/**
 * Awards progress (DXCC, WAC, WAZ, ITU, WAS, grids, British Isles) and logbook charts.
 *
 * Everything is computed from the logbook and cached; confirmed = QSL/LoTW/eQSL/QRZ received.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All QSOs as compact rows (only the fields the statistics need).
 *
 * @return array
 */
function hamshack_awards_rows() {
	global $wpdb;
	$keys = array( '_q_call', '_q_date', '_q_time', '_q_band', '_q_mode', '_q_dxcc', '_q_cont', '_q_cqz', '_q_ituz', '_q_state', '_q_grid', '_q_qsl_r', '_q_country', '_q_km', '_q_geo' );
	$in   = "'" . implode( "','", $keys ) . "'";
	$rows = $wpdb->get_results( "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish' WHERE pm.meta_key IN ($in)", ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r['post_id'] ][ substr( $r['meta_key'], 3 ) ] = $r['meta_value'];
	}
	return $out;
}

/**
 * Computed awards & chart data (cached 6 h, cleared whenever a QSO changes).
 */
function hamshack_awards_data() {
	$cached = get_transient( 'hamshack_awards' );
	if ( false !== $cached ) {
		return $cached;
	}
	$rows = hamshack_awards_rows();
	$d    = array(
		'total'   => count( $rows ),
		'dxcc'    => array(),   // dxcc => [ name, cont, w, c, bands => [ band => 0|1 ] ].
		'cont'    => array(),   // cont => 0|1 (worked / confirmed).
		'cqz'     => array(),
		'ituz'    => array(),
		'state'   => array(),
		'grids'   => array(),
		'vhfgrid' => array(),
		'bands'   => array(),
		'modes'   => array(),
		'months'  => array(),
		'hours'   => array_fill( 0, 24, 0 ),
		'years'   => array(),
		'best'    => null,
		'first'   => '',
		'last'    => '',
		'km'      => 0,
		'cfm'     => 0,
	);
	$set = function ( &$arr, $k, $cfm ) {
		if ( '' === $k || null === $k || '0' === (string) $k ) {
			return;
		}
		$arr[ $k ] = isset( $arr[ $k ] ) ? max( $arr[ $k ], $cfm ) : $cfm;
	};
	foreach ( $rows as $q ) {
		$g    = function ( $k ) use ( $q ) {
			return isset( $q[ $k ] ) ? $q[ $k ] : '';
		};
		$cfm  = 'Y' === $g( 'qsl_r' ) ? 1 : 0;
		$band = $g( 'band' );
		$dx   = (int) $g( 'dxcc' );
		$d['cfm'] += $cfm;
		if ( $dx ) {
			if ( ! isset( $d['dxcc'][ $dx ] ) ) {
				$e                 = hamshack_cty_entity( $dx );
				$d['dxcc'][ $dx ]  = array(
					'name'  => $e ? $e['name'] : $g( 'country' ),
					'cont'  => $g( 'cont' ),
					'c'     => 0,
					'n'     => 0,
					'bands' => array(),
				);
			}
			$d['dxcc'][ $dx ]['n']++;
			$d['dxcc'][ $dx ]['c'] = max( $d['dxcc'][ $dx ]['c'], $cfm );
			if ( $band ) {
				$set( $d['dxcc'][ $dx ]['bands'], $band, $cfm );
			}
		}
		$set( $d['cont'], strtoupper( $g( 'cont' ) ), $cfm );
		$set( $d['cqz'], (int) $g( 'cqz' ), $cfm );
		$set( $d['ituz'], (int) $g( 'ituz' ), $cfm );
		if ( in_array( $dx, array( 291, 6, 110 ), true ) && preg_match( '/^[A-Z]{2}$/', strtoupper( $g( 'state' ) ) ) ) {
			$set( $d['state'], strtoupper( $g( 'state' ) ), $cfm );
		}
		$grid = strtoupper( substr( $g( 'grid' ), 0, 4 ) );
		if ( preg_match( '/^[A-R]{2}\d{2}$/', $grid ) ) {
			$set( $d['grids'], $grid, $cfm );
			if ( in_array( $band, array( '6m', '4m', '2m', '70cm', '23cm' ), true ) ) {
				$d['vhfgrid'][ $band ][ $grid ] = 1;
			}
		}
		if ( $band ) {
			$d['bands'][ $band ] = isset( $d['bands'][ $band ] ) ? $d['bands'][ $band ] + 1 : 1;
		}
		$mode = $g( 'mode' );
		if ( $mode ) {
			$d['modes'][ $mode ] = isset( $d['modes'][ $mode ] ) ? $d['modes'][ $mode ] + 1 : 1;
		}
		$date = $g( 'date' );
		if ( $date ) {
			$ym                 = substr( $date, 0, 7 );
			$d['months'][ $ym ] = isset( $d['months'][ $ym ] ) ? $d['months'][ $ym ] + 1 : 1;
			$y                  = substr( $date, 0, 4 );
			$d['years'][ $y ]   = isset( $d['years'][ $y ] ) ? $d['years'][ $y ] + 1 : 1;
			if ( ! $d['first'] || $date < $d['first'] ) {
				$d['first'] = $date;
			}
			if ( $date > $d['last'] ) {
				$d['last'] = $date;
			}
		}
		$time = $g( 'time' );
		if ( preg_match( '/^(\d{2})/', $time, $m ) ) {
			$d['hours'][ (int) $m[1] % 24 ]++;
		}
		$km = (int) $g( 'km' );
		$d['km'] += $km;
		if ( 'dxcc' !== $g( 'geo' ) && ( ! $d['best'] || $km > $d['best']['km'] ) ) {
			$d['best'] = array( 'call' => $g( 'call' ), 'km' => $km, 'country' => $g( 'country' ), 'band' => $band, 'mode' => $mode, 'date' => $date );
		}
	}
	uasort( $d['dxcc'], function ( $a, $b ) {
		return strcmp( $a['name'], $b['name'] );
	} );
	ksort( $d['months'] );
	ksort( $d['years'] );
	arsort( $d['modes'] );
	set_transient( 'hamshack_awards', $d, 6 * HOUR_IN_SECONDS );
	return $d;
}

/**
 * Entity details by DXCC number.
 */
function hamshack_cty_entity( $dxcc ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( hamshack_cty()['e'] as $e ) {
			if ( ! isset( $map[ $e[1] ] ) ) {
				$map[ $e[1] ] = array( 'name' => $e[0], 'cont' => $e[2] );
			}
		}
	}
	return isset( $map[ (int) $dxcc ] ) ? $map[ (int) $dxcc ] : null;
}

/**
 * Count worked / confirmed in a set.
 */
function hamshack_wc( array $set ) {
	return array( count( $set ), count( array_filter( $set ) ) );
}

/**
 * A progress card.
 */
function hamshack_award_card( $title, $sub, $set, $target, $icon = 'globe' ) {
	list( $w, $c ) = hamshack_wc( $set );
	$pw            = $target ? min( 100, round( 100 * $w / $target ) ) : 0;
	$pc            = $target ? min( 100, round( 100 * $c / $target ) ) : 0;
	ob_start();
	?>
	<div class="award-card<?php echo $target && $c >= $target ? ' is-done' : ''; ?>">
		<div class="award-head">
			<span class="award-icon"><?php hamshack_the_icon( $icon, 20 ); ?></span>
			<div><h3><?php echo esc_html( $title ); ?></h3><p class="muted"><?php echo esc_html( $sub ); ?></p></div>
		</div>
		<div class="award-nums">
			<span class="big mono"><?php echo (int) $w; ?></span><span class="of mono">/ <?php echo $target ? (int) $target : '∞'; ?></span>
			<span class="cfm mono"><?php printf( esc_html__( '%d confirmed', 'hamshack' ), (int) $c ); ?></span>
		</div>
		<?php if ( $target ) : ?>
			<div class="award-bar" role="img" aria-label="<?php echo esc_attr( sprintf( __( '%1$d of %2$d worked, %3$d confirmed', 'hamshack' ), $w, $target, $c ) ); ?>"><span class="w" style="width:<?php echo (int) $pw; ?>%"></span><span class="c" style="width:<?php echo (int) $pc; ?>%"></span></div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Grid of numbered cells (zones) or codes (states, continents).
 */
function hamshack_cells( array $all, array $set, $labels = array() ) {
	$out = '<div class="cells">';
	foreach ( $all as $k ) {
		$st    = isset( $set[ $k ] ) ? ( $set[ $k ] ? 'is-c' : 'is-w' ) : '';
		$title = isset( $labels[ $k ] ) ? $labels[ $k ] : $k;
		$title .= isset( $set[ $k ] ) ? ( $set[ $k ] ? ' – ' . __( 'confirmed', 'hamshack' ) : ' – ' . __( 'worked', 'hamshack' ) ) : '';
		$out  .= '<span class="cell mono ' . esc_attr( $st ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $k ) . '</span>';
	}
	return $out . '</div>';
}

/**
 * Simple SVG column chart.
 *
 * @param array  $data  label => value.
 * @param string $color Bar colour.
 * @param int    $every Show every n-th label.
 */
function hamshack_svg_columns( array $data, $color = '#ffb020', $every = 1, $title = '' ) {
	if ( ! $data ) {
		return '';
	}
	$w     = 720;
	$hgt   = 220;
	$pad_b = 26;
	$pad_t = 16;
	$n     = count( $data );
	$max   = max( 1, max( $data ) );
	$bw    = $w / $n;
	$svg   = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $hgt . '" role="img" aria-label="' . esc_attr( $title ) . '"><title>' . esc_html( $title ) . '</title>';
	foreach ( array( 0.25, 0.5, 0.75, 1 ) as $f ) {
		$y    = $hgt - $pad_b - ( $hgt - $pad_b - $pad_t ) * $f;
		$svg .= '<line class="gl" x1="0" x2="' . $w . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '"/>';
		$svg .= '<text class="gv" x="2" y="' . round( $y - 3, 1 ) . '">' . (int) round( $max * $f ) . '</text>';
	}
	$i = 0;
	foreach ( $data as $label => $v ) {
		$bh   = ( $hgt - $pad_b - $pad_t ) * $v / $max;
		$x    = $i * $bw;
		$svg .= '<g><title>' . esc_html( $label . ': ' . $v ) . '</title><rect x="' . round( $x + $bw * 0.15, 1 ) . '" y="' . round( $hgt - $pad_b - $bh, 1 ) . '" width="' . round( $bw * 0.7, 1 ) . '" height="' . round( max( $bh, $v ? 1 : 0 ), 1 ) . '" rx="2" fill="' . esc_attr( $color ) . '"/></g>';
		if ( 0 === $i % $every ) {
			$svg .= '<text class="gx" x="' . round( 0 === $i ? $x + 2 : ( $i === $n - 1 ? $x + $bw - 2 : $x + $bw / 2 ), 1 ) . '" y="' . ( $hgt - 8 ) . '" text-anchor="' . ( 0 === $i ? 'start' : ( $i === $n - 1 ? 'end' : 'middle' ) ) . '">' . esc_html( $label ) . '</text>';
		}
		$i++;
	}
	return $svg . '</svg>';
}

/**
 * Horizontal bars with per-item colour.
 */
function hamshack_hbars( array $data, $colors = array() ) {
	if ( ! $data ) {
		return '';
	}
	$max = max( 1, max( $data ) );
	$out = '<div class="hbars">';
	foreach ( $data as $k => $v ) {
		$col  = isset( $colors[ $k ] ) ? $colors[ $k ] : 'var(--amber)';
		$out .= '<div class="hbar"><span class="k mono">' . esc_html( $k ) . '</span><span class="track"><span style="width:' . esc_attr( round( 100 * $v / $max, 1 ) ) . '%;background:' . esc_attr( $col ) . '"></span></span><span class="v mono">' . esc_html( number_format_i18n( $v ) ) . '</span></div>';
	}
	return $out . '</div>';
}

/**
 * [hamshack_awards] – the whole statistics & awards page.
 */
add_shortcode( 'hamshack_awards', function () {
	$d = hamshack_awards_data();
	if ( ! $d['total'] ) {
		return '<div class="empty-state">' . esc_html__( 'The logbook is empty – statistics appear here after the first QSOs.', 'hamshack' ) . '</div>';
	}
	$conts  = array( 'EU' => __( 'Europe', 'hamshack' ), 'AS' => __( 'Asia', 'hamshack' ), 'AF' => __( 'Africa', 'hamshack' ), 'NA' => __( 'North America', 'hamshack' ), 'SA' => __( 'South America', 'hamshack' ), 'OC' => __( 'Oceania', 'hamshack' ) );
	$states = explode( ' ', 'AL AK AZ AR CA CO CT DE FL GA HI ID IL IN IA KS KY LA ME MD MA MI MN MS MO MT NE NV NH NJ NM NY NC ND OH OK OR PA RI SC SD TN TX UT VT VA WA WV WI WY' );
	$isles  = array( 223 => __( 'England', 'hamshack' ), 279 => __( 'Scotland', 'hamshack' ), 294 => __( 'Wales', 'hamshack' ), 265 => __( 'Northern Ireland', 'hamshack' ), 114 => __( 'Isle of Man', 'hamshack' ), 122 => __( 'Jersey', 'hamshack' ), 106 => __( 'Guernsey', 'hamshack' ), 245 => __( 'Ireland', 'hamshack' ) );
	$dxset  = array();
	foreach ( $d['dxcc'] as $num => $e ) {
		$dxset[ $num ] = $e['c'];
	}
	$isle_set = array_intersect_key( $dxset, $isles );
	$cont_set = array_intersect_key( $d['cont'], $conts );
	$colors   = hamshack_band_colors();
	$bands    = array();
	foreach ( hamshack_all_bands() as $b ) {
		if ( isset( $d['bands'][ $b ] ) ) {
			$bands[ $b ] = $d['bands'][ $b ];
		}
	}
	// Last 24 months, zero-filled.
	$months = array();
	$start  = new DateTime( 'first day of this month', new DateTimeZone( 'UTC' ) );
	$start->modify( '-23 months' );
	for ( $i = 0; $i < 24; $i++ ) {
		$k            = $start->format( 'Y-m' );
		$months[ $k ] = isset( $d['months'][ $k ] ) ? $d['months'][ $k ] : 0;
		$start->modify( '+1 month' );
	}
	$mlabels = array();
	foreach ( $months as $k => $v ) {
		$mlabels[ date_i18n( 'M y', strtotime( $k . '-01' ) ) ] = $v;
	}
	$hours = array();
	foreach ( $d['hours'] as $h => $v ) {
		$hours[ sprintf( '%02d', $h ) ] = $v;
	}
	$modes = array_slice( $d['modes'], 0, 8, true );
	$top   = $d['dxcc'];
	uasort( $top, function ( $a, $b ) {
		return $b['n'] - $a['n'];
	} );
	$top        = array_slice( $top, 0, 12, true );
	$used_bands = array_keys( $bands );
	ob_start();
	?>
	<div class="hs-app awards-app">
		<div class="award-hero">
			<div class="hero-stat"><span class="num mono"><?php echo esc_html( number_format_i18n( $d['total'] ) ); ?></span><span class="lbl"><?php esc_html_e( 'QSOs', 'hamshack' ); ?></span></div>
			<div class="hero-stat"><span class="num mono"><?php echo esc_html( number_format_i18n( count( $d['dxcc'] ) ) ); ?></span><span class="lbl"><?php esc_html_e( 'DXCC entities', 'hamshack' ); ?></span></div>
			<div class="hero-stat"><span class="num mono"><?php echo esc_html( number_format_i18n( count( $d['grids'] ) ) ); ?></span><span class="lbl"><?php esc_html_e( 'Grid squares', 'hamshack' ); ?></span></div>
			<div class="hero-stat"><span class="num mono"><?php echo esc_html( number_format_i18n( round( $d['km'] ) ) ); ?></span><span class="lbl"><?php esc_html_e( 'km in total', 'hamshack' ); ?></span></div>
			<?php if ( $d['best'] ) : ?>
				<div class="hero-stat is-best"><span class="num mono"><?php echo esc_html( number_format_i18n( $d['best']['km'] ) ); ?> km</span><span class="lbl"><?php printf( esc_html__( 'Best DX: %1$s, %2$s', 'hamshack' ), esc_html( $d['best']['call'] ), esc_html( $d['best']['country'] ) ); ?></span></div>
			<?php endif; ?>
		</div>

		<h2 class="app-h2"><?php esc_html_e( 'Awards progress', 'hamshack' ); ?></h2>
		<div class="award-grid">
			<?php
			echo hamshack_award_card( 'DXCC', __( 'Countries / entities – 100 for the award', 'hamshack' ), $dxset, 100, 'globe' ); // phpcs:ignore
			echo hamshack_award_card( 'WAC', __( 'Worked All Continents', 'hamshack' ), $cont_set, 6, 'antenna' ); // phpcs:ignore
			echo hamshack_award_card( 'WAZ', __( 'Worked All Zones – CQ zones', 'hamshack' ), $d['cqz'], 40, 'map' ); // phpcs:ignore
			echo hamshack_award_card( 'ITU', __( 'ITU zones', 'hamshack' ), $d['ituz'], 90, 'map' ); // phpcs:ignore
			echo hamshack_award_card( 'WAS', __( 'Worked All States (USA)', 'hamshack' ), $d['state'], 50, 'flag' ); // phpcs:ignore
			echo hamshack_award_card( __( 'British Isles', 'hamshack' ), __( 'All 8 DXCC entities of the British Isles', 'hamshack' ), $isle_set, 8, 'home' ); // phpcs:ignore
			echo hamshack_award_card( __( 'Grid squares', 'hamshack' ), __( 'Maidenhead squares (4 characters)', 'hamshack' ), $d['grids'], 0, 'grid' ); // phpcs:ignore
			foreach ( $d['vhfgrid'] as $b => $gs ) {
				echo hamshack_award_card( 'VUCC ' . $b, __( 'Grid squares on this band (VUCC: 100 on 6 m / 2 m, 50 on 70 cm)', 'hamshack' ), $gs, in_array( $b, array( '6m', '2m' ), true ) ? 100 : 50, 'radio' ); // phpcs:ignore
			}
			?>
		</div>
		<p class="app-note"><span class="cell-key is-w"></span> <?php esc_html_e( 'worked', 'hamshack' ); ?> &nbsp; <span class="cell-key is-c"></span> <?php esc_html_e( 'confirmed (QSL card, LoTW, eQSL or QRZ.com)', 'hamshack' ); ?></p>

		<div class="award-panels">
			<section class="panel">
				<h3><?php esc_html_e( 'Continents', 'hamshack' ); ?></h3>
				<?php echo hamshack_cells( array_keys( $conts ), $d['cont'], $conts ); // phpcs:ignore ?>
				<h3 class="mt"><?php esc_html_e( 'British Isles', 'hamshack' ); ?></h3>
				<div class="cells">
					<?php foreach ( $isles as $num => $name ) : ?>
						<span class="cell cell-wide <?php echo isset( $dxset[ $num ] ) ? ( $dxset[ $num ] ? 'is-c' : 'is-w' ) : ''; ?>"><?php echo esc_html( $name ); ?></span>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'CQ zones', 'hamshack' ); ?></h3>
				<?php echo hamshack_cells( range( 1, 40 ), $d['cqz'] ); // phpcs:ignore ?>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'US states', 'hamshack' ); ?></h3>
				<?php echo hamshack_cells( $states, $d['state'] ); // phpcs:ignore ?>
			</section>
		</div>

		<h2 class="app-h2"><?php esc_html_e( 'Activity', 'hamshack' ); ?></h2>
		<div class="chart-grid">
			<section class="panel chart-wide">
				<h3><?php esc_html_e( 'QSOs per month (last 2 years)', 'hamshack' ); ?></h3>
				<?php echo hamshack_svg_columns( $mlabels, '#ffb020', 3, __( 'QSOs per month', 'hamshack' ) ); // phpcs:ignore ?>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'Bands', 'hamshack' ); ?></h3>
				<?php echo hamshack_hbars( $bands, $colors ); // phpcs:ignore ?>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'Modes', 'hamshack' ); ?></h3>
				<?php echo hamshack_hbars( $modes ); // phpcs:ignore ?>
			</section>
			<section class="panel chart-wide">
				<h3><?php esc_html_e( 'Time of day (UTC)', 'hamshack' ); ?></h3>
				<?php echo hamshack_svg_columns( $hours, '#22d3ee', 2, __( 'QSOs by hour of day', 'hamshack' ) ); // phpcs:ignore ?>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'Most worked countries', 'hamshack' ); ?></h3>
				<?php
				$tc = array();
				foreach ( $top as $e ) {
					$tc[ $e['name'] ] = $e['n'];
				}
				echo hamshack_hbars( $tc ); // phpcs:ignore
				?>
			</section>
			<section class="panel">
				<h3><?php esc_html_e( 'Years', 'hamshack' ); ?></h3>
				<?php echo hamshack_hbars( $d['years'] ); // phpcs:ignore ?>
				<p class="app-note"><?php printf( esc_html__( 'First QSO in the log: %1$s · latest: %2$s', 'hamshack' ), esc_html( $d['first'] ), esc_html( $d['last'] ) ); ?></p>
			</section>
		</div>

		<h2 class="app-h2"><?php esc_html_e( 'DXCC by band', 'hamshack' ); ?></h2>
		<div class="table-scroll tall">
			<table class="data-table dxcc-table">
				<thead><tr><th><?php esc_html_e( 'Entity', 'hamshack' ); ?></th><th><?php esc_html_e( 'Cont.', 'hamshack' ); ?></th><th>#</th>
				<?php foreach ( $used_bands as $b ) : ?>
					<th class="c mono"><?php echo esc_html( $b ); ?></th>
				<?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $d['dxcc'] as $num => $e ) : ?>
					<tr>
						<td><?php echo esc_html( $e['name'] ); ?></td>
						<td class="mono muted"><?php echo esc_html( $e['cont'] ); ?></td>
						<td class="mono muted"><?php echo (int) $e['n']; ?></td>
						<?php foreach ( $used_bands as $b ) : ?>
							<td class="c"><?php echo isset( $e['bands'][ $b ] ) ? ( $e['bands'][ $b ] ? '<span class="dot is-c" title="' . esc_attr__( 'confirmed', 'hamshack' ) . '"></span>' : '<span class="dot is-w" title="' . esc_attr__( 'worked', 'hamshack' ) . '"></span>' ) : ''; // phpcs:ignore ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );
