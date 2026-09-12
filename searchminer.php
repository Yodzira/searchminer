<?php
/**
 * Plugin Name:       SearchMiner
 * Plugin URI:        https://wordpress.org/plugins/searchminer/
 * Description:       See what visitors search on your site and what your search can't find — without replacing your search engine. WooCommerce ready.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yodzira
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       searchminer
 */

defined( 'ABSPATH' ) || exit;

define( 'WPSM_VERSION', '0.1.0' );
define( 'WPSM_FILE', __FILE__ );
define( 'WPSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSM_CAP', 'manage_options' );

require_once WPSM_DIR . 'includes/class-wpsm-settings.php';
require_once WPSM_DIR . 'includes/class-wpsm-normalizer.php';
require_once WPSM_DIR . 'includes/class-wpsm-repository.php';
require_once WPSM_DIR . 'includes/class-wpsm-capture.php';
require_once WPSM_DIR . 'includes/class-wpsm-beacon.php';
require_once WPSM_DIR . 'includes/class-wpsm-admin.php';

register_activation_hook( __FILE__, array( 'WPSM_Repository', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPSM_Repository', 'deactivate' ) );

add_action( 'init', array( 'WPSM_Settings', 'init' ) );
add_action( 'init', array( 'WPSM_Capture', 'init' ) );
add_action( 'init', array( 'WPSM_Beacon', 'init' ) );
add_action( 'admin_menu', array( 'WPSM_Admin', 'init' ) );
add_action( 'wp_dashboard_setup', array( 'WPSM_Admin', 'add_dashboard_widget' ) );
