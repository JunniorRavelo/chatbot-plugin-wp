<?php
/**
 * WordPress AI Client helpers.
 *
 * @package Multch_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the site can use the WordPress AI Client API.
 */
function multch_ai_client_available(): bool {
	return function_exists( 'wp_ai_client_prompt' );
}

/**
 * Admin URL for Settings → Connectors (WordPress 7.0+).
 */
function multch_connectors_admin_url(): string {
	if ( function_exists( 'menu_page_url' ) ) {
		$url = menu_page_url( 'wp-connectors', false );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	return admin_url( 'options-general.php?page=wp-connectors' );
}

/**
 * Legacy cloud provider IDs migrated to the WordPress AI Client.
 *
 * @return list<string>
 */
function multch_legacy_cloud_provider_ids(): array {
	return array( 'gemini', 'deepseek', 'openai_compatible' );
}

/**
 * @param array<int, array{role: string, content: string}> $messages
 * @return array{latest: string, history: list<object>}
 */
function multch_ai_client_split_messages( array $messages ): array {
	$latest  = '';
	$history = $messages;

	if ( ! empty( $history ) ) {
		$last = array_pop( $history );
		if ( 'user' === ( $last['role'] ?? '' ) ) {
			$latest = trim( (string) ( $last['content'] ?? '' ) );
		} else {
			$history[] = $last;
		}
	}

	return array(
		'latest'  => $latest,
		'history' => multch_ai_client_build_history_messages( $history ),
	);
}

/**
 * @param array<int, array{role: string, content: string}> $messages
 * @return list<object>
 */
function multch_ai_client_build_history_messages( array $messages ): array {
	if ( ! class_exists( 'WordPress\AiClient\Messages\DTO\MessagePart' ) ) {
		return array();
	}

	$part_class  = 'WordPress\AiClient\Messages\DTO\MessagePart';
	$user_class  = 'WordPress\AiClient\Messages\DTO\UserMessage';
	$model_class = 'WordPress\AiClient\Messages\DTO\ModelMessage';
	$built       = array();

	foreach ( $messages as $turn ) {
		$text = trim( (string) ( $turn['content'] ?? '' ) );
		if ( '' === $text ) {
			continue;
		}

		$part = new $part_class( $text );
		if ( 'user' === ( $turn['role'] ?? '' ) ) {
			$built[] = new $user_class( array( $part ) );
		} else {
			$built[] = new $model_class( array( $part ) );
		}
	}

	return $built;
}

/**
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function multch_ai_client_model_preferences( array $settings ): array {
	$preferred = ! empty( $settings['model'] ) ? trim( (string) $settings['model'] ) : '';
	$pool_raw  = ! empty( $settings['model_candidates'] ) ? (string) $settings['model_candidates'] : '';
	$pool      = array_filter( array_map( 'trim', explode( ',', $pool_raw ) ) );
	$merged    = array_values(
		array_unique(
			array_filter(
				array_merge(
					$preferred ? array( $preferred ) : array(),
					$pool
				)
			)
		)
	);

	return $merged;
}

/**
 * @param mixed $result GenerativeAiResult from the AI Client.
 */
function multch_ai_client_extract_text( $result ): string {
	if ( is_string( $result ) ) {
		return trim( $result );
	}

	if ( ! is_object( $result ) || ! method_exists( $result, 'toMessage' ) ) {
		return '';
	}

	$message = $result->toMessage();
	if ( ! is_object( $message ) || ! method_exists( $message, 'getParts' ) ) {
		return '';
	}

	$text = '';
	foreach ( $message->getParts() as $part ) {
		if ( is_object( $part ) && method_exists( $part, 'isText' ) && $part->isText() && method_exists( $part, 'getText' ) ) {
			$text .= (string) $part->getText();
		}
	}

	return trim( $text );
}

/**
 * @param mixed  $result   GenerativeAiResult from the AI Client.
 * @param string $fallback Model name when metadata is unavailable.
 */
function multch_ai_client_extract_model( $result, string $fallback ): string {
	if ( ! is_object( $result ) || ! method_exists( $result, 'getModelMetadata' ) ) {
		return $fallback;
	}

	$meta = $result->getModelMetadata();
	if ( ! is_object( $meta ) || ! method_exists( $meta, 'getName' ) ) {
		return $fallback;
	}

	$name = trim( (string) $meta->getName() );

	return '' !== $name ? $name : $fallback;
}

/**
 * @param WP_Error $error Error from wp_ai_client_prompt().
 * @return WP_Error
 */
function multch_ai_client_map_error( WP_Error $error ): WP_Error {
	$code    = $error->get_error_code();
	$message = $error->get_error_message();
	$data    = $error->get_error_data();
	$status  = 503;
	$app_code = 'PROVIDER_UPSTREAM';

	if ( is_array( $data ) && isset( $data['status'] ) ) {
		$status = (int) $data['status'];
	}

	if ( str_contains( strtolower( $code ), 'rate' ) || 429 === $status ) {
		return new WP_Error(
			'rate_limit_model',
			__( 'Provider rate limit reached. Please try again shortly.', 'multiai-chatbot' ),
			array(
				'status'      => 429,
				'error_code'  => 'RATE_LIMIT_MODEL_MINUTE',
				'retry_after' => 60,
			)
		);
	}

	if ( str_contains( strtolower( $code ), 'config' ) || str_contains( strtolower( $message ), 'not configured' ) ) {
		return new WP_Error(
			'configuration_error',
			__( 'No AI provider is configured. Open Settings → Connectors and connect a provider.', 'multiai-chatbot' ),
			array( 'status' => 503, 'error_code' => 'CONFIGURATION_ERROR' )
		);
	}

	if ( str_contains( strtolower( $code ), 'timeout' ) || str_contains( strtolower( $message ), 'timeout' ) ) {
		return new WP_Error(
			'provider_timeout',
			__( 'Could not connect to the AI provider.', 'multiai-chatbot' ),
			array( 'status' => 504, 'error_code' => 'PROVIDER_TIMEOUT' )
		);
	}

	return new WP_Error(
		'provider_upstream',
		'' !== $message ? $message : __( 'The AI provider returned an error.', 'multiai-chatbot' ),
		array( 'status' => max( 400, min( 599, $status ) ), 'error_code' => $app_code )
	);
}
