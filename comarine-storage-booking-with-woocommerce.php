<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.georgenicolaou.me/
 * @since             1.0.0
 * @package           Comarine_Storage_Booking_With_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Comarine Storage booking with WooCommerce
 * Plugin URI:        https://www.georgenicolaou.me/plugins/comarine-storage-booking-with-woocommerce/
 * Description:       Booking plugin for CoMarine Storage Units
 * Version:           1.0.0
 * Author:            George Nicolaou
 * Author URI:        https://www.georgenicolaou.me//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       comarine-storage-booking-with-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Composer dependencies when available.
$comarine_storage_booking_with_woocommerce_autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( file_exists( $comarine_storage_booking_with_woocommerce_autoload ) ) {
	require_once $comarine_storage_booking_with_woocommerce_autoload;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION', '1.0.0' );

/**
 * Configure GitHub-based updates via plugin-update-checker.
 *
 * @since 1.0.0
 */
function comarine_storage_booking_with_woocommerce_setup_update_checker() {
	if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	$GLOBALS['comarine_storage_booking_with_woocommerce_update_checker'] = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/GeorgeWebDevCy/comarine-storage-booking-with-woocommerce/',
		__FILE__,
		'comarine-storage-booking-with-woocommerce'
	);

	$GLOBALS['comarine_storage_booking_with_woocommerce_update_checker']->setBranch( 'main' );
}
comarine_storage_booking_with_woocommerce_setup_update_checker();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-comarine-storage-booking-with-woocommerce-activator.php
 */
function activate_comarine_storage_booking_with_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-comarine-storage-booking-with-woocommerce-activator.php';
	Comarine_Storage_Booking_With_Woocommerce_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-comarine-storage-booking-with-woocommerce-deactivator.php
 */
function deactivate_comarine_storage_booking_with_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-comarine-storage-booking-with-woocommerce-deactivator.php';
	Comarine_Storage_Booking_With_Woocommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_comarine_storage_booking_with_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_comarine_storage_booking_with_woocommerce' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-comarine-storage-booking-with-woocommerce.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_comarine_storage_booking_with_woocommerce() {

	$plugin = new Comarine_Storage_Booking_With_Woocommerce();
	$plugin->run();

}
run_comarine_storage_booking_with_woocommerce();
