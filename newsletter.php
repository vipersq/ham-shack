<?php
/**
 * Newsletter: double opt-in subscribers who get an e-mail whenever a new post is published.
 *
 * - [hamshack_subscribe] shortcode, a widget and an automatic box under every post
 * - confirmation / unsubscribe links with a personal token
 * - sending in small batches through WP-Cron (shared hosting friendly)
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function hamshack_news_opts() {
	return wp_parse_args(
		(array) get_option( 'hamshack_news', array() ),
		array(
			'enabled'  => 1,   // e-mail subscribers when a post is published.
			'box'      => 1,   // subscribe box under posts.
			'delay'    => 10,  // minutes between publishing and sending (time to fix typos).
			'batch'    => 25,  // e-mails per run (every 2 minutes).
		)
	);
}

add_action( 'init', function () {
	register_post_type(
		'hamshack_sub',
		array(
			'labels'          => array(
				'name'          => __( 'Subscribers', 'hamshack' ),
				'singular_name' => __( 'Subscriber', 'hamshack' ),
				'all_items'     => __( 'Subscribers', 'hamshack' ),
				'menu_name'     => __( 'Subscribers', 'hamshack' ),
				'not_found'     => __( 'No subscribers yet', 'hamshack' ),
				'search_items'  => __( 'Search subscribers', 'hamshack' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false,
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
 * Find a subscriber by e-mail or token.
 */
function hamshack_sub_find( $field, $value ) {
	$args = array( 'post_type' => 'hamshack_sub', 'post_status' => 'private', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true );
	if ( 'email' === $field ) {
		$args['title'] = $value;
	} else {
		$args['meta_key']   = '_s_token'; // phpcs:ignore
		$args['meta_value'] = $value; // phpcs:ignore
	}
	$ids = get_posts( $args );
	return $ids ? (int) $ids[0] : 0;
}

function hamshack_sub_link( $action, $token ) {
	return add_query_arg( array( 'hamshack_sub' => $action, 't' => $token ), home_url( '/' ) );
}

/* ---------- subscribe form handling ---------- */

