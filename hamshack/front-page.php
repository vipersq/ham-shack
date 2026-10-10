<?php
/**
 * Home page.
 *
 * @package HamShack
 */

get_header();

$blog_id  = (int) get_option( 'page_for_posts' );
$blog_url = $blog_id ? get_permalink( $blog_id ) : home_url( '/' );
$cal_page = get_page_by_path( 'calendar' );
$log_page = get_page_by_path( 'logbook' );
$stn_page = get_page_by_path( 'station' );
$con_page = get_page_by_path( 'contact' );
$map_page = get_page_by_path( 'map' );
$live_page = get_page_by_path( 'live' );
$awd_page = get_page_by_path( 'awards' );
?>

<section class="hero">
	<div class="hero-bg" style="background-image:url(<?php echo hamshack_img( 'hero-scene.svg' ); // phpcs:ignore ?>)" aria-hidden="true"></div>
	<div class="container hero-inner">
		<div class="hero-copy">
			<p class="eyebrow mono"><span class="blink">●</span> <?php echo esc_html( hamshack_join( array( hamshack_opt( 'callsign' ), hamshack_opt( 'locator' ), hamshack_opt( 'qth' ) ) ) ); ?></p>
			<h2 class="hero-title"><?php echo esc_html( hamshack_opt( 'hero_title' ) ); ?></h2>
			<p class="hero-text"><?php echo esc_html( hamshack_opt( 'hero_text' ) ); ?></p>
			<div class="hero-actions">
				<a class="btn btn-primary" href="<?php echo esc_url( $blog_url ); ?>"><?php hamshack_the_icon( 'book', 18 ); ?> <?php esc_html_e( 'Read the blog', 'hamshack' ); ?></a>
				<?php if ( $log_page ) : ?>
					<a class="btn btn-ghost" href="<?php echo esc_url( get_permalink( $log_page ) ); ?>"><?php hamshack_the_icon( 'search', 18 ); ?> <?php esc_html_e( 'Are you in my log?', 'hamshack' ); ?></a>
				<?php endif; ?>
			</div>
			<ul class="hero-chips">
				<?php foreach ( array_slice( (array) hamshack_opt( 'bands', array() ), 0, 8 ) as $b ) : ?>
					<li class="tag mono"><?php echo esc_html( $b ); ?></li>
				<?php endforeach; ?>
				<?php foreach ( array_slice( (array) hamshack_opt( 'modes', array() ), 0, 6 ) as $m ) : ?>
					<li class="tag tag-alt mono"><?php echo esc_html( $m ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="hero-art">
			<img class="rig" src="<?php echo hamshack_img( 'rig.svg' ); // phpcs:ignore ?>" alt="<?php esc_attr_e( 'Illustration of an HF transceiver showing 14.074 MHz', 'hamshack' ); ?>" width="820" height="460">
		</div>
	</div>
</section>

<section class="section section-status">
	<div class="container status-strip">
		<?php get_template_part( 'template-parts/status-card' ); ?>
		<div class="quick-links">
			<?php
			$tiles = array(
				array( $cal_page, 'calendar', __( 'Calendar', 'hamshack' ), __( 'Contests, nets & activations', 'hamshack' ) ),
				array( $log_page, 'book', __( 'Logbook', 'hamshack' ), __( 'Search your callsign', 'hamshack' ) ),
				array( $stn_page, 'antenna', __( 'Station', 'hamshack' ), __( 'Rigs, antennas & QSL', 'hamshack' ) ),
				array( $live_page, 'live', __( 'Live', 'hamshack' ), __( 'FT8, DX cluster & greyline', 'hamshack' ) ),
				array( $map_page, 'map', __( 'QSO map', 'hamshack' ), __( 'Everyone I have worked', 'hamshack' ) ),
				array( $con_page, 'mail', __( 'Contact', 'hamshack' ), __( 'Skeds & QSL requests', 'hamshack' ) ),
			);
			foreach ( $tiles as $t ) :
				if ( ! $t[0] ) {
					continue;
				}
				?>
				<a class="tile" href="<?php echo esc_url( get_permalink( $t[0] ) ); ?>">
					<span class="tile-icon"><?php hamshack_the_icon( $t[1], 24 ); ?></span>
					<span class="tile-title"><?php echo esc_html( $t[2] ); ?></span>
					<span class="tile-sub"><?php echo esc_html( $t[3] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php do_action( 'hamshack_home_top' ); ?>

<?php
$latest = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 6, 'ignore_sticky_posts' => false ) );
if ( $latest->have_posts() ) :
	?>
	<section class="section">
		<div class="container">
			<header class="section-head">
				<div>
					<p class="eyebrow mono"><?php esc_html_e( 'From the shack', 'hamshack' ); ?></p>
					<h2 class="section-title"><?php esc_html_e( 'Latest posts', 'hamshack' ); ?></h2>
				</div>
				<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'All posts', 'hamshack' ); ?> <?php hamshack_the_icon( 'arrow-r', 16 ); ?></a>
			</header>
			<div class="grid grid-3 posts-grid">
				<?php
				while ( $latest->have_posts() ) :
					$latest->the_post();
					get_template_part( 'template-parts/content' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section section-alt">
	<div class="container grid grid-2 cols-events">
		<div>
			<header class="section-head">
				<div>
					<p class="eyebrow mono"><?php esc_html_e( 'On the air', 'hamshack' ); ?></p>
					<h2 class="section-title"><?php esc_html_e( 'Upcoming events', 'hamshack' ); ?></h2>
				</div>
				<?php if ( $cal_page ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $cal_page ) ); ?>"><?php esc_html_e( 'Calendar', 'hamshack' ); ?> <?php hamshack_the_icon( 'arrow-r', 16 ); ?></a>
				<?php endif; ?>
			</header>
			<?php
			$occ = hamshack_upcoming_events( 5 );
			echo $occ ? hamshack_event_list_html( $occ ) : '<p class="muted">' . esc_html__( 'No events scheduled right now.', 'hamshack' ) . '</p>'; // phpcs:ignore
			?>
		</div>
		<div class="panel mini-cal">
			<?php echo hamshack_calendar_html( array( 'nav' => '0', 'agenda' => '0' ) ); // phpcs:ignore ?>
		</div>
	</div>
