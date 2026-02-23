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
 * Version:           1.0.35
 * Author:            George Nicolaou
 * Author URI:        https://www.georgenicolaou.me//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins:  woocommerce, jcc-payment-gateway-for-wc
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
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION', '1.0.35' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_DB_VERSION', '1.0.2' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
// Must stay <= 20 chars (WordPress post type key limit).
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE', 'comarine_storageunit' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE', 'comarine_storage_unit' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_BOOKINGS_TABLE_SUFFIX', 'comarine_bookings' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_AUDIT_TABLE_SUFFIX', 'comarine_booking_audit_log' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION', 'comarine_storage_booking_with_woocommerce_settings' );
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_WC_PLUGIN_FILE', 'woocommerce/woocommerce.php' );
// JCC dependency note (WordPress.org plugin slug): jcc-payment-gateway-for-wc.
define( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_JCC_PLUGIN_FILE', 'jcc-payment-gateway-for-wc/jcc-payment-gateway-for-wc.php' );

/**
 * Get plugin settings defaults.
 *
 * @since 1.0.3
 *
 * @return array<string, mixed>
 */
function comarine_storage_booking_with_woocommerce_get_default_settings() {
	return array(
		'booking_container_product_id' => 0,
		'lock_ttl_minutes'             => 15,
		'paid_unit_status'             => 'reserved',
		'currency'                     => 'EUR',
		'addons_definitions'           => array(),
	);
}

/**
 * Get plugin settings merged with defaults.
 *
 * @since 1.0.3
 *
 * @return array<string, mixed>
 */
function comarine_storage_booking_with_woocommerce_get_settings() {
	$settings = get_option( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, comarine_storage_booking_with_woocommerce_get_default_settings() );
}

/**
 * Get a single plugin setting value.
 *
 * @since 1.0.3
 *
 * @param string $key     Setting key.
 * @param mixed  $default Optional default override.
 * @return mixed
 */
function comarine_storage_booking_with_woocommerce_get_setting( $key, $default = null ) {
	$settings = comarine_storage_booking_with_woocommerce_get_settings();

	if ( array_key_exists( $key, $settings ) ) {
		return $settings[ $key ];
	}

	if ( null !== $default ) {
		return $default;
	}

	$defaults = comarine_storage_booking_with_woocommerce_get_default_settings();

	return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : null;
}

/**
 * Load WordPress plugin admin helper functions when needed.
 *
 * @since 1.0.0
 */
function comarine_storage_booking_with_woocommerce_load_plugin_admin_helpers() {
	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
}

/**
 * Find an installed plugin file by directory slug.
 *
 * @since 1.0.0
 *
 * @param string $directory_slug   Plugin directory slug.
 * @param string $preferred_file   Preferred plugin file path.
 * @return string Empty string if not installed.
 */
function comarine_storage_booking_with_woocommerce_find_installed_plugin_file( $directory_slug, $preferred_file ) {
	comarine_storage_booking_with_woocommerce_load_plugin_admin_helpers();

	$installed_plugins = get_plugins();
	if ( isset( $installed_plugins[ $preferred_file ] ) ) {
		return $preferred_file;
	}

	$prefix = trailingslashit( $directory_slug );
	foreach ( array_keys( $installed_plugins ) as $plugin_file ) {
		if ( 0 === strpos( $plugin_file, $prefix ) ) {
			return $plugin_file;
		}
	}

	return '';
}

/**
 * Check if a plugin file is active (including network activation).
 *
 * @since 1.0.0
 *
 * @param string $plugin_file Plugin file path.
 * @return bool
 */
function comarine_storage_booking_with_woocommerce_is_plugin_file_active( $plugin_file ) {
	if ( empty( $plugin_file ) ) {
		return false;
	}

	comarine_storage_booking_with_woocommerce_load_plugin_admin_helpers();

	return is_plugin_active( $plugin_file ) || ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin_file ) );
}

/**
 * Get required dependency status details.
 *
 * @since 1.0.0
 *
 * @return array[]
 */
function comarine_storage_booking_with_woocommerce_get_dependency_statuses() {
	$dependencies = array(
		array(
			'label'          => 'WooCommerce',
			'directory_slug' => 'woocommerce',
			'preferred_file' => COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_WC_PLUGIN_FILE,
			'install_url'    => 'https://wordpress.org/plugins/woocommerce/',
		),
		array(
			'label'          => 'JCC Payment Gateway for WooCommerce',
			'directory_slug' => 'jcc-payment-gateway-for-wc',
			'preferred_file' => COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_JCC_PLUGIN_FILE,
			'install_url'    => 'https://wordpress.org/plugins/jcc-payment-gateway-for-wc/',
		),
	);

	$statuses = array();
	foreach ( $dependencies as $dependency ) {
		$installed_file = comarine_storage_booking_with_woocommerce_find_installed_plugin_file(
			$dependency['directory_slug'],
			$dependency['preferred_file']
		);

		$dependency['installed_file'] = $installed_file;
		$dependency['is_installed']   = '' !== $installed_file;
		$dependency['is_active']      = $dependency['is_installed'] && comarine_storage_booking_with_woocommerce_is_plugin_file_active( $installed_file );
		$statuses[]                   = $dependency;
	}

	return $statuses;
}

/**
 * Get missing dependency messages for activation/runtime checks.
 *
 * @since 1.0.0
 *
 * @return string[]
 */
