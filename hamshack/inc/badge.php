<?php
/**
 * Live SVG badges for use outside the website (e.g. QRZ.com biography):
 *   /?hamshack_badge=status   – ON AIR pill + VFO display (live from the rig when available)
 *   /?hamshack_badge=log      – QSO statistics + latest contacts
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'hamshack_badge';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$which = get_query_var( 'hamshack_badge' );
	if ( ! $which ) {
		return;
	}
	if ( function_exists( 'hamshack_rig_expire' ) ) {
		hamshack_rig_expire();
	}
	switch ( $which ) {
		case 'log':
			$svg = hamshack_badge_log();
			break;
		case 'logo':
			$svg = hamshack_logo_svg( '', true );
			break;
		case 'qsl':
			$svg = hamshack_qsl_svg();
			break;
		default:
			$svg = hamshack_badge_status();
	}
	nocache_headers();
	header( 'Content-Type: image/svg+xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=60' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'Access-Control-Allow-Origin: *' );
	echo $svg; // phpcs:ignore -- built from escaped parts below.
	exit;
} );

function hamshack_x( $s ) {
	return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function hamshack_badge_wrap( $w, $h, $inner, $title ) {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img"><title>' . hamshack_x( $title ) . '</title>'
		. '<defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#121a23"/><stop offset="1" stop-color="#0b0f14"/></linearGradient>'
		. '<filter id="gl" x="-20%" y="-50%" width="140%" height="200%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>'
		. '<rect x="1" y="1" width="' . ( $w - 2 ) . '" height="' . ( $h - 2 ) . '" rx="16" fill="url(#bg)" stroke="#223040" stroke-width="2"/>'
		. $inner . '</svg>';
}

/**
 * Status / VFO badge (760 × 150).
 */
