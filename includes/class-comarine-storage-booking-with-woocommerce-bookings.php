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
	 * Create or update the bookings table.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public static function create_table() {
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

		if ( defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION' ) ) {
			update_option(
				'comarine_storage_booking_with_woocommerce_db_version',
				COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION
			);
		}
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
	 * Get recent bookings for the admin overview.
	 *
	 * @since 1.0.2
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, object>
	 */
	public static function get_recent_bookings( $limit = 20 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		$table_name = self::get_table_name();
		$limit      = max( 1, min( 100, (int) $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY created_ts DESC, id DESC LIMIT %d", $limit );

		$rows = $wpdb->get_results( $query );

		return is_array( $rows ) ? $rows : array();
	}
}
