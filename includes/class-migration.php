<?php
/**
 * Migrate legacy chatbot_* identifiers to multch_*.
 *
 * @package Multch_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Multch_Migration {

	const LEGACY_OPTION_SETTINGS  = 'chatbot_plugin_settings';
	const LEGACY_OPTION_HISTORY   = 'chatbot_plugin_history_db_version';
	const LEGACY_OPTION_TELEMETRY = 'chatbot_plugin_telemetry_db_version';
	const LEGACY_OPTION_DB        = 'chatbot_plugin_db_version';

	const LEGACY_CRON_HISTORY   = 'chatbot_purge_history';
	const LEGACY_CRON_TELEMETRY = 'chatbot_purge_telemetry';

	const CANONICAL_CRON_HISTORY   = 'multch_purge_history';
	const CANONICAL_CRON_TELEMETRY = 'multch_purge_telemetry';

	/**
	 * Run idempotent steps until no chatbot_* artifacts remain.
	 */
	public static function maybe_migrate(): void {
		self::migrate_options();
		self::migrate_tables();
		self::migrate_cron();
		self::migrate_ai_providers();
		self::migrate_telemetry_file_log();
		self::purge_legacy_chatbot_transients();

		if ( ! self::has_legacy_chatbot_artifacts() ) {
			update_option( 'multch_legacy_migration_done', '1', false );
		}
	}

	/**
	 * Whether pre-1.0.2 chatbot_* options, tables, or cron hooks still exist.
	 */
	private static function has_legacy_chatbot_artifacts(): bool {
		global $wpdb;

		$legacy_options = array(
			self::LEGACY_OPTION_SETTINGS,
			self::LEGACY_OPTION_HISTORY,
			self::LEGACY_OPTION_TELEMETRY,
			self::LEGACY_OPTION_DB,
		);

		foreach ( $legacy_options as $legacy_option ) {
			if ( false !== get_option( $legacy_option, false ) ) {
				return true;
			}
		}

		$legacy_tables = array(
			$wpdb->prefix . 'chatbot_events',
			$wpdb->prefix . 'chatbot_conversations',
			$wpdb->prefix . 'chatbot_messages',
		);

		foreach ( $legacy_tables as $legacy_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $legacy_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) ) ) {
				return true;
			}
		}

		if ( wp_next_scheduled( self::LEGACY_CRON_HISTORY ) || wp_next_scheduled( self::LEGACY_CRON_TELEMETRY ) ) {
			return true;
		}

		return false;
	}

	private static function migrate_options(): void {
		$map = array(
			self::LEGACY_OPTION_SETTINGS  => Multch_Admin_Settings::OPTION_KEY,
			self::LEGACY_OPTION_HISTORY   => 'multch_plugin_history_db_version',
			self::LEGACY_OPTION_TELEMETRY => 'multch_plugin_telemetry_db_version',
			self::LEGACY_OPTION_DB        => 'multch_plugin_db_version',
		);

		foreach ( $map as $legacy => $new ) {
			if ( $legacy === $new ) {
				continue;
			}

			$value = get_option( $legacy, null );
			if ( null === $value ) {
				continue;
			}

			if ( false === get_option( $new, false ) ) {
				add_option( $new, $value, '', false );
			}

			delete_option( $legacy );
		}
	}

	private static function migrate_tables(): void {
		self::rename_table_if_needed( 'events' );
		self::rename_table_if_needed( 'conversations' );
		self::rename_table_if_needed( 'messages' );
	}

	/**
	 * Rename a legacy table when the canonical name does not exist yet.
	 *
	 * @param 'events'|'conversations'|'messages' $which Whitelisted legacy table key.
	 */
	private static function rename_table_if_needed( string $which ): void {
		global $wpdb;

		switch ( $which ) {
			case 'events':
				$legacy_table    = $wpdb->prefix . 'chatbot_events';
				$canonical_table = Multch_Telemetry::table_name();
				break;
			case 'conversations':
				$legacy_table    = $wpdb->prefix . 'chatbot_conversations';
				$canonical_table = Multch_Chat_History::conversations_table();
				break;
			case 'messages':
				$legacy_table    = $wpdb->prefix . 'chatbot_messages';
				$canonical_table = Multch_Chat_History::messages_table();
				break;
			default:
				return;
		}

		if ( $legacy_table === $canonical_table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time rename; fixed plugin suffixes.
		$exists_legacy = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy_table ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$exists_canonical = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $canonical_table ) );
		if ( $exists_legacy !== $legacy_table || $exists_canonical === $canonical_table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time rename; names from fixed suffixes via %i.
		$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $legacy_table, $canonical_table ) );
	}

	/**
	 * Map legacy direct cloud providers to the WordPress AI Client.
	 */
	private static function migrate_ai_providers(): void {
		$done = get_option( 'multch_ai_client_migration_done', '' );
		if ( '1' === $done ) {
			return;
		}

		$stored = get_option( Multch_Admin_Settings::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( 'multch_ai_client_migration_done', '1', false );
			return;
		}

		$provider = (string) ( $stored['provider'] ?? '' );
		if ( in_array( $provider, multch_legacy_cloud_provider_ids(), true ) ) {
			$stored['provider'] = 'wordpress_ai';
		}

		unset( $stored['api_key'], $stored['openai_base_url'], $stored['deepseek_base_url'] );

		update_option( Multch_Admin_Settings::OPTION_KEY, wp_parse_args( $stored, Multch_Admin_Settings::default_settings() ), false );
		Multch_Plugin::clear_settings_cache();
		update_option( 'multch_ai_client_migration_done', '1', false );
	}

	/**
	 * Replace legacy arbitrary telemetry_log_path with uploads-only file log flag.
	 */
	private static function migrate_telemetry_file_log(): void {
		if ( '1' === get_option( 'multch_telemetry_file_log_migration_done', '' ) ) {
			return;
		}

		$stored = get_option( Multch_Admin_Settings::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( 'multch_telemetry_file_log_migration_done', '1', false );
			return;
		}

		if ( array_key_exists( 'telemetry_log_path', $stored ) ) {
			if ( ! empty( $stored['telemetry_log_path'] ) ) {
				$stored['telemetry_file_log'] = true;
			}
			unset( $stored['telemetry_log_path'] );
			update_option(
				Multch_Admin_Settings::OPTION_KEY,
				wp_parse_args( $stored, Multch_Admin_Settings::default_settings() ),
				false
			);
		}

		update_option( 'multch_telemetry_file_log_migration_done', '1', false );
	}

	private static function migrate_cron(): void {
		$map = array(
			self::LEGACY_CRON_HISTORY   => self::CANONICAL_CRON_HISTORY,
			self::LEGACY_CRON_TELEMETRY => self::CANONICAL_CRON_TELEMETRY,
		);

		foreach ( $map as $legacy => $new ) {
			if ( $legacy === $new ) {
				continue;
			}

			$timestamp = wp_next_scheduled( $legacy );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $legacy );
				$timestamp = wp_next_scheduled( $legacy );
			}

			if ( ! wp_next_scheduled( $new ) ) {
				wp_schedule_event( time(), 'daily', $new );
			}
		}
	}

	/**
	 * Remove transients left by the pre-multch plugin (chatbot_* keys only).
	 */
	private static function purge_legacy_chatbot_transients(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy cleanup; no WP API for prefix delete.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_chatbot_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_chatbot_' ) . '%',
				$wpdb->esc_like( '_transient_chatbot-plugin' ) . '%',
				$wpdb->esc_like( '_transient_timeout_chatbot-plugin' ) . '%'
			)
		);
	}
}
