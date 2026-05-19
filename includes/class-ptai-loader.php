<?php
/**
 * Central hook loader for PaperTrail AI.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Loader
 *
 * Single source of truth for hook registration. Every subsystem class is
 * instantiated as a side-effect-free object here; all add_action() /
 * add_filter() calls live in the define_* methods. No class self-wires
 * its own hooks via its constructor.
 */
class PTAI_Loader {

	/**
	 * Custom post type registrar.
	 *
	 * @var PTAI_CPT
	 */
	private $cpt;

	/**
	 * Settings registrar.
	 *
	 * @var PTAI_Settings
	 */
	private $settings;

	/**
	 * Admin controller (only instantiated in admin requests).
	 *
	 * @var PTAI_Admin|null
	 */
	private $admin = null;

	/**
	 * Public/frontend controller.
	 *
	 * @var PTAI_Public
	 */
	private $public_handler;

	/**
	 * Embedding lifecycle manager.
	 *
	 * @var PTAI_Embeddings
	 */
	private $embeddings;

	/**
	 * Shortcode renderer.
	 *
	 * @var PTAI_Shortcode
	 */
	private $shortcode;

	/**
	 * Block registrar.
	 *
	 * @var PTAI_Block
	 */
	private $block;

	/**
	 * Constructor. Instantiates subsystem objects only — never registers
	 * hooks here. All hook wiring lives in run() and its children.
	 */
	public function __construct() {
		$this->cpt            = new PTAI_CPT();
		$this->settings       = new PTAI_Settings();
		$this->embeddings     = new PTAI_Embeddings();
		$this->shortcode      = new PTAI_Shortcode();
		$this->block          = new PTAI_Block( $this->shortcode );
		$this->public_handler = new PTAI_Public();
		if ( is_admin() ) {
			$this->admin = new PTAI_Admin();
		}
	}

	/**
	 * Register every hook the plugin needs.
	 *
	 * @return void
	 */
	public function run() {
		$this->define_core_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_ajax_hooks();
	}

	/**
	 * Hooks that run on every request (admin + frontend).
	 *
	 * @return void
	 */
	public function define_core_hooks() {
		// CPT and taxonomy.
		add_action( 'init', array( $this->cpt, 'register_post_type' ) );
		add_action( 'init', array( $this->cpt, 'register_taxonomy' ) );
		add_action( 'init', array( $this->cpt, 'register_meta_fields' ) );
		add_action( 'init', array( $this->cpt, 'flush_rewrite_rules_if_needed' ), 99 );

		// Embedding lifecycle.
		add_action( 'save_post_' . PTAI_CPT, array( $this->embeddings, 'on_save_post' ), 20 );
		add_action( 'before_delete_post', array( $this->embeddings, 'on_delete_post' ) );
		add_action( PTAI_Embeddings::CRON_HOOK, array( $this->embeddings, 'process_embedding_job' ) );

		// Mirror the delete-on-uninstall flag to a standalone option that
		// uninstall.php can read without bootstrapping plugin classes.
		// Hooking the option-change actions (rather than embedding the
		// mirror inside sanitize_settings) ensures the mirror tracks the
		// actually-stored value on every update path, including early
		// returns to defaults on malformed input.
		add_action( 'add_option_' . PTAI_Settings::OPTION_NAME, array( $this->settings, 'sync_uninstall_flag' ) );
		add_action( 'update_option_' . PTAI_Settings::OPTION_NAME, array( $this->settings, 'sync_uninstall_flag' ) );

		// Shortcode.
		add_shortcode( PTAI_Shortcode::TAG, array( $this->shortcode, 'render' ) );

		// Block.
		add_action( 'init', array( $this->block, 'register' ) );
	}

	/**
	 * Admin-only hooks.
	 *
	 * @return void
	 */
	public function define_admin_hooks() {
		if ( ! is_admin() || ! isset( $this->admin ) ) {
			return;
		}

		// Always-on: hooks that may legitimately fire during admin-ajax
		// or REST flows (post saves, query modifications, settings sync).
		add_action( 'save_post_' . PTAI_CPT, array( $this->admin, 'save_meta_box' ) );
		add_action( 'pre_get_posts', array( $this->admin, 'handle_sortable_columns_query' ) );
		add_action( 'admin_init', array( $this->settings, 'register_settings' ) );

		// Screen-output hooks — useless during admin-ajax.php since
		// nothing renders. Skipping them in AJAX shaves a few callbacks
		// off every admin AJAX request.
		if ( wp_doing_ajax() ) {
			return;
		}

		add_action( 'admin_menu', array( $this->admin, 'register_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );
		add_action( 'add_meta_boxes_' . PTAI_CPT, array( $this->admin, 'add_meta_boxes' ) );

		add_filter( 'manage_' . PTAI_CPT . '_posts_columns', array( $this->admin, 'add_admin_columns' ) );
		add_action( 'manage_' . PTAI_CPT . '_posts_custom_column', array( $this->admin, 'populate_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . PTAI_CPT . '_sortable_columns', array( $this->admin, 'make_admin_columns_sortable' ) );

		add_filter( 'plugin_action_links_' . PTAI_PLUGIN_BASENAME, array( $this->admin, 'add_plugin_action_links' ) );

		add_action( 'admin_notices', array( $this->admin, 'render_ai_status_notice' ) );
		add_action( 'admin_notices', array( $this->admin, 'render_size_limit_notice' ) );
		add_filter( 'post_row_actions', array( $this->admin, 'add_row_actions' ), 10, 2 );
		add_action( 'admin_post_ptai_regenerate_embedding', array( $this->admin, 'handle_regenerate_embedding' ) );

		add_filter( 'bulk_actions-edit-' . PTAI_CPT, array( $this->admin, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . PTAI_CPT, array( $this->admin, 'handle_bulk_action' ), 10, 3 );
	}

	/**
	 * Public/frontend hooks.
	 *
	 * @return void
	 */
	public function define_public_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this->public_handler, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this->public_handler, 'enqueue_scripts' ) );
		add_filter( 'template_include', array( $this->public_handler, 'template_include' ) );
	}

	/**
	 * AJAX hooks.
	 *
	 * @return void
	 */
	public function define_ajax_hooks() {
		// Search — logged-in and logged-out visitors.
		add_action( 'wp_ajax_ptai_search', array( $this->public_handler, 'handle_search_ajax' ) );
		add_action( 'wp_ajax_nopriv_ptai_search', array( $this->public_handler, 'handle_search_ajax' ) );

		// Admin dismiss-notice AJAX — only when the admin object exists.
		if ( isset( $this->admin ) ) {
			add_action( 'wp_ajax_ptai_dismiss_ai_notice', array( $this->admin, 'handle_dismiss_ai_notice' ) );
		}
	}
}
