<?php
/**
 * Inline SVG icon set (24×24, stroke = currentColor).
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return an inline SVG icon.
 *
 * @param string $name  Icon name.
 * @param int    $size  Pixel size.
 * @param string $class Extra class.
 */
function hamshack_icon( $name, $size = 20, $class = '' ) {
	$paths = array(
		'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/><path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 17h2M11 17h2"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
		'radio'    => '<rect x="2.5" y="8" width="19" height="12" rx="2"/><path d="M6 8 17 3.5"/><circle cx="16" cy="14" r="3"/><path d="M5.5 12h5M5.5 15h5M5.5 18h3"/>',
		'antenna'  => '<path d="M12 9 8 21M12 9l4 12M9.3 17h5.4M10.3 13.5h3.4"/><circle cx="12" cy="7" r="1.6"/><path d="M8 3.5a5.5 5.5 0 0 0 0 7M16 3.5a5.5 5.5 0 0 1 0 7M5.2 1.5a9 9 0 0 0 0 11M18.8 1.5a9 9 0 0 1 0 11"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'mic'      => '<rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M8.5 21h7"/>',
		'key'      => '<path d="M3 18h18M5 18v-3h14v3"/><path d="M6 12.5 18 10"/><ellipse cx="6" cy="11.5" rx="3" ry="1.4"/><path d="M15 10.5V15"/>',
		'wave'     => '<path d="M2 12c2-5 4-5 6 0s4 5 6 0 4-5 6 0"/>',
		'book'     => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v16H6.5A2.5 2.5 0 0 0 4 20.5v-16z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/><path d="M8 7h8M8 11h6"/>',
		'mail'     => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 6.5 9 6.5 9-6.5"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20.5 20.5-4.5-4.5"/>',
		'menu'     => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
		'close'    => '<path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/>',
		'arrow-r'  => '<path d="M4 12h15M13 6l6 6-6 6"/>',
		'arrow-l'  => '<path d="M20 12H5M11 6l-6 6 6 6"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 3 2.8 15 0 18M12 3c-2.8 3-2.8 15 0 18"/>',
		'qrz'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.8 3 2.8 15 0 18M12 3c-2.8 3-2.8 15 0 18"/>',
		'youtube'  => '<rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
		'facebook' => '<path d="M14 8.5h2.5V5H14a3.5 3.5 0 0 0-3.5 3.5V11H8v3.5h2.5V22H14v-7.5h2.5L17 11h-3V9a.5.5 0 0 1 .5-.5z"/>',
		'x'        => '<path d="M4 4l16 16M20 4 4 20"/>',
		'mastodon' => '<path d="M20 9.5c0-4-2.6-5.2-2.6-5.2C16 3.7 13.8 3.5 12 3.5s-4 .2-5.4.8C6.6 4.3 4 5.5 4 9.5c0 4.8-.3 10.3 5.8 11.5 1.9.4 3.8.3 5.2-.2l-.1-1.6s-1.6.4-3.2.4c-1.7-.1-3.4-.2-3.6-2.3 2.2.5 5 .6 7.2.3 2.6-.3 4.2-1.8 4.5-3.3.4-2 .2-4.8.2-4.8z"/><path d="M8.5 13V9.8c0-1.7 2.8-1.9 3.2 0v2M15.5 13V9.8c0-1.7-2.8-1.9-3.2 0"/>',
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".8" fill="currentColor"/>',
		'rss'      => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.5" fill="currentColor"/>',
		'moon'     => '<path d="M20.5 14.5A8.5 8.5 0 1 1 9.5 3.5a7 7 0 0 0 11 11z"/>',
		'sun'      => '<circle cx="12" cy="12" r="4.5"/><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/>',
		'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>',
		'upload'   => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
		'signal'   => '<path d="M4 20v-3M9 20v-7M14 20V9M19 20V4"/>',
		'folder'   => '<path d="M3 6.5A1.5 1.5 0 0 1 4.5 5H10l2 2.5h7.5A1.5 1.5 0 0 1 21 9v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z"/>',
		'comment'  => '<path d="M4 5h16v11H9l-5 4z"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'map'      => '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
		'flag'     => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
		'home'     => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
		'grid'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
		'trophy'   => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H4v1a4 4 0 0 0 4 4M16 6h4v1a4 4 0 0 1-4 4M12 13v4M8 21h8M9 17h6"/>',
		'card'     => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M6 10h6M6 14h9M16 9h2v3h-2z"/>',
		'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
		'mountain' => '<path d="m3 20 6-11 4 6 2-3 6 8z"/><path d="m8 11 1.5 1.5L11 11"/>',
		'bell'     => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		'live'     => '<circle cx="12" cy="12" r="2"/><path d="M8.5 8.5a5 5 0 0 0 0 7M15.5 8.5a5 5 0 0 1 0 7M5.6 5.6a9 9 0 0 0 0 12.8M18.4 5.6a9 9 0 0 1 0 12.8"/>',
		'chart'    => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
		'cap'      => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5M22 9v6"/>',
		'send'     => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
		'check'    => '<path d="m5 12 5 5 9-10"/>',
		'play'     => '<path d="M7 4v16l13-8z"/>',
		'stop'     => '<rect x="6" y="6" width="12" height="12" rx="1"/>',
		'shuffle'  => '<path d="M3 7h4l10 10h4M3 17h4l3-3M14 10l3-3h4M18 4l3 3-3 3M18 14l3 3-3 3"/>',
		'satellite'=> '<path d="m7 9 4-4 4 4-4 4z"/><path d="m4 6 3-3 3 3-3 3zM14 16l3-3 3 3-3 3z"/><path d="M13 15a3 3 0 0 1-3 3M16 18a6 6 0 0 1-6 6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="icon icon-%1$s %4$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		(int) $size,
		$paths[ $name ],
		esc_attr( $class )
	);
}

/**
 * Echo an icon.
 */
function hamshack_the_icon( $name, $size = 20, $class = '' ) {
	echo hamshack_icon( $name, $size, $class ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG markup.
}
