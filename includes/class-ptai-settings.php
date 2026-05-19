<?php
/**
 * Settings page and option handling.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Settings
 *
 * Registers the admin settings screen, sanitizes input, and provides
 * read access to PaperTrail AI configuration.
 *
 * The OpenAI API key is stored with a base64 wrapper. This is
 * OBFUSCATION, NOT ENCRYPTION — it prevents casual disclosure of
 * the raw key when an admin happens to browse the wp_options table
 * or a database dump. For real protection, set the key from an
 * environment variable or a constant in wp-config.php and treat the
 * site as a trusted compute environment.
 */
class PTAI_Settings {

	/**
	 * Option name for the settings array.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'ptai_settings';

	/**
	 * Settings group / page slug used by the Settings API.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'ptai_settings_group';

	/**
	 * Page slug used by do_settings_sections().
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'ptai-settings';

	/**
	 * Default values for every settings field.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'openai_api_key'      => '',
			'ai_enabled_override' => true,
			'max_image_size'      => 5,
			'max_video_size'      => 50,
			'max_audio_size'      => 20,
			'delete_on_uninstall' => false,
			'allowed_roles'       => array( 'administrator', 'editor' ),
		);
	}

	/**
	 * Constructor. Intentionally side-effect-free — registration is hooked
	 * in PTAI_Loader::define_admin_hooks().
	 */
	public function __construct() {}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'ptai_section_ai',
			__( 'AI Configuration', 'papertrail-ai' ),
			array( $this, 'render_section_ai' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'ptai_section_uploads',
			__( 'Upload Settings', 'papertrail-ai' ),
			array( $this, 'render_section_uploads' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'ptai_section_access',
			__( 'Access Control', 'papertrail-ai' ),
			array( $this, 'render_section_access' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'ptai_section_advanced',
			__( 'Advanced', 'papertrail-ai' ),
			array( $this, 'render_section_advanced' ),
			self::PAGE_SLUG
		);

		// AI section.
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'papertrail-ai' ),
			array( $this, 'render_field_api_key' ),
			self::PAGE_SLUG,
			'ptai_section_ai'
		);
		add_settings_field(
			'ai_enabled_override',
			__( 'Enable AI Features', 'papertrail-ai' ),
			array( $this, 'render_field_ai_enabled_override' ),
			self::PAGE_SLUG,
			'ptai_section_ai'
		);

		// Upload section.
		add_settings_field(
			'max_image_size',
			__( 'Max Image Size (MB)', 'papertrail-ai' ),
			array( $this, 'render_field_max_image_size' ),
			self::PAGE_SLUG,
			'ptai_section_uploads'
		);
		add_settings_field(
			'max_video_size',
			__( 'Max Video Size (MB)', 'papertrail-ai' ),
			array( $this, 'render_field_max_video_size' ),
			self::PAGE_SLUG,
			'ptai_section_uploads'
		);
		add_settings_field(
			'max_audio_size',
			__( 'Max Audio Size (MB)', 'papertrail-ai' ),
			array( $this, 'render_field_max_audio_size' ),
			self::PAGE_SLUG,
			'ptai_section_uploads'
		);

		// Access section.
		add_settings_field(
			'allowed_roles',
			__( 'Roles allowed to upload', 'papertrail-ai' ),
			array( $this, 'render_field_allowed_roles' ),
			self::PAGE_SLUG,
			'ptai_section_access'
		);

