<?php
/**
 * Plugin settings management
 *
 * @package SIMPBLV
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings and the admin options page
 */
class SIMPBLV_Settings {

	/**
	 * Option name in database
	 */
	const OPTION_NAME = 'simpblv_settings';

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add settings page under Settings menu
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Block Visibility Settings', 'simple-block-visibility' ),
			__( 'Block Visibility', 'simple-block-visibility' ),
			'manage_options',
			'simple-block-visibility',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public static function register_settings() {
		register_setting(
			'simpblv_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);

		add_settings_section(
			'simpblv_breakpoints',
			__( 'Breakpoints', 'simple-block-visibility' ),
			array( __CLASS__, 'render_breakpoints_section' ),
			'simple-block-visibility'
		);

		add_settings_field(
			'mobile_breakpoint',
			__( 'Mobile max-width', 'simple-block-visibility' ),
			array( __CLASS__, 'render_mobile_breakpoint_field' ),
			'simple-block-visibility',
			'simpblv_breakpoints'
		);

		add_settings_field(
			'tablet_breakpoint',
			__( 'Tablet max-width', 'simple-block-visibility' ),
			array( __CLASS__, 'render_tablet_breakpoint_field' ),
			'simple-block-visibility',
			'simpblv_breakpoints'
		);

		add_settings_field(
			'laptop_breakpoint',
			__( 'Laptop max-width', 'simple-block-visibility' ),
			array( __CLASS__, 'render_laptop_breakpoint_field' ),
			'simple-block-visibility',
			'simpblv_breakpoints'
		);
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	private static function get_default_settings() {
		return array(
			'mobile_breakpoint' => 550,
			'tablet_breakpoint' => 768,
			'laptop_breakpoint' => 1440,
		);
	}

	/**
	 * Get layout sizes from theme.json (contentSize and wideSize)
	 *
	 * @return array Array with 'content_size' and 'wide_size' in pixels (0 if unavailable).
	 */
	public static function get_layout_sizes() {
		$layout = wp_get_global_settings( array( 'layout' ) );

		return array(
			'content_size' => self::parse_px_value( $layout['contentSize'] ?? '' ),
			'wide_size'    => self::parse_px_value( $layout['wideSize'] ?? '' ),
		);
	}

	/**
	 * Parse a CSS length value to an integer pixel value.
	 * Supports px, rem, and em units.
	 *
	 * @param string $value CSS length value (e.g. '620px', '40rem').
	 * @return int Pixel value, or 0 if not parseable.
	 */
	private static function parse_px_value( $value ) {
		$value = trim( $value );

		if ( empty( $value ) ) {
			return 0;
		}

		if ( preg_match( '/^([\d.]+)\s*px$/i', $value, $matches ) ) {
			return absint( round( (float) $matches[1] ) );
		}

		if ( preg_match( '/^([\d.]+)\s*r?em$/i', $value, $matches ) ) {
			return absint( round( (float) $matches[1] * 16 ) );
		}

		// Unitless value, treat as px.
		if ( is_numeric( $value ) ) {
			return absint( round( (float) $value ) );
		}

		return 0;
	}

	/**
	 * Compute ordered, non-overlapping breakpoint ranges.
	 *
	 * Merges device breakpoints (mobile, tablet, laptop) with FSE layout
	 * sizes (contentSize, wideSize) from theme.json into a single sorted
	 * chain. Layout breakpoints that are too close to a device breakpoint
	 * (< 50px gap) are disabled to avoid confusing or ineffective ranges.
	 *
	 * @return array Associative array of breakpoint ranges keyed by identifier.
	 */
	public static function get_breakpoint_ranges() {
		$settings    = self::get_settings();
		$layout      = self::get_layout_sizes();
		$min_range   = 50;

		$mobile_max  = absint( $settings['mobile_breakpoint'] );
		$tablet_max  = absint( $settings['tablet_breakpoint'] );
		$laptop_max  = absint( $settings['laptop_breakpoint'] );
		$content_max = absint( $layout['content_size'] );
		$wide_max    = absint( $layout['wide_size'] );

		$device_maxes = array( $mobile_max, $tablet_max, $laptop_max );

		// Check if content width creates a meaningful range.
		$content_enabled = $content_max > 0;
		if ( $content_enabled ) {
			foreach ( $device_maxes as $dm ) {
				if ( abs( $content_max - $dm ) < $min_range ) {
					$content_enabled = false;
					break;
				}
			}
		}

		// Check if wide width creates a meaningful range.
		$wide_enabled = $wide_max > 0;
		if ( $wide_enabled ) {
			foreach ( $device_maxes as $dm ) {
				if ( abs( $wide_max - $dm ) < $min_range ) {
					$wide_enabled = false;
					break;
				}
			}
			if ( $wide_enabled && $content_enabled && abs( $wide_max - $content_max ) < $min_range ) {
				$wide_enabled = false;
			}
		}

		// Build sorted list of active breakpoints.
		$active = array(
			array( 'key' => 'mobile', 'max' => $mobile_max ),
			array( 'key' => 'tablet', 'max' => $tablet_max ),
			array( 'key' => 'laptop', 'max' => $laptop_max ),
		);

		if ( $content_enabled ) {
			$active[] = array( 'key' => 'content-width', 'max' => $content_max );
		}
		if ( $wide_enabled ) {
			$active[] = array( 'key' => 'wide-width', 'max' => $wide_max );
		}

		usort( $active, function ( $a, $b ) {
			return $a['max'] - $b['max'];
		} );

		// Compute ranges from sorted breakpoints.
		$ranges   = array();
		$prev_max = 0;
		foreach ( $active as $i => $bp ) {
			$ranges[ $bp['key'] ] = array(
				'enabled' => true,
				'min'     => ( 0 === $i ) ? 0 : $prev_max + 1,
				'max'     => $bp['max'],
			);
			$prev_max = $bp['max'];
		}

		// Desktop is always the unbounded upper range.
		$ranges['desktop'] = array(
			'enabled' => true,
			'min'     => $prev_max + 1,
			'max'     => 0,
		);

		// Add disabled entries for skipped FSE breakpoints.
		if ( ! $content_enabled ) {
			$ranges['content-width'] = array(
				'enabled' => false,
				'min'     => 0,
				'max'     => $content_max,
			);
		}
		if ( ! $wide_enabled ) {
			$ranges['wide-width'] = array(
				'enabled' => false,
				'min'     => 0,
				'max'     => $wide_max,
			);
		}

		return $ranges;
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['mobile_breakpoint'] ) ) {
			$sanitized['mobile_breakpoint'] = absint( $input['mobile_breakpoint'] );
			$sanitized['mobile_breakpoint'] = max( 320, min( 1024, $sanitized['mobile_breakpoint'] ) );
		}

		if ( isset( $input['tablet_breakpoint'] ) ) {
			$sanitized['tablet_breakpoint'] = absint( $input['tablet_breakpoint'] );
			$sanitized['tablet_breakpoint'] = max( 321, min( 1920, $sanitized['tablet_breakpoint'] ) );
		}

		if ( isset( $input['laptop_breakpoint'] ) ) {
			$sanitized['laptop_breakpoint'] = absint( $input['laptop_breakpoint'] );
			$sanitized['laptop_breakpoint'] = max( 322, min( 3840, $sanitized['laptop_breakpoint'] ) );
		}

		// Ensure each breakpoint is larger than the previous one
		if ( isset( $sanitized['mobile_breakpoint'], $sanitized['tablet_breakpoint'] ) &&
			$sanitized['tablet_breakpoint'] <= $sanitized['mobile_breakpoint'] ) {
			$sanitized['tablet_breakpoint'] = $sanitized['mobile_breakpoint'] + 1;
		}

		if ( isset( $sanitized['tablet_breakpoint'], $sanitized['laptop_breakpoint'] ) &&
			$sanitized['laptop_breakpoint'] <= $sanitized['tablet_breakpoint'] ) {
			$sanitized['laptop_breakpoint'] = $sanitized['tablet_breakpoint'] + 1;
		}

		return $sanitized;
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'simpblv_settings_group' );
				do_settings_sections( 'simple-block-visibility' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render breakpoints section description
	 */
	public static function render_breakpoints_section() {
		$ranges       = self::get_breakpoint_ranges();
		$layout_sizes = self::get_layout_sizes();

		$labels = array(
			'mobile'        => __( 'Mobile', 'simple-block-visibility' ),
			'tablet'        => __( 'Tablet', 'simple-block-visibility' ),
			'content-width' => __( 'Content width', 'simple-block-visibility' ),
			'laptop'        => __( 'Laptop', 'simple-block-visibility' ),
			'wide-width'    => __( 'Wide width', 'simple-block-visibility' ),
			'desktop'       => __( 'Desktop', 'simple-block-visibility' ),
		);

		echo '<p>' . esc_html__( 'Define the screen width breakpoints for each device type. These values determine when blocks with visibility settings will be hidden on the frontend.', 'simple-block-visibility' ) . '</p>';
		echo '<ul class="sblv-breakpoints-list">';

		foreach ( $ranges as $key => $range ) {
			if ( ! $range['enabled'] ) {
				continue;
			}

			$label = $labels[ $key ] ?? $key;

			if ( 0 === $range['min'] ) {
				/* translators: %1$s: breakpoint label, %2$d: max width in pixels. */
				echo '<li>' . esc_html( sprintf( __( '%1$s: ≤ %2$dpx', 'simple-block-visibility' ), $label, $range['max'] ) ) . '</li>';
			} elseif ( 0 === $range['max'] ) {
				/* translators: %1$s: breakpoint label, %2$d: min width in pixels. */
				echo '<li>' . esc_html( sprintf( __( '%1$s: ≥ %2$dpx', 'simple-block-visibility' ), $label, $range['min'] ) ) . '</li>';
			} else {
				/* translators: %1$s: breakpoint label, %2$d: min width, %3$d: max width in pixels. */
				echo '<li>' . esc_html( sprintf( __( '%1$s: %2$dpx–%3$dpx', 'simple-block-visibility' ), $label, $range['min'], $range['max'] ) ) . '</li>';
			}
		}

		echo '</ul>';

		// Show notice for disabled FSE breakpoints.
		$disabled_notices = array();
		if ( isset( $ranges['content-width'] ) && ! $ranges['content-width']['enabled'] && $layout_sizes['content_size'] > 0 ) {
			/* translators: %d: content width in pixels from theme.json. */
			$disabled_notices[] = sprintf( __( 'Content width (%dpx)', 'simple-block-visibility' ), $layout_sizes['content_size'] );
		}
		if ( isset( $ranges['wide-width'] ) && ! $ranges['wide-width']['enabled'] && $layout_sizes['wide_size'] > 0 ) {
			/* translators: %d: wide width in pixels from theme.json. */
			$disabled_notices[] = sprintf( __( 'Wide width (%dpx)', 'simple-block-visibility' ), $layout_sizes['wide_size'] );
		}

		if ( ! empty( $disabled_notices ) ) {
			echo '<p class="description"><em>';
			printf(
				/* translators: %s: comma-separated list of disabled breakpoint names with pixel values. */
				esc_html__( '%s breakpoint(s) from theme.json disabled — too close to an existing device breakpoint.', 'simple-block-visibility' ),
				esc_html( implode( ', ', $disabled_notices ) )
			);
			echo '</em></p>';
		} elseif ( 0 === $layout_sizes['content_size'] && 0 === $layout_sizes['wide_size'] ) {
			echo '<p class="description"><em>' . esc_html__( 'No FSE layout sizes found in theme.json. Content width and wide width breakpoints are unavailable.', 'simple-block-visibility' ) . '</em></p>';
		}
	}

	/**
	 * Render mobile breakpoint field
	 */
	public static function render_mobile_breakpoint_field() {
		$options = get_option( self::OPTION_NAME, self::get_default_settings() );
		$value   = $options['mobile_breakpoint'] ?? 550;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mobile_breakpoint]" value="<?php echo esc_attr( $value ); ?>" min="320" max="1024" step="1"> px
		<p class="description"><?php esc_html_e( 'Screens up to this width are considered "Mobile".', 'simple-block-visibility' ); ?></p>
		<?php
	}

