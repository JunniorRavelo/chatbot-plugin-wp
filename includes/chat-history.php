<?php
/**
 * Chat conversation history storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chatbot_Chat_History {

	const DB_VERSION = '1.0';

	const IDLE_MINUTES = 30;

	public static function conversations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chatbot_conversations';
	}

	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'chatbot_messages';
	}

	public static function create_tables(): void {
		global $wpdb;

		$conversations = self::conversations_table();
		$messages      = self::messages_table();
		$charset       = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(32) NOT NULL,
			session_hash varchar(64) NOT NULL DEFAULT '',
			title varchar(200) NOT NULL DEFAULT '',
			provider varchar(32) NOT NULL DEFAULT '',
			model varchar(64) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'active',
			message_count int(11) NOT NULL DEFAULT 0,
			page_url varchar(500) DEFAULT NULL,
			page_path varchar(255) DEFAULT NULL,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY session_hash (session_hash),
			KEY updated_at (updated_at),
			KEY status (status),
			KEY provider (provider)
		) {$charset};

		CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(16) NOT NULL DEFAULT 'user',
			content text NOT NULL,
			status varchar(32) NOT NULL DEFAULT '',
			latency_ms int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'chatbot_plugin_history_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( 'chatbot_plugin_history_db_version', '' ) ) {
			self::create_tables();
		}
	}

	public static function generate_public_id(): string {
		$base = 'CB-' . wp_date( 'Y-m-d-H-i-s' );
		if ( ! self::public_id_exists( $base ) ) {
			return $base;
		}

		for ( $i = 2; $i <= 99; $i++ ) {
			$candidate = $base . '-' . $i;
			if ( ! self::public_id_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return $base . '-' . wp_generate_password( 4, false, false );
	}

	private static function public_id_exists( string $public_id ): bool {
		global $wpdb;
		$table = self::conversations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE public_id = %s LIMIT 1",
				$public_id
			)
		);
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array{id: int, public_id: string}
	 */
	public static function resolve_conversation( string $session_hash, string $client_ref, array $meta = array() ): array {
		global $wpdb;

		$table = self::conversations_table();
		$now   = current_time( 'mysql', true );

		if ( '' !== $client_ref ) {
			$row = self::find_by_client_ref( $client_ref, $session_hash );
			if ( $row ) {
				return array(
					'id'        => (int) $row['id'],
					'public_id' => (string) $row['public_id'],
				);
			}
		}

		if ( '' !== $session_hash ) {
			$idle_since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::IDLE_MINUTES . ' minutes' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$active = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, public_id FROM {$table}
					WHERE session_hash = %s AND status = 'active' AND updated_at >= %s
					ORDER BY updated_at DESC LIMIT 1",
					$session_hash,
					$idle_since
				),
				ARRAY_A
			);
			if ( $active ) {
				return array(
					'id'        => (int) $active['id'],
					'public_id' => (string) $active['public_id'],
				);
			}
		}

		$public_id = self::generate_public_id();
		$title     = isset( $meta['title'] ) ? self::truncate_title( (string) $meta['title'] ) : '';

		$wpdb->insert(
			$table,
			array(
				'public_id'     => $public_id,
				'session_hash'  => $session_hash,
				'title'         => $title,
				'provider'      => isset( $meta['provider'] ) ? sanitize_text_field( (string) $meta['provider'] ) : '',
				'model'         => '',
				'status'        => 'active',
				'message_count' => 0,
				'page_url'      => isset( $meta['page_url'] ) ? self::sanitize_url_field( (string) $meta['page_url'] ) : null,
				'page_path'     => isset( $meta['page_path'] ) ? self::sanitize_path_field( (string) $meta['page_path'] ) : null,
				'started_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return array(
			'id'        => (int) $wpdb->insert_id,
			'public_id' => $public_id,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function find_by_client_ref( string $client_ref, string $session_hash ) {
		global $wpdb;

		$table = self::conversations_table();
		$ref   = sanitize_text_field( $client_ref );

		if ( preg_match( '/^\d+$/', $ref ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
					(int) $ref
				),
				ARRAY_A
			) ?: null;
		}

		if ( preg_match( '/^CB-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}/', $ref ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE public_id = %s LIMIT 1",
					$ref
				),
				ARRAY_A
			) ?: null;
		}

		return null;
	}

	public static function add_message(
		int $conversation_id,
		string $role,
		string $content,
		array $extra = array()
	): void {
		global $wpdb;

		if ( $conversation_id <= 0 ) {
			return;
		}

		$content = wp_strip_all_tags( $content );
		if ( '' === trim( $content ) ) {
			return;
		}

		$role = 'assistant' === $role ? 'assistant' : 'user';
		$now  = current_time( 'mysql', true );

		$wpdb->insert(
			self::messages_table(),
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'status'          => isset( $extra['status'] ) ? sanitize_text_field( (string) $extra['status'] ) : '',
				'latency_ms'      => isset( $extra['latency_ms'] ) ? (int) $extra['latency_ms'] : 0,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		$conv_table = self::conversations_table();
		$updates    = array(
			'updated_at' => $now,
		);
		$formats    = array( '%s' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updates['message_count'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::messages_table() . ' WHERE conversation_id = %d',
				$conversation_id
			)
		);
		$formats[] = '%d';

		if ( 'user' === $role ) {
			$title = self::truncate_title( $content );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$current_title = (string) $wpdb->get_var(
				$wpdb->prepare( "SELECT title FROM {$conv_table} WHERE id = %d", $conversation_id )
			);
			if ( '' === trim( $current_title ) && '' !== $title ) {
				$updates['title'] = $title;
				$formats[]        = '%s';
			}
		}

		if ( ! empty( $extra['model'] ) ) {
			$updates['model'] = sanitize_text_field( (string) $extra['model'] );
			$formats[]        = '%s';
		}
		if ( ! empty( $extra['provider'] ) ) {
			$updates['provider'] = sanitize_text_field( (string) $extra['provider'] );
			$formats[]           = '%s';
		}
		if ( ! empty( $extra['status'] ) && 'assistant' === $role ) {
			$updates['status'] = sanitize_text_field( (string) $extra['status'] );
			$formats[]         = '%s';
		}
		if ( ! empty( $extra['page_url'] ) ) {
			$updates['page_url'] = self::sanitize_url_field( (string) $extra['page_url'] );
			$formats[]           = '%s';
		}
		if ( ! empty( $extra['page_path'] ) ) {
			$updates['page_path'] = self::sanitize_path_field( (string) $extra['page_path'] );
			$formats[]            = '%s';
		}

		$wpdb->update(
			$conv_table,
			$updates,
			array( 'id' => $conversation_id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_conversations( array $args = array() ): array {
		global $wpdb;

		$table  = self::conversations_table();
		$where  = array( '1=1' );
		$params = array();

		$days = isset( $args['days'] ) ? (int) $args['days'] : 0;
		if ( $days > 0 ) {
			$where[]  = 'updated_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '';
		if ( '' !== $provider && 'all' !== $provider ) {
			$where[]  = 'provider = %s';
			$params[] = $provider;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(public_id LIKE %s OR title LIKE %s OR page_path LIKE %s OR session_hash LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$per    = max( 1, min( 50, (int) ( $args['per_page'] ?? 12 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$order = isset( $args['orderby'] ) && 'started_at' === $args['orderby'] ? 'started_at' : 'updated_at';
		$dir   = isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$order} {$dir} LIMIT %d OFFSET %d";
		$params[] = $per;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public static function count_conversations( array $args = array() ): int {
		global $wpdb;

		$table  = self::conversations_table();
		$where  = array( '1=1' );
		$params = array();

		$days = isset( $args['days'] ) ? (int) $args['days'] : 0;
		if ( $days > 0 ) {
			$where[]  = 'updated_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		}

		$provider = isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '';
		if ( '' !== $provider && 'all' !== $provider ) {
			$where[]  = 'provider = %s';
			$params[] = $provider;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status && 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(public_id LIKE %s OR title LIKE %s OR page_path LIKE %s OR session_hash LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		if ( empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_conversation( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 ) {
			return null;
		}

		$table = self::conversations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_messages( int $conversation_id ): array {
		global $wpdb;

		if ( $conversation_id <= 0 ) {
			return array();
		}

		$table = self::messages_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC, id ASC",
				$conversation_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public static function format_datetime_local( string $mysql_utc ): string {
		if ( '' === $mysql_utc ) {
			return '—';
		}
		$ts = strtotime( $mysql_utc . ' UTC' );
		if ( false === $ts ) {
			return $mysql_utc;
		}
		return wp_date( 'd/m/Y H:i', $ts );
	}

	private static function truncate_title( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text ) ?? $text;
		$text = trim( $text );
		if ( strlen( $text ) > 120 ) {
			$text = substr( $text, 0, 117 ) . '…';
		}
		return $text;
	}

	private static function sanitize_url_field( string $url ): ?string {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return null;
		}
		return substr( $url, 0, 500 );
	}

	private static function sanitize_path_field( string $path ): ?string {
		$path = sanitize_text_field( $path );
		if ( '' === $path ) {
			return null;
		}
		return substr( $path, 0, 255 );
	}

	public static function drop_tables(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::messages_table() );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::conversations_table() );
		delete_option( 'chatbot_plugin_history_db_version' );
	}
}