function comarine_storage_booking_with_woocommerce_get_missing_dependency_messages() {
	$messages = array();

	foreach ( comarine_storage_booking_with_woocommerce_get_dependency_statuses() as $dependency ) {
		if ( ! $dependency['is_installed'] ) {
			$messages[] = sprintf(
				'%1$s is not installed. Install it first: %2$s',
				$dependency['label'],
				$dependency['install_url']
			);
			continue;
		}

		if ( ! $dependency['is_active'] ) {
			$messages[] = sprintf(
				'%1$s is installed but not active. Activate it before using this plugin.',
				$dependency['label']
			);
		}
	}

	return $messages;
}

/**
 * Stop activation when required plugins are missing.
 *
 * @since 1.0.0
 */
function comarine_storage_booking_with_woocommerce_maybe_abort_activation_for_missing_dependencies() {
	$messages = comarine_storage_booking_with_woocommerce_get_missing_dependency_messages();
	if ( empty( $messages ) ) {
		return;
	}

	comarine_storage_booking_with_woocommerce_load_plugin_admin_helpers();
	deactivate_plugins( plugin_basename( __FILE__ ) );

	if ( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}

	$list_items = '';
	foreach ( $messages as $message ) {
		$list_items .= '<li>' . esc_html( $message ) . '</li>';
	}

	wp_die(
		wp_kses_post(
			'<p>CoMarine Storage Booking with WooCommerce requires the following plugins before activation:</p><ul>' . $list_items . '</ul>'
		),
		'Plugin dependency check failed',
		array( 'back_link' => true )
	);
}

/**
 * Whether all required dependencies are active at runtime.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function comarine_storage_booking_with_woocommerce_dependencies_are_ready() {
	return empty( comarine_storage_booking_with_woocommerce_get_missing_dependency_messages() );
}

/**
 * Show an admin notice when dependencies are missing after activation.
 *
 * @since 1.0.0
 */
function comarine_storage_booking_with_woocommerce_missing_dependencies_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$messages = comarine_storage_booking_with_woocommerce_get_missing_dependency_messages();
	if ( empty( $messages ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p><strong>CoMarine Storage Booking with WooCommerce</strong> is inactive because required plugins are missing.</p><ul>';
	foreach ( $messages as $message ) {
		echo '<li>' . esc_html( $message ) . '</li>';
	}
	echo '</ul></div>';
}

/**
 * Migrate legacy invalid Storage Units CPT slug rows to the current valid slug.
 *
 * WordPress limits post type keys to 20 characters. The legacy slug
 * `comarine_storage_unit` is 21 characters and cannot be registered reliably.
 *
 * @since 1.0.24
 *
 * @return void
 */
function comarine_storage_booking_with_woocommerce_maybe_migrate_legacy_storage_unit_post_type_slug() {
	static $did_run = false;

	if ( $did_run ) {
		return;
	}
	$did_run = true;

	if ( ! defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' ) || ! defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE' ) ) {
		return;
	}

	$new_slug    = COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE;
	$legacy_slug = COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE;
	if ( $new_slug === $legacy_slug ) {
		return;
	}

	$migration_option = 'comarine_storage_booking_with_woocommerce_unit_post_type_slug_migration';
	$migration_state  = get_option( $migration_option, '' );
	if ( is_string( $migration_state ) && $new_slug === $migration_state ) {
		return;
	}

	global $wpdb;
	if ( ! isset( $wpdb->posts ) || empty( $wpdb->posts ) ) {
		return;
	}

	$result = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
			$new_slug,
			$legacy_slug
		)
	);

	if ( false === $result ) {
		return;
	}

	update_option( $migration_option, $new_slug );
}
add_action( 'init', 'comarine_storage_booking_with_woocommerce_maybe_migrate_legacy_storage_unit_post_type_slug', 0 );

/**
 * Always register the Storage Units CPT early so direct admin URLs remain valid.
 *
 * This runs even when the plugin later enters dependency-not-ready mode. It keeps
 * `edit.php?post_type=` links for the Storage Units CPT from failing with "Invalid post type"
 * and avoids duplicate registration when the full plugin also loads.
 *
 * @since 1.0.18
 *
 * @return void
 */
function comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback() {
	if ( post_type_exists( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE ) ) {
		return;
	}

	$class_file = plugin_dir_path( __FILE__ ) . 'includes/class-comarine-storage-booking-with-woocommerce-storage-units.php';
	if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Storage_Units' ) && file_exists( $class_file ) ) {
		require_once $class_file;
	}

	if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Storage_Units' ) ) {
		return;
	}

	$storage_units = new Comarine_Storage_Booking_With_Woocommerce_Storage_Units(
		'comarine-storage-booking-with-woocommerce',
		defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION' ) ? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION : '1.0.0'
	);
	$storage_units->register_post_type();
}
add_action( 'init', 'comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback', 1 );

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
	comarine_storage_booking_with_woocommerce_maybe_abort_activation_for_missing_dependencies();
	comarine_storage_booking_with_woocommerce_maybe_migrate_legacy_storage_unit_post_type_slug();

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

if ( ! comarine_storage_booking_with_woocommerce_dependencies_are_ready() ) {
	add_action( 'admin_notices', 'comarine_storage_booking_with_woocommerce_missing_dependencies_admin_notice' );
	add_action( 'network_admin_notices', 'comarine_storage_booking_with_woocommerce_missing_dependencies_admin_notice' );
	return;
}

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
