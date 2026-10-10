<?php
/**
 * eQSL cards for visitors (download per QSO) and OQRS – online requests for paper QSL cards.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Personal QSL card for one QSO (SVG).
 *
 * @param int $id QSO post ID.
 * @return string
 */
function hamshack_eqsl_svg( $id ) {
	$g    = function ( $k ) use ( $id ) {
		return (string) get_post_meta( $id, '_q_' . $k, true );
	};
	$my   = strtoupper( hamshack_opt( 'callsign' ) );
	// Highlight the prefix digit, like the logo.
	list( $ca, $cd, $cb ) = hamshack_call_parts( $my );
	$call = hamshack_x( $ca ) . ( '' !== $cd ? '<tspan fill="url(#am)" filter="url(#g)">' . hamshack_x( $cd ) . '</tspan>' : '' ) . hamshack_x( $cb );
	$fs    = strlen( $my ) > 7 ? 86 : 112;
	$qth   = strtoupper( str_replace( ',', ' ·', hamshack_opt( 'qth' ) ) );
	$info  = sprintf( 'LOC %s  ·  ITU %s  ·  CQ %s', strtoupper( hamshack_opt( 'locator' ) ), hamshack_opt( 'itu_zone' ), hamshack_opt( 'cq_zone' ) );
	$date  = $g( 'date' );
	$freq  = $g( 'freq' ) ? $g( 'freq' ) : $g( 'band' );
	$rst   = $g( 'rst_s' ) ? $g( 'rst_s' ) : '59';
	$mode  = $g( 'mode' );
	$to    = $g( 'call' );
	$tcfs  = strlen( $to ) > 9 ? 16 : 20;
	$foot  = sprintf( 'eQSL · %s · %s', wp_parse_url( home_url(), PHP_URL_HOST ), gmdate( 'Y-m-d' ) );
	$extra = array();
	if ( $g( 'grid' ) ) {
		$extra[] = 'YOUR LOC ' . strtoupper( $g( 'grid' ) );
	}
	if ( $g( 'km' ) ) {
		$extra[] = number_format( (int) $g( 'km' ) ) . ' KM';
	}
	if ( $g( 'my_ref' ) ) {
		$extra[] = 'MY REF ' . $g( 'my_ref' );
	}
	ob_start();
	?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 580" width="1800" height="1160">
<title><?php echo hamshack_x( $my . ' QSL – ' . $to ); ?></title>
<defs>
<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0d1823"/><stop offset="1" stop-color="#081018"/></linearGradient>
<linearGradient id="am" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffd166"/><stop offset="1" stop-color="#ff9f1c"/></linearGradient>
<filter id="g"><feGaussianBlur stdDeviation="3" result="x"/><feMerge><feMergeNode in="x"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
<clipPath id="c"><rect width="900" height="580" rx="22"/></clipPath>
</defs>
<g clip-path="url(#c)">
<rect width="900" height="580" fill="url(#bg)"/>
<g stroke="#16293a" stroke-width="1"><path d="M0 80 H900 M0 160 H900 M0 240 H900 M0 320 H900 M0 400 H900 M0 480 H900"/><path d="M100 0 V580 M250 0 V580 M400 0 V580 M550 0 V580 M700 0 V580 M850 0 V580"/></g>
<path d="M0 250 C120 230 220 236 330 244 C450 252 520 226 640 232 C760 238 820 250 900 240 V330 H0Z" fill="#0c1a26"/>
<g stroke="#1f3a52" stroke-width="4" fill="none" stroke-linecap="round"><path d="M760 70 L735 240 M760 70 L785 240 M742 190 H778 M747 150 H773 M752 110 H768 M742 190 L773 150 M778 190 L747 150 M747 150 L768 110 M773 150 L752 110"/></g>
<g fill="none" stroke="#22d3ee" stroke-linecap="round" opacity=".5"><path d="M782 46 a30 30 0 0 1 0 44" stroke-width="3"/><path d="M798 30 a52 52 0 0 1 0 76" stroke-width="2"/><path d="M738 46 a30 30 0 0 0 0 44" stroke-width="3"/><path d="M722 30 a52 52 0 0 0 0 76" stroke-width="2"/></g><circle cx="760" cy="66" r="6" fill="#ffd166"/>
<text x="50" y="150" font-family="'JetBrains Mono',ui-monospace,Menlo,Consolas,monospace" font-size="<?php echo (int) $fs; ?>" font-weight="800" fill="#e8f1fa" letter-spacing="4"><?php echo $call; // phpcs:ignore ?></text>
<text x="54" y="196" font-family="Inter,'Segoe UI',system-ui,sans-serif" font-size="22" font-weight="600" fill="#b8c7d6" letter-spacing="2"><?php echo hamshack_x( $qth ); ?></text>
<text x="54" y="228" font-family="'JetBrains Mono',ui-monospace,Consolas,monospace" font-size="18" fill="#22d3ee" letter-spacing="2"><?php echo hamshack_x( $info ); ?></text>
<text x="54" y="300" font-family="Inter,'Segoe UI',system-ui,sans-serif" font-size="17" font-weight="700" fill="#8aa0b4" letter-spacing="2">CONFIRMING OUR QSO</text>
<rect x="50" y="330" width="800" height="150" rx="12" fill="#07101a" stroke="#233142" stroke-width="2"/>
<g font-family="Inter,'Segoe UI',system-ui,sans-serif" font-size="15" font-weight="700" fill="#8aa0b4" letter-spacing="1.5" text-anchor="middle">
<text x="140" y="366">TO RADIO</text><text x="290" y="366">DATE</text><text x="410" y="366">UTC</text><text x="520" y="366">MHz</text><text x="630" y="366">MODE</text><text x="760" y="366">RST</text>
</g>
<path d="M50 382 H850" stroke="#233142" stroke-width="2"/>
<path d="M230 330 V<?php echo $extra ? 448 : 480; ?> M350 330 V<?php echo $extra ? 448 : 480; ?> M470 330 V<?php echo $extra ? 448 : 480; ?> M570 330 V<?php echo $extra ? 448 : 480; ?> M690 330 V<?php echo $extra ? 448 : 480; ?>" stroke="#233142" stroke-width="2"/><?php if ( $extra ) : ?><path d="M50 448 H850" stroke="#233142" stroke-width="2"/><?php endif; ?>
<g font-family="'JetBrains Mono',ui-monospace,Consolas,monospace" font-size="20" fill="#ffd166" text-anchor="middle">
<text x="140" y="422" font-size="<?php echo (int) $tcfs; ?>" font-weight="700" fill="#f1f6fb"><?php echo hamshack_x( $to ); ?></text><text x="290" y="422" font-size="18"><?php echo hamshack_x( $date ); ?></text><text x="410" y="422"><?php echo hamshack_x( str_replace( ':', '', $g( 'time' ) ) ); ?></text>
<text x="520" y="422"><?php echo hamshack_x( $freq ); ?></text><text x="630" y="422"><?php echo hamshack_x( $mode ); ?></text><text x="760" y="422"><?php echo hamshack_x( $rst ); ?></text>
</g>
<?php if ( $extra ) : ?>
<text x="450" y="470" text-anchor="middle" font-family="'JetBrains Mono',ui-monospace,Consolas,monospace" font-size="13" fill="#8aa0b4" letter-spacing="1.5"><?php echo hamshack_x( implode( '   ·   ', $extra ) ); ?></text>
<?php endif; ?>
<text x="54" y="526" font-family="Inter,'Segoe UI',system-ui,sans-serif" font-size="18" fill="#b8c7d6">73 &amp; tnx for the QSO!</text>
<text x="846" y="526" text-anchor="end" font-family="'JetBrains Mono',ui-monospace,Consolas,monospace" font-size="18" fill="#39ff88">TNX QSL ✔</text>
<text x="54" y="550" font-family="'JetBrains Mono',ui-monospace,Consolas,monospace" font-size="11" fill="#5d738a"><?php echo hamshack_x( $foot ); ?></text>
<rect x="0" y="560" width="900" height="20" fill="url(#am)" opacity=".85"/>
</g>
</svg>
	<?php
	return trim( ob_get_clean() );
}

