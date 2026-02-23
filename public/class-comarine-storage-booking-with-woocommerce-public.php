<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/public
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Comarine_Storage_Booking_With_Woocommerce_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Comarine_Storage_Booking_With_Woocommerce_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Comarine_Storage_Booking_With_Woocommerce_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/comarine-storage-booking-with-woocommerce-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'jquery-ui-datepicker' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Comarine_Storage_Booking_With_Woocommerce_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Comarine_Storage_Booking_With_Woocommerce_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( 'jquery-ui-datepicker' );
		if ( function_exists( 'wp_localize_jquery_ui_datepicker' ) ) {
			wp_localize_jquery_ui_datepicker();
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/comarine-storage-booking-with-woocommerce-public.js', array( 'jquery', 'jquery-ui-datepicker' ), $this->version, false );

		wp_localize_script(
			$this->plugin_name,
			'comarineStorageBookingPublic',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'availabilityAction'    => 'comarine_storage_booking_daily_availability',
				'availabilityNonce'     => wp_create_nonce( 'comarine_storage_booking_public_availability' ),
				'availabilityHorizonDays' => 540,
				'datepicker'            => array(
					'dateFormat'  => 'yy-mm-dd',
					'firstDay'    => (int) get_option( 'start_of_week', 1 ),
					'changeMonth' => true,
					'changeYear'  => true,
				),
				'i18n'                  => array(
					'loadingAvailability'  => __( 'Loading availability...', 'comarine-storage-booking-with-woocommerce' ),
					'availabilityError'    => __( 'Availability could not be loaded. Dates will be validated at checkout.', 'comarine-storage-booking-with-woocommerce' ),
					'noCapacityForDate'    => __( 'Unavailable on this date', 'comarine-storage-booking-with-woocommerce' ),
					'insufficientArea'     => __( 'Not enough available area for selected m2', 'comarine-storage-booking-with-woocommerce' ),
					'selectStartDateFirst' => __( 'Select start date first', 'comarine-storage-booking-with-woocommerce' ),
					'endDateBlocked'       => __( 'Selected range includes unavailable dates', 'comarine-storage-booking-with-woocommerce' ),
				),
			)
		);

	}

}
