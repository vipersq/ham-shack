<?php
/**
 * Single event.
 *
 * @package HamShack
 */

get_header();

while ( have_posts() ) :
	the_post();
	$o        = hamshack_event_next_occurrence( get_the_ID() );
	$cal_page = get_page_by_path( 'calendar' );
	$cal_url  = $cal_page ? get_permalink( $cal_page ) : get_post_type_archive_link( 'hamshack_event' );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'single-event' ); ?>>
		<header class="page-hero">
			<div class="container narrow">
				<p class="eyebrow mono"><a href="<?php echo esc_url( $cal_url ); ?>">← <?php esc_html_e( 'Calendar', 'hamshack' ); ?></a>
					<?php if ( $o && $o['type_name'] ) : ?> · <span class="chip <?php echo esc_attr( hamshack_type_class( $o['type'] ) ); ?>"><?php echo esc_html( $o['type_name'] ); ?></span><?php endif; ?>
				</p>
				<h1 class="page-title"><?php the_title(); ?></h1>
				<?php if ( $o && ! empty( $o['past'] ) ) : ?>
					<p class="notice"><?php esc_html_e( 'This event has already taken place.', 'hamshack' ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<div class="container narrow">
			<?php if ( $o ) : ?>
				<div class="event-card panel">
					<div class="event-date big" aria-hidden="true">
						<span class="m"><?php echo esc_html( wp_date( 'M', $o['ts'] ) ); ?></span>
						<span class="d"><?php echo esc_html( wp_date( 'j', $o['ts'] ) ); ?></span>
						<span class="w"><?php echo esc_html( wp_date( 'D', $o['ts'] ) ); ?></span>
					</div>
					<dl class="event-facts">
						<div><dt><?php hamshack_the_icon( 'clock', 16 ); ?> <?php esc_html_e( 'When', 'hamshack' ); ?></dt><dd><?php echo esc_html( hamshack_event_when( $o, true ) ); ?>
							<?php
							$rep = array( 'weekly' => __( 'Repeats every week', 'hamshack' ), 'fortnightly' => __( 'Repeats every 2 weeks', 'hamshack' ), 'monthly' => __( 'Repeats every month', 'hamshack' ) );
							if ( isset( $rep[ $o['repeat'] ] ) ) {
								echo '<br><small class="muted">↻ ' . esc_html( $rep[ $o['repeat'] ] ) . ( $o['until'] ? ' ' . esc_html( sprintf( __( 'until %s', 'hamshack' ), wp_date( 'j M Y', strtotime( $o['until'] ) ) ) ) : '' ) . '</small>';
							}
							?>
						</dd></div>
						<?php if ( $o['freq'] || $o['band'] || $o['mode'] ) : ?>
							<div><dt><?php hamshack_the_icon( 'wave', 16 ); ?> <?php esc_html_e( 'Frequency', 'hamshack' ); ?></dt><dd class="mono"><?php echo esc_html( implode( ' · ', array_filter( array( $o['freq'] ? $o['freq'] . ' MHz' : '', $o['band'], $o['mode'] ) ) ) ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $o['location'] ) : ?>
							<div><dt><?php hamshack_the_icon( 'pin', 16 ); ?> <?php esc_html_e( 'Where', 'hamshack' ); ?></dt><dd><?php echo esc_html( $o['location'] ); ?></dd></div>
						<?php endif; ?>
					</dl>
					<div class="event-actions">
						<a class="btn btn-sm" href="<?php echo esc_url( hamshack_gcal_link( $o ) ); ?>" target="_blank" rel="noopener"><?php hamshack_the_icon( 'calendar', 16 ); ?> Google Calendar</a>
						<a class="btn btn-sm btn-ghost" href="<?php echo esc_url( home_url( '/?hamshack_ics=' . get_the_ID() ) ); ?>"><?php hamshack_the_icon( 'download', 16 ); ?> .ics</a>
						<?php $url = get_post_meta( get_the_ID(), '_hs_url', true ); ?>
						<?php if ( $url ) : ?>
							<a class="btn btn-sm btn-ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'More info', 'hamshack' ); ?> <?php hamshack_the_icon( 'external', 14 ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="post-feature"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>

			<div class="entry-content"><?php the_content(); ?></div>

			<?php
			$up = array_filter( hamshack_upcoming_events( 6 ), function ( $x ) {
				return (int) $x['id'] !== get_the_ID();
			} );
			if ( $up ) :
				?>
				<h2 class="section-title small-title"><?php esc_html_e( 'Also coming up', 'hamshack' ); ?></h2>
				<?php echo hamshack_event_list_html( array_slice( $up, 0, 3 ), true ); // phpcs:ignore ?>
			<?php endif; ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
