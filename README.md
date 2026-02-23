# CoMarine Storage Booking with WooCommerce

WordPress plugin for CoMarine storage unit bookings with WooCommerce checkout integration.

## Requirements

- WordPress
- WooCommerce (`woocommerce`)
- JCC Payment Gateway for WooCommerce (`jcc-payment-gateway-for-wc`)
  - WordPress.org: https://wordpress.org/plugins/jcc-payment-gateway-for-wc/

The plugin now blocks activation if WooCommerce or the JCC gateway plugin is not installed/active.

## Installation (Development / Source)

1. Clone this repository into `wp-content/plugins/comarine-storage-booking-with-woocommerce`.
2. Install PHP dependencies:
   - `composer install`
3. Activate and configure:
   - WooCommerce
   - JCC Payment Gateway for WooCommerce
4. Activate `Comarine Storage booking with WooCommerce`.

## Updates

- Composer is used for `yahnis-elsts/plugin-update-checker`.
- Plugin updates are checked from:
  - `https://github.com/GeorgeWebDevCy/comarine-storage-booking-with-woocommerce`
- Stable branch configured:
  - `main`

## Packaging Note

If you deploy from source, include `vendor/` (or run `composer install` as part of your build/release step).
