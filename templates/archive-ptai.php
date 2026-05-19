<?php
/**
 * Archive template for the PaperTrail AI document library.
 *
 * Loaded by:
 *   - PTAI_Public::template_include() for the CPT archive.
 *   - PTAI_Shortcode::render() for inline [papertrail] embeds.
 *
 * Themes may override this by providing their own
 * `papertrail-ai/archive-ptai.php` in the active stylesheet.
 *
 * Expected variables when included by the shortcode:
 *   - $ptai_results array  Search result array (see PTAI_Search::search()).
 *   - $ptai_columns int    1 or 2.
 *   - $ptai_in_shortcode bool
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

// Detect whether we're being included by the shortcode or as a full template.
$ptai_standalone = empty( $ptai_in_shortcode );

// Standalone (theme archive) path — honor `?ptai_q=` GET searches and
// preserve taxonomy-archive scope so a category landing keeps its filter.
$ptai_archive_query    = '';
$ptai_archive_category = 0;
$ptai_archive_mode     = 'auto';

if ( $ptai_standalone ) {
	get_header();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['ptai_q'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ptai_archive_query = sanitize_text_field( wp_unslash( $_GET['ptai_q'] ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['ptai_category'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ptai_archive_category = absint( wp_unslash( $_GET['ptai_category'] ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['ptai_mode'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ptai_archive_mode = sanitize_key( wp_unslash( $_GET['ptai_mode'] ) );
		if ( ! in_array( $ptai_archive_mode, array( 'auto', 'ai', 'core' ), true ) ) {
			$ptai_archive_mode = 'auto';
		}
	}

	// Taxonomy archives carry their own scope — use the queried term so
	// in-page search stays inside the visitor's current category.
	if ( 0 === $ptai_archive_category && is_tax( PTAI_TAXONOMY ) ) {
		$ptai_queried_term = get_queried_object();
		if ( $ptai_queried_term instanceof WP_Term ) {
			$ptai_archive_category = (int) $ptai_queried_term->term_id;
		}
	}

	if ( '' !== $ptai_archive_query ) {
		// A search was submitted — re-run through PTAI_Search so the
		// archive view reflects the query (and uses AI when configured).
		$ptai_search  = new PTAI_Search();
		$ptai_results = $ptai_search->search(
			$ptai_archive_query,
			array(
				'per_page' => (int) get_query_var( 'posts_per_page', 10 ),
				'page'     => max( 1, (int) get_query_var( 'paged', 1 ) ),
				'category' => $ptai_archive_category,
				'mode'     => $ptai_archive_mode,
			)
		);
	} else {
		// No search — render the main query as-is. WordPress has already
		// scoped it to the current taxonomy term (if applicable).
		global $wp_query;
		$ptai_results = array(
			'posts'     => is_array( $wp_query->posts ) ? $wp_query->posts : array(),
			'total'     => (int) $wp_query->found_posts,
			'pages'     => (int) $wp_query->max_num_pages,
			'mode_used' => 'core',
			'query'     => '',
		);
	}

	$ptai_columns = 1;
}

$ptai_results = isset( $ptai_results ) && is_array( $ptai_results )
	? $ptai_results
	: array( 'posts' => array(), 'total' => 0, 'pages' => 0, 'mode_used' => '', 'query' => '' );
$ptai_columns = isset( $ptai_columns ) ? (int) $ptai_columns : 1;
?>

<div class="ptai-document-library">

	<?php if ( $ptai_standalone ) : ?>
		<header class="ptai-archive__header">
			<h1 class="ptai-archive__title">
				<?php esc_html_e( 'Document Library', 'papertrail-ai' ); ?>
			</h1>
		</header>

		<?php
		// Render the search bar partial for the archive page, passing
		// the active category/mode so AJAX and no-JS submits stay scoped
		// to the visitor's current view.
		$ptai_search_context = array(
			'category_id' => (int) $ptai_archive_category,
			'mode'        => (string) $ptai_archive_mode,
		);
		include PTAI_PLUGIN_DIR . 'templates/partials/search-bar.php';
		?>
	<?php endif; ?>

	<?php if ( empty( $ptai_results['posts'] ) ) : ?>
		<p class="ptai-no-results">
			<?php esc_html_e( 'No documents found.', 'papertrail-ai' ); ?>
		</p>
	<?php else : ?>

		<div class="ptai-mode-indicator">
			<?php if ( 'ai' === $ptai_results['mode_used'] ) : ?>
				<span class="ptai-ai-badge">
					<?php esc_html_e( 'AI-powered results', 'papertrail-ai' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<ul class="ptai-file-list ptai-columns-<?php echo esc_attr( $ptai_columns ); ?>">
			<?php
			// Save and restore the global $post — the loop variable below
			// shares the surrounding scope, and overwriting it on the
			// standalone archive would leak the last card's post object
			// into footer/template code that runs after this template.
			$ptai_saved_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;

			foreach ( $ptai_results['posts'] as $post ) :
				if ( ! ( $post instanceof WP_Post ) ) {
					continue;
				}
				include PTAI_PLUGIN_DIR . 'templates/partials/file-card.php';
			endforeach;

			$GLOBALS['post'] = $ptai_saved_post;
			unset( $ptai_saved_post );
			?>
		</ul>

		<?php
		if ( (int) $ptai_results['pages'] > 1 ) {
			$ptai_paged = max( 1, (int) get_query_var( 'paged', 1 ) );
			$ptai_links = paginate_links(
				array(
					'total'     => (int) $ptai_results['pages'],
					'current'   => $ptai_paged,
					'mid_size'  => 2,
					'prev_text' => esc_html__( '« Previous', 'papertrail-ai' ),
					'next_text' => esc_html__( 'Next »', 'papertrail-ai' ),
					'type'      => 'list',
				)
			);
			if ( $ptai_links ) {
				echo '<nav class="ptai-pagination" aria-label="' . esc_attr__( 'Document pagination', 'papertrail-ai' ) . '">';
				echo wp_kses_post( $ptai_links );
				echo '</nav>';
			}
		}
		?>

	<?php endif; ?>

</div>

<?php
if ( $ptai_standalone ) {
	get_footer();
}
