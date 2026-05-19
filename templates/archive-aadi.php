<?php
/**
 * Archive template for the Ask Adam Doc It document library.
 *
 * Loaded by:
 *   - AADI_Public::template_include() for the CPT archive.
 *   - AADI_Shortcode::render() for inline [ask_adam_doc_it] embeds.
 *
 * Themes may override this by providing their own
 * `ask-adam-doc-it/archive-aadi.php` in the active stylesheet.
 *
 * Expected variables when included by the shortcode:
 *   - $aadi_results array  Search result array (see AADI_Search::search()).
 *   - $aadi_columns int    1 or 2.
 *   - $aadi_in_shortcode bool
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

// Detect whether we're being included by the shortcode or as a full template.
$aadi_standalone = empty( $aadi_in_shortcode );

// Standalone (theme archive) path — honor `?aadi_q=` GET searches and
// preserve taxonomy-archive scope so a category landing keeps its filter.
$aadi_archive_query    = '';
$aadi_archive_category = 0;
$aadi_archive_mode     = 'auto';

if ( $aadi_standalone ) {
	get_header();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['aadi_q'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aadi_archive_query = sanitize_text_field( wp_unslash( $_GET['aadi_q'] ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['aadi_category'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aadi_archive_category = absint( wp_unslash( $_GET['aadi_category'] ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['aadi_mode'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aadi_archive_mode = sanitize_key( wp_unslash( $_GET['aadi_mode'] ) );
		if ( ! in_array( $aadi_archive_mode, array( 'auto', 'ai', 'core' ), true ) ) {
			$aadi_archive_mode = 'auto';
		}
	}

	// Taxonomy archives carry their own scope — use the queried term so
	// in-page search stays inside the visitor's current category.
	if ( 0 === $aadi_archive_category && is_tax( AADI_TAXONOMY ) ) {
		$aadi_queried_term = get_queried_object();
		if ( $aadi_queried_term instanceof WP_Term ) {
			$aadi_archive_category = (int) $aadi_queried_term->term_id;
		}
	}

	if ( '' !== $aadi_archive_query ) {
		// A search was submitted — re-run through AADI_Search so the
		// archive view reflects the query (and uses AI when configured).
		$aadi_search  = new AADI_Search();
		$aadi_results = $aadi_search->search(
			$aadi_archive_query,
			array(
				'per_page' => (int) get_query_var( 'posts_per_page', 10 ),
				'page'     => max( 1, (int) get_query_var( 'paged', 1 ) ),
				'category' => $aadi_archive_category,
				'mode'     => $aadi_archive_mode,
			)
		);
	} else {
		// No search — render the main query as-is. WordPress has already
		// scoped it to the current taxonomy term (if applicable).
		global $wp_query;
		$aadi_results = array(
			'posts'     => is_array( $wp_query->posts ) ? $wp_query->posts : array(),
			'total'     => (int) $wp_query->found_posts,
			'pages'     => (int) $wp_query->max_num_pages,
			'mode_used' => 'core',
			'query'     => '',
		);
	}

	$aadi_columns = 1;
}

$aadi_results = isset( $aadi_results ) && is_array( $aadi_results )
	? $aadi_results
	: array( 'posts' => array(), 'total' => 0, 'pages' => 0, 'mode_used' => '', 'query' => '' );
$aadi_columns = isset( $aadi_columns ) ? (int) $aadi_columns : 1;
?>

<div class="aadi-document-library">

	<?php if ( $aadi_standalone ) : ?>
		<header class="aadi-archive__header">
			<h1 class="aadi-archive__title">
				<?php esc_html_e( 'Document Library', 'ask-adam-doc-it' ); ?>
			</h1>
		</header>

		<?php
		// Render the search bar partial for the archive page, passing
		// the active category/mode so AJAX and no-JS submits stay scoped
		// to the visitor's current view.
		$aadi_search_context = array(
			'category_id' => (int) $aadi_archive_category,
			'mode'        => (string) $aadi_archive_mode,
		);
		include AADI_PLUGIN_DIR . 'templates/partials/search-bar.php';
		?>
	<?php endif; ?>

	<?php if ( empty( $aadi_results['posts'] ) ) : ?>
		<p class="aadi-no-results">
			<?php esc_html_e( 'No documents found.', 'ask-adam-doc-it' ); ?>
		</p>
	<?php else : ?>

		<div class="aadi-mode-indicator">
			<?php if ( 'ai' === $aadi_results['mode_used'] ) : ?>
				<span class="aadi-ai-badge">
					<?php esc_html_e( 'AI-powered results', 'ask-adam-doc-it' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<ul class="aadi-file-list aadi-columns-<?php echo esc_attr( $aadi_columns ); ?>">
			<?php
			// Save and restore the global $post — the loop variable below
			// shares the surrounding scope, and overwriting it on the
			// standalone archive would leak the last card's post object
			// into footer/template code that runs after this template.
			$aadi_saved_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

			foreach ( $aadi_results['posts'] as $post ) :
				if ( ! ( $post instanceof WP_Post ) ) {
					continue;
				}
				include AADI_PLUGIN_DIR . 'templates/partials/file-card.php';
			endforeach;

			$GLOBALS['post'] = $aadi_saved_post;
			unset( $aadi_saved_post );
			?>
		</ul>

		<?php
		if ( (int) $aadi_results['pages'] > 1 ) {
			$aadi_paged = max( 1, (int) get_query_var( 'paged', 1 ) );
			$aadi_links = paginate_links(
				array(
					'total'     => (int) $aadi_results['pages'],
					'current'   => $aadi_paged,
					'mid_size'  => 2,
					'prev_text' => esc_html__( '« Previous', 'ask-adam-doc-it' ),
					'next_text' => esc_html__( 'Next »', 'ask-adam-doc-it' ),
					'type'      => 'list',
				)
			);
			if ( $aadi_links ) {
				echo '<nav class="aadi-pagination" aria-label="' . esc_attr__( 'Document pagination', 'ask-adam-doc-it' ) . '">';
				echo wp_kses_post( $aadi_links );
				echo '</nav>';
			}
		}
		?>

	<?php endif; ?>

</div>

<?php
if ( $aadi_standalone ) {
	get_footer();
}