</section>

<?php
$qsos = hamshack_opt( 'show_logbook', 1 ) ? hamshack_recent_qsos( 6 ) : array();
$show_solar = (int) hamshack_opt( 'show_solar', 1 );
if ( $qsos || $show_solar ) :
	?>
	<section class="section">
		<div class="container grid <?php echo ( $qsos && $show_solar ) ? 'grid-log' : ''; ?>">
			<?php if ( $qsos ) : ?>
				<div>
					<header class="section-head">
						<div>
							<p class="eyebrow mono"><?php esc_html_e( 'Logbook', 'hamshack' ); ?></p>
							<h2 class="section-title"><?php esc_html_e( 'Recent QSOs', 'hamshack' ); ?></h2>
						</div>
						<?php if ( $log_page ) : ?>
							<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $log_page ) ); ?>"><?php esc_html_e( 'Full log', 'hamshack' ); ?> <?php hamshack_the_icon( 'arrow-r', 16 ); ?></a>
						<?php endif; ?>
					</header>
					<?php echo hamshack_log_stats_html(); // phpcs:ignore ?>
					<?php echo hamshack_qso_table( $qsos ); // phpcs:ignore ?>
				</div>
			<?php endif; ?>
			<?php if ( $show_solar ) : ?>
				<aside class="panel solar-panel<?php echo $qsos ? '' : ' is-solo'; ?>">
					<h2 class="widget-title"><?php hamshack_the_icon( 'sun', 18 ); ?> <?php esc_html_e( 'Band conditions', 'hamshack' ); ?></h2>
					<?php get_template_part( 'template-parts/solar', null, array( 'banner' => hamshack_opt( 'solar_home', 'solarn0nbh' ) ) ); ?>
				</aside>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( hamshack_opt( 'show_live', 1 ) ) : ?>
<section class="section section-alt section-live">
	<div class="container">
		<header class="section-head">
			<div>
				<p class="eyebrow mono"><?php esc_html_e( 'Live', 'hamshack' ); ?></p>
				<h2 class="section-title"><?php esc_html_e( 'The world from the shack', 'hamshack' ); ?></h2>
			</div>
			<div class="head-actions">
				<?php if ( $map_page ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $map_page ) ); ?>"><?php hamshack_the_icon( 'map', 16 ); ?> <?php esc_html_e( 'QSO map', 'hamshack' ); ?></a>
				<?php endif; ?>
				<?php if ( $awd_page ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $awd_page ) ); ?>"><?php hamshack_the_icon( 'trophy', 16 ); ?> <?php esc_html_e( 'Awards', 'hamshack' ); ?></a>
				<?php endif; ?>
				<?php if ( $live_page ) : ?>
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $live_page ) ); ?>"><?php esc_html_e( 'Live page', 'hamshack' ); ?> <?php hamshack_the_icon( 'arrow-r', 16 ); ?></a>
				<?php endif; ?>
			</div>
		</header>
		<div class="live-home">
			<div><?php echo do_shortcode( '[hamshack_greyline]' ); // phpcs:ignore ?></div>
			<div class="panel live-home-side">
				<?php echo do_shortcode( '[hamshack_ft8 map="0" rows="9"]' ); // phpcs:ignore ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section-station">
	<div class="container station-teaser">
		<div class="station-art" aria-hidden="true">
			<img class="ht" src="<?php echo hamshack_img( 'handheld.svg' ); // phpcs:ignore ?>" alt="" width="220" height="420" loading="lazy">
			<img class="key" src="<?php echo hamshack_img( 'morse-key.svg' ); // phpcs:ignore ?>" alt="" width="420" height="220" loading="lazy">
		</div>
		<div class="station-copy">
			<p class="eyebrow mono"><?php esc_html_e( 'The station', 'hamshack' ); ?></p>
			<h2 class="section-title"><?php esc_html_e( 'Behind the signal', 'hamshack' ); ?></h2>
			<ul class="kit-list">
				<?php foreach ( hamshack_lines( hamshack_opt( 'rigs' ) ) as $line ) : ?>
					<li><?php hamshack_the_icon( 'radio', 18 ); ?> <?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
				<?php foreach ( hamshack_lines( hamshack_opt( 'antennas' ) ) as $line ) : ?>
					<li><?php hamshack_the_icon( 'antenna', 18 ); ?> <?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
			<div class="hero-actions">
				<?php if ( $stn_page ) : ?>
					<a class="btn btn-primary" href="<?php echo esc_url( get_permalink( $stn_page ) ); ?>"><?php esc_html_e( 'Station & QSL info', 'hamshack' ); ?></a>
				<?php endif; ?>
				<?php if ( $con_page ) : ?>
					<a class="btn btn-ghost" href="<?php echo esc_url( get_permalink( $con_page ) ); ?>"><?php esc_html_e( 'Arrange a sked', 'hamshack' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<?php
// Anything written into the Home page itself appears here.
while ( is_page() && have_posts() ) :
	the_post();
	if ( trim( get_the_content() ) ) :
		?>
		<section class="section">
			<div class="container narrow entry-content"><?php the_content(); ?></div>
		</section>
		<?php
	endif;
endwhile;

get_footer();
