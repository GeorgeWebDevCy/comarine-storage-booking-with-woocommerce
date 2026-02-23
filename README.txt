=== CoMarine Storage Booking with WooCommerce ===
Contributors: georgewebdevcy
Donate link: https://www.georgenicolaou.me//
Tags: storage, booking, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce, jcc-payment-gateway-for-wc

Storage booking plugin for CoMarine that integrates with WooCommerce checkout and works with the JCC payment gateway plugin.

== Description ==

This plugin provides the CoMarine storage unit booking flow and uses WooCommerce for checkout/payment processing.

Payments are handled by WooCommerce gateways configured on the site, including the JCC Payment Gateway for WooCommerce plugin (`jcc-payment-gateway-for-wc`).

The plugin includes GitHub update checks via `yahnis-elsts/plugin-update-checker` (Composer-managed).

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

= 1.0.0 =
* Initial plugin bootstrap.
* Added Composer-managed GitHub update checks (`yahnis-elsts/plugin-update-checker`).
* Added activation/runtime dependency checks for WooCommerce and JCC Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.0.0 =
Includes dependency checks for WooCommerce and JCC plugin activation, plus GitHub update checking support.
