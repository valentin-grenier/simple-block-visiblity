<?php
/**
 * Asset management
 *
 * @package SIMPBLV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles enqueueing of editor assets and frontend CSS output
 */
class SIMPBLV_Enqueue {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_frontend_css' ) );
	}

	/**
	 * Enqueue admin area assets
	 */
	public static function enqueue_admin_assets() {
		wp_enqueue_style(
			'simple-block-visibility-admin',
			SIMPBLV_URL . 'build/admin.css',
			array(),
			SIMPBLV_VERSION
		);
	}

	/**
	 * Enqueue editor assets (Block Editor only)
	 */
	public static function enqueue_editor_assets() {
		wp_enqueue_script(
			'simple-block-visibility-editor',
			SIMPBLV_URL . 'build/editor.js',
			array( 'wp-blocks', 'wp-dom', 'wp-i18n', 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
			SIMPBLV_VERSION,
			true
		);

		wp_enqueue_style(
			'simple-block-visibility-editor',
			SIMPBLV_URL . 'build/editor.css',
			array( 'wp-edit-blocks' ),
			SIMPBLV_VERSION
		);

		// Pass computed breakpoint ranges to the editor JS.
		$ranges  = SIMPBLV_Settings::get_breakpoint_ranges();
		$key_map = array(
			'mobile'        => 'mobile',
			'tablet'        => 'tablet',
			'content-width' => 'contentWidth',
			'laptop'        => 'laptop',
			'wide-width'    => 'wideWidth',
			'desktop'       => 'desktop',
		);

		$js_breakpoints = array();
		$order          = array();
		foreach ( $ranges as $key => $range ) {
			$js_key                       = $key_map[ $key ] ?? $key;
			$js_breakpoints[ $js_key ]    = $range;
			if ( $range['enabled'] ) {
				$order[] = $js_key;
			}
		}

		wp_add_inline_script(
			'simple-block-visibility-editor',
			'var simpblvSettings = ' . wp_json_encode( array(
				'breakpoints' => $js_breakpoints,
				'order'       => $order,
			) ) . ';',
			'before'
		);

		wp_set_script_translations(
			'simple-block-visibility-editor',
			'simple-block-visibility',
			SIMPBLV_PATH . 'languages'
		);
	}

	/**
	 * Output dynamic visibility CSS in <head> on the frontend
	 */
	public static function output_frontend_css() {
		if ( is_admin() ) {
			return;
		}

		$css = SIMPBLV_Settings::get_css();
		echo '<style id="simple-block-visibility-css">' . esc_html( $css ) . '</style>' . "\n";
	}
}