/**
 * URL of a visitor's eQSL card.
 */
function hamshack_qsl_url( $id, $call ) {
	return add_query_arg( array( 'hamshack_qsl' => (int) $id, 'call' => rawurlencode( strtoupper( $call ) ) ), home_url( '/' ) );
}

/* Serve the card: ?hamshack_qsl=<id>&call=<CALL>. The callsign must match the QSO. */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['hamshack_qsl'] ) ) { // phpcs:ignore
		return;
	}
	$id   = (int) $_GET['hamshack_qsl']; // phpcs:ignore
	$call = isset( $_GET['call'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', sanitize_text_field( wp_unslash( $_GET['call'] ) ) ) ) : ''; // phpcs:ignore
	if ( ! $id || 'hamshack_qso' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || strtoupper( get_post_meta( $id, '_q_call', true ) ) !== $call ) {
		status_header( 404 );
		nocache_headers();
		echo 'QSO not found';
		exit;
	}
	header( 'Content-Type: image/svg+xml; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Content-Disposition: inline; filename="' . sanitize_file_name( strtolower( hamshack_opt( 'callsign' ) . '-qsl-' . $call . '-' . get_post_meta( $id, '_q_date', true ) ) ) . '.svg"' );
	echo hamshack_eqsl_svg( $id ); // phpcs:ignore
	exit;
} );

