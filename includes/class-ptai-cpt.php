<?php
/**
 * Custom post type and taxonomy registration.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_CPT
 *
 * Registers the `ptai_file` custom post type, the `ptai_category`
 * taxonomy, and all PaperTrail AI post meta fields.
 */
class PTAI_CPT {

	/**
	 * Option key tracking the DB version that last triggered a rewrite flush.
	 *
	 * @var string
	 */
	const REWRITE_VERSION_OPTION = 'ptai_rewrite_version';

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * PTAI_Loader::define_core_hooks().
	 */
	public function __construct() {}

	/**
	 * Register the ptai_file custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Documents', 'post type general name', 'papertrail-ai' ),
			'singular_name'         => _x( 'Document', 'post type singular name', 'papertrail-ai' ),
			'menu_name'             => _x( 'PaperTrail', 'admin menu', 'papertrail-ai' ),
			'name_admin_bar'        => _x( 'Document', 'add new on admin bar', 'papertrail-ai' ),
			'add_new'               => _x( 'Add New', 'document', 'papertrail-ai' ),
			'add_new_item'          => __( 'Add New Document', 'papertrail-ai' ),
			'new_item'              => __( 'New Document', 'papertrail-ai' ),
			'edit_item'             => __( 'Edit Document', 'papertrail-ai' ),
			'view_item'             => __( 'View Document', 'papertrail-ai' ),
			'view_items'            => __( 'View Documents', 'papertrail-ai' ),
			'all_items'             => __( 'All Documents', 'papertrail-ai' ),
			'search_items'          => __( 'Search Documents', 'papertrail-ai' ),
			'parent_item_colon'     => __( 'Parent Document:', 'papertrail-ai' ),
			'not_found'             => __( 'No documents found.', 'papertrail-ai' ),
			'not_found_in_trash'    => __( 'No documents found in Trash.', 'papertrail-ai' ),
			'featured_image'        => __( 'Document thumbnail', 'papertrail-ai' ),
			'set_featured_image'    => __( 'Set document thumbnail', 'papertrail-ai' ),
			'remove_featured_image' => __( 'Remove document thumbnail', 'papertrail-ai' ),
			'use_featured_image'    => __( 'Use as document thumbnail', 'papertrail-ai' ),
			'archives'              => __( 'Document archives', 'papertrail-ai' ),
			'insert_into_item'      => __( 'Insert into document', 'papertrail-ai' ),
			'uploaded_to_this_item' => __( 'Uploaded to this document', 'papertrail-ai' ),
			'filter_items_list'     => __( 'Filter documents list', 'papertrail-ai' ),
			'items_list_navigation' => __( 'Documents list navigation', 'papertrail-ai' ),
			'items_list'            => __( 'Documents list', 'papertrail-ai' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'AI-indexed document files managed by PaperTrail AI.', 'papertrail-ai' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'ptai-files',
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-clipboard',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'has_archive'        => true,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'rewrite'            => array(
				'slug'       => 'documents',
				'with_front' => false,
			),
		);

		register_post_type( PTAI_CPT, $args );
	}

	/**
	 * Register the ptai_category taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Document Categories', 'taxonomy general name', 'papertrail-ai' ),
			'singular_name'              => _x( 'Document Category', 'taxonomy singular name', 'papertrail-ai' ),
			'search_items'               => __( 'Search Categories', 'papertrail-ai' ),
			'popular_items'              => __( 'Popular Categories', 'papertrail-ai' ),
			'all_items'                  => __( 'All Categories', 'papertrail-ai' ),
			'parent_item'                => __( 'Parent Category', 'papertrail-ai' ),
			'parent_item_colon'          => __( 'Parent Category:', 'papertrail-ai' ),
			'edit_item'                  => __( 'Edit Category', 'papertrail-ai' ),
			'view_item'                  => __( 'View Category', 'papertrail-ai' ),
			'update_item'                => __( 'Update Category', 'papertrail-ai' ),
			'add_new_item'               => __( 'Add New Category', 'papertrail-ai' ),
			'new_item_name'              => __( 'New Category Name', 'papertrail-ai' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'papertrail-ai' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'papertrail-ai' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'papertrail-ai' ),
			'not_found'                  => __( 'No categories found.', 'papertrail-ai' ),
			'no_terms'                   => __( 'No categories', 'papertrail-ai' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'papertrail-ai' ),
			'items_list'                 => __( 'Categories list', 'papertrail-ai' ),
			'menu_name'                  => __( 'Categories', 'papertrail-ai' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'show_tagcloud'     => false,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'document-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy( PTAI_TAXONOMY, array( PTAI_CPT ), $args );
	}

	/**
	 * Register custom meta fields for the CPT.
	 *
	 * @return void
	 */
	public function register_meta_fields() {
		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};

		// No-op string sanitizer for opaque payloads (embeddings).
		// sanitize_textarea_field strips bytes that are valid inside
		// JSON / base64 (e.g. "<"), corrupting the stored vector. We
		// only need to guarantee the stored value is a UTF-8 string.
		$passthrough = static function ( $value ) {
			if ( ! is_string( $value ) ) {
				return '';
			}
			return wp_check_invalid_utf8( $value );
		};

		$fields = array(
			'_ptai_file_id'         => array(
				'type'              => 'integer',
				'description'       => __( 'Attachment ID of the file in the WordPress media library.', 'papertrail-ai' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_ptai_file_type'       => array(
				'type'              => 'string',
				'description'       => __( 'MIME type of the attached file.', 'papertrail-ai' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_ptai_file_size'       => array(
				'type'              => 'integer',
				'description'       => __( 'File size in bytes.', 'papertrail-ai' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_ptai_file_ext'        => array(
				'type'              => 'string',
				'description'       => __( 'File extension, lower-case without leading dot.', 'papertrail-ai' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_ptai_download_count'  => array(
				'type'              => 'integer',
				'description'       => __( 'Total number of times the file has been downloaded.', 'papertrail-ai' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_ptai_last_downloaded' => array(
				'type'              => 'string',
				'description'       => __( 'Timestamp (Y-m-d H:i:s) of the most recent download.', 'papertrail-ai' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_ptai_embedding'       => array(
				'type'              => 'string',
				'description'       => __( 'Serialized OpenAI embedding vector for this document. Never exposed via REST.', 'papertrail-ai' ),
				'sanitize_callback' => $passthrough,
				'show_in_rest'      => false,
			),
			'_ptai_embedding_info'  => array(
				'type'              => 'string',
				'description'       => __( 'Internal embedding metadata (model, dimensions, generated_at). Never exposed via REST.', 'papertrail-ai' ),
				'sanitize_callback' => $passthrough,
				'show_in_rest'      => false,
			),
		);

		foreach ( $fields as $meta_key => $config ) {
			register_post_meta(
				PTAI_CPT,
				$meta_key,
				array(
					'type'              => $config['type'],
					'description'       => $config['description'],
					'single'            => true,
					'sanitize_callback' => $config['sanitize_callback'],
					'auth_callback'     => $auth,
					'show_in_rest'      => $config['show_in_rest'],
				)
			);
		}

		register_post_meta(
			PTAI_CPT,
			'_ptai_doc_summary',
			array(
				'type'              => 'string',
				'description'       => __( 'Document summary used as AI embedding source.', 'papertrail-ai' ),
				'single'            => true,
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Flush rewrite rules when the DB version changes.
	 *
	 * @return void
	 */
	public function flush_rewrite_rules_if_needed() {
		$stored = get_option( self::REWRITE_VERSION_OPTION );

		if ( PTAI_DB_VERSION === $stored ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, PTAI_DB_VERSION );
	}
}
