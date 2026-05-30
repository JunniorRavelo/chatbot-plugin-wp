<?php
/**
 * Plugin bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Plugin {

	private static ?Chatbot_Plugin $instance = null;

	public static function instance(): Chatbot_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		Chatbot_Admin_Settings::init();
		Chatbot_Rest_Api::init();
		Chatbot_Enqueue::init();
	}

	public function init(): void {
		Chatbot_Chat_History::maybe_upgrade();
		Chatbot_Admin_Settings::maybe_merge_settings();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'chatbot-plugin-wp',
			false,
			dirname( CHATBOT_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public static function activate(): void {
		Chatbot_Telemetry::create_table();
		Chatbot_Chat_History::create_tables();

		$stored = get_option( 'chatbot_plugin_settings', false );
		if ( false === $stored ) {
			add_option( 'chatbot_plugin_settings', Chatbot_Admin_Settings::default_settings() );
		} else {
			Chatbot_Admin_Settings::maybe_merge_settings();
		}

		Chatbot_Rest_Api::register_stream_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( 'chatbot_plugin_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = wp_parse_args( $settings, Chatbot_Admin_Settings::default_settings() );
		return self::apply_constant_overrides( $settings );
	}

	/**
	 * Permite sobrescribir opciones sensibles desde wp-config.php (equivalente a .env).
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function apply_constant_overrides( array $settings ): array {
		$string_map = array(
			'allowed_origins'             => 'CHATBOT_ALLOWED_ORIGINS',
			'cache_ttl_seconds'           => 'CHATBOT_CACHE_TTL_SECONDS',
			'telemetry_log_path'          => 'CHATBOT_TELEMETRY_LOG_PATH',
			'rate_limit_per_minute'       => 'CHATBOT_RATE_LIMIT_PER_MINUTE',
			'rate_limit_per_day'          => 'CHATBOT_RATE_LIMIT_PER_DAY',
			'rate_limit_model_per_minute' => 'CHATBOT_RATE_LIMIT_MODEL_PER_MINUTE',
			'rate_limit_model_per_day'    => 'CHATBOT_RATE_LIMIT_MODEL_PER_DAY',
			'rate_limit_soft_threshold'   => 'CHATBOT_RATE_LIMIT_SOFT_THRESHOLD',
			'ip_suspend_after_violations' => 'CHATBOT_IP_SUSPEND_AFTER_VIOLATIONS',
			'ip_suspend_seconds'          => 'CHATBOT_IP_SUSPEND_SECONDS',
			'internal_chat_base_url'      => 'CHATBOT_INTERNAL_CHAT_BASE_URL',
			'provider'                    => 'CHATBOT_PROVIDER',
			'model'                       => 'CHATBOT_MODEL',
			'model_candidates'            => 'CHATBOT_MODEL_CANDIDATES',
			'widget_title'                => 'CHATBOT_WIDGET_TITLE',
			'widget_subtitle'             => 'CHATBOT_WIDGET_SUBTITLE',
			'welcome_message'             => 'CHATBOT_WELCOME_MESSAGE',
			'system_prompt'               => 'CHATBOT_SYSTEM_PROMPT',
		);

		foreach ( $string_map as $key => $constant ) {
			if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
				$settings[ $key ] = constant( $constant );
			}
		}

		if ( defined( 'CHATBOT_GEMINI_MODEL' ) && '' !== (string) CHATBOT_GEMINI_MODEL && ! defined( 'CHATBOT_MODEL' ) ) {
			if ( ( $settings['provider'] ?? '' ) === 'gemini' ) {
				$settings['model'] = (string) CHATBOT_GEMINI_MODEL;
			}
		}

		if ( defined( 'CHATBOT_GEMINI_MODEL_CANDIDATES' ) && '' !== (string) CHATBOT_GEMINI_MODEL_CANDIDATES && ! defined( 'CHATBOT_MODEL_CANDIDATES' ) ) {
			if ( ( $settings['provider'] ?? '' ) === 'gemini' ) {
				$settings['model_candidates'] = (string) CHATBOT_GEMINI_MODEL_CANDIDATES;
			}
		}

		if ( defined( 'CHATBOT_STREAMING_ENABLED' ) ) {
			$settings['streaming_enabled'] = (bool) CHATBOT_STREAMING_ENABLED;
		}

		return $settings;
	}
}
