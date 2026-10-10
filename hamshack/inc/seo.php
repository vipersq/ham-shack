<?php
/**
 * Lightweight SEO: meta description, Open Graph / Twitter cards and JSON-LD.
 * Switches itself off when Yoast, Rank Math, AIOSEO or SEOPress is active.
 *
 * Also: a backup / export page for everything the theme stores.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hamshack_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\Load' );
}

/**
 * Description for the current view.
 */
function hamshack_meta_description() {
	$d = '';
	if ( is_front_page() ) {
		$d = hamshack_opt( 'hero_text' );
	} elseif ( is_singular() ) {
		$p = get_queried_object();
		if ( post_password_required( $p ) ) {
			return get_bloginfo( 'description' );
		}
		$d = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_strip_all_tags( strip_shortcodes( $p->post_content ) );
		if ( 'hamshack_event' === $p->post_type && ! trim( $d ) ) {
			$d = sprintf( __( 'Amateur radio event on the %s calendar.', 'hamshack' ), hamshack_opt( 'callsign' ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$d = term_description();
	} elseif ( is_home() ) {
		$d = get_bloginfo( 'description' );
	}
	$d = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $d ) ) );
	if ( ! $d ) {
		$d = get_bloginfo( 'description' );
	}
	return mb_strlen( $d ) > 160 ? rtrim( mb_substr( $d, 0, 157 ) ) . '…' : $d;
}

add_action( 'wp_head', function () {
	if ( hamshack_seo_plugin_active() ) {
		return;
	}
	$desc  = hamshack_meta_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : ( is_front_page() ? home_url( '/' ) : '' );
	$img   = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( null, 'large' );
	}
	if ( ! $img ) {
		$img = has_site_icon() ? get_site_icon_url( 512 ) : HAMSHACK_URI . '/assets/img/og-default.png';
	}
	$type = is_singular( 'post' ) ? 'article' : 'website';
	echo "\n<!-- Ham Shack SEO -->\n";
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	if ( $url ) {
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	}
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
	}
	// JSON-LD.
	$same = array_values( array_filter( array( hamshack_opt( 'qrz_url' ), hamshack_opt( 'youtube' ), hamshack_opt( 'facebook' ), hamshack_opt( 'x' ), hamshack_opt( 'mastodon' ), hamshack_opt( 'instagram' ) ) ) );
	$person = array(
		'@type'    => 'Person',
		'@id'      => home_url( '/#operator' ),
		'name'     => trim( hamshack_opt( 'op_name' ) ) ? hamshack_opt( 'op_name' ) . ' (' . hamshack_opt( 'callsign' ) . ')' : hamshack_opt( 'callsign' ),
		'url'      => home_url( '/' ),
		'sameAs'   => $same,
		'knowsAbout' => array( 'Amateur radio', 'Ham radio' ),
	);
	$graph  = array(
		array( '@type' => 'WebSite', '@id' => home_url( '/#website' ), 'url' => home_url( '/' ), 'name' => get_bloginfo( 'name' ), 'description' => get_bloginfo( 'description' ), 'publisher' => array( '@id' => home_url( '/#operator' ) ), 'inLanguage' => get_bloginfo( 'language' ) ),
		$person,
	);
	if ( is_singular( 'post' ) ) {
		$graph[] = array(
			'@type'         => 'BlogPosting',
			'headline'      => get_the_title(),
			'description'   => $desc,
			'image'         => $img,
			'datePublished' => get_the_date( 'c' ),
			'dateModified'  => get_the_modified_date( 'c' ),
			'author'        => array( '@id' => home_url( '/#operator' ) ),
			'mainEntityOfPage' => get_permalink(),
		);
	}
	if ( is_singular( 'hamshack_event' ) && function_exists( 'hamshack_event_next_occurrence' ) ) {
		$e = hamshack_event_next_occurrence( get_the_ID() );
		if ( ! empty( $e['ts'] ) ) {
			$graph[] = array(
				'@type'               => 'Event',
				'name'                => get_the_title(),
				'description'         => $desc,
				'startDate'           => gmdate( 'c', (int) $e['ts'] ),
				'endDate'             => ! empty( $e['end_ts'] ) ? gmdate( 'c', (int) $e['end_ts'] ) : null,
				'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
				'location'            => array( '@type' => 'VirtualLocation', 'url' => get_permalink() ),
				'organizer'           => array( '@id' => home_url( '/#operator' ) ),
			);
		}
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 3 );

