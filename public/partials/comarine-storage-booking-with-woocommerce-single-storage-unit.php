<?php
/**
 * Frontend single template for Storage Unit posts.
 *
 * @package Comarine_Storage_Booking_With_Woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$comarine_unit_status_labels = array(
	'available'   => __( 'Available', 'comarine-storage-booking-with-woocommerce' ),
	'reserved'    => __( 'Reserved', 'comarine-storage-booking-with-woocommerce' ),
	'occupied'    => __( 'Occupied', 'comarine-storage-booking-with-woocommerce' ),
	'maintenance' => __( 'Maintenance', 'comarine-storage-booking-with-woocommerce' ),
	'archived'    => __( 'Archived', 'comarine-storage-booking-with-woocommerce' ),
);

$comarine_format_money = static function ( $amount ) {
	$currency = 'EUR';
	if ( function_exists( 'comarine_storage_booking_with_woocommerce_get_setting' ) ) {
		$currency = (string) comarine_storage_booking_with_woocommerce_get_setting( 'currency', 'EUR' );
	}

	$currency = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $currency ) );
	if ( '' === $currency ) {
		$currency = 'EUR';
	}

	return number_format_i18n( (float) $amount, 2 ) . ' ' . $currency;
};

$comarine_format_area = static function ( $area ) {
	return number_format_i18n( (float) $area, 2 );
};

$comarine_parse_features = static function ( $raw ) {
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
};

$comarine_duration_labels = array(
	'daily'   => __( 'Daily', 'comarine-storage-booking-with-woocommerce' ),
	'monthly' => __( 'Monthly', 'comarine-storage-booking-with-woocommerce' ),
	'6m'      => __( '6 Months', 'comarine-storage-booking-with-woocommerce' ),
	'12m'     => __( '12 Months', 'comarine-storage-booking-with-woocommerce' ),
);

get_header();
?>
<main id="primary" class="comarine-storage-unit-single">
	<div class="comarine-storage-unit-single__shell">
		<?php if ( function_exists( 'wc_print_notices' ) ) : ?>
			<div class="comarine-storage-unit-single__notices">
				<?php wc_print_notices(); ?>
			</div>
		<?php endif; ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$unit_id         = get_the_ID();
			$unit_code       = (string) get_post_meta( $unit_id, '_csu_unit_code', true );
			$unit_status     = sanitize_key( (string) get_post_meta( $unit_id, '_csu_status', true ) );
			$unit_status     = '' !== $unit_status ? $unit_status : 'available';
			$unit_size_m2    = (string) get_post_meta( $unit_id, '_csu_size_m2', true );
			$unit_dimensions = (string) get_post_meta( $unit_id, '_csu_dimensions', true );
			$unit_floor      = (string) get_post_meta( $unit_id, '_csu_floor', true );
			$raw_features    = get_post_meta( $unit_id, '_csu_features', true );
			$unit_features   = $comarine_parse_features( $raw_features );
			$archive_link    = get_post_type_archive_link( COMARINE_STORAGE_BOOKING_WITH_WOOCOMMERCE_UNIT_POST_TYPE );
			$status_label    = isset( $comarine_unit_status_labels[ $unit_status ] ) ? $comarine_unit_status_labels[ $unit_status ] : ucfirst( $unit_status );
			$summary_text    = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( (string) get_the_content() ), 30 );

			$duration_prices = array();
			$duration_keys   = array(
				'daily'   => '_csu_price_daily',
				'monthly' => '_csu_price_monthly',
				'6m'      => '_csu_price_6m',
				'12m'     => '_csu_price_12m',
			);
			foreach ( $duration_keys as $duration_key => $meta_key ) {
				$raw_price = get_post_meta( $unit_id, $meta_key, true );
				if ( '' === (string) $raw_price || ! is_numeric( $raw_price ) ) {
					continue;
				}

				$price = round( (float) $raw_price, 2 );
				if ( $price <= 0 ) {
					continue;
				}

				$duration_prices[ $duration_key ] = $price;
			}

			$from_price       = ! empty( $duration_prices ) ? min( $duration_prices ) : 0.0;
			$booking_shortcode = sprintf(
				'[comarine_storage_units include_ids="%d" limit="1" show_all="1" show_filters="0" checkout="1" card_action="book" wrapper_class="comarine-storage-units--single-template-booking"]',
				(int) $unit_id
			);

			$detail_rows = array(
				array(
					'label' => __( 'Unit code', 'comarine-storage-booking-with-woocommerce' ),
					'value' => '' !== $unit_code ? $unit_code : (string) $unit_id,
				),
				array(
					'label' => __( 'Availability', 'comarine-storage-booking-with-woocommerce' ),
					'value' => $status_label,
				),
				array(
					'label' => __( 'Size', 'comarine-storage-booking-with-woocommerce' ),
					'value' => '' !== $unit_size_m2 ? $comarine_format_area( $unit_size_m2 ) . ' m2' : __( 'Not specified', 'comarine-storage-booking-with-woocommerce' ),
				),
				array(
					'label' => __( 'Dimensions', 'comarine-storage-booking-with-woocommerce' ),
					'value' => '' !== $unit_dimensions ? $unit_dimensions : __( 'Not specified', 'comarine-storage-booking-with-woocommerce' ),
				),
				array(
					'label' => __( 'Floor / level', 'comarine-storage-booking-with-woocommerce' ),
					'value' => '' !== $unit_floor ? $unit_floor : __( 'Not specified', 'comarine-storage-booking-with-woocommerce' ),
				),
				array(
					'label' => __( 'Booking options', 'comarine-storage-booking-with-woocommerce' ),
					'value' => ! empty( $duration_prices ) ? implode( ', ', array_map(
						static function ( $key ) use ( $comarine_duration_labels ) {
							return isset( $comarine_duration_labels[ $key ] ) ? $comarine_duration_labels[ $key ] : $key;
						},
						array_keys( $duration_prices )
					) ) : __( 'Pricing not configured yet', 'comarine-storage-booking-with-woocommerce' ),
				),
			);
			?>

			<article <?php post_class( 'comarine-storage-unit-single__article comarine-status-' . sanitize_html_class( $unit_status ) ); ?>>
				<?php if ( $archive_link ) : ?>
					<div class="comarine-storage-unit-single__breadcrumbs">
						<a href="<?php echo esc_url( $archive_link ); ?>"><?php esc_html_e( 'All Storage Units', 'comarine-storage-booking-with-woocommerce' ); ?></a>
						<span aria-hidden="true">/</span>
						<span><?php the_title(); ?></span>
					</div>
				<?php endif; ?>

				<section class="comarine-storage-unit-single__hero">
					<div class="comarine-storage-unit-single__media">
						<div class="comarine-storage-unit-single__media-inner">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'large', array( 'class' => 'comarine-storage-unit-single__image', 'loading' => 'eager' ) ); ?>
							<?php else : ?>
								<div class="comarine-storage-unit-single__placeholder" aria-hidden="true"></div>
							<?php endif; ?>
							<span class="comarine-storage-unit-single__status-badge"><?php echo esc_html( $status_label ); ?></span>
						</div>
					</div>

					<div class="comarine-storage-unit-single__summary">
						<p class="comarine-storage-unit-single__eyebrow"><?php esc_html_e( 'Storage Unit', 'comarine-storage-booking-with-woocommerce' ); ?></p>
						<h1 class="comarine-storage-unit-single__title"><?php the_title(); ?></h1>
						<?php if ( '' !== $summary_text ) : ?>
							<p class="comarine-storage-unit-single__lead"><?php echo esc_html( $summary_text ); ?></p>
						<?php endif; ?>

						<div class="comarine-storage-unit-single__chips">
							<span class="comarine-storage-unit-single__chip"><?php echo esc_html__( 'Code', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( '' !== $unit_code ? $unit_code : (string) $unit_id ); ?></span>
							<?php if ( '' !== $unit_size_m2 ) : ?>
								<span class="comarine-storage-unit-single__chip"><?php echo esc_html( $comarine_format_area( $unit_size_m2 ) ); ?> m2</span>
							<?php endif; ?>
							<?php if ( '' !== $unit_floor ) : ?>
								<span class="comarine-storage-unit-single__chip"><?php echo esc_html__( 'Floor', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( $unit_floor ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $unit_dimensions ) : ?>
								<span class="comarine-storage-unit-single__chip"><?php echo esc_html__( 'Dimensions', 'comarine-storage-booking-with-woocommerce' ) . ': ' . esc_html( $unit_dimensions ); ?></span>
							<?php endif; ?>
						</div>

						<div class="comarine-storage-unit-single__hero-actions">
							<a class="comarine-storage-unit-single__button" href="#comarine-unit-booking"><?php esc_html_e( 'Book This Unit', 'comarine-storage-booking-with-woocommerce' ); ?></a>
							<?php if ( $archive_link ) : ?>
								<a class="comarine-storage-unit-single__button is-ghost" href="<?php echo esc_url( $archive_link ); ?>"><?php esc_html_e( 'Browse More Units', 'comarine-storage-booking-with-woocommerce' ); ?></a>
							<?php endif; ?>
						</div>

						<div class="comarine-storage-unit-single__pricing-glance">
							<div class="comarine-storage-unit-single__pricing-head">
								<span><?php esc_html_e( 'Pricing at a glance', 'comarine-storage-booking-with-woocommerce' ); ?></span>
								<?php if ( $from_price > 0 ) : ?>
									<strong><?php echo esc_html__( 'From', 'comarine-storage-booking-with-woocommerce' ) . ' ' . esc_html( $comarine_format_money( $from_price ) ); ?></strong>
								<?php else : ?>
									<strong><?php esc_html_e( 'Request pricing', 'comarine-storage-booking-with-woocommerce' ); ?></strong>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $duration_prices ) ) : ?>
								<ul class="comarine-storage-unit-single__price-list">
									<?php foreach ( $duration_prices as $duration_key => $price ) : ?>
										<li class="comarine-storage-unit-single__price-item">
											<span><?php echo esc_html( isset( $comarine_duration_labels[ $duration_key ] ) ? $comarine_duration_labels[ $duration_key ] : $duration_key ); ?></span>
											<strong><?php echo esc_html( $comarine_format_money( $price ) ); ?></strong>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="comarine-storage-unit-single__pricing-empty"><?php esc_html_e( 'Pricing has not been configured for this unit yet.', 'comarine-storage-booking-with-woocommerce' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<section class="comarine-storage-unit-single__layout">
					<div class="comarine-storage-unit-single__main">
						<section class="comarine-storage-unit-single__panel">
							<h2 class="comarine-storage-unit-single__panel-title"><?php esc_html_e( 'Unit Details', 'comarine-storage-booking-with-woocommerce' ); ?></h2>
							<div class="comarine-storage-unit-single__details-grid">
								<?php foreach ( $detail_rows as $row ) : ?>
									<div class="comarine-storage-unit-single__detail-item">
										<span class="comarine-storage-unit-single__detail-label"><?php echo esc_html( (string) $row['label'] ); ?></span>
										<strong class="comarine-storage-unit-single__detail-value"><?php echo esc_html( (string) $row['value'] ); ?></strong>
									</div>
								<?php endforeach; ?>
							</div>
						</section>

						<?php if ( ! empty( $unit_features ) ) : ?>
							<section class="comarine-storage-unit-single__panel">
								<h2 class="comarine-storage-unit-single__panel-title"><?php esc_html_e( 'Features', 'comarine-storage-booking-with-woocommerce' ); ?></h2>
								<ul class="comarine-storage-unit-single__features">
									<?php foreach ( $unit_features as $feature ) : ?>
										<li><?php echo esc_html( $feature ); ?></li>
									<?php endforeach; ?>
								</ul>
							</section>
						<?php endif; ?>

						<section class="comarine-storage-unit-single__panel">
							<h2 class="comarine-storage-unit-single__panel-title"><?php esc_html_e( 'Description', 'comarine-storage-booking-with-woocommerce' ); ?></h2>
							<div class="comarine-storage-unit-single__content">
								<?php if ( '' !== trim( wp_strip_all_tags( (string) get_the_content() ) ) ) : ?>
									<?php the_content(); ?>
								<?php else : ?>
									<p><?php esc_html_e( 'More details for this storage unit will be added soon. Use the booking panel to check pricing and start a reservation.', 'comarine-storage-booking-with-woocommerce' ); ?></p>
								<?php endif; ?>
							</div>
						</section>
					</div>

					<aside id="comarine-unit-booking" class="comarine-storage-unit-single__sidebar">
						<div class="comarine-storage-unit-single__booking-shell">
							<div class="comarine-storage-unit-single__sidebar-head">
								<p class="comarine-storage-unit-single__eyebrow"><?php esc_html_e( 'Secure This Unit', 'comarine-storage-booking-with-woocommerce' ); ?></p>
								<h2><?php esc_html_e( 'Start Booking', 'comarine-storage-booking-with-woocommerce' ); ?></h2>
								<p><?php esc_html_e( 'Choose your dates and booking duration, then continue to checkout to lock availability.', 'comarine-storage-booking-with-woocommerce' ); ?></p>
							</div>
							<?php echo do_shortcode( $booking_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</aside>
				</section>

				<section class="comarine-storage-unit-single__related">
					<div class="comarine-storage-unit-single__related-head">
						<h2><?php esc_html_e( 'More Storage Units', 'comarine-storage-booking-with-woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Browse other available units if you want different sizes or pricing options.', 'comarine-storage-booking-with-woocommerce' ); ?></p>
					</div>
					<?php
					echo do_shortcode(
						'[comarine_storage_units_latest limit="3" show_all="0" checkout="1"]'
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</section>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();

