<?php
/**
 * Brand graphics generated from the station settings: logo (with Morse underline) and QSL card.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Morse code for a character ('.' and '-').
 */
function hamshack_morse( $ch ) {
	static $map = array(
		'A' => '.-', 'B' => '-...', 'C' => '-.-.', 'D' => '-..', 'E' => '.', 'F' => '..-.', 'G' => '--.', 'H' => '....',
		'I' => '..', 'J' => '.---', 'K' => '-.-', 'L' => '.-..', 'M' => '--', 'N' => '-.', 'O' => '---', 'P' => '.--.',
		'Q' => '--.-', 'R' => '.-.', 'S' => '...', 'T' => '-', 'U' => '..-', 'V' => '...-', 'W' => '.--', 'X' => '-..-',
		'Y' => '-.--', 'Z' => '--..', '0' => '-----', '1' => '.----', '2' => '..---', '3' => '...--', '4' => '....-',
		'5' => '.....', '6' => '-....', '7' => '--...', '8' => '---..', '9' => '----.', '/' => '-..-.',
	);
	$ch = strtoupper( $ch );
	return isset( $map[ $ch ] ) ? $map[ $ch ] : '';
}

/**
 * Callsign as Morse text, e.g. "-- -.... --- . .-.".
 */
function hamshack_morse_text( $text ) {
	$out = array();
	foreach ( str_split( strtoupper( (string) $text ) ) as $ch ) {
		$m = hamshack_morse( $ch );
		if ( $m ) {
			$out[] = $m;
		}
	}
	return implode( ' ', $out );
}

/**
 * Callsign split so that the first digit (the prefix number) can be highlighted.
 */
function hamshack_call_parts( $call ) {
	if ( preg_match( '/^([^0-9]*)([0-9])(.*)$/', $call, $m ) ) {
		return array( $m[1], $m[2], $m[3] );
	}
	return array( $call, '', '' );
}

