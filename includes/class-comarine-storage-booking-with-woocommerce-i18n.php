<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Comarine_Storage_Booking_With_Woocommerce_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'comarine-storage-booking-with-woocommerce',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
