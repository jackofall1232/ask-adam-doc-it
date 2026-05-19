<?php
/**
 * Gutenberg block registration.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Block
 *
 * Registers the PaperTrail AI dynamic Gutenberg block. The block is
 * defined by blocks/papertrail-library/block.json so attribute schema
 * and editor script registration follow WordPress's preferred path.
 * Rendering is server-side via PTAI_Shortcode so no compiled JS build
 * step is required to ship a working block.
 */
class PTAI_Block {

	/**
	 * Block name. Mirrors block.json's "name" field.
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'papertrail-ai/library';

	/**
	 * Shortcode renderer used to produce the dynamic block output.
	 *
	 * @var PTAI_Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor. Intentionally side-effect-free — registration is hooked
	 * in PTAI_Loader::define_core_hooks(). The shortcode dependency is
	 * injected so render_callback() doesn't allocate a new instance per
	 * block render.
	 *
	 * @param PTAI_Shortcode|null $shortcode Optional shortcode renderer.
	 */
	public function __construct( $shortcode = null ) {
		if ( $shortcode instanceof PTAI_Shortcode ) {
			$this->shortcode = $shortcode;
		}
	}

	/**
	 * Register the block type from its directory (block.json metadata).
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			PTAI_PLUGIN_DIR . 'blocks/papertrail-library',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Dynamic render callback — maps block attributes to shortcode atts
	 * and delegates to PTAI_Shortcode for a single rendering path.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner content (unused).
	 * @return string
	 */
	public function render_callback( $attributes, $content = '' ) {
		unset( $content );

		if ( ! class_exists( 'PTAI_Shortcode' ) ) {
			return '';
		}

		$attributes = is_array( $attributes ) ? $attributes : array();

		$atts = array(
			'category'    => isset( $attributes['category'] )
				? sanitize_text_field( (string) $attributes['category'] )
				: '',
			'per_page'    => isset( $attributes['perPage'] )
				? absint( $attributes['perPage'] )
				: 10,
			'show_search' => isset( $attributes['showSearch'] )
				? ( $attributes['showSearch'] ? 'true' : 'false' )
				: 'true',
			'columns'     => isset( $attributes['columns'] )
				? absint( $attributes['columns'] )
				: 1,
			'mode'        => isset( $attributes['mode'] )
				? sanitize_key( (string) $attributes['mode'] )
				: 'auto',
		);

		$shortcode = isset( $this->shortcode ) ? $this->shortcode : new PTAI_Shortcode();
		return $shortcode->render( $atts, '' );
	}
}
