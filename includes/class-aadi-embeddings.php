<?php
/**
 * Embedding storage and lifecycle management.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Embeddings
 *
 * Generates, stores, and retrieves OpenAI embeddings for aadi_file
 * posts. The source text is assembled entirely from WordPress data
 * (title, excerpt, doc summary, category names) — no file parsing.
 *
 * Embedding generation on save_post is offloaded to a WP-Cron single
 * event so saves return immediately. The explicit `generate_embedding()`
 * entry point still runs synchronously for admin-triggered regeneration.
 */
class AADI_Embeddings {

	const META_KEY      = '_aadi_embedding';
	const META_KEY_INFO = '_aadi_embedding_info';
	const CRON_HOOK     = 'aadi_generate_embedding';

	// Embedding model parameters. The WordPress 7.0 AI Client does not yet
	// expose embedding generation, so embeddings are still produced by a
	// direct OpenAI REST call (see request_embedding()).
	const EMBEDDING_MODEL     = 'text-embedding-3-small';
	const EMBEDDING_DIMS      = 1536;
	const MAX_EMBEDDING_CHARS = 8000;
	const API_BASE            = 'https://api.openai.com/v1';
	const REQUEST_TIMEOUT     = 15;

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * AADI_Loader::define_core_hooks().
	 *
	 * The save_post_* hook is wired at priority 20 there so that
	 * AADI_Admin::save_meta_box (priority 10) has already persisted
	 * _aadi_doc_summary before this class reads it.
	 */
	public function __construct() {}