/* Keep search-result style pages out of search engines. */
add_filter( 'wp_robots', function ( $robots ) {
	if ( isset( $_GET['qso_call'] ) || isset( $_GET['qso_page'] ) || isset( $_GET['m6sub'] ) || isset( $_GET['oqrs'] ) ) { // phpcs:ignore
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
} );

/* No private post types in the sitemap. */
add_filter( 'wp_sitemaps_post_types', function ( $types ) {
	unset( $types['hamshack_qso'], $types['hamshack_qslreq'], $types['hamshack_sub'] );
	return $types;
} );

/* ==========================================================================
 * Backup / export
 * ======================================================================== */

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'Backup & export', 'hamshack' ), __( 'Backup & export', 'hamshack' ), 'manage_options', 'hamshack-backup', 'hamshack_backup_page' );
}, 40 );

/**
 * ADIF text of the whole log.
 */
function hamshack_adif_all() {
	$ids = get_posts( array( 'post_type' => 'hamshack_qso', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_q_dt', 'orderby' => 'meta_value', 'order' => 'ASC' ) ); // phpcs:ignore
	$out = hamshack_adif_header();
	foreach ( $ids as $id ) {
		$out .= hamshack_adif_record( $id );
	}
	return $out;
}

function hamshack_adif_f( $name, $value ) {
	$value = (string) $value;
	return '' === $value ? '' : '<' . $name . ':' . strlen( $value ) . '>' . $value . ' ';
}

function hamshack_adif_header() {
	return 'ADIF export from ' . home_url() . "\n" . hamshack_adif_f( 'ADIF_VER', '3.1.4' ) . hamshack_adif_f( 'PROGRAMID', 'HamShack-WP' ) . hamshack_adif_f( 'CREATED_TIMESTAMP', gmdate( 'Ymd His' ) ) . "<EOH>\n";
}

/**
 * One QSO as an ADIF record.
 */
function hamshack_adif_record( $id ) {
	$f = 'hamshack_adif_f';
	$g = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_q_' . $k, true );
	};
	return $f( 'CALL', $g( 'call' ) ) . $f( 'QSO_DATE', str_replace( '-', '', $g( 'date' ) ) ) . $f( 'TIME_ON', str_replace( ':', '', $g( 'time' ) ) )
		. $f( 'BAND', $g( 'band' ) ) . $f( 'FREQ', $g( 'freq' ) ) . $f( 'MODE', $g( 'mode' ) ) . $f( 'RST_SENT', $g( 'rst_s' ) ) . $f( 'RST_RCVD', $g( 'rst_r' ) )
		. $f( 'NAME', $g( 'name' ) ) . $f( 'QTH', $g( 'qth' ) ) . $f( 'GRIDSQUARE', $g( 'grid' ) ) . $f( 'COUNTRY', $g( 'country' ) ) . $f( 'DXCC', $g( 'dxcc' ) )
		. $f( 'CONT', $g( 'cont' ) ) . $f( 'CQZ', $g( 'cqz' ) ) . $f( 'ITUZ', $g( 'ituz' ) ) . $f( 'STATE', $g( 'state' ) )
		. hamshack_adif_my_ref( $f, $g( 'my_ref' ), strtoupper( (string) $g( 'my_ref_type' ) ) )
		. $f( 'QSL_SENT', $g( 'qsl_s' ) ) . $f( 'QSL_RCVD', $g( 'qsl_r' ) ) . $f( 'COMMENT', $g( 'comment' ) )
		. $f( 'STATION_CALLSIGN', hamshack_opt( 'callsign' ) ) . $f( 'MY_GRIDSQUARE', hamshack_opt( 'locator' ) ) . "<EOR>\n";
}

/**
 * Posts of a type as plain arrays (with their meta).
 */
function hamshack_export_posts( $type ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $p ) {
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $v ) {
			if ( '_edit_lock' !== $k && '_edit_last' !== $k ) {
				$meta[ $k ] = maybe_unserialize( $v[0] );
			}
		}
		$terms = array();
		foreach ( get_object_taxonomies( $type ) as $tax ) {
			$terms[ $tax ] = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'names' ) );
		}
		$out[] = array( 'title' => $p->post_title, 'content' => $p->post_content, 'status' => $p->post_status, 'date' => $p->post_date, 'meta' => $meta, 'terms' => $terms );
	}
	return $out;
}

