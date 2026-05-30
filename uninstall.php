<?php
/**
 * Uninstall Chatbot Plugin WP.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/chat-history.php';

global $wpdb;

$table = $wpdb->prefix . 'chatbot_events';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

Chatbot_Chat_History::drop_tables();

delete_option( 'chatbot_plugin_settings' );
delete_option( 'chatbot_plugin_db_version' );
