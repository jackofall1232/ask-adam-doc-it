<?php
/**
 * Search engine for the document library.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Search
 *
 * Provides both classic keyword search and AI-powered semantic search.
 *
 * Semantic search scores pre-computed embeddings already stored in
 * postmeta (generated during admin save / manual regenerate). The only
 * outbound OpenAI call from the search path is the one used to embed
 * the search query itself — never per-document.
 */
class AADI_Search {

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * High-level search entry point.
	 *
	 * @param string $query Search query.
	 * @param array  $args  Search args.
	 * @return array{posts:array,total:int,pages:int,mode_used:string,query:string}
	 */
	public function search( $query, $args = array() ) {
		$query = sanitize_text_field( (string) $query );

		$defaults = array(
			'per_page' => 10,
			'page'     => 1,
			'category' => 0,
			'mode'     => 'auto',
			'orderby'  => 'date',
			'order'    => 'DESC',
		);
		$args = is_array( $args ) ? array_merge( $defaults, $args ) : $defaults;

		$args['per_page'] = max( 1, min( 50, absint( $args['per_page'] ) ) );
		$args['page']     = max( 1, absint( $args['page'] ) );
		$args['category'] = absint( $args['category'] );

		$mode = sanitize_key( (string) $args['mode'] );
		if ( ! in_array( $mode, array( 'auto', 'ai', 'core' ), true ) ) {
			$mode = 'auto';
		}
		$args['mode'] = $mode;

		$orderby = sanitize_key( (string) $args['orderby'] );
		if ( ! in_array( $orderby, array( 'date', 'title', 'downloads' ), true ) ) {
			$orderby = 'date';
		}
		$args['orderby'] = $orderby;

		$order = strtoupper( (string) $args['order'] );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}
		$args['order'] = $order;

		switch ( $mode ) {
			case 'core':
				$results = $this->core_search( $query, $args );
				break;
			case 'ai':
				$results = $this->is_ai_enabled()
					? $this->ai_search( $query, $args )
					: $this->core_search( $query, $args );
				break;
			case 'auto':
			default:
				$results = $this->is_ai_enabled()
					? $this->ai_search( $query, $args )
					: $this->core_search( $query, $args );
				break;
		}

