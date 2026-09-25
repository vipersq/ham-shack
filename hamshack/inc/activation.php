<?php
/**
 * One-click site setup: pages, menus, categories, event types, widgets, sample content.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_switch_theme', function () {
	hamshack_register_events();
	flush_rewrite_rules();
	if ( ! get_option( 'hamshack_installed' ) ) {
		set_transient( 'hamshack_show_setup_notice', 1, DAY_IN_SECONDS );
	}
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'hamshack_installed' ) || ! get_transient( 'hamshack_show_setup_notice' ) ) {
		return;
	}
	if ( isset( $_GET['page'] ) && 'hamshack-setup' === $_GET['page'] ) { // phpcs:ignore
		return;
	}
	echo '<div class="notice notice-info hamshack-notice"><p><strong>Ham Shack theme is active.</strong> ' . esc_html__( 'Build the site (pages, menu, calendar, logbook, widgets and sample content) in one click:', 'hamshack' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=hamshack-setup' ) ) . '">' . esc_html__( 'Run site setup', 'hamshack' ) . '</a></p></div>';
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'Setup & help', 'hamshack' ), __( 'Setup & help', 'hamshack' ), 'manage_options', 'hamshack-setup', 'hamshack_setup_page' );
}, 30 );

function hamshack_setup_page() {
	$done = isset( $_GET['done'] ); // phpcs:ignore
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'Setup & help', 'hamshack' ); ?></h1>
		<?php if ( $done ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Setup complete! Your site is ready.', 'hamshack' ); ?> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'View site →', 'hamshack' ); ?></a></p></div>
		<?php endif; ?>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'antenna' ); ?> <?php esc_html_e( 'One-click site setup', 'hamshack' ); ?></h2>
			<p><?php esc_html_e( 'Creates everything the site needs. It is safe to run again – existing pages, menus and categories are kept, only missing items are added.', 'hamshack' ); ?></p>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Pages: Home, Blog, Calendar, Logbook, Station, Contact', 'hamshack' ); ?></li>
				<li><?php esc_html_e( 'Main & footer menus, sidebar and footer widgets', 'hamshack' ); ?></li>
				<li><?php esc_html_e( 'Blog categories and event types (Contest, Net, Activation…)', 'hamshack' ); ?></li>
				<li><?php esc_html_e( 'Pretty permalinks, your time zone and date format', 'hamshack' ); ?></li>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hamshack_setup' ); ?>
				<input type="hidden" name="action" value="hamshack_setup">
				<?php $o = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() ); ?>
				<h3><?php esc_html_e( '1. Your station', 'hamshack' ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="hs-w-call"><?php esc_html_e( 'Callsign', 'hamshack' ); ?> *</label></th><td><input id="hs-w-call" name="w[callsign]" class="regular-text code" required style="text-transform:uppercase" value="<?php echo esc_attr( 'N0CALL' === $o['callsign'] ? '' : $o['callsign'] ); ?>" placeholder="e.g. G4ABC"></td></tr>
					<tr><th scope="row"><label for="hs-w-name"><?php esc_html_e( 'Your name', 'hamshack' ); ?></label></th><td><input id="hs-w-name" name="w[op_name]" class="regular-text" value="<?php echo esc_attr( $o['op_name'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hs-w-qth"><?php esc_html_e( 'QTH (town, county, country)', 'hamshack' ); ?></label></th><td><input id="hs-w-qth" name="w[qth]" class="regular-text" value="<?php echo esc_attr( $o['qth'] ); ?>" placeholder="e.g. Bath, Somerset, England"></td></tr>
					<tr><th scope="row"><label for="hs-w-loc"><?php esc_html_e( 'Maidenhead locator', 'hamshack' ); ?></label></th><td><input id="hs-w-loc" name="w[locator]" class="small-text code" style="width:8em" value="<?php echo esc_attr( $o['locator'] ); ?>" placeholder="IO81">
						<span class="description"><?php esc_html_e( 'ITU zone', 'hamshack' ); ?></span> <input name="w[itu_zone]" class="small-text" value="<?php echo esc_attr( $o['itu_zone'] ); ?>">
						<span class="description"><?php esc_html_e( 'CQ zone', 'hamshack' ); ?></span> <input name="w[cq_zone]" class="small-text" value="<?php echo esc_attr( $o['cq_zone'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="hs-w-lic"><?php esc_html_e( 'Licence', 'hamshack' ); ?></label></th><td><input id="hs-w-lic" name="w[licence]" class="regular-text" value="<?php echo esc_attr( $o['licence'] ); ?>" placeholder="e.g. Full licence"></td></tr>
					<tr><th scope="row"><label for="hs-w-tz"><?php esc_html_e( 'Time zone', 'hamshack' ); ?></label></th><td><select id="hs-w-tz" name="w_tz"><?php echo wp_timezone_choice( get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC', get_user_locale() ); // phpcs:ignore ?></select></td></tr>
				</table>
				<h3><?php esc_html_e( '2. Build the site', 'hamshack' ); ?></h3>
				<p><label><input type="checkbox" name="samples" value="1" <?php checked( ! get_option( 'hamshack_installed' ) ); ?>> <?php esc_html_e( 'Also add example blog posts and events (edit or delete them later)', 'hamshack' ); ?></label></p>
				<?php submit_button( __( 'Run site setup', 'hamshack' ), 'primary large', 'submit', false ); ?>
			</form>
		</div>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'book' ); ?> <?php esc_html_e( 'Shortcodes', 'hamshack' ); ?></h2>
			<table class="widefat striped">
				<tr><td><code>[hamshack_calendar]</code></td><td><?php esc_html_e( 'Month calendar with navigation, legend, agenda and iCal subscribe link.', 'hamshack' ); ?></td></tr>
				<tr><td><code>[hamshack_events limit="5"]</code></td><td><?php esc_html_e( 'List of upcoming events.', 'hamshack' ); ?></td></tr>
				<tr><td><code>[hamshack_logbook per_page="25"]</code></td><td><?php esc_html_e( 'Public logbook with "Are you in my log?" search and stats.', 'hamshack' ); ?></td></tr>
				<tr><td><code>[hamshack_logbook_stats]</code></td><td><?php esc_html_e( 'Just the QSO statistics and band bars.', 'hamshack' ); ?></td></tr>
				<tr><td><code>[hamshack_contact]</code></td><td><?php esc_html_e( 'Contact form (anti-spam built in).', 'hamshack' ); ?></td></tr>
			</table>
			<p><?php printf( esc_html__( 'Calendar feed for Google / Apple / Outlook: %s', 'hamshack' ), '<code>' . esc_html( home_url( '/?hamshack_ics=all' ) ) . '</code>' ); ?></p>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_setup' );
	if ( isset( $_POST['w'] ) && is_array( $_POST['w'] ) ) {
		$w    = wp_unslash( $_POST['w'] ); // phpcs:ignore -- sanitized below.
		$opts = wp_parse_args( (array) get_option( 'hamshack_station', array() ), hamshack_defaults() );
		foreach ( array( 'callsign', 'op_name', 'qth', 'locator', 'itu_zone', 'cq_zone', 'licence' ) as $k ) {
			if ( isset( $w[ $k ] ) ) {
				$opts[ $k ] = sanitize_text_field( $w[ $k ] );
			}
		}
		$opts['callsign'] = strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', $opts['callsign'] ) );
		if ( '' === $opts['callsign'] ) {
			$opts['callsign'] = 'N0CALL';
		}
		$opts['locator'] = strtoupper( substr( $opts['locator'], 0, 4 ) ) . strtolower( substr( $opts['locator'], 4 ) );
		remove_all_filters( 'sanitize_option_hamshack_station' );
		update_option( 'hamshack_station', $opts );
	}
	if ( ! empty( $_POST['w_tz'] ) ) {
		$tz = sanitize_text_field( wp_unslash( $_POST['w_tz'] ) );
		if ( preg_match( '/^UTC([+-][0-9.]+)?$/', $tz, $m ) ) {
			update_option( 'timezone_string', '' );
			update_option( 'gmt_offset', isset( $m[1] ) ? (float) $m[1] : 0 );
		} elseif ( in_array( $tz, timezone_identifiers_list(), true ) ) {
			update_option( 'timezone_string', $tz );
		}
	}
	hamshack_install( ! empty( $_POST['samples'] ) );
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-setup&done=1' ) );
	exit;
} );

/**
 * Create a page if a page with the slug does not exist. Returns its ID.
 */
