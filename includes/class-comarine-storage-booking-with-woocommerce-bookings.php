<?php

/**
 * Bookings data helpers.
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * Bookings table helpers and lightweight admin queries.
 */
class Comarine_Storage_Booking_With_Woocommerce_Bookings {

	/**
	 * Supported booking duration keys mapped to months.
	 *
	 * @since 1.0.3
	 *
	 * @var array<string, int>
	 */
	const DURATION_MONTHS = array(
		'monthly' => 1,
		'6m'      => 6,
		'12m'     => 12,
	);

	/**
	 * Supported booking statuses.
	 *
	 * @since 1.0.5
	 *
	 * @var array<string, string>
	 */
	const STATUSES = array(
		'pending'   => 'Pending',
		'locked'    => 'Locked',
		'paid'      => 'Paid',
		'cancelled' => 'Cancelled',
		'expired'   => 'Expired',
		'refunded'  => 'Refunded',
		'reserved'  => 'Reserved',
		'occupied'  => 'Occupied',
	);

	/**
	 * Get the bookings table name.
	 *
	 * @since 1.0.2
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		$suffix = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_BOOKINGS_TABLE_SUFFIX' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_BOOKINGS_TABLE_SUFFIX
			: 'comarine_bookings';

		return $wpdb->prefix . $suffix;
	}

	/**
	 * Get the audit log table name.
	 *
	 * @since 1.0.6
	 *
	 * @return string
	 */
	public static function get_audit_table_name() {
		global $wpdb;

		$suffix = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_AUDIT_TABLE_SUFFIX' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_AUDIT_TABLE_SUFFIX
			: 'comarine_booking_audit_log';

		return $wpdb->prefix . $suffix;
	}

