<?php
/**
 * Shared helpers.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default station settings.
 */
function hamshack_defaults() {
	return array(
		'callsign'      => 'N0CALL',
		'op_name'       => '',
		'qth'           => '',
		'locator'       => '',
		'lat'           => '',
		'lon'           => '',
		'licence'       => '',
		'itu_zone'      => '',
		'cq_zone'       => '',
		'rigs'          => "HF transceiver – HF / 6 m\nDual-band VHF/UHF handheld – 2 m / 70 cm",
		'antennas'      => "End-fed half-wave (EFHW) wire for HF\nDual-band 2 m / 70 cm vertical",
		'bands'         => array( '40m', '20m', '15m', '10m', '2m', '70cm' ),
		'modes'         => array( 'SSB', 'FM', 'FT8', 'CW' ),
		'status'        => 'off',
		'status_freq'   => '',
		'status_mode'   => '',
		'status_note'   => '',
		'status_time'   => 0,
		'hero_title'    => 'Welcome to the shack',
		'hero_text'     => 'The radio shack, blog and logbook of {callsign}. HF contacts, portable activations, antenna experiments and everything in between.',
		'qrz_url'       => '',
		'email'         => '',
		'youtube'       => '',
		'facebook'      => '',
		'x'             => '',
		'mastodon'      => '',
		'instagram'     => '',
		'show_solar'    => 1,
		'solar_banner'  => 'solarn0nbh',
		'solar_home'    => 'solarn0nbh',
		'show_logbook'  => 1,
		'show_live'     => 1,
		'qsl_info'      => 'QSL via eQSL, LoTW or direct. 73!',
		'footer_text'   => '73 de {callsign}',
	);
}

/**
 * Read a station option.
 *
 * @param string $key     Option key.
 * @param mixed  $default Fallback.
 */
function hamshack_opt( $key, $default = null ) {
	$opts = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
	if ( 'qrz_url' === $key && '' === $opts['qrz_url'] && $opts['callsign'] && 'N0CALL' !== $opts['callsign'] ) {
		return 'https://www.qrz.com/db/' . rawurlencode( $opts['callsign'] );
	}
	if ( array_key_exists( $key, $opts ) && '' !== $opts[ $key ] ) {
		$value = $opts[ $key ];
		if ( function_exists( 'pll__' ) && is_string( $value ) && in_array( $key, hamshack_translatable_opts(), true ) && ! is_admin() ) {
			$value = pll__( $value );
		}
		// Texts may contain {callsign}, {qth}, {locator} placeholders.
		if ( is_string( $value ) && false !== strpos( $value, '{' ) && ! in_array( $key, array( 'callsign', 'qth', 'locator' ), true ) ) {
			$value = strtr( $value, array( '{callsign}' => $opts['callsign'], '{qth}' => $opts['qth'], '{locator}' => $opts['locator'] ) );
		}
		return $value;
	}
	if ( null !== $default ) {
		return $default;
	}
	return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
}

/**
 * All bands offered in the UI.
 */
function hamshack_all_bands() {
	return array( '160m', '80m', '60m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m', '4m', '2m', '70cm', '23cm', 'QO-100' );
}

/**
 * All modes offered in the UI.
 */
function hamshack_all_modes() {
	return array( 'SSB', 'CW', 'FM', 'AM', 'FT8', 'FT4', 'RTTY', 'PSK31', 'JS8', 'SSTV', 'DMR', 'D-STAR', 'C4FM', 'Packet' );
}

/**
 * On-air status choices.
 */
function hamshack_status_choices() {
	return array(
		'on'       => __( 'On air', 'hamshack' ),
		'portable' => __( 'Portable / activating', 'hamshack' ),
		'listen'   => __( 'Listening (RX only)', 'hamshack' ),
		'off'      => __( 'Off air (QRT)', 'hamshack' ),
	);
}

/**
 * Human label for the current status.
 */
function hamshack_status_label( $status = null ) {
	$status  = $status ? $status : hamshack_opt( 'status', 'off' );
	$choices = hamshack_status_choices();
	return isset( $choices[ $status ] ) ? $choices[ $status ] : $choices['off'];
}

/**
 * Split a textarea into clean lines.
 */
