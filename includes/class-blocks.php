<?php
/**
 * Block rendering and visibility management
 *
 * @package SIMPBLV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles block-related functionality for visibility
 */
class SIMPBLV_Blocks {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_filter( 'render_block', array( __CLASS__, 'add_visibility_classes' ), 10, 2 );
	}

	/**
	 * Add visibility CSS classes to blocks that have hide attributes set
	 *
	 * @param string $block_content Block HTML content.
	 * @param array  $block         Block data.
	 * @return string Modified block content.
	 */
	public static function add_visibility_classes( $block_content, $block ) {
		if ( empty( $block['attrs'] ) ) {
			return $block_content;
		}

		$hide_mobile  = ! empty( $block['attrs']['hideOnMobile'] );
		$hide_tablet  = ! empty( $block['attrs']['hideOnTablet'] );
		$hide_laptop  = ! empty( $block['attrs']['hideOnLaptop'] );
		$hide_desktop = ! empty( $block['attrs']['hideOnDesktop'] );

		if ( ! $hide_mobile && ! $hide_tablet && ! $hide_laptop && ! $hide_desktop ) {
			return $block_content;
		}

		if ( empty( trim( $block_content ) ) ) {
			return $block_content;
		}

		$classes = array();
		if ( $hide_mobile ) {
			$classes[] = 'sblv-hide-mobile';
		}
		if ( $hide_tablet ) {
			$classes[] = 'sblv-hide-tablet';
		}
		if ( $hide_laptop ) {
			$classes[] = 'sblv-hide-laptop';
		}
		if ( $hide_desktop ) {
			$classes[] = 'sblv-hide-desktop';
		}

		$class_string = implode( ' ', $classes );

		$block_content = preg_replace_callback(
			'/^(<[a-z][a-z0-9]*)((?:\s+[^>]*)?)(>)/i',
			function ( $matches ) use ( $class_string ) {
				$tag        = $matches[1];
				$attributes = $matches[2];
				$close      = $matches[3];

				if ( preg_match( '/class=["\']([^"\']*)["\']/', $attributes ) ) {
					$attributes = preg_replace(
						'/class=["\']([^"\']*)["\']/',
						'class="$1 ' . $class_string . '"',
						$attributes
					);
				} else {
					$attributes .= ' class="' . $class_string . '"';
				}

				return $tag . $attributes . $close;
			},
			$block_content,
			1
		);

		return $block_content;
	}
}