add_action( 'template_redirect', function () {
	// Confirm / unsubscribe links.
	if ( isset( $_GET['hamshack_sub'], $_GET['t'] ) ) { // phpcs:ignore
		$action = sanitize_key( $_GET['hamshack_sub'] ); // phpcs:ignore
		$token  = preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['t'] ) ); // phpcs:ignore
		$id     = $token ? hamshack_sub_find( 'token', $token ) : 0;
		$res    = 'invalid';
		if ( $id && 'confirm' === $action ) {
			update_post_meta( $id, '_s_status', 'active' );
			update_post_meta( $id, '_s_confirmed', time() );
			$res = 'confirmed';
		} elseif ( $id && 'unsub' === $action ) {
			wp_delete_post( $id, true ); // Nothing kept after unsubscribing.
			$res = 'unsubscribed';
		} elseif ( 'unsub' === $action ) {
			$res = 'unsubscribed';
		}
		wp_safe_redirect( add_query_arg( 'm6sub', $res, home_url( '/' ) ) );
		exit;
	}
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['hamshack_subscribe'] ) ) { // phpcs:ignore
		return;
	}
	$back = hamshack_back_url();
	$back = remove_query_arg( 'm6sub', $back );
	$go   = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'm6sub', $code, $back ) . '#subscribe' );
		exit;
	};
	if ( ! isset( $_POST['_m6nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_m6nonce'] ), 'hamshack_subscribe' ) ) {
		$go( 'expired' );
	}
	$ts = isset( $_POST['_m6t'] ) ? (int) $_POST['_m6t'] : 0;
	if ( ! empty( $_POST['website'] ) || ! $ts || time() - $ts < 2 ) {
		$go( 'spam' );
	}
	$email = isset( $_POST['s_email'] ) ? sanitize_email( wp_unslash( $_POST['s_email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		$go( 'email' );
	}
	if ( empty( $_POST['s_ok'] ) ) {
		$go( 'consent' );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'hamshack_s_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		$go( 'rate' );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	$email = strtolower( $email );
	$id    = hamshack_sub_find( 'email', $email );
	if ( $id && 'active' === get_post_meta( $id, '_s_status', true ) ) {
		$go( 'check' ); // Same answer as for a new address – do not reveal who is subscribed.
	}
	if ( ! $id ) {
		$id = wp_insert_post( array( 'post_type' => 'hamshack_sub', 'post_status' => 'private', 'post_title' => $email ) );
		if ( ! $id || is_wp_error( $id ) ) {
			$go( 'error' );
		}
		update_post_meta( $id, '_s_token', wp_generate_password( 32, false, false ) );
		update_post_meta( $id, '_s_status', 'pending' );
		update_post_meta( $id, '_s_created', time() );
	}
	$call = isset( $_POST['s_call'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9\/]/', '', sanitize_text_field( wp_unslash( $_POST['s_call'] ) ) ) ) : '';
	if ( $call ) {
		update_post_meta( $id, '_s_call', $call );
	}
	$token = get_post_meta( $id, '_s_token', true );
	$site  = hamshack_opt( 'callsign' );
	$body  = hamshack_mail_template(
		sprintf( __( 'Confirm your subscription to %s', 'hamshack' ), $site ),
		'<p>' . esc_html__( 'Hello! Please confirm that you would like an e-mail whenever a new post appears on the blog. If you did not ask for this, just ignore this message.', 'hamshack' ) . '</p>',
		hamshack_sub_link( 'confirm', $token ),
		__( 'Yes, subscribe me', 'hamshack' ),
		''
	);
	$ok    = wp_mail( $email, sprintf( '[%s] %s', $site, __( 'Please confirm your subscription', 'hamshack' ) ), $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	$go( $ok ? 'check' : 'error' );
} );

/**
 * Status messages shown after a redirect.
 */
function hamshack_sub_messages() {
	return array(
		'check'        => array( 'ok', __( 'Almost there – check your inbox and click the confirmation link.', 'hamshack' ) ),
		'confirmed'    => array( 'ok', __( 'Thank you! Your subscription is confirmed – you will hear about new posts. 73!', 'hamshack' ) ),
		'unsubscribed' => array( 'ok', __( 'You have been unsubscribed and your address deleted. 73!', 'hamshack' ) ),
		'already'      => array( 'ok', __( 'You are already subscribed – thanks!', 'hamshack' ) ),
		'email'        => array( 'err', __( 'Please enter a valid e-mail address.', 'hamshack' ) ),
		'consent'      => array( 'err', __( 'Please tick the box to agree to receive e-mails.', 'hamshack' ) ),
		'expired'      => array( 'err', __( 'The form expired – please try again.', 'hamshack' ) ),
		'spam'         => array( 'err', __( 'That looked automated – please try again.', 'hamshack' ) ),
		'rate'         => array( 'err', __( 'Too many attempts – please try again later.', 'hamshack' ) ),
		'invalid'      => array( 'err', __( 'This link is no longer valid.', 'hamshack' ) ),
		'error'        => array( 'err', __( 'The e-mail could not be sent. Please try again later.', 'hamshack' ) ),
	);
}

/* Toast for confirm/unsubscribe results on any page. */
add_action( 'wp_footer', function () {
	if ( empty( $_GET['m6sub'] ) ) { // phpcs:ignore
		return;
	}
	$code = sanitize_key( $_GET['m6sub'] ); // phpcs:ignore
	$msgs = hamshack_sub_messages();
	if ( ! isset( $msgs[ $code ] ) || ! in_array( $code, array( 'confirmed', 'unsubscribed', 'invalid' ), true ) ) {
		return;
	}
	echo '<div class="hs-toast is-' . esc_attr( $msgs[ $code ][0] ) . '" role="status">' . esc_html( $msgs[ $code ][1] ) . '<button type="button" onclick="this.parentNode.remove()" aria-label="' . esc_attr__( 'Close', 'hamshack' ) . '">×</button></div>';
} );

/**
 * The subscribe form.
 */
function hamshack_subscribe_form( $compact = false ) {
	$msgs = hamshack_sub_messages();
	$code = isset( $_GET['m6sub'] ) ? sanitize_key( $_GET['m6sub'] ) : ''; // phpcs:ignore
	ob_start();
	?>
	<form id="subscribe" class="hs-sub<?php echo $compact ? ' is-compact' : ''; ?>" method="post" action="">
		<?php if ( ! $compact ) : ?>
			<div class="hs-sub-copy">
				<span class="hs-sub-icon"><?php hamshack_the_icon( 'bell', 22 ); ?></span>
				<div>
					<strong><?php esc_html_e( 'Get new posts by e-mail', 'hamshack' ); ?></strong>
					<p><?php esc_html_e( 'One short e-mail when something new goes up on the blog. No spam, unsubscribe with one click.', 'hamshack' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $code && isset( $msgs[ $code ] ) && ! in_array( $code, array( 'confirmed', 'unsubscribed', 'invalid' ), true ) ) : ?>
			<p class="notice notice-<?php echo 'ok' === $msgs[ $code ][0] ? 'ok' : 'err'; ?>" role="status"><?php echo esc_html( $msgs[ $code ][1] ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="hamshack_subscribe" value="1">
		<input type="hidden" name="_m6t" value="<?php echo (int) time(); ?>">
		<?php wp_nonce_field( 'hamshack_subscribe', '_m6nonce' ); ?>
		<div class="hs-sub-row">
			<label class="screen-reader-text" for="hs-sub-email"><?php esc_html_e( 'E-mail', 'hamshack' ); ?></label>
			<input id="hs-sub-email" type="email" name="s_email" required autocomplete="email" placeholder="<?php esc_attr_e( 'your@email', 'hamshack' ); ?>">
			<?php if ( ! $compact ) : ?>
				<label class="screen-reader-text" for="hs-sub-call"><?php esc_html_e( 'Callsign (optional)', 'hamshack' ); ?></label>
				<input id="hs-sub-call" type="text" name="s_call" class="mono" autocomplete="off" placeholder="<?php esc_attr_e( 'Callsign (optional)', 'hamshack' ); ?>">
			<?php endif; ?>
			<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Subscribe', 'hamshack' ); ?></button>
		</div>
		<label class="hs-sub-ok"><input type="checkbox" name="s_ok" value="1" required> <?php esc_html_e( 'I agree to receive e-mails about new posts. My address is used for nothing else and is deleted when I unsubscribe.', 'hamshack' ); ?></label>
		<p class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'hamshack_subscribe', function () {
	return hamshack_subscribe_form();
} );

/* Box under every post. */
add_filter( 'the_content', function ( $content ) {
	if ( is_singular( 'post' ) && in_the_loop() && is_main_query() && hamshack_news_opts()['box'] ) {
		$content .= hamshack_subscribe_form();
	}
	return $content;
}, 30 );

/* Widget. */
add_action( 'widgets_init', function () {
	register_widget( 'HAMSHACK_Subscribe_Widget' );
} );
class HAMSHACK_Subscribe_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'hamshack_subscribe', __( 'Ham Shack: Newsletter', 'hamshack' ), array( 'description' => __( 'E-mail sign-up for new posts.', 'hamshack' ) ) );
	}
	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore
		echo $args['before_title'] . esc_html( ! empty( $instance['title'] ) ? $instance['title'] : __( 'New posts by e-mail', 'hamshack' ) ) . $args['after_title']; // phpcs:ignore
		echo hamshack_subscribe_form( true ); // phpcs:ignore
		echo $args['after_widget']; // phpcs:ignore
	}
	public function form( $instance ) {
		$t = isset( $instance['title'] ) ? $instance['title'] : '';
		echo '<p><label>' . esc_html__( 'Title', 'hamshack' ) . ' <input class="widefat" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" value="' . esc_attr( $t ) . '"></label></p>';
	}
	public function update( $new, $old ) {
		return array( 'title' => sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' ) );
	}
}

/* ---------- sending ---------- */

/**
 * Dark, simple HTML e-mail.
 */
function hamshack_mail_template( $title, $html, $button_url = '', $button_label = '', $footer = '', $image = '' ) {
	$call = esc_html( hamshack_opt( 'callsign' ) );
	$out  = '<!doctype html><html><body style="margin:0;padding:0;background:#0b0f14;">';
	$out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f14;"><tr><td align="center" style="padding:24px 12px;">';
	$out .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#121a23;border:1px solid #223040;border-radius:14px;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#d6e2ee;">';
	$out .= '<tr><td style="padding:20px 26px;border-bottom:1px solid #223040;font-family:Consolas,Menlo,monospace;font-size:22px;font-weight:800;color:#f1f6fb;letter-spacing:2px;">' . $call . '<span style="font-size:12px;color:#8aa0b4;font-weight:400;letter-spacing:1px;"> · ' . esc_html( hamshack_opt( 'qth' ) ) . '</span></td></tr>';
	if ( $image ) {
		$out .= '<tr><td><img src="' . esc_url( $image ) . '" width="600" style="display:block;width:100%;height:auto;border:0;" alt=""></td></tr>';
	}
	$out .= '<tr><td style="padding:24px 26px 8px;"><h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#f1f6fb;">' . esc_html( $title ) . '</h1><div style="font-size:15px;line-height:1.6;color:#c4d3e1;">' . $html . '</div></td></tr>';
	if ( $button_url ) {
		$out .= '<tr><td style="padding:10px 26px 26px;"><a href="' . esc_url( $button_url ) . '" style="display:inline-block;background:#ffb020;color:#1a1204;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:9px;">' . esc_html( $button_label ) . '</a></td></tr>';
	}
	$out .= '<tr><td style="padding:16px 26px;border-top:1px solid #223040;font-size:12px;color:#8aa0b4;">' . ( $footer ? $footer : '73 de ' . $call . ' · <a href="' . esc_url( home_url( '/' ) ) . '" style="color:#22d3ee;">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a>' ) . '</td></tr>';
	$out .= '</table></td></tr></table></body></html>';
	return $out;
}

/* Queue a post when it is first published. */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type || ! hamshack_news_opts()['enabled'] ) {
		return;
	}
	if ( get_post_meta( $post->ID, '_m6_mailed', true ) || strtotime( $post->post_date_gmt . ' UTC' ) < time() - DAY_IN_SECONDS ) {
		return; // Already sent, or an old post being re-published / imported.
	}
	update_post_meta( $post->ID, '_m6_mailed', 'queued' );
	$queue   = (array) get_option( 'hamshack_mail_queue', array() );
	$queue[] = array( 'post' => $post->ID, 'after' => 0, 'start' => time() + 60 * (int) hamshack_news_opts()['delay'] );
	update_option( 'hamshack_mail_queue', $queue, false );
	if ( ! wp_next_scheduled( 'hamshack_mail_run' ) ) {
		wp_schedule_event( time() + 120, 'hamshack_2min', 'hamshack_mail_run' );
	}
}, 10, 3 );

