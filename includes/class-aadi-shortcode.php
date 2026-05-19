<?php
/**
 * [ask_adam_doc_it] shortcode handler.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Shortcode
 *
 * Registers and renders the front-end [ask_adam_doc_it] shortcode for the
 * document library. The Pro-only [ask_adam_doc_it_ask] Q&A shortcode is
 * intentionally NOT registered here.
 */
class AADI_Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	const TAG = 'ask_adam_doc_it';

	/**
	 * Constructor. Intentionally side-effect-free — the shortcode is
	 * registered in AADI_Loader::define_core_hooks().
	 */
	public function __construct() {}

	/**
	 * Default shortcode attribute values.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_atts() {
		return array(
			'category'    => '',
			'per_page'    => 10,
			'show_search' => 'true',
			'columns'     => 1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'mode'        => 'auto',
		);
	}

	/**
	 * Render the shortcode. MUST return a string — never echo.
	 *
	 * @param array       $atts    Shortcode attributes.
	 * @param string|null $content Inner content (unused).
	 * @return string
	 */
	public function render( $atts = array(), $content = null ) {
		$atts = shortcode_atts( $this->get_default_atts(), is_array( $atts ) ? $atts : array(), self::TAG );

		$category    = sanitize_text_field( (string) $atts['category'] );
		$per_page    = max( 1, min( 50, absint( $atts['per_page'] ) ) );
		$show_search = ( 'true' === strtolower( (string) $atts['show_search'] ) );
		$columns     = absint( $atts['columns'] );
		$columns     = ( 2 === $columns ) ? 2 : 1;

		$orderby = sanitize_key( (string) $atts['orderby'] );
		if ( ! in_array( $orderby, array( 'date', 'title', 'downloads' ), true ) ) {
			$orderby = 'date';
		}

		$order = strtoupper( (string) $atts['order'] );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$mode = sanitize_key( (string) $atts['mode'] );
		if ( ! in_array( $mode, array( 'auto', 'ai', 'core' ), true ) ) {
			$mode = 'auto';
		}

		// Resolve category to a term_id.
		$category_id = 0;
		if ( '' !== $category ) {
			if ( is_numeric( $category ) ) {
				$category_id = absint( $category );
			} else {
				$term = get_term_by( 'slug', $category, AADI_TAXONOMY );
				if ( $term && ! is_wp_error( $term ) ) {
					$category_id = (int) $term->term_id;
				}
			}
		}

		$paged = max( 1, absint( get_query_var( 'paged', 1 ) ) );

		// Use the visitor's query string if a search was submitted.
		$query_string = '';
		if ( isset( $_GET['aadi_q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query_string = sanitize_text_field( wp_unslash( $_GET['aadi_q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$search  = new AADI_Search();
		$results = $search->search(
			$query_string,
			array(
				'per_page' => $per_page,
				'page'     => $paged,
				'category' => $category_id,
				'mode'     => $mode,
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		ob_start();

		if ( $show_search ) {
			// Explicit allowlist covers every tag/attribute used in search-bar.php.
			// wp_kses_post() would strip <form>/<input>/<button>, breaking the UI.
			$allowed_html = array(
				'form'  => array(
					'class'  => true,
					'method' => true,
					'action' => true,
					'role'   => true,
				),
				'label' => array(
					'for'   => true,
					'class' => true,
				),
				'input' => array(
					'type'        => true,
					'id'          => true,
					'name'        => true,
					'class'       => true,
					'value'       => true,
					'placeholder' => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			);
			echo wp_kses(
				$this->render_search_bar(
					array(
						'category_id' => absint( $category_id ),
						'mode'        => sanitize_key( $mode ),
					)
				),
				$allowed_html
			);
		}

		$this->render_archive( $results, $columns );

		return (string) ob_get_clean();
	}

	/**
	 * Render the archive template with the given results.
	 *
	 * @param array $results Search result array.
	 * @param int   $columns Column count.
	 * @return void
	 */
	private function render_archive( $results, $columns ) {
		$template = AADI_PLUGIN_DIR . 'templates/archive-aadi.php';

		// Theme override.
		$theme_template = get_stylesheet_directory() . '/ask-adam-doc-it/archive-aadi.php';
		if ( file_exists( $theme_template ) ) {
			$template = $theme_template;
		}

		if ( file_exists( $template ) ) {
			// Variables consumed by the template.
			$aadi_results        = $results; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$aadi_columns        = (int) $columns; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$aadi_in_shortcode   = true; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			include $template;
		}
	}

	/**
	 * Render the search-bar partial and return the HTML.
	 *
	 * @param array $context Extra context (selected category id, mode).
	 * @return string
	 */
	public function render_search_bar( $context = array() ) {
		$template = AADI_PLUGIN_DIR . 'templates/partials/search-bar.php';

		$theme_template = get_stylesheet_directory() . '/ask-adam-doc-it/partials/search-bar.php';
		if ( file_exists( $theme_template ) ) {
			$template = $theme_template;
		}

		if ( ! file_exists( $template ) ) {
			return '';
		}

		$aadi_search_context = is_array( $context ) ? $context : array(); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}
}