function hamshack_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Theme image URL.
 */
function hamshack_img( $file ) {
	return esc_url( HAMSHACK_URI . '/assets/img/' . $file );
}

/**
 * Convert a band name into its approximate lower-edge frequency (MHz) and back.
 */
function hamshack_band_from_freq( $mhz ) {
	$mhz   = (float) $mhz;
	$table = array(
		'160m' => array( 1.8, 2.0 ),
		'80m'  => array( 3.5, 3.8 ),
		'60m'  => array( 5.25, 5.45 ),
		'40m'  => array( 7.0, 7.2 ),
		'30m'  => array( 10.1, 10.15 ),
		'20m'  => array( 14.0, 14.35 ),
		'17m'  => array( 18.068, 18.168 ),
		'15m'  => array( 21.0, 21.45 ),
		'12m'  => array( 24.89, 24.99 ),
		'10m'  => array( 28.0, 29.7 ),
		'6m'   => array( 50.0, 52.0 ),
		'4m'   => array( 70.0, 70.5 ),
		'2m'   => array( 144.0, 146.0 ),
		'70cm' => array( 430.0, 440.0 ),
		'23cm' => array( 1240.0, 1325.0 ),
	);
	foreach ( $table as $band => $range ) {
		if ( $mhz >= $range[0] && $mhz <= $range[1] ) {
			return $band;
		}
	}
	return '';
}

/**
 * Social / contact links configured in station settings.
 */
function hamshack_social_links() {
	$map   = array(
		'qrz_url'   => array( 'QRZ.com', 'qrz' ),
		'youtube'   => array( 'YouTube', 'youtube' ),
		'facebook'  => array( 'Facebook', 'facebook' ),
		'x'         => array( 'X', 'x' ),
		'mastodon'  => array( 'Mastodon', 'mastodon' ),
		'instagram' => array( 'Instagram', 'instagram' ),
	);
	$links = array();
	foreach ( $map as $key => $meta ) {
		$url = hamshack_opt( $key );
		if ( $url ) {
			$links[] = array(
				'url'   => $url,
				'label' => $meta[0],
				'icon'  => $meta[1],
			);
		}
	}
	return $links;
}

/**
 * Estimated reading time.
 */