add_filter( 'cron_schedules', function ( $s ) {
	$s['hamshack_2min'] = array( 'interval' => 120, 'display' => __( 'Every 2 minutes', 'hamshack' ) );
	return $s;
} );

add_action( 'hamshack_mail_run', 'hamshack_mail_run' );
function hamshack_mail_run() {
	$queue = (array) get_option( 'hamshack_mail_queue', array() );
	if ( ! $queue ) {
		wp_clear_scheduled_hook( 'hamshack_mail_run' );
		return;
	}
	$job  = $queue[0];
	$post = get_post( $job['post'] );
	if ( ! $post || 'publish' !== $post->post_status || get_post_meta( $post->ID, '_m6_no_mail', true ) ) {
		array_shift( $queue );
		update_option( 'hamshack_mail_queue', $queue, false );
		if ( $post ) {
			update_post_meta( $post->ID, '_m6_mailed', 'skipped' );
		}
		return;
	}
	if ( time() < (int) $job['start'] ) {
		return;
	}
	$subs = get_posts(
		array(
			'post_type'      => 'hamshack_sub',
			'post_status'    => 'private',
			'posts_per_page' => (int) hamshack_news_opts()['batch'],
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => '_s_status', // phpcs:ignore
			'meta_value'     => 'active', // phpcs:ignore
			'fields'         => 'ids',
			'm6_after'       => (int) $job['after'],
			'suppress_filters' => false,
		)
	);
	$sent = hamshack_mail_post_to( $post, $subs );
	if ( count( $subs ) < (int) hamshack_news_opts()['batch'] ) {
		array_shift( $queue );
		$total = (int) get_post_meta( $post->ID, '_m6_mailed_count', true ) + $sent;
		update_post_meta( $post->ID, '_m6_mailed', 'sent' );
		update_post_meta( $post->ID, '_m6_mailed_count', $total );
		update_post_meta( $post->ID, '_m6_mailed_time', time() );
	} else {
		$queue[0]['after'] = (int) end( $subs );
		update_post_meta( $post->ID, '_m6_mailed_count', (int) get_post_meta( $post->ID, '_m6_mailed_count', true ) + $sent );
	}
	update_option( 'hamshack_mail_queue', $queue, false );
}

