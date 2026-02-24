=== CoMarine Storage Booking with WooCommerce ===
Contributors: orionaselite
Donate link: https://www.georgenicolaou.me//
Tags: storage, booking, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.42
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires Plugins: woocommerce, jcc-payment-gateway-for-wc

Storage booking plugin for CoMarine that integrates with WooCommerce checkout and works with the JCC payment gateway plugin.

== Description ==

This plugin provides the CoMarine storage unit booking flow and uses WooCommerce for checkout/payment processing.

Payments are handled by WooCommerce gateways configured on the site, including the JCC Payment Gateway for WooCommerce plugin (`jcc-payment-gateway-for-wc`).

The plugin includes GitHub update checks via `yahnis-elsts/plugin-update-checker` (Composer-managed).

Current implemented milestone includes:
- Storage Unit custom post type (`comarine_storageunit`)
- Unit detail meta fields in wp-admin
- `wp_comarine_bookings` custom table creation on activation (with WP table prefix)
- Basic Bookings admin overview page
- Booking settings page (container product / lock TTL / paid unit status)
- Shortcode `[comarine_storage_units]` and initial booking form flow
- Added shortcode `[comarine_storage_units_latest]` for a homepage-friendly latest 3 units preview (no search/filter UI) with CTA buttons linking to each unit single post page
- Booking locks + WooCommerce cart/order metadata synchronization
- JCC-compatible paid trigger via WooCommerce `completed` status
- Cart/checkout lock validation with invalid booking cleanup
- Admin warnings for missing/invalid booking container product setup
- Bookings admin filters + manual status/unit actions
- Filtered bookings CSV export from the Bookings admin screen
- Booking audit log table + recent audit event panel for manual admin actions
- Bookings admin filters now include Unit ID and Created date range
- Bookings admin and CSV export now include customer details when available
- Bookings admin now includes a "View" action with a booking detail panel
- Bookings admin now shows a units status overview summary (available/reserved/occupied/etc.)
- Bookings admin table now supports secure bulk actions for booking and unit updates
- Bulk booking status changes write audit log entries with per-booking tracking
- Destructive row actions now show confirmation prompts before execution
- Bulk actions now support optional audit notes and require explicit confirmation for destructive actions
- Frontend shortcode now supports filtering (search/status/floor/size/price/bookable-now)
- Frontend storage unit cards now show richer details and clearer availability messages
- Configurable booking add-ons (JSON settings) with frontend checkbox selection
- Add-ons are included in booking price snapshots, cart display, and order line item meta
- Booking summary block in WooCommerce order admin screen
- Dedicated CoMarine Storage top-level admin menu with grouped submenus for bookings, storage units, and settings
- WordPress Plugins screen now includes an "Open Admin" action link to the CoMarine Storage Bookings page
- Bookings admin date filters use a `dd/mm/yyyy` datepicker input
- Admin booking/audit dates and times are rendered using WordPress date/time settings
- Refreshed plugin UI styling (frontend shortcode + plugin admin screens) using a blue primary theme
- Admin CSS/JS assets are now only loaded on plugin-related admin screens (scoped to avoid affecting other admin pages)
- Plugin menu explicitly ensures Storage Units / Add New submenus are present under the CoMarine Storage menu
- Storage Units CPT is registered via an early fallback so direct `edit.php?post_type=comarine_storageunit` links remain valid
- Added an Overview admin screen with a setup checklist for required and recommended plugin configuration
- Added a one-click admin action to auto-create/reuse the WooCommerce booking container product from Overview/Settings
- Added a one-click admin action to seed the Spec v2 catalog (A1-F2) and replace all existing Storage Units
- Added a defensive Storage Units admin menu URL normalization fix to prevent `Invalid post type` on some setups
- Added a Bookings Calendar admin screen for a monthly visual view of all bookings
- Booking forms use fixed periods (`monthly`, `6m`, `12m`) with a required start date and internally calculated end date
- Spec v2 units are seeded as whole-unit bookings (no partial m2 booking), and unneeded daily/floor/dimensions fields are hidden from the Storage Unit editor UI

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

= 1.0.42 =
* Added a new `Calendar` admin submenu with a monthly bookings calendar view and booking cards linked to booking details.
* Added a destructive `Seed Spec v2 Units (Replace All)` setup action that deletes existing Storage Units (and related booking/audit rows for those units) and creates the 40-unit v2 catalog (`A1`-`F2`).
* Spec v2 units now default to whole-unit fixed-period bookings (`monthly`, `6m`, `12m`) with no customer-selected end date (end date remains internal).
* Storage Unit admin UI now hides unneeded `Daily price`, `Floor`, `Dimensions`, and booking-mode fields for the current spec.
* Overview and Settings screens now surface the Spec v2 seed action and hide demo-data setup buttons to avoid setup confusion.

