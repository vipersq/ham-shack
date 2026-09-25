<?php
/**
 * Sidebar / footer widgets.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small base class: title-only settings.
 */
abstract class HamShack_Simple_Widget extends WP_Widget {
	protected $default_title = '';

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : $this->default_title;
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title:', 'hamshack' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
		if ( method_exists( $this, 'extra_form' ) ) {
			$this->extra_form( $instance );
		}
	}

	public function update( $new, $old ) {
		$new['title'] = sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' );
		if ( isset( $new['count'] ) ) {
			$new['count'] = max( 1, min( 20, (int) $new['count'] ) );
		}
		return $new;
	}

	protected function open( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore
		$title = isset( $instance['title'] ) ? $instance['title'] : $this->default_title;
		if ( $title ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ) . $args['after_title']; // phpcs:ignore
		}
	}

	protected function close( $args ) {
		echo $args['after_widget']; // phpcs:ignore
	}

	protected function count_field( $instance, $default = 5 ) {
		$count = isset( $instance['count'] ) ? (int) $instance['count'] : $default;
		printf(
			'<p><label for="%1$s">%2$s</label> <input id="%1$s" name="%3$s" type="number" min="1" max="20" value="%4$d" style="width:5em"></p>',
			esc_attr( $this->get_field_id( 'count' ) ),
			esc_html__( 'How many:', 'hamshack' ),
			esc_attr( $this->get_field_name( 'count' ) ),
			(int) $count
		);
	}
}

class HamShack_Status_Widget extends HamShack_Simple_Widget {
	protected $default_title = 'Station status';
	public function __construct() {
		parent::__construct( 'hamshack_status', __( 'Ham Shack: Station status', 'hamshack' ), array( 'description' => __( 'On-air status, frequency, locator and live UTC clock.', 'hamshack' ) ) );
	}
	public function widget( $args, $instance ) {
		$this->open( $args, $instance );
		get_template_part( 'template-parts/status-card' );
		$this->close( $args );
	}
}

class HamShack_Events_Widget extends HamShack_Simple_Widget {
	protected $default_title = 'Upcoming on air';
	public function __construct() {
		parent::__construct( 'hamshack_events', __( 'Ham Shack: Upcoming events', 'hamshack' ), array( 'description' => __( 'Next contests, nets and activations from the calendar.', 'hamshack' ) ) );
	}
	public function extra_form( $instance ) {
		$this->count_field( $instance, 4 );
	}
	public function widget( $args, $instance ) {
		$this->open( $args, $instance );
		$occ = hamshack_upcoming_events( isset( $instance['count'] ) ? (int) $instance['count'] : 4 );
		echo $occ ? hamshack_event_list_html( $occ, true ) : '<p class="muted">' . esc_html__( 'No upcoming events.', 'hamshack' ) . '</p>'; // phpcs:ignore
		$cal = get_page_by_path( 'calendar' );
		echo '<p class="widget-more"><a href="' . esc_url( $cal ? get_permalink( $cal ) : get_post_type_archive_link( 'hamshack_event' ) ) . '">' . esc_html__( 'Full calendar', 'hamshack' ) . ' →</a></p>';
		$this->close( $args );
	}
}

class HamShack_QSO_Widget extends HamShack_Simple_Widget {
	protected $default_title = 'Latest QSOs';
	public function __construct() {
		parent::__construct( 'hamshack_qsos', __( 'Ham Shack: Latest QSOs', 'hamshack' ), array( 'description' => __( 'Most recent contacts from the logbook.', 'hamshack' ) ) );
	}
	public function extra_form( $instance ) {
		$this->count_field( $instance, 5 );
	}
	public function widget( $args, $instance ) {
		$ids = hamshack_recent_qsos( isset( $instance['count'] ) ? (int) $instance['count'] : 5 );
		if ( ! $ids ) {
			return;
		}
		$this->open( $args, $instance );
		echo '<ul class="mini-log">';
		foreach ( $ids as $id ) {
			echo '<li><span class="mono call">' . esc_html( get_post_meta( $id, '_q_call', true ) ) . '</span><span class="tag">' . esc_html( get_post_meta( $id, '_q_band', true ) ) . '</span><span class="muted">' . esc_html( get_post_meta( $id, '_q_mode', true ) ) . '</span><span class="muted mono">' . esc_html( get_post_meta( $id, '_q_date', true ) ) . '</span></li>';
		}
		echo '</ul>';
		$this->close( $args );
	}
}

class HamShack_Solar_Widget extends HamShack_Simple_Widget {
	protected $default_title = 'Band conditions';
	public function __construct() {
		parent::__construct( 'hamshack_solar', __( 'Ham Shack: N0NBH band conditions', 'hamshack' ), array( 'description' => __( 'Live N0NBH solar-terrestrial banner (hamqsl.com) – choose any of the N0NBH banners.', 'hamshack' ) ) );
	}
	public function extra_form( $instance ) {
		$current = isset( $instance['banner'] ) ? $instance['banner'] : '';
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'banner' ) ) . '">' . esc_html__( 'N0NBH banner:', 'hamshack' ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( 'banner' ) ) . '" name="' . esc_attr( $this->get_field_name( 'banner' ) ) . '">';
		echo '<option value="">' . esc_html__( '— as in Station settings —', 'hamshack' ) . '</option>';
		foreach ( hamshack_solar_banners() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $current, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
	}
	public function update( $new, $old ) {
		$new           = parent::update( $new, $old );
		$new['banner'] = ! empty( $new['banner'] ) ? hamshack_solar_key( sanitize_key( $new['banner'] ) ) : '';
		return $new;
	}
	public function widget( $args, $instance ) {
		$this->open( $args, $instance );
		get_template_part( 'template-parts/solar', null, array( 'banner' => ! empty( $instance['banner'] ) ? $instance['banner'] : hamshack_opt( 'solar_banner', 'solarn0nbh' ) ) );
		$this->close( $args );
	}
}

add_action( 'widgets_init', function () {
	register_widget( 'HamShack_Status_Widget' );
	register_widget( 'HamShack_Events_Widget' );
	register_widget( 'HamShack_QSO_Widget' );
	register_widget( 'HamShack_Solar_Widget' );
} );
