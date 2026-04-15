<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguard-wrap">
	<h1><?php esc_html_e( 'WP Guard Network Overview', 'wp-guard' ); ?></h1>
	<p><?php esc_html_e( 'Cross-site health visibility for multisite installations.', 'wp-guard' ); ?></p>
	<form method="get" class="wpguard-filter-row">
		<input type="hidden" name="page" value="wpguard-network" />
		<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search sites', 'wp-guard' ); ?>" value="<?php echo isset( $_GET['s'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; ?>" />
		<select name="safe_mode_filter">
			<option value=""><?php esc_html_e( 'All Safe Mode States', 'wp-guard' ); ?></option>
			<option value="on" <?php selected( isset( $_GET['safe_mode_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['safe_mode_filter'] ) ) : '', 'on' ); ?>><?php esc_html_e( 'Safe Mode On', 'wp-guard' ); ?></option>
			<option value="off" <?php selected( isset( $_GET['safe_mode_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['safe_mode_filter'] ) ) : '', 'off' ); ?>><?php esc_html_e( 'Safe Mode Off', 'wp-guard' ); ?></option>
		</select>
		<button class="button"><?php esc_html_e( 'Filter', 'wp-guard' ); ?></button>
	</form>

	<div class="wpguard-table">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'wp-guard' ); ?></th>
				<th><?php esc_html_e( 'URL', 'wp-guard' ); ?></th>
				<th><?php esc_html_e( 'Backups', 'wp-guard' ); ?></th>
				<th><?php esc_html_e( 'Logs', 'wp-guard' ); ?></th>
				<th><?php esc_html_e( 'Latest Backup', 'wp-guard' ); ?></th>
				<th><?php esc_html_e( 'Safe Mode', 'wp-guard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $network_rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No sites found.', 'wp-guard' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $network_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['name'] . ' (#' . $row['blog_id'] . ')' ); ?></td>
						<td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['url'] ); ?></a></td>
						<td><?php echo esc_html( (string) $row['backups_count'] ); ?></td>
						<td><?php echo esc_html( (string) $row['logs_count'] ); ?></td>
						<td><?php echo esc_html( $row['latest_backup'] ); ?></td>
						<td><?php echo esc_html( $row['safe_mode'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>
</div>
