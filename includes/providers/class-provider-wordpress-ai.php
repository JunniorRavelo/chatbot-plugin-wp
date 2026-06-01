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
	 * @return array{text: string, model: string}|WP_Error
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

		$builder = wp_ai_client_prompt( $split['latest'] )
			->using_system_instruction( $system )
			->using_temperature( 0.2 )
			->using_max_tokens( 600 );

		if ( ! empty( $split['history'] ) ) {
			$builder = $builder->with_history( ...$split['history'] );
		}

		$preferences = multch_ai_client_model_preferences( $settings );
		if ( ! empty( $preferences ) ) {
			$builder = $builder->using_model_preference( ...$preferences );
		}

		if ( method_exists( $builder, 'is_supported_for_text_generation' ) && ! $builder->is_supported_for_text_generation() ) {
			return new WP_Error(
				'configuration_error',
				__( 'No AI model is available. Open Settings → Connectors and connect a provider.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
			);
		}

		$result = $builder->generate_text_result();
		if ( is_wp_error( $result ) ) {
			return multch_ai_client_map_error( $result );
		}

		$fallback_model = ! empty( $preferences[0] ) ? (string) $preferences[0] : 'wordpress-ai';
		$text           = multch_ai_client_extract_text( $result );
		$model          = multch_ai_client_extract_model( $result, $fallback_model );

		if ( '' === $text ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'The model did not return a valid response.', 'multiai-chatbot' ),
				array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
			);
		}

		return array(
			'text'  => $text,
			'model' => $model,
		);
	}
}
