<?php
/**
 * Station status card (widget + front page).
 *
 * @package HamShack
 */

$status = hamshack_opt( 'status', 'off' );
$since  = (int) hamshack_opt( 'status_time', 0 );
$live   = function_exists( 'hamshack_rig_online' ) && hamshack_rig_online();
$rig    = $live ? hamshack_rig_state() : array();
$freq   = $live ? hamshack_rig_format_freq( $rig['freq_hz'] ) : hamshack_opt( 'status_freq' );
?>
<div class="status-card status-<?php echo esc_attr( $status ); ?><?php echo $live ? ' is-live' : ''; ?>" data-hs-live="card">
	<div class="status-card-top">
		<span class="callsign mono"><?php echo esc_html( hamshack_opt( 'callsign' ) ); ?></span>
		<?php echo hamshack_status_pill( false ); // phpcs:ignore ?>
	</div>
	<?php if ( 'off' !== $status && ( $freq || hamshack_opt( 'status_mode' ) ) ) : ?>
		<div class="vfo mono" aria-label="<?php esc_attr_e( 'Current frequency', 'hamshack' ); ?>">
			<span class="vfo-freq"><?php echo esc_html( $freq ? $freq : '—' ); ?></span>
			<span class="vfo-unit">MHz</span>
			<?php if ( hamshack_opt( 'status_mode' ) ) : ?><span class="vfo-mode"><?php echo esc_html( hamshack_opt( 'status_mode' ) ); ?></span><?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $live ) : ?>
		<p class="rig-live"><span class="live-dot" aria-hidden="true"></span><?php esc_html_e( 'Live from the rig', 'hamshack' ); ?><?php echo $rig['rig'] ? ' · ' . esc_html( $rig['rig'] ) : ''; ?><?php echo hamshack_band_from_freq( $rig['freq_hz'] / 1000000 ) ? ' · ' . esc_html( hamshack_band_from_freq( $rig['freq_hz'] / 1000000 ) ) : ''; ?></p>
	<?php endif; ?>
	<?php if ( hamshack_opt( 'status_note' ) && 'off' !== $status ) : ?>
		<p class="status-note"><?php echo esc_html( hamshack_opt( 'status_note' ) ); ?></p>
	<?php endif; ?>
	<dl class="status-facts">
		<div><dt>UTC</dt><dd class="mono"><time data-clock="utc-sec"><?php echo esc_html( gmdate( 'H:i:s' ) ); ?></time></dd></div>
		<?php if ( hamshack_opt( 'locator' ) ) : ?><div><dt><?php esc_html_e( 'Locator', 'hamshack' ); ?></dt><dd class="mono"><?php echo esc_html( hamshack_opt( 'locator' ) ); ?></dd></div><?php endif; ?>
		<?php if ( hamshack_opt( 'qth' ) ) : ?><div><dt><?php esc_html_e( 'QTH', 'hamshack' ); ?></dt><dd><?php echo esc_html( hamshack_opt( 'qth' ) ); ?></dd></div><?php endif; ?>
		<?php if ( $since ) : ?>
			<div><dt><?php esc_html_e( 'Updated', 'hamshack' ); ?></dt><dd><?php echo esc_html( sprintf( __( '%s ago', 'hamshack' ), human_time_diff( $since ) ) ); ?></dd></div>
		<?php endif; ?>
	</dl>
</div>