	/**
	 * Render tablet breakpoint field
	 */
	public static function render_tablet_breakpoint_field() {
		$options = get_option( self::OPTION_NAME, self::get_default_settings() );
		$value   = $options['tablet_breakpoint'] ?? 768;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[tablet_breakpoint]" value="<?php echo esc_attr( $value ); ?>" min="321" max="1920" step="1"> px
		<p class="description"><?php esc_html_e( 'Screens up to this width are considered "Tablet".', 'simple-block-visibility' ); ?></p>
		<?php
	}

	/**
	 * Render laptop breakpoint field
	 */
	public static function render_laptop_breakpoint_field() {
		$options = get_option( self::OPTION_NAME, self::get_default_settings() );
		$value   = $options['laptop_breakpoint'] ?? 1440;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[laptop_breakpoint]" value="<?php echo esc_attr( $value ); ?>" min="322" max="3840" step="1"> px
		<p class="description"><?php esc_html_e( 'Screens up to this width are considered "Laptop". Desktop starts at the next pixel.', 'simple-block-visibility' ); ?></p>
		<?php
	}

	/**
	 * Get current settings, merged with defaults to handle missing keys
	 *
	 * @return array Current settings.
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_NAME, array() );
		return array_merge( self::get_default_settings(), $saved );
	}

	/**
	 * Generate the dynamic CSS for frontend visibility
	 *
	 * @return string CSS string with media queries.
	 */
	public static function get_css() {
		$ranges = self::get_breakpoint_ranges();
		$css    = '';

		$class_map = array(
			'mobile'        => 'sblv-hide-mobile',
			'tablet'        => 'sblv-hide-tablet',
			'content-width' => 'sblv-hide-content-width',
			'laptop'        => 'sblv-hide-laptop',
			'wide-width'    => 'sblv-hide-wide-width',
			'desktop'       => 'sblv-hide-desktop',
		);

		foreach ( $ranges as $key => $range ) {
			if ( ! $range['enabled'] || ! isset( $class_map[ $key ] ) ) {
				continue;
			}

			$class = $class_map[ $key ];

			if ( 0 === $range['min'] ) {
				$css .= sprintf( '@media(max-width:%dpx){.%s{display:none!important}}', $range['max'], $class );
			} elseif ( 0 === $range['max'] ) {
				$css .= sprintf( '@media(min-width:%dpx){.%s{display:none!important}}', $range['min'], $class );
			} else {
				$css .= sprintf(
					'@media(min-width:%dpx) and (max-width:%dpx){.%s{display:none!important}}',
					$range['min'],
					$range['max'],
					$class
				);
			}
		}

		return $css;
	}
}
