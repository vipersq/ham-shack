<?php
/**
 * Site header.
 *
 * @package HamShack
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'hamshack' ); ?></a>

<div class="topbar">
	<div class="container topbar-inner">
		<div class="topbar-left">
			<?php echo hamshack_status_pill(); // phpcs:ignore ?>
			<?php if ( hamshack_opt( 'status_note' ) && 'off' !== hamshack_opt( 'status' ) ) : ?>
				<span class="topbar-note hide-sm"><?php echo esc_html( hamshack_opt( 'status_note' ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="topbar-right mono">
			<span class="clock" title="<?php esc_attr_e( 'Coordinated Universal Time', 'hamshack' ); ?>"><span class="lbl">UTC</span> <time data-clock="utc"><?php echo esc_html( gmdate( 'H:i' ) ); ?></time></span>
			<span class="clock hide-sm" title="<?php esc_attr_e( 'Local time', 'hamshack' ); ?>"><span class="lbl"><?php echo esc_html( wp_date( 'T' ) ); ?></span> <time data-clock="local"><?php echo esc_html( wp_date( 'H:i' ) ); ?></time></span>
			<?php if ( hamshack_opt( 'locator' ) ) : ?><span class="loc hide-sm"><?php hamshack_the_icon( 'pin', 14 ); ?> <?php echo esc_html( hamshack_opt( 'locator' ) ); ?></span><?php endif; ?>
		</div>
	</div>
</div>

<header class="site-header" id="top">
	<div class="container header-inner">
		<div class="site-branding">
			<?php hamshack_brand(); ?>
			<?php if ( is_front_page() ) : ?>
				<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?> – <?php bloginfo( 'description' ); ?></h1>
			<?php endif; ?>
		</div>

		<button class="nav-toggle" aria-controls="site-nav" aria-expanded="false">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'hamshack' ); ?></span>
			<?php hamshack_the_icon( 'menu', 24, 'i-open' ); ?><?php hamshack_the_icon( 'close', 24, 'i-close' ); ?>
		</button>

		<nav id="site-nav" class="site-nav" aria-label="<?php esc_attr_e( 'Main menu', 'hamshack' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'depth'          => 2,
					'fallback_cb'    => 'hamshack_menu_fallback',
				)
			);
			?>
			<form role="search" method="get" class="nav-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="nav-s"><?php esc_html_e( 'Search', 'hamshack' ); ?></label>
				<input id="nav-s" type="search" name="s" placeholder="<?php esc_attr_e( 'Search…', 'hamshack' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<button type="submit" aria-label="<?php esc_attr_e( 'Search', 'hamshack' ); ?>"><?php hamshack_the_icon( 'search', 18 ); ?></button>
			</form>
		</nav>
	</div>
</header>

<main id="content" class="site-main">
