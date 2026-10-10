<?php
/**
 * Theme setup, assets and template helpers.
 *
 * @package HamShack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'hamshack_setup' );
function hamshack_setup() {
	load_theme_textdomain( 'hamshack', HAMSHACK_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 420,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => __( 'Amber', 'hamshack' ), 'slug' => 'amber', 'color' => '#ffb020' ),
			array( 'name' => __( 'Cyan', 'hamshack' ), 'slug' => 'cyan', 'color' => '#22d3ee' ),
			array( 'name' => __( 'Signal green', 'hamshack' ), 'slug' => 'green', 'color' => '#39ff88' ),
			array( 'name' => __( 'On-air red', 'hamshack' ), 'slug' => 'red', 'color' => '#ff3b3b' ),
			array( 'name' => __( 'Night', 'hamshack' ), 'slug' => 'night', 'color' => '#0b0f14' ),
			array( 'name' => __( 'Panel', 'hamshack' ), 'slug' => 'panel', 'color' => '#121a23' ),
			array( 'name' => __( 'Text', 'hamshack' ), 'slug' => 'text', 'color' => '#d6e2ee' ),
		)
	);
	add_editor_style( 'assets/css/editor.css' );

	set_post_thumbnail_size( 800, 450, true );
	add_image_size( 'hamshack-hero', 1600, 700, true );

	register_nav_menus(
		array(
			'primary' => __( 'Main menu', 'hamshack' ),
			'footer'  => __( 'Footer menu', 'hamshack' ),
		)
	);

	if ( ! isset( $GLOBALS['content_width'] ) ) {
		$GLOBALS['content_width'] = 760;
	}
}

add_action( 'widgets_init', 'hamshack_widgets_init' );
function hamshack_widgets_init() {
	$common = array(
		'before_widget' => '<section id="%1$s" class="widget panel %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	);
	register_sidebar( array_merge( $common, array( 'name' => __( 'Blog sidebar', 'hamshack' ), 'id' => 'sidebar-1', 'description' => __( 'Shown next to posts and archives.', 'hamshack' ) ) ) );
	register_sidebar( array_merge( $common, array( 'name' => __( 'Footer column 1', 'hamshack' ), 'id' => 'footer-1' ) ) );
	register_sidebar( array_merge( $common, array( 'name' => __( 'Footer column 2', 'hamshack' ), 'id' => 'footer-2' ) ) );
	register_sidebar( array_merge( $common, array( 'name' => __( 'Footer column 3', 'hamshack' ), 'id' => 'footer-3' ) ) );
}

add_action( 'wp_enqueue_scripts', 'hamshack_assets' );
function hamshack_assets() {
	wp_enqueue_style( 'hamshack', get_stylesheet_uri(), array(), HAMSHACK_VERSION );
	wp_enqueue_style( 'hamshack-apps', HAMSHACK_URI . '/assets/css/apps.css', array( 'hamshack' ), HAMSHACK_VERSION );
	wp_enqueue_script( 'hamshack', HAMSHACK_URI . '/assets/js/main.js', array(), HAMSHACK_VERSION, true );
	wp_localize_script(
		'hamshack',
		'HAMSHACK',
		array(
			'callsign' => hamshack_opt( 'callsign' ),
			'tz'       => wp_timezone_string(),
		)
	);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * Favicon fallback when no Site Icon is set.
 */
add_action( 'wp_head', 'hamshack_favicon', 2 );
function hamshack_favicon() {
	if ( ! has_site_icon() ) {
		echo '<link rel="icon" type="image/svg+xml" href="' . hamshack_img( 'icon.svg' ) . '">' . "\n"; // phpcs:ignore
	}
	echo '<meta name="theme-color" content="#0b0f14">' . "\n";
}

/**
 * Brand logo: custom logo if uploaded, otherwise the bundled SVG.
 */
function hamshack_brand() {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	printf(
		'<a class="brand" href="%1$s" rel="home" aria-label="%2$s">%3$s</a>',
		esc_url( home_url( '/' ) ),
		esc_attr( get_bloginfo( 'name' ) ),
		hamshack_logo_svg() // phpcs:ignore -- generated SVG, values escaped inside.
	);
}

/**
 * Fallback menu when none is assigned.
 */
function hamshack_menu_fallback() {
	echo '<ul class="menu">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'hamshack' ) . '</a></li>';
	foreach ( array( 'blog', 'calendar', 'logbook', 'station', 'contact' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			echo '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></li>';
		}
	}
	echo '</ul>';
}

