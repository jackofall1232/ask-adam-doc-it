<?php
/**
 * Custom post type and taxonomy registration.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_CPT
 *
 * Registers the `aadi_file` custom post type, the `aadi_category`
 * taxonomy, and all Ask Adam Doc It post meta fields.
 */
class AADI_CPT {

	/**
	 * Option key tracking the DB version that last triggered a rewrite flush.
	 *
	 * @var string
	 */
	const REWRITE_VERSION_OPTION = 'aadi_rewrite_version';

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * AADI_Loader::define_core_hooks().
	 */
	public function __construct() {}

	/**
	 * Register the aadi_file custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Documents', 'post type general name', 'ask-adam-doc-it' ),
			'singular_name'         => _x( 'Document', 'post type singular name', 'ask-adam-doc-it' ),
			'menu_name'             => _x( 'Ask Adam Doc It', 'admin menu', 'ask-adam-doc-it' ),
			'name_admin_bar'        => _x( 'Document', 'add new on admin bar', 'ask-adam-doc-it' ),
			'add_new'               => _x( 'Add New', 'document', 'ask-adam-doc-it' ),
			'add_new_item'          => __( 'Add New Document', 'ask-adam-doc-it' ),
			'new_item'              => __( 'New Document', 'ask-adam-doc-it' ),
			'edit_item'             => __( 'Edit Document', 'ask-adam-doc-it' ),
			'view_item'             => __( 'View Document', 'ask-adam-doc-it' ),
			'view_items'            => __( 'View Documents', 'ask-adam-doc-it' ),
			'all_items'             => __( 'All Documents', 'ask-adam-doc-it' ),
			'search_items'          => __( 'Search Documents', 'ask-adam-doc-it' ),
			'parent_item_colon'     => __( 'Parent Document:', 'ask-adam-doc-it' ),
			'not_found'             => __( 'No documents found.', 'ask-adam-doc-it' ),
			'not_found_in_trash'    => __( 'No documents found in Trash.', 'ask-adam-doc-it' ),
			'featured_image'        => __( 'Document thumbnail', 'ask-adam-doc-it' ),
			'set_featured_image'    => __( 'Set document thumbnail', 'ask-adam-doc-it' ),
			'remove_featured_image' => __( 'Remove document thumbnail', 'ask-adam-doc-it' ),
			'use_featured_image'    => __( 'Use as document thumbnail', 'ask-adam-doc-it' ),
			'archives'              => __( 'Document archives', 'ask-adam-doc-it' ),
			'insert_into_item'      => __( 'Insert into document', 'ask-adam-doc-it' ),
			'uploaded_to_this_item' => __( 'Uploaded to this document', 'ask-adam-doc-it' ),
			'filter_items_list'     => __( 'Filter documents list', 'ask-adam-doc-it' ),
			'items_list_navigation' => __( 'Documents list navigation', 'ask-adam-doc-it' ),
			'items_list'            => __( 'Documents list', 'ask-adam-doc-it' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'AI-indexed document files managed by Ask Adam Doc It.', 'ask-adam-doc-it' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'aadi-files',
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

		register_post_type( AADI_CPT, $args );
	}

	/**
	 * Register the aadi_category taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Document Categories', 'taxonomy general name', 'ask-adam-doc-it' ),
			'singular_name'              => _x( 'Document Category', 'taxonomy singular name', 'ask-adam-doc-it' ),
			'search_items'               => __( 'Search Categories', 'ask-adam-doc-it' ),
			'popular_items'              => __( 'Popular Categories', 'ask-adam-doc-it' ),
			'all_items'                  => __( 'All Categories', 'ask-adam-doc-it' ),
			'parent_item'                => __( 'Parent Category', 'ask-adam-doc-it' ),
			'parent_item_colon'          => __( 'Parent Category:', 'ask-adam-doc-it' ),
			'edit_item'                  => __( 'Edit Category', 'ask-adam-doc-it' ),
			'view_item'                  => __( 'View Category', 'ask-adam-doc-it' ),
			'update_item'                => __( 'Update Category', 'ask-adam-doc-it' ),
			'add_new_item'               => __( 'Add New Category', 'ask-adam-doc-it' ),
			'new_item_name'              => __( 'New Category Name', 'ask-adam-doc-it' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'ask-adam-doc-it' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'ask-adam-doc-it' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'ask-adam-doc-it' ),
			'not_found'                  => __( 'No categories found.', 'ask-adam-doc-it' ),
			'no_terms'                   => __( 'No categories', 'ask-adam-doc-it' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'ask-adam-doc-it' ),
			'items_list'                 => __( 'Categories list', 'ask-adam-doc-it' ),
			'menu_name'                  => __( 'Categories', 'ask-adam-doc-it' ),
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

		register_taxonomy( AADI_TAXONOMY, array( AADI_CPT ), $args );
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
			'_aadi_file_id'         => array(
				'type'              => 'integer',
				'description'       => __( 'Attachment ID of the file in the WordPress media library.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_aadi_file_type'       => array(
				'type'              => 'string',
				'description'       => __( 'MIME type of the attached file.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_aadi_file_size'       => array(
				'type'              => 'integer',
				'description'       => __( 'File size in bytes.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_aadi_file_ext'        => array(
				'type'              => 'string',
				'description'       => __( 'File extension, lower-case without leading dot.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_aadi_download_count'  => array(
				'type'              => 'integer',
				'description'       => __( 'Total number of times the file has been downloaded.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			),
			'_aadi_last_downloaded' => array(
				'type'              => 'string',
				'description'       => __( 'Timestamp (Y-m-d H:i:s) of the most recent download.', 'ask-adam-doc-it' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
			),
			'_aadi_embedding'       => array(
				'type'              => 'string',
				'description'       => __( 'Serialized OpenAI embedding vector for this document. Never exposed via REST.', 'ask-adam-doc-it' ),
				'sanitize_callback' => $passthrough,
				'show_in_rest'      => false,
			),
			'_aadi_embedding_info'  => array(
				'type'              => 'string',
				'description'       => __( 'Internal embedding metadata (model, dimensions, generated_at). Never exposed via REST.', 'ask-adam-doc-it' ),
				'sanitize_callback' => $passthrough,
				'show_in_rest'      => false,
			),
		);

		foreach ( $fields as $meta_key => $config ) {
			register_post_meta(
				AADI_CPT,
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
			AADI_CPT,
			'_aadi_doc_summary',
			array(
				'type'              => 'string',
				'description'       => __( 'Document summary used as AI embedding source.', 'ask-adam-doc-it' ),
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

		if ( AADI_DB_VERSION === $stored ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, AADI_DB_VERSION );
	}
}
