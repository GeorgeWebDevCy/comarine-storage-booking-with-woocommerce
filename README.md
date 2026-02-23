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
- Settings page for booking container product, lock TTL, paid unit status, and currency
- Shortcode `[comarine_storage_units]` for initial frontend booking entry
- Booking lock creation + cart item metadata + price snapshot handling
- WooCommerce order synchronization hooks (JCC `completed` treated as paid)
- Checkout/cart lock validation and automatic cleanup of invalid booking items
- Admin configuration warnings for missing/invalid booking container product setup

Not implemented yet (next milestones):

- Frontend booking flow
- Advanced availability filtering/search UI
- Atomic lock transactions / stronger concurrency protections
- Add-ons and pricing rules
- Email/SMS notifications

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

## Quick Test Flow (Current Milestone)

1. Create a virtual WooCommerce product to use as the booking container.
2. Open `Storage Units > Settings` and select that product.
3. Create one or more `Storage Units` and set prices/status.
4. Add the shortcode `[comarine_storage_units]` to a page.
5. Book a unit and complete checkout (JCC sets order status to `completed` on success).
6. Test lock expiry behavior by leaving a booking in the cart beyond the configured TTL, then reopening cart/checkout (invalid locks should be removed with a notice).