		$results['query'] = $query;
		return $results;
	}

	/**
	 * Classic WP_Query-based keyword search.
	 *
	 * @param string $query Search query.
	 * @param array  $args  Search args.
	 * @return array
	 */
	private function core_search( $query, $args ) {
		$wp_args = array(
			'post_type'      => AADI_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $args['per_page'],
			'paged'          => (int) $args['page'],
			'order'          => isset( $args['order'] ) ? (string) $args['order'] : 'DESC',
		);

		if ( '' !== $query ) {
			$wp_args['s'] = $query;
		}

		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'date';
		if ( 'downloads' === $orderby ) {
			$wp_args['meta_key'] = '_aadi_download_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$wp_args['orderby']  = 'meta_value_num';
		} elseif ( 'title' === $orderby ) {
			$wp_args['orderby'] = 'title';
		} else {
			$wp_args['orderby'] = 'date';
		}

		if ( $args['category'] > 0 ) {
			$wp_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => AADI_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => (int) $args['category'],
				),
			);
		}

		$q = new WP_Query( $wp_args );

		$results              = $this->format_results( $q, $args );
		$results['mode_used'] = 'core';
		return $results;
	}

	/**
	 * AI-powered semantic search using stored embeddings.
	 *
	 * @param string $query Search query.
	 * @param array  $args  Search args.
	 * @return array
	 */
	private function ai_search( $query, $args ) {
		if ( ! $this->is_ai_enabled() ) {
			return $this->core_search( $query, $args );
		}

		// An empty query is a "browse all" request — no semantic ranking
		// is meaningful, so degrade to recency-ordered core results.
		if ( '' === trim( $query ) ) {
			return $this->core_search( $query, $args );
		}

		$key       = (string) AADI_Settings::get_option( 'openai_api_key', '' );
		$openai    = new AADI_OpenAI( $key );
		$query_vec = $openai->get_embedding( sanitize_text_field( $query ) );

		if ( false === $query_vec ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
					'Ask Adam Doc It: query embedding failed, falling back to core search.'
				);
			}
			return $this->core_search( $query, $args );
		}

		$candidate_args = array(
			'post_type'      => AADI_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( $args['category'] > 0 ) {
			$candidate_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => AADI_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => (int) $args['category'],
				),
			);
		}

		$candidates = new WP_Query( $candidate_args );
		$post_ids   = array_map( 'absint', (array) $candidates->posts );

		if ( empty( $post_ids ) ) {
			return $this->empty_result( 'ai' );
		}

		// Prime the postmeta cache for the whole candidate pool so each
		// AADI_Embeddings::get_embedding() call hits the cache instead of
		// issuing its own SELECT — turns N queries into 1.
		update_meta_cache( 'post', $post_ids );

		$embeddings = new AADI_Embeddings();
		$scored     = array();

		foreach ( $post_ids as $post_id ) {
			$doc_vec = $embeddings->get_embedding( $post_id );
			if ( false === $doc_vec ) {
				continue;
			}
			$scored[] = array(
				'post_id' => (int) $post_id,
				'score'   => $this->cosine_similarity( $query_vec, $doc_vec ),
			);
		}

		if ( empty( $scored ) ) {
			// No documents have embeddings yet — fall back silently.
			return $this->core_search( $query, $args );
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);

		$total    = count( $scored );
		$per_page = (int) $args['per_page'];
		$pages    = (int) ceil( $total / max( 1, $per_page ) );
		$offset   = ( (int) $args['page'] - 1 ) * $per_page;
		$slice    = array_slice( $scored, max( 0, $offset ), $per_page );

		$ordered_ids = array_map(
			static function ( $row ) {
				return (int) $row['post_id'];
			},
			$slice
		);

		$posts = array();
		if ( ! empty( $ordered_ids ) ) {
			$posts = get_posts(
				array(
					'post_type'      => AADI_CPT,
					'post_status'    => 'publish',
					'posts_per_page' => count( $ordered_ids ),
					'post__in'       => $ordered_ids,
					'orderby'        => 'post__in',
					'no_found_rows'  => true,
				)
			);
		}

		return array(
			'posts'     => $posts,
			'total'     => $total,
			'pages'     => $pages,
			'mode_used' => 'ai',
			'query'     => $query,
		);
	}

	/**
	 * Cosine similarity between two equal-length float vectors.
	 *
	 * Pure PHP — must stay fast since this is called once per
	 * candidate document in the scoring loop. No WP calls inside.
	 *
	 * @param array $vec_a Vector A.
	 * @param array $vec_b Vector B.
	 * @return float Between -1.0 and 1.0.
	 */
	public function cosine_similarity( array $vec_a, array $vec_b ) {
		if ( empty( $vec_a ) || empty( $vec_b ) ) {
			return 0.0;
		}
		$count = count( $vec_a );
		if ( $count !== count( $vec_b ) ) {
			return 0.0;
		}

		$dot    = 0.0;
		$norm_a = 0.0;
		$norm_b = 0.0;

		for ( $i = 0; $i < $count; $i++ ) {
			$a       = (float) $vec_a[ $i ];
			$b       = (float) $vec_b[ $i ];
			$dot    += $a * $b;
			$norm_a += $a * $a;
			$norm_b += $b * $b;
		}

		if ( $norm_a <= 0.0 || $norm_b <= 0.0 ) {
			return 0.0;
		}

		return (float) ( $dot / ( sqrt( $norm_a ) * sqrt( $norm_b ) ) );
	}

	/**
	 * Normalize a WP_Query or post array into the standard result shape.
	 *
	 * @param WP_Query|array $source Query object or array of posts.
	 * @param array          $args   Search args (for per_page when array given).
	 * @return array
	 */
	private function format_results( $source, $args = array() ) {
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 10;

		if ( $source instanceof WP_Query ) {
			return array(
				'posts'     => is_array( $source->posts ) ? $source->posts : array(),
				'total'     => (int) $source->found_posts,
				'pages'     => (int) $source->max_num_pages,
				'mode_used' => '',
				'query'     => '',
			);
		}

		if ( is_array( $source ) ) {
			$total = count( $source );
			return array(
				'posts'     => $source,
				'total'     => $total,
				'pages'     => (int) ceil( $total / $per_page ),
				'mode_used' => '',
				'query'     => '',
			);
		}

		return $this->empty_result( '' );
	}

	/**
	 * Empty result helper.
	 *
	 * @param string $mode Mode label.
	 * @return array
	 */
	private function empty_result( $mode = '' ) {
		return array(
			'posts'     => array(),
			'total'     => 0,
			'pages'     => 0,
			'mode_used' => (string) $mode,
			'query'     => '',
		);
	}

	/**
	 * Convenience wrapper for AADI_Settings::is_ai_enabled().
	 *
	 * @return bool
	 */
	private function is_ai_enabled() {
		return AADI_Settings::is_ai_enabled();
	}
}
