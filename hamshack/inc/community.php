<?php
/**
 * Community & on-the-road features: QSL card wall, sked requests,
 * local repeaters, web SDR and APRS position.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * QSL wall – pictures of received cards attached to QSOs
 * ======================================================================== */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'hamshack_qsl_imgs', __( 'QSL card received (pictures)', 'hamshack' ), function ( $post ) {
		wp_nonce_field( 'hamshack_qsl_imgs', 'hamshack_qsl_imgs_nonce' );
		foreach ( array( 'front' => __( 'Front', 'hamshack' ), 'back' => __( 'Back', 'hamshack' ) ) as $k => $label ) {
			$id  = (int) get_post_meta( $post->ID, '_q_qsl_' . $k, true );
			$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
			echo '<div class="hs-qsl-pick" style="margin-bottom:10px"><strong>' . esc_html( $label ) . '</strong><br>';
			echo '<img src="' . esc_url( $src ) . '" style="max-width:100%;' . ( $src ? '' : 'display:none;' ) . 'border-radius:6px;margin:4px 0" alt="">';
			echo '<input type="hidden" name="m6_qsl_' . esc_attr( $k ) . '" value="' . (int) $id . '">';
			echo '<button type="button" class="button hs-pick">' . esc_html__( 'Choose picture', 'hamshack' ) . '</button> <button type="button" class="button-link hs-clear">' . esc_html__( 'Remove', 'hamshack' ) . '</button></div>';
		}
		echo '<p class="description">' . esc_html__( 'Scan or photograph the card. It appears in the public log and on the QSL wall, and the QSO is marked as confirmed.', 'hamshack' ) . '</p>';
	}, 'hamshack_qso', 'side', 'default' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'hamshack_qso' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script(
		'media-editor',
		"jQuery(function($){ $(document).on('click','.hs-pick',function(e){ e.preventDefault(); var box=$(this).closest('.hs-qsl-pick'); var f=wp.media({title:'QSL',library:{type:'image'},multiple:false}); f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); box.find('input').val(a.id); box.find('img').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show(); }); f.open(); }); $(document).on('click','.hs-clear',function(e){ e.preventDefault(); var box=$(this).closest('.hs-qsl-pick'); box.find('input').val(''); box.find('img').hide(); }); });"
	);
} );

add_action( 'save_post_hamshack_qso', function ( $post_id ) {
	if ( ! isset( $_POST['hamshack_qsl_imgs_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_qsl_imgs_nonce'] ), 'hamshack_qsl_imgs' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( 'front', 'back' ) as $k ) {
		$id = isset( $_POST[ 'm6_qsl_' . $k ] ) ? (int) $_POST[ 'm6_qsl_' . $k ] : 0;
		if ( $id && wp_attachment_is_image( $id ) ) {
			update_post_meta( $post_id, '_q_qsl_' . $k, $id );
		} else {
			delete_post_meta( $post_id, '_q_qsl_' . $k );
		}
	}
	if ( get_post_meta( $post_id, '_q_qsl_front', true ) ) {
		update_post_meta( $post_id, '_q_qsl_r', 'Y' );
	}
}, 30 );

/**
 * Small camera link for a QSO with a card picture (used in the log table).
 */
function hamshack_qsl_thumb_link( $qso_id ) {
	$f = (int) get_post_meta( $qso_id, '_q_qsl_front', true );
	if ( ! $f ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $f, 'large' );
	return ' <a class="qsl-pic" href="' . esc_url( $url ) . '" data-lightbox="qsl" data-caption="' . esc_attr( get_post_meta( $qso_id, '_q_call', true ) . ' – QSL' ) . '" title="' . esc_attr__( 'See the QSL card', 'hamshack' ) . '">' . wp_get_attachment_image( $f, 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ) . '</a>';
}

