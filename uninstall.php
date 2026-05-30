<?php
/**
 * Uninstall Chatbot Plugin WP.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/chat-history.php';

global $wpdb;

wp_clear_scheduled_hook( 'chatbot_purge_history' );
wp_clear_scheduled_hook( 'chatbot_purge_telemetry' );

$table = $wpdb->prefix . 'chatbot_events';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

Chatbot_Chat_History::drop_tables();

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '_transient_chatbot_%'
	OR option_name LIKE '_transient_timeout_chatbot_%'"
);

delete_option( 'chatbot_plugin_settings' );
delete_option( 'chatbot_plugin_db_version' );
delete_option( 'chatbot_plugin_telemetry_db_version' );
delete_option( 'chatbot_plugin_history_db_version' );

flush_rewrite_rules( false );