function hamshack_svg_esc( $s ) {
	return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Logo SVG (inline markup, or a standalone document when $standalone is true).
 */
function hamshack_logo_svg( $class = '', $standalone = false ) {
	static $n = 0;
	$n++;
	$id   = 'hsl' . $n;
	$call = strtoupper( hamshack_opt( 'callsign', 'N0CALL' ) );
	$qth  = hamshack_opt( 'qth' );
	$city = $qth ? strtoupper( trim( strtok( $qth, ',' ) ) ) : '';
	$sub  = hamshack_join( array( __( 'AMATEUR RADIO', 'hamshack' ), $city, strtoupper( substr( hamshack_opt( 'locator' ), 0, 4 ) ) ) );
	list( $a, $digit, $b ) = hamshack_call_parts( $call );

	$text_w = 104 + (int) ( strlen( $call ) * 29.6 );
	$sub_w  = 106 + (int) ( strlen( $sub ) * 8.9 );
	$w      = max( 300, $text_w, $sub_w ) + 16;

	// Morse underline.
	$x     = 106;
	$morse = '';
	foreach ( str_split( $call ) as $ch ) {
		$code = hamshack_morse( $ch );
		if ( ! $code ) {
			continue;
		}
		foreach ( str_split( $code ) as $sym ) {
			if ( '-' === $sym ) {
				$morse .= '<rect x="' . $x . '" y="66" width="10" height="3" rx="1.5"/>';
				$x     += 13;
			} else {
				$morse .= '<circle cx="' . ( $x + 1.6 ) . '" cy="67.5" r="1.6"/>';
				$x     += 6;
			}
		}
		$x += 7;
	}

	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' 96" width="' . $w . '" height="96" role="img" aria-label="' . hamshack_svg_esc( $call ) . '"' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
	$svg .= '<defs><linearGradient id="' . $id . 'a" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd166"/><stop offset="1" stop-color="#ff9f1c"/></linearGradient>'
		. '<linearGradient id="' . $id . 'c" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#22d3ee"/><stop offset="1" stop-color="#38bdf8"/></linearGradient></defs>';
	// Badge with lattice mast and waves.
	$svg .= '<g transform="translate(6 6)"><circle cx="42" cy="42" r="40" fill="#0e1620" stroke="#233142" stroke-width="2"/>'
		. '<circle cx="42" cy="42" r="33" fill="none" stroke="#22d3ee" stroke-opacity=".25" stroke-dasharray="2 4"/>'
		. '<g stroke="url(#' . $id . 'a)" stroke-width="2.4" stroke-linecap="round" fill="none"><path d="M42 30 L31 72 M42 30 L53 72"/><path d="M35.5 55 L48.5 55 M37.8 46 L46.2 46 M33.4 64 L50.6 64"/><path d="M35.5 55 L46.2 46 M48.5 55 L37.8 46 M33.4 64 L48.5 55 M50.6 64 L35.5 55"/><path d="M30 30 H54" stroke-width="3"/></g>'
		. '<circle cx="42" cy="26" r="3.6" fill="#ffd166"/>'
		. '<g fill="none" stroke="url(#' . $id . 'c)" stroke-linecap="round"><path d="M52 17 a14 14 0 0 1 0 18" stroke-width="2.6"/><path d="M58 11 a22 22 0 0 1 0 30" stroke-width="2.2" stroke-opacity=".75"/><path d="M32 17 a14 14 0 0 0 0 18" stroke-width="2.6"/><path d="M26 11 a22 22 0 0 0 0 30" stroke-width="2.2" stroke-opacity=".75"/></g></g>';
	// Callsign.
	$svg .= '<text x="104" y="56" font-family="\'JetBrains Mono\',\'Fira Code\',Consolas,\'DejaVu Sans Mono\',Menlo,monospace" font-weight="800" font-size="46" letter-spacing="2" fill="#e8f1fa">'
		. hamshack_svg_esc( $a ) . ( '' !== $digit ? '<tspan fill="url(#' . $id . 'a)">' . hamshack_svg_esc( $digit ) . '</tspan>' : '' ) . hamshack_svg_esc( $b ) . '</text>';
	$svg .= '<g fill="#22d3ee" fill-opacity=".85">' . $morse . '</g>';
	$svg .= '<text x="106" y="86" font-family="Inter,\'Segoe UI\',Roboto,Arial,sans-serif" font-size="11" font-weight="600" letter-spacing="2.1" fill="#8aa0b4">' . hamshack_svg_esc( $sub ) . '</text>';
	$svg .= '</svg>';

	return $standalone ? '<?xml version="1.0" encoding="UTF-8"?>' . $svg : $svg;
}

/**
 * QSL card SVG (standalone document).
 */
function hamshack_qsl_svg() {
	$call = strtoupper( hamshack_opt( 'callsign', 'N0CALL' ) );
	list( $a, $digit, $b ) = hamshack_call_parts( $call );
	$qth   = strtoupper( str_replace( ',', ' ·', hamshack_opt( 'qth' ) ) );
	$line2 = hamshack_join(
		array(
			hamshack_opt( 'locator' ) ? 'LOC ' . strtoupper( substr( hamshack_opt( 'locator' ), 0, 4 ) ) . substr( hamshack_opt( 'locator' ), 4 ) : '',
			hamshack_opt( 'itu_zone' ) ? 'ITU ' . hamshack_opt( 'itu_zone' ) : '',
			hamshack_opt( 'cq_zone' ) ? 'CQ ' . hamshack_opt( 'cq_zone' ) : '',
		),
		'  ·  '
	);
	$size  = strlen( $call ) > 7 ? max( 70, 118 - ( strlen( $call ) - 7 ) * 12 ) : 118;
	$mono  = "font-family=\"'JetBrains Mono',Consolas,'DejaVu Sans Mono',Menlo,monospace\"";
	$sans  = "font-family=\"Inter,'Segoe UI',Roboto,Arial,sans-serif\"";

	$svg  = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 580" width="900" height="580" role="img" aria-label="' . hamshack_svg_esc( $call ) . ' QSL card">';
	$svg .= '<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0d1823"/><stop offset="1" stop-color="#081018"/></linearGradient>'
		. '<linearGradient id="am" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd166"/><stop offset="1" stop-color="#ff9f1c"/></linearGradient>'
		. '<clipPath id="c"><rect width="900" height="580" rx="22"/></clipPath></defs>';
	$svg .= '<g clip-path="url(#c)"><rect width="900" height="580" fill="url(#bg)"/>';
	$svg .= '<g stroke="#16293a"><path d="M0 80H900M0 160H900M0 240H900M0 320H900M0 400H900M0 480H900M100 0V580M250 0V580M400 0V580M550 0V580M700 0V580M850 0V580"/></g>';
	$svg .= '<path d="M0 250 C120 230 220 236 330 244 C450 252 520 226 640 232 C760 238 820 250 900 240 V330 H0Z" fill="#0c1a26"/>';
	// Mast with waves (generic).
	$svg .= '<g stroke="#1f3a52" stroke-width="4" fill="none" stroke-linecap="round"><path d="M760 70 L735 240 M760 70 L785 240 M742 190 H778 M747 150 H773 M752 110 H768 M742 190 L773 150 M778 190 L747 150 M747 150 L768 110 M773 150 L752 110"/></g>';
	$svg .= '<g fill="none" stroke="#22d3ee" stroke-linecap="round" opacity=".5"><path d="M782 46 a30 30 0 0 1 0 44" stroke-width="3"/><path d="M798 30 a52 52 0 0 1 0 76" stroke-width="2"/><path d="M738 46 a30 30 0 0 0 0 44" stroke-width="3"/><path d="M722 30 a52 52 0 0 0 0 76" stroke-width="2"/></g><circle cx="760" cy="66" r="6" fill="#ffd166"/>';
	$svg .= '<text x="50" y="150" ' . $mono . ' font-size="' . $size . '" font-weight="800" fill="#e8f1fa" letter-spacing="4">' . hamshack_svg_esc( $a ) . ( '' !== $digit ? '<tspan fill="url(#am)">' . hamshack_svg_esc( $digit ) . '</tspan>' : '' ) . hamshack_svg_esc( $b ) . '</text>';
	if ( $qth ) {
		$svg .= '<text x="54" y="196" ' . $sans . ' font-size="22" font-weight="600" fill="#b8c7d6" letter-spacing="2">' . hamshack_svg_esc( $qth ) . '</text>';
	}
	if ( $line2 ) {
		$svg .= '<text x="54" y="228" ' . $mono . ' font-size="18" fill="#22d3ee" letter-spacing="2">' . hamshack_svg_esc( $line2 ) . '</text>';
	}
	// QSO table (blank, ready to be filled in).
	$svg .= '<rect x="50" y="330" width="800" height="150" rx="12" fill="#07101a" stroke="#233142" stroke-width="2"/>';
	$svg .= '<g ' . $sans . ' font-size="15" font-weight="700" fill="#8aa0b4" letter-spacing="1.5" text-anchor="middle"><text x="140" y="366">TO RADIO</text><text x="290" y="366">DATE</text><text x="410" y="366">UTC</text><text x="520" y="366">MHz</text><text x="630" y="366">MODE</text><text x="760" y="366">RST</text></g>';
	$svg .= '<path d="M50 382 H850 M230 330 V480 M350 330 V480 M470 330 V480 M570 330 V480 M690 330 V480" stroke="#233142" stroke-width="2"/>';
	$svg .= '<text x="54" y="530" ' . $sans . ' font-size="18" fill="#b8c7d6">73 &amp; tnx for the QSO!</text>';
	$svg .= '<text x="846" y="530" text-anchor="end" ' . $mono . ' font-size="18" fill="#39ff88">PSE ☐  TNX ☐  QSL</text>';
	$svg .= '<rect x="0" y="560" width="900" height="20" fill="url(#am)" opacity=".85"/></g></svg>';
	return $svg;
}
