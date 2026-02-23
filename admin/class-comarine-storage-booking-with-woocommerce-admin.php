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
		if ( ! $this->is_plugin_admin_screen() ) {
			return;
		}

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

		wp_enqueue_style( 'jquery-ui-datepicker' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->is_plugin_admin_screen() ) {
			return;
		}

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

		wp_enqueue_script( 'jquery-ui-datepicker' );
		if ( function_exists( 'wp_localize_jquery_ui_datepicker' ) ) {
			wp_localize_jquery_ui_datepicker();
		}

		wp_localize_script(
			$this->plugin_name,
			'comarineStorageBookingAdmin',
			array(
				'bookingsPageSlug' => 'comarine-storage-bookings',
				'dateInputFormat'  => 'dd/mm/yyyy',
				'datepicker'       => array(
					'dateFormat'      => 'dd/mm/yy',
					'firstDay'        => (int) get_option( 'start_of_week', 1 ),
					'changeMonth'     => true,
					'changeYear'      => true,
					'constrainInput'  => false,
					'showButtonPanel' => true,
				),
			)
		);

	}

	/**
	 * Register admin menu pages for bookings management.
	 *
	 * @since    1.0.2
	 */
	public function register_admin_menu() {
		$menu_slug = 'comarine-storage-bookings';

		add_menu_page(
			__( 'CoMarine Bookings', 'comarine-storage-booking-with-woocommerce' ),
			__( 'CoMarine Storage', 'comarine-storage-booking-with-woocommerce' ),
			$this->get_admin_capability(),
			$menu_slug,
			array( $this, 'render_bookings_page' ),
			'dashicons-store'
		);

		add_submenu_page(
			$menu_slug,
			__( 'Overview', 'comarine-storage-booking-with-woocommerce' ),
			__( 'Overview', 'comarine-storage-booking-with-woocommerce' ),
			$this->get_admin_capability(),
			$this->get_overview_page_slug(),
			array( $this, 'render_overview_page' )
		);

		add_submenu_page(
			$menu_slug,
			__( 'Settings', 'comarine-storage-booking-with-woocommerce' ),
			__( 'Settings', 'comarine-storage-booking-with-woocommerce' ),
			$this->get_admin_capability(),
			$this->get_settings_page_slug(),
			array( $this, 'render_settings_page' )
		);

		// Rename the auto-added first submenu label so it matches the page content.
		global $submenu;
		if ( isset( $submenu[ $menu_slug ][0][0] ) ) {
			$submenu[ $menu_slug ][0][0] = __( 'Bookings', 'comarine-storage-booking-with-woocommerce' );
		}
	}

	/**
	 * Ensure the Storage Units CPT list/add-new screens appear under the plugin menu.
	 *
	 * Some WordPress admin menu setups don't automatically inject custom post type
	 * submenus when `show_in_menu` points to a custom top-level plugin page.
	 *
	 * @since    1.0.17
	 *
	 * @return void
	 */
	public function ensure_storage_units_submenus() {
		$menu_slug = 'comarine-storage-bookings';
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
		$list_slug = 'edit.php?post_type=' . $post_type;
		$add_slug  = 'post-new.php?post_type=' . $post_type;

		global $submenu;
		$existing_slugs = array();
		if ( isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] ) ) {
			foreach ( $submenu[ $menu_slug ] as $submenu_item ) {
				if ( isset( $submenu_item[2] ) ) {
					$existing_slugs[] = (string) $submenu_item[2];
				}
			}
		}

		if ( ! in_array( $list_slug, $existing_slugs, true ) ) {
			add_submenu_page(
				$menu_slug,
				__( 'Storage Units', 'comarine-storage-booking-with-woocommerce' ),
				__( 'Storage Units', 'comarine-storage-booking-with-woocommerce' ),
				'edit_posts',
				$list_slug
			);
		}

		if ( ! in_array( $add_slug, $existing_slugs, true ) ) {
			add_submenu_page(
				$menu_slug,
				__( 'Add New Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
				__( 'Add New', 'comarine-storage-booking-with-woocommerce' ),
				'edit_posts',
				$add_slug
			);
		}

		if ( isset( $submenu[ $menu_slug ] ) && is_array( $submenu[ $menu_slug ] ) ) {
			$order = array(
				$menu_slug                          => 10,
				$this->get_overview_page_slug()     => 15,
				$list_slug                          => 20,
				$add_slug                           => 30,
				$this->get_settings_page_slug()     => 40,
			);

			usort(
				$submenu[ $menu_slug ],
				static function ( $a, $b ) use ( $order ) {
					$a_slug = isset( $a[2] ) ? (string) $a[2] : '';
					$b_slug = isset( $b[2] ) ? (string) $b[2] : '';
					$a_pos  = isset( $order[ $a_slug ] ) ? $order[ $a_slug ] : 1000;
					$b_pos  = isset( $order[ $b_slug ] ) ? $order[ $b_slug ] : 1000;

					if ( $a_pos === $b_pos ) {
						return strnatcasecmp( $a_slug, $b_slug );
					}

					return $a_pos <=> $b_pos;
				}
			);
		}
	}

	/**
	 * Add a quick link to the plugin admin screen on the Plugins page.
	 *
	 * @since    1.0.14
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( $links ) {
		$admin_link = '<a href="' . esc_url( $this->get_bookings_page_url() ) . '">' . esc_html__( 'Open Admin', 'comarine-storage-booking-with-woocommerce' ) . '</a>';

		array_unshift( $links, $admin_link );

		return $links;
	}

	/**
	 * Normalize malformed Storage Units submenu requests to the real CPT admin URLs.
	 *
	 * Some WordPress setups render submenu items under plugin menus as
	 * `admin.php?page=edit.php?post_type=...`. Redirecting these requests ensures the
	 * Storage Units list/add-new screens always load through the correct core URLs.
	 *
	 * @since    1.0.21
	 *
	 * @return void
	 */
	public function maybe_normalize_storage_units_menu_requests() {
		if ( ! is_admin() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
		if ( ! is_string( $page ) || '' === $page ) {
			return;
		}

		$page = trim( $page );
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
		$legacy_post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE
			: 'comarine_storage_unit';
		$request_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$list_page = 'edit.php?post_type=' . $post_type;
		$add_page  = 'post-new.php?post_type=' . $post_type;
		$legacy_list_page = 'edit.php?post_type=' . $legacy_post_type;
		$legacy_add_page  = 'post-new.php?post_type=' . $legacy_post_type;

		if ( in_array( $request_post_type, array( $post_type, $legacy_post_type ), true ) && ! post_type_exists( $post_type ) && function_exists( 'comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback' ) ) {
			comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback();
		}

		global $pagenow;
		if ( $legacy_post_type !== $post_type && $legacy_post_type === $request_post_type ) {
			if ( 'edit.php' === $pagenow ) {
				wp_safe_redirect( $this->get_storage_units_list_url() );
				exit;
			}

			if ( 'post-new.php' === $pagenow ) {
				wp_safe_redirect( $this->get_storage_units_add_new_url() );
				exit;
			}
		}

		if ( $legacy_list_page === $page ) {
			wp_safe_redirect( $this->get_storage_units_list_url() );
			exit;
		}

		if ( $legacy_add_page === $page ) {
			wp_safe_redirect( $this->get_storage_units_add_new_url() );
			exit;
		}

		if ( $list_page === $page ) {
			wp_safe_redirect( $this->get_storage_units_list_url() );
			exit;
		}

		if ( $add_page === $page ) {
			wp_safe_redirect( $this->get_storage_units_add_new_url() );
			exit;
		}
	}

	/**
	 * Ensure the Storage Units CPT is registered on plugin admin requests.
	 *
	 * This covers wp-admin plugin pages (like Overview/Settings) where some
	 * environments may still reach rendering without the CPT registered.
	 *
	 * @since    1.0.23
	 *
	 * @return void
	 */
	public function maybe_ensure_storage_units_cpt_for_admin_requests() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
		$legacy_post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE
			: 'comarine_storage_unit';
		$page = isset( $_GET['page'] ) ? trim( (string) wp_unslash( $_GET['page'] ) ) : '';
		$request_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$is_plugin_admin_page = '' !== $page && 0 === strpos( $page, 'comarine-storage-' );
		$is_storage_units_request = in_array( $request_post_type, array( $post_type, $legacy_post_type ), true );

		if ( ! $is_plugin_admin_page && ! $is_storage_units_request ) {
			return;
		}

		if ( ! post_type_exists( $post_type ) ) {
			$this->register_storage_units_post_type_for_overview();
		}
	}

	/**
	 * Handle setup/admin utility actions (for example auto-creating the container product).
	 *
	 * @since    1.0.20
	 *
	 * @return void
	 */
	public function handle_setup_admin_actions() {
		if ( ! is_admin() || ! current_user_can( $this->get_admin_capability() ) ) {
			return;
		}

		$action = isset( $_GET['comarine_setup_action'] ) ? sanitize_key( wp_unslash( $_GET['comarine_setup_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}

		$redirect_page = isset( $_GET['comarine_setup_redirect'] ) ? sanitize_key( wp_unslash( $_GET['comarine_setup_redirect'] ) ) : $this->get_overview_page_slug();
		if ( ! in_array( $redirect_page, $this->get_setup_action_allowed_redirect_pages(), true ) ) {
			$redirect_page = $this->get_overview_page_slug();
		}

		$nonce = isset( $_GET['_comarine_setup_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_comarine_setup_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'comarine_setup_action' ) ) {
			$this->redirect_after_setup_action(
				$redirect_page,
				array( 'comarine_setup_notice' => 'invalid_nonce' )
			);
		}

		if ( 'create_container_product' !== $action ) {
			$this->redirect_after_setup_action(
				$redirect_page,
				array( 'comarine_setup_notice' => 'invalid_action' )
			);
		}

		$this->handle_create_container_product_action( $redirect_page );
	}

	/**
	 * Render setup action notices on Overview/Settings pages.
	 *
	 * @since    1.0.20
	 *
	 * @return void
	 */
	private function render_setup_action_notice() {
		$notice = isset( $_GET['comarine_setup_notice'] ) ? sanitize_key( wp_unslash( $_GET['comarine_setup_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}

		$product_id = isset( $_GET['comarine_product_id'] ) ? absint( $_GET['comarine_product_id'] ) : 0;
		$class      = 'notice-info';
		$message    = '';

		switch ( $notice ) {
			case 'container_created':
				$class   = 'notice-success';
				$message = $product_id > 0
					? sprintf(
						/* translators: %d: product ID */
						__( 'Booking container product created successfully (Product ID #%d) and saved in plugin settings.', 'comarine-storage-booking-with-woocommerce' ),
						$product_id
					)
					: __( 'Booking container product created successfully and saved in plugin settings.', 'comarine-storage-booking-with-woocommerce' );
				break;
			case 'container_exists':
				$class   = 'notice-info';
				$message = $product_id > 0
					? sprintf(
						/* translators: %d: product ID */
						__( 'A booking container product already exists (Product ID #%d). No new product was created.', 'comarine-storage-booking-with-woocommerce' ),
						$product_id
					)
					: __( 'A booking container product already exists. No new product was created.', 'comarine-storage-booking-with-woocommerce' );
				break;
			case 'woocommerce_unavailable':
				$class   = 'notice-error';
				$message = __( 'WooCommerce product APIs are not available. Make sure WooCommerce is active before creating the container product.', 'comarine-storage-booking-with-woocommerce' );
				break;
			case 'container_create_failed':
				$class   = 'notice-error';
				$message = __( 'The booking container product could not be created automatically. Please create/select one manually in Settings.', 'comarine-storage-booking-with-woocommerce' );
				break;
			case 'invalid_nonce':
				$class   = 'notice-error';
				$message = __( 'The requested setup action could not be verified. Please try again.', 'comarine-storage-booking-with-woocommerce' );
				break;
			case 'invalid_action':
				$class   = 'notice-error';
				$message = __( 'Unknown setup action requested.', 'comarine-storage-booking-with-woocommerce' );
				break;
			default:
				return;
		}

		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handle creating the booking container product automatically.
	 *
	 * @since    1.0.20
	 *
	 * @param string $redirect_page Page slug to redirect back to.
	 * @return void
	 */
	private function handle_create_container_product_action( $redirect_page ) {
		$existing_id = (int) comarine_storage_booking_with_woocommerce_get_setting( 'booking_container_product_id', 0 );
		if ( $existing_id > 0 && function_exists( 'wc_get_product' ) ) {
			$existing_product = wc_get_product( $existing_id );
			if ( $existing_product && is_object( $existing_product ) ) {
				$this->redirect_after_setup_action(
					$redirect_page,
					array(
						'comarine_setup_notice' => 'container_exists',
						'comarine_product_id'   => $existing_id,
					)
				);
			}
		}

		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			$this->redirect_after_setup_action(
				$redirect_page,
				array( 'comarine_setup_notice' => 'woocommerce_unavailable' )
			);
		}

		$auto_created_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_key'       => '_comarine_booking_container_product',
				'meta_value'     => '1',
			)
		);

		if ( ! empty( $auto_created_ids ) ) {
			$auto_created_id = absint( $auto_created_ids[0] );
			if ( $auto_created_id > 0 ) {
				$auto_created_product = wc_get_product( $auto_created_id );
				if ( $auto_created_product && is_object( $auto_created_product ) ) {
					$settings = comarine_storage_booking_with_woocommerce_get_settings();
					$settings['booking_container_product_id'] = $auto_created_id;
					update_option( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION, $settings );

					$this->redirect_after_setup_action(
						$redirect_page,
						array(
							'comarine_setup_notice' => 'container_exists',
							'comarine_product_id'   => $auto_created_id,
						)
					);
				}
			}
		}

		$product = new WC_Product_Simple();
		$product->set_name( __( 'CoMarine Storage Booking Container', 'comarine-storage-booking-with-woocommerce' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		if ( method_exists( $product, 'set_regular_price' ) ) {
			$product->set_regular_price( '0' );
		}
		if ( method_exists( $product, 'set_price' ) ) {
			$product->set_price( '0' );
		}
		if ( method_exists( $product, 'set_tax_status' ) ) {
			$product->set_tax_status( 'none' );
		}
		if ( method_exists( $product, 'set_description' ) ) {
			$product->set_description( __( 'Internal WooCommerce product used as the booking container for CoMarine storage reservations. Prices are overridden by booking snapshots during checkout.', 'comarine-storage-booking-with-woocommerce' ) );
		}
		if ( method_exists( $product, 'set_short_description' ) ) {
			$product->set_short_description( __( 'Auto-created by CoMarine Storage Booking plugin.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		try {
			$product_id = (int) $product->save();
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			$product_id = 0;
		}

		if ( $product_id <= 0 ) {
			$this->redirect_after_setup_action(
				$redirect_page,
				array( 'comarine_setup_notice' => 'container_create_failed' )
			);
		}

		update_post_meta( $product_id, '_comarine_booking_container_product', '1' );

		$settings = comarine_storage_booking_with_woocommerce_get_settings();
		$settings['booking_container_product_id'] = $product_id;
		update_option( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION, $settings );

		$this->redirect_after_setup_action(
			$redirect_page,
			array(
				'comarine_setup_notice' => 'container_created',
				'comarine_product_id'   => $product_id,
			)
		);
	}

	/**
	 * Redirect after a setup action and terminate execution.
	 *
	 * @since    1.0.20
	 *
	 * @param string               $page_slug Target page slug.
	 * @param array<string, mixed> $args      Optional query args.
	 * @return void
	 */
	private function redirect_after_setup_action( $page_slug, $args = array() ) {
		$page_slug = sanitize_key( (string) $page_slug );
		$args      = is_array( $args ) ? $args : array();

		if ( 'comarine-storage-bookings' === $page_slug ) {
			wp_safe_redirect( $this->get_bookings_page_url( $args ) );
			exit;
		}

		$args['page'] = $page_slug;
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Allowed setup action redirect targets.
	 *
	 * @since    1.0.20
	 *
	 * @return array<int, string>
	 */
	private function get_setup_action_allowed_redirect_pages() {
		return array(
			$this->get_overview_page_slug(),
			$this->get_settings_page_slug(),
			'comarine-storage-bookings',
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

		add_settings_field(
			'addons_definitions',
			__( 'Booking add-ons (JSON)', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_addons_definitions_field' ),
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

		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( 'comarine-storage-bookings' !== $page ) {
			return;
		}

		$bulk_action = '';
		foreach ( array( 'comarine_booking_bulk_action_top', 'comarine_booking_bulk_action_bottom', 'comarine_booking_bulk_action' ) as $bulk_action_field ) {
			if ( isset( $_POST[ $bulk_action_field ] ) ) {
				$candidate = sanitize_key( wp_unslash( $_POST[ $bulk_action_field ] ) );
				if ( '' !== $candidate ) {
					$bulk_action = $candidate;
					break;
				}
			}
		}

		if ( isset( $_POST['comarine_apply_bulk_action'] ) || '' !== $bulk_action ) {
			$this->handle_bookings_bulk_action_request( $bulk_action );
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

		if ( 'export_csv' === $action ) {
			$this->stream_bookings_csv_export();
		}

		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		$booking    = $booking_id > 0 ? Comarine_Storage_Booking_With_Woocommerce_Bookings::get_booking( $booking_id ) : null;
		if ( ! $booking ) {
			wp_safe_redirect( $this->get_bookings_page_url( array( 'comarine_notice' => 'booking_not_found' ) ) );
			exit;
		}

		$success                 = false;
		$requested_status        = '';
		$requested_unit_status   = '';
		$paid_unit_status_synced = false;
		$previous_booking_status = isset( $booking->status ) ? sanitize_key( (string) $booking->status ) : '';
		$previous_unit_status    = ( isset( $booking->unit_post_id ) && (int) $booking->unit_post_id > 0 ) ? sanitize_key( (string) get_post_meta( (int) $booking->unit_post_id, '_csu_status', true ) ) : '';

		switch ( $action ) {
			case 'mark_paid':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_paid( $booking_id, (int) $booking->order_id );
				$requested_status = 'paid';
				if ( $success && (int) $booking->unit_post_id > 0 ) {
					$paid_unit_status = (string) comarine_storage_booking_with_woocommerce_get_setting( 'paid_unit_status', 'reserved' );
					$requested_unit_status = in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved';
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking, $requested_unit_status );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					} else {
						$paid_unit_status_synced = false !== update_post_meta( (int) $booking->unit_post_id, '_csu_status', $requested_unit_status );
					}
				}
				break;

			case 'mark_cancelled':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_cancelled( $booking_id, (int) $booking->order_id );
				$requested_status = 'cancelled';
				if ( $success && (int) $booking->unit_post_id > 0 ) {
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					}
				}
				break;

			case 'mark_refunded':
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_refunded( $booking_id, (int) $booking->order_id );
				$requested_status = 'refunded';
				if ( $success && (int) $booking->unit_post_id > 0 ) {
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					}
				}
				break;

			case 'set_booking_status':
				$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
				$success = Comarine_Storage_Booking_With_Woocommerce_Bookings::set_booking_status( $booking_id, $status );
				$requested_status = $status;
				if ( $success && (int) $booking->unit_post_id > 0 ) {
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					}
				}
				break;

			case 'set_unit_status':
				$requested_unit_status = isset( $_GET['unit_status'] ) ? sanitize_key( wp_unslash( $_GET['unit_status'] ) ) : '';
				$success               = $this->update_booking_unit_status( $booking, $requested_unit_status );
				break;
		}

		if ( $success ) {
			switch ( $action ) {
				case 'mark_paid':
				case 'mark_cancelled':
				case 'mark_refunded':
				case 'set_booking_status':
					$this->log_booking_audit_event(
						$booking,
						'admin_' . $action,
						sprintf(
							/* translators: 1: previous status, 2: new status */
							__( 'Admin changed booking status from %1$s to %2$s.', 'comarine-storage-booking-with-woocommerce' ),
							Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( $previous_booking_status ),
							Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( $requested_status )
						),
						array(
							'action'                 => $action,
							'previous_booking_status'=> $previous_booking_status,
							'new_booking_status'     => $requested_status,
						)
					);

					if ( '' !== $requested_unit_status && $requested_unit_status !== $previous_unit_status && $paid_unit_status_synced ) {
						$this->log_booking_audit_event(
							$booking,
							'admin_mark_paid_unit_status_sync',
							sprintf(
								/* translators: 1: previous unit status, 2: new unit status */
								__( 'Admin payment action changed unit status from %1$s to %2$s.', 'comarine-storage-booking-with-woocommerce' ),
								$previous_unit_status ? ucfirst( $previous_unit_status ) : '-',
								ucfirst( $requested_unit_status )
							),
							array(
								'action'              => $action,
								'previous_unit_status'=> $previous_unit_status,
								'new_unit_status'     => $requested_unit_status,
							)
						);
					}
					break;
			}
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
	 * Handle POSTed bulk actions from the Bookings admin list.
	 *
	 * @since    1.0.9
	 *
	 * @param string $bulk_action Bulk action key.
	 * @return void
	 */
	private function handle_bookings_bulk_action_request( $bulk_action ) {
		$filters      = $this->get_bookings_filters_from_request();
		$redirect_args = $this->get_bookings_filter_query_args( $filters );
		$bulk_action  = sanitize_key( (string) $bulk_action );
		$bulk_note    = $this->get_posted_bulk_note();
		$bulk_confirmed = $this->is_bulk_action_confirmation_checked();

		$nonce = isset( $_POST['_comarine_bulk_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_comarine_bulk_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'comarine_booking_bulk_action' ) ) {
			$redirect_args['comarine_notice'] = 'bulk_invalid_nonce';
			wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
			exit;
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			$redirect_args['comarine_notice'] = 'error';
			wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
			exit;
		}

		$booking_ids = array();
		if ( isset( $_POST['booking_ids'] ) && is_array( $_POST['booking_ids'] ) ) {
			$booking_ids = array_map( 'absint', wp_unslash( $_POST['booking_ids'] ) );
			$booking_ids = array_values( array_unique( array_filter( $booking_ids ) ) );
		}

		if ( empty( $booking_ids ) ) {
			$redirect_args['comarine_notice'] = 'bulk_none_selected';
			wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
			exit;
		}

		if ( $this->is_destructive_bulk_booking_action( $bulk_action ) && ! $bulk_confirmed ) {
			$redirect_args['comarine_notice'] = 'bulk_confirmation_required';
			wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
			exit;
		}

		$result = $this->perform_bulk_booking_action( $bulk_action, $booking_ids, $bulk_note );
		if ( ! is_array( $result ) ) {
			$redirect_args['comarine_notice'] = 'bulk_invalid_action';
			wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
			exit;
		}

		$redirect_args['comarine_done']  = (string) (int) $result['updated'];
		$redirect_args['comarine_total'] = (string) (int) $result['total'];

		if ( 1 === (int) $result['total'] && ! empty( $booking_ids[0] ) ) {
			$redirect_args['booking_id'] = (int) $booking_ids[0];
		}

		if ( (int) $result['updated'] <= 0 ) {
			$redirect_args['comarine_notice'] = 'bulk_update_failed';
		} elseif ( (int) $result['failed'] > 0 ) {
			$redirect_args['comarine_notice'] = 'bulk_partial';
		} else {
			$redirect_args['comarine_notice'] = 'bulk_updated';
		}

		wp_safe_redirect( $this->get_bookings_page_url( $redirect_args ) );
		exit;
	}

	/**
	 * Execute a bulk action against selected bookings.
	 *
	 * @since    1.0.9
	 *
	 * @param string     $bulk_action Bulk action key.
	 * @param array<int> $booking_ids Selected booking IDs.
	 * @return array<string, int>|false
	 */
	private function perform_bulk_booking_action( $bulk_action, $booking_ids, $bulk_note = '' ) {
		$bulk_action = sanitize_key( (string) $bulk_action );
		$bulk_note   = sanitize_text_field( (string) $bulk_note );
		$options     = $this->get_bulk_booking_action_options();

		if ( ! isset( $options[ $bulk_action ] ) ) {
			return false;
		}

		$result = array(
			'total'   => count( $booking_ids ),
			'updated' => 0,
			'failed'  => 0,
		);

		foreach ( $booking_ids as $booking_id ) {
			$booking = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_booking( (int) $booking_id );
			if ( ! $booking ) {
				$result['failed']++;
				continue;
			}

			$success = false;

			if ( 0 === strpos( $bulk_action, 'bulk_set_unit_status_' ) ) {
				$unit_status = (string) substr( $bulk_action, strlen( 'bulk_set_unit_status_' ) );
				$success     = $this->update_booking_unit_status(
					$booking,
					$unit_status,
					array(
						'event_type' => 'admin_bulk_set_unit_status',
						'action'     => $bulk_action,
						'note'       => $bulk_note,
						'message_prefix' => __( 'Bulk admin', 'comarine-storage-booking-with-woocommerce' ),
					)
				);
			} else {
				$success = $this->apply_bulk_booking_status_action( $booking, $bulk_action, $bulk_note );
			}

			if ( $success ) {
				$result['updated']++;
			} else {
				$result['failed']++;
			}
		}

		return $result;
	}

	/**
	 * Apply a bulk booking status action and write audit log entries.
	 *
	 * @since    1.0.9
	 *
	 * @param object $booking     Booking row.
	 * @param string $bulk_action Bulk action key.
	 * @return bool
	 */
	private function apply_bulk_booking_status_action( $booking, $bulk_action, $bulk_note = '' ) {
		$booking_id             = isset( $booking->id ) ? absint( $booking->id ) : 0;
		$order_id               = isset( $booking->order_id ) ? absint( $booking->order_id ) : 0;
		$previous_booking_status = isset( $booking->status ) ? sanitize_key( (string) $booking->status ) : '';
		$previous_unit_status   = ( isset( $booking->unit_post_id ) && (int) $booking->unit_post_id > 0 ) ? sanitize_key( (string) get_post_meta( (int) $booking->unit_post_id, '_csu_status', true ) ) : '';
		$requested_status       = '';
		$requested_unit_status  = '';
		$paid_unit_status_synced = false;
		$success                = false;
		$bulk_note              = sanitize_text_field( (string) $bulk_note );

		if ( $booking_id <= 0 ) {
			return false;
		}

		switch ( $bulk_action ) {
			case 'bulk_mark_paid':
				$success          = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_paid( $booking_id, $order_id );
				$requested_status = 'paid';
				if ( $success && isset( $booking->unit_post_id ) && (int) $booking->unit_post_id > 0 ) {
					$paid_unit_status     = (string) comarine_storage_booking_with_woocommerce_get_setting( 'paid_unit_status', 'reserved' );
					$requested_unit_status = in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? $paid_unit_status : 'reserved';
					$capacity_unit_sync   = $this->sync_capacity_managed_unit_status_after_booking_change( $booking, $requested_unit_status );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					} else {
						$paid_unit_status_synced = false !== update_post_meta( (int) $booking->unit_post_id, '_csu_status', $requested_unit_status );
					}
				}
				break;

			case 'bulk_mark_cancelled':
				$success          = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_cancelled( $booking_id, $order_id );
				$requested_status = 'cancelled';
				if ( $success && isset( $booking->unit_post_id ) && (int) $booking->unit_post_id > 0 ) {
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					}
				}
				break;

			case 'bulk_mark_refunded':
				$success          = Comarine_Storage_Booking_With_Woocommerce_Bookings::mark_booking_refunded( $booking_id, $order_id );
				$requested_status = 'refunded';
				if ( $success && isset( $booking->unit_post_id ) && (int) $booking->unit_post_id > 0 ) {
					$capacity_unit_sync = $this->sync_capacity_managed_unit_status_after_booking_change( $booking );
					if ( ! empty( $capacity_unit_sync['handled'] ) ) {
						$requested_unit_status   = (string) $capacity_unit_sync['new_unit_status'];
						$paid_unit_status_synced = ! empty( $capacity_unit_sync['updated'] );
					}
				}
				break;
		}

		if ( ! $success ) {
			return false;
		}

		$event_key = str_replace( 'bulk_', '', $bulk_action );
		$this->log_booking_audit_event(
			$booking,
			'admin_bulk_' . $event_key,
			sprintf(
				/* translators: 1: previous status, 2: new status */
				__( 'Bulk admin action changed booking status from %1$s to %2$s.', 'comarine-storage-booking-with-woocommerce' ),
				Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( $previous_booking_status ),
				Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( $requested_status )
			) . ( '' !== $bulk_note ? ' ' . sprintf( __( 'Note: %s', 'comarine-storage-booking-with-woocommerce' ), $bulk_note ) : '' ),
			array(
				'action'                  => $bulk_action,
				'previous_booking_status' => $previous_booking_status,
				'new_booking_status'      => $requested_status,
				'note'                    => $bulk_note,
			)
		);

		if ( '' !== $requested_unit_status && $requested_unit_status !== $previous_unit_status && $paid_unit_status_synced ) {
			$this->log_booking_audit_event(
				$booking,
				'admin_bulk_mark_paid_unit_status_sync',
				sprintf(
					/* translators: 1: previous unit status, 2: new unit status */
					__( 'Bulk payment action changed unit status from %1$s to %2$s.', 'comarine-storage-booking-with-woocommerce' ),
					$previous_unit_status ? ucfirst( $previous_unit_status ) : '-',
					ucfirst( $requested_unit_status )
				) . ( '' !== $bulk_note ? ' ' . sprintf( __( 'Note: %s', 'comarine-storage-booking-with-woocommerce' ), $bulk_note ) : '' ),
				array(
					'action'               => $bulk_action,
					'previous_unit_status' => $previous_unit_status,
					'new_unit_status'      => $requested_unit_status,
					'note'                 => $bulk_note,
				)
			);
		}

		return true;
	}

	/**
	 * Get supported bulk action labels for the Bookings screen.
	 *
	 * @since    1.0.9
	 *
	 * @return array<string, string>
	 */
	private function get_bulk_booking_action_options() {
		return array(
			'bulk_mark_paid'                => __( 'Mark Paid', 'comarine-storage-booking-with-woocommerce' ),
			'bulk_mark_cancelled'           => __( 'Cancel Booking', 'comarine-storage-booking-with-woocommerce' ),
			'bulk_mark_refunded'            => __( 'Mark Refunded', 'comarine-storage-booking-with-woocommerce' ),
			'bulk_set_unit_status_reserved' => __( 'Set Unit: Reserved', 'comarine-storage-booking-with-woocommerce' ),
			'bulk_set_unit_status_occupied' => __( 'Set Unit: Occupied', 'comarine-storage-booking-with-woocommerce' ),
			'bulk_set_unit_status_available'=> __( 'Set Unit: Available', 'comarine-storage-booking-with-woocommerce' ),
		);
	}

	/**
	 * Whether a bulk action should require explicit confirmation.
	 *
	 * @since    1.0.9
	 *
	 * @param string $bulk_action Bulk action key.
	 * @return bool
	 */
	private function is_destructive_bulk_booking_action( $bulk_action ) {
		return in_array(
			sanitize_key( (string) $bulk_action ),
			array(
				'bulk_mark_cancelled',
				'bulk_mark_refunded',
				'bulk_set_unit_status_available',
			),
			true
		);
	}

	/**
	 * Read the optional audit note submitted with bulk actions.
	 *
	 * @since    1.0.9
	 *
	 * @return string
	 */
	private function get_posted_bulk_note() {
		foreach ( array( 'comarine_bulk_note_top', 'comarine_bulk_note_bottom', 'comarine_bulk_note' ) as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			if ( '' !== $value ) {
				return substr( $value, 0, 250 );
			}
		}

		return '';
	}

	/**
	 * Check whether the user confirmed a destructive bulk action.
	 *
	 * @since    1.0.9
	 *
	 * @return bool
	 */
	private function is_bulk_action_confirmation_checked() {
		foreach ( array( 'comarine_bulk_confirm_top', 'comarine_bulk_confirm_bottom', 'comarine_bulk_confirm' ) as $field ) {
			if ( isset( $_POST[ $field ] ) && '1' === (string) wp_unslash( $_POST[ $field ] ) ) {
				return true;
			}
		}

		return false;
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
			echo '<div class="wrap comarine-storage-booking-admin comarine-storage-booking-admin--bookings"><h1>' . esc_html__( 'Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Bookings helper class is not loaded.', 'comarine-storage-booking-with-woocommerce' ) . '</p></div></div>';
			return;
		}

		$filters        = $this->get_bookings_filters_from_request();
		$status_filter  = $filters['status_filter'];
		$order_filter   = $filters['order_id'];
		$booking_filter = $filters['booking_id'];
		$unit_filter    = $filters['unit_post_id'];
		$created_from   = $filters['created_from'];
		$created_to     = $filters['created_to'];

		$query_args = array(
			'limit'       => 50,
			'status'      => $status_filter,
			'order_id'    => $order_filter,
			'booking_id'  => $booking_filter,
			'unit_post_id'=> $unit_filter,
			'created_from'=> $created_from,
			'created_to'  => $created_to,
		);

		$count         = Comarine_Storage_Booking_With_Woocommerce_Bookings::count_bookings_filtered(
			array(
				'status'      => $status_filter,
				'order_id'    => $order_filter,
				'booking_id'  => $booking_filter,
				'unit_post_id'=> $unit_filter,
				'created_from'=> $created_from,
				'created_to'  => $created_to,
			)
		);
		$recent_rows   = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_bookings( $query_args );
		$table_name    = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_table_name();
		$status_options = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_options();

		echo '<div class="wrap comarine-storage-booking-admin comarine-storage-booking-admin--bookings">';
		echo '<h1>' . esc_html__( 'CoMarine Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		$this->render_bookings_page_notice();
		echo '<p>' . esc_html__( 'Manage booking records, inspect linked WooCommerce orders, and perform manual status overrides when needed.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Bookings table:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> <code>' . esc_html( $table_name ) . '</code></p>';
		echo '<p><strong>' . esc_html__( 'Total bookings:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( (string) $count ) . '</p>';
		$this->render_units_status_overview_panel();
		echo '<form class="comarine-bookings-filters" method="get" style="margin:12px 0 16px;">';
		echo '<input type="hidden" name="page" value="comarine-storage-bookings" />';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Status', 'comarine-storage-booking-with-woocommerce' ) . ' ';
		echo '<select name="status_filter">';
		echo '<option value="">' . esc_html__( 'All statuses', 'comarine-storage-booking-with-woocommerce' ) . '</option>';
		foreach ( $status_options as $status_key => $status_label ) {
			echo '<option value="' . esc_attr( $status_key ) . '" ' . selected( $status_filter, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Unit ID', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="small-text" type="number" min="0" name="unit_post_id" value="' . esc_attr( (string) $unit_filter ) . '" /></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Order ID', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="small-text" type="number" min="0" name="order_id" value="' . esc_attr( (string) $order_filter ) . '" /></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Booking ID', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="small-text" type="number" min="0" name="booking_id" value="' . esc_attr( (string) $booking_filter ) . '" /></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Created From', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="comarine-admin-datepicker" type="text" inputmode="numeric" autocomplete="off" placeholder="dd/mm/yyyy" name="created_from" value="' . esc_attr( $this->format_admin_date_input_value( $created_from ) ) . '" /></label>';
		echo '<label style="margin-right:12px;">' . esc_html__( 'Created To', 'comarine-storage-booking-with-woocommerce' ) . ' <input class="comarine-admin-datepicker" type="text" inputmode="numeric" autocomplete="off" placeholder="dd/mm/yyyy" name="created_to" value="' . esc_attr( $this->format_admin_date_input_value( $created_to ) ) . '" /></label>';
		submit_button( __( 'Filter', 'comarine-storage-booking-with-woocommerce' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( $this->get_bookings_page_url() ) . '">' . esc_html__( 'Reset', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo ' <a class="button" href="' . esc_url( $this->build_bookings_export_link( $filters ) ) . '">' . esc_html__( 'Export CSV', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '</form>';

		if ( $booking_filter > 0 ) {
			$this->render_booking_detail_panel( $booking_filter );
		}

		echo '<h2 class="comarine-admin-section-title">' . esc_html__( 'Recent bookings', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';

		if ( empty( $recent_rows ) ) {
			echo '<p>' . esc_html__( 'No bookings match the current filters yet.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			$this->render_booking_audit_log_section( $order_filter, $booking_filter );
			echo '</div>';
			return;
		}

		echo '<form class="comarine-bookings-bulk-form" method="post" action="' . esc_url( admin_url( 'edit.php' ) ) . '" style="margin:0 0 12px;">';
		$this->render_bookings_bulk_action_controls( $filters, true );

		echo '<table class="widefat striped comarine-bookings-table"><thead><tr>';
		echo '<th style="width:32px;"><input type="checkbox" id="comarine-bookings-select-all" onclick="var c=this.checked;document.querySelectorAll(\'.comarine-booking-checkbox\').forEach(function(el){el.checked=c;});" /></th>';
		echo '<th>' . esc_html__( 'ID', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit Status', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Duration', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Area (m2)', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
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
			$customer      = $this->get_booking_customer_summary( $row );
			$actions       = $this->get_booking_row_actions( $row );

			echo '<tr>';
			echo '<td><input class="comarine-booking-checkbox" type="checkbox" name="booking_ids[]" value="' . esc_attr( (string) $row->id ) . '" /></td>';
			echo '<td>' . esc_html( (string) $row->id ) . '</td>';
			echo '<td>';
			if ( $unit_link ) {
				echo '<a href="' . esc_url( $unit_link ) . '">' . esc_html( (string) $row->unit_code ) . '</a>';
			} else {
				echo esc_html( (string) $row->unit_code );
			}
			echo '</td>';
			echo '<td>' . esc_html( $customer['label'] ) . '</td>';
			echo '<td>' . esc_html( $unit_status ? ucfirst( $unit_status ) : '-' ) . '</td>';
			echo '<td>';
			if ( $order_link ) {
				echo '<a href="' . esc_url( $order_link ) . '">#' . esc_html( (string) $row->order_id ) . '</a>';
			} else {
				echo esc_html( (string) $row->order_id );
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $row->duration_key ) . '</td>';
			echo '<td>' . esc_html( $this->format_booking_area_summary( $row ) ) . '</td>';
			echo '<td>' . esc_html( Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( (string) $row->status ) ) . '</td>';
			echo '<td>' . esc_html( $price_display ) . '</td>';
			echo '<td>' . esc_html( $this->format_admin_datetime_display( isset( $row->lock_expires_ts ) ? (string) $row->lock_expires_ts : '', '-' ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_admin_datetime_display( isset( $row->created_ts ) ? (string) $row->created_ts : '', '-' ) ) . '</td>';
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

		echo '</tbody></table>';
		$this->render_bookings_bulk_action_controls( $filters, false );
		echo '</form>';
		$this->render_booking_audit_log_section( $order_filter, $booking_filter );
		echo '</div>';
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

		echo '<div class="wrap comarine-storage-booking-admin comarine-storage-booking-admin--settings">';
		echo '<h1>' . esc_html__( 'CoMarine Storage Booking Settings', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		$this->render_setup_action_notice();
		echo '<form class="comarine-settings-form" method="post" action="options.php">';
		settings_fields( 'comarine_storage_booking_with_woocommerce_settings_group' );
		do_settings_sections( $this->get_settings_page_slug() );
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Render a setup overview screen with a checklist of required configuration.
	 *
	 * @since    1.0.19
	 *
	 * @return void
	 */
	public function render_overview_page() {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$sections = $this->get_setup_overview_sections();
		$summary  = array(
			'ok'      => 0,
			'warning' => 0,
			'error'   => 0,
		);

		foreach ( $sections as $section ) {
			if ( empty( $section['checks'] ) || ! is_array( $section['checks'] ) ) {
				continue;
			}

			foreach ( $section['checks'] as $check ) {
				$status = isset( $check['status'] ) ? (string) $check['status'] : '';
				if ( isset( $summary[ $status ] ) ) {
					$summary[ $status ]++;
				}
			}
		}

		$overall_state = 'ok';
		if ( $summary['error'] > 0 ) {
			$overall_state = 'error';
		} elseif ( $summary['warning'] > 0 ) {
			$overall_state = 'warning';
		}

		$total_checks        = (int) array_sum( $summary );
		$actionable_checks   = (int) ( $summary['warning'] + $summary['error'] );
		$completion_percent  = $total_checks > 0 ? (int) round( ( $summary['ok'] / $total_checks ) * 100 ) : 100;
		$completion_percent  = max( 0, min( 100, $completion_percent ) );
		$completion_message  = sprintf(
			/* translators: 1: passed checks count, 2: total checks count. */
			__( '%1$d of %2$d checks are currently passing.', 'comarine-storage-booking-with-woocommerce' ),
			(int) $summary['ok'],
			$total_checks
		);
		$attention_message = $actionable_checks > 0
			? sprintf(
				/* translators: %d: count of warning+error checks. */
				_n(
					'%d item still needs attention before go-live.',
					'%d items still need attention before go-live.',
					$actionable_checks,
					'comarine-storage-booking-with-woocommerce'
				),
				$actionable_checks
			)
			: __( 'All checks look good for go-live.', 'comarine-storage-booking-with-woocommerce' );

		$next_action = array(
			'url'   => '',
			'label' => '',
		);
		$next_action_found = false;

		foreach ( array( 'error', 'warning' ) as $priority_status ) {
			foreach ( $sections as $section ) {
				$checks = isset( $section['checks'] ) && is_array( $section['checks'] ) ? $section['checks'] : array();
				foreach ( $checks as $check ) {
					$status       = isset( $check['status'] ) ? (string) $check['status'] : '';
					$action_url   = isset( $check['action_url'] ) ? (string) $check['action_url'] : '';
					$action_label = isset( $check['action_label'] ) ? (string) $check['action_label'] : '';

					if ( $priority_status !== $status || '' === $action_url || '' === $action_label ) {
						continue;
					}

					$next_action = array(
						'url'   => $action_url,
						'label' => $action_label,
					);
					$next_action_found = true;
					break 2;
				}
			}

			if ( $next_action_found ) {
				break;
			}
		}

		echo '<div class="wrap comarine-storage-booking-admin comarine-storage-booking-admin--overview">';
		echo '<h1>' . esc_html__( 'CoMarine Storage Overview', 'comarine-storage-booking-with-woocommerce' ) . '</h1>';
		$this->render_setup_action_notice();
		echo '<p>' . esc_html__( 'Use this checklist to confirm the plugin is configured and ready for bookings. Required items should be green before going live.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';

		echo '<div class="comarine-admin-panel comarine-admin-panel--overview comarine-overview-summary">';
		echo '<div class="comarine-overview-summary__layout">';
		echo '<div class="comarine-overview-summary__main">';
		echo '<p class="comarine-overview-summary__eyebrow">' . esc_html__( 'Setup Readiness', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
		echo '<h2>' . esc_html__( 'Setup Status', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';
		echo '<div class="comarine-overview-summary__row">';
		echo '<span class="comarine-overview-pill is-' . esc_attr( $overall_state ) . '">' . esc_html( $this->get_overview_status_label( $overall_state ) ) . '</span>';
		echo '<span class="comarine-overview-summary__counts">' . esc_html( sprintf( __( '%1$d OK, %2$d warnings, %3$d issues', 'comarine-storage-booking-with-woocommerce' ), $summary['ok'], $summary['warning'], $summary['error'] ) ) . '</span>';
		echo '</div>';
		echo '<p class="comarine-overview-summary__lead">' . esc_html( $completion_message ) . '</p>';
		echo '<p class="comarine-overview-summary__sublead">' . esc_html( $attention_message ) . '</p>';
		echo '<div class="comarine-overview-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $completion_percent ) . '" aria-label="' . esc_attr__( 'Setup checklist completion', 'comarine-storage-booking-with-woocommerce' ) . '">';
		echo '<div class="comarine-overview-progress__track"><span class="comarine-overview-progress__fill" style="width:' . esc_attr( (string) $completion_percent ) . '%;"></span></div>';
		echo '<span class="comarine-overview-progress__label">' . esc_html( sprintf( __( '%d%% complete', 'comarine-storage-booking-with-woocommerce' ), $completion_percent ) ) . '</span>';
		echo '</div>';
		echo '<div class="comarine-overview-summary__actions">';
		echo '<a class="button button-primary" href="' . esc_url( $this->get_settings_page_url() ) . '">' . esc_html__( 'Open Settings', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $this->get_storage_units_list_url() ) . '">' . esc_html__( 'View Storage Units', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $this->get_bookings_page_url() ) . '">' . esc_html__( 'Open Bookings', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $this->get_overview_page_url() ) . '">' . esc_html__( 'Refresh Overview', 'comarine-storage-booking-with-woocommerce' ) . '</a>';
		if ( ! empty( $next_action['url'] ) && ! empty( $next_action['label'] ) ) {
			echo '<a class="button button-secondary comarine-overview-summary__next-action" href="' . esc_url( $next_action['url'] ) . '">' . esc_html( sprintf( __( 'Fix Next Item: %s', 'comarine-storage-booking-with-woocommerce' ), $next_action['label'] ) ) . '</a>';
		}
		echo '</div>';
		echo '</div>';
		echo '<div class="comarine-overview-summary__stats">';
		echo '<div class="comarine-overview-stat-card is-primary">';
		echo '<span class="comarine-overview-stat-card__label">' . esc_html__( 'Completion', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<strong class="comarine-overview-stat-card__value">' . esc_html( sprintf( __( '%d%%', 'comarine-storage-booking-with-woocommerce' ), $completion_percent ) ) . '</strong>';
		echo '<span class="comarine-overview-stat-card__hint">' . esc_html( sprintf( __( '%1$d / %2$d checks', 'comarine-storage-booking-with-woocommerce' ), (int) $summary['ok'], $total_checks ) ) . '</span>';
		echo '</div>';
		echo '<div class="comarine-overview-stat-card is-error">';
		echo '<span class="comarine-overview-stat-card__label">' . esc_html__( 'Issues', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<strong class="comarine-overview-stat-card__value">' . esc_html( (string) (int) $summary['error'] ) . '</strong>';
		echo '<span class="comarine-overview-stat-card__hint">' . esc_html__( 'Required items failing', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '</div>';
		echo '<div class="comarine-overview-stat-card is-warning">';
		echo '<span class="comarine-overview-stat-card__label">' . esc_html__( 'Warnings', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<strong class="comarine-overview-stat-card__value">' . esc_html( (string) (int) $summary['warning'] ) . '</strong>';
		echo '<span class="comarine-overview-stat-card__hint">' . esc_html__( 'Recommended follow-ups', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '</div>';
		echo '<div class="comarine-overview-stat-card is-neutral">';
		echo '<span class="comarine-overview-stat-card__label">' . esc_html__( 'Checks', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '<strong class="comarine-overview-stat-card__value">' . esc_html( (string) $total_checks ) . '</strong>';
		echo '<span class="comarine-overview-stat-card__hint">' . esc_html__( 'Across all sections', 'comarine-storage-booking-with-woocommerce' ) . '</span>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="comarine-overview-grid">';
		foreach ( $sections as $section ) {
			$title       = isset( $section['title'] ) ? (string) $section['title'] : '';
			$description = isset( $section['description'] ) ? (string) $section['description'] : '';
			$checks      = isset( $section['checks'] ) && is_array( $section['checks'] ) ? $section['checks'] : array();
			$section_summary = array(
				'ok'      => 0,
				'warning' => 0,
				'error'   => 0,
			);

			foreach ( $checks as $check ) {
				$section_status = isset( $check['status'] ) ? (string) $check['status'] : '';
				if ( isset( $section_summary[ $section_status ] ) ) {
					$section_summary[ $section_status ]++;
				}
			}

			$section_total = (int) array_sum( $section_summary );
			$section_state = 'ok';
			if ( $section_summary['error'] > 0 ) {
				$section_state = 'error';
			} elseif ( $section_summary['warning'] > 0 ) {
				$section_state = 'warning';
			}

			echo '<section class="comarine-admin-panel comarine-overview-card comarine-overview-card--' . esc_attr( $section_state ) . '">';
			echo '<div class="comarine-overview-card__header">';
			echo '<div class="comarine-overview-card__header-main">';
			echo '<h2>' . esc_html( $title ) . '</h2>';
			if ( '' !== $description ) {
				echo '<p class="comarine-overview-card__description">' . esc_html( $description ) . '</p>';
			}
			echo '</div>';
			echo '<div class="comarine-overview-card__header-side">';
			echo '<span class="comarine-overview-pill is-' . esc_attr( $section_state ) . '">' . esc_html( $this->get_overview_status_label( $section_state ) ) . '</span>';
			echo '<span class="comarine-overview-card__counts">';
			echo esc_html(
				sprintf(
					/* translators: %d: number of checks in the section. */
					_n( '%d check', '%d checks', $section_total, 'comarine-storage-booking-with-woocommerce' ),
					$section_total
				)
			);
			echo '</span>';
			echo '</div>';
			echo '</div>';

			if ( empty( $checks ) ) {
				echo '<p>' . esc_html__( 'No checks available.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
				echo '</section>';
				continue;
			}

			echo '<div class="comarine-overview-card__summary">';
			echo '<span class="comarine-overview-mini-stat is-ok">' . esc_html( sprintf( __( '%d OK', 'comarine-storage-booking-with-woocommerce' ), (int) $section_summary['ok'] ) ) . '</span>';
			echo '<span class="comarine-overview-mini-stat is-warning">' . esc_html( sprintf( __( '%d warnings', 'comarine-storage-booking-with-woocommerce' ), (int) $section_summary['warning'] ) ) . '</span>';
			echo '<span class="comarine-overview-mini-stat is-error">' . esc_html( sprintf( __( '%d issues', 'comarine-storage-booking-with-woocommerce' ), (int) $section_summary['error'] ) ) . '</span>';
			echo '</div>';

			echo '<ul class="comarine-overview-checklist">';
			foreach ( $checks as $check ) {
				$status = isset( $check['status'] ) ? (string) $check['status'] : 'warning';
				$label = isset( $check['label'] ) ? (string) $check['label'] : '';
				$details = isset( $check['details'] ) ? (string) $check['details'] : '';
				$action_url = isset( $check['action_url'] ) ? (string) $check['action_url'] : '';
				$action_label = isset( $check['action_label'] ) ? (string) $check['action_label'] : '';

				echo '<li class="comarine-overview-check comarine-overview-check--' . esc_attr( $status ) . '">';
				echo '<div class="comarine-overview-check__head">';
				echo '<span class="comarine-overview-pill is-' . esc_attr( $status ) . '">' . esc_html( $this->get_overview_status_label( $status ) ) . '</span>';
				echo '<strong class="comarine-overview-check__label">' . esc_html( $label ) . '</strong>';
				echo '</div>';

				if ( '' !== $details ) {
					echo '<p class="comarine-overview-check__details">' . esc_html( $details ) . '</p>';
				}

				if ( '' !== $action_url && '' !== $action_label ) {
					echo '<p class="comarine-overview-check__action"><a class="button button-small" href="' . esc_url( $action_url ) . '">' . esc_html( $action_label ) . '</a></p>';
				}

				echo '</li>';
			}
			echo '</ul>';
			echo '</section>';
		}
		echo '</div>';
		echo '</div>';
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

		$settings['addons_definitions'] = $this->sanitize_addons_definitions_setting(
			isset( $input['addons_definitions'] ) ? wp_unslash( $input['addons_definitions'] ) : ''
		);

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

		if ( function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Simple' ) ) {
			echo '<p class="description">';
			echo '<a class="button button-secondary" href="' . esc_url( $this->get_setup_action_url( 'create_container_product', $this->get_settings_page_slug() ) ) . '">';
			echo esc_html__( 'Create Container Product Automatically', 'comarine-storage-booking-with-woocommerce' );
			echo '</a> ';
			echo esc_html__( 'Creates a hidden virtual product and saves it in this setting if one is not already configured.', 'comarine-storage-booking-with-woocommerce' );
			echo '</p>';
		}
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
	 * Render add-ons JSON settings field.
	 *
	 * @since    1.0.12
	 *
	 * @return void
	 */
	public function render_addons_definitions_field() {
		$current_value = comarine_storage_booking_with_woocommerce_get_setting( 'addons_definitions', array() );
		$json_value    = '[]';

		if ( is_array( $current_value ) ) {
			$encoded = wp_json_encode( array_values( $current_value ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			if ( false !== $encoded ) {
				$json_value = $encoded;
			}
		}

		echo '<textarea class="large-text code" rows="10" name="' . esc_attr( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION ) . '[addons_definitions]">' . esc_textarea( $json_value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Define optional booking add-ons as a JSON array. Supported fields: key, label, price, enabled, taxable.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
		echo '<p class="description"><code>' . esc_html( '[{"key":"insurance","label":"Insurance","price":15,"enabled":true,"taxable":false}]' ) . '</code></p>';
	}

	/**
	 * Sanitize add-ons JSON into a stable array format.
	 *
	 * @since    1.0.12
	 *
	 * @param mixed $raw_value Raw field value.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_addons_definitions_setting( $raw_value ) {
		if ( is_array( $raw_value ) ) {
			$decoded = $raw_value;
		} else {
			$raw_value = trim( (string) $raw_value );
			if ( '' === $raw_value ) {
				return array();
			}

			$decoded = json_decode( (string) $raw_value, true );
			if ( ! is_array( $decoded ) ) {
				add_settings_error(
					COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_SETTINGS_OPTION,
					'comarine_addons_json_invalid',
					__( 'Booking add-ons JSON is invalid. The previous valid add-ons configuration was replaced with an empty list.', 'comarine-storage-booking-with-woocommerce' ),
					'error'
				);
				return array();
			}
		}

		$sanitized = array();
		$seen_keys = array();

		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
			if ( '' === $key || isset( $seen_keys[ $key ] ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$price = isset( $row['price'] ) && is_numeric( $row['price'] ) ? round( (float) $row['price'], 2 ) : 0.0;
			if ( $price < 0 ) {
				$price = 0.0;
			}

			$sanitized[] = array(
				'key'     => $key,
				'label'   => $label,
				'price'   => $price,
				'enabled' => ! isset( $row['enabled'] ) || (bool) $row['enabled'],
				'taxable' => ! empty( $row['taxable'] ),
			);
			$seen_keys[ $key ] = true;
		}

		return $sanitized;
	}

	/**
	 * Render a summary of storage units by status.
	 *
	 * @since    1.0.8
	 *
	 * @return void
	 */
	private function render_units_status_overview_panel() {
		$counts = $this->get_unit_status_overview_counts();

		echo '<div class="comarine-admin-panel comarine-admin-panel--overview" style="margin:12px 0 16px;padding:12px 16px;border:1px solid #dcdcde;background:#fff;">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Units Status Overview', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';
		echo '<p style="margin:6px 0 0;">';
		echo '<strong>' . esc_html__( 'Available', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['available'] );
		echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Reserved', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['reserved'] );
		echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Occupied', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['occupied'] );
		echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Maintenance', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['maintenance'] );
		echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Archived', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['archived'] );
		echo ' &nbsp;|&nbsp; <strong>' . esc_html__( 'Unknown', 'comarine-storage-booking-with-woocommerce' ) . ':</strong> ' . esc_html( (string) $counts['unknown'] );
		echo '</p></div>';
	}

	/**
	 * Count units grouped by `_csu_status`.
	 *
	 * @since    1.0.8
	 *
	 * @return array<string, int>
	 */
	private function get_unit_status_overview_counts() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';

		$unit_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$counts = array(
			'available'   => 0,
			'reserved'    => 0,
			'occupied'    => 0,
			'maintenance' => 0,
			'archived'    => 0,
			'unknown'     => 0,
		);

		foreach ( $unit_ids as $unit_id ) {
			$status = sanitize_key( (string) get_post_meta( (int) $unit_id, '_csu_status', true ) );
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			} else {
				$counts['unknown']++;
			}
		}

		return $counts;
	}

	/**
	 * Render a booking detail panel for a selected booking.
	 *
	 * @since    1.0.8
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function render_booking_detail_panel( $booking_id ) {
		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			return;
		}

		$booking_id = absint( $booking_id );
		if ( $booking_id <= 0 ) {
			return;
		}

		$booking = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_booking( $booking_id );

		echo '<div class="comarine-admin-panel comarine-admin-panel--detail" style="margin:0 0 16px;padding:12px 16px;border:1px solid #dcdcde;background:#fff;">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Booking Detail', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';

		if ( ! $booking ) {
			echo '<p>' . esc_html__( 'The selected booking could not be found.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			echo '</div>';
			return;
		}

		$customer     = $this->get_booking_customer_summary( $booking );
		$unit_post_id = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;
		$order_id     = isset( $booking->order_id ) ? absint( $booking->order_id ) : 0;
		$unit_link    = $unit_post_id > 0 ? get_edit_post_link( $unit_post_id ) : '';
		$order_link   = $order_id > 0 ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';
		$unit_status  = $unit_post_id > 0 ? sanitize_key( (string) get_post_meta( $unit_post_id, '_csu_status', true ) ) : '';
		$actions      = $this->get_booking_row_actions( $booking );

		echo '<table class="widefat striped comarine-booking-detail-table" style="max-width:980px;"><tbody>';
		$this->render_booking_detail_row( __( 'Booking ID', 'comarine-storage-booking-with-woocommerce' ), '#' . (string) $booking_id );
		$this->render_booking_detail_row(
			__( 'Booking Status', 'comarine-storage-booking-with-woocommerce' ),
			Comarine_Storage_Booking_With_Woocommerce_Bookings::get_status_label( isset( $booking->status ) ? (string) $booking->status : '' )
		);
		$this->render_booking_detail_row( __( 'Customer', 'comarine-storage-booking-with-woocommerce' ), $customer['label'] );
		if ( '' !== $customer['email'] ) {
			$this->render_booking_detail_row( __( 'Customer Email', 'comarine-storage-booking-with-woocommerce' ), $customer['email'] );
		}

		$unit_label = isset( $booking->unit_code ) && '' !== (string) $booking->unit_code ? (string) $booking->unit_code : ( $unit_post_id > 0 ? '#' . $unit_post_id : '-' );
		$unit_html  = $unit_link ? '<a href="' . esc_url( $unit_link ) . '">' . esc_html( $unit_label ) . '</a>' : esc_html( $unit_label );
		$this->render_booking_detail_row_html( __( 'Unit', 'comarine-storage-booking-with-woocommerce' ), $unit_html );
		$this->render_booking_detail_row( __( 'Unit Status', 'comarine-storage-booking-with-woocommerce' ), $unit_status ? ucfirst( $unit_status ) : '-' );

		$order_html = $order_link ? '<a href="' . esc_url( $order_link ) . '">#' . esc_html( (string) $order_id ) . '</a>' : esc_html( $order_id > 0 ? '#' . (string) $order_id : '-' );
		$this->render_booking_detail_row_html( __( 'Order', 'comarine-storage-booking-with-woocommerce' ), $order_html );

		$this->render_booking_detail_row( __( 'Duration', 'comarine-storage-booking-with-woocommerce' ), isset( $booking->duration_key ) && '' !== (string) $booking->duration_key ? (string) $booking->duration_key : '-' );
		$booked_area_display = $this->format_booking_area_summary( $booking, true );
		if ( '-' !== $booked_area_display ) {
			$this->render_booking_detail_row( __( 'Booked Area', 'comarine-storage-booking-with-woocommerce' ), $booked_area_display );
		}
		$this->render_booking_detail_row(
			__( 'Price', 'comarine-storage-booking-with-woocommerce' ),
			trim( (string) ( isset( $booking->price_total ) ? $booking->price_total : '' ) . ' ' . (string) ( isset( $booking->currency ) ? $booking->currency : '' ) )
		);
		$this->render_booking_detail_row( __( 'Start', 'comarine-storage-booking-with-woocommerce' ), $this->format_admin_datetime_display( isset( $booking->start_ts ) ? (string) $booking->start_ts : '', '-' ) );
		$this->render_booking_detail_row( __( 'End', 'comarine-storage-booking-with-woocommerce' ), $this->format_admin_datetime_display( isset( $booking->end_ts ) ? (string) $booking->end_ts : '', '-' ) );
		$this->render_booking_detail_row( __( 'Lock Expires', 'comarine-storage-booking-with-woocommerce' ), $this->format_admin_datetime_display( isset( $booking->lock_expires_ts ) ? (string) $booking->lock_expires_ts : '', '-' ) );
		$this->render_booking_detail_row( __( 'Created', 'comarine-storage-booking-with-woocommerce' ), $this->format_admin_datetime_display( isset( $booking->created_ts ) ? (string) $booking->created_ts : '', '-' ) );
		$this->render_booking_detail_row( __( 'Updated', 'comarine-storage-booking-with-woocommerce' ), $this->format_admin_datetime_display( isset( $booking->updated_ts ) ? (string) $booking->updated_ts : '', '-' ) );
		echo '</tbody></table>';

		if ( ! empty( $actions ) ) {
			echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Actions:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ';
			foreach ( $actions as $action_html ) {
				echo $action_html . ' ';
			}
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render a text-only booking detail table row.
	 *
	 * @since    1.0.8
	 *
	 * @param string $label Row label.
	 * @param string $value Row value.
	 * @return void
	 */
	private function render_booking_detail_row( $label, $value ) {
		echo '<tr><th style="width:220px;">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
	}

	/**
	 * Render an HTML row for booking detail output.
	 *
	 * @since    1.0.8
	 *
	 * @param string $label Row label.
	 * @param string $html  Pre-escaped HTML.
	 * @return void
	 */
	private function render_booking_detail_row_html( $label, $html ) {
		echo '<tr><th style="width:220px;">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			echo esc_html__( 'CoMarine Storage Booking: Select a WooCommerce booking container product in CoMarine Storage > Settings before accepting bookings.', 'comarine-storage-booking-with-woocommerce' );
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
	 * Get overview page slug.
	 *
	 * @since    1.0.19
	 *
	 * @return string
	 */
	private function get_overview_page_slug() {
		return 'comarine-storage-overview';
	}

	/**
	 * Build the setup overview admin page URL.
	 *
	 * @since    1.0.19
	 *
	 * @return string
	 */
	private function get_overview_page_url() {
		return add_query_arg(
			array( 'page' => $this->get_overview_page_slug() ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build the settings admin page URL.
	 *
	 * @since    1.0.19
	 *
	 * @return string
	 */
	private function get_settings_page_url() {
		return add_query_arg(
			array( 'page' => $this->get_settings_page_slug() ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Build a nonce-protected setup action URL.
	 *
	 * @since    1.0.20
	 *
	 * @param string $action        Setup action key.
	 * @param string $redirect_page Page slug to return to after execution.
	 * @return string
	 */
	private function get_setup_action_url( $action, $redirect_page ) {
		$action        = sanitize_key( (string) $action );
		$redirect_page = sanitize_key( (string) $redirect_page );

		if ( ! in_array( $redirect_page, $this->get_setup_action_allowed_redirect_pages(), true ) ) {
			$redirect_page = $this->get_overview_page_slug();
		}

		$url = add_query_arg(
			array(
				'page'                   => $redirect_page,
				'comarine_setup_action'  => $action,
				'comarine_setup_redirect' => $redirect_page,
			),
			admin_url( 'admin.php' )
		);

		return wp_nonce_url( $url, 'comarine_setup_action', '_comarine_setup_nonce' );
	}

	/**
	 * Build the Storage Units list admin URL.
	 *
	 * @since    1.0.19
	 *
	 * @return string
	 */
	private function get_storage_units_list_url() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';

		return add_query_arg(
			array( 'post_type' => $post_type ),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Build the Storage Units add-new admin URL.
	 *
	 * @since    1.0.19
	 *
	 * @return string
	 */
	private function get_storage_units_add_new_url() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';

		return add_query_arg(
			array( 'post_type' => $post_type ),
			admin_url( 'post-new.php' )
		);
	}

	/**
	 * Get checklist sections for the setup overview screen.
	 *
	 * @since    1.0.19
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_setup_overview_sections() {
		$post_type_statuses     = $this->ensure_required_post_types_registered_for_overview();
		$post_types_check       = $this->get_required_post_types_overview_check( $post_type_statuses );
		$settings               = comarine_storage_booking_with_woocommerce_get_settings();
		$container_product_id   = isset( $settings['booking_container_product_id'] ) ? absint( $settings['booking_container_product_id'] ) : 0;
		$lock_ttl               = isset( $settings['lock_ttl_minutes'] ) ? (int) $settings['lock_ttl_minutes'] : 15;
		$currency               = isset( $settings['currency'] ) ? strtoupper( sanitize_text_field( (string) $settings['currency'] ) ) : 'EUR';
		$paid_unit_status       = isset( $settings['paid_unit_status'] ) ? sanitize_key( (string) $settings['paid_unit_status'] ) : 'reserved';
		$addons                 = isset( $settings['addons_definitions'] ) && is_array( $settings['addons_definitions'] ) ? $settings['addons_definitions'] : array();
		$addons_enabled_count   = 0;
		$container_product      = null;
		$container_product_link = '';
		$container_virtual      = null;
		$container_exists       = false;
		$unit_stats             = $this->get_storage_unit_setup_stats();
		$shortcode_pages        = $this->find_shortcode_usage_posts();
		$can_auto_create_container = function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Simple' );
		$create_container_url      = $can_auto_create_container ? $this->get_setup_action_url( 'create_container_product', $this->get_overview_page_slug() ) : '';

		foreach ( $addons as $addon ) {
			if ( is_array( $addon ) && ( ! isset( $addon['enabled'] ) || ! empty( $addon['enabled'] ) ) ) {
				$addons_enabled_count++;
			}
		}

		if ( $container_product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$container_product = wc_get_product( $container_product_id );
			if ( $container_product && is_object( $container_product ) ) {
				$container_exists = true;
				$container_product_link = get_edit_post_link( $container_product_id ) ? get_edit_post_link( $container_product_id ) : '';
				$container_virtual = method_exists( $container_product, 'is_virtual' ) ? (bool) $container_product->is_virtual() : null;
			}
		}

		$dependency_checks = array();
		if ( function_exists( 'comarine_storage_booking_with_woocommerce_get_dependency_statuses' ) ) {
			$plugin_install_url = admin_url( 'plugins.php' );
			$dependency_statuses = comarine_storage_booking_with_woocommerce_get_dependency_statuses();
			foreach ( $dependency_statuses as $dependency ) {
				$is_active = ! empty( $dependency['is_active'] );
				$is_installed = ! empty( $dependency['is_installed'] );
				$label = isset( $dependency['label'] ) ? (string) $dependency['label'] : __( 'Dependency', 'comarine-storage-booking-with-woocommerce' );
				$details = $is_active
					? __( 'Installed and active.', 'comarine-storage-booking-with-woocommerce' )
					: ( $is_installed
						? __( 'Installed but not active.', 'comarine-storage-booking-with-woocommerce' )
						: __( 'Not installed.', 'comarine-storage-booking-with-woocommerce' ) );

				$dependency_checks[] = array(
					'status'      => $is_active ? 'ok' : 'error',
					'label'       => $label,
					'details'     => $details,
					'action_url'  => $plugin_install_url,
					'action_label'=> __( 'Manage Plugins', 'comarine-storage-booking-with-woocommerce' ),
				);
			}
		}

		$required_checks = array(
			$post_types_check,
			array(
				'status'       => $container_product_id > 0 ? 'ok' : 'error',
				'label'        => __( 'Booking container product selected', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $container_product_id > 0
					? sprintf( __( 'Product ID #%d is selected in plugin settings.', 'comarine-storage-booking-with-woocommerce' ), $container_product_id )
					: __( 'Select a WooCommerce product in Settings to use as the checkout booking container.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => $container_product_id > 0 ? $this->get_settings_page_url() : ( $create_container_url ? $create_container_url : $this->get_settings_page_url() ),
				'action_label' => $container_product_id > 0 ? __( 'Open Settings', 'comarine-storage-booking-with-woocommerce' ) : ( $create_container_url ? __( 'Create Container Product', 'comarine-storage-booking-with-woocommerce' ) : __( 'Open Settings', 'comarine-storage-booking-with-woocommerce' ) ),
			),
			array(
				'status'       => $container_product_id > 0 ? ( $container_exists ? 'ok' : 'error' ) : 'warning',
				'label'        => __( 'Configured container product exists', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $container_product_id <= 0
					? __( 'No container product selected yet.', 'comarine-storage-booking-with-woocommerce' )
					: ( $container_exists ? __( 'The selected container product can be loaded.', 'comarine-storage-booking-with-woocommerce' ) : __( 'The selected container product no longer exists or WooCommerce product loading is unavailable.', 'comarine-storage-booking-with-woocommerce' ) ),
				'action_url'   => $container_exists && $container_product_link ? $container_product_link : ( $create_container_url ? $create_container_url : $this->get_settings_page_url() ),
				'action_label' => $container_exists && $container_product_link ? __( 'Edit Product', 'comarine-storage-booking-with-woocommerce' ) : ( $create_container_url ? __( 'Create Container Product', 'comarine-storage-booking-with-woocommerce' ) : __( 'Open Settings', 'comarine-storage-booking-with-woocommerce' ) ),
			),
			array(
				'status'       => $unit_stats['total'] > 0 ? 'ok' : 'error',
				'label'        => __( 'Storage units created', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $unit_stats['total'] > 0
					? sprintf( __( '%d storage units found.', 'comarine-storage-booking-with-woocommerce' ), $unit_stats['total'] )
					: __( 'Create at least one storage unit before accepting bookings.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => $unit_stats['total'] > 0 ? $this->get_storage_units_list_url() : $this->get_storage_units_add_new_url(),
				'action_label' => $unit_stats['total'] > 0 ? __( 'View Storage Units', 'comarine-storage-booking-with-woocommerce' ) : __( 'Add Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'       => $unit_stats['with_pricing'] > 0 ? 'ok' : 'error',
				'label'        => __( 'At least one unit has pricing', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $unit_stats['with_pricing'] > 0
					? sprintf( __( '%d units have at least one configured duration price.', 'comarine-storage-booking-with-woocommerce' ), $unit_stats['with_pricing'] )
					: __( 'Booking cannot start until at least one unit has pricing (monthly, 6m, or 12m).', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => $this->get_storage_units_list_url(),
				'action_label' => __( 'Review Units', 'comarine-storage-booking-with-woocommerce' ),
			),
		);

		$recommended_checks = array(
			array(
				'status'       => true === $container_virtual ? 'ok' : ( false === $container_virtual ? 'warning' : 'warning' ),
				'label'        => __( 'Container product is virtual', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => true === $container_virtual
					? __( 'Virtual product confirmed (recommended to avoid shipping side effects).', 'comarine-storage-booking-with-woocommerce' )
					: ( false === $container_virtual
						? __( 'The selected container product is not virtual. This can introduce shipping/fulfillment side effects.', 'comarine-storage-booking-with-woocommerce' )
						: __( 'Unable to verify virtual product setting yet.', 'comarine-storage-booking-with-woocommerce' ) ),
				'action_url'   => $container_exists && $container_product_link ? $container_product_link : $this->get_settings_page_url(),
				'action_label' => $container_exists && $container_product_link ? __( 'Edit Product', 'comarine-storage-booking-with-woocommerce' ) : __( 'Open Settings', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'       => $unit_stats['available'] > 0 ? 'ok' : 'warning',
				'label'        => __( 'At least one unit is available', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $unit_stats['available'] > 0
					? sprintf( __( '%d units are currently marked as available.', 'comarine-storage-booking-with-woocommerce' ), $unit_stats['available'] )
					: __( 'No units are currently marked as available, so customers may not be able to book right now.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => $this->get_storage_units_list_url(),
				'action_label' => __( 'Open Storage Units', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'       => $this->woocommerce_pages_look_configured() ? 'ok' : 'warning',
				'label'        => __( 'WooCommerce cart and checkout pages exist', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $this->woocommerce_pages_look_configured()
					? __( 'Cart and checkout pages are configured in WooCommerce.', 'comarine-storage-booking-with-woocommerce' )
					: __( 'WooCommerce cart and/or checkout pages appear missing. Checkout flow may fail.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => admin_url( 'admin.php?page=wc-settings&tab=advanced' ),
				'action_label' => __( 'WooCommerce Advanced Settings', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'       => ! empty( $shortcode_pages ) ? 'ok' : 'warning',
				'label'        => __( 'Booking shortcode is placed on a page/post', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => ! empty( $shortcode_pages )
					? sprintf( __( 'Shortcode found on %d page(s)/post(s).', 'comarine-storage-booking-with-woocommerce' ), count( $shortcode_pages ) )
					: __( 'No page/post containing `[comarine_storage_units]` was detected in recent content scans.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => ! empty( $shortcode_pages ) && ! empty( $shortcode_pages[0]['edit_link'] ) ? (string) $shortcode_pages[0]['edit_link'] : admin_url( 'edit.php?post_type=page' ),
				'action_label' => ! empty( $shortcode_pages ) ? __( 'Edit Shortcode Page', 'comarine-storage-booking-with-woocommerce' ) : __( 'Open Pages', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'       => $this->bookings_tables_look_ready() ? 'ok' : 'warning',
				'label'        => __( 'Custom booking tables are available', 'comarine-storage-booking-with-woocommerce' ),
				'details'      => $this->bookings_tables_look_ready()
					? __( 'Bookings and audit tables are available.', 'comarine-storage-booking-with-woocommerce' )
					: __( 'Bookings and/or audit table is not available yet. Reload after activation/upgrade.', 'comarine-storage-booking-with-woocommerce' ),
				'action_url'   => $this->get_bookings_page_url(),
				'action_label' => __( 'Open Bookings', 'comarine-storage-booking-with-woocommerce' ),
			),
		);

		$current_config_checks = array(
			array(
				'status'  => 'ok',
				'label'   => __( 'Lock TTL', 'comarine-storage-booking-with-woocommerce' ),
				'details' => sprintf( __( '%d minutes', 'comarine-storage-booking-with-woocommerce' ), max( 1, (int) $lock_ttl ) ),
			),
			array(
				'status'  => 'ok',
				'label'   => __( 'Paid unit status', 'comarine-storage-booking-with-woocommerce' ),
				'details' => in_array( $paid_unit_status, array( 'reserved', 'occupied' ), true ) ? ucfirst( $paid_unit_status ) : __( 'Reserved (fallback)', 'comarine-storage-booking-with-woocommerce' ),
			),
			array(
				'status'  => 'ok',
				'label'   => __( 'Currency snapshot code', 'comarine-storage-booking-with-woocommerce' ),
				'details' => '' !== $currency ? $currency : 'EUR',
			),
			array(
				'status'  => 'ok',
				'label'   => __( 'Configured add-ons', 'comarine-storage-booking-with-woocommerce' ),
				'details' => sprintf( __( '%1$d enabled of %2$d configured', 'comarine-storage-booking-with-woocommerce' ), $addons_enabled_count, count( $addons ) ),
			),
		);

		return array(
			array(
				'title'       => __( 'Required Setup', 'comarine-storage-booking-with-woocommerce' ),
				'description' => __( 'These items should be completed before opening bookings to customers.', 'comarine-storage-booking-with-woocommerce' ),
				'checks'      => array_merge( $dependency_checks, $required_checks ),
			),
			array(
				'title'       => __( 'Recommended Checks', 'comarine-storage-booking-with-woocommerce' ),
				'description' => __( 'These checks improve reliability and reduce checkout support issues.', 'comarine-storage-booking-with-woocommerce' ),
				'checks'      => $recommended_checks,
			),
			array(
				'title'       => __( 'Current Configuration Snapshot', 'comarine-storage-booking-with-woocommerce' ),
				'description' => __( 'Quick view of key plugin settings currently in use.', 'comarine-storage-booking-with-woocommerce' ),
				'checks'      => $current_config_checks,
			),
		);
	}

	/**
	 * Ensure overview-required post types are registered and auto-repair if possible.
	 *
	 * @since    1.0.22
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function ensure_required_post_types_registered_for_overview() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';

		$was_registered = post_type_exists( $post_type );
		if ( ! $was_registered ) {
			$this->register_storage_units_post_type_for_overview();
		}

		$is_registered = post_type_exists( $post_type );

		return array(
			array(
				'slug'            => $post_type,
				'label'           => __( 'Storage Units', 'comarine-storage-booking-with-woocommerce' ),
				'was_registered'  => $was_registered,
				'auto_registered' => ! $was_registered && $is_registered,
				'is_registered'   => $is_registered,
			),
		);
	}

	/**
	 * Attempt to register the Storage Units CPT when overview detects it missing.
	 *
	 * @since    1.0.22
	 *
	 * @return void
	 */
	private function register_storage_units_post_type_for_overview() {
		if ( function_exists( 'comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback' ) ) {
			comarine_storage_booking_with_woocommerce_register_storage_units_cpt_fallback();
		}

		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
		if ( post_type_exists( $post_type ) ) {
			return;
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Storage_Units' ) ) {
			$class_file = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-comarine-storage-booking-with-woocommerce-storage-units.php';
			if ( file_exists( $class_file ) ) {
				require_once $class_file;
			}
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Storage_Units' ) ) {
			return;
		}

		$storage_units = new Comarine_Storage_Booking_With_Woocommerce_Storage_Units( $this->plugin_name, $this->version );
		if ( method_exists( $storage_units, 'register_post_type' ) ) {
			$storage_units->register_post_type();
		}
	}

	/**
	 * Build the overview checklist check for required post type registrations.
	 *
	 * @since    1.0.22
	 *
	 * @param array<int, array<string, mixed>> $post_type_statuses Registration statuses.
	 * @return array<string, string>
	 */
	private function get_required_post_types_overview_check( $post_type_statuses ) {
		$total_required     = 0;
		$missing_labels     = array();
		$auto_fixed_labels  = array();

		foreach ( $post_type_statuses as $post_type_status ) {
			if ( ! is_array( $post_type_status ) ) {
				continue;
			}

			$total_required++;
			$label = isset( $post_type_status['label'] ) ? (string) $post_type_status['label'] : __( 'Post type', 'comarine-storage-booking-with-woocommerce' );

			if ( ! empty( $post_type_status['auto_registered'] ) ) {
				$auto_fixed_labels[] = $label;
			}

			if ( empty( $post_type_status['is_registered'] ) ) {
				$missing_labels[] = $label;
			}
		}

		$status = empty( $missing_labels ) ? 'ok' : 'error';
		if ( empty( $missing_labels ) ) {
			if ( ! empty( $auto_fixed_labels ) ) {
				$details = sprintf(
					/* translators: %s: comma-separated post type labels. */
					__( 'All required post types are available. Auto-registered during this overview load: %s.', 'comarine-storage-booking-with-woocommerce' ),
					implode( ', ', $auto_fixed_labels )
				);
			} else {
				$details = sprintf(
					/* translators: %d: number of required post types. */
					_n(
						'All %d required post type is registered.',
						'All %d required post types are registered.',
						max( 1, $total_required ),
						'comarine-storage-booking-with-woocommerce'
					),
					max( 1, $total_required )
				);
			}
		} else {
			$details = sprintf(
				/* translators: %s: comma-separated post type labels. */
				__( 'Missing required post type registrations: %s.', 'comarine-storage-booking-with-woocommerce' ),
				implode( ', ', $missing_labels )
			);
		}

		return array(
			'status'       => $status,
			'label'        => __( 'Required post types registered', 'comarine-storage-booking-with-woocommerce' ),
			'details'      => $details,
			'action_url'   => $this->get_storage_units_list_url(),
			'action_label' => __( 'Open Storage Units', 'comarine-storage-booking-with-woocommerce' ),
		);
	}

	/**
	 * Build unit readiness stats for the setup overview screen.
	 *
	 * @since    1.0.19
	 *
	 * @return array<string, int>
	 */
	private function get_storage_unit_setup_stats() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';

		$unit_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$stats = array(
			'total'        => count( $unit_ids ),
			'available'    => 0,
			'with_pricing' => 0,
		);

		foreach ( $unit_ids as $unit_id ) {
			$status = sanitize_key( (string) get_post_meta( (int) $unit_id, '_csu_status', true ) );
			if ( 'available' === $status ) {
				$stats['available']++;
			}

			foreach ( array( '_csu_price_monthly', '_csu_price_6m', '_csu_price_12m' ) as $price_key ) {
				$price_value = (string) get_post_meta( (int) $unit_id, $price_key, true );
				if ( '' !== $price_value && is_numeric( $price_value ) && (float) $price_value > 0 ) {
					$stats['with_pricing']++;
					break;
				}
			}
		}

		return $stats;
	}

	/**
	 * Find recent pages/posts using the storage units shortcode.
	 *
	 * @since    1.0.19
	 *
	 * @return array<int, array<string, string>>
	 */
	private function find_shortcode_usage_posts() {
		$results = array();
		$candidates = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		foreach ( $candidates as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			if ( ! function_exists( 'has_shortcode' ) || ! has_shortcode( (string) $post->post_content, 'comarine_storage_units' ) ) {
				continue;
			}

			$results[] = array(
				'title'     => (string) get_the_title( $post ),
				'edit_link' => (string) get_edit_post_link( $post->ID ),
			);

			if ( count( $results ) >= 5 ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Whether WooCommerce cart and checkout pages are configured.
	 *
	 * @since    1.0.19
	 *
	 * @return bool
	 */
	private function woocommerce_pages_look_configured() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		return (int) wc_get_page_id( 'cart' ) > 0 && (int) wc_get_page_id( 'checkout' ) > 0;
	}

	/**
	 * Whether custom bookings and audit tables are available.
	 *
	 * @since    1.0.19
	 *
	 * @return bool
	 */
	private function bookings_tables_look_ready() {
		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			return false;
		}

		return Comarine_Storage_Booking_With_Woocommerce_Bookings::table_exists()
			&& Comarine_Storage_Booking_With_Woocommerce_Bookings::audit_table_exists();
	}

	/**
	 * Convert a setup checklist status key to a human label.
	 *
	 * @since    1.0.19
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_overview_status_label( $status ) {
		switch ( $status ) {
			case 'ok':
				return __( 'OK', 'comarine-storage-booking-with-woocommerce' );
			case 'error':
				return __( 'Action Needed', 'comarine-storage-booking-with-woocommerce' );
			case 'warning':
			default:
				return __( 'Check', 'comarine-storage-booking-with-woocommerce' );
		}
	}

	/**
	 * Parse bookings admin filters from the current request.
	 *
	 * @since    1.0.7
	 *
	 * @return array<string, mixed>
	 */
	private function get_bookings_filters_from_request() {
		return array(
			'status_filter' => isset( $_REQUEST['status_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['status_filter'] ) ) : '',
			'order_id'      => isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0,
			'booking_id'    => isset( $_REQUEST['booking_id'] ) ? absint( $_REQUEST['booking_id'] ) : 0,
			'unit_post_id'  => isset( $_REQUEST['unit_post_id'] ) ? absint( $_REQUEST['unit_post_id'] ) : 0,
			'created_from'  => isset( $_REQUEST['created_from'] ) ? $this->normalize_admin_date_input( wp_unslash( $_REQUEST['created_from'] ) ) : '',
			'created_to'    => isset( $_REQUEST['created_to'] ) ? $this->normalize_admin_date_input( wp_unslash( $_REQUEST['created_to'] ) ) : '',
		);
	}

	/**
	 * Convert filter values into query args for redirect URLs.
	 *
	 * @since    1.0.9
	 *
	 * @param array<string, mixed> $filters Bookings filter values.
	 * @return array<string, scalar>
	 */
	private function get_bookings_filter_query_args( $filters ) {
		$filters = wp_parse_args(
			is_array( $filters ) ? $filters : array(),
			array(
				'status_filter' => '',
				'order_id'      => 0,
				'booking_id'    => 0,
				'unit_post_id'  => 0,
				'created_from'  => '',
				'created_to'    => '',
			)
		);

		$args = array();

		$status_filter = sanitize_key( (string) $filters['status_filter'] );
		if ( '' !== $status_filter ) {
			$args['status_filter'] = $status_filter;
		}

		foreach ( array( 'order_id', 'booking_id', 'unit_post_id' ) as $int_key ) {
			$value = absint( $filters[ $int_key ] );
			if ( $value > 0 ) {
				$args[ $int_key ] = $value;
			}
		}

		foreach ( array( 'created_from', 'created_to' ) as $date_key ) {
			$date_value = $this->normalize_admin_date_input( $filters[ $date_key ] );
			if ( '' !== $date_value ) {
				$args[ $date_key ] = $date_value;
			}
		}

		return $args;
	}

	/**
	 * Render bulk action controls and hidden filter fields for the bookings table.
	 *
	 * @since    1.0.9
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @param bool                 $top     Whether rendering the top toolbar.
	 * @return void
	 */
	private function render_bookings_bulk_action_controls( $filters, $top = true ) {
		if ( $top ) {
			$this->render_bookings_filters_hidden_inputs( $filters );
			wp_nonce_field( 'comarine_booking_bulk_action', '_comarine_bulk_nonce' );
		}

		$options = $this->get_bulk_booking_action_options();
		$position = $top ? 'top' : 'bottom';

		echo '<div class="tablenav top comarine-bookings-bulk-toolbar" style="height:auto;padding:8px 0;">';
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="comarine_booking_bulk_action_' . esc_attr( $position ) . '">' . esc_html__( 'Select bulk action', 'comarine-storage-booking-with-woocommerce' ) . '</label>';
		echo '<select name="comarine_booking_bulk_action_' . esc_attr( $position ) . '" id="comarine_booking_bulk_action_' . esc_attr( $position ) . '">';
		echo '<option value="">' . esc_html__( 'Bulk actions', 'comarine-storage-booking-with-woocommerce' ) . '</option>';
		foreach ( $options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<input class="comarine-bookings-bulk-note" type="text" name="comarine_bulk_note_' . esc_attr( $position ) . '" placeholder="' . esc_attr__( 'Optional audit note', 'comarine-storage-booking-with-woocommerce' ) . '" style="width:220px;max-width:35vw;" /> ';
		echo '<label class="comarine-bookings-bulk-confirm" style="margin-right:8px;"><input type="checkbox" name="comarine_bulk_confirm_' . esc_attr( $position ) . '" value="1" /> ' . esc_html__( 'Confirm destructive action', 'comarine-storage-booking-with-woocommerce' ) . '</label>';
		submit_button( __( 'Apply', 'comarine-storage-booking-with-woocommerce' ), 'secondary', 'comarine_apply_bulk_action', false );
		echo '</div>';
		echo '<div class="clear"></div>';
		echo '</div>';
	}

	/**
	 * Render hidden inputs to preserve current bookings filters across POST actions.
	 *
	 * @since    1.0.9
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @return void
	 */
	private function render_bookings_filters_hidden_inputs( $filters ) {
		$query_args = $this->get_bookings_filter_query_args( $filters );

		echo '<input type="hidden" name="page" value="comarine-storage-bookings" />';

		foreach ( $query_args as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( (string) $key ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
	}

	/**
	 * Normalize an admin date input (`dd/mm/yyyy` or `YYYY-MM-DD`) or return empty string.
	 *
	 * @since    1.0.7
	 *
	 * @param mixed $value Raw request value.
	 * @return string
	 */
	private function normalize_admin_date_input( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$year  = 0;
		$month = 0;
		$day   = 0;

		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			$year  = (int) $matches[1];
			$month = (int) $matches[2];
			$day   = (int) $matches[3];
		} elseif ( preg_match( '/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $matches ) ) {
			$day   = (int) $matches[1];
			$month = (int) $matches[2];
			$year  = (int) $matches[3];
		} else {
			return '';
		}

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Format an admin date filter value for the `dd/mm/yyyy` datepicker input.
	 *
	 * @since    1.0.15
	 *
	 * @param mixed $value Raw or normalized date value.
	 * @return string
	 */
	private function format_admin_date_input_value( $value ) {
		$normalized = $this->normalize_admin_date_input( $value );
		if ( '' === $normalized ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $normalized, wp_timezone() );
		if ( false === $date ) {
			return $normalized;
		}

		return $date->format( 'd/m/Y' );
	}

	/**
	 * Format a plugin date/datetime using WordPress date/time settings for admin UI.
	 *
	 * @since    1.0.15
	 *
	 * @param mixed  $value    Raw date/datetime string.
	 * @param string $fallback Fallback value for empty strings.
	 * @return string
	 */
	private function format_admin_datetime_display( $value, $fallback = '-' ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) {
			return $fallback;
		}

		$is_date_only = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
		$datetime     = $this->parse_wp_local_datetime_string( $value );

		if ( ! $datetime ) {
			return $value;
		}

		$format = $is_date_only ? get_option( 'date_format', 'Y-m-d' ) : trim( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ) );
		if ( '' === $format ) {
			$format = $is_date_only ? 'Y-m-d' : 'Y-m-d H:i';
		}

		return wp_date( $format, $datetime->getTimestamp(), wp_timezone() );
	}

	/**
	 * Parse a local (site timezone) date/datetime string stored by the plugin.
	 *
	 * @since    1.0.15
	 *
	 * @param string $value Date/datetime string.
	 * @return DateTimeImmutable|null
	 */
	private function parse_wp_local_datetime_string( $value ) {
		$value   = trim( (string) $value );
		$tz      = wp_timezone();
		$formats = array(
			'Y-m-d H:i:s',
			'Y-m-d H:i',
			'Y-m-d',
		);

		foreach ( $formats as $format ) {
			$datetime = DateTimeImmutable::createFromFormat( '!' . $format, $value, $tz );
			if ( false === $datetime ) {
				continue;
			}

			$errors = DateTimeImmutable::getLastErrors();
			if ( is_array( $errors ) && ( (int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0 ) ) {
				continue;
			}

			return $datetime;
		}

		return null;
	}

	/**
	 * Get customer display info for a booking row.
	 *
	 * @since    1.0.7
	 *
	 * @param object $booking Booking row.
	 * @return array{label:string,email:string}
	 */
	private function get_booking_customer_summary( $booking ) {
		$default = array(
			'label' => __( 'Guest', 'comarine-storage-booking-with-woocommerce' ),
			'email' => '',
		);

		if ( ! is_object( $booking ) ) {
			return $default;
		}

		$user_id = isset( $booking->user_id ) ? absint( $booking->user_id ) : 0;
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
			if ( $user instanceof WP_User ) {
				$label = ! empty( $user->display_name ) ? (string) $user->display_name : (string) $user->user_login;

				return array(
					'label' => $label,
					'email' => (string) $user->user_email,
				);
			}
		}

		$order_id = isset( $booking->order_id ) ? absint( $booking->order_id ) : 0;
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order && is_object( $order ) ) {
				$name = '';
				if ( method_exists( $order, 'get_formatted_billing_full_name' ) ) {
					$name = trim( (string) $order->get_formatted_billing_full_name() );
				}
				$email = method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '';

				if ( '' === $name ) {
					$name = '' !== $email ? $email : sprintf(
						/* translators: %d: order ID */
						__( 'Guest (Order #%d)', 'comarine-storage-booking-with-woocommerce' ),
						$order_id
					);
				}

				return array(
					'label' => $name,
					'email' => $email,
				);
			}
		}

		return $default;
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
			'page'      => 'comarine-storage-bookings',
		);

		return add_query_arg( array_merge( $base_args, $args ), admin_url( 'admin.php' ) );
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

		$done_count  = isset( $_GET['comarine_done'] ) ? absint( $_GET['comarine_done'] ) : 0;
		$total_count = isset( $_GET['comarine_total'] ) ? absint( $_GET['comarine_total'] ) : 0;

		if ( 'bulk_updated' === $notice && $total_count > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: updated count, 2: total selected count */
					__( 'Bulk action applied to %1$d of %2$d selected bookings.', 'comarine-storage-booking-with-woocommerce' ),
					$done_count,
					$total_count
				)
			) . '</p></div>';
			return;
		}

		if ( 'bulk_partial' === $notice && $total_count > 0 ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: updated count, 2: total selected count */
					__( 'Bulk action completed for %1$d of %2$d selected bookings. Some rows could not be updated.', 'comarine-storage-booking-with-woocommerce' ),
					$done_count,
					$total_count
				)
			) . '</p></div>';
			return;
		}

		$messages = array(
			'updated'          => array( 'success', __( 'Booking record updated.', 'comarine-storage-booking-with-woocommerce' ) ),
			'update_failed'    => array( 'error', __( 'Could not update the booking record.', 'comarine-storage-booking-with-woocommerce' ) ),
			'bulk_update_failed' => array( 'error', __( 'Bulk action did not update any selected bookings.', 'comarine-storage-booking-with-woocommerce' ) ),
			'bulk_none_selected' => array( 'warning', __( 'Select at least one booking before applying a bulk action.', 'comarine-storage-booking-with-woocommerce' ) ),
			'bulk_invalid_action' => array( 'error', __( 'Invalid bulk action requested.', 'comarine-storage-booking-with-woocommerce' ) ),
			'bulk_confirmation_required' => array( 'warning', __( 'Confirm the destructive bulk action before applying it.', 'comarine-storage-booking-with-woocommerce' ) ),
			'bulk_invalid_nonce'  => array( 'error', __( 'Security check failed for bulk booking action.', 'comarine-storage-booking-with-woocommerce' ) ),
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
	 * Build a secured CSV export URL for the bookings admin page.
	 *
	 * @since    1.0.6
	 *
	 * @param array<string, mixed> $filters Optional bookings filters.
	 * @return string
	 */
	private function build_bookings_export_link( $filters = array() ) {
		$args = array(
			'comarine_booking_admin_action' => 'export_csv',
			'_comarine_nonce'               => wp_create_nonce( 'comarine_booking_admin_action' ),
		);

		$filters = wp_parse_args(
			is_array( $filters ) ? $filters : array(),
			array(
				'status_filter' => '',
				'order_id'      => 0,
				'booking_id'    => 0,
				'unit_post_id'  => 0,
				'created_from'  => '',
				'created_to'    => '',
			)
		);

		$status_filter  = sanitize_key( (string) $filters['status_filter'] );
		$order_filter   = absint( $filters['order_id'] );
		$booking_filter = absint( $filters['booking_id'] );
		$unit_filter    = absint( $filters['unit_post_id'] );
		$created_from   = $this->normalize_admin_date_input( $filters['created_from'] );
		$created_to     = $this->normalize_admin_date_input( $filters['created_to'] );

		if ( '' !== $status_filter ) {
			$args['status_filter'] = $status_filter;
		}

		if ( $order_filter > 0 ) {
			$args['order_id'] = $order_filter;
		}

		if ( $booking_filter > 0 ) {
			$args['booking_id'] = $booking_filter;
		}

		if ( $unit_filter > 0 ) {
			$args['unit_post_id'] = $unit_filter;
		}

		if ( '' !== $created_from ) {
			$args['created_from'] = $created_from;
		}

		if ( '' !== $created_to ) {
			$args['created_to'] = $created_to;
		}

		return $this->get_bookings_page_url( $args );
	}

	/**
	 * Stream a CSV export of bookings (supports current filters).
	 *
	 * @since    1.0.6
	 *
	 * @return void
	 */
	private function stream_bookings_csv_export() {
		if ( ! current_user_can( $this->get_admin_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export bookings.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			wp_die( esc_html__( 'Bookings helper class is not loaded.', 'comarine-storage-booking-with-woocommerce' ) );
		}

		$filters        = $this->get_bookings_filters_from_request();
		$status_filter  = $filters['status_filter'];
		$order_filter   = $filters['order_id'];
		$booking_filter = $filters['booking_id'];
		$unit_filter    = $filters['unit_post_id'];
		$created_from   = $filters['created_from'];
		$created_to     = $filters['created_to'];

		$filename = 'comarine-bookings-export-' . wp_date( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv(
			$output,
			array(
				'booking_id',
				'unit_post_id',
				'unit_code',
				'unit_status',
				'customer',
				'customer_email',
				'order_id',
				'user_id',
				'duration_key',
				'requested_area_m2',
				'unit_capacity_m2',
				'start_ts',
				'end_ts',
				'price_total',
				'currency',
				'status',
				'lock_expires_ts',
				'created_ts',
				'updated_ts',
			)
		);

		$offset     = 0;
		$batch_size = 200;

		while ( true ) {
			$rows = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_bookings(
				array(
					'limit'       => $batch_size,
					'offset'      => $offset,
					'status'      => $status_filter,
					'order_id'    => $order_filter,
					'booking_id'  => $booking_filter,
					'unit_post_id'=> $unit_filter,
					'created_from'=> $created_from,
					'created_to'  => $created_to,
				)
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$unit_post_id = isset( $row->unit_post_id ) ? (int) $row->unit_post_id : 0;
				$unit_status  = $unit_post_id > 0 ? (string) get_post_meta( $unit_post_id, '_csu_status', true ) : '';
				$customer     = $this->get_booking_customer_summary( $row );

				fputcsv(
					$output,
					array(
						isset( $row->id ) ? (int) $row->id : 0,
						$unit_post_id,
						isset( $row->unit_code ) ? (string) $row->unit_code : '',
						$unit_status,
						$customer['label'],
						$customer['email'],
						isset( $row->order_id ) ? (int) $row->order_id : 0,
						isset( $row->user_id ) ? (int) $row->user_id : 0,
						isset( $row->duration_key ) ? (string) $row->duration_key : '',
						isset( $row->requested_area_m2 ) ? (string) $row->requested_area_m2 : '',
						isset( $row->unit_capacity_m2 ) ? (string) $row->unit_capacity_m2 : '',
						isset( $row->start_ts ) ? (string) $row->start_ts : '',
						isset( $row->end_ts ) ? (string) $row->end_ts : '',
						isset( $row->price_total ) ? (string) $row->price_total : '',
						isset( $row->currency ) ? (string) $row->currency : '',
						isset( $row->status ) ? (string) $row->status : '',
						isset( $row->lock_expires_ts ) ? (string) $row->lock_expires_ts : '',
						isset( $row->created_ts ) ? (string) $row->created_ts : '',
						isset( $row->updated_ts ) ? (string) $row->updated_ts : '',
					)
				);
			}

			if ( count( $rows ) < $batch_size ) {
				break;
			}

			$offset += $batch_size;
		}

		fclose( $output );
		exit;
	}

	/**
	 * Render recent audit log events below the bookings table.
	 *
	 * @since    1.0.6
	 *
	 * @param int $order_filter   Optional order filter.
	 * @param int $booking_filter Optional booking filter.
	 * @return void
	 */
	private function render_booking_audit_log_section( $order_filter = 0, $booking_filter = 0 ) {
		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) ) {
			return;
		}

		if ( ! Comarine_Storage_Booking_With_Woocommerce_Bookings::audit_table_exists() ) {
			echo '<h2>' . esc_html__( 'Recent audit events', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';
			echo '<p>' . esc_html__( 'Audit log table is not available yet. Reload the page after plugin upgrade schema runs.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			return;
		}

		$filters = array(
			'order_id'   => absint( $order_filter ),
			'booking_id' => absint( $booking_filter ),
		);
		$count   = Comarine_Storage_Booking_With_Woocommerce_Bookings::count_audit_events( $filters );
		$events   = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_audit_events(
			array_merge(
				$filters,
				array(
					'limit' => 20,
				)
			)
		);

		echo '<h2 class="comarine-admin-section-title" style="margin-top:24px;">' . esc_html__( 'Recent audit events', 'comarine-storage-booking-with-woocommerce' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Audit rows:', 'comarine-storage-booking-with-woocommerce' ) . '</strong> ' . esc_html( (string) $count ) . '</p>';

		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No audit events logged for the current filters yet.', 'comarine-storage-booking-with-woocommerce' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped comarine-bookings-audit-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Time', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Event', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Booking', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Unit', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Actor', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'comarine-storage-booking-with-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $events as $event ) {
			$booking_id  = isset( $event->booking_id ) ? (int) $event->booking_id : 0;
			$unit_post_id = isset( $event->unit_post_id ) ? (int) $event->unit_post_id : 0;
			$order_id    = isset( $event->order_id ) ? (int) $event->order_id : 0;
			$unit_link   = $unit_post_id > 0 ? get_edit_post_link( $unit_post_id ) : '';
			$order_link  = $order_id > 0 ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';
			$booking_link = $booking_id > 0 ? $this->get_bookings_page_url( array( 'booking_id' => $booking_id ) ) : '';
			$actor_label = isset( $event->actor_label ) ? (string) $event->actor_label : '';
			$message     = isset( $event->message ) ? (string) $event->message : '';

			echo '<tr>';
			echo '<td>' . esc_html( $this->format_admin_datetime_display( isset( $event->created_ts ) ? (string) $event->created_ts : '', '' ) ) . '</td>';
			echo '<td><code>' . esc_html( isset( $event->event_type ) ? (string) $event->event_type : '' ) . '</code></td>';
			echo '<td>';
			if ( $booking_link ) {
				echo '<a href="' . esc_url( $booking_link ) . '">#' . esc_html( (string) $booking_id ) . '</a>';
			} else {
				echo $booking_id > 0 ? '#' . esc_html( (string) $booking_id ) : '&ndash;';
			}
			echo '</td>';
			echo '<td>';
			if ( $unit_link ) {
				echo '<a href="' . esc_url( $unit_link ) . '">' . esc_html( get_the_title( $unit_post_id ) ?: (string) $unit_post_id ) . '</a>';
			} else {
				echo $unit_post_id > 0 ? esc_html( (string) $unit_post_id ) : '&ndash;';
			}
			echo '</td>';
			echo '<td>';
			if ( $order_link ) {
				echo '<a href="' . esc_url( $order_link ) . '">#' . esc_html( (string) $order_id ) . '</a>';
			} else {
				echo $order_id > 0 ? '#' . esc_html( (string) $order_id ) : '&ndash;';
			}
			echo '</td>';
			echo '<td>' . esc_html( '' !== $actor_label ? $actor_label : __( 'System', 'comarine-storage-booking-with-woocommerce' ) ) . '</td>';
			echo '<td>';
			echo '' !== $message ? esc_html( $message ) : '&ndash;';
			if ( isset( $event->context_json ) && ! empty( $event->context_json ) ) {
				echo '<div><code style="white-space:pre-wrap;word-break:break-word;">' . esc_html( (string) $event->context_json ) . '</code></div>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
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

		$actions[] = '<a class="button button-small" href="' . esc_url( $this->get_bookings_page_url( array( 'booking_id' => $booking_id ) ) ) . '">' . esc_html__( 'View', 'comarine-storage-booking-with-woocommerce' ) . '</a>';

		$current_status = isset( $booking->status ) ? sanitize_key( (string) $booking->status ) : '';
		$unit_post_id   = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;

		if ( ! in_array( $current_status, array( 'paid', 'cancelled', 'refunded', 'expired' ), true ) ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'mark_paid', __( 'Mark Paid', 'comarine-storage-booking-with-woocommerce' ) );
		}

		if ( 'cancelled' !== $current_status ) {
			$actions[] = $this->build_booking_action_link(
				$booking_id,
				'mark_cancelled',
				__( 'Cancel', 'comarine-storage-booking-with-woocommerce' ),
				array(),
				__( 'Cancel this booking? This will release the lock and update the booking status.', 'comarine-storage-booking-with-woocommerce' )
			);
		}

		if ( 'refunded' !== $current_status && ( isset( $booking->order_id ) && (int) $booking->order_id > 0 ) ) {
			$actions[] = $this->build_booking_action_link(
				$booking_id,
				'mark_refunded',
				__( 'Mark Refunded', 'comarine-storage-booking-with-woocommerce' ),
				array(),
				__( 'Mark this booking as refunded? Review the linked WooCommerce order before continuing.', 'comarine-storage-booking-with-woocommerce' )
			);
		}

		if ( $unit_post_id > 0 ) {
			$actions[] = $this->build_booking_action_link( $booking_id, 'set_unit_status', __( 'Unit: Reserved', 'comarine-storage-booking-with-woocommerce' ), array( 'unit_status' => 'reserved' ) );
			$actions[] = $this->build_booking_action_link( $booking_id, 'set_unit_status', __( 'Unit: Occupied', 'comarine-storage-booking-with-woocommerce' ), array( 'unit_status' => 'occupied' ) );
			$actions[] = $this->build_booking_action_link(
				$booking_id,
				'set_unit_status',
				__( 'Unit: Available', 'comarine-storage-booking-with-woocommerce' ),
				array( 'unit_status' => 'available' ),
				__( 'Set the linked unit to Available? This can reopen the unit for new bookings.', 'comarine-storage-booking-with-woocommerce' )
			);
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
	 * @param array  $extra_args       Extra query args.
	 * @param string $confirm_message  Optional confirmation prompt.
	 * @return string
	 */
	private function build_booking_action_link( $booking_id, $action, $label, $extra_args = array(), $confirm_message = '' ) {
		$args = array_merge(
			array(
				'booking_id'                    => absint( $booking_id ),
				'comarine_booking_admin_action' => sanitize_key( $action ),
				'_comarine_nonce'               => wp_create_nonce( 'comarine_booking_admin_action' ),
			),
			$extra_args
		);

		$attributes = '';
		if ( '' !== $confirm_message ) {
			$attributes .= ' onclick="return window.confirm(\'' . esc_js( (string) $confirm_message ) . '\');"';
		}

		return '<a class="button button-small" href="' . esc_url( $this->get_bookings_page_url( $args ) ) . '"' . $attributes . '>' . esc_html( $label ) . '</a>';
	}

	/**
	 * Write an audit log event linked to a booking.
	 *
	 * @since    1.0.6
	 *
	 * @param object               $booking     Booking row.
	 * @param string               $event_type  Event type.
	 * @param string               $message     Human-readable message.
	 * @param array<string, mixed> $context     Optional structured context.
	 * @return void
	 */
	private function log_booking_audit_event( $booking, $event_type, $message, $context = array() ) {
		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) || ! is_object( $booking ) ) {
			return;
		}

		Comarine_Storage_Booking_With_Woocommerce_Bookings::log_audit_event(
			array(
				'booking_id'    => isset( $booking->id ) ? (int) $booking->id : 0,
				'unit_post_id'  => isset( $booking->unit_post_id ) ? (int) $booking->unit_post_id : 0,
				'order_id'      => isset( $booking->order_id ) ? (int) $booking->order_id : 0,
				'event_type'    => $event_type,
				'message'       => $message,
				'context'       => $context,
				'actor_user_id' => get_current_user_id(),
				'actor_label'   => $this->get_current_actor_label(),
			)
		);
	}

	/**
	 * Get current admin actor label for audit entries.
	 *
	 * @since    1.0.6
	 *
	 * @return string
	 */
	private function get_current_actor_label() {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return 'wp-admin';
		}

		$label = (string) $user->user_login;
		if ( ! empty( $user->display_name ) && $user->display_name !== $user->user_login ) {
			$label .= ' (' . (string) $user->display_name . ')';
		}

		return $label;
	}

	/**
	 * Update the linked unit status for a booking.
	 *
	 * @since    1.0.5
	 *
	 * @param object               $booking     Booking row.
	 * @param string               $unit_status Unit status key.
	 * @param array<string, mixed> $audit_args  Optional audit metadata overrides.
	 * @return bool
	 */
	private function update_booking_unit_status( $booking, $unit_status, $audit_args = array() ) {
		$unit_post_id = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;
		$unit_status  = sanitize_key( (string) $unit_status );
		$audit_args   = wp_parse_args(
			is_array( $audit_args ) ? $audit_args : array(),
			array(
				'event_type'      => 'admin_set_unit_status',
				'action'          => 'set_unit_status',
				'note'            => '',
				'message_prefix'  => __( 'Admin', 'comarine-storage-booking-with-woocommerce' ),
			)
		);

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

		$updated = false !== update_post_meta( $unit_post_id, '_csu_status', $unit_status );
		if ( $updated ) {
			$note = sanitize_text_field( (string) $audit_args['note'] );
			$this->log_booking_audit_event(
				$booking,
				sanitize_key( (string) $audit_args['event_type'] ),
				sprintf(
					/* translators: 1: previous unit status, 2: new unit status */
					__( '%3$s changed unit status from %1$s to %2$s.', 'comarine-storage-booking-with-woocommerce' ),
					$current ? ucfirst( $current ) : '-',
					ucfirst( $unit_status ),
					(string) $audit_args['message_prefix']
				) . ( '' !== $note ? ' ' . sprintf( __( 'Note: %s', 'comarine-storage-booking-with-woocommerce' ), $note ) : '' ),
				array(
					'action'               => sanitize_key( (string) $audit_args['action'] ),
					'previous_unit_status' => $current,
					'new_unit_status'      => $unit_status,
					'note'                 => $note,
				)
			);
		}

		return $updated;
	}

	/**
	 * Sync capacity-managed unit status after booking status changes in wp-admin actions.
	 *
	 * Capacity-managed units are only marked reserved/occupied when fully booked.
	 * Units manually set to maintenance/archived are left unchanged.
	 *
	 * @since    1.0.26
	 *
	 * @param object $booking     Booking row.
	 * @param string $full_status Status to use when the unit is fully booked.
	 * @return array<string, mixed>
	 */
	private function sync_capacity_managed_unit_status_after_booking_change( $booking, $full_status = 'reserved' ) {
		$result = array(
			'handled'         => false,
			'updated'         => false,
			'new_unit_status' => '',
		);

		if ( ! class_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings' ) || ! is_object( $booking ) ) {
			return $result;
		}

		$unit_post_id = isset( $booking->unit_post_id ) ? absint( $booking->unit_post_id ) : 0;
		if ( $unit_post_id <= 0 || ! method_exists( 'Comarine_Storage_Booking_With_Woocommerce_Bookings', 'get_unit_capacity_availability' ) ) {
			return $result;
		}

		$snapshot = Comarine_Storage_Booking_With_Woocommerce_Bookings::get_unit_capacity_availability( $unit_post_id );
		if ( empty( $snapshot['is_capacity_managed'] ) ) {
			return $result;
		}

		$result['handled'] = true;

		$current_status = sanitize_key( (string) get_post_meta( $unit_post_id, '_csu_status', true ) );
		if ( in_array( $current_status, array( 'maintenance', 'archived' ), true ) ) {
			$result['new_unit_status'] = $current_status;
			return $result;
		}

		$full_status = sanitize_key( (string) $full_status );
		if ( ! in_array( $full_status, array( 'reserved', 'occupied' ), true ) ) {
			$full_status = 'reserved';
		}

		$new_unit_status             = ! empty( $snapshot['is_full'] ) ? $full_status : 'available';
		$result['new_unit_status']   = $new_unit_status;

		if ( $current_status === $new_unit_status ) {
			return $result;
		}

		$result['updated'] = false !== update_post_meta( $unit_post_id, '_csu_status', $new_unit_status );

		return $result;
	}

	/**
	 * Format booking area summary for admin list/detail output.
	 *
	 * @since    1.0.26
	 *
	 * @param object $booking       Booking row object.
	 * @param bool   $include_units Whether to append `m2` units in the rendered value.
	 * @return string
	 */
	private function format_booking_area_summary( $booking, $include_units = false ) {
		if ( ! is_object( $booking ) ) {
			return '-';
		}

		$requested_area_m2 = ( isset( $booking->requested_area_m2 ) && is_numeric( $booking->requested_area_m2 ) ) ? (float) $booking->requested_area_m2 : 0.0;
		$unit_capacity_m2  = ( isset( $booking->unit_capacity_m2 ) && is_numeric( $booking->unit_capacity_m2 ) ) ? (float) $booking->unit_capacity_m2 : 0.0;

		if ( $requested_area_m2 <= 0 && $unit_capacity_m2 <= 0 ) {
			return '-';
		}

		if ( $requested_area_m2 <= 0 && $unit_capacity_m2 > 0 ) {
			return sprintf(
				/* translators: %s: unit capacity in m2 */
				$include_units ? __( 'Full unit (%s m2)', 'comarine-storage-booking-with-woocommerce' ) : __( 'Full unit (%s)', 'comarine-storage-booking-with-woocommerce' ),
				$this->format_area_m2_value( $unit_capacity_m2 )
			);
		}

		$requested_label = $this->format_area_m2_value( $requested_area_m2 ) . ( $include_units ? ' m2' : '' );
		if ( $unit_capacity_m2 > 0 ) {
			return $requested_label . ' / ' . $this->format_area_m2_value( $unit_capacity_m2 ) . ( $include_units ? ' m2' : '' );
		}

		return $requested_label;
	}

	/**
	 * Format an area value in m2 for admin display.
	 *
	 * @since    1.0.26
	 *
	 * @param float $area Area in square meters.
	 * @return string
	 */
	private function format_area_m2_value( $area ) {
		return number_format_i18n( (float) $area, 2 );
	}

	/**
	 * Determine whether the current admin screen is related to this plugin.
	 *
	 * @since    1.0.4
	 *
	 * @return bool
	 */
	private function is_plugin_admin_screen() {
		$post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
		$legacy_post_type = defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_LEGACY_UNIT_POST_TYPE
			: 'comarine_storage_unit';

		// Fallback to request inspection because some environments report
		// unexpected submenu screen IDs for plugin pages.
		$request_page = isset( $_GET['page'] ) ? trim( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( '' !== $request_page && 0 === strpos( $request_page, 'comarine-storage-' ) ) {
			return true;
		}

		$request_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( in_array( $request_post_type, array( $post_type, $legacy_post_type ), true ) ) {
			return true;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->id ) ) {
			return false;
		}

		$targets = array(
			'edit-' . $post_type,
			$post_type,
			'toplevel_page_comarine-storage-bookings',
			'comarine-storage-bookings_page_' . $this->get_overview_page_slug(),
			'comarine-storage-bookings_page_' . $this->get_settings_page_slug(),
		);

		if ( in_array( $screen->id, $targets, true ) ) {
			return true;
		}

		$screen_id = (string) $screen->id;
		$screen_base = isset( $screen->base ) ? (string) $screen->base : '';
		if ( false !== strpos( $screen_id, '_page_comarine-storage-' ) || false !== strpos( $screen_base, '_page_comarine-storage-' ) ) {
			return true;
		}

		return false;
	}

}

