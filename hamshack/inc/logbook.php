<?php
/**
 * Logbook: QSO post type, fast entry, ADIF import/export, public log & stats.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QSO field definitions: key => label.
 */
function hamshack_qso_fields() {
	return array(
		'call'     => __( 'Callsign worked', 'hamshack' ),
		'date'     => __( 'Date (UTC)', 'hamshack' ),
		'time'     => __( 'Time (UTC)', 'hamshack' ),
		'freq'     => __( 'Frequency (MHz)', 'hamshack' ),
		'band'     => __( 'Band', 'hamshack' ),
		'mode'     => __( 'Mode', 'hamshack' ),
		'rst_s'    => __( 'RST sent', 'hamshack' ),
		'rst_r'    => __( 'RST rcvd', 'hamshack' ),
		'name'     => __( 'Name', 'hamshack' ),
		'qth'      => __( 'QTH', 'hamshack' ),
		'grid'     => __( 'Locator', 'hamshack' ),
		'country'  => __( 'Country / DXCC', 'hamshack' ),
		'ref'      => __( 'POTA/SOTA/WWFF ref', 'hamshack' ),
		'qsl_s'    => __( 'QSL sent', 'hamshack' ),
		'qsl_r'    => __( 'QSL rcvd', 'hamshack' ),
		'comment'  => __( 'Notes', 'hamshack' ),
	);
}

add_action( 'init', function () {
	register_post_type(
		'hamshack_qso',
		array(
			'labels'          => array(
				'name'          => __( 'Logbook', 'hamshack' ),
				'singular_name' => __( 'QSO', 'hamshack' ),
				'add_new'       => __( 'Log QSO', 'hamshack' ),
				'add_new_item'  => __( 'Log a new QSO', 'hamshack' ),
				'edit_item'     => __( 'Edit QSO', 'hamshack' ),
				'all_items'     => __( 'Logbook', 'hamshack' ),
				'search_items'  => __( 'Search log', 'hamshack' ),
				'not_found'     => __( 'No QSOs logged yet', 'hamshack' ),
				'menu_name'     => __( 'Logbook', 'hamshack' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'hamshack',
			'show_in_rest'    => false,
			'supports'        => false,
			'capability_type' => 'post',
		)
	);
} );

/* --------------------------------------------------------------------------
 * Saving
 * ------------------------------------------------------------------------ */

/**
 * Normalise & save QSO data to a post.
 *
 * @param int   $post_id Post ID (0 = create).
 * @param array $data    Raw data.
 * @return int|WP_Error
 */
function hamshack_save_qso( $post_id, array $data ) {
	$clean = array();
	foreach ( array_keys( hamshack_qso_fields() ) as $k ) {
		$v           = isset( $data[ $k ] ) ? $data[ $k ] : '';
		$clean[ $k ] = 'comment' === $k ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	}
	$clean['call'] = strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', $clean['call'] ) );
	if ( '' === $clean['call'] ) {
		return new WP_Error( 'hamshack_no_call', __( 'Callsign is required.', 'hamshack' ) );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean['date'] ) ) {
		$clean['date'] = gmdate( 'Y-m-d' );
	}
	if ( preg_match( '/^(\d{2}):?(\d{2})/', $clean['time'], $m ) ) {
		$clean['time'] = $m[1] . ':' . $m[2];
	} else {
		$clean['time'] = gmdate( 'H:i' );
	}
	$clean['freq'] = $clean['freq'] ? rtrim( rtrim( number_format( (float) str_replace( ',', '.', $clean['freq'] ), 4, '.', '' ), '0' ), '.' ) : '';
	if ( ! $clean['band'] && $clean['freq'] ) {
		$clean['band'] = hamshack_band_from_freq( $clean['freq'] );
	}
	$clean['mode']  = strtoupper( $clean['mode'] );
	$clean['grid']  = $clean['grid'] ? strtoupper( substr( $clean['grid'], 0, 4 ) ) . strtolower( substr( $clean['grid'], 4 ) ) : '';
	$clean['qsl_s'] = in_array( strtoupper( $clean['qsl_s'] ), array( 'Y', 'N', 'R', 'Q' ), true ) ? strtoupper( $clean['qsl_s'] ) : 'N';
	$clean['qsl_r'] = in_array( strtoupper( $clean['qsl_r'] ), array( 'Y', 'N', 'R', 'Q' ), true ) ? strtoupper( $clean['qsl_r'] ) : 'N';

	$title = sprintf( '%s · %s %s · %s %s', $clean['call'], $clean['date'], $clean['time'], $clean['band'], $clean['mode'] );
	$args  = array(
		'post_type'   => 'hamshack_qso',
		'post_status' => 'publish',
		'post_title'  => $title,
		'post_name'   => sanitize_title( $clean['call'] . '-' . $clean['date'] . '-' . str_replace( ':', '', $clean['time'] ) ),
	);
	remove_action( 'save_post_hamshack_qso', 'hamshack_qso_save_hook' );
	if ( $post_id ) {
		$args['ID'] = $post_id;
		$post_id    = wp_update_post( $args, true );
	} else {
		$post_id = wp_insert_post( $args, true );
	}
	add_action( 'save_post_hamshack_qso', 'hamshack_qso_save_hook' );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	foreach ( $clean as $k => $v ) {
		update_post_meta( $post_id, '_q_' . $k, $v );
	}
	update_post_meta( $post_id, '_q_dt', $clean['date'] . ' ' . $clean['time'] );
	delete_transient( 'hamshack_log_stats' );
	return $post_id;
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_qso_box', __( 'QSO details', 'hamshack' ), 'hamshack_qso_metabox', 'hamshack_qso', 'normal', 'high' );
} );

