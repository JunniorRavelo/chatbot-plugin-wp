<?php
/**
 * Google Gemini provider (direct API with site-owned API key).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Provider_Google_IA implements Multch_AI_Provider {

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @param array<string, mixed>                             $settings
	 * @return array{text: string, model: string, model_primary?: string, used_fallback?: bool}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings ) {
		$api_key = self::resolve_api_key( $settings );
		if ( '' === $api_key ) {
			return new WP_Error(
				'configuration_error',
				__( 'Google AI API key is not configured.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$chain         = multch_ai_client_model_chain( $settings );
		$attempts      = ! empty( $chain ) ? $chain : array( 'gemini-2.5-flash' );
		$model_primary = (string) $attempts[0];
		$timeout       = isset( $settings['request_timeout'] ) ? (int) $settings['request_timeout'] : 22;
		$user_prompt   = self::build_user_prompt( $messages );

		foreach ( $attempts as $index => $model ) {
			$result = self::request_model( $api_key, $model, $system, $user_prompt, $timeout );

			if ( is_wp_error( $result ) ) {
				$is_last = ( $index === count( $attempts ) - 1 );
				if ( $is_last || ! multch_ai_client_should_try_next_model( $result ) ) {
					return $result;
				}
				continue;
			}

			$result['model_primary']  = $model_primary;
			$result['used_fallback']  = $index > 0;
			return $result;
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'Models are not available at this time. Please try again later.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}

	/**
	 * @return array{text: string, model: string}|WP_Error
	 */
	private static function request_model( string $api_key, string $model, string $system, string $user_prompt, int $timeout ) {
		$model = multch_ai_client_normalize_model_id( $model );
		if ( '' === $model ) {
			return new WP_Error(
				'configuration_error',
				__( 'No Google AI model is configured.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$endpoint = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => max( 5, $timeout ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'systemInstruction' => array(
							'parts' => array( array( 'text' => $system ) ),
						),
						'contents'          => array(
							array(
								'role'  => 'user',
								'parts' => array( array( 'text' => $user_prompt ) ),
							),
						),
						'generationConfig'  => array(
							'temperature'     => 0.2,
							'maxOutputTokens' => 600,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( self::is_timeout_error( $response ) ) {
				return new WP_Error(
					'provider_timeout',
					__( 'The AI provider took too long to respond.', 'multiai-chatbot' ),
					array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
				);
			}

			return new WP_Error(
				'provider_upstream',
				__( 'Could not reach the Google AI API.', 'multiai-chatbot' ),
				array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'Google AI rate limit reached. Try again shortly or switch to the fallback model.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}
		if ( 404 === $code || 400 === $code ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'The selected Google AI model is not available.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'provider_upstream',
				__( 'Google AI returned an unexpected response.', 'multiai-chatbot' ),
				array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = '';
		if ( is_array( $body ) && isset( $body['candidates'][0]['content']['parts'] ) ) {
			foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
				$text .= isset( $part['text'] ) ? (string) $part['text'] : '';
			}
		}
		$text = trim( self::sanitize_output( $text ) );

		if ( '' === $text ) {
			return new WP_Error(
				'provider_upstream',
				__( 'Google AI returned an empty response.', 'multiai-chatbot' ),
				array( 'status' => 502, 'error_code' => 'PROVIDER_UPSTREAM' )
			);
		}

		return array(
			'text'  => $text,
			'model' => $model,
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private static function resolve_api_key( array $settings ): string {
		$from_config = multch_resolve_constant( 'MULTCH_GEMINI_API_KEY', 'CHATBOT_GEMINI_API_KEY' );
		if ( '' !== $from_config ) {
			return $from_config;
		}

		return ! empty( $settings['api_key'] ) ? (string) $settings['api_key'] : '';
	}

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 */
	private static function build_user_prompt( array $messages ): string {
		$lines = array();
		foreach ( $messages as $message ) {
			$role    = 'assistant' === ( $message['role'] ?? '' ) ? 'Assistant' : 'User';
			$content = trim( (string) ( $message['content'] ?? '' ) );
			if ( '' !== $content ) {
				$lines[] = "{$role}: {$content}";
			}
		}

		return implode( "\n", $lines );
	}

	private static function sanitize_output( string $text ): string {
		$text = preg_replace( '/^\*?\s*style:/im', '', $text ) ?? $text;
		return trim( $text );
	}

	private static function is_timeout_error( WP_Error $error ): bool {
		$code = strtolower( $error->get_error_code() );
		return in_array( $code, array( 'http_request_failed', 'http_request_timeout' ), true )
			|| str_contains( strtolower( $error->get_error_message() ), 'timed out' );
	}
}
