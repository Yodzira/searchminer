<?php
/**
 * Uninstall cleanup: drop our tables, options and scheduled events.
 *
 * @package SearchMiner
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wpsm_queries',
	$wpdb->prefix . 'wpsm_daily',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- hardcoded table names, uninstall time.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'wpsm_settings' );
delete_option( 'wpsm_version' );

wp_clear_scheduled_hook( 'wpsm_daily_prune' );
