<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.georgenicolaou.me/
 * @since      1.0.0
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/admin
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Comarine_Storage_Booking_With_Woocommerce_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/comarine-storage-booking-with-woocommerce-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/comarine-storage-booking-with-woocommerce-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Register admin menu pages for bookings management.
	 *
	 * @since    1.0.2
	 */
	public function register_admin_menu() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storage_unit';

		add_submenu_page(
			'edit.php?post_type=' . $post_type,
			__( 'Bookings', 'comarine-storage-booking-with-woocommerce' ),
			__( 'Bookings', 'comarine-storage-booking-with-woocommerce' ),
			$this->get_admin_capability(),
			'comarine-storage-bookings',
			array( $this, 'render_bookings_page' )
		);
	}

	/**
	 * Render a basic bookings overview page.
	 *
	 * @since    1.0.2
	 */
	public function render_bookings_page() {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Bookings helper class is not loaded.', 'comarine-storage-booking-with-woocommerce' ) . '</p></div></div>';
			return;
		}

		$count         = Comarine_Storage_Booking_With_Woocommerce_Bookings::count_bookings();
		$recent_rows   = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_recent_bookings( 20 );
		$table_name    = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_table_name();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'CoMarine Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		echo '<p>' . esc_html__( 'This is the initial bookings administration view. Booking creation and checkout synchronization will be added in the next milestones.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Bookings table:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> <code>' . esc_html( $table_name ) . '</code></p>';
		echo '<p><strong>' . esc_html__( 'Total bookings:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( (string) $count ) . '</p>';

		echo '<h2>' . esc_html__( 'Recent bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';

		if ( empty( $recent_rows ) ) {
			echo '<p>' . esc_html__( 'No bookings found yet. The booking flow is not implemented in this milestone, so this table is expected to be empty.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $recent_rows as $row ) {
			$price_display = trim( (string) $row->price_total . ' ' . (string) $row->currency );

			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>' . esc_html( (string) $row->unit_code ) . '</td>';
			echo '<td>' . esc_html( (string) $row->order_id ) . '</td>';
			echo '<td>' . esc_html( (string) $row->duration_key ) . '</td>';
			echo '<td>' . esc_html( (string) $row->status ) . '</td>';
			echo '<td>' . esc_html( $price_display ) . '</td>';
			echo '<td>' . esc_html( (string) $row->created_ts ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Get capability required for plugin management pages.
	 *
	 * @since    1.0.2
	 *
	 * @return string
	 */
	private function get_admin_capability() {
		return current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

}