= 1.0.30 =
* Added a per-unit `Daily price` field and daily booking mode support using start/end date range selection.
* Booking totals now support per-day pricing and m2-based price recalculation (including live frontend estimate updates as users change area/dates).
* Capacity-managed booking forms now emphasize the required area (m2) input and show it before other booking inputs.
* Homepage shortcode preview cards now link to each unit single post page (`View Unit Details`) instead of showing the booking form.
* Demo unit generator and setup checks now treat daily pricing as valid configured pricing and generated demo units include a daily rate.

= 1.0.29 =
* Booking forms now require customers to select a start date before adding a storage booking to cart.
* Booking lock rows now store `start_ts`/`end_ts` based on the selected start date (instead of using booking creation time as the start).
* Cart/checkout and order item metadata now display the selected booking start date.
* Added frontend card date-input UI styling for the required start date field.

= 1.0.28 =
* Updated `[comarine_storage_units_latest]` homepage shortcode layout to render as a 3-column grid on desktop (responsive 2-column tablet / 1-column mobile).

= 1.0.27 =
* Added a one-click admin setup action to generate 5 demo Storage Units with randomized capacities and prices (easy to delete later).
* Added homepage-friendly shortcode `[comarine_storage_units_latest]` to show the latest 3 units without the search/filter UI.
* Extended the main storage units shortcode renderer with optional hidden-filter/latest-sort modes to support compact homepage layouts.

= 1.0.26 =
* Added partial-capacity booking for storage units using the unit size (`_csu_size_m2`) as total available m2.
* Booking form now accepts a requested area (m2) for capacity-managed units and validates against remaining capacity.
* Capacity-managed unit prices are prorated at checkout based on requested m2 vs total unit capacity (configured duration prices remain full-unit prices).
* Units are only marked unavailable/reserved when their full capacity is booked out; partial bookings leave remaining capacity bookable.
* Added booked-area m2 data to booking records, cart/order metadata, and Bookings admin list/detail/CSV export.
* Added frontend UI updates to show available/reserved m2 on unit cards and collect requested area.

= 1.0.25 =
* Fixed plugin admin screen detection so the Overview page reliably loads the plugin admin CSS/JS (restoring the intended card-based UI on affected WordPress setups).

