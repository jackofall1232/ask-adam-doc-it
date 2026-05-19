<?php
/**
 * Plugin Name:       Ask Adam Doc It
 * Plugin URI:        https://github.com/jackofall1232/ask-adam-doc-it
 * Description:       AI-powered document library for WordPress. Upload, organize, and semantically search files using OpenAI embeddings.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            jackofall1232
 * Author URI:        https://github.com/jackofall1232
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ask-adam-doc-it
 * Domain Path:       /languages
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'AADI_VERSION', '1.0.0' );
define( 'AADI_PLUGIN_FILE', __FILE__ );
define( 'AADI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AADI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AADI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'AADI_TEXT_DOMAIN', 'ask-adam-doc-it' );
define( 'AADI_CPT', 'aadi_file' );
define( 'AADI_TAXONOMY', 'aadi_category' );
define( 'AADI_DB_VERSION', '1.0' );
define( 'AADI_MIN_PHP', '7.4' );
define( 'AADI_MIN_WP', '6.0' );

/**
 * Environment compatibility check.
 *
 * @return bool
 */
function aadi_check_environment() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, AADI_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: required PHP version, 2: current PHP version */
							__( 'Ask Adam Doc It requires PHP %1$s or higher. You are running PHP %2$s.', 'ask-adam-doc-it' ),
							AADI_MIN_PHP,
							PHP_VERSION
						)
					)
				);
			}
		);
		return false;
	}

	if ( isset( $wp_version ) && version_compare( $wp_version, AADI_MIN_WP, '<' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $wp_version ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: required WP version, 2: current WP version */
							__( 'Ask Adam Doc It requires WordPress %1$s or higher. You are running WordPress %2$s.', 'ask-adam-doc-it' ),
							AADI_MIN_WP,
							$wp_version
						)
					)
				);
			}
		);
		return false;
	}

	return true;
}

/**
 * Load plugin files.
 */
function aadi_load_files() {
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-loader.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-cpt.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-settings.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-openai.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-embeddings.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-search.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-shortcode.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-block.php';
	require_once AADI_PLUGIN_DIR . 'includes/class-aadi-pro.php';
	require_once AADI_PLUGIN_DIR . 'admin/class-aadi-admin.php';
	require_once AADI_PLUGIN_DIR . 'public/class-aadi-public.php';
}

/**
 * Activation hook.
 */
function aadi_activate() {
	if ( ! aadi_check_environment() ) {
		return;
	}

	aadi_load_files();

	if ( class_exists( 'AADI_CPT' ) ) {
		$cpt = new AADI_CPT();
		$cpt->register_post_type();
		$cpt->register_taxonomy();
	}

	flush_rewrite_rules();

	update_option( 'aadi_db_version', AADI_DB_VERSION );
	update_option( 'aadi_activated_at', time() );
}
register_activation_hook( __FILE__, 'aadi_activate' );

/**
 * Deactivation hook.
 */
function aadi_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'aadi_deactivate' );

/**
 * Bootstrap the plugin.
 *
 * AADI_Loader is the single source of truth for all hook registration.
 * This function does the bare minimum: environment gate, file loading,
 * text domain, and one loader instantiation.
 */
function aadi_bootstrap() {
	if ( ! aadi_check_environment() ) {
		return;
	}

	aadi_load_files();

	// Translation loading is handled automatically by WordPress for plugins
	// hosted on WordPress.org (since WP 4.6). The Text Domain and Domain Path
	// headers in the plugin file header are sufficient. Calling
	// load_plugin_textdomain() here is explicitly discouraged by Plugin Check
	// and is intentionally omitted.
	$loader = new AADI_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'aadi_bootstrap' );

/**
 * Register the file download and search REST endpoints.
 *
 *   GET /wp-json/ask-adam-doc-it/v1/download/{id}
 *   GET /wp-json/ask-adam-doc-it/v1/search?q=...
 */
add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'ask-adam-doc-it/v1',
			'/download/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'aadi_handle_download',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'ask-adam-doc-it/v1',
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'aadi_handle_search',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'        => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $param ) {
							return is_string( $param ) && strlen( $param ) <= 200;
						},
					),
					'category' => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'mode'     => array(
						'required'          => false,
						'default'           => 'auto',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $param ) {
							return in_array( $param, array( 'auto', 'ai', 'core' ), true );
						},
					),
				),
			)
		);
	}
);

