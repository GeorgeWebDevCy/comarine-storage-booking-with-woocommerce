<?php

/**
 * WooCommerce booking flow integration.
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * Handles booking locks, cart metadata, and order synchronization.
 */
class Comarine_Storage_Booking_With_Woocommerce_WooCommerce_Integration {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since 1.0.3
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register frontend shortcodes.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'comarine_storage_units', array( $this, 'render_storage_units_shortcode' ) );
	}

	/**
	 * Periodically expire stale booking locks.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public function maybe_expire_stale_locks() {
		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			return;
		}

		$transient_key = 'comarine_storage_booking_lock_cleanup_ran';
		if ( get_transient( $transient_key ) ) {
			return;
		}

		Comarine_Storage_Booking_With_Woocommerce_Bookings::expire_stale_locks();
		set_transient( $transient_key, '1', MINUTE_IN_SECONDS );
	}

	/**
	 * Handle booking form submission and create a booking lock.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public function maybe_handle_booking_submission() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		if ( empty( $_POST['comarine_storage_booking_action'] ) || 'start_booking' !== sanitize_key( wp_unslash( $_POST['comarine_storage_booking_action'] ) ) ) {
			return;
		}

		$redirect_url = wp_get_referer();
		if ( empty( $redirect_url ) ) {
			$redirect_url = home_url( '/' );
		}

		if ( ! isset( $_POST['comarine_storage_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['comarine_storage_booking_nonce'] ) ), 'comarine_storage_start_booking' ) ) {
			$this->add_wc_notice( __( 'Security check failed. Please try again.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( ! $this->wc_cart_is_ready() ) {
			$this->add_wc_notice( __( 'WooCommerce cart is not available yet. Please try again.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$settings             = comarine_storage_booking_with_woocommerce_get_settings();
		$container_product_id = isset( $settings['booking_container_product_id'] ) ? absint( $settings['booking_container_product_id'] ) : 0;
		if ( $container_product_id <= 0 || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $container_product_id ) ) {
			$this->add_wc_notice( __( 'Booking container product is not configured. Please contact the site administrator.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$unit_post_id = isset( $_POST['comarine_unit_post_id'] ) ? absint( $_POST['comarine_unit_post_id'] ) : 0;
		$duration_key = isset( $_POST['comarine_duration_key'] ) ? sanitize_key( wp_unslash( $_POST['comarine_duration_key'] ) ) : '';

		$unit = get_post( $unit_post_id );
		if ( ! $unit || COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE !== $unit->post_type || 'publish' !== $unit->post_status ) {
			$this->add_wc_notice( __( 'Selected storage unit is invalid.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$unit_status = (string) get_post_meta( $unit_post_id, '_csu_status', true );
		if ( '' === $unit_status ) {
			$unit_status = 'available';
		}
		if ( 'available' !== $unit_status ) {
			$this->add_wc_notice( __( 'This unit is currently not available.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$price_map = $this->get_unit_duration_prices( $unit_post_id );
		if ( ! isset( $price_map[ $duration_key ] ) ) {
			$this->add_wc_notice( __( 'Selected rental duration is not available for this unit.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$this->remove_existing_cart_booking_for_unit( $unit_post_id );

		$lock_result = Comarine_Storage_Booking_With_Woocommerce_Bookings::create_locked_booking(
			array(
				'unit_post_id'       => $unit_post_id,
				'unit_code'          => (string) get_post_meta( $unit_post_id, '_csu_unit_code', true ) ?: $unit->post_title,
				'duration_key'       => $duration_key,
				'price_total'        => (float) $price_map[ $duration_key ],
				'currency'           => isset( $settings['currency'] ) ? (string) $settings['currency'] : 'EUR',
				'user_id'            => get_current_user_id(),
				'lock_ttl_minutes'   => isset( $settings['lock_ttl_minutes'] ) ? (int) $settings['lock_ttl_minutes'] : 15,
			)
		);

		if ( is_wp_error( $lock_result ) ) {
			$this->add_wc_notice( $lock_result->get_error_message(), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$cart_item_data = array(
			'comarine_storage_booking' => array(
				'unit_post_id'    => $unit_post_id,
				'unit_code'       => (string) get_post_meta( $unit_post_id, '_csu_unit_code', true ) ?: $unit->post_title,
				'unit_title'      => $unit->post_title,
				'duration_key'    => $duration_key,
				'booking_id'      => (int) $lock_result['booking_id'],
				'lock_token'      => (string) $lock_result['lock_token'],
				'lock_expires_ts' => (string) $lock_result['lock_expires_ts'],
				'price_snapshot'  => (float) $lock_result['price_total'],
				'currency'        => (string) $lock_result['currency'],
			),
			'comarine_storage_booking_key' => md5( wp_json_encode( $lock_result ) . microtime( true ) ),
		);

		$added = WC()->cart->add_to_cart( $container_product_id, 1, 0, array(), $cart_item_data );
		if ( ! $added ) {
			Comarine_Storage_Booking_With_Woocommerce_Bookings::cancel_booking_lock( (int) $lock_result['booking_id'], (string) $lock_result['lock_token'] );
			$this->add_wc_notice( __( 'Could not add the booking to the cart. Please try again.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$this->add_wc_notice( __( 'Storage booking added to your cart. Continue to checkout to complete payment.', 'comarine-storage-booking-with-woocommerce' ) );

		$go_to_checkout = ! empty( $_POST['comarine_redirect_to_checkout'] );
		$target_url     = $go_to_checkout && function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : wc_get_cart_url();

		wp_safe_redirect( $target_url ? $target_url : $redirect_url );
		exit;
	}

	/**
	 * Restore booking cart item data from session.
	 *
	 * @since 1.0.3
	 *
	 * @param array  $session_data Restored session item data.
	 * @param array  $values       Session values.
	 * @param string $key          Cart item key.
	 * @return array
	 */
	public function restore_cart_item_from_session( $session_data, $values, $key ) {
		unset( $key );

		if ( isset( $values['comarine_storage_booking'] ) && is_array( $values['comarine_storage_booking'] ) ) {
			$session_data['comarine_storage_booking'] = $values['comarine_storage_booking'];
		}

		if ( isset( $values['comarine_storage_booking_key'] ) ) {
			$session_data['comarine_storage_booking_key'] = $values['comarine_storage_booking_key'];
		}

		return $session_data;
	}

	/**
	 * Show booking metadata in cart/checkout line item summaries.
	 *
	 * @since 1.0.3
	 *
	 * @param array $item_data Existing item data.
	 * @param array $cart_item Cart item data.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		$booking = $this->get_booking_payload_from_cart_item( $cart_item );
		if ( empty( $booking ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => __( 'Storage unit', 'comarine-storage-booking-with-woocommerce' ),
			'value' => (string) $booking['unit_code'],
		);

		$item_data[] = array(
			'key'   => __( 'Duration', 'comarine-storage-booking-with-woocommerce' ),
			'value' => $this->format_duration_label( (string) $booking['duration_key'] ),
		);

		return $item_data;
	}

	/**
	 * Customize cart line name for booking container product.
	 *
	 * @since 1.0.3
	 *
	 * @param string $name         Product name.
	 * @param array  $cart_item    Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_name( $name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );

		$booking = $this->get_booking_payload_from_cart_item( $cart_item );
		if ( empty( $booking ) ) {
			return $name;
		}

		return sprintf(
			'%1$s (%2$s - %3$s)',
			$name,
			(string) $booking['unit_code'],
			$this->format_duration_label( (string) $booking['duration_key'] )
		);
	}

	/**
	 * Apply locked booking price snapshots to cart items.
	 *
	 * @since 1.0.3
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public function apply_booking_prices_to_cart( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$booking = $this->get_booking_payload_from_cart_item( $cart_item );
			if ( empty( $booking ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$price = isset( $booking['price_snapshot'] ) ? (float) $booking['price_snapshot'] : 0.0;
			if ( $price > 0 && method_exists( $cart_item['data'], 'set_price' ) ) {
				$cart->cart_contents[ $cart_item_key ]['data']->set_price( $price );
			}
		}
	}

	/**
	 * Validate booking locks still exist when cart is checked.
	 *
	 * @since 1.0.3
	 *
	 * @return void
	 */
	public function validate_booking_cart_items() {
		if ( ! $this->wc_cart_is_ready() ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$booking = $this->get_booking_payload_from_cart_item( $cart_item );
			if ( empty( $booking ) ) {
				continue;
			}

			$row = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_booking( (int) $booking['booking_id'] );
			if ( ! $row ) {
				$this->add_wc_notice( __( 'A storage booking in your cart is no longer available. Please reselect the unit.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
				continue;
			}

			if ( in_array( (string) $row->status, array( 'expired', 'cancelled' ), true ) ) {
				$this->add_wc_notice( __( 'A storage booking lock in your cart has expired. Please start the booking again.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
				continue;
			}
		}
	}

	/**
	 * Cancel booking lock when cart item is removed.
	 *
	 * @since 1.0.3
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param WC_Cart $cart          Cart object.
	 * @return void
	 */
	public function handle_cart_item_removed( $cart_item_key, $cart ) {
		if ( ! is_object( $cart ) || empty( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
			return;
		}

		$removed_item = $cart->removed_cart_contents[ $cart_item_key ];
		$booking      = $this->get_booking_payload_from_cart_item( $removed_item );
		if ( empty( $booking ) ) {
			return;
		}

		Comarine_Storage_Booking_With_Woocommerce_Bookings::cancel_booking_lock(
			(int) $booking['booking_id'],
			(string) $booking['lock_token']
		);
	}

	/**
	 * Persist booking references to order line item meta.
	 *
	 * @since 1.0.3
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 * @return void
	 */
	public function add_booking_meta_to_order_line_item( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		$booking = $this->get_booking_payload_from_cart_item( $values );
		if ( empty( $booking ) ) {
			return;
		}

		$item->add_meta_data( '_comarine_booking_id', (int) $booking['booking_id'], true );
		$item->add_meta_data( '_comarine_lock_token', (string) $booking['lock_token'], true );
		$item->add_meta_data( '_comarine_unit_post_id', (int) $booking['unit_post_id'], true );
		$item->add_meta_data( '_comarine_unit_code', (string) $booking['unit_code'], true );
		$item->add_meta_data( '_comarine_duration_key', (string) $booking['duration_key'], true );
	}

	/**
	 * Link booking locks to the WooCommerce order after checkout.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Checkout posted data.
	 * @param WC_Order $order       Order object.
	 * @return void
	 */
	public function link_bookings_to_order( $order_id, $posted_data, $order ) {
		unset( $posted_data );

		if ( ! $order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		$booking_ids = array();

		foreach ( $order->get_items() as $item ) {
			$booking_id = (int) $item->get_meta( '_comarine_booking_id', true );
			$lock_token = (string) $item->get_meta( '_comarine_lock_token', true );

			if ( $booking_id <= 0 || '' === $lock_token ) {
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::assign_order_to_booking( $booking_id, $lock_token, (int) $order_id );
			$booking_ids[] = $booking_id;
		}

		if ( ! empty( $booking_ids ) ) {
			update_post_meta( $order_id, '_comarine_booking_ids', array_values( array_unique( $booking_ids ) ) );
		}
	}

	/**
	 * Handle JCC paid orders (JCC sets order status to completed).
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_order_completed( $order_id, $order = null ) {
		$this->mark_order_bookings_paid( $order_id, $order );
	}

	/**
	 * Compatibility hook for gateways that use processing for paid orders.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_order_processing( $order_id, $order = null ) {
		$this->mark_order_bookings_paid( $order_id, $order );
	}

	/**
	 * Handle failed orders.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_order_failed( $order_id, $order = null ) {
		$this->update_order_bookings_to_status( $order_id, $order, 'failed' );
	}

	/**
	 * Handle cancelled orders.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_order_cancelled( $order_id, $order = null ) {
		$this->update_order_bookings_to_status( $order_id, $order, 'cancelled' );
	}

	/**
	 * Handle refunded orders.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	public function handle_order_refunded( $order_id, $order = null ) {
		$this->update_order_bookings_to_status( $order_id, $order, 'refunded' );
	}

	/**
	 * Render storage units booking list shortcode.
	 *
	 * @since 1.0.3
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_storage_units_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'      => 12,
				'status'     => 'available',
				'show_all'   => '0',
				'checkout'   => '1',
			),
			$atts,
			'comarine_storage_units'
		);

		$limit       = max( 1, min( 100, (int) $atts['limit'] ) );
		$status_only = '1' !== (string) $atts['show_all'];
		$status      = sanitize_key( (string) $atts['status'] );

		$query_args = array(
			'post_type'      => COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		);

		if ( $status_only && $status ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_csu_status',
					'value' => $status,
				),
			);
		}

		$units = get_posts( $query_args );

		ob_start();

		echo '<div class="comarine-storage-units">';
		if ( empty( $units ) ) {
			echo '<p>' . esc_html__( 'No storage units are currently available.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$container_product_id = (int) comarine_storage_booking_with_woocommerce_get_setting( 'booking_container_product_id', 0 );
		if ( $container_product_id <= 0 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Booking is not configured yet: no WooCommerce booking container product has been selected.', 'comarine-storage-booking-with-woocommerce' ) . '</p></div>';
		}

		foreach ( $units as $unit ) {
			$unit_id      = (int) $unit->ID;
			$unit_code    = (string) get_post_meta( $unit_id, '_csu_unit_code', true );
			$unit_status  = (string) get_post_meta( $unit_id, '_csu_status', true );
			$unit_size    = (string) get_post_meta( $unit_id, '_csu_size_m2', true );
			$unit_floor   = (string) get_post_meta( $unit_id, '_csu_floor', true );
			$durations    = $this->get_unit_duration_prices( $unit_id );
			$can_book     = 'available' === ( $unit_status ?: 'available' ) && ! empty( $durations ) && $container_product_id > 0;
			$default_key  = ! empty( $durations ) ? array_key_first( $durations ) : '';

			echo '<div class="comarine-storage-unit-card" style="border:1px solid #ddd;padding:16px;margin:0 0 16px;">';
			echo '<h3>' . esc_html( get_the_title( $unit ) ) . '</h3>';
			echo '<p><strong>' . esc_html__( 'Unit code:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( $unit_code ?: (string) $unit_id ) . '</p>';
			echo '<p><strong>' . esc_html__( 'Status:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( ucfirst( $unit_status ?: 'available' ) ) . '</p>';
			if ( '' !== $unit_size ) {
				echo '<p><strong>' . esc_html__( 'Size (m2):', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( $unit_size ) . '</p>';
			}
			if ( '' !== $unit_floor ) {
				echo '<p><strong>' . esc_html__( 'Floor:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( $unit_floor ) . '</p>';
			}

			if ( ! empty( $durations ) ) {
				echo '<form method="post" action="">';
				wp_nonce_field( 'comarine_storage_start_booking', 'comarine_storage_booking_nonce' );
				echo '<input type="hidden" name="comarine_storage_booking_action" value="start_booking" />';
				echo '<input type="hidden" name="comarine_unit_post_id" value="' . esc_attr( (string) $unit_id ) . '" />';
				echo '<input type="hidden" name="comarine_redirect_to_checkout" value="' . esc_attr( (string) ( '1' === (string) $atts['checkout'] ? '1' : '0' ) ) . '" />';

				echo '<p><strong>' . esc_html__( 'Select duration', 'comarine-storage-booking-with-woocommerce' ) . '</strong></p>';
				foreach ( $durations as $duration_key => $price ) {
					echo '<label style="display:block;margin:0 0 4px;">';
					echo '<input type="radio" name="comarine_duration_key" value="' . esc_attr( $duration_key ) . '" ' . checked( $duration_key, $default_key, false ) . ' ' . disabled( $can_book, false, false ) . ' /> ';
					echo esc_html( $this->format_duration_label( $duration_key ) . ' - ' . $this->format_money( (float) $price ) );
					echo '</label>';
				}

				echo '<p style="margin-top:8px;">';
				echo '<button type="submit" ' . disabled( $can_book, false, false ) . '>' . esc_html__( 'Book Now', 'comarine-storage-booking-with-woocommerce' ) . '</button>';
				echo '</p>';
				echo '</form>';
			} else {
				echo '<p>' . esc_html__( 'Pricing is not configured for this unit yet.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			}

			echo '</div>';
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Mark bookings linked to an order as paid and update unit status.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @return void
	 */
	private function mark_order_bookings_paid( $order_id, $order = null ) {
		$order = $this->get_order_object( $order_id, $order );
		if ( ! $order ) {
			return;
		}

		$paid_unit_status = (string) comarine_storage_booking_with_woocommerce_get_setting( 'paid_unit_status', 'reserved' );

		foreach ( $order->get_items() as $item ) {
			$booking_id = (int) $item->get_meta( '_comarine_booking_id', true );
			if ( $booking_id <= 0 ) {
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_paid( $booking_id, (int) $order->get_id() );

			$unit_post_id = (int) $item->get_meta( '_comarine_unit_post_id', true );
			if ( $unit_post_id > 0 ) {
				update_post_meta( $unit_post_id, '_csu_status', in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved' );
			}
		}
	}

	/**
	 * Update bookings linked to an order to a non-paid terminal status.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 * @param string   $status   Status key.
	 * @return void
	 */
	private function update_order_bookings_to_status( $order_id, $order = null, $status = 'cancelled' ) {
		$order = $this->get_order_object( $order_id, $order );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$booking_id = (int) $item->get_meta( '_comarine_booking_id', true );
			if ( $booking_id <= 0 ) {
				continue;
			}

			if ( 'refunded' === $status ) {
				Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_refunded( $booking_id, (int) $order->get_id() );
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_cancelled( $booking_id, (int) $order->get_id() );
		}
	}

	/**
	 * Get duration price map for a unit.
	 *
	 * @since 1.0.3
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return array<string, float>
	 */
	private function get_unit_duration_prices( $unit_post_id ) {
		$unit_post_id = absint( $unit_post_id );
		$map          = array();

		$candidates = array(
			'monthly' => '_csu_price_monthly',
			'6m'      => '_csu_price_6m',
			'12m'     => '_csu_price_12m',
		);

		foreach ( $candidates as $duration_key => $meta_key ) {
			$raw = get_post_meta( $unit_post_id, $meta_key, true );
			if ( '' === $raw || ! is_numeric( $raw ) ) {
				continue;
			}

			$price = (float) $raw;
			if ( $price <= 0 ) {
				continue;
			}

			$map[ $duration_key ] = $price;
		}

		return $map;
	}

	/**
	 * Remove existing booking cart items for the same unit (avoid duplicate locks in cart).
	 *
	 * @since 1.0.3
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return void
	 */
	private function remove_existing_cart_booking_for_unit( $unit_post_id ) {
		if ( ! $this->wc_cart_is_ready() ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$booking = $this->get_booking_payload_from_cart_item( $cart_item );
			if ( empty( $booking ) ) {
				continue;
			}

			if ( (int) $booking['unit_post_id'] === (int) $unit_post_id ) {
				WC()->cart->remove_cart_item( $cart_item_key );
			}
		}
	}

	/**
	 * Extract booking payload from a cart item.
	 *
	 * @since 1.0.3
	 *
	 * @param array $cart_item Cart item array.
	 * @return array<string, mixed>
	 */
	private function get_booking_payload_from_cart_item( $cart_item ) {
		if ( ! is_array( $cart_item ) || empty( $cart_item['comarine_storage_booking'] ) || ! is_array( $cart_item['comarine_storage_booking'] ) ) {
			return array();
		}

		return $cart_item['comarine_storage_booking'];
	}

	/**
	 * Get a WC order object.
	 *
	 * @since 1.0.3
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Existing order object.
	 * @return WC_Order|null
	 */
	private function get_order_object( $order_id, $order = null ) {
		if ( $order && is_object( $order ) ) {
			return $order;
		}

		if ( function_exists( 'wc_get_order' ) ) {
			return wc_get_order( $order_id );
		}

		return null;
	}

	/**
	 * Check if WooCommerce cart is ready.
	 *
	 * @since 1.0.3
	 *
	 * @return bool
	 */
	private function wc_cart_is_ready() {
		return function_exists( 'WC' ) && WC() && isset( WC()->cart ) && is_object( WC()->cart );
	}

	/**
	 * Add a WooCommerce notice if available.
	 *
	 * @since 1.0.3
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 * @return void
	 */
	private function add_wc_notice( $message, $type = 'success' ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $type );
		}
	}

	/**
	 * Format duration key to label.
	 *
	 * @since 1.0.3
	 *
	 * @param string $duration_key Duration key.
	 * @return string
	 */
	private function format_duration_label( $duration_key ) {
		$labels = array(
			'monthly' => __( 'Monthly', 'comarine-storage-booking-with-woocommerce' ),
			'6m'      => __( '6 months', 'comarine-storage-booking-with-woocommerce' ),
			'12m'     => __( 'Annual', 'comarine-storage-booking-with-woocommerce' ),
		);

		return isset( $labels[ $duration_key ] ) ? $labels[ $duration_key ] : $duration_key;
	}

	/**
	 * Format a money value for display.
	 *
	 * @since 1.0.3
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_money( $amount ) {
		$currency = (string) comarine_storage_booking_with_woocommerce_get_setting( 'currency', 'EUR' );

		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $amount ) );
		}

		return number_format_i18n( $amount, 2 ) . ' ' . $currency;
	}
}
