<?php
/**
 * WordPress AI Client provider (WordPress 7.0+ Connectors).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Provider_WordPress_AI implements Multch_AI_Provider {

	/**
	 * @param array<int, array{role: string, content: string}> $messages
	 * @param array<string, mixed>                             $settings
	 * @return array{text: string, model: string, model_primary?: string, used_fallback?: bool}|WP_Error
	 */
	public function complete( string $system, array $messages, array $settings ) {
		if ( ! multch_ai_client_available() ) {
			return new WP_Error(
				'configuration_error',
				__( 'WordPress AI Client is not available. Use WordPress 7.0 or newer, or choose Ollama for a local model.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		if ( ! class_exists( 'WordPress\AiClient\Messages\DTO\UserMessage' ) ) {
			return new WP_Error(
				'configuration_error',
				__( 'WordPress AI Client libraries are not loaded.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$split = multch_ai_client_split_messages( $messages );
		if ( '' === $split['latest'] ) {
			return new WP_Error(
				'invalid_request',
				__( 'Invalid request.', 'multiai-chatbot' ),
				array( 'status' => 400, 'error_code' => 'INVALID_REQUEST' )
			);
		}

		$chain         = multch_ai_client_model_chain( $settings );
		$attempts      = ! empty( $chain ) ? $chain : array( '' );
		$model_primary = isset( $chain[0] ) ? (string) $chain[0] : '';
		$last_error    = null;
		$last_result   = null;

		foreach ( $attempts as $index => $model_id ) {
			$is_last   = ( $index === count( $attempts ) - 1 );
			$builder   = $this->create_builder( $system, $split );
			$prefs     = '' !== $model_id ? array( $model_id ) : array();
			$fallback  = '' !== $model_id ? $model_id : 'wordpress-ai';
			$result    = multch_ai_client_generate_from_builder( $builder, $prefs, $fallback );

			if ( is_wp_error( $result ) ) {
				$last_error = $result;
				if ( $is_last || ! multch_ai_client_should_try_next_model( $result ) ) {
					return $result;
				}
				continue;
			}

			$last_result = $result;
			if ( '' !== $result['text'] ) {
				$result['model_primary']  = $model_primary;
				$result['used_fallback']  = $index > 0 && '' !== $model_primary;
				return $result;
			}
		}

		if ( is_array( $last_result ) ) {
			$last_result['model_primary'] = $model_primary;
			$last_result['used_fallback'] = false;
			return $last_result;
		}

		if ( $last_error instanceof WP_Error ) {
			return $last_error;
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'The model did not return a valid response. Check Settings → Connectors and the model ID in AI Model.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}

	/**
	 * @param array{latest: string, history: list<object>} $split
	 * @return object|null
	 */
	private function create_builder( string $system, array $split ) {
		$builder = multch_wp_ai_client_prompt( $split['latest'] )
			->using_system_instruction( $system )
			->using_temperature( 0.2 )
			->using_max_tokens( 600 );

		if ( ! empty( $split['history'] ) ) {
			$builder = $builder->with_history( ...$split['history'] );
		}

		return $builder;
	}
}
