<?php
/**
 * Settings page and option handling.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Settings
 *
 * Registers the admin settings screen, sanitizes input, and provides
 * read access to Ask Adam Doc It configuration.
 *
 * The OpenAI API key is stored with a base64 wrapper. This is
 * OBFUSCATION, NOT ENCRYPTION — it prevents casual disclosure of
 * the raw key when an admin happens to browse the wp_options table
 * or a database dump. For real protection, set the key from an
 * environment variable or a constant in wp-config.php and treat the
 * site as a trusted compute environment.
 */
class AADI_Settings {

	/**
	 * Option name for the settings array.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aadi_settings';

	/**
	 * Settings group / page slug used by the Settings API.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'aadi_settings_group';

	/**
	 * Page slug used by do_settings_sections().
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'aadi-settings';

	/**
	 * Default values for every settings field.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'openai_api_key'      => '',
			'ai_enabled_override' => true,
			'summarize_enabled'   => false,
			'max_image_size'      => 5,
			'max_video_size'      => 50,
			'max_audio_size'      => 20,
			'delete_on_uninstall' => false,
			'allowed_roles'       => array( 'administrator', 'editor' ),
		);
	}

	/**
	 * Constructor. Intentionally side-effect-free — registration is hooked
	 * in AADI_Loader::define_admin_hooks().
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
			'aadi_section_ai',
			__( 'AI Configuration', 'ask-adam-doc-it' ),
			array( $this, 'render_section_ai' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'aadi_section_uploads',
			__( 'Upload Settings', 'ask-adam-doc-it' ),
			array( $this, 'render_section_uploads' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'aadi_section_access',
			__( 'Access Control', 'ask-adam-doc-it' ),
			array( $this, 'render_section_access' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			'aadi_section_advanced',
			__( 'Advanced', 'ask-adam-doc-it' ),
			array( $this, 'render_section_advanced' ),
			self::PAGE_SLUG
		);

		// AI section.
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'ask-adam-doc-it' ),
			array( $this, 'render_field_api_key' ),
			self::PAGE_SLUG,
			'aadi_section_ai'
		);
		add_settings_field(
			'ai_enabled_override',
			__( 'Enable AI Features', 'ask-adam-doc-it' ),
			array( $this, 'render_field_ai_enabled_override' ),
			self::PAGE_SLUG,
			'aadi_section_ai'
		);
		add_settings_field(
			'summarize_enabled',
			__( 'Document Summarize Button', 'ask-adam-doc-it' ),
			array( $this, 'render_field_summarize_enabled' ),
			self::PAGE_SLUG,
			'aadi_section_ai'
		);

		// Upload section.
		add_settings_field(
			'max_image_size',
			__( 'Max Image Size (MB)', 'ask-adam-doc-it' ),
			array( $this, 'render_field_max_image_size' ),
			self::PAGE_SLUG,
			'aadi_section_uploads'
		);
		add_settings_field(
			'max_video_size',
			__( 'Max Video Size (MB)', 'ask-adam-doc-it' ),
			array( $this, 'render_field_max_video_size' ),
			self::PAGE_SLUG,
			'aadi_section_uploads'
		);
		add_settings_field(
			'max_audio_size',
			__( 'Max Audio Size (MB)', 'ask-adam-doc-it' ),
			array( $this, 'render_field_max_audio_size' ),
			self::PAGE_SLUG,
			'aadi_section_uploads'
		);

		// Access section.
		add_settings_field(
			'allowed_roles',
			__( 'Roles allowed to upload', 'ask-adam-doc-it' ),
			array( $this, 'render_field_allowed_roles' ),
			self::PAGE_SLUG,
			'aadi_section_access'
		);

		// Advanced section.
		add_settings_field(
			'delete_on_uninstall',
			__( 'Delete data on uninstall', 'ask-adam-doc-it' ),
			array( $this, 'render_field_delete_on_uninstall' ),
			self::PAGE_SLUG,
			'aadi_section_advanced'
		);
	}

	/**
	 * Section description renderers.
	 */
	public function render_section_ai() {
		echo '<p>' . esc_html__( 'Configure the OpenAI API key used to generate embeddings and answer questions.', 'ask-adam-doc-it' ) . '</p>';
	}
	public function render_section_uploads() {
		echo '<p>' . esc_html__( 'Per-type file size limits applied on top of the WordPress upload limit.', 'ask-adam-doc-it' ) . '</p>';
	}
	public function render_section_access() {
		echo '<p>' . esc_html__( 'Choose which user roles may upload and manage documents.', 'ask-adam-doc-it' ) . '</p>';
	}
	public function render_section_advanced() {
		echo '<p>' . esc_html__( 'Maintenance and removal behavior.', 'ask-adam-doc-it' ) . '</p>';
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
			'<input type="password" id="aadi_openai_api_key" name="%1$s[openai_api_key]" value="" autocomplete="off" spellcheck="false" autocapitalize="none" autocorrect="off" class="regular-text" placeholder="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $placeholder )
		);
		printf(
			' <button type="button" class="button button-secondary" id="aadi_toggle_api_key" aria-controls="aadi_openai_api_key" aria-pressed="false" data-show-label="%1$s" data-hide-label="%2$s">%1$s</button>',
			esc_attr__( 'Show', 'ask-adam-doc-it' ),
			esc_attr__( 'Hide', 'ask-adam-doc-it' )
		);
		?>
		<script>
		( function () {
			var toggle = document.getElementById( 'aadi_toggle_api_key' );
			var field  = document.getElementById( 'aadi_openai_api_key' );
			if ( ! toggle || ! field ) {
				return;
			}
			toggle.addEventListener( 'click', function () {
				var show = 'password' === field.getAttribute( 'type' );
				field.setAttribute( 'type', show ? 'text' : 'password' );
				toggle.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
				toggle.textContent = show ? toggle.getAttribute( 'data-hide-label' ) : toggle.getAttribute( 'data-show-label' );
			} );
		}() );
		</script>
		<?php
		if ( '' !== $masked ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: masked API key suffix. */
					__( 'A key is currently stored (ends in %s). Leave blank and save to keep it; replace to update.', 'ask-adam-doc-it' ),
					$masked
				)
			) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Paste a key beginning with "sk-". Stored base64-obfuscated; use a wp-config constant for real secrecy.', 'ask-adam-doc-it' ) . '</p>';
		}
	}

	public function render_field_ai_enabled_override() {
		$value = (bool) self::get_option( 'ai_enabled_override', true );
		printf(
			'<label><input type="checkbox" name="%1$s[ai_enabled_override]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'AI features active when an API key is present', 'ask-adam-doc-it' )
		);
		echo '<p class="description">' . esc_html__( 'Uncheck to disable AI even if an API key is stored.', 'ask-adam-doc-it' ) . '</p>';
	}

	public function render_field_summarize_enabled() {
		$value = (bool) self::get_option( 'summarize_enabled', false );
		printf(
			'<label><input type="checkbox" name="%1$s[summarize_enabled]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $value, true, false ),
			esc_html__( 'Show a "Summarize" button on document cards and single document pages', 'ask-adam-doc-it' )
		);
		echo '<p class="description">' . esc_html__( 'Generates a short AI summary on demand. Only active when AI features are enabled above.', 'ask-adam-doc-it' ) . '</p>';
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
			esc_html__( 'MB', 'ask-adam-doc-it' )
		);
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: minimum MB, 2: maximum MB */
					__( 'Allowed range: %1$d–%2$d MB.', 'ask-adam-doc-it' ),
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
				'<label class="aadi-role-option"><input type="checkbox" name="%1$s[allowed_roles][]" value="%2$s" %3$s /> %4$s</label>',
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
			esc_html__( 'Permanently delete all Ask Adam Doc It data when the plugin is uninstalled', 'ask-adam-doc-it' )
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

		$valid_prefixes   = array( 'sk-', 'sk-proj-', 'sk-ant-' );
		$has_valid_prefix = false;
		foreach ( $valid_prefixes as $prefix ) {
			if ( 0 === strpos( $value, $prefix ) ) {
				$has_valid_prefix = true;
				break;
			}
		}

		if ( ! $has_valid_prefix ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Ask Adam Doc It: rejected API key (missing supported prefix).' );
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
			add_settings_error( self::OPTION_NAME, 'aadi_invalid_input', __( 'Settings input was malformed and has been reset to defaults.', 'ask-adam-doc-it' ) );
			return $defaults;
		}

		// API key — sanitize, then base64-encode for storage.
		// An empty submission means "keep the existing key" so admins can
		// re-save other settings without re-entering the key each time.
		$key_accepted = false;
		if ( array_key_exists( 'openai_api_key', $input ) ) {
			$raw_input = (string) $input['openai_api_key'];
			$existing  = get_option( self::OPTION_NAME, array() );
			$old_key   = is_array( $existing ) && isset( $existing['openai_api_key'] ) ? $existing['openai_api_key'] : '';

			if ( '' === trim( $raw_input ) ) {
				$sanitized['openai_api_key'] = $old_key;
			} else {
				$plain = $this->sanitize_api_key( $raw_input );
				if ( '' === $plain ) {
					add_settings_error( self::OPTION_NAME, 'aadi_bad_api_key', __( 'The OpenAI API key was not saved. Keys must begin with "sk-" or "sk-proj-".', 'ask-adam-doc-it' ) );
					$sanitized['openai_api_key'] = $old_key;
				} else {
					$sanitized['openai_api_key'] = base64_encode( $plain );
					$key_accepted                = true;
				}
			}
		}

		// Booleans (checkbox unchecked = not present in $input).
		$sanitized['ai_enabled_override'] = ! empty( $input['ai_enabled_override'] );
		$sanitized['summarize_enabled']   = ! empty( $input['summarize_enabled'] );
		$sanitized['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		// Integer size limits with bounds.
		$sanitized['max_image_size'] = $this->sanitize_bounded_int( $input, 'max_image_size', __( 'Max image size (MB)', 'ask-adam-doc-it' ), $defaults['max_image_size'], 1, 50 );
		$sanitized['max_video_size'] = $this->sanitize_bounded_int( $input, 'max_video_size', __( 'Max video size (MB)', 'ask-adam-doc-it' ), $defaults['max_video_size'], 1, 500 );
		$sanitized['max_audio_size'] = $this->sanitize_bounded_int( $input, 'max_audio_size', __( 'Max audio size (MB)', 'ask-adam-doc-it' ), $defaults['max_audio_size'], 1, 100 );

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
			add_settings_error( self::OPTION_NAME, 'aadi_empty_roles', __( 'At least one role must be allowed. Reset to administrator only.', 'ask-adam-doc-it' ), 'updated' );
		}
		$sanitized['allowed_roles'] = array_values( array_unique( $roles ) );

		// Clear the circuit breaker only when the admin explicitly submitted
		// a key that passed sanitization. Re-pasting the same valid key counts
		// as intent to retry, but unrelated settings saves (which preserve the
		// stored key) and rejected replacements must not reset the breaker —
		// otherwise a known-bad key would be retried on every save.
		if ( $key_accepted ) {
			delete_option( 'aadi_openai_auth_failed' );
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
				'aadi_out_of_range_' . $key,
				sprintf(
					/* translators: 1: human-readable field label, 2: min, 3: max */
					__( '%1$s must be between %2$d and %3$d. Value was clamped.', 'ask-adam-doc-it' ),
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
	 * Wiring this through the `updated_option_aadi_settings` /
	 * `added_option_aadi_settings` actions guarantees the mirror tracks the
	 * actually-stored value on every save path — including resets to
	 * defaults caused by malformed input, programmatic updates, and imports
	 * — not just successful runs through sanitize_settings().
	 *
	 * @return void
	 */
	public function sync_uninstall_flag() {
		$flag = (bool) self::get_option( 'delete_on_uninstall', false );
		if ( $flag ) {
			update_option( 'aadi_delete_data_on_uninstall', 1 );
		} else {
			delete_option( 'aadi_delete_data_on_uninstall' );
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
					error_log( 'Ask Adam Doc It: stored OpenAI API key is not base64-encoded; treating as legacy plaintext. Re-save the key to obfuscate it.' );
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
	 * Whether the on-demand document Summarize button should be available.
	 *
	 * Requires AI to be active AND the admin to have opted in.
	 *
	 * @return bool
	 */
	public static function is_summarize_enabled() {
		// Hide the button when the OpenAI auth circuit breaker is tripped —
		// every click would fail until a working key is saved.
		if ( get_option( 'aadi_openai_auth_failed' ) ) {
			return false;
		}
		return self::is_ai_enabled() && (bool) self::get_option( 'summarize_enabled', false );
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
			<h1><?php esc_html_e( 'Ask Adam Doc It Settings', 'ask-adam-doc-it' ); ?></h1>
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
