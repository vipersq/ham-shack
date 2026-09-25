<?php
/**
 * Ham Shack theme bootstrap.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HAMSHACK_VERSION', '1.0.0' );
define( 'HAMSHACK_DIR', get_template_directory() );
define( 'HAMSHACK_URI', get_template_directory_uri() );

require HAMSHACK_DIR . '/inc/helpers.php';
require HAMSHACK_DIR . '/inc/brand.php';
require HAMSHACK_DIR . '/inc/icons.php';
require HAMSHACK_DIR . '/inc/setup.php';
require HAMSHACK_DIR . '/inc/station.php';
require HAMSHACK_DIR . '/inc/events.php';
require HAMSHACK_DIR . '/inc/logbook.php';
require HAMSHACK_DIR . '/inc/qrz.php';
require HAMSHACK_DIR . '/inc/rig.php';
require HAMSHACK_DIR . '/inc/badge.php';
require HAMSHACK_DIR . '/inc/contact.php';
require HAMSHACK_DIR . '/inc/widgets.php';
require HAMSHACK_DIR . '/inc/dashboard.php';
require HAMSHACK_DIR . '/inc/activation.php';
