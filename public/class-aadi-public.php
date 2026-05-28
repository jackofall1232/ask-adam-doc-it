<?php
/**
 * Public/frontend controller.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Public
 *
 * Handles public-facing scripts/styles, template loading, and AJAX search.
 */
class AADI_Public {

	/**
	 * Site-wide AI search rate limit per hour. Free version cap.
	 * Prevents the plugin from becoming an open OpenAI proxy.
	 */
	const RATE_LIMIT_PER_HOUR = 60;

	/**
	 * Site-wide cap on fresh (uncached) summary generations per hour.
	 * Cached repeats never count against this bucket.
	 */
	const SUMMARIZE_RATE_LIMIT_PER_HOUR = 30;

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * AADI_Loader::define_public_hooks() and define_ajax_hooks().
	 */
	public function __construct() {}

	/**
	 * Whether the current request should load the public assets.
	 *
	 * @return bool
	 */
	private function should_enqueue_assets() {
		if ( is_singular( AADI_CPT ) ) {
			return true;
		}
		if ( is_post_type_archive( AADI_CPT ) ) {
			return true;
		}
		if ( is_tax( AADI_TAXONOMY ) ) {
			return true;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post ) {
				$content = (string) $post->post_content;
				if ( has_shortcode( $content, AADI_Shortcode::TAG ) ) {
					return true;
				}
				if ( function_exists( 'has_block' ) && has_block( AADI_Block::BLOCK_NAME, $post ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Enqueue public styles.
	 *
	 * Registers the style handle unconditionally so block.json's `style`
	 * reference resolves on block-only pages (where register_block_type()
	 * does the enqueue). Conditional enqueue still applies for shortcode
	 * and CPT/archive/taxonomy contexts.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_register_style(
			'ask-adam-doc-it-public',
			AADI_PLUGIN_URL . 'public/css/public.css',
			array(),
			AADI_VERSION
		);

		if ( ! $this->should_enqueue_assets() ) {
			return;
		}
		wp_enqueue_style( 'ask-adam-doc-it-public' );
	}

	/**
	 * Enqueue public scripts.
	 *
	 * Registers the script handle unconditionally so block.json's
	 * `viewScript` reference resolves on block-only pages. The localized
	 * `aadiPublic` object is attached at registration time so it ships
	 * with the handle regardless of which path enqueues it.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_register_script(
			'ask-adam-doc-it-public',
			AADI_PLUGIN_URL . 'public/js/public.js',
			array( 'jquery' ),
			AADI_VERSION,
			true
		);
		wp_localize_script(
			'ask-adam-doc-it-public',
			'aadiPublic',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'aadi_public_search' ),
				'summarizeEnabled' => AADI_Settings::is_summarize_enabled(),
				'strings'          => array(
					'searching'       => __( 'Searching...', 'ask-adam-doc-it' ),
					'no_results'      => __( 'No documents found.', 'ask-adam-doc-it' ),
					'error'           => __( 'Search error. Please try again.', 'ask-adam-doc-it' ),
					'search'          => __( 'Search', 'ask-adam-doc-it' ),
					'download'        => __( 'Download', 'ask-adam-doc-it' ),
					'summarize'       => __( 'Summarize', 'ask-adam-doc-it' ),
					'summarizing'     => __( 'Summarizing...', 'ask-adam-doc-it' ),
					'summarize_error' => __( 'Could not generate a summary. Please try again.', 'ask-adam-doc-it' ),
				),
			)
		);

		if ( ! $this->should_enqueue_assets() ) {
			return;
		}
		wp_enqueue_script( 'ask-adam-doc-it-public' );
	}

	/**
	 * Override single/archive templates for the CPT, respecting theme overrides.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function template_include( $template ) {
		// Single document.
		if ( is_singular( AADI_CPT ) ) {
			$theme = get_stylesheet_directory() . '/ask-adam-doc-it/single-aadi.php';
			if ( file_exists( $theme ) ) {
				return $theme;
			}
			$plugin = AADI_PLUGIN_DIR . 'templates/single-aadi.php';
			if ( file_exists( $plugin ) ) {
				return $plugin;
			}
		}

		// Archive / taxonomy archive.
		if ( is_post_type_archive( AADI_CPT ) || is_tax( AADI_TAXONOMY ) ) {
			$theme = get_stylesheet_directory() . '/ask-adam-doc-it/archive-aadi.php';
			if ( file_exists( $theme ) ) {
				return $theme;
			}
			$plugin = AADI_PLUGIN_DIR . 'templates/archive-aadi.php';
			if ( file_exists( $plugin ) ) {
				return $plugin;
			}
		}

		return $template;
	}

	/**
	 * Apply the shared site-wide AI search rate limit.
	 *
	 * Returns the effective mode — may be downgraded to 'core' when the
	 * hourly bucket is exhausted. Prevents the free plugin from becoming
	 * an open OpenAI proxy. No PII, no IP — a single salted bucket per hour.
	 *
	 * Only requests that will actually invoke OpenAI consume the bucket:
	 * empty queries and sites without an API key both degrade to core in
	 * AADI_Search::ai_search(), so charging them here would needlessly
	 * starve later real AI searches.
	 *
	 * @param string $mode  Requested mode.
	 * @param string $query Search query (optional — used to skip empty searches).
	 * @return string Effective mode after limiting.
	 */
	public static function apply_rate_limit( $mode, $query = '' ) {
		$mode = sanitize_key( (string) $mode );
		if ( 'core' === $mode ) {
			return 'core';
		}

		// AI not configured — no OpenAI call will happen.
		if ( ! AADI_Settings::is_ai_enabled() ) {
			return $mode;
		}

		// Empty queries degrade to core inside ai_search() — no OpenAI call.
		if ( '' === trim( (string) $query ) ) {
			return $mode;
		}

		// gmdate() — timezone-agnostic hourly bucket for rate limiting.
		$window = gmdate( 'Y-m-d-H' );
		$salt   = wp_salt( 'auth' );
		$token  = hash( 'sha256', 'search' . $window . $salt );
		$key    = 'aadi_srch_' . substr( $token, 0, 40 );
		$count  = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_PER_HOUR ) {
			return 'core';
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return $mode;
	}

	/**
	 * Site-wide hourly throttle for fresh summary generations.
	 *
	 * Mirrors apply_rate_limit(): a single salted, PII-free hourly bucket.
	 * Returns false once the cap is reached so the caller can refuse the
	 * generation. Only invoked on cache misses, so cached repeats are free.
	 *
	 * @return bool True if a fresh generation is allowed; false if capped.
	 */
	public static function apply_summarize_rate_limit() {
		$window = gmdate( 'Y-m-d-H' );
		$salt   = wp_salt( 'auth' );
		$token  = hash( 'sha256', 'summarize' . $window . $salt );
		$key    = 'aadi_sum_rl_' . substr( $token, 0, 40 );
		$count  = (int) get_transient( $key );

		if ( $count >= self::SUMMARIZE_RATE_LIMIT_PER_HOUR ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Map a list of WP_Post objects to safe-for-JSON associative arrays.
	 *
	 * Returned values are *sanitized text*, not HTML-encoded — the JS
	 * client escapes once at the DOM-insertion sink to avoid double
	 * encoding (titles like "A & B" otherwise display as "A &amp; B").
	 *
	 * @param array $posts WP_Post[].
	 * @return array
	 */
	public static function format_posts_for_response( $posts ) {
		$out = array();
		if ( ! is_array( $posts ) ) {
			return $out;
		}
		foreach ( $posts as $post ) {
			if ( ! ( $post instanceof WP_Post ) ) {
				continue;
			}

			$post_id   = (int) $post->ID;
			$file_id   = absint( get_post_meta( $post_id, '_aadi_file_id', true ) );
			$file_type = (string) get_post_meta( $post_id, '_aadi_file_type', true );
			$file_ext  = (string) get_post_meta( $post_id, '_aadi_file_ext', true );
			$file_size = absint( get_post_meta( $post_id, '_aadi_file_size', true ) );
			$downloads = absint( get_post_meta( $post_id, '_aadi_download_count', true ) );

			$icon_class = 'aadi-icon-file';
			if ( 'application/pdf' === $file_type ) {
				$icon_class = 'aadi-icon-pdf';
			} elseif ( 0 === strpos( $file_type, 'image/' ) ) {
				$icon_class = 'aadi-icon-image';
			} elseif ( 0 === strpos( $file_type, 'audio/' ) ) {
				$icon_class = 'aadi-icon-audio';
			} elseif ( 0 === strpos( $file_type, 'video/' ) ) {
				$icon_class = 'aadi-icon-video';
			}

			$meta_bits = array();
			$type_lbl  = '' !== $file_ext ? strtoupper( $file_ext ) : $file_type;
			if ( '' !== $type_lbl ) {
				$meta_bits[] = $type_lbl;
			}
			if ( $file_size > 0 ) {
				$meta_bits[] = size_format( $file_size );
			}
			$meta_bits[] = sprintf(
				/* translators: %s: localized download count. */
				_n( '%s download', '%s downloads', $downloads, 'ask-adam-doc-it' ),
				number_format_i18n( $downloads )
			);

			$out[] = array(
				'id'           => $post_id,
				'title'        => sanitize_text_field( wp_strip_all_tags( get_the_title( $post ) ) ),
				'permalink'    => get_permalink( $post ),
				'excerpt'      => sanitize_text_field( wp_strip_all_tags( wp_trim_words( get_the_excerpt( $post ), 20 ) ) ),
				'file_type'    => sanitize_text_field( $file_type ),
				'file_size'    => $file_size > 0 ? size_format( $file_size ) : '',
				'downloads'    => $downloads,
				'meta_text'    => implode( ' · ', $meta_bits ),
				'icon_class'   => $icon_class,
				'has_file'     => $file_id > 0,
				'download_url' => $file_id > 0
					? get_rest_url( null, 'ask-adam-doc-it/v1/download/' . $post_id )
					: '',
			);
		}
		return $out;
	}

	/**
	 * Handle AJAX search request.
	 *
	 * @return void
	 */
	public function handle_search_ajax() {
		check_ajax_referer( 'aadi_public_search', 'nonce' );

		$query    = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$category = isset( $_POST['category'] ) ? absint( wp_unslash( $_POST['category'] ) ) : 0;
		$page     = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
		$mode     = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';

		if ( ! in_array( $mode, array( 'auto', 'ai', 'core' ), true ) ) {
			$mode = 'auto';
		}

		// Apply rate limit only when AI would actually run.
		$mode = self::apply_rate_limit( $mode, $query );

		$search  = new AADI_Search();
		$results = $search->search(
			$query,
			array(
				'per_page' => 10,
				'page'     => $page,
				'category' => $category,
				'mode'     => $mode,
			)
		);

		wp_send_json_success(
			array(
				'posts'     => self::format_posts_for_response( $results['posts'] ),
				'total'     => absint( $results['total'] ),
				'pages'     => absint( $results['pages'] ),
				'mode_used' => sanitize_key( $results['mode_used'] ),
			)
		);
		wp_die();
	}

	/**
	 * Handle AJAX document-summarize request.
	 *
	 * Returns a short AI-written summary for a document via the WordPress 7.0
	 * AI Client (wp_ai_client_prompt()). Results are cached in a transient
	 * keyed by post ID + a hash of the source text, so repeat clicks on any
	 * document serve from cache without a new generation call. Only cache
	 * misses consume the site-wide hourly generation bucket.
	 *
	 * @return void
	 */
	public function handle_summarize_ajax() {
		check_ajax_referer( 'aadi_public_search', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! AADI_Settings::is_summarize_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Summaries are not available.', 'ask-adam-doc-it' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || AADI_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'ask-adam-doc-it' ) ) );
		}

		// Password-protected documents must not leak their contents (title,
		// excerpt, admin summary) to anonymous visitors via a summary.
		if ( '' !== (string) $post->post_password ) {
			wp_send_json_error( array( 'message' => __( 'Protected documents cannot be summarized.', 'ask-adam-doc-it' ) ) );
		}

		// Assemble source material: title + admin summary meta + excerpt.
		$title   = wp_strip_all_tags( get_the_title( $post ) );
		$meta    = wp_strip_all_tags( (string) get_post_meta( $post_id, '_aadi_doc_summary', true ) );
		$excerpt = wp_strip_all_tags( get_the_excerpt( $post ) );

		// Require substantive source beyond the title. Summarizing a
		// title/filename alone wastes the API budget and yields a summary that
		// describes nothing the user can't already see on the card.
		$has_substance = '' !== trim( $meta ) || '' !== trim( $excerpt );
		if ( ! $has_substance ) {
			wp_send_json_error( array( 'message' => __( 'There is not enough information to summarize this document.', 'ask-adam-doc-it' ) ) );
		}

		$source_parts = array();
		if ( '' !== trim( $title ) ) {
			$source_parts[] = 'Title: ' . $title;
		}
		if ( '' !== trim( $meta ) ) {
			$source_parts[] = 'Description: ' . $meta;
		}
		if ( '' !== trim( $excerpt ) ) {
			$source_parts[] = 'Excerpt: ' . $excerpt;
		}

		$source = implode( "\n", $source_parts );

		// Serve from cache if available — no rate-limit consumed.
		$cache_key = 'aadi_sum_' . $post_id . '_' . substr( md5( $source ), 0, 16 );
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			wp_send_json_success(
				array(
					'summary' => $cached,
					'cached'  => true,
				)
			);
		}

		// Don't spend the rate-limit budget on a request that cannot succeed.
		// is_summarize_enabled() (checked above) already confirms the AI
		// Client is available and supports text generation.

		// Fresh generation — throttle to protect the API budget.
		if ( ! self::apply_summarize_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'The summary service is busy right now. Please try again later.', 'ask-adam-doc-it' ) ) );
		}

		$result = wp_ai_client_prompt( $source )
			->using_system_instruction(
				'You are a helpful assistant that writes concise, plain-English summaries of documents to help a reader decide whether to download them. Reply with 2-3 sentences. No preamble, no markdown, no bullet points.'
			)
			->using_max_tokens( 150 )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not generate summary.', 'ask-adam-doc-it' ),
				)
			);
			wp_die();
		}

		$summary = sanitize_text_field( $result );

		if ( '' === trim( $summary ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not generate a summary. Please try again.', 'ask-adam-doc-it' ) ) );
		}

		set_transient( $cache_key, $summary, WEEK_IN_SECONDS );

		wp_send_json_success(
			array(
				'summary' => $summary,
				'cached'  => false,
			)
		);
	}
}