	/**
	 * Create or update the bookings table.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function create_table() {
		self::create_bookings_table();
		self::create_audit_log_table();

		if ( defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION' ) ) {
			update_option(
				'comarine_storage_booking_with_woocommerce_db_version',
				COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION
			);
		}
	}

	/**
	 * Maybe upgrade plugin DB schema after updates.
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema() {
		$current_db_version = (string) get_option( 'comarine_storage_booking_with_woocommerce_db_version', '' );
		$target_db_version  = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION' ) ? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION : '';

		if ( ! self::table_exists() || ! self::audit_table_exists() ) {
			self::create_table();
			return;
		}

		if ( '' !== $target_db_version && version_compare( $current_db_version, (string) $target_db_version, '<' ) ) {
			self::create_table();
		}
	}

	/**
	 * Create or update the main bookings table.
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	private static function create_bookings_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			unit_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			unit_code varchar(64) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned DEFAULT NULL,
			duration_key varchar(16) NOT NULL DEFAULT '',
			start_ts datetime DEFAULT NULL,
			end_ts datetime DEFAULT NULL,
			requested_area_m2 decimal(12,2) NOT NULL DEFAULT 0.00,
			unit_capacity_m2 decimal(12,2) NOT NULL DEFAULT 0.00,
			price_total decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(8) NOT NULL DEFAULT 'EUR',
			status varchar(20) NOT NULL DEFAULT 'pending',
			lock_token varchar(64) DEFAULT NULL,
			lock_expires_ts datetime DEFAULT NULL,
			created_ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY unit_post_id (unit_post_id),
			KEY order_id (order_id),
			KEY booking_status (status),
			KEY lock_expires_ts (lock_expires_ts),
			KEY unit_status (unit_post_id, status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or update the audit log table.
	 *
	 * @since 1.0.6
	 *
	 * @return void
	 */
	private static function create_audit_log_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_audit_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			unit_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(64) NOT NULL DEFAULT '',
			message text NULL,
			context_json longtext NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_label varchar(191) NOT NULL DEFAULT '',
			created_ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY unit_post_id (unit_post_id),
			KEY order_id (order_id),
			KEY event_type (event_type),
			KEY created_ts (created_ts)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Check if the bookings table exists.
	 *
	 * @since 1.0.2
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $table_name === $result;
	}

	/**
	 * Check if the audit log table exists.
	 *
	 * @since 1.0.6
	 *
	 * @return bool
	 */
	public static function audit_table_exists() {
		global $wpdb;

		$table_name = self::get_audit_table_name();
		$result     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $table_name === $result;
	}

	/**
	 * Count bookings rows.
	 *
	 * @since 1.0.2
	 *
	 * @return int
	 */
	public static function count_bookings() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();
		$count      = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (int) $count;
	}

	/**
	 * Insert an audit event row.
	 *
	 * @since 1.0.6
	 *
	 * @param array<string, mixed> $args Audit event data.
	 * @return bool
	 */
	public static function log_audit_event( $args ) {
		global $wpdb;

		if ( ! self::audit_table_exists() ) {
			return false;
		}

		$defaults = array(
			'booking_id'    => 0,
			'unit_post_id'  => 0,
			'order_id'      => 0,
			'event_type'    => '',
			'message'       => '',
			'context'       => array(),
			'actor_user_id' => 0,
			'actor_label'   => '',
			'created_ts'    => current_time( 'mysql' ),
		);
		$args     = wp_parse_args( $args, $defaults );

		$event_type = sanitize_key( (string) $args['event_type'] );
		if ( '' === $event_type ) {
			return false;
		}

		$context_json = null;
		if ( ! empty( $args['context'] ) ) {
			$encoded = wp_json_encode( $args['context'] );
			if ( false !== $encoded ) {
				$context_json = $encoded;
			}
		}

		$actor_label = sanitize_text_field( (string) $args['actor_label'] );
		if ( strlen( $actor_label ) > 191 ) {
			$actor_label = substr( $actor_label, 0, 191 );
		}

		$table_name = self::get_audit_table_name();
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'booking_id'    => absint( $args['booking_id'] ),
				'unit_post_id'  => absint( $args['unit_post_id'] ),
				'order_id'      => absint( $args['order_id'] ),
				'event_type'    => $event_type,
				'message'       => '' !== (string) $args['message'] ? sanitize_textarea_field( (string) $args['message'] ) : null,
				'context_json'  => $context_json,
				'actor_user_id' => absint( $args['actor_user_id'] ),
				'actor_label'   => $actor_label,
				'created_ts'    => sanitize_text_field( (string) $args['created_ts'] ),
			),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		return false !== $inserted;
	}

	/**
	 * Get audit events with optional filters.
	 *
	 * @since 1.0.6
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function get_audit_events( $args = array() ) {
		global $wpdb;

		if ( ! self::audit_table_exists() ) {
			return array();
		}

		$defaults = array(
			'limit'      => 25,
			'offset'     => 0,
			'booking_id' => 0,
			'order_id'   => 0,
			'event_type' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$table_name = self::get_audit_table_name();
		$limit      = max( 1, min( 200, (int) $args['limit'] ) );
		$offset     = max( 0, (int) $args['offset'] );
		$booking_id = absint( $args['booking_id'] );
		$order_id   = absint( $args['order_id'] );
		$event_type = sanitize_key( (string) $args['event_type'] );

		$where_parts = array( '1=1' );
		$params      = array();

		if ( $booking_id > 0 ) {
			$where_parts[] = 'booking_id = %d';
			$params[]      = $booking_id;
		}

		if ( $order_id > 0 ) {
			$where_parts[] = 'order_id = %d';
			$params[]      = $order_id;
		}

		if ( '' !== $event_type ) {
			$where_parts[] = 'event_type = %s';
			$params[]      = $event_type;
		}

		$where_sql = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_template = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_ts DESC, id DESC LIMIT %d OFFSET %d";
		$params[]       = $limit;
		$params[]       = $offset;
		$query          = $wpdb->prepare( $query_template, $params );
		$rows           = $wpdb->get_results( $query );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count audit events with optional filters.
	 *
	 * @since 1.0.6
	 *
	 * @param array<string, mixed> $args Count filters.
	 * @return int
	 */
	public static function count_audit_events( $args = array() ) {
		global $wpdb;

		if ( ! self::audit_table_exists() ) {
			return 0;
		}

		$defaults = array(
			'booking_id' => 0,
			'order_id'   => 0,
			'event_type' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$table_name = self::get_audit_table_name();
		$booking_id = absint( $args['booking_id'] );
		$order_id   = absint( $args['order_id'] );
		$event_type = sanitize_key( (string) $args['event_type'] );

		$where_parts = array( '1=1' );
		$params      = array();

		if ( $booking_id > 0 ) {
			$where_parts[] = 'booking_id = %d';
			$params[]      = $booking_id;
		}

		if ( $order_id > 0 ) {
			$where_parts[] = 'order_id = %d';
			$params[]      = $order_id;
		}

		if ( '' !== $event_type ) {
			$where_parts[] = 'event_type = %s';
			$params[]      = $event_type;
		}

		$where_sql = implode( ' AND ', $where_parts );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_template = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		$query          = empty( $params ) ? $query_template : $wpdb->prepare( $query_template, $params );
		$count          = $wpdb->get_var( $query );

		return (int) $count;
	}

	/**
	 * Get recent bookings for the admin overview.
	 *
	 * @since 1.0.2
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, object>
	 */
	public static function get_recent_bookings( $limit = 20 ) {
		return self::get_bookings(
			array(
				'limit' => $limit,
			)
		);
	}

	/**
	 * Get bookings with optional filters.
	 *
	 * @since 1.0.5
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function get_bookings( $args = array() ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		$defaults = array(
			'limit'       => 20,
			'offset'      => 0,
			'status'      => '',
			'order_id'    => 0,
			'booking_id'  => 0,
			'unit_post_id'=> 0,
			'created_from'=> '',
			'created_to'  => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$table_name   = self::get_table_name();
		$limit        = max( 1, min( 200, (int) $args['limit'] ) );
		$offset       = max( 0, (int) $args['offset'] );
		$status       = sanitize_key( (string) $args['status'] );
		$order_id     = absint( $args['order_id'] );
		$booking_id   = absint( $args['booking_id'] );
		$unit_post_id = absint( $args['unit_post_id'] );
		$created_from = self::normalize_filter_date( $args['created_from'], false );
		$created_to   = self::normalize_filter_date( $args['created_to'], true );

		$where_parts = array( '1=1' );
		$params      = array();

		if ( '' !== $status ) {
			$where_parts[] = 'status = %s';
			$params[]      = $status;
		}

		if ( $order_id > 0 ) {
			$where_parts[] = 'order_id = %d';
			$params[]      = $order_id;
		}

		if ( $booking_id > 0 ) {
			$where_parts[] = 'id = %d';
			$params[]      = $booking_id;
		}

		if ( $unit_post_id > 0 ) {
			$where_parts[] = 'unit_post_id = %d';
			$params[]      = $unit_post_id;
		}

		if ( '' !== $created_from ) {
			$where_parts[] = 'created_ts >= %s';
			$params[]      = $created_from;
		}

		if ( '' !== $created_to ) {
			$where_parts[] = 'created_ts <= %s';
			$params[]      = $created_to;
		}

		$where_sql = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_template = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_ts DESC, id DESC LIMIT %d OFFSET %d";
		$params[]       = $limit;
		$params[]       = $offset;
		$query          = $wpdb->prepare( $query_template, $params );

		$rows = $wpdb->get_results( $query );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count bookings with optional filters.
	 *
	 * @since 1.0.5
	 *
	 * @param array<string, mixed> $args Count filters.
	 * @return int
	 */
	public static function count_bookings_filtered( $args = array() ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$defaults = array(
			'status'      => '',
			'order_id'    => 0,
			'booking_id'  => 0,
			'unit_post_id'=> 0,
			'created_from'=> '',
			'created_to'  => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$table_name   = self::get_table_name();
		$status       = sanitize_key( (string) $args['status'] );
		$order_id     = absint( $args['order_id'] );
		$booking_id   = absint( $args['booking_id'] );
		$unit_post_id = absint( $args['unit_post_id'] );
		$created_from = self::normalize_filter_date( $args['created_from'], false );
		$created_to   = self::normalize_filter_date( $args['created_to'], true );

		$where_parts = array( '1=1' );
		$params      = array();

		if ( '' !== $status ) {
			$where_parts[] = 'status = %s';
			$params[]      = $status;
		}

		if ( $order_id > 0 ) {
			$where_parts[] = 'order_id = %d';
			$params[]      = $order_id;
		}

		if ( $booking_id > 0 ) {
			$where_parts[] = 'id = %d';
			$params[]      = $booking_id;
		}

		if ( $unit_post_id > 0 ) {
			$where_parts[] = 'unit_post_id = %d';
			$params[]      = $unit_post_id;
		}

		if ( '' !== $created_from ) {
			$where_parts[] = 'created_ts >= %s';
			$params[]      = $created_from;
		}

		if ( '' !== $created_to ) {
			$where_parts[] = 'created_ts <= %s';
			$params[]      = $created_to;
		}

		$where_sql = implode( ' AND ', $where_parts );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_template = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		$query          = empty( $params ) ? $query_template : $wpdb->prepare( $query_template, $params );
		$count          = $wpdb->get_var( $query );

		return (int) $count;
	}

	/**
	 * Normalize a YYYY-MM-DD admin filter into a DATETIME string.
	 *
	 * @since 1.0.7
	 *
	 * @param mixed $value      Raw date value.
	 * @param bool  $end_of_day Whether to use 23:59:59 instead of 00:00:00.
	 * @return string Empty string when invalid.
	 */
	private static function normalize_filter_date( $value, $end_of_day = false ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$parts = array_map( 'intval', explode( '-', $value ) );
		if ( 3 !== count( $parts ) ) {
			return '';
		}

		list( $year, $month, $day ) = $parts;
		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf(
			'%04d-%02d-%02d %s',
			$year,
			$month,
			$day,
			$end_of_day ? '23:59:59' : '00:00:00'
		);
	}

	/**
	 * Get bookings linked to a WooCommerce order.
	 *
	 * @since 1.0.5
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, object>
	 */
	public static function get_bookings_for_order( $order_id ) {
		return self::get_bookings(
			array(
				'order_id' => absint( $order_id ),
				'limit'    => 200,
			)
		);
	}

	/**
	 * Get supported booking statuses.
	 *
	 * @since 1.0.5
	 *
	 * @return array<string, string>
	 */
	public static function get_status_options() {
		return self::STATUSES;
	}

	/**
	 * Get a human-readable booking status label.
	 *
	 * @since 1.0.5
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$status  = sanitize_key( (string) $status );
		$options = self::get_status_options();

		return isset( $options[ $status ] ) ? $options[ $status ] : $status;
	}

	/**
	 * Expire stale locks.
	 *
	 * @since 1.0.3
	 *
	 * @return int Affected rows.
	 */
	public static function expire_stale_locks() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"UPDATE {$table_name}
			SET status = %s, updated_ts = %s
			WHERE status IN ('locked','pending')
				AND order_id = 0
				AND lock_expires_ts IS NOT NULL
				AND lock_expires_ts < %s",
			'expired',
			$now,
			$now
		);

		$result = $wpdb->query( $sql );

		return is_numeric( $result ) ? (int) $result : 0;
	}

	/**
	 * Get the configured capacity (size) for a storage unit in m2.
	 *
	 * @since 1.0.26
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return float
	 */
	public static function get_unit_capacity_m2( $unit_post_id ) {
		$unit_post_id = absint( $unit_post_id );
		if ( $unit_post_id <= 0 ) {
			return 0.0;
		}

		$raw_capacity = get_post_meta( $unit_post_id, '_csu_size_m2', true );
		if ( '' === $raw_capacity || ! is_numeric( $raw_capacity ) ) {
			return 0.0;
		}

		$capacity = round( (float) $raw_capacity, 2 );

		return $capacity > 0 ? $capacity : 0.0;
	}

	/**
	 * Get reserved vs remaining capacity for a unit based on active bookings/locks.
	 *
	 * For legacy bookings created before capacity support, rows with no stored
	 * `requested_area_m2` are treated as consuming the full current unit capacity.
	 *
	 * @since 1.0.26
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return array<string, float|bool>
	 */
	public static function get_unit_capacity_availability( $unit_post_id ) {
		global $wpdb;

		$unit_post_id = absint( $unit_post_id );
		$capacity_m2  = self::get_unit_capacity_m2( $unit_post_id );
		$result       = array(
			'is_capacity_managed' => $capacity_m2 > 0,
			'capacity_m2'         => $capacity_m2,
			'reserved_m2'         => 0.0,
			'remaining_m2'        => $capacity_m2 > 0 ? $capacity_m2 : 0.0,
			'is_full'             => false,
		);

		if ( $unit_post_id <= 0 || $capacity_m2 <= 0 || ! self::table_exists() ) {
			return $result;
		}

		self::expire_stale_locks();

		$table_name = self::get_table_name();
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT requested_area_m2, unit_capacity_m2
			FROM {$table_name}
			WHERE unit_post_id = %d
			  AND (
					status = 'paid'
					OR status = 'reserved'
					OR status = 'occupied'
					OR (status IN ('locked','pending') AND (lock_expires_ts IS NULL OR lock_expires_ts >= %s))
			  )",
			$unit_post_id,
			$now
		);

		$rows = $wpdb->get_results( $sql );
		if ( empty( $rows ) ) {
			return $result;
		}

		$reserved_m2 = 0.0;
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}

			$row_requested = isset( $row->requested_area_m2 ) && is_numeric( $row->requested_area_m2 ) ? (float) $row->requested_area_m2 : 0.0;
			if ( $row_requested > 0 ) {
				$reserved_m2 += $row_requested;
				continue;
			}

			// Legacy rows had no requested-area field: treat them as full-unit bookings.
			$row_capacity = isset( $row->unit_capacity_m2 ) && is_numeric( $row->unit_capacity_m2 ) ? (float) $row->unit_capacity_m2 : 0.0;
			if ( $row_capacity <= 0 ) {
				$row_capacity = $capacity_m2;
			}

			$reserved_m2 += max( 0.0, $row_capacity );
		}

		$reserved_m2            = round( $reserved_m2, 2 );
		$remaining_m2           = max( 0.0, round( $capacity_m2 - $reserved_m2, 2 ) );
		$result['reserved_m2']  = $reserved_m2;
		$result['remaining_m2'] = $remaining_m2;
		$result['is_full']      = $remaining_m2 <= 0;

		return $result;
	}

	/**
	 * Determine if a unit has any active booking/lock in non-capacity mode.
	 *
	 * @since 1.0.26
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return bool
	 */
	private static function has_any_active_booking_conflict( $unit_post_id ) {
		global $wpdb;

		$unit_post_id = absint( $unit_post_id );
		if ( $unit_post_id <= 0 || ! self::table_exists() ) {
			return false;
		}

		self::expire_stale_locks();

		$table_name = self::get_table_name();
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$table_name}
			WHERE unit_post_id = %d
			  AND (
					status = 'paid'
					OR status = 'reserved'
					OR status = 'occupied'
					OR (status IN ('locked','pending') AND (lock_expires_ts IS NULL OR lock_expires_ts >= %s))
			  )",
			$unit_post_id,
			$now
		);

		$count = $wpdb->get_var( $sql );

		return ( (int) $count ) > 0;
	}

	/**
	 * Determine if a unit has an active lock or paid booking.
	 *
	 * @since 1.0.3
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return bool
	 */
	public static function has_conflicting_booking( $unit_post_id ) {
		$unit_post_id = absint( $unit_post_id );
		if ( $unit_post_id <= 0 ) {
			return false;
		}

		$capacity_snapshot = self::get_unit_capacity_availability( $unit_post_id );
		if ( ! empty( $capacity_snapshot['is_capacity_managed'] ) ) {
			return ! empty( $capacity_snapshot['is_full'] );
		}

		return self::has_any_active_booking_conflict( $unit_post_id );
	}

	/**
	 * Create a locked booking row before checkout.
	 *
	 * @since 1.0.3
	 *
	 * @param array<string, mixed> $args Booking args.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_locked_booking( $args ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			self::create_table();
		}

		$unit_post_id  = isset( $args['unit_post_id'] ) ? absint( $args['unit_post_id'] ) : 0;
		$unit_code     = isset( $args['unit_code'] ) ? sanitize_text_field( (string) $args['unit_code'] ) : '';
		$duration_key  = isset( $args['duration_key'] ) ? sanitize_key( (string) $args['duration_key'] ) : '';
		$requested_area_m2 = isset( $args['requested_area_m2'] ) && is_numeric( $args['requested_area_m2'] ) ? round( (float) $args['requested_area_m2'], 2 ) : 0.0;
		$unit_capacity_m2  = isset( $args['unit_capacity_m2'] ) && is_numeric( $args['unit_capacity_m2'] ) ? round( (float) $args['unit_capacity_m2'], 2 ) : 0.0;
		$price_total   = isset( $args['price_total'] ) ? (float) $args['price_total'] : 0.0;
		$currency      = isset( $args['currency'] ) ? strtoupper( sanitize_text_field( (string) $args['currency'] ) ) : 'EUR';
		$user_id       = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
		$lock_ttl      = isset( $args['lock_ttl_minutes'] ) ? max( 1, min( 120, (int) $args['lock_ttl_minutes'] ) ) : 15;

		if ( $unit_post_id <= 0 ) {
			return new WP_Error( 'comarine_invalid_unit', __( 'Invalid storage unit.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( ! isset( self::DURATION_MONTHS[ $duration_key ] ) ) {
			return new WP_Error( 'comarine_invalid_duration', __( 'Invalid booking duration selected.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( $price_total <= 0 ) {
			return new WP_Error( 'comarine_invalid_price', __( 'Invalid booking price.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( $unit_capacity_m2 <= 0 ) {
			$unit_capacity_m2 = self::get_unit_capacity_m2( $unit_post_id );
		}

		if ( $unit_capacity_m2 > 0 ) {
			if ( $requested_area_m2 <= 0 ) {
				return new WP_Error( 'comarine_invalid_area', __( 'Please enter the area (m2) you want to book.', 'comarine-storage-booking-with-woocommerce' ) );
			}

			if ( $requested_area_m2 > $unit_capacity_m2 ) {
				return new WP_Error(
					'comarine_area_exceeds_unit_capacity',
					sprintf(
						/* translators: %s: unit capacity in m2 */
						__( 'Requested area exceeds the unit capacity (%s m2).', 'comarine-storage-booking-with-woocommerce' ),
						number_format_i18n( $unit_capacity_m2, 2 )
					)
				);
			}

			$capacity_snapshot = self::get_unit_capacity_availability( $unit_post_id );
			$remaining_m2      = isset( $capacity_snapshot['remaining_m2'] ) ? (float) $capacity_snapshot['remaining_m2'] : $unit_capacity_m2;
			$unit_capacity_m2  = isset( $capacity_snapshot['capacity_m2'] ) ? (float) $capacity_snapshot['capacity_m2'] : $unit_capacity_m2;

			if ( $remaining_m2 + 0.0001 < $requested_area_m2 ) {
				return new WP_Error(
					'comarine_unit_capacity_unavailable',
					sprintf(
						/* translators: %s: remaining area in m2 */
						__( 'Only %s m2 is currently available for this unit.', 'comarine-storage-booking-with-woocommerce' ),
						number_format_i18n( max( 0, $remaining_m2 ), 2 )
					)
				);
			}
		} elseif ( self::has_any_active_booking_conflict( $unit_post_id ) ) {
			return new WP_Error( 'comarine_unit_unavailable', __( 'This unit is no longer available for booking.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$now_dt          = new DateTimeImmutable( current_time( 'mysql' ) );
		$lock_expires_dt = $now_dt->modify( '+' . $lock_ttl . ' minutes' );
		$end_dt          = $now_dt->modify( '+' . self::DURATION_MONTHS[ $duration_key ] . ' months' );
		$lock_token      = wp_generate_password( 32, false, false );
		$table_name      = self::get_table_name();

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'unit_post_id'     => $unit_post_id,
				'unit_code'        => $unit_code,
				'order_id'         => 0,
				'user_id'          => $user_id > 0 ? $user_id : null,
				'duration_key'     => $duration_key,
				'start_ts'         => $now_dt->format( 'Y-m-d H:i:s' ),
				'end_ts'           => $end_dt->format( 'Y-m-d H:i:s' ),
				'requested_area_m2'=> $requested_area_m2 > 0 ? number_format( $requested_area_m2, 2, '.', '' ) : '0.00',
				'unit_capacity_m2' => $unit_capacity_m2 > 0 ? number_format( $unit_capacity_m2, 2, '.', '' ) : '0.00',
				'price_total'      => number_format( $price_total, 2, '.', '' ),
				'currency'         => $currency ?: 'EUR',
				'status'           => 'locked',
				'lock_token'       => $lock_token,
				'lock_expires_ts'  => $lock_expires_dt->format( 'Y-m-d H:i:s' ),
				'created_ts'       => $now_dt->format( 'Y-m-d H:i:s' ),
				'updated_ts'       => $now_dt->format( 'Y-m-d H:i:s' ),
			),
			array(
				'%d',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'comarine_booking_insert_failed', __( 'Could not create booking lock.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		return array(
			'booking_id'       => (int) $wpdb->insert_id,
			'lock_token'       => $lock_token,
			'lock_expires_ts'  => $lock_expires_dt->format( 'Y-m-d H:i:s' ),
			'duration_key'     => $duration_key,
			'requested_area_m2'=> $requested_area_m2 > 0 ? number_format( $requested_area_m2, 2, '.', '' ) : '0.00',
			'unit_capacity_m2' => $unit_capacity_m2 > 0 ? number_format( $unit_capacity_m2, 2, '.', '' ) : '0.00',
			'price_total'      => number_format( $price_total, 2, '.', '' ),
			'currency'         => $currency ?: 'EUR',
		);
	}

	/**
	 * Get a booking row by ID.
	 *
	 * @since 1.0.3
	 *
	 * @param int $booking_id Booking ID.
	 * @return object|null
	 */
	public static function get_booking( $booking_id ) {
		global $wpdb;

		$booking_id = absint( $booking_id );
		if ( $booking_id <= 0 || ! self::table_exists() ) {
			return null;
		}

		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $booking_id );

		return $wpdb->get_row( $sql );
	}

	/**
	 * Validate a booking lock row and token.
	 *
	 * @since 1.0.4
	 *
	 * @param int    $booking_id         Booking ID.
	 * @param string $lock_token         Lock token.
	 * @param bool   $allow_order_linked Whether rows already linked to an order are allowed.
	 * @return object|WP_Error
	 */
	public static function validate_booking_lock( $booking_id, $lock_token, $allow_order_linked = false ) {
		$booking_id = absint( $booking_id );
		$lock_token = sanitize_text_field( (string) $lock_token );

		if ( $booking_id <= 0 || '' === $lock_token ) {
			return new WP_Error( 'comarine_invalid_booking_lock', __( 'Invalid booking lock data.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$row = self::get_booking( $booking_id );
		if ( ! $row ) {
			return new WP_Error( 'comarine_booking_not_found', __( 'The booking lock could not be found.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( (string) $row->lock_token !== $lock_token ) {
			return new WP_Error( 'comarine_booking_lock_mismatch', __( 'The booking lock is no longer valid.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( ! $allow_order_linked && (int) $row->order_id > 0 ) {
			return new WP_Error( 'comarine_booking_already_linked', __( 'This booking lock is already linked to an order.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( in_array( (string) $row->status, array( 'cancelled', 'expired', 'refunded' ), true ) ) {
			return new WP_Error( 'comarine_booking_inactive', __( 'This booking lock is no longer active.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( (int) $row->order_id <= 0 && ! empty( $row->lock_expires_ts ) ) {
			$expires_dt = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $row->lock_expires_ts, wp_timezone() );
			if ( false !== $expires_dt ) {
				$errors = DateTimeImmutable::getLastErrors();
				if ( ! is_array( $errors ) || ( (int) $errors['warning_count'] <= 0 && (int) $errors['error_count'] <= 0 ) ) {
					$now_dt = new DateTimeImmutable( 'now', wp_timezone() );
					if ( $expires_dt < $now_dt ) {
						self::mark_booking_expired( $booking_id );
						return new WP_Error( 'comarine_booking_expired', __( 'This booking lock has expired.', 'comarine-storage-booking-with-woocommerce' ) );
					}
				}
			}
		}

		return $row;
	}

	/**
	 * Refresh a pre-order booking lock expiry.
	 *
	 * @since 1.0.4
	 *
	 * @param int    $booking_id        Booking ID.
	 * @param string $lock_token        Lock token.
	 * @param int    $lock_ttl_minutes  TTL in minutes.
	 * @return bool
	 */
	public static function refresh_booking_lock( $booking_id, $lock_token, $lock_ttl_minutes ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$validation = self::validate_booking_lock( $booking_id, $lock_token, true );
		if ( is_wp_error( $validation ) ) {
			return false;
		}

		$row = $validation;
		if ( (int) $row->order_id > 0 ) {
			return true;
		}

		$lock_ttl_minutes = max( 1, min( 120, (int) $lock_ttl_minutes ) );
		$now_dt           = new DateTimeImmutable( current_time( 'mysql' ) );
		$now              = $now_dt->format( 'Y-m-d H:i:s' );
		$expires          = $now_dt->modify( '+' . $lock_ttl_minutes . ' minutes' )->format( 'Y-m-d H:i:s' );

		$table_name = self::get_table_name();
		$result     = $wpdb->update(
			$table_name,
			array(
				'lock_expires_ts' => $expires,
				'updated_ts'      => $now,
			),
			array(
				'id'        => (int) $row->id,
				'lock_token' => (string) $row->lock_token,
				'order_id'   => 0,
			),
			array( '%s', '%s' ),
			array( '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Attach an order ID to a booking lock.
	 *
	 * @since 1.0.3
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $lock_token Lock token.
	 * @param int    $order_id   Order ID.
	 * @return bool
	 */
	public static function assign_order_to_booking( $booking_id, $lock_token, $order_id ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$booking_id = absint( $booking_id );
		$order_id   = absint( $order_id );
		$lock_token = sanitize_text_field( (string) $lock_token );
		$now        = current_time( 'mysql' );

		if ( $booking_id <= 0 || $order_id <= 0 || '' === $lock_token ) {
			return false;
		}

		$validation = self::validate_booking_lock( $booking_id, $lock_token, false );
		if ( is_wp_error( $validation ) ) {
			return false;
		}

		$row = $validation;
		if ( (int) $row->order_id > 0 && (int) $row->order_id === $order_id ) {
			return true;
		}

		$table_name = self::get_table_name();
		$result     = $wpdb->update(
			$table_name,
			array(
				'order_id'    => $order_id,
				'status'      => 'pending',
				'updated_ts'  => $now,
			),
			array(
				'id'         => $booking_id,
				'lock_token' => $lock_token,
				'order_id'   => 0,
			),
			array( '%d', '%s', '%s' ),
			array( '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark booking as paid and release lock.
	 *
	 * @since 1.0.3
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $order_id   Optional order ID.
	 * @return bool
	 */
	public static function mark_booking_paid( $booking_id, $order_id = 0 ) {
		return self::update_booking_status( $booking_id, 'paid', $order_id, true );
	}

	/**
	 * Mark booking as cancelled and release lock.
	 *
	 * @since 1.0.3
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $order_id   Optional order ID.
	 * @return bool
	 */
	public static function mark_booking_cancelled( $booking_id, $order_id = 0 ) {
		return self::update_booking_status( $booking_id, 'cancelled', $order_id, true );
	}

	/**
	 * Mark booking as refunded and release lock.
	 *
	 * @since 1.0.3
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $order_id   Optional order ID.
	 * @return bool
	 */
	public static function mark_booking_refunded( $booking_id, $order_id = 0 ) {
		return self::update_booking_status( $booking_id, 'refunded', $order_id, true );
	}

	/**
	 * Mark booking as expired and release lock.
	 *
	 * @since 1.0.3
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public static function mark_booking_expired( $booking_id ) {
		return self::update_booking_status( $booking_id, 'expired', 0, true );
	}

	/**
	 * Manually set a booking status from admin tooling.
	 *
	 * @since 1.0.5
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 * @return bool
	 */
	public static function set_booking_status( $booking_id, $status ) {
		$status = sanitize_key( (string) $status );

		if ( ! array_key_exists( $status, self::get_status_options() ) ) {
			return false;
		}

		$release_lock = in_array( $status, array( 'paid', 'cancelled', 'expired', 'refunded', 'reserved', 'occupied' ), true );

		return self::update_booking_status( $booking_id, $status, 0, $release_lock );
	}

	/**
	 * Release/cancel a booking lock by ID + token, used for cart removal.
	 *
	 * @since 1.0.3
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $lock_token Lock token.
	 * @return bool
	 */
	public static function cancel_booking_lock( $booking_id, $lock_token ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$booking_id = absint( $booking_id );
		$lock_token = sanitize_text_field( (string) $lock_token );
		$now        = current_time( 'mysql' );

		if ( $booking_id <= 0 || '' === $lock_token ) {
			return false;
		}

		$table_name = self::get_table_name();
		$result     = $wpdb->update(
			$table_name,
			array(
				'status'          => 'cancelled',
				'lock_token'      => null,
				'lock_expires_ts' => null,
				'updated_ts'      => $now,
			),
			array(
				'id'         => $booking_id,
				'lock_token' => $lock_token,
				'order_id'   => 0,
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update a booking status.
	 *
	 * @since 1.0.3
	 *
	 * @param int    $booking_id   Booking ID.
	 * @param string $status       New status.
	 * @param int    $order_id     Optional order ID.
	 * @param bool   $release_lock Whether to clear lock columns.
	 * @return bool
	 */
	private static function update_booking_status( $booking_id, $status, $order_id = 0, $release_lock = false ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$booking_id = absint( $booking_id );
		$order_id   = absint( $order_id );
		$status     = sanitize_key( $status );
		$now        = current_time( 'mysql' );

		if ( $booking_id <= 0 || '' === $status ) {
			return false;
		}

		$data = array(
			'status'     => $status,
			'updated_ts' => $now,
		);
		$formats = array( '%s', '%s' );

		if ( $order_id > 0 ) {
			$data['order_id'] = $order_id;
			$formats[]        = '%d';
		}

		if ( $release_lock ) {
			$data['lock_token']      = null;
			$data['lock_expires_ts'] = null;
			$formats[]               = '%s';
			$formats[]               = '%s';
		}

		$table_name = self::get_table_name();
		$result     = $wpdb->update(
			$table_name,
			$data,
			array( 'id' => $booking_id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}
}
