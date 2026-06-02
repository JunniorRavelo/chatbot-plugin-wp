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
	 * @return array{text: string, model: string, model_primary?: string, used_fallback?: bool, model_trace?: array}|WP_Error
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

		$chain            = multch_ai_client_model_chain( $settings );
		$attempts         = ! empty( $chain ) ? $chain : array( '' );
		$model_primary    = isset( $chain[0] ) ? (string) $chain[0] : '';
		$allow_google_any = multch_ai_client_allow_google_any_model( $settings );
		$attempt_log      = array();
		$model_trace      = array(
			'steps'   => array(),
			'outcome' => 'error',
			'planned' => multch_ai_client_trace_planned_steps( $chain, $allow_google_any ),
		);
		$last_error       = null;
		$last_result      = null;

		foreach ( $attempts as $index => $model_id ) {
			$is_last = ( $index === count( $attempts ) - 1 );
			$result  = $this->run_model_attempt( $system, $split, $model_id, $chain, $index, $is_last, $model_primary, $allow_google_any );

			if ( is_wp_error( $result ) ) {
				multch_ai_client_log_provider_attempt( $attempt_log, $model_id, $result );
				$this->trace_failed_step( $model_trace, $index, $model_id, $result, $chain );
				$last_error = $result;
				if ( $is_last || ! multch_ai_client_should_try_next_model( $result ) ) {
					break;
				}
				continue;
			}

			$last_result = $result;
			if ( is_array( $result ) ) {
				multch_ai_client_trace_record_success( $model_trace, $index, $model_id, $result, $chain, $allow_google_any );
				return multch_ai_client_attach_trace_to_result( $result, $model_trace );
			}
		}

		if ( $allow_google_any ) {
			if ( multch_ai_client_is_gemma_model( $model_primary ) ) {
				$gemma_retry = $this->run_model_attempt( $system, $split, $model_primary, $chain, 0, true, $model_primary, $allow_google_any );
				if ( is_array( $gemma_retry ) ) {
					multch_ai_client_trace_record_success( $model_trace, 0, $model_primary, $gemma_retry, $chain, $allow_google_any );
					return multch_ai_client_attach_trace_to_result( $gemma_retry, $model_trace );
				}
				if ( $gemma_retry instanceof WP_Error ) {
					multch_ai_client_log_provider_attempt( $attempt_log, $model_primary . '-retry', $gemma_retry );
					$this->trace_failed_step( $model_trace, 0, $model_primary, $gemma_retry, $chain, 'primary' );
					$last_error = $gemma_retry;
				}
			} else {
				$automatic = $this->attempt_google_automatic( $system, $split, $model_primary, $chain );
				if ( is_array( $automatic ) ) {
					multch_ai_client_trace_add_step(
						$model_trace,
						'google_automatic',
						__( 'Any available text model', 'multiai-chatbot' ),
						'success',
						array(
							'model_used' => (string) ( $automatic['model'] ?? '' ),
						)
					);
					$model_trace['outcome']     = 'success';
					$model_trace['model_final'] = (string) ( $automatic['model'] ?? '' );
					return multch_ai_client_attach_trace_to_result( $automatic, $model_trace );
				}
				if ( $automatic instanceof WP_Error ) {
					multch_ai_client_log_provider_attempt( $attempt_log, 'google-automatic', $automatic );
					multch_ai_client_trace_add_step(
						$model_trace,
						'google_automatic',
						__( 'Any available text model', 'multiai-chatbot' ),
						'failed',
						array(
							'error_code' => multch_ai_client_extract_error_code( $automatic ),
							'message'    => $automatic->get_error_message(),
						)
					);
					$last_error = $automatic;
				}
			}
		}

		if ( is_array( $last_result ) && '' !== trim( (string) ( $last_result['text'] ?? '' ) ) ) {
			multch_ai_client_trace_record_success( $model_trace, 0, $model_primary, $last_result, $chain, $allow_google_any );
			return multch_ai_client_attach_trace_to_result(
				multch_ai_client_finalize_provider_result( $last_result, $model_primary, 0, $chain ),
				$model_trace
			);
		}

		if ( $last_error instanceof WP_Error ) {
			if ( empty( $model_trace['steps'] ) && ! empty( $attempt_log ) ) {
				$model_trace = multch_ai_client_trace_from_attempt_log( $attempt_log, $chain, $allow_google_any );
			}
			$model_trace['planned'] = multch_ai_client_trace_planned_steps( $chain, $allow_google_any );
			$model_trace['outcome']   = 'error';

			return $this->wrap_error_with_trace( $last_error, $attempt_log, $model_trace );
		}

		return new WP_Error(
			'model_temp_unavailable',
			__( 'The model did not return a valid response. Check Settings → Connectors and the model ID in AI Model.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'MODEL_TEMP_UNAVAILABLE' )
		);
	}

	/**
	 * @param array{steps: list<array<string, mixed>>} $model_trace
	 */
	private function trace_failed_step( array &$model_trace, int $index, string $model_id, WP_Error $error, array $chain, string $slot_override = '' ): void {
		$slot = '' !== $slot_override ? $slot_override : multch_ai_client_trace_slot_for_index( $index, $chain );
		multch_ai_client_trace_add_step(
			$model_trace,
			$slot,
			$model_id,
			'failed',
			array(
				'error_code' => multch_ai_client_extract_error_code( $error ),
				'message'    => $error->get_error_message(),
			)
		);
	}

	/**
	 * @param list<array{model: string, error_code: string, message?: string}> $attempt_log
	 * @param array{steps: list<array<string, mixed>>, outcome: string}      $model_trace
	 * @return WP_Error
	 */
	private function wrap_error_with_trace( WP_Error $error, array $attempt_log, array $model_trace ): WP_Error {
		$wrapped = multch_ai_client_wrap_error_with_attempt_log( $error, $attempt_log );
		$data    = $wrapped->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['model_trace'] = $model_trace;

		return new WP_Error(
			$wrapped->get_error_code(),
			$wrapped->get_error_message(),
			$data
		);
	}

	/**
	 * @param list<string> $chain
	 * @return array<string, mixed>|WP_Error
	 */
	private function run_model_attempt(
		string $system,
		array $split,
		string $model_id,
		array $chain,
		int $index,
		bool $is_last,
		string $model_primary,
		bool $allow_google_any
	) {
		$builder  = $this->create_builder( $system, $split );
		$prefs    = '' !== $model_id ? array( $model_id ) : array();
		$fallback = '' !== $model_id ? $model_id : 'wordpress-ai';
		$result   = multch_ai_client_generate_from_builder( $builder, $prefs, $fallback );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( '' === trim( (string) ( $result['text'] ?? '' ) ) ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'The model did not return a valid response.', 'multiai-chatbot' ),
				array(
					'status'     => 503,
					'error_code' => 'MODEL_TEMP_UNAVAILABLE',
					'model'      => $model_id,
				)
			);
		}

		$actual_model    = (string) ( $result['model'] ?? '' );
		$requested_model = (string) ( $result['model_requested'] ?? $model_id );
		$substituted     = ! empty( $result['model_substituted'] );

		if ( $substituted ) {
			$actual_index = multch_ai_client_chain_index_of( $actual_model, $chain );

			if ( $actual_index > $index && multch_ai_client_is_allowed_response_model( $actual_model, (string) $chain[ $actual_index ], $chain ) ) {
				return multch_ai_client_finalize_provider_result( $result, $model_primary, $actual_index, $chain );
			}

			if ( multch_ai_client_is_provider_text_substitute( $actual_model ) ) {
				$result['fallback_configured'] = $model_id;
				$result['provider_rerouted']   = true;
				return multch_ai_client_finalize_provider_result( $result, $model_primary, $index, $chain );
			}
		}

		if ( multch_ai_client_response_matches_attempt( $actual_model, $model_id, $chain, $index ) ) {
			if ( ! multch_ai_client_is_allowed_response_model( $actual_model, $requested_model, $chain ) ) {
				return $this->model_not_allowed_error( $actual_model, $requested_model, $is_last, $allow_google_any );
			}

			return multch_ai_client_finalize_provider_result( $result, $model_primary, $index, $chain );
		}

		if ( multch_ai_client_is_provider_text_substitute( $actual_model ) ) {
			$result['fallback_configured'] = $model_id;
			$result['provider_rerouted']   = true;
			return multch_ai_client_finalize_provider_result( $result, $model_primary, $index, $chain );
		}

		return new WP_Error(
			'model_fallback_mismatch',
			sprintf(
				/* translators: 1: model ID configured for this step, 2: model ID the provider actually used */
				__( 'Configured model %1$s was not used. The provider answered with %2$s instead. Enable “Google automatic fallback” in AI Model settings or pick the model your API serves.', 'multiai-chatbot' ),
				$model_id,
				$actual_model
			),
			array(
				'status'     => 503,
				'error_code' => 'MODEL_FALLBACK_MISMATCH',
				'model'      => $model_id,
			)
		);
	}

	/**
	 * @param list<string> $chain
	 * @return array<string, mixed>|WP_Error
	 */
	private function attempt_google_automatic( string $system, array $split, string $model_primary, array $chain ) {
		$builder = $this->create_builder( $system, $split );
		$result  = multch_ai_client_generate_from_builder( $builder, array(), 'wordpress-ai' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( '' === trim( (string) ( $result['text'] ?? '' ) ) ) {
			return new WP_Error(
				'model_temp_unavailable',
				__( 'No AI model returned a valid response.', 'multiai-chatbot' ),
				array(
					'status'     => 503,
					'error_code' => 'MODEL_TEMP_UNAVAILABLE',
					'model'      => 'google-automatic',
				)
			);
		}

		$actual_model = (string) ( $result['model'] ?? '' );
		if ( '' !== $actual_model && ! multch_ai_client_is_provider_text_substitute( $actual_model ) ) {
			return new WP_Error(
				'model_not_allowed',
				sprintf(
					/* translators: %s: model ID returned by the provider */
					__( 'Google returned %s, which is not allowed for text chat.', 'multiai-chatbot' ),
					$actual_model
				),
				array(
					'status'     => 503,
					'error_code' => 'MODEL_NOT_ALLOWED',
					'model'      => 'google-automatic',
				)
			);
		}

		$last_configured = ! empty( $chain ) ? (string) $chain[ count( $chain ) - 1 ] : '';
		if ( '' !== $last_configured ) {
			$result['fallback_configured'] = $last_configured;
		}
		$result['google_auto_reroute'] = true;

		return multch_ai_client_finalize_provider_result( $result, $model_primary, max( 1, count( $chain ) - 1 ), $chain );
	}

	/**
	 * @return WP_Error
	 */
	private function model_not_allowed_error( string $actual_model, string $requested_model, bool $is_last, bool $allow_google_any ) {
		return new WP_Error(
			'model_substituted',
			sprintf(
				/* translators: 1: model ID used by the provider, 2: model ID that was requested */
				__( 'The provider switched to %1$s instead of %2$s. That model is not in your primary/fallback list.%3$s', 'multiai-chatbot' ),
				$actual_model,
				$requested_model,
				$allow_google_any && $is_last ? '' : ' ' . __( 'Enable “Google automatic fallback” or update AI Model settings.', 'multiai-chatbot' )
			),
			array(
				'status'     => 503,
				'error_code' => 'MODEL_NOT_ALLOWED',
				'model'      => $requested_model,
			)
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
