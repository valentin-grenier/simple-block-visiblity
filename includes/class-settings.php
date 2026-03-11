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
		$settings     = self::get_settings();
		$layout_sizes = self::get_layout_sizes();
		$mobile_max   = absint( $settings['mobile_breakpoint'] );
		$tablet_max   = absint( $settings['tablet_breakpoint'] );
		$laptop_max   = absint( $settings['laptop_breakpoint'] );
		$tablet_min   = $mobile_max + 1;
		$laptop_min   = $tablet_max + 1;
		$desktop_min  = $laptop_max + 1;
		echo '<p>' . esc_html__( 'Define the screen width breakpoints for each device type. These values determine when blocks with visibility settings will be hidden on the frontend.', 'simple-block-visibility' ) . '</p>';
		echo '<ul class="sblv-breakpoints-list">';
		// translators: %d: maximum screen width in pixels for mobile devices.
		echo '<li>' . esc_html( sprintf( __( 'Mobile: ≤ %dpx', 'simple-block-visibility' ), $mobile_max ) ) . '</li>';
		// translators: %1$d: minimum screen width in pixels, %2$d: maximum screen width in pixels for tablet devices.
		echo '<li>' . esc_html( sprintf( __( 'Tablet: %1$dpx–%2$dpx', 'simple-block-visibility' ), $tablet_min, $tablet_max ) ) . '</li>';
		// translators: %1$d: minimum screen width in pixels, %2$d: maximum screen width in pixels for laptop devices.
		echo '<li>' . esc_html( sprintf( __( 'Laptop: %1$dpx–%2$dpx', 'simple-block-visibility' ), $laptop_min, $laptop_max ) ) . '</li>';
		// translators: %d: minimum screen width in pixels for desktop devices.
		echo '<li>' . esc_html( sprintf( __( 'Desktop: ≥ %dpx', 'simple-block-visibility' ), $desktop_min ) ) . '</li>';
		echo '</ul>';

		if ( $layout_sizes['content_size'] > 0 || $layout_sizes['wide_size'] > 0 ) {
			echo '<p style="margin-top:1em;">' . esc_html__( 'FSE layout breakpoints (from theme.json):', 'simple-block-visibility' ) . '</p>';
			echo '<ul class="sblv-breakpoints-list">';
			if ( $layout_sizes['content_size'] > 0 ) {
				// translators: %d: content width in pixels from theme.json.
				echo '<li>' . esc_html( sprintf( __( 'Content width: ≤ %dpx', 'simple-block-visibility' ), $layout_sizes['content_size'] + 64 ) ) . '</li>';
			}
			if ( $layout_sizes['wide_size'] > 0 ) {
				// translators: %d: wide width in pixels from theme.json.
				echo '<li>' . esc_html( sprintf( __( 'Wide width: ≤ %dpx', 'simple-block-visibility' ), $layout_sizes['wide_size'] + 64 ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p style="margin-top:1em;"><em>' . esc_html__( 'No FSE layout sizes found in theme.json. Content width and wide width breakpoints are unavailable.', 'simple-block-visibility' ) . '</em></p>';
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
		$settings     = self::get_settings();
		$layout_sizes = self::get_layout_sizes();
		$mobile_max   = absint( $settings['mobile_breakpoint'] );
		$tablet_max   = absint( $settings['tablet_breakpoint'] );
		$laptop_max   = absint( $settings['laptop_breakpoint'] );
		$tablet_min   = $mobile_max + 1;
		$laptop_min   = $tablet_max + 1;
		$desktop_min  = $laptop_max + 1;

		$css = sprintf(
			'@media(max-width:%1$dpx){.sblv-hide-mobile{display:none!important}}' .
			'@media(min-width:%2$dpx) and (max-width:%3$dpx){.sblv-hide-tablet{display:none!important}}' .
			'@media(min-width:%4$dpx) and (max-width:%5$dpx){.sblv-hide-laptop{display:none!important}}' .
			'@media(min-width:%6$dpx){.sblv-hide-desktop{display:none!important}}',
			$mobile_max,
			$tablet_min,
			$tablet_max,
			$laptop_min,
			$laptop_max,
			$desktop_min
		);

		if ( $layout_sizes['content_size'] > 0 ) {
			$css .= sprintf(
				'@media(max-width:%dpx){.sblv-hide-content-width{display:none!important}}',
				$layout_sizes['content_size']
			);
		}

		if ( $layout_sizes['wide_size'] > 0 ) {
			$css .= sprintf(
				'@media(max-width:%dpx){.sblv-hide-wide-width{display:none!important}}',
				$layout_sizes['wide_size']
			);
		}

		return $css;
	}
}