= 1.0.24 =
* Fixed the Storage Units CPT slug length issue (legacy `comarine_storage_unit` exceeded WordPress's 20-character post type key limit and could fail registration).
* Changed the Storage Units CPT slug to `comarine_storageunit` and added a one-time migration for legacy rows using the old slug.
* Added admin compatibility redirects so old Storage Units admin URLs using the legacy slug are redirected to the current CPT URLs.

= 1.0.23 =
* Hardened the Overview/admin CPT auto-repair path so it retries direct registration if the fallback helper does not restore the Storage Units post type.
* Added an early `admin_init` safeguard to ensure the Storage Units CPT is registered on CoMarine plugin admin page requests before rendering setup checks.
* Refreshed the Overview, Settings, and Storage Units admin UI styling for clearer hierarchy and improved usability.

= 1.0.22 =
* Overview now checks that required plugin post types are registered and attempts to auto-register missing ones before rendering setup checks.

= 1.0.21 =
* Added an admin request normalizer for Storage Units menu clicks so malformed submenu requests are redirected to the correct CPT URLs.
* Added an extra admin-init CPT safety registration path for Storage Units requests to avoid `Invalid post type` edge cases.

= 1.0.20 =
* Added a one-click admin setup action that auto-creates a hidden virtual WooCommerce booking container product when missing.
* If a previously auto-created container product exists, the action reuses it and re-saves the plugin setting instead of creating duplicates.
* Added setup action notices and action buttons to the Overview and Settings screens.

= 1.0.19 =
* Added a new Overview admin screen with setup readiness checks (dependencies, container product, units/pricing, WooCommerce pages, and key settings snapshot).
* Added Overview submenu under CoMarine Storage and integrated it with the plugin admin styling.

= 1.0.18 =
* Added an early Storage Units CPT registration fallback so direct admin URLs do not fail with "Invalid post type".
* Guarded Storage Units CPT registration against duplicate registration when the full plugin bootstraps later.

= 1.0.17 =
* Added a late admin-menu fallback to ensure `Storage Units` and `Add New` appear under the CoMarine Storage menu.
* Reordered plugin submenus so Bookings, Storage Units, Add New, and Settings appear consistently.

= 1.0.16 =
* Refreshed frontend shortcode UI styling using a blue primary theme (`#2ea3f2`) with improved cards, filters, and buttons.
* Added a scoped admin UI polish pass for Bookings/Settings and Storage Units CPT screens.
* Restricted plugin admin CSS/JS loading to plugin-related admin screens only.
* Kept UI styles scoped to plugin wrappers/body classes so non-plugin screens and site theme styling are not affected.

= 1.0.15 =
* Changed Bookings admin date filter inputs to a `dd/mm/yyyy` datepicker.
* Updated admin booking and audit timestamp displays to use WordPress date/time format settings.
* Updated booking lock expiry validation to compare timestamps using the site timezone.
* Updated CSV export filenames to use WordPress-local time (`wp_date()`).

= 1.0.14 =
* Added an "Open Admin" action link on the WordPress Plugins screen for quick access to the CoMarine Storage admin page.

= 1.0.13 =
* Added a dedicated top-level CoMarine Storage admin menu for plugin screens.
* Moved Storage Units CPT under the plugin menu so related screens are grouped together.
* Updated Bookings admin routing/helpers to use the top-level menu page URL.

= 1.0.12 =
* Added configurable booking add-ons setting (JSON definitions) in plugin settings.
* Added add-ons selection UI on frontend booking forms.
* Included add-ons in booking price snapshots/cart pricing and cart display metadata.
* Added add-ons metadata to WooCommerce order line items.

= 1.0.11 =
* Added frontend shortcode filter UI (search/status/floor/size/price/bookable-now).
* Added richer frontend unit card layout with status badges, meta chips, pricing summary, and features support.
* Improved availability messaging for locked/reserved/configuration/pricing conditions.
* Added public CSS styling for the shortcode filters, cards, and booking form layout.

= 1.0.10 =
* Added confirmation prompts for destructive row actions (cancel/refund/unit available).
* Added optional audit note input to Bookings bulk actions.
* Destructive bulk actions now require an explicit confirmation checkbox before execution.
* Fixed bulk action form handling so top/bottom action controls submit reliably.

= 1.0.9 =
* Added secure bulk actions to the Bookings admin table (nonce/capability checked).
* Added bulk booking status updates (paid/cancelled/refunded) with audit logging.
* Added bulk unit status updates (available/reserved/occupied) from the Bookings admin table.
* Added bulk action result notices with updated/failed counts.

= 1.0.8 =
* Added "View" row action and booking detail panel in Bookings admin.
* Added units status overview panel on the Bookings admin screen.
* Booking detail panel includes linked unit/order/customer snapshot and manual actions.

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
* Added Storage Unit custom post type registration (`comarine_storageunit`).
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

= 1.0.42 =
Adds a bookings calendar admin screen, a destructive Spec v2 unit seeding action (replace-all), and fixed-period whole-unit booking defaults for the current catalog.

= 1.0.30 =
Adds daily pricing with start/end date range booking, m2-aware live price estimates on the frontend, and homepage preview cards that link to unit single pages.

= 1.0.29 =
Adds a required booking start date field to the storage booking form and stores the selected date in booking/cart/order records.

= 1.0.28 =
Improves the homepage latest-units shortcode layout so it displays in 3 columns on desktop while staying responsive on smaller screens.

= 1.0.27 =
Adds a demo Storage Units generator in wp-admin and a homepage shortcode (`[comarine_storage_units_latest]`) for showing the latest 3 units without filters.

= 1.0.26 =
Adds partial m2 booking for capacity-managed units with prorated pricing and full-capacity locking behavior (includes DB schema update for booked area fields).

= 1.0.25 =
Fixes Overview admin asset loading on some WordPress setups so the styled card UI renders consistently.

= 1.0.24 =
Fixes Storage Units CPT registration by using a valid post type key length and migrates legacy rows/URLs to the new slug.

= 1.0.23 =
Improves admin-side CPT recovery for Storage Units and refreshes the Overview, Settings, and Storage Units admin UI styling.

= 1.0.22 =
Overview now verifies required post type registrations and auto-registers missing plugin post types before rendering setup checks.

= 1.0.21 =
Fixes Storage Units submenu clicks on some WordPress admin setups by normalizing the request to the correct CPT URL and preventing `Invalid post type`.

= 1.0.20 =
Adds a one-click admin action to create (or reuse) the hidden WooCommerce booking container product and save it in plugin settings.

= 1.0.19 =
Adds a setup Overview screen in wp-admin so you can quickly verify required plugin configuration before going live.

= 1.0.18 =
Fixes direct Storage Units admin URLs (`edit.php?post_type=comarine_storageunit`) by ensuring the CPT is registered early.

= 1.0.17 =
Ensures the Storage Units admin submenu is always shown under CoMarine Storage (with consistent submenu ordering).

= 1.0.16 =
Adds a scoped UI refresh (frontend + plugin admin screens) themed with primary color `#2ea3f2`.

= 1.0.15 =
Adds WordPress-localized admin date/time display plus `dd/mm/yyyy` Bookings datepickers.

= 1.0.14 =
Adds a quick "Open Admin" link on the Plugins screen that opens the CoMarine Storage admin page.

= 1.0.13 =
Groups plugin-related admin screens under a dedicated CoMarine Storage top-level menu (Bookings, Storage Units, Settings).

= 1.0.12 =
Adds MVP booking add-ons: configurable add-ons in settings with frontend selection and checkout/order meta support.

= 1.0.11 =
Adds the first major frontend UX improvement pass for `[comarine_storage_units]` (filters + richer unit cards).

= 1.0.10 =
Adds safer admin workflows: confirmations for destructive actions and audit notes for bulk updates.

= 1.0.9 =
Adds secure bulk actions in Bookings admin, including audit-logged bulk booking status changes.

= 1.0.8 =
Adds a booking detail view in admin plus a units status overview panel for staff visibility.

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