		// Advanced section.
		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete data on uninstall', 'papertrail-ai' ),
			array( $this, 'render_field_delete_on_uninstall' ),
			self::PAGE_SLUG,
			'ptai_section_advanced'
		);
	}

	/**
	 * Section description renderers.
	 */
	public function render_section_ai() {
		echo '<p>' . esc_html__( 'Configure the OpenAI API key used to generate embeddings and answer questions.', 'papertrail-ai' ) . '</p>';
	}
	public function render_section_uploads() {
		echo '<p>' . esc_html__( 'Per-type file size limits applied on top of the WordPress upload limit.', 'papertrail-ai' ) . '</p>';
	}
	public function render_section_access() {
		echo '<p>' . esc_html__( 'Choose which user roles may upload and manage documents.', 'papertrail-ai' ) . '</p>';
	}
	public function render_section_advanced() {
		echo '<p>' . esc_html__( 'Maintenance and removal behavior.', 'papertrail-ai' ) . '</p>';
	}

	/**
	 * Field renderers.
	 */
	public function render_field_api_key() {
		$stored = (string) self::get_option( 'openai_api_key', '' );
		$masked = '' !== $stored ? str_repeat( '•', 8 ) . substr( $stored, -4 ) : '';
		// Never render the raw key into HTML. The submit handler treats an
		// empty submission as "keep the existing key".
		$placeholder = '' !== $stored ? '••••••••••••' : 'sk-...';
		printf(
			'<input type="password" id="ptai_openai_api_key" name="%1$s[openai_api_key]" value="" autocomplete="new-password" class="regular-text" placeholder="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $placeholder )
		);
		if ( '' !== $masked ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: masked API key suffix. */
					__( 'A key is currently stored (ends in %s). Leave blank and save to keep it; replace to update.', 'papertrail-ai' ),
					$masked
				)
			) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Paste a key beginning with "sk-". Stored base64-obfuscated; use a wp-config constant for real secrecy.', 'papertrail-ai' ) . '</p>';
		}
	}

	public function render_field_ai_enabled_override() {
		$value = (bool) self::get_option( 'ai_enabled_override', true );
		printf(
			'<label><input type="checkbox" name="%1$s[ai_enabled_override]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'AI features active when an API key is present', 'papertrail-ai' )
		);
		echo '<p class="description">' . esc_html__( 'Uncheck to disable AI even if an API key is stored.', 'papertrail-ai' ) . '</p>';
	}

	public function render_field_max_image_size() {
		$this->render_size_field( 'max_image_size', 5, 1, 50 );
	}
	public function render_field_max_video_size() {
		$this->render_size_field( 'max_video_size', 50, 1, 500 );
	}
	public function render_field_max_audio_size() {
		$this->render_size_field( 'max_audio_size', 20, 1, 100 );
	}

	/**
	 * Shared numeric size renderer.
	 *
	 * @param string $key     Setting key.
	 * @param int    $default Default in MB.
	 * @param int    $min     Minimum allowed value.
	 * @param int    $max     Maximum allowed value.
	 */
	private function render_size_field( $key, $default, $min, $max ) {
		$value = (int) self::get_option( $key, $default );
		printf(
			'<input type="number" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" step="1" class="small-text" /> %6$s',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_html__( 'MB', 'papertrail-ai' )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: minimum MB, 2: maximum MB */
					__( 'Allowed range: %1$d–%2$d MB.', 'papertrail-ai' ),
					$min,
					$max
				)
			)
		);
	}

	public function render_field_allowed_roles() {
		$selected = (array) self::get_option( 'allowed_roles', array( 'administrator', 'editor' ) );
		$roles    = get_editable_roles();

		echo '<fieldset>';
		foreach ( $roles as $role_key => $role ) {
			printf(
				'<label class="ptai-role-option"><input type="checkbox" name="%1$s[allowed_roles][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $role_key ),
				checked( in_array( $role_key, $selected, true ), true, false ),
				esc_html( translate_user_role( $role['name'] ) )
			);
		}
		echo '</fieldset>';
	}

	public function render_field_delete_on_uninstall() {
		$value = (bool) self::get_option( 'delete_on_uninstall', false );
		printf(
			'<label><input type="checkbox" name="%1$s[delete_on_uninstall]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'Permanently delete all PaperTrail AI data when the plugin is uninstalled', 'papertrail-ai' )
		);
	}

	/**
	 * Sanitize the OpenAI API key for standalone use.
	 *
	 * Returns the cleaned plaintext key (the form sanitizer applies
	 * base64 wrapping separately). Empty string if invalid.
	 *
	 * @param string $value Raw API key.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( sanitize_text_field( $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( 0 !== strpos( $value, 'sk-' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'PaperTrail AI: rejected API key (missing sk- prefix).' );
			}
			return '';
		}

		return $value;
	}

	/**
	 * Sanitize the full settings array submitted from the form.
	 *
	 * @param mixed $input Raw input.
	 * @return array Sanitized settings ready for storage.
	 */
	public function sanitize_settings( $input ) {
		$defaults  = self::get_defaults();
		$sanitized = $defaults;

		if ( ! is_array( $input ) ) {
			add_settings_error( self::OPTION_NAME, 'ptai_invalid_input', __( 'Settings input was malformed and has been reset to defaults.', 'papertrail-ai' ) );
			return $defaults;
		}

		// API key — sanitize, then base64-encode for storage.
		// An empty submission means "keep the existing key" so admins can
		// re-save other settings without re-entering the key each time.
		$key_changed = false;
		if ( array_key_exists( 'openai_api_key', $input ) ) {
			$raw_input = (string) $input['openai_api_key'];
			$existing  = get_option( self::OPTION_NAME, array() );
			$old_key   = is_array( $existing ) && isset( $existing['openai_api_key'] ) ? $existing['openai_api_key'] : '';

			if ( '' === trim( $raw_input ) ) {
				$sanitized['openai_api_key'] = $old_key;
			} else {
				$plain = $this->sanitize_api_key( $raw_input );
				if ( '' === $plain ) {
					add_settings_error( self::OPTION_NAME, 'ptai_bad_api_key', __( 'The OpenAI API key was not saved. Keys must begin with "sk-".', 'papertrail-ai' ) );
					$sanitized['openai_api_key'] = $old_key;
				} else {
					$sanitized['openai_api_key'] = base64_encode( $plain );
					if ( $sanitized['openai_api_key'] !== $old_key ) {
						$key_changed = true;
					}
				}
			}
		}

		// Booleans (checkbox unchecked = not present in $input).
		$sanitized['ai_enabled_override'] = ! empty( $input['ai_enabled_override'] );
		$sanitized['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		// Integer size limits with bounds.
		$sanitized['max_image_size'] = $this->sanitize_bounded_int( $input, 'max_image_size', __( 'Max image size (MB)', 'papertrail-ai' ), $defaults['max_image_size'], 1, 50 );
		$sanitized['max_video_size'] = $this->sanitize_bounded_int( $input, 'max_video_size', __( 'Max video size (MB)', 'papertrail-ai' ), $defaults['max_video_size'], 1, 500 );
		$sanitized['max_audio_size'] = $this->sanitize_bounded_int( $input, 'max_audio_size', __( 'Max audio size (MB)', 'papertrail-ai' ), $defaults['max_audio_size'], 1, 100 );

		// Allowed roles — validate against editable roles.
		$valid_roles = array_keys( get_editable_roles() );
		$incoming    = isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ? $input['allowed_roles'] : array();
		$roles       = array();
		foreach ( $incoming as $role ) {
			$role = sanitize_key( $role );
			if ( in_array( $role, $valid_roles, true ) ) {
				$roles[] = $role;
			}
		}
		if ( empty( $roles ) ) {
			$roles = array( 'administrator' );
			add_settings_error( self::OPTION_NAME, 'ptai_empty_roles', __( 'At least one role must be allowed. Reset to administrator only.', 'papertrail-ai' ), 'updated' );
		}
		$sanitized['allowed_roles'] = array_values( array_unique( $roles ) );

		// Clear the OpenAI auth-failure circuit breaker only when the
		// stored key actually changed. Resetting on every save would
		// retry against a known-bad key after unrelated edits.
		if ( $key_changed ) {
			delete_option( 'ptai_openai_auth_failed' );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a bounded integer from an input array.
	 *
	 * @param array  $input   Raw input.
	 * @param string $key     Key to read.
	 * @param string $label   Human-readable field name (already translated).
	 * @param int    $default Default if missing/invalid.
	 * @param int    $min     Minimum.
	 * @param int    $max     Maximum.
	 * @return int
	 */
	private function sanitize_bounded_int( $input, $key, $label, $default, $min, $max ) {
		if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
			return $default;
		}
		$value = absint( $input[ $key ] );

		if ( $value < $min || $value > $max ) {
			add_settings_error(
				self::OPTION_NAME,
				'ptai_out_of_range_' . $key,
				sprintf(
					/* translators: 1: human-readable field label, 2: min, 3: max */
					__( '%1$s must be between %2$d and %3$d. Value was clamped.', 'papertrail-ai' ),
					$label,
					$min,
					$max
				)
			);
			$value = max( $min, min( $max, $value ) );
		}

		return $value;
	}

	/**
	 * Mirror the `delete_on_uninstall` flag to a standalone option.
	 *
	 * uninstall.php runs in a stripped-down WP bootstrap that may not load
	 * this plugin's classes, so it reads the standalone option directly.
	 * Wiring this through the `updated_option_ptai_settings` /
	 * `added_option_ptai_settings` actions guarantees the mirror tracks the
	 * actually-stored value on every save path — including resets to
	 * defaults caused by malformed input, programmatic updates, and imports
	 * — not just successful runs through sanitize_settings().
	 *
	 * @return void
	 */
	public function sync_uninstall_flag() {
		$flag = (bool) self::get_option( 'delete_on_uninstall', false );
		if ( $flag ) {
			update_option( 'ptai_delete_data_on_uninstall', 1 );
		} else {
			delete_option( 'ptai_delete_data_on_uninstall' );
		}
	}

	/**
	 * Get a single option value.
	 *
	 * Transparently base64-decodes the OpenAI API key.
	 *
	 * @param string $key            Setting key.
	 * @param mixed  $default_value  Default if not set.
	 * @return mixed
	 */
	public static function get_option( $key, $default_value = null ) {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( 'openai_api_key' === $key ) {
			if ( empty( $settings['openai_api_key'] ) ) {
				return null === $default_value ? '' : $default_value;
			}
			$stored = (string) $settings['openai_api_key'];

			// Strict base64 decode. If the stored value isn't valid base64,
			// fall back to returning it as-is — most likely a legacy
			// plaintext key from before obfuscation was introduced. This
			// preserves the admin's data rather than silently losing it.
			$decoded = base64_decode( $stored, true );
			if ( false === $decoded ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'PaperTrail AI: stored OpenAI API key is not base64-encoded; treating as legacy plaintext. Re-save the key to obfuscate it.' );
				}
				return $stored;
			}
			return $decoded;
		}

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		$defaults = self::get_defaults();
		if ( array_key_exists( $key, $defaults ) ) {
			return null === $default_value ? $defaults[ $key ] : $default_value;
		}

		return $default_value;
	}

	/**
	 * Whether AI features should be considered active.
	 *
	 * @return bool
	 */
	public static function is_ai_enabled() {
		$key = (string) self::get_option( 'openai_api_key', '' );
		if ( '' === $key ) {
			return false;
		}

		$override = self::get_option( 'ai_enabled_override', true );
		if ( false === $override ) {
			return false;
		}

		return true;
	}

	/**
	 * Allowed MIME types, grouped or flattened.
	 *
	 * @param string|null $group Optional. One of 'documents', 'images', 'video', 'audio'.
	 * @return array<string,string>|array<string,array<string,string>>
	 */
	public static function get_allowed_mime_types( $group = null ) {
		$groups = array(
			'documents' => array(
				'pdf'        => 'application/pdf',
				'doc'        => 'application/msword',
				'docx'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'xls'        => 'application/vnd.ms-excel',
				'xlsx'       => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ppt'        => 'application/vnd.ms-powerpoint',
				'pptx'       => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'txt'        => 'text/plain',
				'csv'        => 'text/csv',
			),
			'images'    => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'     => 'image/png',
				'gif'     => 'image/gif',
				'webp'    => 'image/webp',
				// SVG intentionally excluded — stored XSS vector.
				// SVG support requires dedicated sanitization — available in Pro.
			),
			'video'     => array(
				'mp4'  => 'video/mp4',
				'webm' => 'video/webm',
				'ogv'  => 'video/ogg',
			),
			'audio'     => array(
				'mp3' => 'audio/mpeg',
				'wav' => 'audio/wav',
				'oga' => 'audio/ogg',
			),
		);

		if ( null !== $group ) {
			return isset( $groups[ $group ] ) ? $groups[ $group ] : array();
		}

		$merged = array();
		foreach ( $groups as $set ) {
			$merged = array_merge( $merged, $set );
		}
		return $merged;
	}

	/**
	 * Size limit in bytes for a given MIME type.
	 *
	 * @param string $mime_type MIME type to check.
	 * @return int Bytes.
	 */
	public static function get_size_limit( $mime_type ) {
		$mime_type = strtolower( (string) $mime_type );
		$mb        = 1024 * 1024;

		if ( 0 === strpos( $mime_type, 'image/' ) ) {
			return ( (int) self::get_option( 'max_image_size', 5 ) ) * $mb;
		}
		if ( 0 === strpos( $mime_type, 'video/' ) ) {
			return ( (int) self::get_option( 'max_video_size', 50 ) ) * $mb;
		}
		if ( 0 === strpos( $mime_type, 'audio/' ) ) {
			return ( (int) self::get_option( 'max_audio_size', 20 ) ) * $mb;
		}

		return (int) wp_max_upload_size();
	}

	/**
	 * Render the settings page HTML.
	 *
	 * @return void
	 */
	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PaperTrail AI Settings', 'papertrail-ai' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
