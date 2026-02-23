# CoMarine Storage Booking with WooCommerce

WordPress plugin for CoMarine storage unit bookings with WooCommerce checkout integration.

## Current Status (Milestone 20)

Implemented in the codebase so far:

- Dependency guards for WooCommerce + JCC plugin activation/runtime
- GitHub-based plugin update checks (Composer + `plugin-update-checker`)
- Storage Unit custom post type (`comarine_storageunit`)
- Storage Unit admin meta fields (unit code, size, floor, pricing, status)
- Storage Unit admin list columns for key metadata
- Bookings custom database table (`wp_comarine_bookings` with prefix-aware table name)
- Audit log custom database table (`wp_comarine_booking_audit_log` with prefix-aware table name)
- Automatic DB schema upgrade checks for custom tables after plugin updates
- Bookings admin management screen (filters, manual actions, CSV export, audit log)
- Bookings admin reporting filters for unit/date range (used by on-screen list and CSV export)
- Customer column in bookings admin and customer fields in CSV export
- Booking detail panel in Bookings admin (selected booking summary + actions)
- Units status overview panel on the Bookings admin screen
- Bulk actions on the Bookings admin table (with nonce/capability checks)
- Audit logging for bulk booking status changes and bulk unit status overrides
- Destructive row actions now require confirmation prompts (cancel/refund/unit available)
- Bulk actions support optional audit notes and require explicit confirmation for destructive bulk actions
- Frontend shortcode now includes filter controls (search/status/floor/size/price/bookable-now)
- Frontend unit cards now show richer details (status badge, dimensions, features, pricing summary)
- Frontend availability messaging improved (locked/reserved/configuration/pricing reasons)
- MVP configurable add-ons via plugin settings (JSON definitions)
- Frontend booking forms now support optional add-on selection
- Add-ons are included in cart display, price snapshots, and order line item meta
- Dedicated `CoMarine Storage` admin menu with grouped submenus for Bookings, Storage Units (CPT), and Settings
- `Plugins` screen now includes an `Open Admin` action link that opens the CoMarine Storage admin screen
- Bookings admin date filters now use a consistent `dd/mm/yyyy` datepicker input
- Admin booking/audit date-time displays now follow WordPress date/time settings
- Frontend shortcode UI refreshed with a blue (`#2ea3f2`) visual theme and improved card/filter polish
- Plugin admin screens (Bookings/Settings + Storage Units CPT) now have a scoped visual refresh without affecting other admin pages
- Plugin menu now explicitly ensures `Storage Units` and `Add New` submenus appear under `CoMarine Storage` (fallback for WP submenu edge cases)
- Storage Units CPT now has an early bootstrap registration fallback so direct admin URLs do not fail with `Invalid post type`
- New `Overview` admin screen provides a setup checklist for required/recommended plugin configuration
- One-click admin action to auto-create/reuse the WooCommerce booking container product and save it in plugin settings
- One-click admin action to create 5 demo Storage Units with random capacities/prices for testing (deletable later)
- Storage Units admin submenu clicks now normalize to the correct CPT URLs to avoid `Invalid post type` errors on some WP admin menu setups
- Settings page for booking container product, lock TTL, paid unit status, and currency
- Shortcode `[comarine_storage_units]` for initial frontend booking entry
- Shortcode `[comarine_storage_units_latest]` for a homepage-friendly latest 3 units view (no filter/search UI, 3-column desktop grid)
- Booking lock creation + cart item metadata + price snapshot handling
- WooCommerce order synchronization hooks (JCC `completed` treated as paid)
- Checkout/cart lock validation and automatic cleanup of invalid booking items
- Admin configuration warnings for missing/invalid booking container product setup
- Booking summary panel on WooCommerce order admin pages
- Audit log entries for manual booking/unit status actions
- Capacity-managed units (size in m2) now support partial-area bookings with checkout price proration and full-capacity-only locking

Not implemented yet (next milestones):

- Frontend UX polish (availability messaging, richer unit details)
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

1. Open `CoMarine Storage > Overview` (or `Settings`) and use the auto-create action for the booking container product, or create/select a virtual WooCommerce product manually.
2. Open `CoMarine Storage > Settings` and confirm the booking container product is selected.
3. Open `CoMarine Storage > Storage Units` and create one or more storage units with prices/status.
4. Add the shortcode `[comarine_storage_units]` to a page.
5. Book a unit and complete checkout (JCC sets order status to `completed` on success).
6. Test lock expiry behavior by leaving a booking in the cart beyond the configured TTL, then reopening cart/checkout (invalid locks should be removed with a notice).
7. In `CoMarine Storage > Bookings`, apply unit/date filters using `dd/mm/yyyy` and confirm the list and `Export CSV` output match.
8. Click `View` on a booking row and confirm the Booking Detail panel appears with links/actions.
9. Use a manual booking/unit action and confirm the new audit entry appears.
10. Select multiple bookings, apply a bulk action (for example `Cancel Booking`), and verify the result notice + audit log rows.
11. Test a destructive row action (`Cancel`, `Mark Refunded`, or `Unit: Available`) and confirm the browser prompt appears before the action runs.
12. Open a page with `[comarine_storage_units]`, test filters, and verify card availability messages / booking button states.
13. Configure add-ons in `CoMarine Storage > Settings`, select them during booking, and confirm totals/meta in cart and order.
14. Confirm the refreshed plugin UI styling (frontend shortcode + plugin admin screens) appears correctly and does not alter non-plugin site/admin screens.
15. Confirm `CoMarine Storage` shows `Bookings`, `Storage Units`, `Add New`, and `Settings` in the admin menu.
16. Open `/wp-admin/edit.php?post_type=comarine_storageunit` directly and confirm the Storage Units list loads (no `Invalid post type` error).
17. Open `CoMarine Storage > Overview` and confirm the setup checklist reports container product, dependencies, storage units/pricing, and key configuration status.
18. If the container product is missing, use the `Create Container Product` action and confirm the setting is populated automatically.
19. Click `CoMarine Storage > Storage Units` and `CoMarine Storage > Add New` and confirm both open the correct CPT screens (no `Invalid post type` message).
20. For a unit with `Size (m2)` set (for example `1000`), book a partial area (for example `250`) and confirm the checkout price is prorated and the unit remains bookable until total reserved area reaches full capacity.