function hamshack_qso_metabox( $post ) {
	wp_nonce_field( 'hamshack_qso_save', 'hamshack_qso_nonce' );
	$is_new = 'auto-draft' === $post->post_status;
	$v      = function ( $k ) use ( $post, $is_new ) {
		$val = get_post_meta( $post->ID, '_q_' . $k, true );
		if ( $is_new && '' === $val ) {
			$defaults = array(
				'date'  => gmdate( 'Y-m-d' ),
				'time'  => gmdate( 'H:i' ),
				'rst_s' => '59',
				'rst_r' => '59',
				'freq'  => hamshack_opt( 'status_freq' ),
				'mode'  => hamshack_opt( 'status_mode' ),
			);
			return isset( $defaults[ $k ] ) ? $defaults[ $k ] : '';
		}
		return $val;
	};
	$f      = hamshack_qso_fields();
	echo '<div class="hamshack-grid hamshack-qso-grid">';
	foreach ( $f as $k => $label ) {
		echo '<p class="f-' . esc_attr( $k ) . '"><label><strong>' . esc_html( $label ) . '</strong><br>';
		switch ( $k ) {
			case 'date':
				echo '<input type="date" name="q[date]" value="' . esc_attr( $v( 'date' ) ) . '">';
				break;
			case 'time':
				echo '<input type="time" name="q[time]" value="' . esc_attr( $v( 'time' ) ) . '">';
				break;
			case 'band':
				echo '<select name="q[band]"><option value="">' . esc_html__( 'auto from freq', 'hamshack' ) . '</option>';
				foreach ( hamshack_all_bands() as $b ) {
					echo '<option ' . selected( $v( 'band' ), $b, false ) . '>' . esc_html( $b ) . '</option>';
				}
				echo '</select>';
				break;
			case 'mode':
				echo '<input type="text" list="hamshack-modes" name="q[mode]" value="' . esc_attr( $v( 'mode' ) ) . '"><datalist id="hamshack-modes">';
				foreach ( hamshack_all_modes() as $m ) {
					echo '<option value="' . esc_attr( $m ) . '">';
				}
				echo '</datalist>';
				break;
			case 'qsl_s':
			case 'qsl_r':
				echo '<select name="q[' . esc_attr( $k ) . ']">';
				foreach ( array( 'N' => __( 'No', 'hamshack' ), 'Y' => __( 'Yes', 'hamshack' ), 'R' => __( 'Requested', 'hamshack' ), 'Q' => __( 'Queued', 'hamshack' ) ) as $code => $lab ) {
					echo '<option value="' . esc_attr( $code ) . '" ' . selected( $v( $k ) ? $v( $k ) : 'N', $code, false ) . '>' . esc_html( $lab ) . '</option>';
				}
				echo '</select>';
				break;
			case 'comment':
				echo '<textarea name="q[comment]" rows="3" class="widefat">' . esc_textarea( $v( 'comment' ) ) . '</textarea>';
				break;
			default:
				$attr = 'call' === $k ? ' required autofocus style="text-transform:uppercase;font-size:1.3em;font-family:monospace"' : '';
				echo '<input type="text" name="q[' . esc_attr( $k ) . ']" value="' . esc_attr( $v( $k ) ) . '"' . $attr . '>'; // phpcs:ignore
		}
		echo '</label></p>';
	}
	echo '</div>';
	echo '<p class="description">' . esc_html__( 'Date and time are UTC. Band is filled in automatically from the frequency. Only callsign, date, time, band, mode, RST and country are shown publicly – name, QTH and notes stay private.', 'hamshack' ) . '</p>';
}