add_filter( 'excerpt_length', function () {
	return 26;
} );
add_filter( 'excerpt_more', function () {
	return '…';
} );

/**
 * Post meta line.
 */
function hamshack_post_meta() {
	echo '<div class="post-meta">';
	echo '<span>' . hamshack_icon( 'calendar', 15 ) . '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time></span>'; // phpcs:ignore
	echo '<span>' . hamshack_icon( 'clock', 15 ) . esc_html( sprintf( _n( '%d min read', '%d min read', hamshack_reading_time(), 'hamshack' ), hamshack_reading_time() ) ) . '</span>'; // phpcs:ignore
	$cats = get_the_category();
	if ( $cats ) {
		echo '<span>' . hamshack_icon( 'folder', 15 ) . '<a href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a></span>'; // phpcs:ignore
	}
	if ( comments_open() || get_comments_number() ) {
		echo '<span>' . hamshack_icon( 'comment', 15 ) . esc_html( get_comments_number() ) . '</span>'; // phpcs:ignore
	}
	echo '</div>';
}

/**
 * Thumbnail with SVG placeholder fallback.
 */
function hamshack_thumb( $size = 'post-thumbnail' ) {
	if ( has_post_thumbnail() ) {
		the_post_thumbnail( $size, array( 'loading' => 'lazy' ) );
	} else {
		echo '<img src="' . hamshack_img( 'placeholder.svg' ) . '" alt="" loading="lazy" width="800" height="450">'; // phpcs:ignore
	}
}

/**
 * Numbered pagination.
 */
function hamshack_pagination() {
	$links = paginate_links(
		array(
			'type'      => 'list',
			'prev_text' => hamshack_icon( 'arrow-l', 16 ) . '<span class="screen-reader-text">' . __( 'Previous', 'hamshack' ) . '</span>',
			'next_text' => '<span class="screen-reader-text">' . __( 'Next', 'hamshack' ) . '</span>' . hamshack_icon( 'arrow-r', 16 ),
		)
	);
	if ( $links ) {
		echo '<nav class="pagination" aria-label="' . esc_attr__( 'Pagination', 'hamshack' ) . '">' . $links . '</nav>'; // phpcs:ignore
	}
}

/**
 * Status pill markup (used in header, widgets, front page).
 */
function hamshack_status_pill( $with_detail = true ) {
	$status = hamshack_opt( 'status', 'off' );
	$freq   = hamshack_opt( 'status_freq' );
	$mode   = hamshack_opt( 'status_mode' );
	$out    = '<span class="status-pill status-' . esc_attr( $status ) . '" data-hs-live="pill"><span class="dot" aria-hidden="true"></span>' . esc_html( hamshack_status_label( $status ) );
	if ( $with_detail && 'off' !== $status && ( $freq || $mode ) ) {
		$out .= '<span class="status-detail">' . esc_html( trim( $freq . ( $freq ? ' MHz ' : '' ) . $mode ) ) . '</span>';
	}
	$out .= '</span>';
	return $out;
}

/**
 * Body classes.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( is_active_sidebar( 'sidebar-1' ) && ( is_home() || is_archive() || is_search() || is_singular( 'post' ) ) ) {
		$classes[] = 'has-sidebar';
	}
	return $classes;
} );

/**
 * Comment markup.
 */
function hamshack_comment( $comment, $args, $depth ) {
	?>
	<li id="comment-<?php comment_ID(); ?>" <?php comment_class( 'comment-item' ); ?>>
		<article class="comment-body panel">
			<header class="comment-head">
				<?php echo get_avatar( $comment, 44 ); ?>
				<div>
					<strong class="comment-author"><?php comment_author_link(); ?></strong>
					<a class="comment-date" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php comment_time( 'c' ); ?>"><?php comment_date(); ?> · <?php comment_time(); ?></time></a>
				</div>
			</header>
			<?php if ( '0' === $comment->comment_approved ) : ?>
				<p class="notice"><?php esc_html_e( 'Your comment is awaiting moderation.', 'hamshack' ); ?></p>
			<?php endif; ?>
			<div class="comment-text"><?php comment_text(); ?></div>
			<?php
			comment_reply_link(
				array_merge(
					$args,
					array(
						'depth'     => $depth,
						'max_depth' => $args['max_depth'],
						'before'    => '<div class="reply">',
						'after'     => '</div>',
					)
				)
			);
			?>
		</article>
	<?php
}
