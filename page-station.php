<?php
/**
 * Station page (used automatically for the page with slug "station").
 *
 * @package HamShack
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<header class="page-hero station-hero">
		<div class="container station-hero-inner">
			<div>
				<?php if ( hamshack_opt( 'licence' ) ) : ?><p class="eyebrow mono"><?php echo esc_html( hamshack_opt( 'licence' ) ); ?></p><?php endif; ?>
				<h1 class="page-title mono callsign-xl"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?></h1>
				<?php if ( hamshack_opt( 'op_name' ) ) : ?>
					<p class="op-name"><?php hamshack_the_icon( 'user', 18 ); ?> <?php echo esc_html( hamshack_opt( 'op_name' ) ); ?></p>
				<?php endif; ?>
				<ul class="station-facts mono">
					<?php echo hamshack_facts( array( __( 'QTH', 'hamshack' ) => hamshack_opt( 'qth' ), __( 'Locator', 'hamshack' ) => hamshack_opt( 'locator' ), __( 'ITU zone', 'hamshack' ) => hamshack_opt( 'itu_zone' ), __( 'CQ zone', 'hamshack' ) => hamshack_opt( 'cq_zone' ) ) ); // phpcs:ignore ?>
				</ul>
			</div>
			<?php get_template_part( 'template-parts/status-card' ); ?>
		</div>
	</header>

	<div class="container">
		<?php if ( trim( get_the_content() ) ) : ?>
			<div class="entry-content narrow-left"><?php the_content(); ?></div>
		<?php endif; ?>

		<div class="grid grid-2 kit-grid">
			<section class="panel kit-panel">
				<img class="kit-art" src="<?php echo hamshack_img( 'rig.svg' ); // phpcs:ignore ?>" alt="" width="820" height="460" loading="lazy">
				<h2><?php hamshack_the_icon( 'radio', 22 ); ?> <?php esc_html_e( 'Radios', 'hamshack' ); ?></h2>
				<ul class="kit-list">
					<?php foreach ( hamshack_lines( hamshack_opt( 'rigs' ) ) as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="panel kit-panel">
				<img class="kit-art" src="<?php echo hamshack_img( 'hero-scene.svg' ); // phpcs:ignore ?>" alt="" width="1600" height="640" loading="lazy">
				<h2><?php hamshack_the_icon( 'antenna', 22 ); ?> <?php esc_html_e( 'Antennas', 'hamshack' ); ?></h2>
				<ul class="kit-list">
					<?php foreach ( hamshack_lines( hamshack_opt( 'antennas' ) ) as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>

		<section class="panel bands-panel">
			<h2><?php hamshack_the_icon( 'signal', 22 ); ?> <?php esc_html_e( 'Bands & modes', 'hamshack' ); ?></h2>
			<div class="band-strip" role="list">
				<?php
				$active = (array) hamshack_opt( 'bands', array() );
				foreach ( hamshack_all_bands() as $b ) :
					?>
					<span role="listitem" class="band <?php echo in_array( $b, $active, true ) ? 'is-on' : ''; ?>"><?php echo esc_html( $b ); ?></span>
				<?php endforeach; ?>
			</div>
			<p class="hero-chips">
				<?php foreach ( (array) hamshack_opt( 'modes', array() ) as $m ) : ?>
					<span class="tag tag-alt mono"><?php echo esc_html( $m ); ?></span>
				<?php endforeach; ?>
			</p>
		</section>

		<section class="qsl-section">
			<div class="qsl-copy">
				<p class="eyebrow mono">QSL</p>
				<h2 class="section-title"><?php esc_html_e( 'Confirming contacts', 'hamshack' ); ?></h2>
				<p><?php echo esc_html( hamshack_opt( 'qsl_info' ) ); ?></p>
				<div class="hero-actions">
					<?php $log = get_page_by_path( 'logbook' ); ?>
					<?php if ( $log ) : ?>
						<a class="btn btn-primary" href="<?php echo esc_url( get_permalink( $log ) ); ?>"><?php esc_html_e( 'Check the logbook', 'hamshack' ); ?></a>
					<?php endif; ?>
					<?php if ( hamshack_opt( 'qrz_url' ) ) : ?>
						<a class="btn btn-ghost" href="<?php echo esc_url( hamshack_opt( 'qrz_url' ) ); ?>" target="_blank" rel="noopener">QRZ.com <?php hamshack_the_icon( 'external', 16 ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<img class="qsl-card" src="<?php echo esc_url( home_url( '/?hamshack_badge=qsl' ) ); ?>" alt="<?php echo esc_attr( sprintf( __( '%s QSL card', 'hamshack' ), hamshack_opt( 'callsign' ) ) ); ?>" width="900" height="580" loading="lazy">
		</section>
	</div>
	<?php
endwhile;

get_footer();