add_action( 'save_post_hamshack_qso', 'hamshack_qso_save_hook' );
function hamshack_qso_save_hook( $post_id ) {
	if ( ! isset( $_POST['hamshack_qso_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_qso_nonce'] ), 'hamshack_qso_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$data = isset( $_POST['q'] ) ? wp_unslash( (array) $_POST['q'] ) : array(); // phpcs:ignore
	hamshack_save_qso( $post_id, $data );
}

/* Admin columns */
add_filter( 'manage_hamshack_qso_posts_columns', function () {
	return array(
		'cb'      => '<input type="checkbox">',
		'title'   => __( 'Callsign', 'hamshack' ),
		'q_dt'    => __( 'UTC', 'hamshack' ),
		'q_band'  => __( 'Band', 'hamshack' ),
		'q_mode'  => __( 'Mode', 'hamshack' ),
		'q_rst'   => __( 'RST s/r', 'hamshack' ),
		'q_ctry'  => __( 'Country', 'hamshack' ),
		'q_qsl'   => __( 'QSL s/r', 'hamshack' ),
	);
} );
add_filter( 'the_title', function ( $title, $id = 0 ) {
	if ( is_admin() && $id && 'hamshack_qso' === get_post_type( $id ) ) {
		$call = get_post_meta( $id, '_q_call', true );
		return $call ? $call : $title;
	}
	return $title;
}, 10, 2 );
add_action( 'manage_hamshack_qso_posts_custom_column', function ( $col, $id ) {
	$g = function ( $k ) use ( $id ) {
		return get_post_meta( $id, '_q_' . $k, true );
	};
	switch ( $col ) {
		case 'q_dt':
			echo esc_html( $g( 'date' ) . ' ' . $g( 'time' ) );
			break;
		case 'q_band':
			echo esc_html( $g( 'band' ) . ( $g( 'freq' ) ? ' (' . $g( 'freq' ) . ')' : '' ) );
			break;
		case 'q_mode':
			echo esc_html( $g( 'mode' ) );
			break;
		case 'q_rst':
			echo esc_html( $g( 'rst_s' ) . ' / ' . $g( 'rst_r' ) );
			break;
		case 'q_ctry':
			echo esc_html( $g( 'country' ) );
			break;
		case 'q_qsl':
			echo esc_html( $g( 'qsl_s' ) . ' / ' . $g( 'qsl_r' ) );
			break;
	}
}, 10, 2 );
add_filter( 'manage_edit-hamshack_qso_sortable_columns', function ( $c ) {
	$c['q_dt']   = 'q_dt';
	$c['title']  = 'q_call';
	$c['q_band'] = 'q_band';
	return $c;
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'hamshack_qso' !== $q->get( 'post_type' ) ) {
		return;
	}
	$ob  = $q->get( 'orderby' );
	$map = array( 'q_dt' => '_q_dt', 'q_call' => '_q_call', 'q_band' => '_q_band' );
	$key = isset( $map[ $ob ] ) ? $map[ $ob ] : '_q_dt';
	$q->set( 'meta_key', $key );
	$q->set( 'orderby', 'meta_value' );
	if ( ! $q->get( 'order' ) || ! $ob ) {
		$q->set( 'order', 'DESC' );
	}
	// Search by callsign.
	$s = $q->get( 's' );
	if ( $s ) {
		$q->set( 's', '' );
		$q->set( 'meta_query', array( array( 'key' => '_q_call', 'value' => strtoupper( $s ), 'compare' => 'LIKE' ) ) );
	}
} );

