<?php
/**
 * Plugin Name: Smart Admin Notice Manager
 * Plugin URI:  https://phptutorialpoints.in/
 * Description: Manages plugin & theme admin notices intelligently. Groups notices, auto-hides repeated ones, and offers "Remind me later" functionality.
 * Version:     1.0.0
 * Author:      umangapps48
 * Author URI:  https://profiles.wordpress.org/umangapps48/
 * License:     GPLv2 or later
 * Text Domain: smart-admin-notice-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SANM_VERSION', '1.0.0' );
define( 'SANM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SANM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SANM_PLUGIN_DIR . 'includes/class-smart-notice-manager.php';

function sanm_init() {
	$manager = new Smart_Notice_Manager();
	$manager->init();
}
add_action( 'plugins_loaded', 'sanm_init' );