/**
 * Settings without secrets (QRZ key and Rig Link token are never exported).
 */
function hamshack_export_settings() {
	$rig = (array) get_option( 'hamshack_rig', array() );
	unset( $rig['token'] );
	$qrz = (array) get_option( 'hamshack_qrz', array() );
	unset( $qrz['key'] );
	return array(
		'theme'   => 'hamshack',
		'version' => HAMSHACK_VERSION,
		'site'    => home_url(),
		'date'    => gmdate( 'c' ),
		'station' => get_option( 'hamshack_station', array() ),
		'rig'     => $rig,
		'qrz'     => $qrz,
		'news'    => get_option( 'hamshack_news', array() ),
	);
}

add_action( 'admin_post_hamshack_backup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_backup' );
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$what  = isset( $_GET['what'] ) ? sanitize_key( $_GET['what'] ) : 'zip';
	$call  = sanitize_file_name( strtolower( hamshack_opt( 'callsign' ) ) );
	$stamp = gmdate( 'Ymd-Hi' );
	$files = array(
		'settings.json'     => wp_json_encode( hamshack_export_settings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'logbook.adi'       => hamshack_adif_all(),
		'events.json'       => wp_json_encode( hamshack_export_posts( 'hamshack_event' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
		'activations.json'  => wp_json_encode( hamshack_export_posts( 'hamshack_activation' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
		'qsl-requests.json' => wp_json_encode( hamshack_export_posts( 'hamshack_qslreq' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
		'subscribers.csv'   => function_exists( 'hamshack_subscribers_csv' ) ? hamshack_subscribers_csv() : '',
		'README.txt'        => "Backup of the Ham Shack theme data from " . home_url() . " (" . gmdate( 'c' ) . ").\n\nlogbook.adi  - the whole logbook (import into any logging program, or back via QRZ sync & ADIF)\nsettings.json - station settings (restore on the Backup & export page)\n*.json/csv    - events, activations, QSL requests, newsletter subscribers\n\nThe QRZ.com API key and the Rig Link token are NOT included.\nPosts, pages and images are backed up with a full-site plugin such as UpdraftPlus.\n",
	);
	nocache_headers();
	if ( 'zip' === $what && class_exists( 'ZipArchive' ) ) {
		$tmp = wp_tempnam( 'hamshack-backup' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::OVERWRITE );
		foreach ( $files as $name => $data ) {
			$zip->addFromString( $call . '-backup-' . $stamp . '/' . $name, (string) $data );
		}
		$zip->close();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $call . '-backup-' . $stamp . '.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore
		@unlink( $tmp ); // phpcs:ignore
		exit;
	}
	$name = isset( $files[ $what . '.json' ] ) ? $what . '.json' : ( 'adi' === $what ? 'logbook.adi' : 'settings.json' );
	header( 'Content-Type: ' . ( '.adi' === substr( $name, -4 ) ? 'text/plain' : 'application/json' ) . '; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $call . '-' . $name . '"' );
	echo $files[ $name ]; // phpcs:ignore
	exit;
} );

add_action( 'admin_post_hamshack_restore', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_restore' );
	$ok = 0;
	if ( ! empty( $_FILES['settings']['tmp_name'] ) && is_uploaded_file( $_FILES['settings']['tmp_name'] ) ) { // phpcs:ignore
		$data = json_decode( (string) file_get_contents( $_FILES['settings']['tmp_name'] ), true ); // phpcs:ignore
		if ( is_array( $data ) && isset( $data['theme'], $data['station'] ) && 'hamshack' === $data['theme'] ) {
			// The registered sanitize callback runs again inside update_option(), so keep the "_full" marker.
			$clean = hamshack_sanitize_station( array_merge( (array) $data['station'], array( '_full' => 1 ) ) );
			update_option( 'hamshack_station', array_merge( $clean, array( '_full' => 1 ) ) );
			if ( ! empty( $data['news'] ) ) {
				update_option( 'hamshack_news', array_map( 'intval', (array) $data['news'] ) );
			}
			$ok = 1;
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-backup&restored=' . $ok ) );
	exit;
} );

function hamshack_backup_page() {
	$u = function ( $what ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_backup&what=' . $what ), 'hamshack_backup' );
	};
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'Backup & export', 'hamshack' ); ?></h1>
		<?php if ( isset( $_GET['restored'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-<?php echo $_GET['restored'] ? 'success' : 'error'; // phpcs:ignore ?>"><p><?php echo $_GET['restored'] ? esc_html__( 'Settings restored.', 'hamshack' ) : esc_html__( 'That file is not a Ham Shack settings backup.', 'hamshack' ); // phpcs:ignore ?></p></div>
		<?php endif; ?>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'download' ); ?> <?php esc_html_e( 'Download a backup of the radio data', 'hamshack' ); ?></h2>
			<p><?php esc_html_e( 'One ZIP with the logbook (ADIF), station settings, events, activations, QSL requests and newsletter subscribers. The QRZ.com API key and Rig Link token are left out on purpose.', 'hamshack' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $u( 'zip' ) ); ?>"><?php esc_html_e( 'Download ZIP backup', 'hamshack' ); ?></a>
				<a class="button" href="<?php echo esc_url( $u( 'adi' ) ); ?>"><?php esc_html_e( 'Logbook only (.adi)', 'hamshack' ); ?></a>
				<a class="button" href="<?php echo esc_url( $u( 'settings' ) ); ?>"><?php esc_html_e( 'Settings only (.json)', 'hamshack' ); ?></a>
			</p>
		</div>
		<form class="hamshack-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<h2><?php hamshack_the_icon( 'upload' ); ?> <?php esc_html_e( 'Restore settings', 'hamshack' ); ?></h2>
			<p><?php esc_html_e( 'Upload settings.json from a backup to restore the station settings. (The logbook comes back through QRZ sync or ADIF import.)', 'hamshack' ); ?></p>
			<?php wp_nonce_field( 'hamshack_restore' ); ?>
			<input type="hidden" name="action" value="hamshack_restore">
			<input type="file" name="settings" accept=".json" required> <button class="button"><?php esc_html_e( 'Restore', 'hamshack' ); ?></button>
		</form>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'folder' ); ?> <?php esc_html_e( 'Full-site backup (recommended)', 'hamshack' ); ?></h2>
			<ol>
				<li><?php echo wp_kses_post( __( 'Install the free plugin <strong>UpdraftPlus</strong> (Plugins → Add New → search “UpdraftPlus”).', 'hamshack' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Settings → UpdraftPlus Backups → Settings: files <strong>weekly</strong>, database <strong>daily</strong>, keep 4 copies.', 'hamshack' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Choose remote storage – e.g. <strong>Google Drive</strong> or Dropbox – so a copy lives outside your hosting.', 'hamshack' ) ); ?></li>
				<li><?php esc_html_e( 'Click “Backup Now” once to check it works. Your host’s cPanel “Backup” tool is a good extra safety net.', 'hamshack' ); ?></li>
			</ol>
		</div>
	</div>
	<?php
}

/**
 * MY_*_REF field for an exported QSO.
 */
function hamshack_adif_my_ref( $f, $ref, $type ) {
	$f = is_callable( $f ) ? $f : 'hamshack_adif_f';
	if ( '' === (string) $ref ) {
		return '';
	}
	$map = array( 'POTA' => 'MY_POTA_REF', 'SOTA' => 'MY_SOTA_REF', 'WWFF' => 'MY_WWFF_REF', 'SIG' => 'MY_SIG_INFO' );
	if ( ! isset( $map[ $type ] ) ) {
		$type = preg_match( '#^[A-Z0-9]{1,4}/[A-Z]{2}-\d{3}$#', $ref ) ? 'SOTA' : ( preg_match( '/^[A-Z0-9]{1,3}FF-\d{4}$/', $ref ) ? 'WWFF' : 'POTA' );
	}
	return $f( $map[ $type ], $ref );
}
