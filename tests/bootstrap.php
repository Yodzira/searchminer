<?php
/**
 * PHPUnit bootstrap.
 *
 * Requires the WordPress core test suite. Point WP_TESTS_DIR to a
 * checkout of wordpress-develop/tests/phpunit (wp-env provides it
 * automatically at /wordpress-phpunit).
 *
 * @package SearchMiner
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found in {$_tests_dir}. Set WP_TESTS_DIR." . PHP_EOL;
	exit( 1 );
}

if ( ! defined( 'WPSM_TESTS_PLUGIN_DIR' ) ) {
	define( 'WPSM_TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
}

// Give tests a hook to load the plugin before the suite boots.
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require WPSM_TESTS_PLUGIN_DIR . '/searchminer.php';
		// Activation hooks do not fire when a plugin is force-loaded this way,
		// so create tables and schedule maintenance explicitly.
		WPSM_Repository::activate();
	}
);

// Load the suite.
require $_tests_dir . '/includes/bootstrap.php';
