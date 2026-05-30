<?php
/**
 * Privacy policy suggested content for WordPress Privacy tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Privacy {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_content' ) );
	}

	public static function register_privacy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = self::get_policy_content();

		wp_add_privacy_policy_content(
			'MultiAI Chatbot for WordPress',
			wp_kses_post( $content )
		);
	}

	private static function get_policy_content(): string {
		$settings = Chatbot_Plugin::get_settings();
		$history_days = isset( $settings['history_retention_days'] ) ? (int) $settings['history_retention_days'] : 0;
		$telemetry_days = isset( $settings['telemetry_retention_days'] ) ? (int) $settings['telemetry_retention_days'] : 0;

		$history_retention = $history_days > 0
			? sprintf(
				/* translators: %d: number of days */
				__( 'Conversation history is automatically purged after %d days.', 'chatbot-plugin-wp' ),
				$history_days
			)
			: __( 'Conversation history is kept until manually deleted or until the plugin is uninstalled.', 'chatbot-plugin-wp' );

		$telemetry_retention = $telemetry_days > 0
			? sprintf(
				/* translators: %d: number of days */
				__( 'Telemetry events are automatically purged after %d days.', 'chatbot-plugin-wp' ),
				$telemetry_days
			)
			: __( 'Telemetry events are kept until manually purged or until the plugin is uninstalled.', 'chatbot-plugin-wp' );

		$sections = array(
			'<h2>' . esc_html__( 'What data this plugin collects', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html__( 'When visitors use the chat widget, the plugin may store and process the following data:', 'chatbot-plugin-wp' ) . '</p>',
			'<ul>',
			'<li>' . esc_html__( 'Chat messages sent by visitors and AI assistant replies.', 'chatbot-plugin-wp' ) . '</li>',
			'<li>' . esc_html__( 'An anonymous session identifier (hashed; not linked to WordPress user accounts by default).', 'chatbot-plugin-wp' ) . '</li>',
			'<li>' . esc_html__( 'Page URL and path where the conversation started.', 'chatbot-plugin-wp' ) . '</li>',
			'<li>' . esc_html__( 'Technical telemetry: provider, model, response status, latency, and error codes.', 'chatbot-plugin-wp' ) . '</li>',
			'<li>' . esc_html__( 'Temporary rate-limit data derived from the visitor IP address (hashed in transients).', 'chatbot-plugin-wp' ) . '</li>',
			'</ul>',

			'<h2>' . esc_html__( 'Data sent to third-party AI services', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html__( 'To generate responses, the plugin sends chat content to the AI provider configured by the site administrator (such as Google Gemini, DeepSeek, Ollama, or an OpenAI-compatible API). This typically includes:', 'chatbot-plugin-wp' ) . '</p>',
			'<ul>',
			'<li>' . esc_html__( 'The visitor message and recent conversation context.', 'chatbot-plugin-wp' ) . '</li>',
			'<li>' . esc_html__( 'The system prompt configured in the plugin settings.', 'chatbot-plugin-wp' ) . '</li>',
			'</ul>',
			'<p>' . esc_html__( 'The site administrator is responsible for choosing the provider and ensuring compliance with applicable privacy laws and the provider terms of service.', 'chatbot-plugin-wp' ) . '</p>',

			'<h2>' . esc_html__( 'Data not sent to the plugin author', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html__( 'This plugin does not send site data, chat content, or telemetry to the plugin author. Data is processed on your server and only forwarded to AI providers you configure.', 'chatbot-plugin-wp' ) . '</p>',

			'<h2>' . esc_html__( 'Retention', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html( $history_retention ) . '</p>',
			'<p>' . esc_html( $telemetry_retention ) . '</p>',

			'<h2>' . esc_html__( 'Uninstall', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html__( 'When the plugin is deleted via the WordPress admin, database tables, plugin settings, scheduled tasks, and plugin transients are removed. External log files configured via telemetry_log_path are not deleted automatically.', 'chatbot-plugin-wp' ) . '</p>',

			'<h2>' . esc_html__( 'Personal data requests', 'chatbot-plugin-wp' ) . '</h2>',
			'<p>' . esc_html__( 'Chat history is stored with anonymous session identifiers and is not linked to visitor email addresses or WordPress user accounts by default. Site administrators can review, export, or delete conversations from the Chatbot admin screens.', 'chatbot-plugin-wp' ) . '</p>',
		);

		return implode( "\n", $sections );
	}
}

Chatbot_Privacy::init();