function hamshack_ensure_page( $slug, $title, $content = '' ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		if ( 'publish' !== $page->post_status ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) );
		}
		return $page->ID;
	}
	return wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);
}

/**
 * Full install routine.
 */
function hamshack_install( $samples = true ) {
	hamshack_register_events();

	// --- Site settings (only when still at WordPress defaults) ---
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	$call = hamshack_opt( 'callsign', 'N0CALL' );
	$qth  = hamshack_opt( 'qth' );
	$town = $qth ? trim( strtok( $qth, ',' ) ) : '';
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'start_of_week', 1 );
	$name = get_option( 'blogname' );
	if ( ! $name || in_array( $name, array( 'My WordPress Website', 'My Blog', 'WordPress', 'My Site' ), true ) ) {
		update_option( 'blogname', $call );
	}
	$desc = get_option( 'blogdescription' );
	if ( ! $desc || 'Just another WordPress site' === $desc ) {
		update_option( 'blogdescription', $town ? sprintf( 'Amateur radio from %s', $town ) : 'Amateur radio station' );
	}
	if ( false === get_option( 'hamshack_station' ) ) {
		add_option( 'hamshack_station', hamshack_defaults() );
	}

	// --- Pages ---
	$home     = hamshack_ensure_page( 'home', 'Home' );
	$blog     = hamshack_ensure_page( 'blog', 'Blog' );
	$calendar = hamshack_ensure_page( 'calendar', 'Calendar', "<!-- wp:paragraph -->\n<p>Contests, nets, portable activations and club nights I plan to be on air for. All times are local time, with UTC in brackets. Subscribe with the <strong>iCal</strong> button to get them in your own calendar.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[hamshack_calendar]\n<!-- /wp:shortcode -->" );
	$logbook  = hamshack_ensure_page( 'logbook', 'Logbook', "<!-- wp:paragraph -->\n<p>Worked " . esc_html( $call ) . "? Search for your callsign below to confirm the QSO. Times are UTC.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[hamshack_logbook]\n<!-- /wp:shortcode -->" );
	$station  = hamshack_ensure_page( 'station', 'Station', "<!-- wp:paragraph -->\n<p>Hello and welcome to the shack! I'm a radio amateur" . ( $town ? ' based in ' . esc_html( $town ) : '' ) . ", operating under the callsign " . esc_html( $call ) . ". This page describes my station – edit it in <em>Pages → Station</em>. Equipment, bands and modes are managed in <em>Ham Shack → Station settings</em> and appear here automatically.</p>\n<!-- /wp:paragraph -->" );
	$contact  = hamshack_ensure_page( 'contact', 'Contact', "<!-- wp:paragraph -->\n<p>Want to arrange a sked, ask about a QSL or just say hello? Drop me a message below.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[hamshack_contact]\n<!-- /wp:shortcode -->" );

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );
	update_option( 'page_for_posts', $blog );

	// --- Categories ---
	$cats = array(
		'HF'                  => 'HF bands – 160 m to 10 m.',
		'VHF & UHF'           => '6 m, 2 m, 70 cm and above.',
		'Portable & Outdoor'  => 'POTA, SOTA, WWFF and operating away from home.',
		'Antennas'            => 'Wires, verticals, Yagis and experiments.',
		'Digital Modes'       => 'FT8, FT4, JS8, RTTY, SSTV and friends.',
		'Contests'            => 'Contest logs, scores and lessons learnt.',
		'Shack & Kit'         => 'Radios, accessories, repairs and reviews.',
		'Learning & Licence'  => 'Exams, theory and getting started in the hobby.',
	);
	$cat_ids = array();
	foreach ( $cats as $cname => $cdesc ) {
		$t = term_exists( $cname, 'category' );
		if ( ! $t ) {
			$t = wp_insert_term( $cname, 'category', array( 'description' => $cdesc ) );
		}
		if ( ! is_wp_error( $t ) ) {
			$cat_ids[ $cname ] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
		}
	}

	// --- Event types ---
	$types = array(
		'contest'    => 'Contest',
		'net'        => 'Net',
		'activation' => 'Activation',
		'club'       => 'Club meeting',
		'rally'      => 'Rally / hamfest',
		'satellite'  => 'Satellite',
		'personal'   => 'On air / personal',
	);
	$type_ids = array();
	foreach ( $types as $slug => $tname ) {
		$t = term_exists( $slug, 'hamshack_event_type' );
		if ( ! $t ) {
			$t = wp_insert_term( $tname, 'hamshack_event_type', array( 'slug' => $slug ) );
		}
		if ( ! is_wp_error( $t ) ) {
			$type_ids[ $slug ] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
		}
	}

	// --- Menus ---
	$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations['primary'] ) || ! wp_get_nav_menu_object( $locations['primary'] ) ) {
		$menu = wp_get_nav_menu_object( 'Main menu' );
		$mid  = $menu ? $menu->term_id : wp_create_nav_menu( 'Main menu' );
		if ( ! is_wp_error( $mid ) && ! wp_get_nav_menu_items( $mid ) ) {
			$pos = 1;
			foreach ( array( $home, $blog, $calendar, $logbook, $station, $contact ) as $pid ) {
				wp_update_nav_menu_item( $mid, 0, array( 'menu-item-object-id' => $pid, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $pos++ ) );
			}
		}
		if ( ! is_wp_error( $mid ) ) {
			$locations['primary'] = $mid;
		}
	}
	if ( empty( $locations['footer'] ) || ! wp_get_nav_menu_object( $locations['footer'] ) ) {
		$menu = wp_get_nav_menu_object( 'Footer menu' );
		$fid  = $menu ? $menu->term_id : wp_create_nav_menu( 'Footer menu' );
		if ( ! is_wp_error( $fid ) && ! wp_get_nav_menu_items( $fid ) ) {
			$pos = 1;
			foreach ( array( $station, $logbook, $contact, (int) get_option( 'wp_page_for_privacy_policy' ) ) as $pid ) {
				if ( $pid && 'publish' === get_post_status( $pid ) ) {
					wp_update_nav_menu_item( $fid, 0, array( 'menu-item-object-id' => $pid, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $pos++ ) );
				}
			}
			wp_update_nav_menu_item( $fid, 0, array( 'menu-item-title' => 'RSS', 'menu-item-url' => get_feed_link(), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-position' => $pos ) );
		}
		if ( ! is_wp_error( $fid ) ) {
			$locations['footer'] = $fid;
		}
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	// --- Widgets (only fill empty areas) ---
	$sidebars = get_option( 'sidebars_widgets', array() );
	$add      = function ( $base, $settings ) {
		$opt = get_option( 'widget_' . $base, array() );
		$opt = is_array( $opt ) ? $opt : array();
		$n   = 2;
		while ( isset( $opt[ $n ] ) ) {
			$n++;
		}
		$opt[ $n ]            = $settings;
		$opt['_multiwidget'] = 1;
		update_option( 'widget_' . $base, $opt );
		return $base . '-' . $n;
	};
	$layout = array(
		'sidebar-1' => array(
			array( 'hamshack_status', array( 'title' => 'Station status' ) ),
			array( 'hamshack_events', array( 'title' => 'Upcoming on air', 'count' => 4 ) ),
			array( 'hamshack_solar', array( 'title' => 'Band conditions' ) ),
			array( 'categories', array( 'title' => 'Topics', 'count' => 1 ) ),
			array( 'archives', array( 'title' => 'Archive', 'dropdown' => 1 ) ),
		),
		'footer-1'  => array( array( 'hamshack_events', array( 'title' => 'Coming up', 'count' => 3 ) ) ),
		'footer-2'  => array( array( 'recent-posts', array( 'title' => 'From the blog', 'number' => 4 ) ) ),
		'footer-3'  => array( array( 'hamshack_qsos', array( 'title' => 'Latest QSOs', 'count' => 5 ) ) ),
	);
	$first_run = ! get_option( 'hamshack_installed' );
	foreach ( $layout as $area => $widgets ) {
		if ( ! empty( $sidebars[ $area ] ) ) {
			// On the first run replace WordPress' stock widgets (kept in "Inactive widgets", nothing is lost).
			if ( ! $first_run ) {
				continue;
			}
			$sidebars['wp_inactive_widgets'] = array_merge( isset( $sidebars['wp_inactive_widgets'] ) ? (array) $sidebars['wp_inactive_widgets'] : array(), (array) $sidebars[ $area ] );
		}
		$sidebars[ $area ] = array();
		foreach ( $widgets as $w ) {
			$sidebars[ $area ][] = $add( $w[0], $w[1] );
		}
	}
	if ( ! isset( $sidebars['wp_inactive_widgets'] ) ) {
		$sidebars['wp_inactive_widgets'] = array();
	}
	update_option( 'sidebars_widgets', $sidebars );

	// --- Sample content ---
	if ( $samples && ! get_option( 'hamshack_samples_added' ) ) {
		hamshack_add_samples( $cat_ids, $type_ids, $call, $town );
		update_option( 'hamshack_samples_added', 1 );
	}

	// Remove the stock "Hello world!" post and "Sample Page" if untouched.
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( $hello && $hello->post_date === $hello->post_modified ) {
		wp_trash_post( $hello->ID );
	}
	$sample = get_page_by_path( 'sample-page' );
	if ( $sample && $sample->post_date === $sample->post_modified ) {
		wp_trash_post( $sample->ID );
	}

	delete_transient( 'hamshack_events_all' );
	update_option( 'hamshack_installed', HAMSHACK_VERSION );
	flush_rewrite_rules();
}

/**
 * Example posts & events.
 */
function hamshack_add_samples( $cat, $type, $call = 'N0CALL', $town = '' ) {
	$posts = array(
		array(
			'title' => 'CQ CQ de ' . $call . ' – welcome to the shack',
			'cats'  => array( 'Learning & Licence' ),
			'body'  => "<!-- wp:paragraph -->\n<p>Welcome to my little corner of the web! I'm " . esc_html( $call ) . ", a radio amateur" . ( $town ? ' based in ' . esc_html( $town ) : '' ) . ". This blog is where I'll share my contacts, antenna experiments, portable outings and everything I learn along the way.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">What you'll find here</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><li><strong>Blog</strong> – stories from the shack and the field.</li><li><strong>Calendar</strong> – contests, nets and activations I'm planning to be on air for.</li><li><strong>Logbook</strong> – search for your callsign to see if we've had a QSO.</li><li><strong>Station</strong> – the radios and antennas behind the signal.</li></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>If you hear me on the bands, give me a call. 73!</p>\n<!-- /wp:paragraph -->",
		),
		array(
			'title' => 'An end-fed half-wave for a small garden',
			'cats'  => array( 'Antennas', 'HF' ),
			'body'  => "<!-- wp:paragraph -->\n<p><em>Example post – edit or delete me.</em> Like many amateurs I don't have room for a full-size dipole, so the end-fed half-wave (EFHW) is an attractive option: one wire, a 49:1 transformer at the feed point and multi-band operation on 40/20/15/10 m without a tuner.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Notes to self</h2>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><li>Start the wire slightly long and trim for the lowest SWR on the highest band.</li><li>A short counterpoise and a choke on the coax help keep RF out of the shack.</li><li>Sloping or inverted-L layouts work well when space is tight.</li></ul>\n<!-- /wp:list -->",
		),
		array(
			'title' => 'First evening on FT8',
			'cats'  => array( 'Digital Modes', 'HF' ),
			'body'  => "<!-- wp:paragraph -->\n<p><em>Example post – edit or delete me.</em> FT8 is a great way to see what the bands are doing: even with modest power and a simple wire antenna, the waterfall fills up with signals from across Europe and beyond.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Tip: keep your PC clock synced (within a second) and keep the audio level low enough that the ALC doesn't move – a clean signal gets more replies.</p>\n<!-- /wp:paragraph -->",
		),
	);
	$i = 0;
	foreach ( $posts as $p ) {
		wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $p['title'],
				'post_content'  => $p['body'],
				'post_date'     => wp_date( 'Y-m-d H:i:s', time() - ( 3 - $i ) * DAY_IN_SECONDS ),
				'post_category' => array_values( array_filter( array_map( function ( $c ) use ( $cat ) {
					return isset( $cat[ $c ] ) ? $cat[ $c ] : 0;
				}, $p['cats'] ) ) ),
			)
		);
		$i++;
	}

	$tz     = wp_timezone();
	$now    = new DateTimeImmutable( 'now', $tz );
	$events = array(
		array(
			'title'  => 'Weekly local 2 m net',
			'start'  => $now->modify( 'next monday' )->setTime( 20, 0 ),
			'len'    => '+1 hour',
			'type'   => 'net',
			'band'   => '2m',
			'mode'   => 'FM',
			'freq'   => '',
			'repeat' => 'weekly',
			'loc'    => 'Local area – check in from home or mobile',
			'body'   => 'Example event – edit the frequency and details. A friendly weekly natter for local stations; all licence classes welcome.',
		),
		array(
			'title'  => 'FT8 evening on 20 m',
			'start'  => $now->modify( '+5 days' )->setTime( 19, 30 ),
			'len'    => '+2 hours',
			'type'   => 'personal',
			'band'   => '20m',
			'mode'   => 'FT8',
			'freq'   => '14.074',
			'repeat' => 'none',
			'loc'    => 'Home QTH',
			'body'   => 'Example event. Look for me on the waterfall!',
		),
		array(
			'title'  => 'Portable activation (location TBC)',
			'start'  => $now->modify( '+12 days' )->setTime( 10, 0 ),
			'len'    => '+4 hours',
			'type'   => 'activation',
			'band'   => 'Multi',
			'mode'   => 'SSB',
			'freq'   => '',
			'repeat' => 'none',
			'loc'    => 'Park reference to be announced',
			'body'   => 'Example event. Operating portable with a battery and a wire antenna – spots appreciated.',
		),
		array(
			'title'  => 'Club night',
			'start'  => $now->modify( 'first thursday of next month' )->setTime( 19, 30 ),
			'len'    => '+2 hours',
			'type'   => 'club',
			'band'   => '',
			'mode'   => '',
			'freq'   => '',
			'repeat' => 'monthly',
			'loc'    => 'Local radio club – add venue',
			'body'   => 'Example event. Replace with your club\'s details.',
		),
	);
	foreach ( $events as $e ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'hamshack_event',
				'post_status'  => 'publish',
				'post_title'   => $e['title'],
				'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $e['body'] ) . '</p><!-- /wp:paragraph -->',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_hs_start', $e['start']->format( 'Y-m-d H:i' ) );
		update_post_meta( $id, '_hs_end', $e['start']->modify( $e['len'] )->format( 'Y-m-d H:i' ) );
		update_post_meta( $id, '_hs_allday', 0 );
		update_post_meta( $id, '_hs_repeat', $e['repeat'] );
		update_post_meta( $id, '_hs_band', $e['band'] );
		update_post_meta( $id, '_hs_mode', $e['mode'] );
		update_post_meta( $id, '_hs_freq', $e['freq'] );
		update_post_meta( $id, '_hs_location', $e['loc'] );
		if ( isset( $type[ $e['type'] ] ) ) {
			wp_set_object_terms( $id, array( $type[ $e['type'] ] ), 'hamshack_event_type' );
		}
	}
	delete_transient( 'hamshack_events_all' );
}
