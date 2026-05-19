<?php
/**
 * Embedding storage and lifecycle management.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Embeddings
 *
 * Generates, stores, and retrieves OpenAI embeddings for ptai_file
 * posts. The source text is assembled entirely from WordPress data
 * (title, excerpt, doc summary, category names) — no file parsing.
 *
 * Embedding generation on save_post is offloaded to a WP-Cron single
 * event so saves return immediately. The explicit `generate_embedding()`
 * entry point still runs synchronously for admin-triggered regeneration.
 */
class PTAI_Embeddings {

	const META_KEY      = '_ptai_embedding';
	const META_KEY_INFO = '_ptai_embedding_info';
	const CRON_HOOK     = 'ptai_generate_embedding';

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * PTAI_Loader::define_core_hooks().
	 *
	 * The save_post_* hook is wired at priority 20 there so that
	 * PTAI_Admin::save_meta_box (priority 10) has already persisted
	 * _ptai_doc_summary before this class reads it.
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
		if ( ! PTAI_Settings::is_ai_enabled() ) {
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
		if ( PTAI_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		if ( ! PTAI_Settings::is_ai_enabled() ) {
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
					sprintf( 'PaperTrail AI: skipping embedding, source text unchanged for post %d', $post_id )
				);
			}
			return;
		}

		$key       = (string) PTAI_Settings::get_option( 'openai_api_key', '' );
		$openai    = new PTAI_OpenAI( $key );
		$embedding = $openai->get_embedding( $text );

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

		$summary = wp_strip_all_tags( (string) get_post_meta( $post_id, '_ptai_doc_summary', true ) );
		$summary = trim( $summary );
		if ( '' !== $summary ) {
			$pieces[] = $summary;
		}

		$terms = get_the_terms( $post_id, PTAI_TAXONOMY );
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
			? mb_substr( $text, 0, PTAI_OpenAI::MAX_EMBEDDING_CHARS, 'UTF-8' )
			: substr( $text, 0, PTAI_OpenAI::MAX_EMBEDDING_CHARS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'PTAI_DEBUG' ) && PTAI_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				'PaperTrail AI [embeddings]: source fields = '
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
		if ( '' !== trim( wp_strip_all_tags( (string) get_post_meta( $post_id, '_ptai_doc_summary', true ) ) ) ) {
			$fields[] = 'doc_summary';
		}
		$terms = get_the_terms( $post_id, PTAI_TAXONOMY );
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
					'model'         => PTAI_OpenAI::EMBEDDING_MODEL,
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
		if ( ! PTAI_Settings::is_ai_enabled() ) {
			return false;
		}

		$text = $this->build_source_text( $post_id );
		if ( '' === $text ) {
			return false;
		}

		$key       = (string) PTAI_Settings::get_option( 'openai_api_key', '' );
		$openai    = new PTAI_OpenAI( $key );
		$embedding = $openai->get_embedding( $text );

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
		if ( count( $embedding ) !== PTAI_OpenAI::EMBEDDING_DIMS ) {
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
		if ( PTAI_CPT !== get_post_type( $post_id ) ) {
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
		// Treat a tripped circuit breaker as disabled — the UI shouldn't
		// offer actions that will hit a known-bad key.
		if ( get_option( 'ptai_openai_auth_failed' ) ) {
			return 'disabled';
		}
		if ( ! PTAI_Settings::is_ai_enabled() ) {
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
