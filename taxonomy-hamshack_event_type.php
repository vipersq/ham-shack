<?php
/**
 * Events of one type.
 *
 * @package HamShack
 */

get_header();
$term = get_queried_object();
$now  = new DateTimeImmutable( 'now', wp_timezone() );
$occ  = array_filter(
	hamshack_occurrences( $now->modify( '-3 hours' ), $now->modify( '+1 year' ) ),
	function ( $o ) use ( $term ) {
		return $o['type'] === $term->slug;
	}
);
?>
<header class="page-hero">
	<div class="container narrow">
		<p class="eyebrow mono"><?php esc_html_e( 'Event type', 'hamshack' ); ?></p>
		<h1 class="page-title"><span class="swatch big <?php echo esc_attr( hamshack_type_class( $term->slug ) ); ?>"></span><?php echo esc_html( $term->name ); ?></h1>
		<?php if ( $term->description ) : ?>
			<p class="page-desc"><?php echo esc_html( $term->description ); ?></p>
		<?php endif; ?>
	</div>
</header>
<div class="container narrow">
	<?php echo $occ ? hamshack_event_list_html( array_slice( $occ, 0, 30 ) ) : '<p class="muted">' . esc_html__( 'Nothing of this type scheduled in the next 12 months.', 'hamshack' ) . '</p>'; // phpcs:ignore ?>
</div>
<?php
get_footer();