add_shortcode( 'hamshack_qsl_wall', function () {
	$ids = get_posts( array( 'post_type' => 'hamshack_qso', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_key' => '_q_qsl_front', 'meta_compare' => 'EXISTS', 'orderby' => 'modified', 'order' => 'DESC' ) ); // phpcs:ignore
	if ( ! $ids ) {
		$msg = current_user_can( 'edit_posts' ) ? __( 'No QSL cards yet. Open a QSO in the logbook and add a picture of the card in “QSL card received”.', 'hamshack' ) : __( 'The first QSL cards are on their way!', 'hamshack' );
		return '<div class="empty-state">' . esc_html( $msg ) . '</div>';
	}
	ob_start();
	echo '<div class="hs-app qsl-wall" data-hs-gallery>';
	foreach ( $ids as $id ) {
		$f = (int) get_post_meta( $id, '_q_qsl_front', true );
		$b = (int) get_post_meta( $id, '_q_qsl_back', true );
		$c = get_post_meta( $id, '_q_call', true );
		echo '<figure class="qsl-item' . ( $b ? ' has-back' : '' ) . '">';
		echo '<a class="qsl-flip" href="' . esc_url( wp_get_attachment_image_url( $f, 'large' ) ) . '" data-lightbox="qsl" data-caption="' . esc_attr( $c . ' · ' . get_post_meta( $id, '_q_country', true ) ) . '">';
		echo '<span class="qsl-face qsl-front">' . wp_get_attachment_image( $f, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => esc_attr( $c . ' QSL' ) ) ) . '</span>';
		if ( $b ) {
			echo '<span class="qsl-face qsl-back">' . wp_get_attachment_image( $b, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => '' ) ) . '</span>';
		}
		echo '</a>';
		if ( $b ) {
			echo '<a class="screen-reader-text" href="' . esc_url( wp_get_attachment_image_url( $b, 'large' ) ) . '" data-lightbox="qsl" data-caption="' . esc_attr( $c . ' – back' ) . '">' . esc_html__( 'Back of the card', 'hamshack' ) . '</a>';
		}
		echo '<figcaption><b class="mono">' . esc_html( $c ) . '</b> <span>' . esc_html( get_post_meta( $id, '_q_country', true ) ) . '</span> <span class="mono muted">' . esc_html( get_post_meta( $id, '_q_date', true ) . ' · ' . get_post_meta( $id, '_q_band', true ) . ' ' . get_post_meta( $id, '_q_mode', true ) ) . '</span></figcaption>';
		echo '</figure>';
	}
	echo '</div>';
	return ob_get_clean();
} );

/* ==========================================================================
 * Sked requests
 * ======================================================================== */

