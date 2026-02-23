<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Comarine_Storage_Booking_With_Woocommerce_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-bookings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-storage-units.php';

		Comarine_Storage_Booking_With_Woocommerce_Bookings::create_table();

		$storage_units = new Comarine_Storage_Booking_With_Woocommerce_Storage_Units();
		$storage_units->register_post_type();

		flush_rewrite_rules();
	}

}
