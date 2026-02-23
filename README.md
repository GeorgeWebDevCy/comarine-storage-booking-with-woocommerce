# CoMarine Storage Booking with WooCommerce

WordPress plugin for CoMarine storage unit bookings with WooCommerce checkout integration.

## Current Status (Milestone 1)

Implemented in the codebase so far:

- Dependency guards for WooCommerce + JCC plugin activation/runtime
- GitHub-based plugin update checks (Composer + `plugin-update-checker`)
- Storage Unit custom post type (`comarine_storage_unit`)
- Storage Unit admin meta fields (unit code, size, floor, pricing, status)
- Storage Unit admin list columns for key metadata
- Bookings custom database table (`wp_comarine_bookings` with prefix-aware table name)
- Basic admin "Bookings" submenu page (overview / placeholder list)

Not implemented yet (next milestones):

- Frontend booking flow
- WooCommerce cart/checkout booking synchronization
- Reservation locking lifecycle
- Order status -> booking status transitions

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

Versioning note: feature milestones committed to `main` should also bump the plugin version so the update checker can surface them for testing.

## Packaging Note

If you deploy from source, include `vendor/` (or run `composer install` as part of your build/release step).