	/**
	 * Hook callback: queue an embedding regeneration after save.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_save_post( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! AADI_Settings::is_ai_enabled() ) {
			return;
		}

		$post_id = (int) $post_id;
		// wp_schedule_single_event de-duplicates same (hook,args) within
		// a ~10-minute window, so repeat saves coalesce into one job.
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Cron callback: do the actual OpenAI call and persist the vector.
	 *
	 * Runs outside the admin request so a slow API never blocks saves.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function process_embedding_job( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 ) {
			return;
		}
		if ( AADI_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		if ( ! AADI_Settings::is_ai_enabled() ) {
			return;
		}

		$text = $this->build_source_text( $post_id );
		if ( '' === $text ) {
			return;
		}

		// Source-text hash check — skip the OpenAI round-trip when the
		// assembled source text is byte-identical to the run that produced
		// the stored vector. Manual regenerate via generate_embedding()
		// deliberately bypasses this so admins can force a fresh call.
		$new_hash = md5( $text );
		$raw_info = get_post_meta( $post_id, self::META_KEY_INFO, true );
		$info     = is_string( $raw_info ) ? json_decode( $raw_info, true ) : null;
		if ( is_array( $info ) && isset( $info['source_hash'] ) && $info['source_hash'] === $new_hash ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
					sprintf( 'Ask Adam Doc It: skipping embedding, source text unchanged for post %d', $post_id )
				);
			}
			return;
		}

		$embedding = $this->request_embedding( $text );

		if ( false === $embedding ) {
			return;
		}

		$this->store_embedding( $post_id, $embedding );
		$this->write_info( $post_id, $embedding, $text, $new_hash );
	}

	/**
	 * Build the text used as the embedding source.
	 *
	 * @param int $post_id Post ID.
	 * @return string Assembled text, or '' if no usable content.
	 */
	private function build_source_text( $post_id ) {
		$pieces = array();

		$title = wp_strip_all_tags( (string) get_the_title( $post_id ) );
		$title = trim( $title );
		if ( '' !== $title ) {
			$pieces[] = $title;
		}

		$excerpt = wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $post_id ) );
		$excerpt = trim( $excerpt );
		if ( '' !== $excerpt ) {
			$pieces[] = $excerpt;
		}

		$summary = wp_strip_all_tags( (string) get_post_meta( $post_id, '_aadi_doc_summary', true ) );
		$summary = trim( $summary );
		if ( '' !== $summary ) {
			$pieces[] = $summary;
		}

		$terms = get_the_terms( $post_id, AADI_TAXONOMY );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$names = array();
			foreach ( $terms as $term ) {
				if ( isset( $term->name ) ) {
					$name = trim( wp_strip_all_tags( (string) $term->name ) );
					if ( '' !== $name ) {
						$names[] = $name;
					}
				}
			}
			if ( ! empty( $names ) ) {
				$pieces[] = implode( ', ', $names );
			}
		}

		if ( empty( $pieces ) ) {
			return '';
		}

		$text = implode( "\n", $pieces );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = (string) $text;
		// mb_substr to avoid splitting UTF-8 sequences when truncating.
		$text = function_exists( 'mb_substr' )
			? mb_substr( $text, 0, self::MAX_EMBEDDING_CHARS, 'UTF-8' )
			: substr( $text, 0, self::MAX_EMBEDDING_CHARS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'AADI_DEBUG' ) && AADI_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				'Ask Adam Doc It [embeddings]: source fields = '
				. implode( ',', $this->get_source_fields( $post_id ) )
			);
		}

		return $text;
	}

	/**
	 * List the source fields that contributed non-empty content.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,string>
	 */
	private function get_source_fields( $post_id ) {
		$fields = array();

		if ( '' !== trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) ) ) {
			$fields[] = 'title';
		}
		if ( '' !== trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $post_id ) ) ) ) {
			$fields[] = 'excerpt';
		}
		if ( '' !== trim( wp_strip_all_tags( (string) get_post_meta( $post_id, '_aadi_doc_summary', true ) ) ) ) {
			$fields[] = 'doc_summary';
		}
		$terms = get_the_terms( $post_id, AADI_TAXONOMY );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$fields[] = 'categories';
		}

		return $fields;
	}

	/**
	 * Write the embedding info blob (model, dims, timestamp, etc.).
	 *
	 * Documented fields on the stored JSON blob:
	 *  - model:         OpenAI embedding model identifier.
	 *  - dims:          Vector dimensionality.
	 *  - generated_at:  Local timestamp in `Y-m-d H:i:s`.
	 *  - source_length: Byte-length of the source text used.
	 *  - source_fields: Array of contributing field names.
	 *  - source_hash:   md5() of the source text — used by
	 *                   process_embedding_job() to skip no-op
	 *                   regenerations and avoid extra API spend.
	 *
	 * @param int               $post_id   Post ID.
	 * @param array<int,float>  $embedding Vector.
	 * @param string            $text      Source text used.
	 * @param string|null       $hash      Pre-computed md5 of $text. Null = compute here.
	 * @return void
	 */
	private function write_info( $post_id, array $embedding, $text, $hash = null ) {
		update_post_meta(
			$post_id,
			self::META_KEY_INFO,
			wp_json_encode(
				array(
					'model'         => self::EMBEDDING_MODEL,
					'dims'          => count( $embedding ),
					'generated_at'  => current_time( 'mysql' ),
					'source_length' => strlen( $text ),
					'source_fields' => $this->get_source_fields( $post_id ),
					'source_hash'   => null === $hash ? md5( $text ) : (string) $hash,
				)
			)
		);
	}

	/**
	 * Public entry point for explicit regeneration. Synchronous.
	 *
	 * Used by the admin "Regenerate Embedding" row action — admins
	 * expect immediate feedback when they click the link.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True on success.
	 */
	public function generate_embedding( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		if ( ! AADI_Settings::is_ai_enabled() ) {
			return false;
		}

		$text = $this->build_source_text( $post_id );
		if ( '' === $text ) {
			return false;
		}

		$embedding = $this->request_embedding( $text );

		if ( false === $embedding ) {
			return false;
		}

		$stored = $this->store_embedding( $post_id, $embedding );
		if ( ! $stored ) {
			return false;
		}

		$this->write_info( $post_id, $embedding, $text );

		return true;
	}

	/**
	 * Public entry point to embed an arbitrary string (e.g. a search query).
	 *
	 * @param string $text Text to embed.
	 * @return array<int,float>|false Embedding vector or false on failure.
	 */
	public function embed_text( $text ) {
		return $this->request_embedding( $text );
	}

	/**
	 * Generate an embedding vector for the given text.
	 *
	 * TODO: Replace with wp_ai_client_prompt() embedding support
	 * when WordPress AI Client adds embedding generation.
	 * Requires: AI Provider for OpenAI plugin installed and configured.
	 * See: https://wordpress.org/plugins/ai-provider-for-openai/
	 *
	 * The WordPress 7.0 AI Client does not yet expose embedding generation,
	 * so this still calls the OpenAI REST API directly. The API key is no
	 * longer stored in plugin settings; install the official "AI Provider
	 * for OpenAI" plugin (https://wordpress.org/plugins/ai-provider-for-openai/),
	 * which registers the credential through the core Connectors API, or
	 * supply a key via the `aadi_openai_api_key` filter. When no key and no
	 * AI Client are available the method degrades gracefully to false so the
	 * search path falls back to keyword search.
	 *
	 * @param string $text Text to embed.
	 * @return array<int,float>|false Embedding vector or false on failure.
	 */
	private function request_embedding( $text ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		// Resolve the OpenAI API key mirroring the same precedence order
		// used by the WordPress 7.0 Connectors API and the AI Provider for
		// OpenAI plugin: env var → PHP constant → Connectors DB option.
		// Core exposes no public getter for the resolved value, so the
		// precedence is replicated here. The filter allows advanced users
		// to override any resolved source.
		$api_key = '';

		$env_key = getenv( 'OPENAI_API_KEY' );
		if ( is_string( $env_key ) && '' !== $env_key ) {
			$api_key = $env_key;
		}

		if ( '' === $api_key && defined( 'OPENAI_API_KEY' ) ) {
			$api_key = (string) OPENAI_API_KEY;
		}

		if ( '' === $api_key ) {
			$api_key = (string) get_option( 'connectors_ai_openai_api_key', '' );
		}

		// Allow override of any resolved key.
		$api_key = (string) apply_filters( 'aadi_openai_api_key', $api_key );

		if ( '' === $api_key ) {
			// Throttle the warning so a misconfigured site doesn't flood the
			// debug log during bulk saves or repeated frontend searches.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! get_transient( 'aadi_key_warning_logged' ) ) {
				set_transient( 'aadi_key_warning_logged', 1, HOUR_IN_SECONDS );
				error_log( 'Ask Adam Doc It [embeddings]: No OpenAI API key found. Configure the AI Provider for OpenAI plugin under Settings → Connectors.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		$text = trim( wp_strip_all_tags( (string) $text ) );
		// mb_substr to avoid splitting UTF-8 sequences when truncating.
		$text = function_exists( 'mb_substr' )
			? mb_substr( $text, 0, self::MAX_EMBEDDING_CHARS, 'UTF-8' )
			: substr( $text, 0, self::MAX_EMBEDDING_CHARS );
		if ( '' === $text ) {
			return false;
		}

		$response = wp_remote_post(
			self::API_BASE . '/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'           => self::EMBEDDING_MODEL,
						'input'           => $text,
						'encoding_format' => 'float',
					)
				),
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Ask Adam Doc It [embeddings]: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// Embeddings run in a background cron job, so surfacing the
				// failure detail in the log is the only troubleshooting hook.
				error_log( 'Ask Adam Doc It [embeddings]: HTTP ' . $code . ' — ' . wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) {
			return false;
		}
		if ( ! isset( $decoded['data'][0]['embedding'] ) || ! is_array( $decoded['data'][0]['embedding'] ) ) {
			return false;
		}
		if ( count( $decoded['data'][0]['embedding'] ) !== self::EMBEDDING_DIMS ) {
			return false;
		}

		return $decoded['data'][0]['embedding'];
	}

	/**
	 * Persist an embedding vector to post meta.
	 *
	 * update_post_meta() returns false both on error and when the new
	 * value is identical to the old one — treat the no-op case as success
	 * so unchanged regenerations don't report a fake failure.
	 *
	 * @param int               $post_id   Post ID.
	 * @param array<int,float>  $embedding Vector.
	 * @return bool
	 */
	public function store_embedding( $post_id, array $embedding ) {
		$encoded = wp_json_encode( $embedding );
		if ( ! $encoded || '' === $encoded ) {
			return false;
		}
		$existing = get_post_meta( $post_id, self::META_KEY, true );
		if ( is_string( $existing ) && $existing === $encoded ) {
			return true;
		}
		return false !== update_post_meta( $post_id, self::META_KEY, $encoded );
	}

	/**
	 * Retrieve a stored embedding vector.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,float>|false
	 */
	public function get_embedding( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $raw ) ) {
			return false;
		}
		$embedding = json_decode( (string) $raw, true );
		if ( ! is_array( $embedding ) ) {
			return false;
		}
		if ( count( $embedding ) !== self::EMBEDDING_DIMS ) {
			return false;
		}
		return $embedding;
	}

	/**
	 * Delete both the vector and its info blob.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_embedding( $post_id ) {
		delete_post_meta( $post_id, self::META_KEY );
		delete_post_meta( $post_id, self::META_KEY_INFO );
	}

	/**
	 * Whether a valid embedding is stored.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function embedding_exists( $post_id ) {
		return false !== $this->get_embedding( $post_id );
	}

	/**
	 * Hook callback: clean up embeddings when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete_post( $post_id ) {
		if ( AADI_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		$this->delete_embedding( $post_id );
		// Drop any pending cron job for this post.
		wp_clear_scheduled_hook( self::CRON_HOOK, array( (int) $post_id ) );
	}

	/**
	 * High-level status used by row badges and notices.
	 *
	 * @param int $post_id Post ID.
	 * @return string One of 'disabled' | 'missing' | 'stale' | 'current'.
	 */
	public function get_embedding_status( $post_id ) {
		if ( ! AADI_Settings::is_ai_enabled() ) {
			return 'disabled';
		}
		if ( ! $this->embedding_exists( $post_id ) ) {
			return 'missing';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return 'missing';
		}

		$raw  = get_post_meta( $post_id, self::META_KEY_INFO, true );
		$info = json_decode( (string) $raw, true );

		if ( is_array( $info ) && isset( $info['generated_at'] ) ) {
			$generated = strtotime( (string) $info['generated_at'] );
			$modified  = strtotime( (string) $post->post_modified );
			if ( is_int( $generated ) && is_int( $modified ) && $generated < $modified ) {
				return 'stale';
			}
		}

		return 'current';
	}
}