function hamshack_badge_status() {
	$mono   = "font-family=\"'JetBrains Mono',Consolas,'DejaVu Sans Mono',Menlo,monospace\"";
	$sans   = "font-family=\"'Segoe UI',Inter,Roboto,Arial,sans-serif\"";
	$status = hamshack_opt( 'status', 'off' );
	$colors = array( 'on' => '#ff3b3b', 'portable' => '#ffb020', 'listen' => '#22d3ee', 'off' => '#6b7c8c' );
	$col    = isset( $colors[ $status ] ) ? $colors[ $status ] : '#6b7c8c';
	$live   = function_exists( 'hamshack_rig_online' ) && hamshack_rig_online();
	$rig    = $live ? hamshack_rig_state() : array();
	$freq   = $live ? hamshack_rig_format_freq( $rig['freq_hz'] ) : hamshack_opt( 'status_freq' );
	$mode   = hamshack_opt( 'status_mode' );
	$band   = $live ? hamshack_band_from_freq( $rig['freq_hz'] / 1000000 ) : ( $freq ? hamshack_band_from_freq( (float) $freq ) : '' );
	$label  = strtoupper( hamshack_status_label( $status ) );
	$pillw  = 34 + (int) ( strlen( $label ) * 9.2 );
	$o      = '';

	// Left: callsign + QTH.
	$o .= '<text x="28" y="62" ' . $mono . ' font-size="40" font-weight="800" fill="#f1f6fb" letter-spacing="2">' . hamshack_x( hamshack_opt( 'callsign' ) ) . '</text>';
	$o .= '<text x="30" y="92" ' . $sans . ' font-size="14" fill="#8aa0b4">' . hamshack_x( hamshack_opt( 'qth' ) ) . '</text>';
	$o .= '<text x="30" y="116" ' . $mono . ' font-size="13" fill="#22d3ee">' . hamshack_x( hamshack_join( array( hamshack_opt( 'locator' ) ? 'LOC ' . hamshack_opt( 'locator' ) : '', hamshack_opt( 'itu_zone' ) ? 'ITU ' . hamshack_opt( 'itu_zone' ) : '', hamshack_opt( 'cq_zone' ) ? 'CQ ' . hamshack_opt( 'cq_zone' ) : '' ) ) ) . '</text>';

	// Status pill.
	$px = 300;
	$o .= '<rect x="' . $px . '" y="24" width="' . $pillw . '" height="28" rx="14" fill="' . $col . '" fill-opacity=".12" stroke="' . $col . '" stroke-opacity=".6"/>';
	$o .= '<circle cx="' . ( $px + 16 ) . '" cy="38" r="5" fill="' . $col . '" filter="url(#gl)"/>';
	$o .= '<text x="' . ( $px + 28 ) . '" y="43" ' . $mono . ' font-size="13" font-weight="700" fill="' . $col . '" letter-spacing="1">' . hamshack_x( $label ) . '</text>';

	// VFO display.
	$vx = 300;
	$vw = 430;
	$o .= '<rect x="' . $vx . '" y="62" width="' . $vw . '" height="62" rx="10" fill="#050a07" stroke="' . ( $live ? '#2a6b45' : '#1d2b22' ) . '"/>';
	if ( 'off' !== $status && $freq ) {
		$o .= '<text x="' . ( $vx + 18 ) . '" y="106" ' . $mono . ' font-size="34" font-weight="700" fill="#ffb020" filter="url(#gl)">' . hamshack_x( $freq ) . '</text>';
		$o .= '<text x="' . ( $vx + 18 + (int) ( strlen( $freq ) * 20.6 ) + 8 ) . '" y="106" ' . $mono . ' font-size="13" fill="#ffd166">MHz</text>';
		$o .= '<text x="' . ( $vx + $vw - 16 ) . '" y="90" text-anchor="end" ' . $mono . ' font-size="16" font-weight="700" fill="#39ff88" filter="url(#gl)">' . hamshack_x( $mode ) . '</text>';
		if ( $band ) {
			$o .= '<text x="' . ( $vx + $vw - 16 ) . '" y="112" text-anchor="end" ' . $mono . ' font-size="12" fill="#22d3ee">' . hamshack_x( $band ) . '</text>';
		}
	} else {
		$o .= '<text x="' . ( $vx + 18 ) . '" y="102" ' . $mono . ' font-size="22" fill="#3b4d5e">QRT · listening soon</text>';
	}
	$sub = $live ? 'LIVE FROM THE RIG' . ( $rig['rig'] ? ' · ' . strtoupper( $rig['rig'] ) : '' ) : 'UPDATED ' . gmdate( 'H:i' ) . 'z';
	$o  .= '<text x="' . ( $px + $pillw + 14 ) . '" y="43" ' . $mono . ' font-size="11" fill="' . ( $live ? '#39ff88' : '#8aa0b4' ) . '" letter-spacing="1">' . hamshack_x( $sub ) . '</text>';
	$o  .= '<text x="' . ( $vx + $vw ) . '" y="142" text-anchor="end" ' . $sans . ' font-size="10" fill="#4f6273">' . hamshack_x( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</text>';

	return hamshack_badge_wrap( 760, 150, $o, hamshack_opt( 'callsign' ) . ' station status' );
}

/**
 * Logbook badge (760 × 250): stats + latest 5 QSOs.
 */
function hamshack_badge_log() {
	$mono  = "font-family=\"'JetBrains Mono',Consolas,'DejaVu Sans Mono',Menlo,monospace\"";
	$sans  = "font-family=\"'Segoe UI',Inter,Roboto,Arial,sans-serif\"";
	$s     = hamshack_log_stats();
	$ids   = hamshack_recent_qsos( 5 );
	$o     = '';
	$tiles = array(
		array( $s['total'], 'QSOS', '#ffb020' ),
		array( $s['calls'], 'UNIQUE CALLS', '#22d3ee' ),
		array( $s['countries'], 'COUNTRIES', '#39ff88' ),
		array( $s['grids'], 'GRIDS', '#a78bfa' ),
	);
	foreach ( $tiles as $i => $t ) {
		$x  = 24 + $i * 180;
		$o .= '<rect x="' . $x . '" y="20" width="168" height="64" rx="10" fill="#121a23" stroke="#223040"/>';
		$o .= '<text x="' . ( $x + 14 ) . '" y="54" ' . $mono . ' font-size="26" font-weight="800" fill="' . $t[2] . '">' . hamshack_x( number_format( (int) $t[0] ) ) . '</text>';
		$o .= '<text x="' . ( $x + 14 ) . '" y="74" ' . $sans . ' font-size="10" fill="#8aa0b4" letter-spacing="1.5">' . $t[1] . '</text>';
	}
	$cols = array( 24 => 'DATE', 134 => 'UTC', 204 => 'CALL', 340 => 'BAND', 420 => 'MODE', 510 => 'RST', 600 => 'COUNTRY' );
	foreach ( $cols as $x => $h ) {
		$o .= '<text x="' . ( $x + 6 ) . '" y="108" ' . $sans . ' font-size="10" font-weight="700" fill="#8aa0b4" letter-spacing="1.2">' . $h . '</text>';
	}
	$o .= '<path d="M24 116 H736" stroke="#223040"/>';
	if ( ! $ids ) {
		$o .= '<text x="30" y="150" ' . $sans . ' font-size="14" fill="#8aa0b4">No QSOs logged yet – hope to work you soon!</text>';
	}
	foreach ( $ids as $i => $id ) {
		$y  = 138 + $i * 22;
		$g  = function ( $k ) use ( $id ) {
			return get_post_meta( $id, '_q_' . $k, true );
		};
		$o .= '<text x="30" y="' . $y . '" ' . $mono . ' font-size="12" fill="#d6e2ee">' . hamshack_x( $g( 'date' ) ) . '</text>';
		$o .= '<text x="140" y="' . $y . '" ' . $mono . ' font-size="12" fill="#d6e2ee">' . hamshack_x( $g( 'time' ) ) . '</text>';
		$o .= '<text x="210" y="' . $y . '" ' . $mono . ' font-size="13" font-weight="700" fill="#ffd166">' . hamshack_x( $g( 'call' ) ) . '</text>';
		$o .= '<text x="346" y="' . $y . '" ' . $mono . ' font-size="12" fill="#22d3ee">' . hamshack_x( $g( 'band' ) ) . '</text>';
		$o .= '<text x="426" y="' . $y . '" ' . $mono . ' font-size="12" fill="#d6e2ee">' . hamshack_x( $g( 'mode' ) ) . '</text>';
		$o .= '<text x="516" y="' . $y . '" ' . $mono . ' font-size="12" fill="#d6e2ee">' . hamshack_x( $g( 'rst_s' ) . '/' . $g( 'rst_r' ) ) . '</text>';
		$o .= '<text x="606" y="' . $y . '" ' . $sans . ' font-size="12" fill="#b8c7d6">' . hamshack_x( wp_html_excerpt( $g( 'country' ), 18, '…' ) ) . '</text>';
	}
	return hamshack_badge_wrap( 760, 250, $o, hamshack_opt( 'callsign' ) . ' logbook' );
}

/* --------------------------------------------------------------------------
 * QRZ.com biography generator (inline-styled HTML, no scripts)
 * ------------------------------------------------------------------------ */

function hamshack_qrz_bio_html() {
	$site  = untrailingslashit( home_url() );
	$img   = HAMSHACK_URI . '/assets/img/';
	$page  = function ( $slug ) use ( $site ) {
		$p = get_page_by_path( $slug );
		return $p ? get_permalink( $p ) : $site . '/';
	};
	$blog  = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : $site . '/';
	$e     = 'esc_html';
	$u     = 'esc_url';
	$font  = "font-family:'Segoe UI',Roboto,Arial,sans-serif;";
	$mono  = "font-family:Consolas,'Courier New',monospace;";
	$btn   = 'display:inline-block;margin:4px 6px 4px 0;padding:9px 14px;border-radius:9px;text-decoration:none;font-weight:700;font-size:14px;' . $font;
	$b1    = $btn . 'background:#ffb020;color:#1a1204;';
	$b2    = $btn . 'background:#17222e;color:#f1f6fb;border:1px solid #2c3d50;';
	$h     = 'margin:0 0 10px;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#ffb020;' . $mono;
	$chipb = 'display:inline-block;margin:0 5px 6px 0;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:700;' . $mono;
	$ts    = gmdate( 'YmdH' ); // Refreshes the badge URLs once an hour against aggressive caches.

	$rows = '';
	foreach ( hamshack_lines( hamshack_opt( 'rigs' ) ) as $l ) {
		$rows .= '<div style="margin:0 0 6px;padding:9px 12px;background:#121a23;border:1px solid #223040;border-radius:9px;font-size:14px;color:#d6e2ee;"><span style="color:#ffb020;">&#9656;</span> ' . $e( $l ) . '</div>';
	}
	foreach ( hamshack_lines( hamshack_opt( 'antennas' ) ) as $l ) {
		$rows .= '<div style="margin:0 0 6px;padding:9px 12px;background:#121a23;border:1px solid #223040;border-radius:9px;font-size:14px;color:#d6e2ee;"><span style="color:#22d3ee;">&#9656;</span> ' . $e( $l ) . '</div>';
	}
	$chips = '';
	foreach ( (array) hamshack_opt( 'bands', array() ) as $b ) {
		$chips .= '<span style="' . $chipb . 'background:#0f2a24;color:#39ff88;border:1px solid #1f6b4a;">' . $e( $b ) . '</span>';
	}
	foreach ( (array) hamshack_opt( 'modes', array() ) as $m ) {
		$chips .= '<span style="' . $chipb . 'background:#2a1f08;color:#ffd166;border:1px solid #6b4f12;">' . $e( $m ) . '</span>';
	}

	$html  = '<!-- ' . $e( hamshack_opt( 'callsign' ) ) . ' QRZ biography – generated by the Ham Shack WordPress theme -->' . "\n";
	$html .= '<div style="max-width:760px;margin:0 auto;background:#0b0f14;border:1px solid #223040;border-radius:18px;overflow:hidden;color:#d6e2ee;' . $font . '">' . "\n";

	// Hero.
	$html .= '<div style="padding:26px 26px 22px;background-color:#0b0f14;background-image:linear-gradient(90deg,rgba(11,15,20,.95),rgba(11,15,20,.55)),url(' . $u( $img . 'hero-scene.svg' ) . ');background-size:cover;background-position:center bottom;">' . "\n";
	$html .= '<a href="' . $u( $site ) . '"><img src="' . $u( $site . '/?hamshack_badge=logo' ) . '" alt="' . esc_attr( hamshack_opt( 'callsign' ) ) . '" width="330" style="display:block;max-width:100%;height:auto;border:0;"></a>' . "\n";
	$html .= '<h2 style="margin:18px 0 8px;font-size:30px;line-height:1.15;color:#f1f6fb;' . $font . '">' . $e( hamshack_opt( 'hero_title' ) ) . '</h2>' . "\n";
	$html .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#b9c9d9;">' . $e( hamshack_opt( 'hero_text' ) ) . '</p>' . "\n";
	$html .= '<a href="' . $u( $site ) . '" style="' . $b1 . '">' . $e( wp_parse_url( $site, PHP_URL_HOST ) ) . ' &rsaquo;' . '</a>';
	$html .= '<a href="' . $u( $blog ) . '" style="' . $b2 . '">Blog</a>';
	$html .= '<a href="' . $u( $page( 'logbook' ) ) . '" style="' . $b2 . '">Are you in my log?</a>';
	$html .= '<a href="' . $u( $page( 'calendar' ) ) . '" style="' . $b2 . '">Calendar</a>';
	$html .= '<a href="' . $u( $page( 'contact' ) ) . '" style="' . $b2 . '">Contact</a>' . "\n";
	$html .= '</div>' . "\n";

	// Live status.
	$html .= '<div style="padding:18px 22px 6px;"><a href="' . $u( $site ) . '"><img src="' . $u( $site . '/?hamshack_badge=status&amp;t=' . $ts ) . '" alt="Live station status" width="716" style="display:block;width:100%;max-width:716px;height:auto;border:0;"></a></div>' . "\n";

	// Station.
	$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';
	$html .= '<td valign="top" style="padding:16px 11px 10px 22px;width:50%;"><p style="' . $h . '">The station</p>' . $rows . '<div style="margin-top:10px;">' . $chips . '</div></td>';
	$html .= '<td valign="middle" style="padding:16px 22px 10px 11px;width:50%;"><img src="' . $u( $img . 'rig.svg' ) . '" alt="" width="340" style="display:block;width:100%;height:auto;border:0;"></td>';
	$html .= '</tr></table>' . "\n";

	// Logbook.
	$html .= '<div style="padding:10px 22px 6px;"><p style="' . $h . '">Latest from the logbook</p><a href="' . $u( $page( 'logbook' ) ) . '"><img src="' . $u( $site . '/?hamshack_badge=log&amp;t=' . $ts ) . '" alt="Logbook statistics and latest QSOs" width="716" style="display:block;width:100%;max-width:716px;height:auto;border:0;"></a></div>' . "\n";

	// N0NBH + QSL.
	$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>';
	$html .= '<td valign="top" align="center" style="padding:16px 11px 16px 22px;width:36%;"><p style="' . $h . 'text-align:left;">Band conditions</p><center><a href="https://www.hamqsl.com/solar.html" title="Click to add Solar-Terrestrial Data to your website!"><img src="https://www.hamqsl.com/solarn0nbh.php" alt="Solar-terrestrial data by N0NBH" style="border:0;"></a></center></td>';
	$html .= '<td valign="top" style="padding:16px 22px 16px 11px;"><p style="' . $h . '">QSL</p><img src="' . $u( $site . '/?hamshack_badge=qsl' ) . '" alt="' . esc_attr( hamshack_opt( 'callsign' ) ) . ' QSL card" width="440" style="display:block;width:100%;height:auto;border-radius:12px;border:0;"><p style="margin:12px 0 0;font-size:14px;line-height:1.6;color:#b9c9d9;">' . $e( hamshack_opt( 'qsl_info' ) ) . '</p></td>';
	$html .= '</tr></table>' . "\n";

	// Footer.
	$html .= '<div style="padding:14px 22px;background:#070a0e;border-top:1px solid #223040;font-size:13px;color:#8aa0b4;"><span style="color:#22d3ee;letter-spacing:2px;' . $mono . '">' . $e( hamshack_morse_text( hamshack_opt( 'callsign' ) ) ) . '</span> &nbsp; ' . $e( hamshack_opt( 'footer_text' ) ) . ' · <a href="' . $u( $site ) . '" style="color:#22d3ee;">' . $e( wp_parse_url( $site, PHP_URL_HOST ) ) . '</a></div>' . "\n";
	$html .= '</div>';

	// QRZ.com cuts the biography at the first 4-byte character (emoji etc.), so strip them all.
	$html = preg_replace( '/[\x{10000}-\x{10FFFF}]/u', '', $html );
	$html = preg_replace( '/&#(?:x0*1[0-9a-f]{4}|x0*10[0-9a-f]{4}|0*(?:6553[6-9]|655[4-9]\d|65[6-9]\d\d|6[6-9]\d{3}|[7-9]\d{4}|[1-9]\d{5,}));/i', '', $html );
	return $html;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'QRZ biography', 'hamshack' ), __( 'QRZ biography', 'hamshack' ), 'edit_posts', 'hamshack-qrzbio', 'hamshack_qrz_bio_page' );
}, 26 );