/* --------------------------------------------------------------------------
 * ADIF import / export
 * ------------------------------------------------------------------------ */

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'QRZ sync & ADIF', 'hamshack' ), __( 'QRZ sync & ADIF', 'hamshack' ), 'edit_posts', 'hamshack-adif', 'hamshack_adif_page' );
}, 20 );

function hamshack_adif_page() {
	$count = wp_count_posts( 'hamshack_qso' );
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'QRZ.com sync & ADIF import / export', 'hamshack' ); ?></h1>
		<?php if ( isset( $_GET['imported'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-success"><p><?php printf( esc_html__( 'Imported %1$d QSOs, skipped %2$d duplicates.', 'hamshack' ), (int) $_GET['imported'], isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0 ); // phpcs:ignore ?></p></div>
		<?php endif; ?>
		<?php do_action( 'hamshack_logbook_sync_panel' ); ?>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'upload' ); ?> <?php esc_html_e( 'Import ADIF (.adi)', 'hamshack' ); ?></h2>
			<p><?php esc_html_e( 'Export an ADIF file from WSJT-X, Log4OM, N1MM, QRZ Logbook, HRD, etc. and upload it here. Duplicates (same call, date, time and band) are skipped.', 'hamshack' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hamshack_adif_import' ); ?>
				<input type="hidden" name="action" value="hamshack_adif_import">
				<input type="file" name="adif" accept=".adi,.adif,.txt" required>
				<?php submit_button( __( 'Import', 'hamshack' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'download' ); ?> <?php esc_html_e( 'Export ADIF', 'hamshack' ); ?></h2>
			<p><?php printf( esc_html__( 'Your log currently holds %d QSOs.', 'hamshack' ), (int) $count->publish ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_adif_export' ), 'hamshack_adif_export' ) ); ?>"><?php esc_html_e( 'Download .adi file', 'hamshack' ); ?></a>
		</div>
	</div>
	<?php
}

/**
 * Parse ADIF text into an array of records (lower-case field names).
 */
function hamshack_parse_adif( $text ) {
	$pos = stripos( $text, '<eoh>' );
	if ( false !== $pos ) {
		$text = substr( $text, $pos + 5 );
	}
	$records = array();
	$rec     = array();
	$len     = strlen( $text );
	$i       = 0;
	while ( $i < $len ) {
		$lt = strpos( $text, '<', $i );
		if ( false === $lt ) {
			break;
		}
		$gt = strpos( $text, '>', $lt );
		if ( false === $gt ) {
			break;
		}
		$tag   = substr( $text, $lt + 1, $gt - $lt - 1 );
		$parts = explode( ':', $tag );
		$name  = strtolower( trim( $parts[0] ) );
		if ( 'eor' === $name ) {
			if ( $rec ) {
				$records[] = $rec;
			}
			$rec = array();
			$i   = $gt + 1;
			continue;
		}
		$flen = isset( $parts[1] ) ? (int) $parts[1] : 0;
		$rec[ $name ] = trim( substr( $text, $gt + 1, $flen ) );
		$i            = $gt + 1 + $flen;
	}
	return $records;
}

add_action( 'admin_post_hamshack_adif_import', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_adif_import' );
	if ( empty( $_FILES['adif']['tmp_name'] ) || ! is_uploaded_file( $_FILES['adif']['tmp_name'] ) ) { // phpcs:ignore
		wp_die( esc_html__( 'No file uploaded.', 'hamshack' ) );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$text     = file_get_contents( $_FILES['adif']['tmp_name'] ); // phpcs:ignore
	$records  = hamshack_parse_adif( (string) $text );
	$imported = 0;
	$skipped  = 0;
	wp_defer_term_counting( true );
	foreach ( $records as $r ) {
		if ( empty( $r['call'] ) ) {
			continue;
		}
		$date = isset( $r['qso_date'] ) && preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $r['qso_date'], $d ) ? "$d[1]-$d[2]-$d[3]" : '';
		$time = isset( $r['time_on'] ) ? substr( $r['time_on'], 0, 2 ) . ':' . substr( $r['time_on'], 2, 2 ) : '';
		$band = isset( $r['band'] ) ? strtolower( $r['band'] ) : '';
		$mode = isset( $r['submode'] ) && $r['submode'] ? $r['submode'] : ( isset( $r['mode'] ) ? $r['mode'] : '' );
		$dupe = get_posts(
			array(
				'post_type'      => 'hamshack_qso',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array( 'key' => '_q_call', 'value' => strtoupper( $r['call'] ) ),
					array( 'key' => '_q_dt', 'value' => $date . ' ' . $time ),
				),
			)
		);
		if ( $dupe ) {
			$skipped++;
			continue;
		}
		$res = hamshack_save_qso(
			0,
			array(
				'call'    => $r['call'],
				'date'    => $date,
				'time'    => $time,
				'freq'    => isset( $r['freq'] ) ? $r['freq'] : '',
				'band'    => in_array( $band, array_map( 'strtolower', hamshack_all_bands() ), true ) ? $band : '',
				'mode'    => $mode,
				'rst_s'   => isset( $r['rst_sent'] ) ? $r['rst_sent'] : '',
				'rst_r'   => isset( $r['rst_rcvd'] ) ? $r['rst_rcvd'] : '',
				'name'    => isset( $r['name'] ) ? $r['name'] : '',
				'qth'     => isset( $r['qth'] ) ? $r['qth'] : '',
				'grid'    => isset( $r['gridsquare'] ) ? $r['gridsquare'] : '',
				'country' => isset( $r['country'] ) ? ucwords( strtolower( $r['country'] ) ) : '',
				'ref'     => isset( $r['pota_ref'] ) ? $r['pota_ref'] : ( isset( $r['sota_ref'] ) ? $r['sota_ref'] : '' ),
				'qsl_s'   => isset( $r['qsl_sent'] ) ? $r['qsl_sent'] : 'N',
				'qsl_r'   => isset( $r['qsl_rcvd'] ) ? $r['qsl_rcvd'] : 'N',
				'comment' => isset( $r['comment'] ) ? $r['comment'] : '',
			)
		);
		if ( ! is_wp_error( $res ) ) {
			$imported++;
		}
	}
	wp_defer_term_counting( false );
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-adif&imported=' . $imported . '&skipped=' . $skipped ) );
	exit;
} );

add_action( 'admin_post_hamshack_adif_export', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_adif_export' );
	$ids = get_posts( array( 'post_type' => 'hamshack_qso', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_q_dt', 'orderby' => 'meta_value', 'order' => 'ASC' ) );
	$f   = function ( $name, $value ) {
		$value = (string) $value;
		return '' === $value ? '' : '<' . $name . ':' . strlen( $value ) . '>' . $value . ' ';
	};
	$out = 'ADIF export from ' . home_url() . "\n" . $f( 'ADIF_VER', '3.1.4' ) . $f( 'PROGRAMID', 'HamShack-WP' ) . $f( 'CREATED_TIMESTAMP', gmdate( 'Ymd His' ) ) . "<EOH>\n";
	foreach ( $ids as $id ) {
		$g    = function ( $k ) use ( $id ) {
			return get_post_meta( $id, '_q_' . $k, true );
		};
		$out .= $f( 'CALL', $g( 'call' ) ) . $f( 'QSO_DATE', str_replace( '-', '', $g( 'date' ) ) ) . $f( 'TIME_ON', str_replace( ':', '', $g( 'time' ) ) )
			. $f( 'BAND', $g( 'band' ) ) . $f( 'FREQ', $g( 'freq' ) ) . $f( 'MODE', $g( 'mode' ) ) . $f( 'RST_SENT', $g( 'rst_s' ) ) . $f( 'RST_RCVD', $g( 'rst_r' ) )
			. $f( 'NAME', $g( 'name' ) ) . $f( 'QTH', $g( 'qth' ) ) . $f( 'GRIDSQUARE', $g( 'grid' ) ) . $f( 'COUNTRY', $g( 'country' ) )
			. $f( 'QSL_SENT', $g( 'qsl_s' ) ) . $f( 'QSL_RCVD', $g( 'qsl_r' ) ) . $f( 'COMMENT', $g( 'comment' ) )
			. $f( 'STATION_CALLSIGN', hamshack_opt( 'callsign' ) ) . $f( 'MY_GRIDSQUARE', hamshack_opt( 'locator' ) ) . "<EOR>\n";
	}
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( strtolower( hamshack_opt( 'callsign' ) ) . '-log-' . gmdate( 'Ymd' ) ) . '.adi"' );
	echo $out; // phpcs:ignore
	exit;
} );

/* --------------------------------------------------------------------------
 * Public logbook
 * ------------------------------------------------------------------------ */

/**
 * Log statistics (cached).
 */
function hamshack_log_stats() {
	$stats = get_transient( 'hamshack_log_stats' );
	if ( false !== $stats ) {
		return $stats;
	}
	global $wpdb;
	$base  = "FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'hamshack_qso' AND p.post_status = 'publish'";
	$stats = array(
		'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) $base WHERE pm.meta_key = '_q_call'" ), // phpcs:ignore
		'calls'     => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT pm.meta_value) $base WHERE pm.meta_key = '_q_call'" ), // phpcs:ignore
		'countries' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT pm.meta_value) $base WHERE pm.meta_key = '_q_country' AND pm.meta_value <> ''" ), // phpcs:ignore
		'grids'     => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT SUBSTR(pm.meta_value,1,4)) $base WHERE pm.meta_key = '_q_grid' AND pm.meta_value <> ''" ), // phpcs:ignore
		'bands'     => $wpdb->get_results( "SELECT pm.meta_value AS k, COUNT(*) AS n $base WHERE pm.meta_key = '_q_band' AND pm.meta_value <> '' GROUP BY pm.meta_value ORDER BY n DESC", ARRAY_A ), // phpcs:ignore
		'modes'     => $wpdb->get_results( "SELECT pm.meta_value AS k, COUNT(*) AS n $base WHERE pm.meta_key = '_q_mode' AND pm.meta_value <> '' GROUP BY pm.meta_value ORDER BY n DESC", ARRAY_A ), // phpcs:ignore
	);
	set_transient( 'hamshack_log_stats', $stats, 6 * HOUR_IN_SECONDS );
	return $stats;
}
add_action( 'deleted_post', function () {
	delete_transient( 'hamshack_log_stats' );
} );
add_action( 'trashed_post', function () {
	delete_transient( 'hamshack_log_stats' );
} );

