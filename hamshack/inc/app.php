<?php
/**
 * App and speed: installable PWA (manifest + service worker + offline page),
 * light/dark switch, WebP uploads, deferred scripts, no emoji script.
 * Every part can be switched off in Ham Shack → Integrations.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * PWA
 * ======================================================================== */

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'hamshack_pwa';
	return $vars;
} );

/** Path the service worker controls (the site root, also in a sub-folder install). */
function hamshack_pwa_scope() {
	$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return $path ? trailingslashit( $path ) : '/';
}

function hamshack_pwa_url( $what ) {
	return add_query_arg( 'hamshack_pwa', $what, home_url( '/' ) );
}

/** App icons: the Site Icon if one is set, otherwise the bundled ones. */
function hamshack_pwa_icons() {
	if ( has_site_icon() ) {
		return array(
			array( 'src' => get_site_icon_url( 192 ), 'sizes' => '192x192', 'type' => 'image/png' ),
			array( 'src' => get_site_icon_url( 512 ), 'sizes' => '512x512', 'type' => 'image/png' ),
		);
	}
	return array(
		array( 'src' => hamshack_img( 'icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => hamshack_img( 'icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => hamshack_img( 'icon-maskable-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
	);
}

add_action( 'wp_head', function () {
	if ( ! hamshack_int( 'pwa' ) ) {
		return;
	}
	echo '<link rel="manifest" href="' . esc_url( hamshack_pwa_url( 'manifest' ) ) . '">' . "\n";
	if ( ! has_site_icon() ) {
		echo '<link rel="apple-touch-icon" href="' . esc_url( hamshack_img( 'apple-touch-icon.png' ) ) . '">' . "\n";
	}
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
}, 3 );

add_action( 'wp_footer', function () {
	if ( ! hamshack_int( 'pwa' ) || is_customize_preview() ) {
		return;
	}
	$sw = wp_json_encode( hamshack_pwa_url( 'sw' ) );
	$sc = wp_json_encode( hamshack_pwa_scope() );
	echo "<script>if('serviceWorker' in navigator&&(location.protocol==='https:'||location.hostname==='localhost')){window.addEventListener('load',function(){navigator.serviceWorker.register({$sw},{scope:{$sc}}).catch(function(){});});}</script>\n"; // phpcs:ignore
}, 50 );

add_action( 'template_redirect', function () {
	$what = get_query_var( 'hamshack_pwa' );
	if ( ! $what ) {
		return;
	}
	if ( ! hamshack_int( 'pwa' ) ) {
		status_header( 404 );
		exit;
	}
	nocache_headers();
	header( 'X-Content-Type-Options: nosniff' );

	if ( 'manifest' === $what ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		$name = get_bloginfo( 'name' );
		echo wp_json_encode(
			array(
				'id'               => hamshack_pwa_scope(),
				'name'             => $name . ( get_bloginfo( 'description' ) ? ' – ' . get_bloginfo( 'description' ) : '' ),
				'short_name'       => mb_substr( $name, 0, 12 ),
				'description'      => get_bloginfo( 'description' ),
				'lang'             => str_replace( '_', '-', get_locale() ),
				'start_url'        => home_url( '/' ),
				'scope'            => hamshack_pwa_scope(),
				'display'          => 'standalone',
				'background_color' => '#0b0f14',
				'theme_color'      => '#0b0f14',
				'icons'            => hamshack_pwa_icons(),
				'shortcuts'        => array_values( array_filter( array(
					hamshack_pwa_shortcut( 'live', __( 'Live', 'hamshack' ) ),
					hamshack_pwa_shortcut( 'logbook', __( 'Logbook', 'hamshack' ) ),
					hamshack_pwa_shortcut( 'calendar', __( 'Calendar', 'hamshack' ) ),
				) ) ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	if ( 'sw' === $what ) {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . hamshack_pwa_scope() );
		$cfg = array(
			'v'       => HAMSHACK_VERSION . '-' . substr( md5( (string) get_option( 'hamshack_pwa_rev', '1' ) ), 0, 6 ),
			'offline' => hamshack_pwa_url( 'offline' ),
			'home'    => home_url( '/' ),
			'pre'     => array( hamshack_pwa_url( 'offline' ), get_stylesheet_uri() . '?ver=' . HAMSHACK_VERSION, HAMSHACK_URI . '/assets/css/apps.css?ver=' . HAMSHACK_VERSION, HAMSHACK_URI . '/assets/js/main.js?ver=' . HAMSHACK_VERSION ),
			'skip'    => array( '/wp-admin', '/wp-login', '/wp-json', 'wp-cron', 'hamshack_pwa=sw', 'preview=', 'customize', '/feed', 'hamshack_ics', '.ics', '.adi' ),
		);
		echo "/* Ham Shack service worker */\nvar C=" . wp_json_encode( $cfg, JSON_UNESCAPED_SLASHES ) . ";\n"; // phpcs:ignore
		echo <<<'JS'
var PAGES = 'hs-pages-' + C.v, STATIC = 'hs-static-' + C.v, MAXPAGES = 40;
self.addEventListener('install', function (e) {
	e.waitUntil(caches.open(STATIC).then(function (c) { return c.addAll(C.pre); }).catch(function () {}).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (keys) {
		return Promise.all(keys.filter(function (k) { return k.indexOf('hs-') === 0 && k !== PAGES && k !== STATIC; }).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});
function skip(url) { return C.skip.some(function (s) { return url.indexOf(s) !== -1; }); }
function trim(name, max) { caches.open(name).then(function (c) { c.keys().then(function (k) { if (k.length > max) { c.delete(k[0]).then(function () { trim(name, max); }); } }); }); }
self.addEventListener('fetch', function (e) {
	var r = e.request, u = new URL(r.url);
	if (r.method !== 'GET' || u.origin !== location.origin || skip(r.url)) { return; }
	if (r.mode === 'navigate') {
		// Pages: always try the network (fresh logbook, cluster…); fall back to the last copy, then the offline page.
		e.respondWith(fetch(r).then(function (res) {
			if (res.ok && !res.headers.get('x-hs-nocache') && res.type === 'basic') {
				var copy = res.clone(); caches.open(PAGES).then(function (c) { c.put(r, copy); trim(PAGES, MAXPAGES); });
			}
			return res;
		}).catch(function () {
			return caches.match(r).then(function (hit) { return hit || caches.match(C.offline); });
		}));
		return;
	}
	if (/\.(css|js|woff2?|svg|png|jpe?g|webp|gif|ico)$/i.test(u.pathname)) {
		// Theme files and images: cache first (they are versioned).
		e.respondWith(caches.match(r).then(function (hit) {
			return hit || fetch(r).then(function (res) {
				if (res.ok && res.type === 'basic') { var copy = res.clone(); caches.open(STATIC).then(function (c) { c.put(r, copy); trim(STATIC, 150); }); }
				return res;
			});
		}));
	}
});
JS;
		exit;
	}

	if ( 'offline' === $what ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		$name = get_bloginfo( 'name' );
		?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title><?php echo esc_html( $name ); ?> – <?php esc_html_e( 'Offline', 'hamshack' ); ?></title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b0f14;color:#d6e2ee;font:16px/1.6 system-ui,sans-serif;text-align:center;padding:24px;box-sizing:border-box}main{max-width:420px}img{width:96px;height:96px}h1{color:#f1f6fb;font-size:1.6rem;margin:.6em 0 .2em}code{font-family:monospace;color:#ffb020}a,button{display:inline-block;margin-top:18px;background:#ffb020;color:#1a1204;border:0;border-radius:10px;padding:.7em 1.3em;font:700 1rem system-ui;text-decoration:none;cursor:pointer}</style></head>
<body><main><img src="<?php echo esc_url( hamshack_img( 'icon.svg' ) ); ?>" alt=""><h1><?php esc_html_e( 'No signal…', 'hamshack' ); ?></h1><p><?php esc_html_e( 'You are offline and this page has not been saved yet. Pages you visited before still open – try again when you are back on the air.', 'hamshack' ); ?></p><p><code>QRT · QRV soon · 73</code></p><button onclick="location.reload()"><?php esc_html_e( 'Try again', 'hamshack' ); ?></button></main></body></html>
		<?php
		exit;
	}
	status_header( 404 );
	exit;
}, 0 );

function hamshack_pwa_shortcut( $slug, $label ) {
	$p = get_page_by_path( $slug );
	if ( ! $p || 'publish' !== $p->post_status ) {
		return null;
	}
	return array( 'name' => $label, 'url' => get_permalink( $p ) );
}

/* Logged-in pages (admin bar, drafts…) are never stored in the offline cache. */
add_action( 'send_headers', function () {
	if ( is_user_logged_in() ) {
		header( 'X-HS-Nocache: 1' );
	}
} );

/* ==========================================================================
 * Light / dark switch
 * ======================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( hamshack_int( 'theme_toggle' ) ) {
		wp_enqueue_style( 'hamshack-light', HAMSHACK_URI . '/assets/css/light.css', array( 'hamshack-apps' ), HAMSHACK_VERSION );
	}
}, 20 );

/* Runs before the page is painted, so there is no dark→light flash. */
add_action( 'wp_head', function () {
	if ( ! hamshack_int( 'theme_toggle' ) ) {
		return;
	}
	echo "<script>(function(){try{var t=localStorage.getItem('hs-theme');if(t==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}}());</script>\n";
}, 0 );

add_action( 'hamshack_topbar_end', function () {
	if ( ! hamshack_int( 'theme_toggle' ) ) {
		return;
	}
	printf(
		'<button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" title="%1$s"><span class="screen-reader-text">%1$s</span>%2$s%3$s</button>',
		esc_attr__( 'Light / dark', 'hamshack' ),
		hamshack_icon( 'moon', 15, 'i-moon' ), // phpcs:ignore
		hamshack_icon( 'sun', 15, 'i-sun' ) // phpcs:ignore
	);
} );

/* ==========================================================================
 * Speed
 * ======================================================================== */

/* New JPEG/PNG uploads are served as WebP (WordPress keeps the original file too). */
add_filter( 'image_editor_output_format', function ( $formats ) {
	if ( hamshack_int( 'webp' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
	}
	return $formats;
} );

/* Theme scripts load deferred (they all wait for DOMContentLoaded anyway). */
function hamshack_defer_scripts() {
	if ( ! hamshack_int( 'defer' ) || is_admin() ) {
		return;
	}
	$wp = wp_scripts();
	foreach ( array_merge( $wp->queue, array_keys( $wp->registered ) ) as $h ) {
		if ( 0 === strpos( $h, 'hamshack' ) || 'satellite-js' === $h ) {
			$wp->add_data( $h, 'strategy', 'defer' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hamshack_defer_scripts', 99 );
add_action( 'wp_footer', 'hamshack_defer_scripts', 1 ); // Scripts added by shortcodes.

add_action( 'init', function () {
	if ( ! hamshack_int( 'no_emoji' ) ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	add_filter( 'wp_resource_hints', function ( $urls, $type ) {
		return 'dns-prefetch' === $type ? array_filter( $urls, function ( $u ) {
			return false === strpos( is_array( $u ) ? (string) ( $u['href'] ?? '' ) : (string) $u, 's.w.org' );
		} ) : $urls;
	}, 10, 2 );
} );