/* --------------------------------------------------------------------------
 * OQRS – QSL card requests
 * ------------------------------------------------------------------------ */

add_action( 'init', function () {
	register_post_type(
		'hamshack_qslreq',
		array(
			'labels'          => array(
				'name'          => __( 'QSL requests', 'hamshack' ),
				'singular_name' => __( 'QSL request', 'hamshack' ),
				'menu_name'     => __( 'QSL requests', 'hamshack' ),
				'all_items'     => __( 'QSL requests', 'hamshack' ),
				'edit_item'     => __( 'QSL request', 'hamshack' ),
				'not_found'     => __( 'No QSL requests yet', 'hamshack' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'hamshack',
			'supports'        => array( 'title' ),
			'capabilities'    => array(
				'edit_post'              => 'manage_options',
				'read_post'              => 'manage_options',
				'delete_post'            => 'manage_options',
				'edit_posts'             => 'manage_options',
				'edit_others_posts'      => 'manage_options',
				'edit_published_posts'   => 'manage_options',
				'edit_private_posts'     => 'manage_options',
				'publish_posts'          => 'manage_options',
				'read_private_posts'     => 'manage_options',
				'delete_posts'           => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'create_posts'           => 'do_not_allow',
			),
			'map_meta_cap'    => false,
		)
	);
} );

/**
 * Route labels.
 */
function hamshack_qsl_routes() {
	return array(
		'bureau' => __( 'Via the bureau', 'hamshack' ),
		'direct' => __( 'Direct by post', 'hamshack' ),
		'swap'   => __( 'I have already sent my card', 'hamshack' ),
	);
}

/* Handle a request. */
add_action( 'template_redirect', function () {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['hamshack_oqrs'] ) ) { // phpcs:ignore
		return;
	}
	$back = hamshack_back_url();
	$back = remove_query_arg( array( 'oqrs', 'oerr' ), $back );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'oerr', $code, $back ) . '#oqrs' );
		exit;
	};
	if ( ! isset( $_POST['_m6nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_m6nonce'] ), 'hamshack_oqrs' ) ) {
		$fail( 'expired' );
	}
	$ts = isset( $_POST['_m6t'] ) ? (int) $_POST['_m6t'] : 0;
	if ( ! empty( $_POST['website'] ) || ! $ts || time() - $ts < 3 ) {
		$fail( 'spam' );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'hamshack_o_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		$fail( 'rate' );
	}
	$call  = isset( $_POST['o_call'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', sanitize_text_field( wp_unslash( $_POST['o_call'] ) ) ) ) : '';
	$ids   = isset( $_POST['o_qso'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['o_qso'] ) ) : array(); // phpcs:ignore
	$route = isset( $_POST['o_route'] ) ? sanitize_key( $_POST['o_route'] ) : 'bureau';
	$name  = isset( $_POST['o_name'] ) ? sanitize_text_field( wp_unslash( $_POST['o_name'] ) ) : '';
	$email = isset( $_POST['o_email'] ) ? sanitize_email( wp_unslash( $_POST['o_email'] ) ) : '';
	$addr  = isset( $_POST['o_addr'] ) ? sanitize_textarea_field( wp_unslash( $_POST['o_addr'] ) ) : '';
	$msg   = isset( $_POST['o_msg'] ) ? sanitize_textarea_field( wp_unslash( $_POST['o_msg'] ) ) : '';
	if ( ! isset( hamshack_qsl_routes()[ $route ] ) ) {
		$route = 'bureau';
	}
	// Only QSOs that really belong to this callsign.
	$ids = array_values( array_filter( array_slice( array_unique( $ids ), 0, 30 ), function ( $id ) use ( $call ) {
		return 'hamshack_qso' === get_post_type( $id ) && 'publish' === get_post_status( $id ) && strtoupper( get_post_meta( $id, '_q_call', true ) ) === $call;
	} ) );
	if ( ! $call || ! $ids ) {
		$fail( 'qso' );
	}
	if ( $email && ! is_email( $email ) ) {
		$fail( 'fields' );
	}
	if ( 'direct' === $route && strlen( $addr ) < 10 ) {
		$fail( 'addr' );
	}
	$lines = array();
	foreach ( $ids as $id ) {
		$lines[] = sprintf( '%s %s UTC  %s  %s', get_post_meta( $id, '_q_date', true ), get_post_meta( $id, '_q_time', true ), get_post_meta( $id, '_q_band', true ), get_post_meta( $id, '_q_mode', true ) );
	}
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'hamshack_qslreq',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s – %s', $call, hamshack_qsl_routes()[ $route ] ),
		)
	);
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		$fail( 'mail' );
	}
	update_post_meta( $post_id, '_r_call', $call );
	update_post_meta( $post_id, '_r_qsos', $ids );
	update_post_meta( $post_id, '_r_route', $route );
	update_post_meta( $post_id, '_r_name', $name );
	update_post_meta( $post_id, '_r_email', $email );
	update_post_meta( $post_id, '_r_addr', $addr );
	update_post_meta( $post_id, '_r_msg', $msg );
	update_post_meta( $post_id, '_r_status', 'new' );
	foreach ( $ids as $id ) {
		if ( 'Y' !== get_post_meta( $id, '_q_qsl_s', true ) ) {
			update_post_meta( $id, '_q_qsl_s', 'R' );
		}
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	$to   = hamshack_opt( 'email' ) ? hamshack_opt( 'email' ) : get_option( 'admin_email' );
	$body = sprintf(
		"QSL request from %s (%s)\nRoute: %s\nEmail: %s\n\nQSOs:\n%s\n%s%s\n\nManage: %s",
		$call,
		$name ? $name : '-',
		hamshack_qsl_routes()[ $route ],
		$email ? $email : '-',
		implode( "\n", $lines ),
		$addr ? "\nAddress:\n" . $addr . "\n" : '',
		$msg ? "\nMessage:\n" . $msg : '',
		admin_url( 'edit.php?post_type=hamshack_qslreq' )
	);
	$rname = trim( str_replace( array( ',', '"', ';', '<', '>', "\r", "\n" ), ' ', $name ? $name : $call ) );
	wp_mail( $to, sprintf( '[%s] QSL request: %s', hamshack_opt( 'callsign' ), $call ), $body, $email ? array( 'Reply-To: "' . $rname . '" <' . $email . '>' ) : array() );
	wp_safe_redirect( add_query_arg( 'oqrs', '1', $back ) . '#oqrs' );
	exit;
} );

