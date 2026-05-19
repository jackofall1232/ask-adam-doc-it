<?php
/**
 * OpenAI API client wrapper.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_OpenAI
 *
 * Thin wrapper around the OpenAI REST API for embeddings and chat
 * completions. All requests have hard timeouts. All responses are
 * defensively parsed. The API key is never logged or echoed.
 */
class AADI_OpenAI {

	const EMBEDDING_MODEL     = 'text-embedding-3-small';
	const CHAT_MODEL          = 'gpt-5-mini';
	const CHAT_MODEL_SNAPSHOT = 'gpt-5-mini-2025-08-07';
	// Hard API cap for gpt-5-mini on v1/chat/completions.
	// GPT-5 series does not accept max_tokens — use max_completion_tokens.
	const CHAT_MAX_TOKENS_CAP = 4096;
	const EMBEDDING_DIMS      = 1536;
	const API_BASE            = 'https://api.openai.com/v1';
	const MAX_EMBEDDING_CHARS = 8000;
	const REQUEST_TIMEOUT     = 15;

	/**
	 * Stored API key. Never exposed.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Most recent error message, suitable for surfacing to admins.
	 *
	 * @var string
	 */
	private string $last_error = '';

	/**
	 * Constructor.
	 *
	 * @param string $api_key OpenAI API key.
	 */
	public function __construct( $api_key ) {
		$this->api_key = sanitize_text_field( (string) $api_key );
	}

	/**
	 * Whether a usable key is configured and the circuit breaker is closed.
	 *
	 * @return bool
	 */
	public function is_configured() {
		if ( '' === $this->api_key ) {
			return false;
		}
		if ( 0 !== strpos( $this->api_key, 'sk-' ) ) {
			return false;
		}
		if ( get_option( 'aadi_openai_auth_failed' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Generate an embedding vector for the given text.
	 *
	 * @param string $text Text to embed.
	 * @return array<int,float>|false Embedding vector or false on failure.
	 */
	public function get_embedding( $text ) {
		if ( ! $this->is_configured() ) {
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

		$body = wp_json_encode(
			array(
				'model'           => self::EMBEDDING_MODEL,
				'input'           => $text,
				'encoding_format' => 'float',
			)
		);

		$response = wp_remote_post(
			self::API_BASE . '/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_error( $response, 'get_embedding' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Check 401 before requiring a decodable body — a rejected key
		// with an empty/malformed response must still trip the breaker.
		if ( 401 === (int) $code ) {
			$this->trigger_circuit_breaker();
			return false;
		}

		$decoded = $this->handle_response( $response );

		if ( false === $decoded ) {
			$this->handle_error( $response, 'get_embedding' );
			return false;
		}
		if ( 200 !== (int) $code ) {
			$this->handle_error( $response, 'get_embedding' );
			return false;
		}

		if ( ! isset( $decoded['data'] ) || ! is_array( $decoded['data'] ) ) {
			$this->handle_error( $response, 'invalid_embedding_response' );
			return false;
		}
		if ( ! isset( $decoded['data'][0]['embedding'] ) ) {
			$this->handle_error( $response, 'invalid_embedding_response' );
			return false;
		}
		if ( ! is_array( $decoded['data'][0]['embedding'] ) ) {
			$this->handle_error( $response, 'invalid_embedding_response' );
			return false;
		}
		if ( count( $decoded['data'][0]['embedding'] ) !== self::EMBEDDING_DIMS ) {
			$this->handle_error( $response, 'invalid_embedding_response' );
			return false;
		}

		return $decoded['data'][0]['embedding'];
	}

	/**
	 * Run a chat completion request.
	 *
	 * GPT-5 series requires max_completion_tokens (NOT max_tokens).
	 *
	 * @param array<int,array<string,string>> $messages   Chat messages.
	 * @param string                          $model      Model name.
	 * @param int                             $max_tokens Max output tokens.
	 * @return string|false Trimmed assistant message or false on failure.
	 */
	public function chat_completion( $messages, $model = self::CHAT_MODEL_SNAPSHOT, $max_tokens = 500 ) {
		if ( ! $this->is_configured() ) {
			return false;
		}

		if ( ! is_array( $messages ) || empty( $messages ) ) {
			return false;
		}
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				return false;
			}
			if ( ! isset( $message['role'] ) || ! is_string( $message['role'] ) ) {
				return false;
			}
			if ( ! isset( $message['content'] ) || ! is_string( $message['content'] ) ) {
				return false;
			}
		}

		$max_tokens = min( absint( $max_tokens ), self::CHAT_MAX_TOKENS_CAP );
		if ( $max_tokens < 1 ) {
			$max_tokens = 500;
		}

		$body = wp_json_encode(
			array(
				'model'                 => $model,
				'messages'              => $messages,
				'max_completion_tokens' => $max_tokens,
			)
		);

		$response = wp_remote_post(
			self::API_BASE . '/chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
				'timeout' => self::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_error( $response, 'chat_completion' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// Check 401 before requiring a decodable body — a rejected key
		// with an empty/malformed response must still trip the breaker.
		if ( 401 === (int) $code ) {
			$this->trigger_circuit_breaker();
			return false;
		}

		$decoded = $this->handle_response( $response );

		if ( false === $decoded ) {
			$this->handle_error( $response, 'chat_completion' );
			return false;
		}
		if ( 200 !== (int) $code ) {
			$this->handle_error( $response, 'chat_completion' );
			return false;
		}

		if ( ! isset( $decoded['choices'] ) || ! is_array( $decoded['choices'] ) ) {
			$this->handle_error( $response, 'invalid_chat_response' );
			return false;
		}
		if ( ! isset( $decoded['choices'][0]['message']['content'] ) ) {
			$this->handle_error( $response, 'invalid_chat_response' );
			return false;
		}
		if ( ! is_string( $decoded['choices'][0]['message']['content'] ) ) {
			$this->handle_error( $response, 'invalid_chat_response' );
			return false;
		}

		return trim( $decoded['choices'][0]['message']['content'] );
	}

	/**
	 * Decode the JSON body of a successful response.
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return array<string,mixed>|false
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return false;
		}
		$decoded = json_decode( $body, true );
		if ( null === $decoded ) {
			return false;
		}
		if ( ! is_array( $decoded ) ) {
			return false;
		}
		return $decoded;
	}

	/**
	 * Record an error for surfacing later and (in WP_DEBUG) the log.
	 *
	 * The API key is never appended to the message.
	 *
	 * @param array|WP_Error $response Original response.
	 * @param string         $context  Short context tag.
	 * @return void
	 */
	private function handle_error( $response, $context = '' ) {
		$message = 'Ask Adam Doc It [' . sanitize_key( $context ) . ']: ';

		if ( is_wp_error( $response ) ) {
			$message .= $response->get_error_message();
		} else {
			$code     = (int) wp_remote_retrieve_response_code( $response );
			$message .= 'HTTP ' . $code;
			$decoded  = $this->handle_response( $response );
			if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
				$message .= ' — ' . $decoded['error']['message'];
			}
		}

		$this->last_error = $message;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * Mark the key as rejected. Persists until settings are re-saved.
	 *
	 * @return void
	 */
	private function trigger_circuit_breaker() {
		update_option( 'aadi_openai_auth_failed', true );
		$this->last_error = __(
			'OpenAI API key rejected (401). Please update your key in Settings.',
			'ask-adam-doc-it'
		);
	}

	/**
	 * Most recent error message string.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}
}