/**
 * Handle a file download request.
 *
 * Verifies the post, increments the download counter, and 302-redirects
 * to the underlying attachment URL. Does not log IP, user ID, or any
 * other PII — only the per-post counter and timestamp are updated.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function aadi_handle_download( WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'id' );

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'aadi_not_found', __( 'Document not found.', 'ask-adam-doc-it' ), array( 'status' => 404 ) );
	}

	if ( AADI_CPT !== $post->post_type ) {
		return new WP_Error( 'aadi_invalid_type', __( 'Requested item is not an Ask Adam Doc It document.', 'ask-adam-doc-it' ), array( 'status' => 400 ) );
	}

	if ( 'publish' !== $post->post_status ) {
		return new WP_Error( 'aadi_not_published', __( 'Document is not published.', 'ask-adam-doc-it' ), array( 'status' => 403 ) );
	}

	$file_id = (int) get_post_meta( $post_id, '_aadi_file_id', true );
	if ( $file_id <= 0 ) {
		return new WP_Error( 'aadi_no_file', __( 'No file is attached to this document.', 'ask-adam-doc-it' ), array( 'status' => 404 ) );
	}

	$attached = get_attached_file( $file_id );
	if ( ! $attached ) {
		return new WP_Error( 'aadi_no_file', __( 'Attached file is missing.', 'ask-adam-doc-it' ), array( 'status' => 404 ) );
	}

	$url = wp_get_attachment_url( $file_id );
	if ( empty( $url ) ) {
		return new WP_Error( 'aadi_file_missing', __( 'Attached file URL is unavailable.', 'ask-adam-doc-it' ), array( 'status' => 404 ) );
	}

	// Rate limiting — prevent counter inflation.
	// Token is hashed from post ID + hour window + server salt.
	// No IP addresses or user identifiers stored. GDPR friendly.
	// gmdate() — timezone-agnostic hourly bucket for rate limiting.
	$window        = gmdate( 'Y-m-d-H' );
	$salt          = wp_salt( 'auth' );
	$token         = hash( 'sha256', (string) $post_id . $window . $salt );
	$transient_key = 'aadi_dl_' . substr( $token, 0, 40 );

	if ( ! get_transient( $transient_key ) ) {
		// First hit in this hour window — count it.
		set_transient( $transient_key, 1, HOUR_IN_SECONDS );
		$count = absint( get_post_meta( $post_id, '_aadi_download_count', true ) );
		update_post_meta( $post_id, '_aadi_download_count', $count + 1 );
		update_post_meta( $post_id, '_aadi_last_downloaded', current_time( 'mysql' ) );
	}
	// File is always served regardless of rate limit state.
	// Counter accuracy is best-effort. Users always get their download.

	// Bypass the REST JSON envelope: emit a true HTTP redirect.
	// wp_safe_redirect() restricts to allowed hosts; the attachment URL is
	// always on the same site, so it is safe by definition.
	nocache_headers();
	wp_safe_redirect( $url, 302 );
	exit;
}

/**
 * Handle the REST search request.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function aadi_handle_search( WP_REST_Request $request ) {
	$q        = (string) $request->get_param( 'q' );
	$category = absint( $request->get_param( 'category' ) );
	$page     = max( 1, absint( $request->get_param( 'page' ) ) );
	$per_page = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
	$mode     = sanitize_key( (string) $request->get_param( 'mode' ) );

	if ( ! in_array( $mode, array( 'auto', 'ai', 'core' ), true ) ) {
		$mode = 'auto';
	}

	// Same hourly rate limit as the AJAX handler. Free version cap to
	// prevent the plugin from acting as an open OpenAI proxy. Skip empty
	// queries and AI-off sites so they don't consume the bucket.
	if ( class_exists( 'AADI_Public' ) ) {
		$mode = AADI_Public::apply_rate_limit( $mode, $q );
	}

	$search  = new AADI_Search();
	$results = $search->search(
		$q,
		array(
			'per_page' => $per_page,
			'page'     => $page,
			'category' => $category,
			'mode'     => $mode,
		)
	);

	$data = array(
		'posts'     => AADI_Public::format_posts_for_response( $results['posts'] ),
		'total'     => absint( $results['total'] ),
		'pages'     => absint( $results['pages'] ),
		'mode_used' => sanitize_key( $results['mode_used'] ),
		'query'     => sanitize_text_field( $q ),
	);

	return new WP_REST_Response( $data, 200 );
}
