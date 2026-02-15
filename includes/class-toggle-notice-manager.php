<?php
/**
 * Core class for Toggle Admin Notices.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Toggle_Notice_Manager {

	/**
	 * Initialize the manager.
	 */
	public function init() {
		// Hook into admin_init to intercept other plugins' notices.
		// Priority 1000 ensures we run after most plugins have added their hooks.
		add_action( 'admin_init', array( $this, 'intercept_notices' ), 1000 );
		
		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handler for dismissed/snoozed notices.
		add_action( 'wp_ajax_tan_dismiss_notice', array( $this, 'handle_ajax_dismiss' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'tan-admin-css', TAN_PLUGIN_URL . 'assets/css/admin-notices.css', array(), TAN_VERSION );
		wp_enqueue_script( 'tan-admin-js', TAN_PLUGIN_URL . 'assets/js/admin-notices.js', array( 'jquery' ), TAN_VERSION, true );
		
		wp_localize_script( 'tan-admin-js', 'tandata', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tan_nonce' ),
		) );
	}

	/**
	 * Intercept admin_notices hooks.
	 */
	public function intercept_notices() {
		global $wp_filter;

		if ( ! isset( $wp_filter['admin_notices'] ) ) {
			return;
		}

		foreach ( $wp_filter['admin_notices'] as $priority => $callbacks ) {
			foreach ( $callbacks as $callback_id => $callback_data ) {
				$callback = $callback_data['function'];
				
				// Identify source.
				$source = $this->identify_source( $callback );
				
				// Skip if source is this plugin itself to avoid infinite loops or self-wrapping.
				// We check against the current directory name.
				if ( basename( untrailingslashit( TAN_PLUGIN_DIR ) ) === $source['slug'] ) {
					continue;
				}

				// Replace callback with our wrapper.
				// We need to modify the global $wp_filter directly.
				// This is risky but standard for this kind of "interceptor" plugin.
				$wp_filter['admin_notices']->callbacks[$priority][$callback_id]['function'] = function() use ( $callback, $source ) {
					$this->wrapped_callback( $callback, $source );
				};
			}
		}
	}

	/**
	 * Wrapped callback function.
	 * 
	 * @param callable $original_callback The original callback function.
	 * @param array    $source            Source information.
	 */
	public function wrapped_callback( $original_callback, $source ) {
		ob_start();
		call_user_func( $original_callback );
		$content = ob_get_clean();

		if ( empty( trim( $content ) ) ) {
			return;
		}

		// Calculate hash.
		// Normalize content to improve stability (ignore dynamic URLs/nonces in attributes).
		$normalized_content = wp_strip_all_tags( $content );
		// remove all whitespace to further normalize
		$normalized_content = preg_replace( '/\s+/', '', $normalized_content );
		$notice_hash = md5( $normalized_content );

		// Check if dismissed or snoozed.
		if ( $this->is_dismissed( $notice_hash ) ) {
			return;
		}

		// Output wrapped content.
		echo '<div class="tan-wrapper" data-source-slug="' . esc_attr( $source['slug'] ) . '" data-source-name="' . esc_attr( $source['name'] ) . '" data-hash="' . esc_attr( $notice_hash ) . '">';
		echo wp_kses_post( $content );
		echo '</div>';
	}

	/**
	 * Identify the source of the callback.
	 * 
	 * @param callable $callback The callback to identify.
	 * @return array Source info ['name', 'slug'].
	 */
	private function identify_source( $callback ) {
		$source = array(
			'name' => 'Unknown',
			'slug' => 'unknown',
		);

		try {
			if ( is_array( $callback ) ) {
				$object = $callback[0];
				if ( is_object( $object ) ) {
					$reflector = new ReflectionObject( $object );
					$filename  = $reflector->getFileName();
				} else {
					// Static method.
					$class_name = $callback[0];
					$reflector  = new ReflectionClass( $class_name );
					$filename   = $reflector->getFileName();
				}
			} elseif ( is_string( $callback ) || is_callable( $callback ) ) {
				if ( is_string( $callback ) && function_exists( $callback ) ) {
					$reflector = new ReflectionFunction( $callback );
					$filename  = $reflector->getFileName();
				} else {
					// Closure or unknown.
					// Closures are hard to trace back to a file unless we use debug backtrace during definition, 
					// but here we only have the closure object.
					// We can try ReflectionFunction on the closure.
					$reflector = new ReflectionFunction( $callback );
					$filename  = $reflector->getFileName();
				}
			}
		} catch ( Exception $e ) {
			$filename = '';
		}

		if ( ! empty( $filename ) ) {
			// Try to find plugin or theme info from path.
			$content_dir = wp_normalize_path( WP_CONTENT_DIR );
			$filename    = wp_normalize_path( $filename );

			if ( strpos( $filename, $content_dir . '/plugins/' ) !== false ) {
				$rel_path = str_replace( $content_dir . '/plugins/', '', $filename );
				$parts    = explode( '/', $rel_path );
				$slug     = $parts[0];
				$source   = array(
					'name' => ucwords( str_replace( '-', ' ', $slug ) ), // Fallback name
					'slug' => $slug,
				);
				
				// Attempt to get better name from main plugin file if possible (expensive, maybe optimize later).
			} elseif ( strpos( $filename, $content_dir . '/themes/' ) !== false ) {
				$rel_path = str_replace( $content_dir . '/themes/', '', $filename );
				$parts    = explode( '/', $rel_path );
				$slug     = $parts[0];
				$source   = array(
					'name' => ucwords( str_replace( '-', ' ', $slug ) ),
					'slug' => $slug,
				);
			} else {
				$source = array(
					'name' => 'WordPress Core / Other',
					'slug' => 'wp-core',
				);
			}
		}

		return $source;
	}

	/**
	 * Check if notice is dismissed.
	 * 
	 * @param string $hash Notice hash.
	 * @return bool True if dismissed.
	 */
	private function is_dismissed( $hash ) {
		// Use get_option for global settings
		$dismissed = get_option( 'tan_dismissed_notices', array() );
		
		if ( ! is_array( $dismissed ) ) {
			return false;
		}

		if ( isset( $dismissed[ $hash ] ) ) {
			$expiration = $dismissed[ $hash ];
			if ( $expiration === 'forever' ) {
				return true;
			}
			if ( is_numeric( $expiration ) && $expiration > time() ) {
				return true;
			}
			// Expired, clean up.
			unset( $dismissed[ $hash ] );
			update_option( 'tan_dismissed_notices', $dismissed );
		}

		return false;
	}

	/**
	 * Handle AJAX dismiss/snooze.
	 */
	public function handle_ajax_dismiss() {
		check_ajax_referer( 'tan_nonce', 'nonce' );

		if ( ! isset( $_POST['hash'] ) || ! isset( $_POST['type'] ) ) {
			wp_send_json_error( 'Missing parameters' );
		}

		$hash = sanitize_text_field( $_POST['hash'] );
		$type = sanitize_text_field( $_POST['type'] ); // 'forever' or 'snooze' (e.g., 1 day)
		
		$expiration = 'forever';
		if ( 'snooze' === $type ) {
			$expiration = time() + DAY_IN_SECONDS; // Default 1 day for now
		}

		// Use get_option/update_option for global settings
		$dismissed = get_option( 'tan_dismissed_notices', array() );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		$dismissed[ $hash ] = $expiration;
		update_option( 'tan_dismissed_notices', $dismissed );

		wp_send_json_success();
	}
}
