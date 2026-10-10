<?php
/**
 * Contact form shortcode: [hamshack_contact]
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'hamshack_contact_handle' );
function hamshack_contact_handle() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['hamshack_contact'] ) ) { // phpcs:ignore
		return;
	}
	$back = hamshack_back_url();
	$back = remove_query_arg( array( 'sent', 'cerr' ), $back );
	$fail = function ( $code ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'cerr', $code, $back ) . '#contact-form' );
		exit;
	};
	if ( is_user_logged_in() && ( ! isset( $_POST['_m6nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_m6nonce'] ), 'hamshack_contact' ) ) ) {
		$fail( 'expired' );
	}
	// Honeypot + minimum fill time (bots).
	if ( ! empty( $_POST['website'] ) ) {
		$fail( 'spam' );
	}
	$ts = hamshack_form_stamp_check( isset( $_POST['_m6t'] ) ? sanitize_text_field( wp_unslash( $_POST['_m6t'] ) ) : '' );
	if ( ! $ts || time() - $ts < 4 ) {
		$fail( 'spam' );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'hamshack_c_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		$fail( 'rate' );
	}
	$name    = isset( $_POST['c_name'] ) ? sanitize_text_field( wp_unslash( $_POST['c_name'] ) ) : '';
	$email   = isset( $_POST['c_email'] ) ? sanitize_email( wp_unslash( $_POST['c_email'] ) ) : '';
	$call    = isset( $_POST['c_call'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['c_call'] ) ) ) : '';
	$subject = isset( $_POST['c_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['c_subject'] ) ) : '';
	$message = isset( $_POST['c_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['c_message'] ) ) : '';
	if ( ! $name || ! is_email( $email ) || strlen( $message ) < 5 ) {
		$fail( 'fields' );
	}
	$to   = hamshack_opt( 'email' ) ? hamshack_opt( 'email' ) : get_option( 'admin_email' );
	$subj = sprintf( '[%s] %s', hamshack_opt( 'callsign' ), $subject ? $subject : __( 'New message from the website', 'hamshack' ) );
	$body = sprintf( "Name: %s\nCallsign: %s\nEmail: %s\n\n%s\n\n-- \nSent from %s", $name, $call ? $call : '-', $email, $message, home_url( '/' ) );
	$ok   = wp_mail( $to, $subj, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	if ( ! $ok ) {
		$fail( 'mail' );
	}
	wp_safe_redirect( add_query_arg( 'sent', '1', $back ) . '#contact-form' );
	exit;
}

add_shortcode( 'hamshack_contact', function () {
	$errors = array(
		'expired' => __( 'The form expired – please try again.', 'hamshack' ),
		'spam'    => __( 'Your message looked automated. Please try again.', 'hamshack' ),
		'rate'    => __( 'Too many messages – please try again later.', 'hamshack' ),
		'fields'  => __( 'Please fill in your name, a valid email and a message.', 'hamshack' ),
		'mail'    => __( 'The message could not be sent. Please try again later.', 'hamshack' ),
	);
	ob_start();
	?>
	<form id="contact-form" class="contact-form panel" method="post" action="">
		<?php if ( isset( $_GET['sent'] ) ) : // phpcs:ignore ?>
			<p class="notice notice-ok" role="status"><?php esc_html_e( 'Thanks – your message is on its way. 73!', 'hamshack' ); ?></p>
		<?php elseif ( isset( $_GET['cerr'] ) && isset( $errors[ sanitize_key( $_GET['cerr'] ) ] ) ) : // phpcs:ignore ?>
			<p class="notice notice-err" role="alert"><?php echo esc_html( $errors[ sanitize_key( $_GET['cerr'] ) ] ); // phpcs:ignore ?></p>
		<?php endif; ?>
		<input type="hidden" name="hamshack_contact" value="1">
		<input type="hidden" name="_m6t" value="<?php echo esc_attr( hamshack_form_stamp() ); ?>">
		<?php wp_nonce_field( 'hamshack_contact', '_m6nonce' ); ?>
		<div class="form-row">
			<label><?php esc_html_e( 'Name', 'hamshack' ); ?> <span class="req">*</span><input type="text" name="c_name" required autocomplete="name"></label>
			<label><?php esc_html_e( 'Callsign', 'hamshack' ); ?><input type="text" name="c_call" class="mono" autocomplete="off" placeholder="<?php esc_attr_e( 'optional', 'hamshack' ); ?>"></label>
		</div>
		<div class="form-row">
			<label><?php esc_html_e( 'Email', 'hamshack' ); ?> <span class="req">*</span><input type="email" name="c_email" required autocomplete="email"></label>
			<label><?php esc_html_e( 'Subject', 'hamshack' ); ?><input type="text" name="c_subject" placeholder="<?php esc_attr_e( 'Sked request, QSL, question…', 'hamshack' ); ?>"></label>
		</div>
		<label><?php esc_html_e( 'Message', 'hamshack' ); ?> <span class="req">*</span><textarea name="c_message" rows="6" required></textarea></label>
		<p class="hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
		<button type="submit" class="btn btn-primary"><?php hamshack_the_icon( 'mail', 18 ); ?> <?php esc_html_e( 'Send message', 'hamshack' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
} );