function hamshack_reading_time( $post_id = null ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * N0NBH (hamqsl.com) solar-terrestrial banners that can be shown.
 * Key = image file on hamqsl.com (without .php).
 */
function hamshack_solar_banners() {
	return array(
		'solarn0nbh'  => __( 'N0NBH classic – HF & VHF conditions + live Sun image (tall)', 'hamshack' ),
		'solarvhf'    => __( 'HF & VHF conditions, MUF, EME (tall)', 'hamshack' ),
		'solar2'      => __( 'Band conditions, multi-colour (medium)', 'hamshack' ),
		'solar100sc'  => __( 'Band conditions, compact', 'hamshack' ),
		'solarbc'     => __( 'Band conditions only (small)', 'hamshack' ),
		'solarsmall'  => __( 'Small summary', 'hamshack' ),
		'solar101vhf' => __( 'Europe – HF & VHF conditions (wide)', 'hamshack' ),
		'solar101sc'  => __( 'Europe – band conditions (wide)', 'hamshack' ),
		'solarsun'    => __( 'Current image of the Sun (wide)', 'hamshack' ),
		'solargraph'  => __( '30-day solar flux & sunspot graphs (wide)', 'hamshack' ),
		'solarmuf'    => __( 'World sunlight map with flux & MUF (wide)', 'hamshack' ),
		'solarmap'    => __( 'World sunlight map (grey line)', 'hamshack' ),
	);
}

/**
 * Validate a banner key, falling back to the N0NBH classic.
 */
function hamshack_solar_key( $key ) {
	return array_key_exists( $key, hamshack_solar_banners() ) ? $key : 'solarn0nbh';
}

/**
 * Join the non-empty values with a separator (keeps layouts tidy when fields are blank).
 */
function hamshack_join( array $parts, $sep = ' · ' ) {
	return implode( $sep, array_filter( array_map( 'trim', $parts ), 'strlen' ) );
}

/**
 * <li> list of label → value pairs, skipping empty values.
 */
function hamshack_facts( array $pairs ) {
	$out = '';
	foreach ( $pairs as $label => $value ) {
		if ( '' !== trim( (string) $value ) ) {
			$out .= '<li><span>' . esc_html( $label ) . '</span>' . esc_html( $value ) . '</li>';
		}
	}
	return $out;
}

/**
 * Does the content hold a wide app (calendar, logbook, maps…)? Then the page uses the full container.
 *
 * @param string $content Post content.
 */
function hamshack_is_wide_content( $content ) {
	$wide = apply_filters( 'hamshack_wide_shortcodes', array( 'hamshack_calendar', 'hamshack_logbook', 'hamshack_qso_map', 'hamshack_awards', 'hamshack_live', 'hamshack_greyline', 'hamshack_dx_cluster', 'hamshack_psk', 'hamshack_ft8', 'hamshack_satellites', 'hamshack_activations', 'hamshack_gallery', 'hamshack_morse', 'hamshack_quiz', 'hamshack_propagation', 'hamshack_muf', 'hamshack_best_times', 'hamshack_space_weather', 'hamshack_weather', 'hamshack_contests', 'hamshack_contest', 'hamshack_qsl_wall', 'hamshack_sked', 'hamshack_repeaters', 'hamshack_sdr', 'hamshack_aprs' ) );
	foreach ( $wide as $sc ) {
		if ( has_shortcode( $content, $sc ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Where to send a visitor back to after a form post (the page the form was on).
 *
 * wp_get_referer() returns false when the form posts to its own URL, so use the raw referer.
 */
function hamshack_back_url() {
	$ref = wp_get_raw_referer();
	return $ref ? wp_validate_redirect( $ref, home_url( '/' ) ) : home_url( '/' );
}

/**
 * Station texts that can be translated with Polylang (Languages → Translations).
 */
function hamshack_translatable_opts() {
	return array( 'hero_title', 'hero_text', 'qsl_info', 'footer_text', 'licence', 'qth', 'rigs', 'antennas' );
}

add_action( 'init', function () {
	if ( function_exists( 'pll_register_string' ) ) {
		$opts = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
		foreach ( hamshack_translatable_opts() as $k ) {
			if ( ! empty( $opts[ $k ] ) && is_string( $opts[ $k ] ) ) {
				pll_register_string( 'hamshack_' . $k, $opts[ $k ], 'Ham Shack', false !== strpos( $opts[ $k ], "\n" ) );
			}
		}
	}
} );

/**
 * EN | PL switcher in the top bar (only when Polylang is active).
 */
function hamshack_language_switcher() {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return;
	}
	$langs = pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0, 'hide_if_no_translation' => 0 ) );
	if ( ! is_array( $langs ) || count( $langs ) < 2 ) {
		return;
	}
	echo '<span class="lang-switch" aria-label="' . esc_attr__( 'Language', 'hamshack' ) . '">';
	foreach ( $langs as $l ) {
		printf(
			'<a href="%1$s" lang="%2$s" hreflang="%2$s"%3$s>%4$s</a>',
			esc_url( $l['url'] ),
			esc_attr( $l['locale'] ? str_replace( '_', '-', $l['locale'] ) : $l['slug'] ),
			! empty( $l['current_lang'] ) ? ' class="is-current" aria-current="true"' : '',
			esc_html( strtoupper( $l['slug'] ) )
		);
	}
	echo '</span>';
}

/**
 * Signed time stamp for public forms: shows when the form was loaded, cannot be faked
 * and – unlike a nonce – keeps working when the page is cached.
 */
function hamshack_form_stamp() {
	$t = time();
	return $t . '.' . substr( hash_hmac( 'sha256', 'm6form|' . $t, wp_salt( 'nonce' ) ), 0, 20 );
}

/** @return int Time stamp, or 0 if the signature is wrong. */
function hamshack_form_stamp_check( $stamp ) {
	$p = explode( '.', (string) $stamp );
	if ( 2 !== count( $p ) || ! ctype_digit( $p[0] ) ) {
		return 0;
	}
	return hash_equals( substr( hash_hmac( 'sha256', 'm6form|' . $p[0], wp_salt( 'nonce' ) ), 0, 20 ), $p[1] ) ? (int) $p[0] : 0;
}
