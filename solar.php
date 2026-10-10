<?php
/**
 * N0NBH solar-terrestrial banner (hamqsl.com).
 * The banner and its credit link are shown exactly as N0NBH publishes them.
 *
 * Args: banner (string) – hamqsl.com image key, e.g. "solarn0nbh".
 *
 * @package HamShack
 */

$banner = hamshack_solar_key( isset( $args['banner'] ) ? $args['banner'] : hamshack_opt( 'solar_banner', 'solarn0nbh' ) );
$labels = hamshack_solar_banners();
?>
<div class="solar solar-<?php echo esc_attr( $banner ); ?>">
	<a class="solar-link" href="https://www.hamqsl.com/solar.html" target="_blank" rel="noopener" title="Click to add Solar-Terrestrial Data to your website!">
		<img src="<?php echo esc_url( 'https://www.hamqsl.com/' . $banner . '.php' ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Live solar-terrestrial data by N0NBH: %s', 'hamshack' ), $labels[ $banner ] ) ); ?>" loading="lazy" decoding="async">
	</a>
	<p class="solar-offline"><?php hamshack_the_icon( 'sun', 28 ); ?><span><?php esc_html_e( 'Live N0NBH data is taking a break.', 'hamshack' ); ?> <a href="https://www.hamqsl.com/solar.html" target="_blank" rel="noopener"><?php esc_html_e( 'View it on hamqsl.com', 'hamshack' ); ?> →</a></span></p>
	<p class="solar-credit"><span class="live-dot" aria-hidden="true"></span><?php esc_html_e( 'Live data', 'hamshack' ); ?> · <a href="https://www.hamqsl.com/solar.html" target="_blank" rel="noopener">N0NBH · hamqsl.com</a></p>
</div>