/**
 * Stats markup.
 */
function hamshack_log_stats_html() {
	$s = hamshack_log_stats();
	ob_start();
	?>
	<div class="log-stats">
		<div class="stat"><span class="num" data-count="<?php echo (int) $s['total']; ?>"><?php echo esc_html( number_format_i18n( $s['total'] ) ); ?></span><span class="lbl"><?php esc_html_e( 'QSOs', 'hamshack' ); ?></span></div>
		<div class="stat"><span class="num"><?php echo esc_html( number_format_i18n( $s['calls'] ) ); ?></span><span class="lbl"><?php esc_html_e( 'Unique calls', 'hamshack' ); ?></span></div>
		<div class="stat"><span class="num"><?php echo esc_html( number_format_i18n( $s['countries'] ) ); ?></span><span class="lbl"><?php esc_html_e( 'Countries', 'hamshack' ); ?></span></div>
		<div class="stat"><span class="num"><?php echo esc_html( number_format_i18n( $s['grids'] ) ); ?></span><span class="lbl"><?php esc_html_e( 'Grid squares', 'hamshack' ); ?></span></div>
	</div>
	<?php if ( $s['bands'] ) : ?>
		<div class="log-bars">
			<?php
			$max = max( array_map( 'intval', wp_list_pluck( $s['bands'], 'n' ) ) );
			foreach ( $s['bands'] as $b ) :
				?>
				<div class="bar-row"><span class="bar-k mono"><?php echo esc_html( $b['k'] ); ?></span><span class="bar"><span style="width:<?php echo esc_attr( round( 100 * $b['n'] / max( 1, $max ) ) ); ?>%"></span></span><span class="bar-n mono"><?php echo (int) $b['n']; ?></span></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}
