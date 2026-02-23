=== CoMarine Storage Booking with WooCommerce ===
Contributors: orionaselite
Donate link: https://www.georgenicolaou.me//
Tags: storage, booking, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce, jcc-payment-gateway-for-wc

Storage booking plugin for CoMarine that integrates with WooCommerce checkout and works with the JCC payment gateway plugin.

== Description ==

This plugin provides the CoMarine storage unit booking flow and uses WooCommerce for checkout/payment processing.

Payments are handled by WooCommerce gateways configured on the site, including the JCC Payment Gateway for WooCommerce plugin (`jcc-payment-gateway-for-wc`).

The plugin includes GitHub update checks via `yahnis-elsts/plugin-update-checker` (Composer-managed).

Current implemented milestone includes:
- Storage Unit custom post type (`comarine_storage_unit`)
- Unit detail meta fields in wp-admin
- `wp_comarine_bookings` custom table creation on activation (with WP table prefix)
- Basic Bookings admin overview page
- Booking settings page (container product / lock TTL / paid unit status)
- Shortcode `[comarine_storage_units]` and initial booking form flow
- Booking locks + WooCommerce cart/order metadata synchronization
- JCC-compatible paid trigger via WooCommerce `completed` status
- Cart/checkout lock validation with invalid booking cleanup
- Admin warnings for missing/invalid booking container product setup
- Bookings admin filters + manual status/unit actions
- Filtered bookings CSV export from the Bookings admin screen
- Booking audit log table + recent audit event panel for manual admin actions
- Bookings admin filters now include Unit ID and Created date range
- Bookings admin and CSV export now include customer details when available
- Booking summary block in WooCommerce order admin screen

== Installation ==

1. Upload the full plugin folder to `/wp-content/plugins/comarine-storage-booking-with-woocommerce/`.
1. Ensure WooCommerce is installed and active.
1. Ensure JCC Payment Gateway for WooCommerce (`jcc-payment-gateway-for-wc`) is installed and active.
1. If installing from source repository, run `composer install` so `vendor/` is present.
1. Activate the plugin through the 'Plugins' menu in WordPress.

The plugin will block activation and show an error if WooCommerce or the JCC plugin is missing/inactive.

== Frequently Asked Questions ==

= Why won't the plugin activate? =

The plugin requires both WooCommerce and the JCC Payment Gateway for WooCommerce plugin. Install and activate both first.

= How do updates work? =

The plugin uses a GitHub-based update checker (`plugin-update-checker`) and checks updates from the public repository.

== Screenshots ==

1. Storage units listing / booking entry point.
2. Checkout flow via WooCommerce.
3. Admin booking management screen.

== Changelog ==

= 1.0.7 =
* Added Bookings admin filters for Unit ID and Created date range.
* Extended CSV export to support the new unit/date filters.
* Added Customer column in Bookings admin and customer fields in CSV export (when available).

= 1.0.6 =
* Added custom audit log table and schema upgrade checks for plugin DB changes.
* Added filtered CSV export from the Bookings admin screen.
* Added audit logging for manual booking and unit status changes from admin actions.
* Added recent audit events table to the Bookings admin screen.

= 1.0.5 =
* Added Bookings admin filters (status/order/booking ID) and manual booking/unit status actions.
* Added WooCommerce order admin booking summary panel with links to units/bookings.
* Added booking helper query/status methods for admin tooling.

= 1.0.4 =
* Hardened booking lock expiry so rows already linked to an order are not auto-expired by stale lock cleanup.
* Added explicit booking lock validation/refresh helpers used by cart and checkout validation.
* Added checkout validation hook to block order creation when booking locks are invalid/expired.
* Auto-removes invalid booking items from cart during cart/checkout validation.
* Added admin configuration notices for missing/invalid/non-virtual booking container product setup.

= 1.0.3 =
* Added settings page for booking container product, lock TTL, paid unit status, and currency.
* Added `[comarine_storage_units]` shortcode and initial frontend booking form flow.
* Added booking lock lifecycle helpers (lock create, expire, cancel, assign order, paid/cancelled/refunded).
* Added WooCommerce cart item metadata, price snapshot handling, and order line item booking meta.
* Added order status synchronization hooks (JCC `completed` treated as successful payment).

= 1.0.2 =
* Added Storage Unit custom post type registration (`comarine_storage_unit`).
* Added admin meta fields and list columns for unit details/pricing/status.
* Added custom bookings table creation on activation (`wp_comarine_bookings` with prefix support).
* Added initial Bookings admin submenu page (overview/placeholder).
* Flush rewrite rules on activation/deactivation for CPT support.

= 1.0.1 =
* Bumped plugin version to test GitHub update delivery via plugin-update-checker.
* Updated WordPress.org contributor username.

= 1.0.0 =
* Initial plugin bootstrap.
* Added Composer-managed GitHub update checks (`yahnis-elsts/plugin-update-checker`).
* Added activation/runtime dependency checks for WooCommerce and JCC Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.0.7 =
Adds better reporting filters (unit/date range) plus customer details in Bookings admin and CSV exports.

= 1.0.6 =
Adds filtered CSV export and an audit log for manual admin booking/unit changes.

= 1.0.5 =
Adds practical admin tooling: booking management actions and booking visibility in WooCommerce order admin.

= 1.0.4 =
Hardens booking lock behavior and checkout validation, reducing stale/invalid lock edge cases during checkout.

= 1.0.3 =
Adds the first WooCommerce booking flow scaffolding: shortcode booking form, locks, cart metadata, and order sync hooks.

= 1.0.2 =
Adds the first functional booking foundation: storage units CPT, unit admin fields, and bookings table scaffolding.

= 1.0.1 =
Test release to verify GitHub-based plugin update detection.

= 1.0.0 =
Includes dependency checks for WooCommerce and JCC plugin activation, plus GitHub update checking support.
