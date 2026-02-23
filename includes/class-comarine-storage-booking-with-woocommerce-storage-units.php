<?php

/**
 * Storage unit custom post type and admin metadata.
 *
 * @package    Comarine_Storage_Booking_With_Woocommerce
 * @subpackage Comarine_Storage_Booking_With_Woocommerce/includes
 */

/**
 * Registers and manages storage unit data.
 */
class Comarine_Storage_Booking_With_Woocommerce_Storage_Units {

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
	 * @since 1.0.2
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name = 'comarine-storage-booking-with-woocommerce', $version = '1.0.2' ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Get the storage unit post type slug.
	 *
	 * @since 1.0.2
	 *
	 * @return string
	 */
	public function get_post_type() {
		return defined( 'COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE' )
			? COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE
			: 'comarine_storageunit';
	}

	/**
	 * Register the storage unit custom post type.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public function register_post_type() {
		if ( post_type_exists( $this->get_post_type() ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'Storage Units', 'comarine-storage-booking-with-woocommerce' ),
			'singular_name'      => __( 'Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'menu_name'          => __( 'Storage Units', 'comarine-storage-booking-with-woocommerce' ),
			'name_admin_bar'     => __( 'Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'add_new'            => __( 'Add New', 'comarine-storage-booking-with-woocommerce' ),
			'add_new_item'       => __( 'Add New Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'edit_item'          => __( 'Edit Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'new_item'           => __( 'New Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'view_item'          => __( 'View Storage Unit', 'comarine-storage-booking-with-woocommerce' ),
			'search_items'       => __( 'Search Storage Units', 'comarine-storage-booking-with-woocommerce' ),
			'not_found'          => __( 'No storage units found.', 'comarine-storage-booking-with-woocommerce' ),
			'not_found_in_trash' => __( 'No storage units found in Trash.', 'comarine-storage-booking-with-woocommerce' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => 'comarine-storage-bookings',
			'show_in_rest'       => true,
			'has_archive'        => true,
			'rewrite'            => array( 'slug' => 'storage-units' ),
			'menu_icon'          => 'dashicons-store',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
		);

		$registered = register_post_type( $this->get_post_type(), $args );
		if ( is_wp_error( $registered ) ) {
			return;
		}
	}

	/**
	 * Provide a plugin single template for Storage Units when the theme has no CPT-specific template.
	 *
	 * @since 1.0.32
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function filter_single_storage_unit_template( $template ) {
		if ( is_admin() ) {
			return $template;
		}

		if ( ! function_exists( 'is_singular' ) || ! is_singular( $this->get_post_type() ) ) {
			return $template;
		}

		$theme_specific_template = locate_template( array( 'single-' . $this->get_post_type() . '.php' ) );
		if ( '' !== (string) $theme_specific_template ) {
			return $template;
		}

		$plugin_template = plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/comarine-storage-booking-with-woocommerce-single-storage-unit.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Add meta boxes for storage unit details.
	 *
	 * @since 1.0.2
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'comarine_storage_unit_details',
			__( 'Storage Unit Details', 'comarine-storage-booking-with-woocommerce' ),
			array( $this, 'render_details_meta_box' ),
			$this->get_post_type(),
			'normal',
			'high'
		);
	}

	/**
	 * Render the unit details meta box.
	 *
	 * @since 1.0.2
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_details_meta_box( $post ) {
		wp_nonce_field( 'comarine_storage_unit_meta', 'comarine_storage_unit_meta_nonce' );

		$fields = $this->get_meta_field_definitions();

		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $meta_key => $field ) {
			$value = get_post_meta( $post->ID, $meta_key, true );
			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label></th>';
			echo '<td>';

			if ( 'select' === $field['type'] ) {
				echo '<select class="regular-text" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '">';
				foreach ( $field['options'] as $option_value => $option_label ) {
					echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'gallery' === $field['type'] ) {
				$gallery_ids = $this->parse_gallery_image_ids( $value );

				echo '<div class="comarine-storage-unit-gallery-field" data-comarine-storage-unit-gallery-field>';
				echo '<input type="hidden" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" class="comarine-storage-unit-gallery-field__input" value="' . esc_attr( implode( ',', $gallery_ids ) ) . '" />';
				echo '<div class="comarine-storage-unit-gallery-field__actions">';
				echo '<button type="button" class="button button-secondary comarine-storage-unit-gallery-field__select">';
				echo esc_html( ! empty( $gallery_ids ) ? __( 'Edit Gallery Images', 'comarine-storage-booking-with-woocommerce' ) : __( 'Select Gallery Images', 'comarine-storage-booking-with-woocommerce' ) );
				echo '</button>';
				echo '<button type="button" class="button-link-delete comarine-storage-unit-gallery-field__clear" ' . ( empty( $gallery_ids ) ? 'style="display:none;"' : '' ) . '>';
				echo esc_html__( 'Clear gallery', 'comarine-storage-booking-with-woocommerce' );
				echo '</button>';
				echo '</div>';

				echo '<ul class="comarine-storage-unit-gallery-field__preview" aria-live="polite">';
				foreach ( $gallery_ids as $attachment_id ) {
					if ( ! wp_attachment_is_image( $attachment_id ) ) {
						continue;
					}

					$thumb_html = wp_get_attachment_image(
						$attachment_id,
						'thumbnail',
						false,
						array(
							'class' => 'comarine-storage-unit-gallery-field__preview-image',
						)
					);
					if ( '' === (string) $thumb_html ) {
						continue;
					}

					$image_label = get_the_title( $attachment_id );
					if ( '' === (string) $image_label ) {
						$image_label = sprintf(
							/* translators: %d: attachment ID */
							__( 'Image #%d', 'comarine-storage-booking-with-woocommerce' ),
							(int) $attachment_id
						);
					}

					echo '<li class="comarine-storage-unit-gallery-field__preview-item" data-image-id="' . esc_attr( (string) $attachment_id ) . '">';
					echo '<span class="comarine-storage-unit-gallery-field__preview-thumb">' . $thumb_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<span class="comarine-storage-unit-gallery-field__preview-label">' . esc_html( $image_label ) . '</span>';
					echo '<button type="button" class="button-link-delete comarine-storage-unit-gallery-field__remove" aria-label="' . esc_attr__( 'Remove image from gallery', 'comarine-storage-booking-with-woocommerce' ) . '">';
					echo esc_html__( 'Remove', 'comarine-storage-booking-with-woocommerce' );
					echo '</button>';
					echo '</li>';
				}
				echo '</ul>';
				echo '</div>';
			} else {
				$step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
				echo '<input class="regular-text" type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '"' . $step . ' />';
			}

			if ( ! empty( $field['description'] ) ) {
				echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
			}

			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Save unit metadata.
	 *
	 * @since 1.0.2
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function save_unit_meta( $post_id, $post, $update = false ) {
		unset( $update );

		if ( ! isset( $_POST['comarine_storage_unit_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['comarine_storage_unit_meta_nonce'] ) ), 'comarine_storage_unit_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( $this->get_post_type() !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->get_meta_field_definitions() as $meta_key => $field ) {
			$raw_value = isset( $_POST[ $meta_key ] ) ? wp_unslash( $_POST[ $meta_key ] ) : '';
			$value     = $this->sanitize_meta_value( $raw_value, $field );

			if ( '' === $value && ! empty( $field['allow_empty_delete'] ) ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Customize admin list columns for storage units.
	 *
	 * @since 1.0.2
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function filter_admin_columns( $columns ) {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'title' === $key ) {
				$updated['csu_unit_code'] = __( 'Unit Code', 'comarine-storage-booking-with-woocommerce' );
				$updated['csu_size_m2']   = __( 'Size (m2)', 'comarine-storage-booking-with-woocommerce' );
				$updated['csu_floor']     = __( 'Floor', 'comarine-storage-booking-with-woocommerce' );
				$updated['csu_status']    = __( 'Status', 'comarine-storage-booking-with-woocommerce' );
				$updated['csu_pricing']   = __( 'Pricing', 'comarine-storage-booking-with-woocommerce' );
			}
		}

		return $updated;
	}

	/**
	 * Render custom admin columns.
	 *
	 * @since 1.0.2
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'csu_unit_code':
				echo esc_html( get_post_meta( $post_id, '_csu_unit_code', true ) );
				break;

			case 'csu_size_m2':
				echo esc_html( get_post_meta( $post_id, '_csu_size_m2', true ) );
				break;

			case 'csu_floor':
				echo esc_html( get_post_meta( $post_id, '_csu_floor', true ) );
				break;

			case 'csu_status':
				$status  = (string) get_post_meta( $post_id, '_csu_status', true );
				$options = $this->get_status_options();
				echo esc_html( isset( $options[ $status ] ) ? $options[ $status ] : $status );
				break;

			case 'csu_pricing':
				$daily   = (string) get_post_meta( $post_id, '_csu_price_daily', true );
				$monthly = (string) get_post_meta( $post_id, '_csu_price_monthly', true );
				$price_6 = (string) get_post_meta( $post_id, '_csu_price_6m', true );
				$price_12 = (string) get_post_meta( $post_id, '_csu_price_12m', true );

				echo esc_html(
					sprintf(
						'D: %1$s | M: %2$s | 6M: %3$s | 12M: %4$s',
						$daily ?: '-',
						$monthly ?: '-',
						$price_6 ?: '-',
						$price_12 ?: '-'
					)
				);
				break;
		}
	}

	/**
	 * Get meta field definitions.
	 *
	 * @since 1.0.2
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_meta_field_definitions() {
		return array(
			'_csu_unit_code'      => array(
				'label'               => __( 'Unit code / ID', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'text',
				'description'         => __( 'Human-readable unit identifier (e.g. A-101).', 'comarine-storage-booking-with-woocommerce' ),
				'allow_empty_delete'  => true,
			),
			'_csu_size_m2'        => array(
				'label'               => __( 'Size (m2)', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'number',
				'step'                => '0.01',
				'allow_empty_delete'  => true,
			),
			'_csu_dimensions'     => array(
				'label'               => __( 'Dimensions', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'text',
				'description'         => __( 'Example: 2.5 x 2', 'comarine-storage-booking-with-woocommerce' ),
				'allow_empty_delete'  => true,
			),
			'_csu_gallery_image_ids' => array(
				'label'               => __( 'Unit image gallery', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'gallery',
				'description'         => __( 'Optional gallery images for the unit single page. The featured image is used as the main cover image, and gallery images appear as thumbnails/lightbox images.', 'comarine-storage-booking-with-woocommerce' ),
				'allow_empty_delete'  => true,
			),
			'_csu_floor'          => array(
				'label'               => __( 'Floor / Level', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'text',
				'allow_empty_delete'  => true,
			),
			'_csu_price_daily'    => array(
				'label'               => __( 'Daily price', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'number',
				'step'                => '0.01',
				'description'         => __( 'Full-unit price per day. Enables date-range booking (start/end dates) on the frontend.', 'comarine-storage-booking-with-woocommerce' ),
				'allow_empty_delete'  => true,
			),
			'_csu_price_monthly'  => array(
				'label'               => __( 'Monthly price', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'number',
				'step'                => '0.01',
				'allow_empty_delete'  => true,
			),
			'_csu_price_6m'       => array(
				'label'               => __( '6-month price', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'number',
				'step'                => '0.01',
				'allow_empty_delete'  => true,
			),
			'_csu_price_12m'      => array(
				'label'               => __( 'Annual price', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'number',
				'step'                => '0.01',
				'allow_empty_delete'  => true,
			),
			'_csu_status'         => array(
				'label'               => __( 'Status', 'comarine-storage-booking-with-woocommerce' ),
				'type'                => 'select',
				'options'             => $this->get_status_options(),
			),
		);
	}

	/**
	 * Get supported unit status options.
	 *
	 * @since 1.0.2
	 *
	 * @return array<string, string>
	 */
	private function get_status_options() {
		return array(
			'available'   => __( 'Available', 'comarine-storage-booking-with-woocommerce' ),
			'reserved'    => __( 'Reserved', 'comarine-storage-booking-with-woocommerce' ),
			'occupied'    => __( 'Occupied', 'comarine-storage-booking-with-woocommerce' ),
			'maintenance' => __( 'Maintenance', 'comarine-storage-booking-with-woocommerce' ),
			'archived'    => __( 'Archived', 'comarine-storage-booking-with-woocommerce' ),
		);
	}

	/**
	 * Sanitize a meta field value based on type.
	 *
	 * @since 1.0.2
	 *
	 * @param mixed $raw_value Raw submitted value.
	 * @param array $field     Field definition.
	 * @return string
	 */
	private function sanitize_meta_value( $raw_value, $field ) {
		$raw_value = is_string( $raw_value ) ? trim( $raw_value ) : '';

		if ( 'select' === $field['type'] ) {
			$options = isset( $field['options'] ) ? $field['options'] : array();
			return isset( $options[ $raw_value ] ) ? $raw_value : 'available';
		}

		if ( 'gallery' === $field['type'] ) {
			$ids           = $this->parse_gallery_image_ids( $raw_value );
			$sanitized_ids = array();

			foreach ( $ids as $attachment_id ) {
				if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
					$sanitized_ids[] = (int) $attachment_id;
				}
			}

			return implode( ',', array_values( array_unique( $sanitized_ids ) ) );
		}

		if ( 'number' === $field['type'] ) {
			if ( '' === $raw_value ) {
				return '';
			}

			if ( ! is_numeric( $raw_value ) ) {
				return '';
			}

			return (string) round( (float) $raw_value, 2 );
		}

		return sanitize_text_field( $raw_value );
	}

	/**
	 * Parse a CSV list of gallery image IDs.
	 *
	 * @since 1.0.40
	 *
	 * @param mixed $raw_value Raw stored/submitted gallery value.
	 * @return int[]
	 */
	private function parse_gallery_image_ids( $raw_value ) {
		if ( is_array( $raw_value ) ) {
			$parts = $raw_value;
		} else {
			$raw_value = is_string( $raw_value ) ? trim( $raw_value ) : '';
			if ( '' === $raw_value ) {
				return array();
			}

			$parts = preg_split( '/[\s,]+/', $raw_value );
		}

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$ids = array();
		foreach ( $parts as $part ) {
			$attachment_id = absint( $part );
			if ( $attachment_id > 0 ) {
				$ids[ $attachment_id ] = $attachment_id;
			}
		}

		return array_values( $ids );
	}
}