add_action( 'init', function () {
	register_post_type(
		'hamshack_sked',
		array(
			'labels'       => array(
				'name'          => __( 'Sked requests', 'hamshack' ),
				'singular_name' => __( 'Sked request', 'hamshack' ),
				'menu_name'     => __( 'Sked requests', 'hamshack' ),
				'all_items'     => __( 'Sked requests', 'hamshack' ),
				'edit_item'     => __( 'Sked request', 'hamshack' ),
				'not_found'     => __( 'No sked requests yet', 'hamshack' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'hamshack',
			'supports'     => array( 'title' ),
			'capabilities' => array(
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
			'map_meta_cap' => false,
		)
	);
} );

add_action( 'template_redirect', function () {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['hamshack_sked'] ) ) { // phpcs:ignore
		return;
	}
	$back = remove_query_arg( array( 'sked', 'skerr' ), hamshack_back_url() );
	$fail = function ( $c ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'skerr', $c, $back ) . '#sked' );
		exit;
	};
	// A nonce only means something for logged-in users; visitors get a signed time stamp instead (works with page caching).
	if ( is_user_logged_in() && ( ! isset( $_POST['_m6nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_m6nonce'] ), 'hamshack_sked' ) ) ) {
		$fail( 'expired' );
	}
	$stamp = isset( $_POST['_m6t'] ) ? sanitize_text_field( wp_unslash( $_POST['_m6t'] ) ) : '';
	$ts    = hamshack_form_stamp_check( $stamp );
	if ( ! empty( $_POST['website'] ) || ! $ts || time() - $ts < 3 ) {
		$fail( 'spam' );
	}
	if ( time() - $ts > 7 * DAY_IN_SECONDS ) {
		$fail( 'expired' );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'hamshack_sk_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 4 || (int) get_transient( 'hamshack_sk_all' ) >= 20 ) {
		$fail( 'rate' );
	}
	$f = function ( $k, $max = 100 ) {
		return isset( $_POST[ $k ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ), 0, $max ) : ''; // phpcs:ignore
	};
	$call  = strtoupper( substr( preg_replace( '/[^A-Za-z0-9\/]/', '', $f( 'k_call' ) ), 0, 16 ) );
	$email = sanitize_email( $f( 'k_email' ) );
	$date  = $f( 'k_date' );
	$time  = $f( 'k_time' );
	$when  = ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $dm ) && checkdate( (int) $dm[2], (int) $dm[3], (int) $dm[1] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) ? strtotime( $date . ' ' . $time . ' UTC' ) : 0;
	if ( strlen( $call ) < 3 || ! is_email( $email ) ) {
		$fail( 'fields' );
	}
	if ( ! $when || $when < time() + HOUR_IN_SECONDS || $when > time() + 365 * DAY_IN_SECONDS ) {
		$fail( 'when' );
	}
	$band = in_array( $f( 'k_band' ), hamshack_all_bands(), true ) ? $f( 'k_band' ) : '';
	$mode = in_array( $f( 'k_mode' ), hamshack_all_modes(), true ) ? $f( 'k_mode' ) : '';
	$id   = wp_insert_post( array( 'post_type' => 'hamshack_sked', 'post_status' => 'publish', 'post_title' => sprintf( '%s – %s UTC', $call, gmdate( 'D j M Y H:i', $when ) ) ) );
	if ( ! $id || is_wp_error( $id ) ) {
		$fail( 'error' );
	}
	$meta = array( 'call' => $call, 'name' => $f( 'k_name', 60 ), 'email' => $email, 'when' => $when, 'band' => $band, 'mode' => $mode, 'freq' => substr( preg_replace( '/[^0-9.]/', '', $f( 'k_freq' ) ), 0, 12 ), 'msg' => isset( $_POST['k_msg'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['k_msg'] ) ), 0, 1000 ) : '', 'status' => 'new' );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, '_k_' . $k, $v );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	set_transient( 'hamshack_sk_all', (int) get_transient( 'hamshack_sk_all' ) + 1, HOUR_IN_SECONDS );
	$to   = hamshack_opt( 'email' ) ? hamshack_opt( 'email' ) : get_option( 'admin_email' );
	$text = sprintf( "%s (%s) asks for a sked:\n%s UTC · %s %s %s\n\n%s\n\nAccept or decline: %s", $call, $meta['name'] ? $meta['name'] : '-', gmdate( 'D j M Y H:i', $when ), $band, $mode, $meta['freq'] ? $meta['freq'] . ' MHz' : '', $meta['msg'], admin_url( 'edit.php?post_type=hamshack_sked' ) );
	wp_mail( $to, sprintf( '[%s] Sked request: %s', hamshack_opt( 'callsign' ), $call ), $text, array( 'Reply-To: <' . $email . '>' ) );
	if ( function_exists( 'hamshack_notify_later' ) ) {
		hamshack_notify_later( 'sked', sprintf( __( 'Sked request from %s', 'hamshack' ), $call ), gmdate( 'D j M H:i', $when ) . ' UTC · ' . trim( $band . ' ' . $mode ), admin_url( 'edit.php?post_type=hamshack_sked' ) );
	}
	wp_safe_redirect( add_query_arg( 'sked', '1', $back ) . '#sked' );
	exit;
} );