function hamshack_qrz_bio_page() {
	$code = hamshack_qrz_bio_html();
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'QRZ.com biography', 'hamshack' ); ?></h1>
		<p><?php esc_html_e( 'A ready-made biography for your QRZ.com page in the style of this website. Status and logbook are live images served by this site, so they stay up to date on QRZ automatically.', 'hamshack' ); ?></p>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'book' ); ?> <?php esc_html_e( 'Code to paste', 'hamshack' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Click “Copy code”.', 'hamshack' ); ?></li>
				<li><?php esc_html_e( 'On QRZ.com open your callsign page → Edit → Biography.', 'hamshack' ); ?></li>
				<li><?php esc_html_e( 'Switch the editor to HTML / Source mode, delete the old content, paste, and save.', 'hamshack' ); ?></li>
			</ol>
			<p><button type="button" class="button button-primary" id="hs-copy-bio"><?php esc_html_e( 'Copy code', 'hamshack' ); ?></button> <span id="hs-copy-ok" style="display:none;color:#00a32a;font-weight:600"><?php esc_html_e( 'Copied!', 'hamshack' ); ?></span></p>
			<textarea id="hs-bio-code" class="large-text code" rows="8" readonly><?php echo esc_textarea( $code ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Changed your equipment or texts in Station settings? Come back here and copy the code again.', 'hamshack' ); ?></p>
		</div>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'globe' ); ?> <?php esc_html_e( 'Preview', 'hamshack' ); ?></h2>
			<div style="background:#fff;padding:20px;border:1px solid #dcdcde;border-radius:8px"><?php echo $code; // phpcs:ignore -- generated above from escaped values. ?></div>
		</div>
	</div>
	<script>
	document.getElementById('hs-copy-bio').addEventListener('click', function () {
		var t = document.getElementById('hs-bio-code');
		t.select();
		var done = function () { document.getElementById('hs-copy-ok').style.display = 'inline'; };
		if (navigator.clipboard) { navigator.clipboard.writeText(t.value).then(done, function () { document.execCommand('copy'); done(); }); }
		else { document.execCommand('copy'); done(); }
	});
	</script>
	<?php
}