add_shortcode( 'hamshack_logbook_stats', 'hamshack_log_stats_html' );

/**
 * QSO table rows.
 */
function hamshack_qso_table( $ids, $show_search = false ) {
	ob_start();
	?>
	<div class="table-wrap">
		<table class="qso-table">
			<thead><tr>
				<th><?php esc_html_e( 'Date', 'hamshack' ); ?></th>
				<th><?php esc_html_e( 'UTC', 'hamshack' ); ?></th>
				<th><?php esc_html_e( 'Call', 'hamshack' ); ?></th>
				<th><?php esc_html_e( 'Band', 'hamshack' ); ?></th>
				<th><?php esc_html_e( 'Mode', 'hamshack' ); ?></th>
				<th><?php esc_html_e( 'RST s/r', 'hamshack' ); ?></th>
				<th class="hide-sm"><?php esc_html_e( 'Country', 'hamshack' ); ?></th>
				<th class="hide-sm"><?php esc_html_e( 'QSL', 'hamshack' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $ids ) : ?>
				<tr><td colspan="8" class="muted"><?php esc_html_e( 'No QSOs found.', 'hamshack' ); ?></td></tr>
			<?php endif; ?>
			<?php
			foreach ( $ids as $id ) :
				$g    = function ( $k ) use ( $id ) {
					return get_post_meta( $id, '_q_' . $k, true );
				};
				$call = $g( 'call' );
				?>
				<tr>
					<td class="mono"><?php echo esc_html( $g( 'date' ) ); ?></td>
					<td class="mono"><?php echo esc_html( $g( 'time' ) ); ?></td>
					<td class="mono call"><a href="<?php echo esc_url( 'https://www.qrz.com/db/' . rawurlencode( $call ) ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $call ); ?></a></td>
					<td><span class="tag"><?php echo esc_html( $g( 'band' ) ); ?></span></td>
					<td><?php echo esc_html( $g( 'mode' ) ); ?></td>
					<td class="mono"><?php echo esc_html( $g( 'rst_s' ) . ' / ' . $g( 'rst_r' ) ); ?></td>
					<td class="hide-sm"><?php echo esc_html( $g( 'country' ) ); ?></td>
					<td class="hide-sm"><?php echo 'Y' === $g( 'qsl_r' ) ? '<span class="ok" title="' . esc_attr__( 'QSL received', 'hamshack' ) . '">✔</span>' : ( 'Y' === $g( 'qsl_s' ) ? '<span class="muted" title="' . esc_attr__( 'QSL sent', 'hamshack' ) . '">↗</span>' : '' ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Recent QSO IDs.
 */
function hamshack_recent_qsos( $limit = 5 ) {
	return get_posts(
		array(
			'post_type'      => 'hamshack_qso',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_key'       => '_q_dt',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
}

add_shortcode( 'hamshack_logbook', function ( $atts ) {
	$atts  = shortcode_atts( array( 'per_page' => 25, 'stats' => '1' ), $atts, 'hamshack_logbook' );
	$call  = isset( $_GET['qso_call'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', sanitize_text_field( wp_unslash( $_GET['qso_call'] ) ) ) ) : ''; // phpcs:ignore
	$band  = isset( $_GET['qso_band'] ) ? sanitize_text_field( wp_unslash( $_GET['qso_band'] ) ) : ''; // phpcs:ignore
	$paged = isset( $_GET['qso_page'] ) ? max( 1, (int) $_GET['qso_page'] ) : 1; // phpcs:ignore
	$mq    = array();
	if ( $call ) {
		$mq[] = array( 'key' => '_q_call', 'value' => $call, 'compare' => 'LIKE' );
	}
	if ( $band && in_array( $band, hamshack_all_bands(), true ) ) {
		$mq[] = array( 'key' => '_q_band', 'value' => $band );
	}
	$q = new WP_Query(
		array(
			'post_type'      => 'hamshack_qso',
			'posts_per_page' => (int) $atts['per_page'],
			'paged'          => $paged,
			'fields'         => 'ids',
			'meta_key'       => '_q_dt',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
			'meta_query'     => $mq, // phpcs:ignore
		)
	);
	ob_start();
	if ( '1' === $atts['stats'] ) {
		echo hamshack_log_stats_html(); // phpcs:ignore
	}
	?>
	<form class="log-search" method="get" action="">
		<label class="screen-reader-text" for="qso_call"><?php esc_html_e( 'Search callsign', 'hamshack' ); ?></label>
		<span class="input-icon"><?php hamshack_the_icon( 'search', 18 ); ?><input id="qso_call" type="search" name="qso_call" value="<?php echo esc_attr( $call ); ?>" placeholder="<?php esc_attr_e( 'Are you in my log? Enter your callsign…', 'hamshack' ); ?>" autocomplete="off" spellcheck="false"></span>
		<select name="qso_band" aria-label="<?php esc_attr_e( 'Band', 'hamshack' ); ?>">
			<option value=""><?php esc_html_e( 'All bands', 'hamshack' ); ?></option>
			<?php foreach ( hamshack_all_bands() as $b ) : ?>
				<option <?php selected( $band, $b ); ?>><?php echo esc_html( $b ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn" type="submit"><?php esc_html_e( 'Search', 'hamshack' ); ?></button>
	</form>
	<?php if ( $call ) : ?>
		<p class="log-result <?php echo $q->found_posts ? 'is-found' : 'is-missing'; ?>">
			<?php
			if ( $q->found_posts ) {
				printf( esc_html( _n( '%1$s – found %2$d QSO in the log. Thanks for the contact, 73!', '%1$s – found %2$d QSOs in the log. Thanks for the contacts, 73!', $q->found_posts, 'hamshack' ) ), '<strong class="mono">' . esc_html( $call ) . '</strong>', (int) $q->found_posts ); // phpcs:ignore
			} else {
				printf( esc_html__( 'No QSOs with %s yet – hope to work you soon!', 'hamshack' ), '<strong class="mono">' . esc_html( $call ) . '</strong>' ); // phpcs:ignore
			}
			?>
		</p>
	<?php endif; ?>
	<?php
	echo hamshack_qso_table( $q->posts ); // phpcs:ignore
	if ( $q->max_num_pages > 1 ) {
		echo '<nav class="pagination">' . paginate_links( // phpcs:ignore
			array(
				'base'      => add_query_arg( 'qso_page', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $q->max_num_pages,
				'type'      => 'list',
				'prev_text' => '‹',
				'next_text' => '›',
			)
		) . '</nav>';
	}
	return ob_get_clean();
} );
