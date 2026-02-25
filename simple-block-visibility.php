<?php

/**
 * Plugin Name: Simple Block Visibility
 * Plugin URI: https://github.com/valentin-grenier/simple-block-visibility
 * Description: Show or hide Gutenberg blocks based on custom breakpoints.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * Author: Valentin Grenier
 * Author URI: https://www.linkedin.com/in/valentin-grenier/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-block-visibility
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPBLV_VERSION', '1.0.0' );
define( 'SIMPBLV_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPBLV_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPBLV_PATH . 'includes/class-enqueue.php';
require_once SIMPBLV_PATH . 'includes/class-blocks.php';
require_once SIMPBLV_PATH . 'includes/class-settings.php';

/**
 * Initialize plugin
 */
function simpblv_init() {
	SIMPBLV_Enqueue::init();
	SIMPBLV_Blocks::init();
	SIMPBLV_Settings::init();
}
add_action( 'plugins_loaded', 'simpblv_init' );

/**
 * Activation hook
 */
function simpblv_activate() {
	if ( ! get_option( SIMPBLV_Settings::OPTION_NAME ) ) {
		add_option(
			SIMPBLV_Settings::OPTION_NAME,
			array(
				'mobile_breakpoint' => 550,
				'tablet_breakpoint' => 768,
				'laptop_breakpoint' => 1440,
			)
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'simpblv_activate' );

/**
 * Deactivation hook
 */
function simpblv_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'simpblv_deactivate' );
