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
		add_shortcode( 'comarine_storage_units_latest', array( $this, 'render_latest_storage_units_shortcode' ) );
	}

	/**
	 * AJAX: Get daily availability for a storage unit date range (for frontend datepicker UI).
	 *
	 * @since 1.0.32
	 *
	 * @return void
	 */
	public function ajax_get_daily_unit_availability() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'comarine_storage_booking_public_availability' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid availability request.', 'comarine-storage-booking-with-woocommerce' ),
				),
				403
			);
		}

		$unit_post_id = isset( $_REQUEST['unit_post_id'] ) ? absint( wp_unslash( $_REQUEST['unit_post_id'] ) ) : 0;
		if ( $unit_post_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid storage unit.', 'comarine-storage-booking-with-woocommerce' ),
				),
				400
			);
		}

		$unit = get_post( $unit_post_id );
		if ( ! $unit || COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE !== $unit->post_type || 'publish' !== $unit->post_status ) {
			wp_send_json_error(
				array(
					'message' => __( 'Storage unit not found.', 'comarine-storage-booking-with-woocommerce' ),
				),
				404
			);
		}

		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$today_dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', wp_date( 'Y-m-d', null, $site_timezone ), $site_timezone );
		if ( ! $today_dt instanceof DateTimeImmutable ) {
			$today_dt = new DateTimeImmutable( 'today', $site_timezone );
		}

		$start_date = isset( $_REQUEST['start_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['start_date'] ) ) : $today_dt->format( 'Y-m-d' );
		$end_date   = isset( $_REQUEST['end_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['end_date'] ) ) : '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			$start_date = $today_dt->format( 'Y-m-d' );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$end_date = $today_dt->modify( '+540 days' )->format( 'Y-m-d' );
		}

		if ( class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' )
			&& method_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings', 'get_unit_daily_availability_map' )
		) {
			$availability = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_unit_daily_availability_map( $unit_post_id, $start_date, $end_date );
		} else {
			$availability = array(
				'unit_post_id'        => $unit_post_id,
				'is_capacity_managed' => false,
				'capacity_m2'         => 0.0,
				'range_start_date'    => $start_date,
				'range_end_date'      => $end_date,
				'days'                => array(),
			);
		}

		$unit_status = sanitize_key( (string) get_post_meta( $unit_post_id, '_csu_status', true ) );
		if ( '' === $unit_status ) {
			$unit_status = 'available';
		}

		$manual_block_statuses = array( 'maintenance', 'archived' );
		$blocked_by_status     = in_array( $unit_status, $manual_block_statuses, true );

		wp_send_json_success(
			array(
				'unit_post_id'       => $unit_post_id,
				'unit_status'        => $unit_status,
				'blocked_by_status'  => $blocked_by_status,
				'availability'       => $availability,
			)
		);
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
		$requested_start_date = isset( $_POST['comarine_start_date'] ) ? $this->sanitize_booking_start_date( wp_unslash( $_POST['comarine_start_date'] ) ) : '';
		$requested_end_date   = isset( $_POST['comarine_end_date'] ) ? $this->sanitize_booking_end_date( wp_unslash( $_POST['comarine_end_date'] ) ) : '';
		$requested_area_m2 = isset( $_POST['comarine_requested_area_m2'] ) ? $this->sanitize_requested_area_m2( wp_unslash( $_POST['comarine_requested_area_m2'] ) ) : 0.0;

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

		$start_date_validation = $this->validate_booking_start_date( $requested_start_date );
		if ( is_wp_error( $start_date_validation ) ) {
			$this->add_wc_notice( $start_date_validation->get_error_message(), 'error' );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$booking_day_count = 0;
		if ( 'daily' === $duration_key ) {
			$date_range_validation = $this->validate_booking_date_range( $requested_start_date, $requested_end_date );
			if ( is_wp_error( $date_range_validation ) ) {
				$this->add_wc_notice( $date_range_validation->get_error_message(), 'error' );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$requested_start_date = (string) $date_range_validation['start_date'];
			$requested_end_date   = (string) $date_range_validation['end_date'];
			$booking_day_count    = (int) $date_range_validation['day_count'];
		}

		$availability_range_end_date = '';
		if ( 'daily' === $duration_key ) {
			$availability_range_end_date = $requested_end_date;
		} elseif ( class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' )
			&& isset( Comarine_Storage_Booking_With_Woocommerce_Bookings::DURATION_MONTHS[ $duration_key ] )
		) {
			$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$start_dt_for_overlap = DateTimeImmutable::createFromFormat( '!Y-m-d', $requested_start_date, $site_timezone );
			if ( $start_dt_for_overlap instanceof DateTimeImmutable ) {
				$end_dt_for_overlap = $start_dt_for_overlap->modify( '+' . (int) Comarine_Storage_Booking_With_Woocommerce_Bookings::DURATION_MONTHS[ $duration_key ] . ' months' );
				if ( $end_dt_for_overlap instanceof DateTimeImmutable ) {
					$availability_range_end_date = $end_dt_for_overlap->format( 'Y-m-d' );
				}
			}
		}

		$capacity_snapshot = $this->get_unit_capacity_booking_snapshot( $unit_post_id, $requested_start_date, $availability_range_end_date );
		$unit_capacity_m2  = isset( $capacity_snapshot['capacity_m2'] ) ? (float) $capacity_snapshot['capacity_m2'] : 0.0;
		if ( ! empty( $capacity_snapshot['is_capacity_managed'] ) ) {
			if ( $requested_area_m2 <= 0 ) {
				$this->add_wc_notice( __( 'Please enter how many square meters you want to book.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			if ( $requested_area_m2 > $unit_capacity_m2 ) {
				$this->add_wc_notice(
					sprintf(
						/* translators: %s: unit capacity in square meters */
						__( 'Requested area exceeds the unit capacity (%s m2).', 'comarine-storage-booking-with-woocommerce' ),
						$this->format_area_m2( $unit_capacity_m2 )
					),
					'error'
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$remaining_capacity_m2 = isset( $capacity_snapshot['remaining_m2'] ) ? (float) $capacity_snapshot['remaining_m2'] : $unit_capacity_m2;
			if ( $remaining_capacity_m2 + 0.0001 < $requested_area_m2 ) {
				$this->add_wc_notice(
					sprintf(
						/* translators: %s: remaining area in square meters */
						__( 'Only %s m2 is currently available for this unit.', 'comarine-storage-booking-with-woocommerce' ),
						$this->format_area_m2( max( 0, $remaining_capacity_m2 ) )
					),
					'error'
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		$configured_addons = $this->get_configured_booking_addons();
		$selected_addons   = $this->get_selected_booking_addons_from_request( $configured_addons );
		$selected_full_unit_price = (float) $price_map[ $duration_key ];
		if ( 'daily' === $duration_key ) {
			$selected_full_unit_price = $this->calculate_daily_booking_total( $selected_full_unit_price, $booking_day_count );
		}

		$base_price        = ! empty( $capacity_snapshot['is_capacity_managed'] )
			? $this->calculate_capacity_prorated_price( $selected_full_unit_price, $requested_area_m2, $unit_capacity_m2 )
			: $selected_full_unit_price;
		$addons_total      = $this->get_selected_booking_addons_total( $selected_addons );
		$total_price       = $base_price + $addons_total;

		$this->remove_existing_cart_booking_for_unit( $unit_post_id );

		$lock_result = Comarine_Storage_Booking_With_Woocommerce_Bookings::create_locked_booking(
			array(
				'unit_post_id'       => $unit_post_id,
				'unit_code'          => (string) get_post_meta( $unit_post_id, '_csu_unit_code', true ) ?: $unit->post_title,
				'duration_key'       => $duration_key,
				'start_date'         => $requested_start_date,
				'end_date'           => 'daily' === $duration_key ? $requested_end_date : '',
				'price_total'        => $total_price,
				'currency'           => isset( $settings['currency'] ) ? (string) $settings['currency'] : 'EUR',
				'user_id'            => get_current_user_id(),
				'lock_ttl_minutes'   => isset( $settings['lock_ttl_minutes'] ) ? (int) $settings['lock_ttl_minutes'] : 15,
				'requested_area_m2'  => $requested_area_m2,
				'unit_capacity_m2'   => $unit_capacity_m2,
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
				'start_date'      => isset( $lock_result['start_date'] ) ? (string) $lock_result['start_date'] : $requested_start_date,
				'end_date'        => isset( $lock_result['end_date'] ) ? (string) $lock_result['end_date'] : ( 'daily' === $duration_key ? $requested_end_date : '' ),
				'start_ts'        => isset( $lock_result['start_ts'] ) ? (string) $lock_result['start_ts'] : '',
				'end_ts'          => isset( $lock_result['end_ts'] ) ? (string) $lock_result['end_ts'] : '',
				'booking_day_count' => (int) $booking_day_count,
				'requested_area_m2' => (float) $requested_area_m2,
				'unit_capacity_m2'  => (float) $unit_capacity_m2,
				'booking_id'      => (int) $lock_result['booking_id'],
				'lock_token'      => (string) $lock_result['lock_token'],
				'lock_expires_ts' => (string) $lock_result['lock_expires_ts'],
				'base_price_snapshot' => $base_price,
				'full_unit_base_price_snapshot' => $selected_full_unit_price,
				'full_unit_daily_price_snapshot' => 'daily' === $duration_key ? (float) $price_map[ $duration_key ] : 0.0,
				'addons'          => $selected_addons,
				'addons_total'    => $addons_total,
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

		if ( ! empty( $booking['start_date'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Start date', 'comarine-storage-booking-with-woocommerce' ),
				'value' => $this->format_booking_start_date_for_display( (string) $booking['start_date'] ),
			);
		}
		if ( ! empty( $booking['end_date'] ) ) {
			$item_data[] = array(
				'key'   => __( 'End date', 'comarine-storage-booking-with-woocommerce' ),
				'value' => $this->format_booking_start_date_for_display( (string) $booking['end_date'] ),
			);
		}
		if ( isset( $booking['booking_day_count'] ) && (int) $booking['booking_day_count'] > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Booked days', 'comarine-storage-booking-with-woocommerce' ),
				'value' => (string) (int) $booking['booking_day_count'],
			);
		}

		if ( isset( $booking['requested_area_m2'] ) && is_numeric( $booking['requested_area_m2'] ) && (float) $booking['requested_area_m2'] > 0 ) {
			$area_label = $this->format_area_m2( (float) $booking['requested_area_m2'] ) . ' m2';
			if ( isset( $booking['unit_capacity_m2'] ) && is_numeric( $booking['unit_capacity_m2'] ) && (float) $booking['unit_capacity_m2'] > 0 ) {
				$area_label .= ' / ' . $this->format_area_m2( (float) $booking['unit_capacity_m2'] ) . ' m2';
			}

			$item_data[] = array(
				'key'   => __( 'Booked area', 'comarine-storage-booking-with-woocommerce' ),
				'value' => $area_label,
			);
		}

		if ( ! empty( $booking['addons'] ) && is_array( $booking['addons'] ) ) {
			$addon_labels = array();
			foreach ( $booking['addons'] as $addon ) {
				if ( ! is_array( $addon ) ) {
					continue;
				}

				$label = isset( $addon['label'] ) ? (string) $addon['label'] : '';
				$price = isset( $addon['price'] ) ? (float) $addon['price'] : 0.0;
				if ( '' === $label ) {
					continue;
				}

				$addon_labels[] = $label . ' (' . $this->format_money( $price ) . ')';
			}

			if ( ! empty( $addon_labels ) ) {
				$item_data[] = array(
					'key'   => __( 'Add-ons', 'comarine-storage-booking-with-woocommerce' ),
					'value' => implode( ', ', $addon_labels ),
				);
			}
		}

		if ( isset( $booking['addons_total'] ) && (float) $booking['addons_total'] > 0 ) {
			$item_data[] = array(
				'key'   => __( 'Add-ons total', 'comarine-storage-booking-with-woocommerce' ),
				'value' => $this->format_money( (float) $booking['addons_total'] ),
			);
		}

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

		$suffix = (string) $booking['unit_code'] . ' - ' . $this->format_duration_label( (string) $booking['duration_key'] );
		if ( ! empty( $booking['start_date'] ) ) {
			$suffix .= ' - ' . $this->format_booking_start_date_for_display( (string) $booking['start_date'] );
			if ( ! empty( $booking['end_date'] ) ) {
				$suffix .= ' to ' . $this->format_booking_start_date_for_display( (string) $booking['end_date'] );
			}
		}
		if ( isset( $booking['requested_area_m2'] ) && is_numeric( $booking['requested_area_m2'] ) && (float) $booking['requested_area_m2'] > 0 ) {
			$suffix .= ' - ' . $this->format_area_m2( (float) $booking['requested_area_m2'] ) . ' m2';
		}

		return sprintf( '%1$s (%2$s)', $name, $suffix );
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

		$ttl_minutes       = (int) comarine_storage_booking_with_woocommerce_get_setting( 'lock_ttl_minutes', 15 );
		$invalid_item_keys = array();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$booking = $this->get_booking_payload_from_cart_item( $cart_item );
			if ( empty( $booking ) ) {
				continue;
			}

			$validation = Comarine_Storage_Booking_With_Woocommerce_Bookings::validate_booking_lock(
				(int) $booking['booking_id'],
				(string) $booking['lock_token'],
				false
			);

			if ( is_wp_error( $validation ) ) {
				$this->add_wc_notice( __( 'A storage booking in your cart is no longer available. Please reselect the unit.', 'comarine-storage-booking-with-woocommerce' ), 'error' );
				$invalid_item_keys[] = $cart_item_key;
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::refresh_booking_lock(
				(int) $booking['booking_id'],
				(string) $booking['lock_token'],
				$ttl_minutes
			);
		}

		if ( empty( $invalid_item_keys ) ) {
			return;
		}

		foreach ( array_unique( $invalid_item_keys ) as $invalid_item_key ) {
			WC()->cart->remove_cart_item( $invalid_item_key );
		}
	}

	/**
	 * Validate booking locks during checkout before order creation.
	 *
	 * @since 1.0.4
	 *
	 * @param array    $posted_data Checkout posted data.
	 * @param WP_Error $errors      Checkout validation errors.
	 * @return void
	 */
	public function validate_checkout_booking_locks( $posted_data, $errors ) {
		unset( $posted_data );

		if ( ! $this->wc_cart_is_ready() || ! is_wp_error( $errors ) ) {
			return;
		}

		$ttl_minutes = (int) comarine_storage_booking_with_woocommerce_get_setting( 'lock_ttl_minutes', 15 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$booking = $this->get_booking_payload_from_cart_item( $cart_item );
			if ( empty( $booking ) ) {
				continue;
			}

			$validation = Comarine_Storage_Booking_With_Woocommerce_Bookings::validate_booking_lock(
				(int) $booking['booking_id'],
				(string) $booking['lock_token'],
				false
			);

			if ( is_wp_error( $validation ) ) {
				$errors->add(
					'comarine_booking_lock_invalid',
					__( 'One of your storage booking locks expired or became invalid. Please review your cart and try again.', 'comarine-storage-booking-with-woocommerce' )
				);

				WC()->cart->remove_cart_item( $cart_item_key );
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::refresh_booking_lock(
				(int) $booking['booking_id'],
				(string) $booking['lock_token'],
				$ttl_minutes
			);
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
		if ( ! empty( $booking['start_date'] ) ) {
			$item->add_meta_data( '_comarine_start_date', (string) $booking['start_date'], true );
			$item->add_meta_data(
				__( 'Start date', 'comarine-storage-booking-with-woocommerce' ),
				$this->format_booking_start_date_for_display( (string) $booking['start_date'] ),
				true
			);
		}
		if ( ! empty( $booking['end_date'] ) ) {
			$item->add_meta_data( '_comarine_end_date', (string) $booking['end_date'], true );
			$item->add_meta_data(
				__( 'End date', 'comarine-storage-booking-with-woocommerce' ),
				$this->format_booking_start_date_for_display( (string) $booking['end_date'] ),
				true
			);
		}
		if ( isset( $booking['booking_day_count'] ) && (int) $booking['booking_day_count'] > 0 ) {
			$item->add_meta_data( '_comarine_booking_day_count', (int) $booking['booking_day_count'], true );
			$item->add_meta_data( __( 'Booked days', 'comarine-storage-booking-with-woocommerce' ), (int) $booking['booking_day_count'], true );
		}
		if ( ! empty( $booking['start_ts'] ) ) {
			$item->add_meta_data( '_comarine_start_ts', (string) $booking['start_ts'], true );
		}
		if ( ! empty( $booking['end_ts'] ) ) {
			$item->add_meta_data( '_comarine_end_ts', (string) $booking['end_ts'], true );
		}
		if ( isset( $booking['requested_area_m2'] ) && is_numeric( $booking['requested_area_m2'] ) && (float) $booking['requested_area_m2'] > 0 ) {
			$item->add_meta_data( '_comarine_requested_area_m2', (float) $booking['requested_area_m2'], true );
			$item->add_meta_data( '_comarine_unit_capacity_m2', isset( $booking['unit_capacity_m2'] ) && is_numeric( $booking['unit_capacity_m2'] ) ? (float) $booking['unit_capacity_m2'] : 0.0, true );
			$item->add_meta_data(
				__( 'Booked area', 'comarine-storage-booking-with-woocommerce' ),
				$this->format_area_m2( (float) $booking['requested_area_m2'] ) . ' m2',
				true
			);
		}

		if ( isset( $booking['addons'] ) && is_array( $booking['addons'] ) ) {
			$item->add_meta_data( '_comarine_addons', wp_json_encode( $booking['addons'] ), true );

			$addon_labels = array();
			foreach ( $booking['addons'] as $addon ) {
				if ( ! is_array( $addon ) || empty( $addon['label'] ) ) {
					continue;
				}

				$addon_labels[] = (string) $addon['label'];
			}

			if ( ! empty( $addon_labels ) ) {
				$item->add_meta_data( __( 'Storage add-ons', 'comarine-storage-booking-with-woocommerce' ), implode( ', ', $addon_labels ), true );
			}
		}
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

			$assigned = Comarine_Storage_Booking_With_Woocommerce_Bookings::assign_order_to_booking( $booking_id, $lock_token, (int) $order_id );
			if ( ! $assigned ) {
				$order->add_order_note(
					sprintf(
						/* translators: %d: booking ID */
						__( 'CoMarine booking %d could not be linked to the order (invalid or expired lock).', 'comarine-storage-booking-with-woocommerce' ),
						$booking_id
					)
				);
				continue;
			}

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
				'limit'         => 12,
				'status'        => 'available',
				'show_all'      => '0',
				'checkout'      => '1',
				'show_filters'  => '1',
				'sort'          => 'default',
				'include_ids'   => '',
				'wrapper_class' => '',
				'card_action'   => 'book',
			),
			$atts,
			'comarine_storage_units'
		);

		$limit          = max( 1, min( 100, (int) $atts['limit'] ) );
		$status_only    = '1' !== (string) $atts['show_all'];
		$show_filters   = '0' !== (string) $atts['show_filters'];
		$default_status = sanitize_key( (string) $atts['status'] );
		$sort_mode      = sanitize_key( (string) $atts['sort'] );
		$raw_include_ids = trim( (string) $atts['include_ids'] );
		$wrapper_class  = sanitize_html_class( (string) $atts['wrapper_class'] );
		$card_action    = sanitize_key( (string) $atts['card_action'] );
		$include_ids    = array();

		if ( '' !== $raw_include_ids ) {
			$parts = preg_split( '/[\s,]+/', $raw_include_ids );
			if ( is_array( $parts ) ) {
				foreach ( $parts as $part ) {
					$maybe_id = absint( $part );
					if ( $maybe_id > 0 ) {
						$include_ids[] = $maybe_id;
					}
				}
			}

			$include_ids = array_values( array_unique( $include_ids ) );
		}

		if ( ! in_array( $card_action, array( 'book', 'single' ), true ) ) {
			$card_action = 'book';
		}

		if ( ! in_array( $sort_mode, array( 'default', 'latest' ), true ) ) {
			$sort_mode = 'default';
		}

		if ( $show_filters ) {
			$filters = $this->get_storage_units_frontend_filters_from_request( $status_only ? $default_status : '' );
		} else {
			$filters = array(
				'query'         => '',
				'status'        => $status_only ? $default_status : 'all',
				'floor'         => '',
				'min_size'      => null,
				'max_size'      => null,
				'max_price'     => null,
				'bookable_only' => false,
			);
		}

		$status = $status_only ? $default_status : $filters['status'];

		$query_args = array(
			'post_type'      => COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( ! empty( $include_ids ) ) {
			$query_args['post__in']       = $include_ids;
			$query_args['posts_per_page'] = count( $include_ids );
			$query_args['orderby']        = 'post__in';
		} elseif ( 'latest' === $sort_mode ) {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		} else {
			$query_args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
		}

		if ( $status && 'all' !== $status ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_csu_status',
					'value' => $status,
				),
			);
		}

		$units = get_posts( $query_args );

		ob_start();

		$container_classes = 'comarine-storage-units';
		if ( '' !== $wrapper_class ) {
			$container_classes .= ' ' . $wrapper_class;
		}

		echo '<div class="' . esc_attr( $container_classes ) . '">';
		$container_product_id = (int) comarine_storage_booking_with_woocommerce_get_setting( 'booking_container_product_id', 0 );
		$configured_addons    = $this->get_configured_booking_addons();
		if ( 'book' === $card_action && $container_product_id <= 0 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Booking is not configured yet: no WooCommerce booking container product has been selected.', 'comarine-storage-booking-with-woocommerce' ) . '</p></div>';
		}

		if ( $show_filters ) {
			$floor_options = $this->get_storage_units_floor_options( $units );
			$this->render_storage_units_filter_form( $filters, $status_only, $default_status, $floor_options );
		}

		if ( empty( $units ) ) {
			echo '<p class="comarine-storage-units-empty">' . esc_html__( 'No storage units are currently available.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$cards = array();

		foreach ( $units as $unit ) {
			$unit_id      = (int) $unit->ID;
			$unit_code    = (string) get_post_meta( $unit_id, '_csu_unit_code', true );
			$unit_status  = (string) get_post_meta( $unit_id, '_csu_status', true );
			$unit_size    = (string) get_post_meta( $unit_id, '_csu_size_m2', true );
			$unit_dimensions = (string) get_post_meta( $unit_id, '_csu_dimensions', true );
			$unit_floor   = (string) get_post_meta( $unit_id, '_csu_floor', true );
			$durations    = $this->get_unit_duration_prices( $unit_id );
			$uses_daily_date_range = isset( $durations['daily'] ) && is_numeric( $durations['daily'] ) && (float) $durations['daily'] > 0;
			$normalized_status = $unit_status ? sanitize_key( $unit_status ) : 'available';
			$capacity_snapshot = $this->get_unit_capacity_booking_snapshot( $unit_id );
			$has_conflict = class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' )
				? ( ! empty( $capacity_snapshot['is_capacity_managed'] )
					? ! empty( $capacity_snapshot['is_full'] )
					: Comarine_Storage_Booking_With_Woocommerce_Bookings::has_conflicting_booking( $unit_id ) )
				: false;
			$can_book     = 'available' === $normalized_status && ! empty( $durations ) && $container_product_id > 0;
			$default_key  = ! empty( $durations ) ? array_key_first( $durations ) : '';
			$unit_size_float = is_numeric( $unit_size ) ? (float) $unit_size : null;
			$from_price    = ! empty( $durations ) ? (float) min( $durations ) : 0.0;
			$features      = $this->get_unit_features_list( $unit_id );
			$excerpt       = has_excerpt( $unit_id ) ? get_the_excerpt( $unit_id ) : wp_trim_words( wp_strip_all_tags( (string) $unit->post_content ), 24 );
			$unavailable_reason = $this->get_unit_unavailable_reason( $normalized_status, $has_conflict, $durations, $container_product_id );

			$unit_data = array(
				'id'                => $unit_id,
				'object'            => $unit,
				'unit_code'         => $unit_code ?: (string) $unit_id,
				'status'            => $normalized_status ?: 'available',
				'size_raw'          => $unit_size,
				'size_value'        => $unit_size_float,
				'capacity_snapshot' => $capacity_snapshot,
				'dimensions'        => $unit_dimensions,
				'floor'             => $unit_floor,
				'durations'         => $durations,
				'default_duration'  => $default_key,
				'can_book'          => $can_book,
				'has_conflict'      => $has_conflict,
				'from_price'        => $from_price,
				'features'          => $features,
				'excerpt'           => $excerpt,
				'unavailable_reason'=> $unavailable_reason,
			);

			if ( ! $this->storage_unit_matches_frontend_filters( $unit_data, $filters ) ) {
				continue;
			}

			$cards[] = $unit_data;
		}

		if ( empty( $cards ) ) {
			$message = $show_filters
				? __( 'No storage units matched the current filters.', 'comarine-storage-booking-with-woocommerce' )
				: __( 'No storage units are currently available.', 'comarine-storage-booking-with-woocommerce' );
			echo '<p class="comarine-storage-units-empty">' . esc_html( $message ) . '</p>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$cards = array_slice( $cards, 0, $limit );
		echo '<div class="comarine-storage-units-grid">';

		foreach ( $cards as $card ) {
			$unit      = $card['object'];
			$unit_id    = (int) $card['id'];
			$durations = $card['durations'];
			$default_key = (string) $card['default_duration'];
			$can_book = ! empty( $card['can_book'] );
			$has_conflict = ! empty( $card['has_conflict'] );
			$capacity_snapshot = isset( $card['capacity_snapshot'] ) && is_array( $card['capacity_snapshot'] ) ? $card['capacity_snapshot'] : array();
			$is_capacity_managed = ! empty( $capacity_snapshot['is_capacity_managed'] );
			$capacity_total_m2 = $is_capacity_managed && isset( $capacity_snapshot['capacity_m2'] ) ? (float) $capacity_snapshot['capacity_m2'] : 0.0;
			$capacity_remaining_m2 = $is_capacity_managed && isset( $capacity_snapshot['remaining_m2'] ) ? (float) $capacity_snapshot['remaining_m2'] : 0.0;
			$capacity_reserved_m2 = $is_capacity_managed && isset( $capacity_snapshot['reserved_m2'] ) ? (float) $capacity_snapshot['reserved_m2'] : 0.0;
			$default_requested_area_m2 = $is_capacity_managed ? max( 0.01, round( $capacity_remaining_m2, 2 ) ) : 0.0;
			$default_start_date = $this->get_default_booking_start_date_input_value();
			$default_end_date   = $this->get_default_booking_end_date_input_value( $default_start_date );
			$unit_permalink     = get_permalink( $unit_id );
			if ( $uses_daily_date_range ) {
				$default_key = 'daily';
			}
			$price_preview_payload = array(
				'prices'           => array_map( 'floatval', is_array( $durations ) ? $durations : array() ),
				'currency'         => (string) comarine_storage_booking_with_woocommerce_get_setting( 'currency', 'EUR' ),
				'unit_capacity_m2' => $capacity_total_m2,
				'uses_daily'       => $uses_daily_date_range,
			);

			echo '<article class="comarine-storage-unit-card comarine-status-' . esc_attr( (string) $card['status'] ) . '">';
			echo '<div class="comarine-storage-unit-card__header">';
			echo '<div>';
			echo '<h3 class="comarine-storage-unit-card__title">' . esc_html( get_the_title( $unit ) ) . '</h3>';
			echo '<p class="comarine-storage-unit-card__code">' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( (string) $card['unit_code'] ) . '</p>';
			echo '</div>';
			echo '<span class="comarine-storage-unit-card__status-badge">' . esc_html( $this->format_storage_unit_status_label( (string) $card['status'] ) ) . '</span>';
			echo '</div>';

			if ( ! empty( $card['excerpt'] ) ) {
				echo '<p class="comarine-storage-unit-card__excerpt">' . esc_html( (string) $card['excerpt'] ) . '</p>';
			}

			echo '<div class="comarine-storage-unit-card__meta">';
			if ( '' !== (string) $card['size_raw'] ) {
				echo '<span class="comarine-storage-unit-card__chip">' . esc_html( (string) $card['size_raw'] ) . ' m2</span>';
			}
			if ( $is_capacity_managed ) {
				echo '<span class="comarine-storage-unit-card__chip">' . esc_html__( 'Available', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( $this->format_area_m2( max( 0, $capacity_remaining_m2 ) ) ) . ' m2</span>';
				echo '<span class="comarine-storage-unit-card__chip">' . esc_html__( 'Reserved', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( $this->format_area_m2( max( 0, $capacity_reserved_m2 ) ) ) . ' m2</span>';
			}
			if ( '' !== (string) $card['dimensions'] ) {
				echo '<span class="comarine-storage-unit-card__chip">' . esc_html__( 'Dimensions', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( (string) $card['dimensions'] ) . '</span>';
			}
			if ( '' !== (string) $card['floor'] ) {
				echo '<span class="comarine-storage-unit-card__chip">' . esc_html__( 'Floor', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( (string) $card['floor'] ) . '</span>';
			}
			echo '</div>';

			if ( ! empty( $card['features'] ) ) {
				echo '<ul class="comarine-storage-unit-card__features">';
				foreach ( $card['features'] as $feature ) {
					echo '<li>' . esc_html( (string) $feature ) . '</li>';
				}
				echo '</ul>';
			}

			if ( ! empty( $durations ) ) {
				echo '<div class="comarine-storage-unit-card__pricing">';
				echo '<p class="comarine-storage-unit-card__from-price"><strong>' . esc_html__( 'From', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( $this->format_money( (float) $card['from_price'] ) ) . '</p>';
				if ( $uses_daily_date_range ) {
					echo '<p class="comarine-storage-unit-card__pricing-note">' . esc_html__( 'Daily price is available for this unit. Select area (m2) and start/end dates to calculate your booking total.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
				} elseif ( $is_capacity_managed && $capacity_total_m2 > 0 ) {
					echo '<p class="comarine-storage-unit-card__pricing-note">' . esc_html__( 'Prices shown are for the full unit. Checkout price is prorated by the m2 you book.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
				}
				echo '<ul class="comarine-storage-unit-card__duration-list">';
				foreach ( $durations as $duration_key => $price ) {
					echo '<li><span>' . esc_html( $this->format_duration_label( $duration_key ) ) . '</span><strong data-comarine-price-key="' . esc_attr( $duration_key ) . '" data-comarine-price-full="' . esc_attr( (string) (float) $price ) . '">' . esc_html( $this->format_money( (float) $price ) ) . '</strong></li>';
				}
				echo '</ul>';
				echo '</div>';

				if ( 'single' === $card_action ) {
					if ( $unit_permalink ) {
						echo '<p class="comarine-storage-unit-card__availability-note is-available">' . esc_html__( 'View the unit page for full details, gallery photos, and booking options.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
						echo '<a class="comarine-storage-unit-card__book-button" href="' . esc_url( $unit_permalink ) . '">' . esc_html__( 'View Unit Details', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
					} else {
						echo '<p class="comarine-storage-unit-card__availability-note is-unavailable">' . esc_html__( 'Unit page link is not available right now.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
					}
				} else {
					echo '<form class="comarine-storage-unit-card__booking-form" method="post" action="" data-comarine-price-preview="' . esc_attr( wp_json_encode( $price_preview_payload ) ) . '">';
					wp_nonce_field( 'comarine_storage_start_booking', 'comarine_storage_booking_nonce' );
					echo '<input type="hidden" name="comarine_storage_booking_action" value="start_booking" />';
					echo '<input type="hidden" name="comarine_unit_post_id" value="' . esc_attr( (string) $unit_id ) . '" />';
					echo '<input type="hidden" name="comarine_redirect_to_checkout" value="' . esc_attr( (string) ( '1' === (string) $atts['checkout'] ? '1' : '0' ) ) . '" />';

					if ( $is_capacity_managed && $capacity_total_m2 > 0 ) {
						echo '<label class="comarine-storage-unit-card__area-field is-primary">';
						echo '<span>' . esc_html__( 'Area required for storage (m2)', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
						echo '<input type="number" step="0.01" min="0.01" max="' . esc_attr( (string) max( 0.01, round( $capacity_remaining_m2, 2 ) ) ) . '" name="comarine_requested_area_m2" value="' . esc_attr( $this->format_decimal_input( $default_requested_area_m2 ) ) . '" ' . disabled( $can_book, false, false ) . ' required />';
						echo '<small>' . esc_html( sprintf( __( 'Required: select the area you need for storage first. Up to %s m2 is currently available.', 'comarine-storage-booking-with-woocommerce' ), $this->format_area_m2( max( 0, $capacity_remaining_m2 ) ) ) ) . '</small>';
						echo '</label>';
					}

					echo '<label class="comarine-storage-unit-card__start-date-field">';
					echo '<span>' . esc_html__( 'Start date', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
					echo '<input type="date" name="comarine_start_date" min="' . esc_attr( $default_start_date ) . '" value="' . esc_attr( $default_start_date ) . '" ' . disabled( $can_book, false, false ) . ' required />';
					echo '<small>' . esc_html__( 'Select when your storage booking should begin.', 'comarine-storage-booking-with-woocommerce' ) . '</small>';
					echo '</label>';

					if ( $uses_daily_date_range ) {
						echo '<input type="hidden" name="comarine_duration_key" value="daily" />';
						echo '<label class="comarine-storage-unit-card__start-date-field comarine-storage-unit-card__end-date-field">';
						echo '<span>' . esc_html__( 'End date', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
						echo '<input type="date" name="comarine_end_date" min="' . esc_attr( $default_end_date ) . '" value="' . esc_attr( $default_end_date ) . '" ' . disabled( $can_book, false, false ) . ' required />';
						echo '<small>' . esc_html__( 'Select the date your booking ends (must be after the start date).', 'comarine-storage-booking-with-woocommerce' ) . '</small>';
						echo '</label>';
					} else {
						echo '<fieldset class="comarine-storage-unit-card__duration-fieldset">';
						echo '<legend>' . esc_html__( 'Select duration', 'comarine-storage-booking-with-woocommerce' ) . '</legend>';
						foreach ( $durations as $duration_key => $price ) {
							echo '<label class="comarine-storage-unit-card__duration-option">';
							echo '<input type="radio" name="comarine_duration_key" value="' . esc_attr( $duration_key ) . '" ' . checked( $duration_key, $default_key, false ) . ' ' . disabled( $can_book, false, false ) . ' /> ';
							echo '<span class="comarine-storage-unit-card__duration-label">' . esc_html( $this->format_duration_label( $duration_key ) ) . '</span>';
							echo '<span class="comarine-storage-unit-card__duration-price" data-comarine-price-key="' . esc_attr( $duration_key ) . '" data-comarine-price-full="' . esc_attr( (string) (float) $price ) . '">' . esc_html( $this->format_money( (float) $price ) ) . '</span>';
							echo '</label>';
						}
						echo '</fieldset>';
					}

					echo '<div class="comarine-storage-unit-card__price-estimate" data-comarine-price-estimate>';
					echo '<strong>' . esc_html__( 'Estimated total', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ';
					echo '<span class="comarine-storage-unit-card__price-estimate-value" data-comarine-price-estimate-value>' . esc_html__( 'Select booking details', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
					echo '<small class="comarine-storage-unit-card__price-estimate-note" data-comarine-price-estimate-note>' . esc_html__( 'Estimate updates when you change area and dates/duration. Add-ons are added separately.', 'comarine-storage-booking-with-woocommerce' ) . '</small>';
					echo '</div>';

					if ( ! empty( $configured_addons ) ) {
						echo '<fieldset class="comarine-storage-unit-card__addons-fieldset">';
						echo '<legend>' . esc_html__( 'Optional add-ons', 'comarine-storage-booking-with-woocommerce' ) . '</legend>';
						foreach ( $configured_addons as $addon ) {
							if ( ! is_array( $addon ) ) {
								continue;
							}

							$addon_key   = isset( $addon['key'] ) ? sanitize_key( (string) $addon['key'] ) : '';
							$addon_label = isset( $addon['label'] ) ? (string) $addon['label'] : '';
							$addon_price = isset( $addon['price'] ) ? (float) $addon['price'] : 0.0;
							if ( '' === $addon_key || '' === $addon_label ) {
								continue;
							}

							echo '<label class="comarine-storage-unit-card__addon-option">';
							echo '<input type="checkbox" name="comarine_addons[]" value="' . esc_attr( $addon_key ) . '" ' . disabled( $can_book, false, false ) . ' /> ';
							echo '<span class="comarine-storage-unit-card__addon-label">' . esc_html( $addon_label ) . '</span>';
							echo '<span class="comarine-storage-unit-card__addon-price">' . esc_html( $this->format_money( $addon_price ) ) . '</span>';
							echo '</label>';
						}
						echo '</fieldset>';
					}

					if ( ! $can_book && '' !== (string) $card['unavailable_reason'] ) {
						echo '<p class="comarine-storage-unit-card__availability-note is-unavailable">' . esc_html( (string) $card['unavailable_reason'] ) . '</p>';
					} elseif ( $can_book ) {
						if ( $has_conflict ) {
							echo '<p class="comarine-storage-unit-card__availability-note is-available">' . esc_html__( 'Some dates are already booked. Select an available start date from the calendar and the plugin will block unavailable ranges automatically.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
						} elseif ( $is_capacity_managed ) {
							echo '<p class="comarine-storage-unit-card__availability-note is-available">' . esc_html( sprintf( __( 'Available now. %s m2 can still be booked and your selected area will be locked for checkout.', 'comarine-storage-booking-with-woocommerce' ), $this->format_area_m2( max( 0, $capacity_remaining_m2 ) ) ) ) . '</p>';
						} else {
							echo '<p class="comarine-storage-unit-card__availability-note is-available">' . esc_html__( 'Available now. Your selection will be locked for checkout.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
						}
					}

					echo '<button class="comarine-storage-unit-card__book-button" type="submit" ' . disabled( $can_book, false, false ) . '>' . esc_html__( 'Book Now', 'comarine-storage-booking-with-woocommerce' ) . '</button>';
					echo '</form>';
				}
			} else {
				if ( 'single' === $card_action && $unit_permalink ) {
					echo '<p class="comarine-storage-unit-card__availability-note is-available">' . esc_html__( 'View the unit page for full details, photos, and availability information.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
					echo '<a class="comarine-storage-unit-card__book-button" href="' . esc_url( $unit_permalink ) . '">' . esc_html__( 'View Unit Details', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
				} else {
					echo '<p class="comarine-storage-unit-card__availability-note is-unavailable">' . esc_html__( 'Pricing is not configured for this unit yet.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
				}
			}

			echo '</article>';
		}

		echo '</div>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render a homepage-friendly shortcode with the latest storage units.
	 *
	 * This shortcode reuses the same card UI as the main storage units shortcode
	 * but hides the search/filter form and defaults to the latest 3 available units.
	 *
	 * Usage example: [comarine_storage_units_latest]
	 *
	 * @since 1.0.27
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_latest_storage_units_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 3,
				'status'   => 'available',
				'show_all' => '0',
				'checkout' => '1',
			),
			$atts,
			'comarine_storage_units_latest'
		);

		$atts['show_filters'] = '0';
		$atts['sort']         = 'latest';
		$atts['wrapper_class'] = 'comarine-storage-units--latest-home';
		$atts['card_action']   = 'single';

		return $this->render_storage_units_shortcode( $atts );
	}

	/**
	 * Parse frontend shortcode filter values from the current request.
	 *
	 * @since 1.0.11
	 *
	 * @param string $forced_status Optional fixed status key.
	 * @return array<string, mixed>
	 */
	private function get_storage_units_frontend_filters_from_request( $forced_status = '' ) {
		$allowed_statuses = array( 'all', 'available', 'reserved', 'occupied', 'maintenance', 'archived' );
		$status           = isset( $_GET['comarine_su_status'] ) ? sanitize_key( wp_unslash( $_GET['comarine_su_status'] ) ) : 'all';
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'all';
		}

		$forced_status = sanitize_key( (string) $forced_status );
		if ( '' !== $forced_status ) {
			$status = $forced_status;
		}

		return array(
			'query'        => isset( $_GET['comarine_su_q'] ) ? sanitize_text_field( wp_unslash( $_GET['comarine_su_q'] ) ) : '',
			'status'       => $status,
			'floor'        => isset( $_GET['comarine_su_floor'] ) ? sanitize_text_field( wp_unslash( $_GET['comarine_su_floor'] ) ) : '',
			'min_size'     => isset( $_GET['comarine_su_min_size'] ) && is_numeric( wp_unslash( $_GET['comarine_su_min_size'] ) ) ? (float) wp_unslash( $_GET['comarine_su_min_size'] ) : null,
			'max_size'     => isset( $_GET['comarine_su_max_size'] ) && is_numeric( wp_unslash( $_GET['comarine_su_max_size'] ) ) ? (float) wp_unslash( $_GET['comarine_su_max_size'] ) : null,
			'max_price'    => isset( $_GET['comarine_su_max_price'] ) && is_numeric( wp_unslash( $_GET['comarine_su_max_price'] ) ) ? (float) wp_unslash( $_GET['comarine_su_max_price'] ) : null,
			'bookable_only'=> isset( $_GET['comarine_su_bookable'] ) && '1' === (string) wp_unslash( $_GET['comarine_su_bookable'] ),
		);
	}

	/**
	 * Render the frontend filter form used by the storage units shortcode.
	 *
	 * @since 1.0.11
	 *
	 * @param array<string, mixed>  $filters        Active filters.
	 * @param bool                  $status_locked  Whether status is fixed by shortcode attrs.
	 * @param string                $default_status Fixed status value when locked.
	 * @param array<int, string>    $floor_options  Available floor options.
	 * @return void
	 */
	private function render_storage_units_filter_form( $filters, $status_locked, $default_status, $floor_options ) {
		$reset_url = remove_query_arg(
			array(
				'comarine_su_q',
				'comarine_su_status',
				'comarine_su_floor',
				'comarine_su_min_size',
				'comarine_su_max_size',
				'comarine_su_max_price',
				'comarine_su_bookable',
			)
		);

		echo '<form class="comarine-storage-units__filters" method="get" action="">';
		$this->render_storage_units_preserved_query_inputs();
		echo '<div class="comarine-storage-units__filters-grid">';

		echo '<label class="comarine-storage-units__filter-field">';
		echo '<span>' . esc_html__( 'Search', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<input type="search" name="comarine_su_q" value="' . esc_attr( (string) $filters['query'] ) . '" placeholder="' . esc_attr__( 'Unit title or code', 'comarine-storage-booking-with-woocommerce' ) . '" />';
		echo '</label>';

		if ( ! $status_locked ) {
			echo '<label class="comarine-storage-units__filter-field">';
			echo '<span>' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
			echo '<select name="comarine_su_status">';
			$status_options = array(
				'all'         => __( 'All statuses', 'comarine-storage-booking-with-woocommerce' ),
				'available'   => __( 'Available', 'comarine-storage-booking-with-woocommerce' ),
				'reserved'    => __( 'Reserved', 'comarine-storage-booking-with-woocommerce' ),
				'occupied'    => __( 'Occupied', 'comarine-storage-booking-with-woocommerce' ),
				'maintenance' => __( 'Maintenance', 'comarine-storage-booking-with-woocommerce' ),
				'archived'    => __( 'Archived', 'comarine-storage-booking-with-woocommerce' ),
			);
			foreach ( $status_options as $status_key => $label ) {
				echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( (string) $filters['status'], $status_key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			echo '</label>';
		} else {
			echo '<input type="hidden" name="comarine_su_status" value="' . esc_attr( $default_status ) . '" />';
		}

		echo '<label class="comarine-storage-units__filter-field">';
		echo '<span>' . esc_html__( 'Floor', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<select name="comarine_su_floor">';
		echo '<option value="">' . esc_html__( 'All floors', 'comarine-storage-booking-with-woocommerce' ) . '</option>';
		foreach ( $floor_options as $floor_option ) {
			echo '<option value="' . esc_attr( $floor_option ) . '" ' . selected( (string) $filters['floor'], (string) $floor_option, false ) . '>' . esc_html( $floor_option ) . '</option>';
		}
		echo '</select>';
		echo '</label>';

		echo '<label class="comarine-storage-units__filter-field">';
		echo '<span>' . esc_html__( 'Min size (m2)', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<input type="number" step="0.01" min="0" name="comarine_su_min_size" value="' . esc_attr( null !== $filters['min_size'] ? (string) $filters['min_size'] : '' ) . '" />';
		echo '</label>';

		echo '<label class="comarine-storage-units__filter-field">';
		echo '<span>' . esc_html__( 'Max size (m2)', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<input type="number" step="0.01" min="0" name="comarine_su_max_size" value="' . esc_attr( null !== $filters['max_size'] ? (string) $filters['max_size'] : '' ) . '" />';
		echo '</label>';

		echo '<label class="comarine-storage-units__filter-field">';
		echo '<span>' . esc_html__( 'Max price', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<input type="number" step="0.01" min="0" name="comarine_su_max_price" value="' . esc_attr( null !== $filters['max_price'] ? (string) $filters['max_price'] : '' ) . '" />';
		echo '</label>';

		echo '</div>';
		echo '<div class="comarine-storage-units__filters-actions">';
		echo '<label class="comarine-storage-units__checkbox"><input type="checkbox" name="comarine_su_bookable" value="1" ' . checked( ! empty( $filters['bookable_only'] ), true, false ) . ' /> ' . esc_html__( 'Only show units bookable now', 'comarine-storage-booking-with-woocommerce' ) . '</label>';
		echo '<button type="submit" class="comarine-storage-units__filter-button">' . esc_html__( 'Apply filters', 'comarine-storage-booking-with-woocommerce' ) . '</button>';
		echo '<a class="comarine-storage-units__reset-link" href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '</div>';
		echo '</form>';
	}

	/**
	 * Render hidden query params to preserve unrelated frontend URL parameters.
	 *
	 * @since 1.0.11
	 *
	 * @return void
	 */
	private function render_storage_units_preserved_query_inputs() {
		$skip_keys = array(
			'comarine_su_q',
			'comarine_su_status',
			'comarine_su_floor',
			'comarine_su_min_size',
			'comarine_su_max_size',
			'comarine_su_max_price',
			'comarine_su_bookable',
		);

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$key = sanitize_key( (string) $key );
			if ( '' === $key || in_array( $key, $skip_keys, true ) || is_array( $value ) ) {
				continue;
			}

			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $value ) ) ) . '" />';
		}
	}

	/**
	 * Build unique floor options for the shortcode filter UI.
	 *
	 * @since 1.0.11
	 *
	 * @param array<int, WP_Post> $units Unit posts.
	 * @return array<int, string>
	 */
	private function get_storage_units_floor_options( $units ) {
		$floors = array();

		foreach ( $units as $unit ) {
			if ( ! $unit || ! isset( $unit->ID ) ) {
				continue;
			}

			$floor = trim( (string) get_post_meta( (int) $unit->ID, '_csu_floor', true ) );
			if ( '' === $floor ) {
				continue;
			}

			$floors[] = $floor;
		}

		$floors = array_values( array_unique( $floors ) );
		natcasesort( $floors );

		return array_values( $floors );
	}

	/**
	 * Check whether a unit card matches the active frontend filters.
	 *
	 * @since 1.0.11
	 *
	 * @param array<string, mixed> $unit_data Unit card data.
	 * @param array<string, mixed> $filters   Active frontend filters.
	 * @return bool
	 */
	private function storage_unit_matches_frontend_filters( $unit_data, $filters ) {
		$query = isset( $filters['query'] ) ? strtolower( trim( (string) $filters['query'] ) ) : '';
		if ( '' !== $query ) {
			$title = strtolower( (string) get_the_title( (int) $unit_data['id'] ) );
			$code  = strtolower( (string) $unit_data['unit_code'] );
			if ( false === strpos( $title, $query ) && false === strpos( $code, $query ) ) {
				return false;
			}
		}

		$status = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : 'all';
		if ( '' !== $status && 'all' !== $status && $status !== (string) $unit_data['status'] ) {
			return false;
		}

		$floor_filter = trim( (string) ( isset( $filters['floor'] ) ? $filters['floor'] : '' ) );
		if ( '' !== $floor_filter && 0 !== strcasecmp( $floor_filter, (string) $unit_data['floor'] ) ) {
			return false;
		}

		$size_value = isset( $unit_data['size_value'] ) && is_numeric( $unit_data['size_value'] ) ? (float) $unit_data['size_value'] : null;
		if ( null !== $filters['min_size'] && ( null === $size_value || $size_value < (float) $filters['min_size'] ) ) {
			return false;
		}
		if ( null !== $filters['max_size'] && ( null === $size_value || $size_value > (float) $filters['max_size'] ) ) {
			return false;
		}

		if ( null !== $filters['max_price'] ) {
			$from_price = isset( $unit_data['from_price'] ) ? (float) $unit_data['from_price'] : 0.0;
			if ( $from_price <= 0 || $from_price > (float) $filters['max_price'] ) {
				return false;
			}
		}

		if ( ! empty( $filters['bookable_only'] ) && empty( $unit_data['can_book'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Parse unit features from `_csu_features` meta (array, JSON, or delimited text).
	 *
	 * @since 1.0.11
	 *
	 * @param int $unit_post_id Unit post ID.
	 * @return array<int, string>
	 */
	private function get_unit_features_list( $unit_post_id ) {
		$raw = get_post_meta( absint( $unit_post_id ), '_csu_features', true );
		$features = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $item ) {
				if ( is_scalar( $item ) ) {
					$value = trim( (string) $item );
					if ( '' !== $value ) {
						$features[] = $value;
					}
				}
			}
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$trimmed = trim( $raw );
			if ( '[' === substr( $trimmed, 0, 1 ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $item ) {
						if ( is_scalar( $item ) ) {
							$value = trim( (string) $item );
							if ( '' !== $value ) {
								$features[] = $value;
							}
						}
					}
				}
			}

			if ( empty( $features ) ) {
				$parts = preg_split( '/[\r\n,;|]+/', $trimmed );
				if ( is_array( $parts ) ) {
					foreach ( $parts as $part ) {
						$value = trim( (string) $part );
						if ( '' !== $value ) {
							$features[] = $value;
						}
					}
				}
			}
		}

		return array_values( array_unique( $features ) );
	}

	/**
	 * Get the reason why a unit cannot currently be booked.
	 *
	 * @since 1.0.11
	 *
	 * @param string               $status               Unit status key.
	 * @param bool                 $has_conflict         Whether an active booking lock/conflict exists.
	 * @param array<string, float> $durations            Duration price map.
	 * @param int                  $container_product_id Configured container product ID.
	 * @return string
	 */
	private function get_unit_unavailable_reason( $status, $has_conflict, $durations, $container_product_id ) {
		$status = sanitize_key( (string) $status );

		if ( $container_product_id <= 0 ) {
			return __( 'Booking is temporarily unavailable because checkout configuration is incomplete.', 'comarine-storage-booking-with-woocommerce' );
		}

		if ( empty( $durations ) ) {
			return __( 'Pricing is not configured for this unit yet.', 'comarine-storage-booking-with-woocommerce' );
		}

		if ( $has_conflict ) {
			return __( 'This unit is currently locked or already reserved. Please choose another unit.', 'comarine-storage-booking-with-woocommerce' );
		}

		if ( 'available' !== $status ) {
			return sprintf(
				/* translators: %s: unit status label */
				__( 'This unit is currently %s.', 'comarine-storage-booking-with-woocommerce' ),
				$this->format_storage_unit_status_label( $status )
			);
		}

		return '';
	}

	/**
	 * Format a unit status key to a frontend label.
	 *
	 * @since 1.0.11
	 *
	 * @param string $status Unit status key.
	 * @return string
	 */
	private function format_storage_unit_status_label( $status ) {
		$labels = array(
			'available'   => __( 'Available', 'comarine-storage-booking-with-woocommerce' ),
			'reserved'    => __( 'Reserved', 'comarine-storage-booking-with-woocommerce' ),
			'occupied'    => __( 'Occupied', 'comarine-storage-booking-with-woocommerce' ),
			'maintenance' => __( 'Maintenance', 'comarine-storage-booking-with-woocommerce' ),
			'archived'    => __( 'Archived', 'comarine-storage-booking-with-woocommerce' ),
		);

		$status = sanitize_key( (string) $status );

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status ?: 'available' );
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
				$handled_capacity_status = $this->sync_capacity_managed_unit_status_after_booking_change(
					$unit_post_id,
					in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved'
				);
				if ( ! $handled_capacity_status ) {
					update_post_meta( $unit_post_id, '_csu_status', in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved' );
				}
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
			$unit_post_id = (int) $item->get_meta( '_comarine_unit_post_id', true );
			if ( $booking_id <= 0 ) {
				continue;
			}

			if ( 'refunded' === $status ) {
				Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_refunded( $booking_id, (int) $order->get_id() );
				if ( $unit_post_id > 0 ) {
					$this->sync_capacity_managed_unit_status_after_booking_change( $unit_post_id );
				}
				continue;
			}

			Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_cancelled( $booking_id, (int) $order->get_id() );
			if ( $unit_post_id > 0 ) {
				$this->sync_capacity_managed_unit_status_after_booking_change( $unit_post_id );
			}
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
	 * Get capacity availability snapshot for a storage unit.
	 *
	 * @since 1.0.26
	 *
	 * @param int    $unit_post_id Unit post ID.
	 * @param string $range_start  Optional overlap range start date (`Y-m-d`).
	 * @param string $range_end    Optional overlap range end date (`Y-m-d`, exclusive).
	 * @return array<string, float|bool>
	 */
	private function get_unit_capacity_booking_snapshot( $unit_post_id, $range_start = '', $range_end = '' ) {
		$unit_post_id = absint( $unit_post_id );
		$range_start  = is_string( $range_start ) ? trim( $range_start ) : '';
		$range_end    = is_string( $range_end ) ? trim( $range_end ) : '';
		$fallback = array(
			'is_capacity_managed' => false,
			'capacity_m2'         => 0.0,
			'reserved_m2'         => 0.0,
			'remaining_m2'        => 0.0,
			'is_full'             => false,
		);

		if ( $unit_post_id <= 0 ) {
			return $fallback;
		}

		if ( class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) && method_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings', 'get_unit_capacity_availability' ) ) {
			$snapshot = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_unit_capacity_availability( $unit_post_id, $range_start, $range_end );
			if ( is_array( $snapshot ) ) {
				return wp_parse_args( $snapshot, $fallback );
			}
		}

		$booking_mode = sanitize_key( (string) get_post_meta( $unit_post_id, '_csu_booking_mode', true ) );
		if ( 'partial_area' !== $booking_mode ) {
			return $fallback;
		}

		$raw_capacity = get_post_meta( $unit_post_id, '_csu_size_m2', true );
		if ( '' === $raw_capacity || ! is_numeric( $raw_capacity ) ) {
			return $fallback;
		}

		$capacity = round( (float) $raw_capacity, 2 );
		if ( $capacity <= 0 ) {
			return $fallback;
		}

		return array(
			'is_capacity_managed' => true,
			'capacity_m2'         => $capacity,
			'reserved_m2'         => 0.0,
			'remaining_m2'        => $capacity,
			'is_full'             => false,
		);
	}

	/**
	 * Normalize requested booking area (m2) from request input.
	 *
	 * @since 1.0.26
	 *
	 * @param mixed $raw_value Raw request value.
	 * @return float
	 */
	private function sanitize_requested_area_m2( $raw_value ) {
		if ( is_array( $raw_value ) ) {
			return 0.0;
		}

		$value = str_replace( ',', '.', trim( (string) $raw_value ) );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return 0.0;
		}

		$area = round( (float) $value, 2 );

		return $area > 0 ? $area : 0.0;
	}

	/**
	 * Normalize a booking start date from request input (Y-m-d).
	 *
	 * @since 1.0.29
	 *
	 * @param mixed $raw_value Raw request value.
	 * @return string
	 */
	private function sanitize_booking_start_date( $raw_value ) {
		if ( is_array( $raw_value ) ) {
			return '';
		}

		$value = trim( (string) $raw_value );
		if ( '' === $value ) {
			return '';
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Normalize a booking end date from request input (Y-m-d).
	 *
	 * @since 1.0.30
	 *
	 * @param mixed $raw_value Raw request value.
	 * @return string
	 */
	private function sanitize_booking_end_date( $raw_value ) {
		return $this->sanitize_booking_start_date( $raw_value );
	}

	/**
	 * Validate the selected booking start date.
	 *
	 * @since 1.0.29
	 *
	 * @param string $start_date Selected date in Y-m-d format.
	 * @return true|WP_Error
	 */
	private function validate_booking_start_date( $start_date ) {
		$start_date = $this->sanitize_booking_start_date( $start_date );
		if ( '' === $start_date ) {
			return new WP_Error( 'comarine_missing_start_date', __( 'Please select a booking start date.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$start_dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $site_timezone );
		if ( ! $start_dt || $start_dt->format( 'Y-m-d' ) !== $start_date ) {
			return new WP_Error( 'comarine_invalid_start_date', __( 'Invalid booking start date selected.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$today = wp_date( 'Y-m-d', null, $site_timezone );
		if ( $start_date < $today ) {
			return new WP_Error( 'comarine_start_date_in_past', __( 'Booking start date cannot be in the past.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		return true;
	}

	/**
	 * Validate a booking date range and return day-count metadata.
	 *
	 * End date is treated as exclusive (the booking runs from start date up to,
	 * but not including, the end date), so minimum valid range is 1 day.
	 *
	 * @since 1.0.30
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate_booking_date_range( $start_date, $end_date ) {
		$start_check = $this->validate_booking_start_date( $start_date );
		if ( is_wp_error( $start_check ) ) {
			return $start_check;
		}

		$end_date = $this->sanitize_booking_end_date( $end_date );
		if ( '' === $end_date ) {
			return new WP_Error( 'comarine_missing_end_date', __( 'Please select a booking end date.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$start_dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $site_timezone );
		$end_dt        = DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, $site_timezone );

		if ( ! $start_dt || ! $end_dt || $start_dt->format( 'Y-m-d' ) !== $start_date || $end_dt->format( 'Y-m-d' ) !== $end_date ) {
			return new WP_Error( 'comarine_invalid_booking_dates', __( 'Invalid booking date range selected.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$day_count = (int) $start_dt->diff( $end_dt )->days;
		if ( $end_dt <= $start_dt || $day_count <= 0 ) {
			return new WP_Error( 'comarine_invalid_booking_dates', __( 'End date must be after the start date.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'day_count'  => $day_count,
		);
	}

	/**
	 * Calculate prorated booking price based on requested m2 vs unit capacity.
	 *
	 * Assumes the configured duration price is for the full unit.
	 *
	 * @since 1.0.26
	 *
	 * @param float $full_unit_price Full-unit price for the selected duration.
	 * @param float $requested_area_m2 Requested area in m2.
	 * @param float $unit_capacity_m2 Total unit capacity in m2.
	 * @return float
	 */
	private function calculate_capacity_prorated_price( $full_unit_price, $requested_area_m2, $unit_capacity_m2 ) {
		$full_unit_price   = (float) $full_unit_price;
		$requested_area_m2 = (float) $requested_area_m2;
		$unit_capacity_m2  = (float) $unit_capacity_m2;

		if ( $full_unit_price <= 0 || $requested_area_m2 <= 0 || $unit_capacity_m2 <= 0 ) {
			return max( 0.0, round( $full_unit_price, 2 ) );
		}

		return round( $full_unit_price * ( $requested_area_m2 / $unit_capacity_m2 ), 2 );
	}

	/**
	 * Calculate a daily booking total from a full-unit daily rate and day count.
	 *
	 * @since 1.0.30
	 *
	 * @param float $full_unit_daily_price Full-unit daily price.
	 * @param int   $day_count             Number of booked days.
	 * @return float
	 */
	private function calculate_daily_booking_total( $full_unit_daily_price, $day_count ) {
		$full_unit_daily_price = (float) $full_unit_daily_price;
		$day_count             = max( 0, (int) $day_count );

		if ( $full_unit_daily_price <= 0 || $day_count <= 0 ) {
			return 0.0;
		}

		return round( $full_unit_daily_price * $day_count, 2 );
	}

	/**
	 * Sync a capacity-managed unit status after a booking status change.
	 *
	 * Units with manual `maintenance` or `archived` status are left untouched.
	 *
	 * @since 1.0.26
	 *
	 * @param int    $unit_post_id Unit post ID.
	 * @param string $full_status  Status to apply only when capacity is fully booked.
	 * @return bool True when capacity mode was applied (even if no DB write needed).
	 */
	private function sync_capacity_managed_unit_status_after_booking_change( $unit_post_id, $full_status = 'reserved' ) {
		$unit_post_id = absint( $unit_post_id );
		if ( $unit_post_id <= 0 ) {
			return false;
		}

		$snapshot = $this->get_unit_capacity_booking_snapshot( $unit_post_id );
		if ( empty( $snapshot['is_capacity_managed'] ) ) {
			return false;
		}

		$current_status = sanitize_key( (string) get_post_meta( $unit_post_id, '_csu_status', true ) );
		if ( in_array( $current_status, array( 'maintenance', 'archived' ), true ) ) {
			return true;
		}

		$full_status = sanitize_key( (string) $full_status );
		if ( ! in_array( $full_status, array( 'reserved', 'occupied' ), true ) ) {
			$full_status = 'reserved';
		}

		$target_status = ! empty( $snapshot['is_full'] ) ? $full_status : 'available';
		if ( '' === $current_status ) {
			$current_status = 'available';
		}

		if ( $current_status === $target_status ) {
			return true;
		}

		update_post_meta( $unit_post_id, '_csu_status', $target_status );

		return true;
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
	 * Get configured booking add-ons from plugin settings.
	 *
	 * @since 1.0.12
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_configured_booking_addons() {
		$raw = comarine_storage_booking_with_woocommerce_get_setting( 'addons_definitions', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$addons = array();
		$seen   = array();

		foreach ( $raw as $addon ) {
			if ( ! is_array( $addon ) ) {
				continue;
			}

			$key   = isset( $addon['key'] ) ? sanitize_key( (string) $addon['key'] ) : '';
			$label = isset( $addon['label'] ) ? sanitize_text_field( (string) $addon['label'] ) : '';
			$price = isset( $addon['price'] ) && is_numeric( $addon['price'] ) ? round( (float) $addon['price'], 2 ) : 0.0;

			if ( '' === $key || '' === $label || isset( $seen[ $key ] ) ) {
				continue;
			}

			if ( isset( $addon['enabled'] ) && ! (bool) $addon['enabled'] ) {
				continue;
			}

			if ( $price < 0 ) {
				$price = 0.0;
			}

			$addons[] = array(
				'key'     => $key,
				'label'   => $label,
				'price'   => $price,
				'enabled' => true,
				'taxable' => ! empty( $addon['taxable'] ),
			);
			$seen[ $key ] = true;
		}

		return $addons;
	}

	/**
	 * Parse selected add-ons from POST and return validated snapshots.
	 *
	 * @since 1.0.12
	 *
	 * @param array<int, array<string, mixed>> $configured_addons Configured add-ons.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_selected_booking_addons_from_request( $configured_addons ) {
		if ( empty( $configured_addons ) || empty( $_POST['comarine_addons'] ) || ! is_array( $_POST['comarine_addons'] ) ) {
			return array();
		}

		$configured_map = array();
		foreach ( $configured_addons as $addon ) {
			if ( is_array( $addon ) && ! empty( $addon['key'] ) ) {
				$configured_map[ (string) $addon['key'] ] = $addon;
			}
		}

		$selected_keys = array_map( 'sanitize_key', wp_unslash( $_POST['comarine_addons'] ) );
		$selected_keys = array_values( array_unique( array_filter( $selected_keys ) ) );

		$selected = array();
		foreach ( $selected_keys as $selected_key ) {
			if ( ! isset( $configured_map[ $selected_key ] ) ) {
				continue;
			}

			$addon = $configured_map[ $selected_key ];
			$selected[] = array(
				'key'     => (string) $addon['key'],
				'label'   => (string) $addon['label'],
				'price'   => round( (float) $addon['price'], 2 ),
				'taxable' => ! empty( $addon['taxable'] ),
			);
		}

		return $selected;
	}

	/**
	 * Calculate the total for selected booking add-ons.
	 *
	 * @since 1.0.12
	 *
	 * @param array<int, array<string, mixed>> $selected_addons Selected add-ons.
	 * @return float
	 */
	private function get_selected_booking_addons_total( $selected_addons ) {
		$total = 0.0;

		if ( ! is_array( $selected_addons ) ) {
			return 0.0;
		}

		foreach ( $selected_addons as $addon ) {
			if ( ! is_array( $addon ) || ! isset( $addon['price'] ) ) {
				continue;
			}

			$price = is_numeric( $addon['price'] ) ? (float) $addon['price'] : 0.0;
			if ( $price > 0 ) {
				$total += $price;
			}
		}

		return round( $total, 2 );
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
			'daily'   => __( 'Per day', 'comarine-storage-booking-with-woocommerce' ),
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

	/**
	 * Get the default booking start date for the booking form date input.
	 *
	 * @since 1.0.29
	 *
	 * @return string
	 */
	private function get_default_booking_start_date_input_value() {
		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

		return wp_date( 'Y-m-d', null, $site_timezone );
	}

	/**
	 * Get the default booking end date for date-range bookings (start + 1 day).
	 *
	 * @since 1.0.30
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @return string
	 */
	private function get_default_booking_end_date_input_value( $start_date ) {
		$start_date = $this->sanitize_booking_start_date( $start_date );
		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now_dt = new DateTimeImmutable( 'now', $site_timezone );

		if ( '' === $start_date ) {
			return $now_dt->modify( '+1 day' )->format( 'Y-m-d' );
		}

		$start_dt = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $site_timezone );
		if ( ! $start_dt ) {
			return $now_dt->modify( '+1 day' )->format( 'Y-m-d' );
		}

		return $start_dt->modify( '+1 day' )->format( 'Y-m-d' );
	}

	/**
	 * Format a booking start date (Y-m-d) for customer-facing display.
	 *
	 * @since 1.0.29
	 *
	 * @param string $start_date Date in Y-m-d format.
	 * @return string
	 */
	private function format_booking_start_date_for_display( $start_date ) {
		$start_date = $this->sanitize_booking_start_date( $start_date );
		if ( '' === $start_date ) {
			return '';
		}

		$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$start_dt      = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, $site_timezone );
		if ( ! $start_dt ) {
			return $start_date;
		}

		return wp_date( 'd-m-Y', $start_dt->getTimestamp(), $site_timezone );
	}

	/**
	 * Format an m2 amount for display.
	 *
	 * @since 1.0.26
	 *
	 * @param float $area Area in m2.
	 * @return string
	 */
	private function format_area_m2( $area ) {
		return number_format_i18n( (float) $area, 2 );
	}

	/**
	 * Format a decimal number for HTML input value attributes.
	 *
	 * @since 1.0.26
	 *
	 * @param float $value Decimal value.
	 * @return string
	 */
	private function format_decimal_input( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}
}