/**
 * The OQRS form for one callsign (shown under the logbook search result).
 *
 * @param string $call Callsign.
 * @param int[]  $ids  QSO IDs for that call.
 */
function hamshack_oqrs_form( $call, array $ids ) {
	$errors = array(
		'expired' => __( 'The form expired – please try again.', 'hamshack' ),
		'spam'    => __( 'Your request looked automated. Please try again.', 'hamshack' ),
		'rate'    => __( 'Too many requests – please try again later.', 'hamshack' ),
		'qso'     => __( 'Please tick at least one QSO.', 'hamshack' ),
		'fields'  => __( 'Please enter a valid email address (or leave it empty).', 'hamshack' ),
		'addr'    => __( 'For a direct card I need your postal address.', 'hamshack' ),
		'mail'    => __( 'Something went wrong. Please try again later.', 'hamshack' ),
	);
	ob_start();
	?>
	<details id="oqrs" class="oqrs panel" <?php echo isset( $_GET['oqrs'] ) || isset( $_GET['oerr'] ) ? 'open' : ''; // phpcs:ignore ?>>
		<summary><?php hamshack_the_icon( 'card', 20 ); ?> <span><?php esc_html_e( 'Would you like a paper QSL card? Request it here (OQRS)', 'hamshack' ); ?></span></summary>
		<?php if ( isset( $_GET['oqrs'] ) ) : // phpcs:ignore ?>
			<p class="notice notice-ok" role="status"><?php esc_html_e( 'Thanks – your QSL request is in. I will send the card as soon as I can. 73!', 'hamshack' ); ?></p>
		<?php else : ?>
			<?php if ( isset( $_GET['oerr'] ) && isset( $errors[ sanitize_key( $_GET['oerr'] ) ] ) ) : // phpcs:ignore ?>
				<p class="notice notice-err" role="alert"><?php echo esc_html( $errors[ sanitize_key( $_GET['oerr'] ) ] ); // phpcs:ignore ?></p>
			<?php endif; ?>
			<form method="post" action="" class="oqrs-form">
				<input type="hidden" name="hamshack_oqrs" value="1">
				<input type="hidden" name="o_call" value="<?php echo esc_attr( $call ); ?>">
				<input type="hidden" name="_m6t" value="<?php echo (int) time(); ?>">
				<?php wp_nonce_field( 'hamshack_oqrs', '_m6nonce' ); ?>
				<fieldset>
					<legend><?php esc_html_e( 'QSOs', 'hamshack' ); ?></legend>
					<div class="oqrs-qsos">
					<?php
					foreach ( $ids as $id ) :
						$sent = 'Y' === get_post_meta( $id, '_q_qsl_s', true );
						?>
						<label class="oqrs-qso"><input type="checkbox" name="o_qso[]" value="<?php echo (int) $id; ?>" <?php checked( ! $sent ); ?>>
							<span class="mono"><?php echo esc_html( get_post_meta( $id, '_q_date', true ) . ' ' . get_post_meta( $id, '_q_time', true ) ); ?></span>
							<span class="tag"><?php echo esc_html( get_post_meta( $id, '_q_band', true ) ); ?></span>
							<span><?php echo esc_html( get_post_meta( $id, '_q_mode', true ) ); ?></span>
							<?php if ( $sent ) : ?><span class="muted"><?php esc_html_e( '(card already sent)', 'hamshack' ); ?></span><?php endif; ?>
						</label>
					<?php endforeach; ?>
					</div>
				</fieldset>
				<fieldset class="oqrs-routes">
					<legend><?php esc_html_e( 'How should the card travel?', 'hamshack' ); ?></legend>
					<?php foreach ( hamshack_qsl_routes() as $k => $label ) : ?>
						<label><input type="radio" name="o_route" value="<?php echo esc_attr( $k ); ?>" <?php checked( 'bureau', $k ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<div class="form-row">
					<label><?php esc_html_e( 'Name', 'hamshack' ); ?><input type="text" name="o_name" autocomplete="name"></label>
					<label><?php esc_html_e( 'Email (for a confirmation)', 'hamshack' ); ?><input type="email" name="o_email" autocomplete="email"></label>
				</div>
				<label class="oqrs-addr"><?php esc_html_e( 'Postal address (only for direct cards)', 'hamshack' ); ?><textarea name="o_addr" rows="3" autocomplete="street-address"></textarea></label>
				<label><?php esc_html_e( 'Message', 'hamshack' ); ?><textarea name="o_msg" rows="2"></textarea></label>
				<p class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
				<p class="app-note"><?php esc_html_e( 'Your address is used only to post the card and is deleted once the card is sent.', 'hamshack' ); ?></p>
				<button type="submit" class="btn btn-primary"><?php hamshack_the_icon( 'send', 18 ); ?> <?php esc_html_e( 'Request QSL card', 'hamshack' ); ?></button>
			</form>
		<?php endif; ?>
	</details>
	<?php
	return ob_get_clean();
}

/* ---------- admin ---------- */

add_filter( 'manage_hamshack_qslreq_posts_columns', function () {
	return array(
		'cb'       => '<input type="checkbox">',
		'title'    => __( 'Request', 'hamshack' ),
		'r_qsos'   => __( 'QSOs', 'hamshack' ),
		'r_status' => __( 'Status', 'hamshack' ),
		'date'     => __( 'Received', 'hamshack' ),
	);
} );
add_action( 'manage_hamshack_qslreq_posts_custom_column', function ( $col, $id ) {
	if ( 'r_qsos' === $col ) {
		foreach ( (array) get_post_meta( $id, '_r_qsos', true ) as $q ) {
			echo esc_html( get_post_meta( $q, '_q_date', true ) . ' ' . get_post_meta( $q, '_q_time', true ) . ' · ' . get_post_meta( $q, '_q_band', true ) . ' ' . get_post_meta( $q, '_q_mode', true ) ) . '<br>';
		}
	}
	if ( 'r_status' === $col ) {
		$sent = 'sent' === get_post_meta( $id, '_r_status', true );
		echo $sent ? '<span style="color:#1a7f37;font-weight:600">✔ ' . esc_html__( 'Sent', 'hamshack' ) . '</span>' : '<strong style="color:#b26a00">' . esc_html__( 'New', 'hamshack' ) . '</strong>';
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'hamshack_qslreq' === $post->post_type && 'sent' !== get_post_meta( $post->ID, '_r_status', true ) ) {
		$actions['hamshack_sent'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_qsl_sent&id=' . $post->ID ), 'hamshack_qsl_sent_' . $post->ID ) ) . '">' . esc_html__( 'Mark card as sent', 'hamshack' ) . '</a>';
	}
	unset( $actions['inline hide-if-no-js'] );
	return $actions;
}, 10, 2 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_qslreq_box', __( 'Request details', 'hamshack' ), function ( $post ) {
		$g = function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_r_' . $k, true );
		};
		$routes = hamshack_qsl_routes();
		echo '<table class="form-table"><tbody>';
		$rows = array(
			__( 'Callsign', 'hamshack' ) => '<strong style="font-family:monospace;font-size:1.3em">' . esc_html( $g( 'call' ) ) . '</strong>',
			__( 'Route', 'hamshack' )    => esc_html( isset( $routes[ $g( 'route' ) ] ) ? $routes[ $g( 'route' ) ] : $g( 'route' ) ),
			__( 'Name', 'hamshack' )     => esc_html( $g( 'name' ) ),
			__( 'Email', 'hamshack' )    => $g( 'email' ) ? '<a href="mailto:' . esc_attr( $g( 'email' ) ) . '">' . esc_html( $g( 'email' ) ) . '</a>' : '–',
			__( 'Address', 'hamshack' )  => nl2br( esc_html( $g( 'addr' ) ) ),
			__( 'Message', 'hamshack' )  => nl2br( esc_html( $g( 'msg' ) ) ),
		);
		foreach ( $rows as $k => $v ) {
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore
		}
		echo '<tr><th>' . esc_html__( 'QSOs', 'hamshack' ) . '</th><td>';
		foreach ( (array) $g( 'qsos' ) as $q ) {
			echo esc_html( get_post_meta( $q, '_q_date', true ) . ' ' . get_post_meta( $q, '_q_time', true ) . ' UTC · ' . get_post_meta( $q, '_q_band', true ) . ' ' . get_post_meta( $q, '_q_mode', true ) . ' · RST ' . get_post_meta( $q, '_q_rst_s', true ) ) . '<br>';
		}
		echo '</td></tr></tbody></table>';
		if ( 'sent' !== $g( 'status' ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_qsl_sent&id=' . $post->ID ), 'hamshack_qsl_sent_' . $post->ID ) ) . '">' . esc_html__( 'Mark card as sent', 'hamshack' ) . '</a> <span class="description">' . esc_html__( 'Sets "QSL sent" on the QSOs, emails the requester and deletes the postal address.', 'hamshack' ) . '</span></p>';
		} else {
			echo '<p><strong>✔ ' . esc_html__( 'Card sent', 'hamshack' ) . '</strong></p>';
		}
	}, 'hamshack_qslreq', 'normal', 'high' );
} );