add_shortcode( 'hamshack_sked', function () {
	$errors = array(
		'expired' => __( 'The form expired – please try again.', 'hamshack' ),
		'spam'    => __( 'That looked automated – please try again.', 'hamshack' ),
		'rate'    => __( 'Too many requests – please try again later.', 'hamshack' ),
		'fields'  => __( 'Please enter your callsign and a valid e-mail address.', 'hamshack' ),
		'when'    => __( 'Please pick a date and time (UTC) at least an hour from now.', 'hamshack' ),
		'error'   => __( 'Something went wrong. Please try again later.', 'hamshack' ),
	);
	ob_start();
	?>
	<form id="sked" class="hs-app sked-form panel contact-form" method="post" action="">
		<?php if ( isset( $_GET['sked'] ) ) : // phpcs:ignore ?>
			<p class="notice notice-ok" role="status"><?php esc_html_e( 'Thanks! I will check the date and confirm by e-mail. 73!', 'hamshack' ); ?></p>
		<?php elseif ( isset( $_GET['skerr'] ) && isset( $errors[ sanitize_key( $_GET['skerr'] ) ] ) ) : // phpcs:ignore ?>
			<p class="notice notice-err" role="alert"><?php echo esc_html( $errors[ sanitize_key( $_GET['skerr'] ) ] ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<input type="hidden" name="hamshack_sked" value="1">
		<input type="hidden" name="_m6t" value="<?php echo esc_attr( hamshack_form_stamp() ); ?>">
		<?php wp_nonce_field( 'hamshack_sked', '_m6nonce' ); ?>
		<div class="form-row">
			<label><?php esc_html_e( 'Your callsign', 'hamshack' ); ?> <span class="req">*</span><input type="text" name="k_call" class="mono" required autocomplete="off"></label>
			<label><?php esc_html_e( 'Name', 'hamshack' ); ?><input type="text" name="k_name" autocomplete="name"></label>
		</div>
		<div class="form-row">
			<label><?php esc_html_e( 'Date', 'hamshack' ); ?> <span class="req">*</span><input type="date" name="k_date" required min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></label>
			<label><?php esc_html_e( 'Time (UTC)', 'hamshack' ); ?> <span class="req">*</span><input type="time" name="k_time" required step="900"></label>
		</div>
		<div class="form-row form-row-3">
			<label><?php esc_html_e( 'Band', 'hamshack' ); ?><select name="k_band"><option value=""><?php esc_html_e( 'Any', 'hamshack' ); ?></option><?php foreach ( (array) hamshack_opt( 'bands' ) as $b ) : ?><option><?php echo esc_html( $b ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Mode', 'hamshack' ); ?><select name="k_mode"><option value=""><?php esc_html_e( 'Any', 'hamshack' ); ?></option><?php foreach ( (array) hamshack_opt( 'modes' ) as $m ) : ?><option><?php echo esc_html( $m ); ?></option><?php endforeach; ?></select></label>
			<label><?php esc_html_e( 'Frequency (MHz)', 'hamshack' ); ?><input type="text" name="k_freq" inputmode="decimal" placeholder="14.250"></label>
		</div>
		<label><?php esc_html_e( 'E-mail (for the confirmation)', 'hamshack' ); ?> <span class="req">*</span><input type="email" name="k_email" required autocomplete="email"></label>
		<label><?php esc_html_e( 'Message', 'hamshack' ); ?><textarea name="k_msg" rows="3" placeholder="<?php esc_attr_e( 'Your QTH, antenna, power… anything useful', 'hamshack' ); ?>"></textarea></label>
		<p class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
		<p class="app-note"><?php printf( esc_html__( 'All times in UTC – it is %s UTC now. Your e-mail is only used to confirm the sked.', 'hamshack' ), '<b class="mono">' . esc_html( gmdate( 'H:i' ) ) . '</b>' ); // phpcs:ignore ?></p>
		<button type="submit" class="btn btn-primary"><?php hamshack_the_icon( 'calendar', 18 ); ?> <?php esc_html_e( 'Request a sked', 'hamshack' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
} );

/* Admin list + accept / decline. */
add_filter( 'manage_hamshack_sked_posts_columns', function () {
	return array( 'cb' => '<input type="checkbox">', 'title' => __( 'Sked', 'hamshack' ), 'k_what' => __( 'Band / mode', 'hamshack' ), 'k_status' => __( 'Status', 'hamshack' ), 'date' => __( 'Received', 'hamshack' ) );
} );
add_action( 'manage_hamshack_sked_posts_custom_column', function ( $col, $id ) {
	if ( 'k_what' === $col ) {
		echo esc_html( trim( get_post_meta( $id, '_k_band', true ) . ' ' . get_post_meta( $id, '_k_mode', true ) . ' ' . get_post_meta( $id, '_k_freq', true ) ) );
		$msg = get_post_meta( $id, '_k_msg', true );
		if ( $msg ) {
			echo '<br><small>' . esc_html( wp_trim_words( $msg, 20 ) ) . '</small>';
		}
	}
	if ( 'k_status' === $col ) {
		$st = get_post_meta( $id, '_k_status', true );
		echo 'accepted' === $st ? '<span style="color:#1a7f37;font-weight:600">✔ ' . esc_html__( 'Accepted', 'hamshack' ) . '</span>' : '<strong style="color:#b26a00">' . esc_html__( 'New', 'hamshack' ) . '</strong>';
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'hamshack_sked' === $post->post_type && 'accepted' !== get_post_meta( $post->ID, '_k_status', true ) ) {
		foreach ( array( 'accept' => __( 'Accept', 'hamshack' ), 'decline' => __( 'Decline', 'hamshack' ) ) as $k => $l ) {
			$actions[ 'm6_' . $k ] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_sked_' . $k . '&id=' . $post->ID ), 'hamshack_sked_' . $post->ID ) ) . '">' . esc_html( $l ) . '</a>';
		}
	}
	if ( 'hamshack_sked' === $post->post_type ) {
		unset( $actions['inline hide-if-no-js'], $actions['edit'] );
	}
	return $actions;
}, 10, 2 );

foreach ( array( 'accept', 'decline' ) as $m6_sked_action ) {
	add_action( 'admin_post_hamshack_sked_' . $m6_sked_action, function () use ( $m6_sked_action ) {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! $id || 'hamshack_sked' !== get_post_type( $id ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
		}
		check_admin_referer( 'hamshack_sked_' . $id );
		if ( 'accepted' === get_post_meta( $id, '_k_status', true ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=hamshack_sked' ) );
			exit;
		}
		$g     = function ( $k ) use ( $id ) {
			return get_post_meta( $id, '_k_' . $k, true );
		};
		$call  = $g( 'call' );
		$when  = (int) $g( 'when' );
		$my    = hamshack_opt( 'callsign' );
		$email = $g( 'email' );
		if ( 'accept' === $m6_sked_action ) {
			$ev = wp_insert_post( array( 'post_type' => 'hamshack_event', 'post_status' => 'publish', 'post_title' => sprintf( 'Sked: %s', $call ) ) );
			$ev = is_wp_error( $ev ) ? 0 : (int) $ev;
			if ( $ev && ! is_wp_error( $ev ) ) {
				update_post_meta( $ev, '_hs_start', wp_date( 'Y-m-d H:i', $when ) );
				update_post_meta( $ev, '_hs_end', wp_date( 'Y-m-d H:i', $when + 30 * MINUTE_IN_SECONDS ) );
				update_post_meta( $ev, '_hs_repeat', 'none' );
				update_post_meta( $ev, '_hs_band', $g( 'band' ) );
				update_post_meta( $ev, '_hs_mode', $g( 'mode' ) );
				update_post_meta( $ev, '_hs_freq', $g( 'freq' ) );
				$t = term_exists( 'personal', 'hamshack_event_type' );
				if ( $t ) {
					wp_set_object_terms( $ev, array( (int) ( is_array( $t ) ? $t['term_id'] : $t ) ), 'hamshack_event_type' );
				}
				delete_transient( 'hamshack_events_all' );
			}
			update_post_meta( $id, '_k_status', 'accepted' );
			update_post_meta( $id, '_k_event', is_wp_error( $ev ) ? 0 : (int) $ev );
			$gcal = add_query_arg( array( 'action' => 'TEMPLATE', 'text' => rawurlencode( 'Sked ' . $my . ' – ' . $call ), 'dates' => gmdate( 'Ymd\THis\Z', $when ) . '/' . gmdate( 'Ymd\THis\Z', $when + 1800 ) ), 'https://calendar.google.com/calendar/render' );
			wp_mail(
				$email,
				sprintf( '[%s] Sked confirmed: %s UTC', $my, gmdate( 'D j M H:i', $when ) ),
				sprintf( "Hello %s,\n\nsked confirmed: %s UTC%s.\nAdd it to Google Calendar: %s\nCalendar file: %s\n\nSee you on the air – 73 de %s\n%s", $call, gmdate( 'l j F Y, H:i', $when ), ( trim( $g( 'band' ) . ' ' . $g( 'mode' ) . ( $g( 'freq' ) ? ' ' . $g( 'freq' ) . ' MHz' : '' ) ) ? ' · ' . trim( $g( 'band' ) . ' ' . $g( 'mode' ) . ( $g( 'freq' ) ? ' ' . $g( 'freq' ) . ' MHz' : '' ) ) : '' ), $gcal, $ev ? add_query_arg( 'hamshack_ics', $ev, home_url( '/' ) ) : '', $my, home_url( '/' ) )
			);
		} else {
			wp_mail( $email, sprintf( '[%s] Sked request', $my ), sprintf( "Hello %s,\n\nsorry – I can't make %s UTC. Please try another time, or call me when you hear me on the air.\n\n73 de %s", $call, gmdate( 'l j F, H:i', $when ), $my ) );
			wp_delete_post( $id, true ); // Nothing kept.
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=hamshack_sked' ) );
		exit;
	} );
}

/* ==========================================================================
 * Repeaters (ukrepeater.net + your own list)
 * ======================================================================== */

function hamshack_repeater_modes( $codes ) {
	$map = array( 'A' => 'FM', 'D' => 'D-STAR', 'F' => 'C4FM', 'M' => 'DMR', 'N' => 'NXDN', 'P' => 'P25', 'E' => 'Echolink' );
	$out = array();
	foreach ( str_split( strtoupper( (string) $codes ) ) as $c ) {
		$out[] = isset( $map[ $c ] ) ? $map[ $c ] : $c;
	}
	return implode( ' · ', $out );
}

/** Is the station in (or next to) the UK? Then the ukrepeater.net list is used. */
function hamshack_in_uk() {
	list( $la, $lo ) = hamshack_home_latlon();
	$src = (string) hamshack_int( 'repeater_source' );
	if ( 'uk' === $src || 'none' === $src ) {
		return 'uk' === $src;
	}
	return $la && $la >= 49.8 && $la <= 61 && $lo >= -8.7 && $lo <= 2;
}

function hamshack_repeaters() {
	$cached = get_transient( 'hamshack_repeaters' );
	if ( false !== $cached ) {
		return $cached;
	}
	list( $hl, $ho ) = hamshack_home_latlon();
	$radius = (int) hamshack_int( 'repeater_radius' );
	$list   = array();
	$res    = null;
	if ( hamshack_in_uk() ) {
		$res = wp_remote_get( 'https://ukrepeater.net/csvcreate1.php', array( 'timeout' => 20, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION ) );
	}
	if ( $res && ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		foreach ( array_slice( preg_split( '/\r\n|\n/', wp_remote_retrieve_body( $res ) ), 1 ) as $line ) {
			$r = str_getcsv( $line, ',', '"', '\\' );
			if ( count( $r ) < 13 || ! is_numeric( $r[11] ) || ! is_numeric( $r[12] ) ) {
				continue;
			}
			$km = hamshack_distance_km( $hl, $ho, (float) $r[11], (float) $r[12] );
			if ( $km > $radius ) {
				continue;
			}
			$list[] = array(
				'call'  => sanitize_text_field( $r[0] ),
				'band'  => strtolower( sanitize_text_field( $r[1] ) ),
				'out'   => (float) $r[3],
				'in'    => (float) $r[4],
				'modes' => hamshack_repeater_modes( $r[5] ),
				'grid'  => sanitize_text_field( $r[6] ),
				'place' => ucwords( strtolower( sanitize_text_field( $r[7] ) ) ),
				'tone'  => sanitize_text_field( $r[9] ),
				'lat'   => (float) $r[11],
				'lon'   => (float) $r[12],
				'km'    => (int) round( $km ),
			);
		}
		update_option( 'hamshack_repeaters_last', $list, false );
	} else {
		$list = (array) get_option( 'hamshack_repeaters_last', array() );
	}
	foreach ( preg_split( '/\r\n|\n/', (string) hamshack_int( 'repeater_extra' ) ) as $line ) {
		$r = array_map( 'trim', explode( ',', $line ) );
		if ( count( $r ) < 2 || '' === $r[0] ) {
			continue;
		}
		$ll = isset( $r[4] ) ? hamshack_grid_to_latlon( $r[4] ) : null;
		$list[] = array(
			'call'  => sanitize_text_field( $r[0] ),
			'band'  => (float) $r[1] > 400 ? '70cm' : ( (float) $r[1] > 100 ? '2m' : '' ),
			'out'   => (float) $r[1],
			'in'    => isset( $r[2] ) ? (float) $r[2] : 0,
			'modes' => 'FM',
			'grid'  => isset( $r[4] ) ? sanitize_text_field( $r[4] ) : '',
			'place' => isset( $r[5] ) ? sanitize_text_field( $r[5] ) : '',
			'tone'  => isset( $r[3] ) ? sanitize_text_field( $r[3] ) : '',
			'lat'   => $ll ? $ll[0] : null,
			'lon'   => $ll ? $ll[1] : null,
			'km'    => $ll ? (int) round( hamshack_distance_km( $hl, $ho, $ll[0], $ll[1] ) ) : 0,
		);
	}
	usort( $list, function ( $a, $b ) {
		return $a['km'] - $b['km'];
	} );
	set_transient( 'hamshack_repeaters', $list, $list ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
	return $list;
}

add_shortcode( 'hamshack_repeaters', function () {
	$list = hamshack_repeaters();
	hamshack_enqueue_map();
	$pts = array();
	foreach ( $list as $r ) {
		if ( null !== $r['lat'] ) {
			$pts[] = array( 'lat' => $r['lat'], 'lon' => $r['lon'], 'title' => $r['call'], 'color' => '2m' === $r['band'] ? '#e879f9' : ( '70cm' === $r['band'] ? '#c084fc' : '#22d3ee' ), 'lines' => array( $r['place'], number_format( $r['out'], 4 ) . ' MHz · ' . $r['modes'], $r['tone'] ? 'CTCSS / CC ' . $r['tone'] : '', $r['km'] . ' km' ) );
		}
	}
	ob_start();
	?>
	<div class="hs-app rpt-app">
		<div data-hs-points data-items="<?php echo esc_attr( wp_json_encode( $pts ) ); ?>" data-fit="1"><div data-hs-map></div></div>
		<div class="table-scroll tall">
			<table class="data-table rpt-table">
				<thead><tr><th><?php esc_html_e( 'Repeater', 'hamshack' ); ?></th><th><?php esc_html_e( 'Output', 'hamshack' ); ?></th><th><?php esc_html_e( 'Input', 'hamshack' ); ?></th><th><?php esc_html_e( 'Tone / CC', 'hamshack' ); ?></th><th><?php esc_html_e( 'Modes', 'hamshack' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Where', 'hamshack' ); ?></th><th>km</th></tr></thead>
				<tbody>
				<?php foreach ( $list as $r ) : ?>
					<tr><td class="mono call"><?php echo esc_html( $r['call'] ); ?> <span class="tag"><?php echo esc_html( $r['band'] ); ?></span></td><td class="mono"><?php echo esc_html( number_format( $r['out'], 4 ) ); ?></td><td class="mono muted"><?php echo esc_html( $r['in'] ? number_format( $r['in'], 4 ) : '' ); ?></td><td class="mono"><?php echo esc_html( $r['tone'] ); ?></td><td><?php echo esc_html( $r['modes'] ); ?></td><td class="hide-sm"><?php echo esc_html( trim( $r['place'] . ' ' . $r['grid'] ) ); ?></td><td class="mono muted"><?php echo (int) $r['km']; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $list ) : ?>
					<tr><td colspan="7" class="muted"><?php esc_html_e( 'No repeaters found – add your own in Ham Shack → Integrations.', 'hamshack' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php if ( hamshack_in_uk() ) : ?>
		<p class="app-note"><?php printf( esc_html__( 'Repeaters within %1$d km. UK data from %2$s (ETCC) – please report changes there.', 'hamshack' ), (int) hamshack_int( 'repeater_radius' ), '<a href="https://ukrepeater.net/" target="_blank" rel="noopener">ukrepeater.net</a>' ); // phpcs:ignore ?></p>
		<?php else : ?>
		<p class="app-note"><?php printf( esc_html__( 'Repeaters within %d km of my station.', 'hamshack' ), (int) hamshack_int( 'repeater_radius' ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
 * Web SDR
 * ======================================================================== */

add_shortcode( 'hamshack_sdr', function () {
	$url = (string) hamshack_int( 'sdr_url' );
	if ( ! $url ) {
		return '';
	}
	$name = hamshack_int( 'sdr_name' ) ? hamshack_int( 'sdr_name' ) : wp_parse_url( $url, PHP_URL_HOST );
	ob_start();
	?>
	<div class="hs-app sdr-app panel" data-hs-sdr data-src="<?php echo esc_url( $url ); ?>">
		<div class="sdr-head"><?php hamshack_the_icon( 'radio', 22 ); ?> <div><strong><?php echo esc_html( $name ); ?></strong><p class="muted small"><?php esc_html_e( 'Listen to the bands through a web receiver – it opens in your browser, no software needed.', 'hamshack' ); ?></p></div>
			<a class="btn btn-sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in a new window ↗', 'hamshack' ); ?></a>
			<button type="button" class="btn btn-primary btn-sm" data-sdr-load><?php hamshack_the_icon( 'play', 16 ); ?> <?php esc_html_e( 'Listen here', 'hamshack' ); ?></button></div>
		<div class="sdr-frame" data-sdr-frame hidden></div>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
 * APRS (aprs.fi)
 * ======================================================================== */

function hamshack_aprs_position() {
	$key  = (string) hamshack_int( 'aprs_key' );
	$call = (string) hamshack_int( 'aprs_call' );
	if ( ! $key || ! $call ) {
		return null;
	}
	$cached = get_transient( 'hamshack_aprs' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}
	$res = wp_remote_get( add_query_arg( array( 'name' => rawurlencode( $call ), 'what' => 'loc', 'apikey' => rawurlencode( $key ), 'format' => 'json' ), 'https://api.aprs.fi/api/get' ), array( 'timeout' => 10, 'user-agent' => 'HamShack-WP/' . HAMSHACK_VERSION . ' (+' . home_url() . ')' ) );
	$d   = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
	$pos = 0;
	if ( isset( $d['entries'][0]['lat'] ) ) {
		$e   = $d['entries'][0];
		$pos = array(
			'call'    => sanitize_text_field( $e['name'] ),
			'lat'     => (float) $e['lat'],
			'lon'     => (float) $e['lng'],
			'time'    => (int) ( isset( $e['lasttime'] ) ? $e['lasttime'] : $e['time'] ),
			'speed'   => isset( $e['speed'] ) ? (float) $e['speed'] : null,
			'comment' => isset( $e['comment'] ) ? sanitize_text_field( $e['comment'] ) : '',
		);
	}
	set_transient( 'hamshack_aprs', $pos, 5 * MINUTE_IN_SECONDS );
	return $pos ? $pos : null;
}

add_shortcode( 'hamshack_aprs', function ( $atts ) {
	$atts = shortcode_atts( array( 'max_age' => 24 ), $atts, 'hamshack_aprs' );
	$p    = hamshack_aprs_position();
	if ( ! $p || time() - $p['time'] > (int) $atts['max_age'] * HOUR_IN_SECONDS ) {
		return '';
	}
	hamshack_enqueue_map();
	$grid = hamshack_latlon_to_grid( $p['lat'], $p['lon'] );
	$pts  = array( array( 'lat' => $p['lat'], 'lon' => $p['lon'], 'title' => $p['call'], 'color' => '#39ff88', 'r' => 6, 'lines' => array( $grid, sprintf( __( '%s ago', 'hamshack' ), human_time_diff( $p['time'] ) ), $p['comment'] ) ) );
	ob_start();
	?>
	<div class="hs-app aprs-app">
		<p><?php printf( esc_html__( '%1$s was last heard on APRS %2$s ago near %3$s.', 'hamshack' ), '<b class="mono">' . esc_html( $p['call'] ) . '</b>', esc_html( human_time_diff( $p['time'] ) ), '<b class="mono">' . esc_html( $grid ) . '</b>' ); // phpcs:ignore ?> <a href="<?php echo esc_url( 'https://aprs.fi/#!call=' . rawurlencode( $p['call'] ) ); ?>" target="_blank" rel="noopener">aprs.fi ↗</a></p>
		<div data-hs-points data-items="<?php echo esc_attr( wp_json_encode( $pts ) ); ?>" data-zoom="30"><div data-hs-map></div></div>
	</div>
	<?php
	return ob_get_clean();
} );

/* A new QTH means a new repeater list. */
add_action( 'update_option_hamshack_station', function ( $old, $new ) {
	foreach ( array( 'locator', 'lat', 'lon' ) as $k ) {
		if ( ( isset( $old[ $k ] ) ? $old[ $k ] : '' ) !== ( isset( $new[ $k ] ) ? $new[ $k ] : '' ) ) {
			delete_transient( 'hamshack_repeaters' );
			delete_option( 'hamshack_repeaters_last' );
			return;
		}
	}
}, 10, 2 );
