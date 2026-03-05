<?php
/**
 * Plugin Name: Toggle Admin Notices
 * Plugin URI:  https://phptutorialpoints.in/
 * Description: Manages plugin & theme admin notices intelligently. Groups notices, auto-hides repeated ones, and offers "Remind me later" functionality.
 * Version:     1.0.0
 * Author:      umangapps
 * Author URI:  https://profiles.wordpress.org/umangapps/
 * License:     GPLv2 or later
 * Text Domain: toggle-admin-notices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TOGGADNO_VERSION', '1.0.0' );
define( 'TOGGADNO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOGGADNO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TOGGADNO_PLUGIN_DIR . 'includes/class-toggle-notice-manager.php';

function toggadno_init() {
	$manager = new Toggadno_Notice_Manager();
	$manager->init();
}
add_action( 'plugins_loaded', 'toggadno_init' );