add_action( 'admin_post_hamshack_qsl_sent', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $id || 'hamshack_qslreq' !== get_post_type( $id ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_qsl_sent_' . $id );
	foreach ( (array) get_post_meta( $id, '_r_qsos', true ) as $q ) {
		update_post_meta( $q, '_q_qsl_s', 'Y' );
	}
	update_post_meta( $id, '_r_status', 'sent' );
	delete_post_meta( $id, '_r_addr' );
	$email = get_post_meta( $id, '_r_email', true );
	if ( $email && is_email( $email ) ) {
		$call = get_post_meta( $id, '_r_call', true );
		wp_mail(
			$email,
			sprintf( '[%s] Your QSL card is on its way', hamshack_opt( 'callsign' ) ),
			sprintf( "Hello %s,\n\nmy QSL card for our QSO is on its way to you. Thanks for the contact!\n\n73 de %s\n%s", $call, hamshack_opt( 'callsign' ), home_url( '/' ) )
		);
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=hamshack_qslreq&m6sent=1' ) );
	exit;
} );

/* Count of open requests in the admin menu. */
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['hamshack'] ) ) {
		return;
	}
	$open = get_posts( array( 'post_type' => 'hamshack_qslreq', 'posts_per_page' => 50, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_r_status', 'meta_value' => 'new' ) ); // phpcs:ignore
	if ( ! $open ) {
		return;
	}
	foreach ( $submenu['hamshack'] as &$item ) {
		if ( 'edit.php?post_type=hamshack_qslreq' === $item[2] ) {
			$item[0] .= ' <span class="awaiting-mod">' . count( $open ) . '</span>';
		}
	}
}, 99 );