/* Only subscribers with an ID above the last batch. */
add_filter( 'posts_where', function ( $where, $q ) {
	$after = (int) $q->get( 'm6_after' );
	if ( $after ) {
		global $wpdb;
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after );
	}
	return $where;
}, 10, 2 );

/**
 * Send one post to a list of subscriber IDs. Returns the number sent.
 */
function hamshack_mail_post_to( $post, array $sub_ids ) {
	$title   = get_the_title( $post );
	$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 55 );
	$image   = get_the_post_thumbnail_url( $post, 'large' );
	$url     = get_permalink( $post );
	$n       = 0;
	foreach ( $sub_ids as $sid ) {
		$email = get_post_field( 'post_title', $sid );
		$unsub = hamshack_sub_link( 'unsub', get_post_meta( $sid, '_s_token', true ) );
		$body  = hamshack_mail_template(
			$title,
			'<p>' . esc_html( $excerpt ) . '</p>',
			$url,
			__( 'Read the post', 'hamshack' ),
			sprintf(
				/* translators: 1: callsign, 2: unsubscribe link */
				__( 'You get this because you subscribed to new posts from %1$s. %2$s', 'hamshack' ),
				esc_html( hamshack_opt( 'callsign' ) ),
				'<a href="' . esc_url( $unsub ) . '" style="color:#22d3ee;">' . esc_html__( 'Unsubscribe', 'hamshack' ) . '</a>'
			),
			$image
		);
		$ok = wp_mail(
			$email,
			sprintf( '[%s] %s', hamshack_opt( 'callsign' ), wp_specialchars_decode( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES ) ),
			$body,
			array( 'Content-Type: text/html; charset=UTF-8', 'List-Unsubscribe: <' . esc_url_raw( $unsub ) . '>' )
		);
		if ( $ok ) {
			$n++;
		}
	}
	return $n;
}

