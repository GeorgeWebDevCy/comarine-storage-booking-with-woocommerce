<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Comarine_Storage_Booking_With_Woocommerce {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Comarine_Storage_Booking_With_Woocommerce_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION' ) ) {
			$this->version = COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'comarine-storage-booking-with-woocommerce';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_domain_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Comarine_Storage_Booking_With_Woocommerce_Loader. Orchestrates the hooks of the plugin.
	 * - Comarine_Storage_Booking_With_Woocommerce_i18n. Defines internationalization functionality.
	 * - Comarine_Storage_Booking_With_Woocommerce_Admin. Defines all hooks for the admin area.
	 * - Comarine_Storage_Booking_With_Woocommerce_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-i18n.php';

		/**
		 * Storage units and bookings domain classes.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-bookings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-storage-units.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-woocommerce-integration.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-comarine-storage-booking-with-woocommerce-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-comarine-storage-booking-with-woocommerce-public.php';

		$this->loader = new Comarine_Storage_Booking_With_Woocommerce_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Comarine_Storage_Booking_With_Woocommerce_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Comarine_Storage_Booking_With_Woocommerce_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register domain hooks shared across admin and frontend.
	 *
	 * @since    1.0.2
	 * @access   private
	 */
	private function define_domain_hooks() {
		$storage_units = new Comarine_Storage_Booking_With_Woocommerce_Storage_Units( $this->get_plugin_name(), $this->get_version() );
		$bookings = new Comarine_Storage_Booking_With_Woocommerce_Bookings();
		$wc_integration = new Comarine_Storage_Booking_With_Woocommerce_WooCommerce_Integration( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'plugins_loaded', $bookings, 'maybe_upgrade_schema', 20 );
		$this->loader->add_action( 'init', $storage_units, 'register_post_type', 5 );
		$this->loader->add_action( 'init', $wc_integration, 'register_shortcodes', 20 );
		$this->loader->add_action( 'init', $wc_integration, 'maybe_expire_stale_locks', 30 );
		$this->loader->add_action( 'template_redirect', $wc_integration, 'maybe_handle_booking_submission' );

		// WooCommerce booking item/cart/order integration hooks.
		$this->loader->add_filter( 'woocommerce_get_cart_item_from_session', $wc_integration, 'restore_cart_item_from_session', 10, 3 );
		$this->loader->add_filter( 'woocommerce_get_item_data', $wc_integration, 'display_cart_item_data', 10, 2 );
		$this->loader->add_filter( 'woocommerce_cart_item_name', $wc_integration, 'filter_cart_item_name', 10, 3 );
		$this->loader->add_action( 'woocommerce_before_calculate_totals', $wc_integration, 'apply_booking_prices_to_cart' );
		$this->loader->add_action( 'woocommerce_check_cart_items', $wc_integration, 'validate_booking_cart_items' );
		$this->loader->add_action( 'woocommerce_after_checkout_validation', $wc_integration, 'validate_checkout_booking_locks', 10, 2 );
		$this->loader->add_action( 'woocommerce_cart_item_removed', $wc_integration, 'handle_cart_item_removed', 10, 2 );
		$this->loader->add_action( 'woocommerce_checkout_create_order_line_item', $wc_integration, 'add_booking_meta_to_order_line_item', 10, 4 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', $wc_integration, 'link_bookings_to_order', 10, 3 );

		// JCC marks successful payments as "completed". Keep "processing" as a gateway compatibility fallback.
		$this->loader->add_action( 'woocommerce_order_status_completed', $wc_integration, 'handle_order_completed', 10, 2 );
		$this->loader->add_action( 'woocommerce_order_status_processing', $wc_integration, 'handle_order_processing', 10, 2 );
		$this->loader->add_action( 'woocommerce_order_status_failed', $wc_integration, 'handle_order_failed', 10, 2 );
		$this->loader->add_action( 'woocommerce_order_status_cancelled', $wc_integration, 'handle_order_cancelled', 10, 2 );
		$this->loader->add_action( 'woocommerce_order_status_refunded', $wc_integration, 'handle_order_refunded', 10, 2 );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Comarine_Storage_Booking_With_Woocommerce_Admin( $this->get_plugin_name(), $this->get_version() );
		$storage_units = new Comarine_Storage_Booking_With_Woocommerce_Storage_Units( $this->get_plugin_name(), $this->get_version() );
		$storage_unit_post_type = $storage_units->get_post_type();
		$plugin_basename = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_PLUGIN_BASENAME' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_PLUGIN_BASENAME
			: plugin_basename( dirname( dirname( __FILE__ ) ) . '/comarine-storage-booking-with-woocommerce.php' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_menu' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'ensure_storage_units_submenus', 999 );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_setup_admin_actions' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'handle_bookings_admin_actions' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'maybe_show_configuration_notices' );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_order_details', $plugin_admin, 'render_order_booking_summary' );
		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_plugin_action_links' );

		$this->loader->add_action( 'add_meta_boxes', $storage_units, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post_' . $storage_unit_post_type, $storage_units, 'save_unit_meta', 10, 3 );
		$this->loader->add_filter( 'manage_' . $storage_unit_post_type . '_posts_columns', $storage_units, 'filter_admin_columns' );
		$this->loader->add_action( 'manage_' . $storage_unit_post_type . '_posts_custom_column', $storage_units, 'render_admin_column', 10, 2 );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Comarine_Storage_Booking_With_Woocommerce_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Comarine_Storage_Booking_With_Woocommerce_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
