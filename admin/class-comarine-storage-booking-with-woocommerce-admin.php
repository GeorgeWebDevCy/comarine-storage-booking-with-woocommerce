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

		add_submenu_page(
			'edit.php?post_type=' . $post_type,
			__( 'Settings', 'comarine-storage-booking-with-woocommerce' ),
			__( 'Settings', 'comarine-storage-booking-with-woocommerce' ),
			$this->get_admin_capability(),
			$this->get_settings_page_slug(),
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings and fields.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'comarine_storage_booking_with_woocommerce_settings_group',
			COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'comarine_storage_booking_general_settings',
			__( 'Booking Settings', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_settings_section_intro' ),
			$this->get_settings_page_slug()
		);

		add_settings_field(
			'booking_container_product_id',
			__( 'Booking container product', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_booking_container_product_field' ),
			$this->get_settings_page_slug(),
			'comarine_storage_booking_general_settings'
		);

		add_settings_field(
			'lock_ttl_minutes',
			__( 'Lock TTL (minutes)', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_lock_ttl_field' ),
			$this->get_settings_page_slug(),
			'comarine_storage_booking_general_settings'
		);

		add_settings_field(
			'paid_unit_status',
			__( 'Unit status after payment', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_paid_status_field' ),
			$this->get_settings_page_slug(),
			'comarine_storage_booking_general_settings'
		);

		add_settings_field(
			'currency',
			__( 'Booking currency snapshot', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_currency_field' ),
			$this->get_settings_page_slug(),
			'comarine_storage_booking_general_settings'
		);
	}

	/**
	 * Handle booking admin actions submitted via query params.
	 *
	 * @since    1.0.5
	 *
	 * @return void
	 */
	public function handle_bookings_admin_actions() {
		if ( ! is_admin() || ! current_user_can( $this->get_admin_capability() ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'comarine-storage-bookings' !== $page ) {
			return;
		}

		$action = isset( $_GET['comarine_booking_admin_action'] ) ? sanitize_key( wp_unslash( $_GET['comarine_booking_admin_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}

		$nonce = isset( $_GET['_comarine_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_comarine_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'comarine_booking_admin_action' ) ) {
			wp_safe_redirect( $this->get_bookings_page_url( array( 'comarine_notice' => 'invalid_nonce' ) ) );
			exit;
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			wp_safe_redirect( $this->get_bookings_page_url( array( 'comarine_notice' => 'error' ) ) );
			exit;
		}

		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		$booking    = $booking_id > 0 ? Comarine_Storage_Booking_With_Woocommerce_Bookings::get_booking( $booking_id ) : null;
		if ( ! $booking ) {
			wp_safe_redirect( $this->get_bookings_page_url( array( 'comarine_notice' => 'booking_not_found' ) ) );
			exit;
		}

		$success = false;

		switch ( $action ) {
			case 'mark_paid':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_paid( $booking_id, (int) $booking->order_id );
				if ( $success && (int) $booking->unit_post_id > 0 ) {
					$paid_unit_status = (string) comarine_storage_booking_with_woocommerce_get_setting( 'paid_unit_status', 'reserved' );
					update_post_meta( (int) $booking->unit_post_id, '_csu_status', in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved' );
				}
				break;

			case 'mark_cancelled':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_cancelled( $booking_id, (int) $booking->order_id );
				break;

			case 'mark_refunded':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_refunded( $booking_id, (int) $booking->order_id );
				break;

			case 'set_booking_status':
				$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::set_booking_status( $booking_id, $status );
				break;

			case 'set_unit_status':
				$unit_status = isset( $_GET['unit_status'] ) ? sanitize_key( wp_unslash( $_GET['unit_status'] ) ) : '';
				$success     = $this->update_booking_unit_status( $booking, $unit_status );
				break;
		}

		wp_safe_redirect(
			$this->get_bookings_page_url(
				array(
					'comarine_notice' => $success ? 'updated' : 'update_failed',
					'booking_id'       => $booking_id,
				)
			)
		);
		exit;
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

		$status_filter = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : '';
		$order_filter  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$booking_filter = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;

		$query_args = array(
			'limit'      => 50,
			'status'     => $status_filter,
			'order_id'   => $order_filter,
			'booking_id' => $booking_filter,
		);

		$count         = Comarine_Storage_Booking_With_Woocommerce_Bookings::count_bookings_filtered(
			array(
				'status'    => $status_filter,
				'order_id'  => $order_filter,
				'booking_id' => $booking_filter,
			)
		);
		$recent_rows   = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_bookings( $query_args );
		$table_name    = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_table_name();
		$status_options = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_options();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'CoMarine Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		$this->render_bookings_page_notice();
		echo '<p>' . esc_html__( 'Manage booking records, inspect linked WooCommerce orders, and perform manual status overrides when needed.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Bookings table:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> <code>' . esc_html( $table_name ) . '</code></p>';
		echo '<p><strong>' . esc_html__( 'Total bookings:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( (string) $count ) . '</p>';
		echo '<form method="get" style="margin:12px 0 16px;">';
		echo '<input type="hidden" name="post_type" value="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE ) . '" />';
		echo '<input type="hidden" name="page" value="comarine-storage-bookings" />';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . ' ';
		echo '<select name="status_filter">';
		echo '<option value="">' . esc_html__( 'All statuses', 'comarine-storage-booking-with-woocommerce' ) . '</option>';
		foreach ( $status_options as $status_key => $status_label ) {
			echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $status_filter, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Order ID', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="small-text" type="number" min="0" name="order_id" value="' . esc_attr( (string) $order_filter ) . '" /></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Booking ID', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="small-text" type="number" min="0" name="booking_id" value="' . esc_attr( (string) $booking_filter ) . '" /></label>';
		submit_button( __( 'Filter', 'comarine-storage-booking-with-woocommerce' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( $this->get_bookings_page_url() ) . '">' . esc_html__( 'Reset', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recent bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';

		if ( empty( $recent_rows ) ) {
			echo '<p>' . esc_html__( 'No bookings found yet. The booking flow is not implemented in this milestone, so this table is expected to be empty.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit Status', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Lock Expires', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $recent_rows as $row ) {
			$price_display = trim( (string) $row->price_total . ' ' . (string) $row->currency );
			$unit_status   = (int) $row->unit_post_id > 0 ? (string) get_post_meta( (int) $row->unit_post_id, '_csu_status', true ) : '';
			$unit_link     = (int) $row->unit_post_id > 0 ? get_edit_post_link( (int) $row->unit_post_id ) : '';
			$order_link    = (int) $row->order_id > 0 ? admin_url( 'post.php?post=' . absint( $row->order_id ) . '&action=edit' ) : '';
			$actions       = $this->get_booking_row_actions( $row );

			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>';
			if ( $unit_link ) {
				echo '<a href="' . esc_url( $unit_link ) . '">' . esc_html( (string) $row->unit_code ) . '</a>';
			} else {
				echo esc_html( (string) $row->unit_code );
			}
			echo '</td>';
			echo '<td>' . esc_html( $unit_status ? ucfirst( $unit_status ) : '-' ) . '</td>';
			echo '<td>';
			if ( $order_link ) {
				echo '<a href="' . esc_url( $order_link ) . '">#' . esc_html( (string) $row->order_id ) . '</a>';
			} else {
				echo esc_html( (string) $row->order_id );
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $row->duration_key ) . '</td>';
			echo '<td>' . esc_html( Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( (string) $row->status ) ) . '</td>';
			echo '<td>' . esc_html( $price_display ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row->lock_expires_ts ?: '-' ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row->created_ts ) . '</td>';
			echo '<td>';
			if ( empty( $actions ) ) {
				echo esc_html__( 'No actions', 'comarine-storage-booking-with-woocommerce' );
			} else {
				foreach ( $actions as $action_html ) {
					echo $action_html . ' ';
				}
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the settings page.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'CoMarine Storage Booking Settings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'comarine_storage_booking_with_woocommerce_settings_group' );
		do_settings_sections( $this->get_settings_page_slug() );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @since    1.0.3
	 *
	 * @param array $input Raw submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$settings = comarine_storage_booking_with_woocommerce_get_default_settings();

		$settings['booking_container_product_id'] = isset( $input['booking_container_product_id'] ) ? absint( $input['booking_container_product_id'] ) : 0;

		$ttl = isset( $input['lock_ttl_minutes'] ) ? (int) $input['lock_ttl_minutes'] : (int) $settings['lock_ttl_minutes'];
		$settings['lock_ttl_minutes'] = max( 1, min( 120, $ttl ) );

		$paid_status = isset( $input['paid_unit_status'] ) ? sanitize_text_field( wp_unslash( $input['paid_unit_status'] ) ) : (string) $settings['paid_unit_status'];
		$settings['paid_unit_status'] = in_array( $paid_status, array( 'reserved', 'occupied' ), true ) ? $paid_status : 'reserved';

		$currency = isset( $input['currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $input['currency'] ) ) ) : (string) $settings['currency'];
		$currency = preg_replace( '/[^A-Z]/', '', $currency );
		$settings['currency'] = ! empty( $currency ) ? substr( $currency, 0, 8 ) : 'EUR';

		return $settings;
	}

	/**
	 * Render settings section intro text.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_settings_section_intro() {
		echo '<p>' . esc_html__( 'Configure the WooCommerce booking container product and booking lock behavior. These settings are used by the booking flow and checkout synchronization hooks.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
	}

	/**
	 * Render the booking container product field.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_booking_container_product_field() {
		$current_value = (int) comarine_storage_booking_with_woocommerce_get_setting( 'booking_container_product_id', 0 );
		$products      = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		echo '<select class="regular-text" name="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION ) . '[booking_container_product_id]">';
		echo '<option value="0">' . esc_html__( 'Select a WooCommerce product', 'comarine-storage-booking-with-woocommerce' ) . '</option>';

		foreach ( $products as $product_id ) {
			$title = get_the_title( $product_id );
			echo '<option value="' . esc_attr( (string) $product_id ) . '" ' . selected( $current_value, (int) $product_id, false ) . '>' . esc_html( sprintf( '#%1$d %2$s', $product_id, $title ) ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Use a virtual WooCommerce product as the checkout wrapper for storage bookings.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
	}

	/**
	 * Render lock TTL field.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_lock_ttl_field() {
		$current_value = (int) comarine_storage_booking_with_woocommerce_get_setting( 'lock_ttl_minutes', 15 );

		echo '<input type="number" min="1" max="120" class="small-text" name="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION ) . '[lock_ttl_minutes]" value="' . esc_attr( (string) $current_value ) . '" />';
		echo '<p class="description">' . esc_html__( 'How long a unit stays locked while the customer is in checkout (default 15 minutes).', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
	}

	/**
	 * Render paid unit status field.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_paid_status_field() {
		$current_value = (string) comarine_storage_booking_with_woocommerce_get_setting( 'paid_unit_status', 'reserved' );
		$options       = array(
			'reserved' => __( 'Reserved', 'comarine-storage-booking-with-woocommerce' ),
			'occupied' => __( 'Occupied', 'comarine-storage-booking-with-woocommerce' ),
		);

		echo '<select name="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION ) . '[paid_unit_status]">';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_value, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Status to apply to a unit when the linked WooCommerce order is paid.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
	}

	/**
	 * Render currency field.
	 *
	 * @since    1.0.3
	 *
	 * @return void
	 */
	public function render_currency_field() {
		$current_value = (string) comarine_storage_booking_with_woocommerce_get_setting( 'currency', 'EUR' );

		echo '<input type="text" maxlength="8" class="regular-text" name="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION ) . '[currency]" value="' . esc_attr( $current_value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Currency code stored on booking snapshots (for example EUR).', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
	}

	/**
	 * Show configuration notices for incomplete/invalid booking setup.
	 *
	 * @since    1.0.4
	 *
	 * @return void
	 */
	public function maybe_show_configuration_notices() {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			return;
		}

		if ( ! $this->is_plugin_admin_screen() ) {
			return;
		}

		$container_product_id = (int) comarine_storage_booking_with_woocommerce_get_setting( 'booking_container_product_id', 0 );
		if ( $container_product_id <= 0 ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'CoMarine Storage Booking: Select a WooCommerce booking container product in Storage Units > Settings before accepting bookings.', 'comarine-storage-booking-with-woocommerce' );
			echo '</p></div>';
			return;
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( $container_product_id );
		if ( ! $product ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'CoMarine Storage Booking: The configured booking container product no longer exists. Update the setting before accepting bookings.', 'comarine-storage-booking-with-woocommerce' );
			echo '</p></div>';
			return;
		}

		if ( method_exists( $product, 'is_virtual' ) && ! $product->is_virtual() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'CoMarine Storage Booking: The booking container product should be virtual to avoid shipping/fulfillment side effects during checkout.', 'comarine-storage-booking-with-woocommerce' );
			echo '</p></div>';
		}
	}

	/**
	 * Render booking details on the WooCommerce order admin page.
	 *
	 * @since    1.0.5
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public function render_order_booking_summary( $order ) {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			return;
		}

		if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			return;
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			return;
		}

		$bookings = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_bookings_for_order( (int) $order->get_id() );
		if ( empty( $bookings ) ) {
			return;
		}

		echo '<div class="order_data_column" style="width:100%;">';
		echo '<h3>' . esc_html__( 'CoMarine Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h3>';
		echo '<p><a href="' . esc_url( $this->get_bookings_page_url( array( 'order_id' => (int) $order->get_id() ) ) ) . '">' . esc_html__( 'View in Bookings admin', 'comarine-storage-booking-with-woocommerce' ) . '</a></p>';
		echo '<table class="widefat striped" style="margin-bottom:8px;"><thead><tr>';
		echo '<th>' . esc_html__( 'Booking', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $bookings as $booking ) {
			$unit_link = (int) $booking->unit_post_id > 0 ? get_edit_post_link( (int) $booking->unit_post_id ) : '';
			$price     = trim( (string) $booking->price_total . ' ' . (string) $booking->currency );

			echo '<tr>';
			echo '<td>#' . esc_html( (string) $booking->id ) . '</td>';
			echo '<td>';
			if ( $unit_link ) {
				echo '<a href="' . esc_url( $unit_link ) . '">' . esc_html( (string) $booking->unit_code ) . '</a>';
			} else {
				echo esc_html( (string) $booking->unit_code );
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $booking->duration_key ) . '</td>';
			echo '<td>' . esc_html( Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( (string) $booking->status ) ) . '</td>';
			echo '<td>' . esc_html( $price ) . '</td>';
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

	/**
	 * Get settings page slug.
	 *
	 * @since    1.0.3
	 *
	 * @return string
	 */
	private function get_settings_page_slug() {
		return 'comarine-storage-booking-settings';
	}

	/**
	 * Build the Bookings admin page URL.
	 *
	 * @since    1.0.5
	 *
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	private function get_bookings_page_url( $args = array() ) {
		$base_args = array(
			'post_type' => defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' ) ? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE : 'comarine_storage_unit',
			'page'      => 'comarine-storage-bookings',
		);

		return add_query_arg( array_merge( $base_args, $args ), admin_url( 'edit.php' ) );
	}

	/**
	 * Render admin notice for bookings page action results.
	 *
	 * @since    1.0.5
	 *
	 * @return void
	 */
	private function render_bookings_page_notice() {
		$notice = isset( $_GET['comarine_notice'] ) ? sanitize_key( wp_unslash( $_GET['comarine_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}

		$messages = array(
			'updated'          => array( 'success', __( 'Booking record updated.', 'comarine-storage-booking-with-woocommerce' ) ),
			'update_failed'    => array( 'error', __( 'Could not update the booking record.', 'comarine-storage-booking-with-woocommerce' ) ),
			'booking_not_found'=> array( 'error', __( 'Booking record not found.', 'comarine-storage-booking-with-woocommerce' ) ),
			'invalid_nonce'    => array( 'error', __( 'Security check failed for booking action.', 'comarine-storage-booking-with-woocommerce' ) ),
			'error'            => array( 'error', __( 'An unexpected booking admin error occurred.', 'comarine-storage-booking-with-woocommerce' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $messages[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Get row action links for a booking.
	 *
	 * @since    1.0.5
	 *
	 * @param object $booking Booking row.
	 * @return array<int, string>
	 */
	private function get_booking_row_actions( $booking ) {
		$actions = array();

		$booking_id = isset( $booking->id ) ? absint( $booking->id ) : 0;
		if ( $booking_id <= 0 ) {
			return $actions;
		}

		$current_status = isset( $booking->status ) ? sanitize_key( (string) $booking->status ) : '';
		$unit_post_id   = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;

		if ( ! in_array( $current_status, array( 'paid', 'cancelled', 'refunded', 'expired' ), true ) ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'mark_paid', __( 'Mark Paid', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( 'cancelled' !== $current_status ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'mark_cancelled', __( 'Cancel', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( 'refunded' !== $current_status && ( isset( $booking->order_id ) && (int) $booking->order_id > 0 ) ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'mark_refunded', __( 'Mark Refunded', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( $unit_post_id > 0 ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'set_unit_status', __( 'Unit: Reserved', 'comarine-storage-booking-with-woocommerce' ), array( 'unit_status' => 'reserved' ) );
			$actions[] = $this->build_booking_action_link( $booking_id, 'set_unit_status', __( 'Unit: Occupied', 'comarine-storage-booking-with-woocommerce' ), array( 'unit_status' => 'occupied' ) );
			$actions[] = $this->build_booking_action_link( $booking_id, 'set_unit_status', __( 'Unit: Available', 'comarine-storage-booking-with-woocommerce' ), array( 'unit_status' => 'available' ) );
		}

		return $actions;
	}

	/**
	 * Build a secured bookings admin action link.
	 *
	 * @since    1.0.5
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $action     Action key.
	 * @param string $label      Link label.
	 * @param array  $extra_args Extra query args.
	 * @return string
	 */
	private function build_booking_action_link( $booking_id, $action, $label, $extra_args = array() ) {
		$args = array_merge(
			array(
				'booking_id'                    => absint( $booking_id ),
				'comarine_booking_admin_action' => sanitize_key( $action ),
				'_comarine_nonce'               => wp_create_nonce( 'comarine_booking_admin_action' ),
			),
			$extra_args
		);

		return '<a class="button button-small" href="' . esc_url( $this->get_bookings_page_url( $args ) ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Update the linked unit status for a booking.
	 *
	 * @since    1.0.5
	 *
	 * @param object $booking     Booking row.
	 * @param string $unit_status Unit status key.
	 * @return bool
	 */
	private function update_booking_unit_status( $booking, $unit_status ) {
		$unit_post_id = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;
		$unit_status  = sanitize_key( (string) $unit_status );

		if ( $unit_post_id <= 0 ) {
			return false;
		}

		$allowed = array( 'available', 'reserved', 'occupied', 'maintenance', 'archived' );
		if ( ! in_array( $unit_status, $allowed, true ) ) {
			return false;
		}

		$current = (string) get_post_meta( $unit_post_id, '_csu_status', true );
		if ( $current === $unit_status ) {
			return true;
		}

		return false !== update_post_meta( $unit_post_id, '_csu_status', $unit_status );
	}

	/**
	 * Determine whether the current admin screen is related to this plugin.
	 *
	 * @since    1.0.4
	 *
	 * @return bool
	 */
	private function is_plugin_admin_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storage_unit';

		$targets = array(
			'edit-' . $post_type,
			$post_type,
			$post_type . '_page_comarine-storage-bookings',
			$post_type . '_page_' . $this->get_settings_page_slug(),
		);

		return in_array( $screen->id, $targets, true );
	}

}
