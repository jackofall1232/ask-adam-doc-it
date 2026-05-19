<?php
/**
 * Gutenberg block registration.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Block
 *
 * Registers the Ask Adam Doc It dynamic Gutenberg block. The block is
 * defined by blocks/ask-adam-library/block.json so attribute schema
 * and editor script registration follow WordPress's preferred path.
 * Rendering is server-side via AADI_Shortcode so no compiled JS build
 * step is required to ship a working block.
 */
class AADI_Block {

	/**
	 * Block name. Mirrors block.json's "name" field.
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'ask-adam-doc-it/library';

	/**
	 * Shortcode renderer used to produce the dynamic block output.
	 *
	 * @var AADI_Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor. Intentionally side-effect-free — registration is hooked
	 * in AADI_Loader::define_core_hooks(). The shortcode dependency is
	 * injected so render_callback() doesn't allocate a new instance per
	 * block render.
	 *
	 * @param AADI_Shortcode|null $shortcode Optional shortcode renderer.
	 */
	public function __construct( $shortcode = null ) {
		if ( $shortcode instanceof AADI_Shortcode ) {
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
			AADI_PLUGIN_DIR . 'blocks/ask-adam-library',
			array(
				'render_callback' => array( $this, 'render_callback' ),
			)
		);
	}

	/**
	 * Dynamic render callback — maps block attributes to shortcode atts
	 * and delegates to AADI_Shortcode for a single rendering path.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner content (unused).
	 * @return string
	 */
	public function render_callback( $attributes, $content = '' ) {
		unset( $content );

		if ( ! class_exists( 'AADI_Shortcode' ) ) {
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

		$shortcode = isset( $this->shortcode ) ? $this->shortcode : new AADI_Shortcode();
		return $shortcode->render( $atts, '' );
	}
}