/* "Don't e-mail this post" box in the editor. */
add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'hamshack_news_box', __( 'Newsletter', 'hamshack' ), function ( $post ) {
		wp_nonce_field( 'hamshack_news_post', 'hamshack_news_nonce' );
		$state = get_post_meta( $post->ID, '_m6_mailed', true );
		if ( 'sent' === $state ) {
			echo '<p>✔ ' . esc_html( sprintf( __( 'Sent to %d subscribers.', 'hamshack' ), (int) get_post_meta( $post->ID, '_m6_mailed_count', true ) ) ) . '</p>';
			return;
		}
		echo '<label><input type="checkbox" name="m6_no_mail" value="1" ' . checked( (bool) get_post_meta( $post->ID, '_m6_no_mail', true ), true, false ) . '> ' . esc_html__( 'Do not e-mail this post to subscribers', 'hamshack' ) . '</label>';
		if ( 'queued' === $state ) {
			echo '<p class="description">' . esc_html__( 'Queued – it goes out a few minutes after publishing.', 'hamshack' ) . '</p>';
		}
	}, 'post', 'side', 'low' );
} );
add_action( 'save_post_post', function ( $post_id ) {
	if ( ! isset( $_POST['hamshack_news_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hamshack_news_nonce'] ), 'hamshack_news_post' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_m6_no_mail', empty( $_POST['m6_no_mail'] ) ? '' : '1' );
} );

/* ---------- admin page ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'hamshack', __( 'Newsletter', 'hamshack' ), __( 'Newsletter', 'hamshack' ), 'manage_options', 'hamshack-news', 'hamshack_news_page' );
}, 30 );

function hamshack_news_page() {
	$o      = hamshack_news_opts();
	$counts = array( 'active' => 0, 'pending' => 0 );
	foreach ( array_keys( $counts ) as $st ) {
		$counts[ $st ] = count( get_posts( array( 'post_type' => 'hamshack_sub', 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_s_status', 'meta_value' => $st ) ) ); // phpcs:ignore
	}
	$queue = (array) get_option( 'hamshack_mail_queue', array() );
	$subs  = get_posts( array( 'post_type' => 'hamshack_sub', 'post_status' => 'private', 'posts_per_page' => 200 ) );
	?>
	<div class="wrap hamshack-admin">
		<h1><?php esc_html_e( 'Newsletter', 'hamshack' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'hamshack' ); ?></p></div><?php endif; ?>
		<?php if ( isset( $_GET['test'] ) ) : // phpcs:ignore ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test e-mail sent to your admin address.', 'hamshack' ); ?></p></div><?php endif; ?>
		<div class="hamshack-card">
			<h2><?php hamshack_the_icon( 'bell' ); ?> <?php esc_html_e( 'Subscribers', 'hamshack' ); ?></h2>
			<p style="font-size:1.2em"><strong><?php echo (int) $counts['active']; ?></strong> <?php esc_html_e( 'confirmed', 'hamshack' ); ?> · <?php echo (int) $counts['pending']; ?> <?php esc_html_e( 'waiting for confirmation', 'hamshack' ); ?></p>
			<?php if ( $queue ) : ?>
				<p><?php printf( esc_html__( 'Sending in progress: %d post(s) in the queue.', 'hamshack' ), count( $queue ) ); ?></p>
			<?php endif; ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_news_csv' ), 'hamshack_news_csv' ) ); ?>"><?php esc_html_e( 'Export CSV', 'hamshack' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hamshack_news_test' ), 'hamshack_news_test' ) ); ?>"><?php esc_html_e( 'Send me a test e-mail (latest post)', 'hamshack' ); ?></a>
			</p>
			<?php if ( $subs ) : ?>
				<table class="widefat striped" style="max-width:760px"><thead><tr><th><?php esc_html_e( 'E-mail', 'hamshack' ); ?></th><th><?php esc_html_e( 'Callsign', 'hamshack' ); ?></th><th><?php esc_html_e( 'Status', 'hamshack' ); ?></th><th><?php esc_html_e( 'Since', 'hamshack' ); ?></th><th></th></tr></thead><tbody>
				<?php foreach ( $subs as $s ) : ?>
					<tr>
						<td><?php echo esc_html( $s->post_title ); ?></td>
						<td><code><?php echo esc_html( get_post_meta( $s->ID, '_s_call', true ) ); ?></code></td>
						<td><?php echo 'active' === get_post_meta( $s->ID, '_s_status', true ) ? '✔ ' . esc_html__( 'confirmed', 'hamshack' ) : esc_html__( 'pending', 'hamshack' ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) get_post_meta( $s->ID, '_s_created', true ) ) ); ?></td>
						<td><a href="<?php echo esc_url( get_delete_post_link( $s->ID, '', true ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this subscriber?', 'hamshack' ) ); ?>')"><?php esc_html_e( 'Delete', 'hamshack' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<form class="hamshack-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hamshack_news_save' ); ?>
			<input type="hidden" name="action" value="hamshack_news_save">
			<h2><?php hamshack_the_icon( 'mail' ); ?> <?php esc_html_e( 'Settings', 'hamshack' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Automatic e-mails', 'hamshack' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $o['enabled'] ); ?>> <?php esc_html_e( 'E-mail subscribers when I publish a new post', 'hamshack' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Delay', 'hamshack' ); ?></th><td><input type="number" min="0" max="240" name="delay" value="<?php echo (int) $o['delay']; ?>" style="width:6em"> <?php esc_html_e( 'minutes after publishing (time to fix typos)', 'hamshack' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Batch size', 'hamshack' ); ?></th><td><input type="number" min="5" max="200" name="batch" value="<?php echo (int) $o['batch']; ?>" style="width:6em"> <?php esc_html_e( 'e-mails every 2 minutes (keep it low on shared hosting)', 'hamshack' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Sign-up box', 'hamshack' ); ?></th><td><label><input type="checkbox" name="box" value="1" <?php checked( $o['box'] ); ?>> <?php esc_html_e( 'Show the sign-up box under every post', 'hamshack' ); ?></label>
				<p class="description"><?php esc_html_e( 'You can also use the shortcode [hamshack_subscribe] or the “Ham Shack: Newsletter” widget. For reliable delivery install an SMTP plugin (e.g. WP Mail SMTP) and send through your mailbox.', 'hamshack' ); ?></p></td></tr>
			</table>
			<p><button class="button button-primary"><?php esc_html_e( 'Save', 'hamshack' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_hamshack_news_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_news_save' );
	update_option(
		'hamshack_news',
		array(
			'enabled' => empty( $_POST['enabled'] ) ? 0 : 1,
			'box'     => empty( $_POST['box'] ) ? 0 : 1,
			'delay'   => isset( $_POST['delay'] ) ? max( 0, min( 240, (int) $_POST['delay'] ) ) : 10,
			'batch'   => isset( $_POST['batch'] ) ? max( 5, min( 200, (int) $_POST['batch'] ) ) : 25,
		)
	);
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-news&saved=1' ) );
	exit;
} );

add_action( 'admin_post_hamshack_news_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_news_test' );
	$latest = get_posts( array( 'posts_per_page' => 1 ) );
	if ( $latest ) {
		$title = get_the_title( $latest[0] );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%s] %s (test)', hamshack_opt( 'callsign' ), $title ),
			hamshack_mail_template( $title, '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( $latest[0]->post_content ), 55 ) ) . '</p>', get_permalink( $latest[0] ), __( 'Read the post', 'hamshack' ), '', get_the_post_thumbnail_url( $latest[0], 'large' ) ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}
	wp_safe_redirect( admin_url( 'admin.php?page=hamshack-news&test=1' ) );
	exit;
} );

add_action( 'admin_post_hamshack_news_csv', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hamshack' ) );
	}
	check_admin_referer( 'hamshack_news_csv' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="subscribers-' . gmdate( 'Ymd' ) . '.csv"' );
	echo hamshack_subscribers_csv(); // phpcs:ignore
	exit;
} );

function hamshack_subscribers_csv() {
	$out = "email,callsign,status,created\n";
	foreach ( get_posts( array( 'post_type' => 'hamshack_sub', 'post_status' => 'private', 'posts_per_page' => -1 ) ) as $s ) {
		$out .= sprintf( "%s,%s,%s,%s\n", $s->post_title, get_post_meta( $s->ID, '_s_call', true ), get_post_meta( $s->ID, '_s_status', true ), gmdate( 'Y-m-d', (int) get_post_meta( $s->ID, '_s_created', true ) ) );
	}
	return $out;
}
