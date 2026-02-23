# COMARINE Storage Booking (WooCommerce) — Project Requirements (Developer/AI Spec)

> Purpose: This document is written to be **directly usable by an AI code assistant** (or a dev team) to implement the **COMARINE online storage booking system** as a **WordPress plugin that integrates with WooCommerce**.
>
> Payments: **JCC is handled by an existing gateway plugin** (`jcc-payment-gateway-for-wc`). This project **must not** implement JCC itself; it must **work with it** as a standard WooCommerce payment method.

---

## 0) Project Summary

Build a WordPress plugin (working with WooCommerce) that allows customers to:

- Browse storage units (by size m², features, photos, floor, etc.)
- See availability in real-time (no double-booking)
- Select rental duration: **Monthly / 6-month / Annual**
- Checkout and pay via **WooCommerce checkout** (supporting JCC, Apple Pay/Google Pay via JCC plugin/other gateways, and PayPal/Stripe as configured)
- Receive automated notifications (Email + optional SMS)
- Use **multilingual** site (Greek / English / Russian) with WPML compatibility
- Admin staff can manage units, bookings, and statuses easily
- Architecture is modular for later **Logistics** expansion

---

## 1) Scope

### 1.1 In Scope (MVP)
- Storage unit catalog + unit detail pages
- Booking flow (select unit, duration, add-ons, checkout)
- Reservation lock to prevent double booking
- WooCommerce order integration
- Admin UI for managing units and viewing bookings
- Email confirmations (WooCommerce templates + custom content)
- WPML-compatible strings and templates
- Payment via WooCommerce checkout **using installed gateways**, incl. JCC plugin

### 1.2 Out of Scope (MVP)
- Implementing JCC payment gateway logic (already provided by `jcc-payment-gateway-for-wc`)
- Full logistics (inventory/shipping) — only “infrastructure hooks”
- Complex date-based calendar bookings (this is *unit reservation + duration*, not nightly rentals)
- Membership tiers / subscriptions (optional future)

---

## 2) Key Goals & Success Criteria

### Business Goals
- Booking + payment in **3–4 minutes**
- Clear availability / no confusion
- Automated confirmations & access instructions
- Easy admin management

### Technical Success Criteria
- **Zero double bookings** (atomic lock + status transitions)
- Correct price calculation for duration + add-ons
- Booking survives refresh/back/duplicate payment attempts
- Compatible with modern WP/WooCommerce + caching plugins
- Secure (nonces, caps, sanitization) & robust logging

---

## 3) Dependencies

### Required
- WordPress
- WooCommerce
- JCC Payment plugin: `jcc-payment-gateway-for-wc`
  - The gateway is configured in WooCommerce and behaves like a normal WC payment method.
  - Our plugin must remain gateway-agnostic.

### Optional (feature toggles)
- WPML (multilingual)
- SMS provider plugin OR direct integration (Twilio / local SMS API)

---

## 4) High-Level UX Flow

### 4.1 Customer Flow (Target: < 4 minutes)
1. Customer opens “Storage Units” page
2. Filters by size / price / floor / features
3. Views unit card (photos + dimensions + highlights)
4. Clicks “Book Now”
5. Selects **duration** (Monthly / 6-month / Annual)
6. Optional: Select add-on services (insurance, moving help, packing)
7. Click “Proceed to Checkout”
8. WooCommerce Checkout
9. Payment (JCC / Apple Pay / Google Pay / PayPal / Stripe depending on store config)
10. Confirmation page + Email/SMS + access instructions

### 4.2 Admin Flow
- Create/Edit Storage Units
- See availability
- See bookings table (with order links)
- Manual override (set occupied/reserved/available with audit log)
- Export bookings CSV

---

## 5) Data Model

### 5.1 Storage Units
Implement as **Custom Post Type**: `comarine_storage_unit`

**Recommended:** store structured fields via custom meta (ACF compatible) *or* a custom meta box system.

**Fields**
| Field | Meta Key | Type | Required |
|---|---|---:|---:|
| Unit code/ID (human readable) | `_csu_unit_code` | string | ✅ |
| Size (m²) | `_csu_size_m2` | float | ✅ |
| Dimensions text (e.g., 2.5 x 2) | `_csu_dimensions` | string | ✅ |
| Floor/Level | `_csu_floor` | string/select | ✅ |
| Features | `_csu_features` | array/repeater | ❌ |
| Base pricing monthly | `_csu_price_monthly` | decimal | ✅ |
| Base pricing 6-month | `_csu_price_6m` | decimal | ✅ |
| Base pricing annual | `_csu_price_12m` | decimal | ✅ |
| Status | `_csu_status` | enum | ✅ |
| Gallery attachment IDs | `_csu_gallery` | array[int] | ✅ |
| Diagram image ID | `_csu_diagram_id` | int | ❌ |
| Location marker (optional) | `_csu_map_location` | JSON/string | ❌ |

**Status Enum**
- `available`
- `reserved`
- `occupied`
- `maintenance` (optional but recommended)
- `archived` (not shown on frontend)

### 5.2 Bookings (Custom Table)
Use a custom table for bookings to avoid abusing WC order meta for searching/reporting.

**Table:** `wp_comarine_bookings`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK AI | |
| unit_post_id | BIGINT | Storage unit post ID |
| unit_code | VARCHAR(64) | redundant snapshot |
| order_id | BIGINT | Woo order id |
| user_id | BIGINT NULL | guest allowed |
| duration_key | VARCHAR(16) | `monthly` / `6m` / `12m` |
| start_ts | DATETIME NULL | optional (usually booking date) |
| end_ts | DATETIME NULL | calculated end |
| price_total | DECIMAL(12,2) | snapshot |
| currency | VARCHAR(8) | usually `EUR` |
| status | VARCHAR(20) | `pending`, `locked`, `paid`, `cancelled`, `expired`, `refunded` |
| lock_token | VARCHAR(64) NULL | for reservation lock |
| lock_expires_ts | DATETIME NULL | lock TTL |
| created_ts | DATETIME | |
| updated_ts | DATETIME | |

**Important:** Booking record should be created *before checkout* with `locked` status, and finalized after payment.

### 5.3 Lock Table (Optional)
If you want stronger concurrency guarantees, create a dedicated lock table:
- `wp_comarine_unit_locks (unit_post_id PK, lock_token, expires_ts)`

Or store lock info in bookings table with a unique constraint.

---

## 6) Reservation Locking & Anti Double-Booking

### 6.1 Core Rule
A unit can be reserved by **only one** active booking at a time.

### 6.2 Lock Algorithm (Recommended)
- When user clicks “Proceed to Checkout”:
  1. Start transaction (if using custom table + InnoDB)
  2. Check if unit is `available` AND no active lock exists (lock not expired)
  3. Create booking record with `locked` status + `lock_token`
  4. Set lock expiry (e.g., `now + 15 minutes`)
  5. Add `booking_id` + `lock_token` to WooCommerce session/cart item meta

**Lock TTL:** 10–15 minutes (configurable)

### 6.3 Lock Release
- On payment failure / order cancelled / timeout:
  - Mark booking `cancelled` or `expired`
  - Clear lock (or allow TTL to expire)
- On successful payment:
  - Mark booking `paid`
  - Unit status becomes `reserved` or `occupied` based on business rules (see 6.4)

### 6.4 Status Transitions
- `available` → `reserved` when paid
- `reserved` → `occupied` when keys/access are provided (admin action) OR immediately if that’s the policy
- `occupied` → `available` when rental ends (future automation) OR admin action

---

## 7) WooCommerce Integration

### 7.1 Product Strategy (Recommended for clean UX)
Use a **single “booking container product”** (virtual product) + cart item meta referencing the chosen unit.
- Advantages: simpler checkout and gateway compatibility; avoids creating a WC product per unit.
- The storage unit is a CPT item; WooCommerce product is just a “purchase wrapper”.

Alternative: Create a custom WC product type. (More work; only do if you *need* it.)

### 7.2 Cart Item Meta
Store:
- `comarine_unit_post_id`
- `comarine_duration_key`
- `comarine_booking_id`
- `comarine_lock_token`
- `comarine_price_snapshot`
- add-ons selected

### 7.3 Price Calculation Hooks
Implement dynamic pricing per cart item using WC hooks:
- `woocommerce_before_calculate_totals`
- Store snapshots to avoid mid-checkout price changes

### 7.4 Order Meta
On checkout create order meta:
- `_comarine_booking_id`
- `_comarine_unit_post_id`
- `_comarine_duration_key`
- `_comarine_unit_code`

And ensure order admin displays it clearly.

---

## 8) Payments (Gateway-Agnostic; JCC via Existing Plugin)

### 8.1 Requirement
The system must work with normal WooCommerce payment gateways, including **JCC via** `jcc-payment-gateway-for-wc`.

No custom payment processing is implemented here.

### 8.2 Payment Lifecycle Events to Handle
Hook into WC order status changes:
- `pending` / `failed` / `cancelled` → release lock / set booking cancelled
- `processing` / `completed` → confirm booking paid; mark unit reserved/occupied

Important note from JCC plugin description:
- Physical orders often end `processing`; virtual/downloadable may end `completed`.
Your logic should treat both `processing` and `completed` as “paid”.

### 8.3 Refunds
If WC order is refunded:
- Update booking status to `refunded`
- Optional: revert unit status to `available` if business permits (usually no; depends on policy)

---

## 9) Notifications

### 9.1 Email
- Use WooCommerce email templates + add booking details in:
  - Order received page
  - Customer processing/completed email
- Custom email content includes:
  - Unit code, size, floor
  - Duration
  - Total price paid
  - Access instructions (static template or per-unit field)
  - Google Maps link to facility

---

## Implementation Notes (Repo / Dependency Reference)

- JCC dependency reference (keep this exact slug for checks and docs):
  - WordPress.org plugin: `jcc-payment-gateway-for-wc`
  - URL: https://wordpress.org/plugins/jcc-payment-gateway-for-wc/
- The plugin should block activation if either required dependency is missing or inactive:
  - `woocommerce`
  - `jcc-payment-gateway-for-wc`
- GitHub updates are implemented using Composer + `yahnis-elsts/plugin-update-checker`.
- Repository update source:
  - `https://github.com/GeorgeWebDevCy/comarine-storage-booking-with-woocommerce`
  - Branch: `main`
- Release/build note:
  - Ship `vendor/` with the plugin ZIP, or run `composer install` during the build step.

### 9.2 SMS (Optional Feature Flag)
- Admin setting: enable/disable SMS notifications
- Provider abstraction:
  - Interface: `send_sms($phone, $message, $context=[])`
- Triggers:
  - Booking paid (processing/completed)
  - Optional: reminder before expiry (future)

---

## 10) Multilingual (Greek / English / Russian)

### 10.1 Requirements
- All plugin strings must be translatable via:
  - `__()`, `_e()`, `esc_html__()` etc.
- WPML compatibility:
  - Register dynamic strings where needed
  - Ensure unit CPT supports translation (WPML config file optional)
- Email templates must support multilingual output (based on order language)

---

## 11) Admin UI

### 11.1 Menu
Add admin menu: `COMARINE → Bookings`

### 11.2 Screens
1. **Bookings List**
   - Filters: status, date range, unit, order id
   - Columns: Booking ID, Unit Code, Customer, Duration, Total, Status, Order link, Created
   - Actions: View, Export CSV
2. **Booking Detail**
   - Full booking + order link
   - Manual status change (capability restricted)
3. **Units Status Overview**
   - Available/Reserved/Occupied counts
   - Quick link to edit unit

### 11.3 Capabilities
- `manage_options` for settings
- Custom caps optional:
  - `manage_comarine_bookings`
  - `edit_comarine_storage_units`

---

## 12) Add-On Services (“Καλάθι Πελάτη”)

### 12.1 MVP Approach
Define add-ons as:
- Either normal WooCommerce products that can be added together
- Or structured add-ons within plugin settings (name, price), added as fees.

**Recommended MVP:** add-ons as “fees” attached to cart item for simplicity.

### 12.2 Data
- Add-on definitions stored in `wp_options`:
  - `comarine_addons = [{key, label, price, taxable, enabled}]`

---

## 13) Google Maps Integration

- Provide site options:
  - Facility address
  - Office pickup address
  - Map embed or link
- Frontend:
  - map section on contact page and/or booking confirmation

---

## 14) FAQ, Tips, Articles

This can be implemented as normal WP pages/posts + a dedicated FAQ block/shortcode.
If needed:
- CPT: `comarine_faq` with question/answer fields.
- Output: accordion shortcode/block.

---

## 15) Logging & Diagnostics

### 15.1 Logging Requirements
- Debug setting in plugin options:
  - off / errors only / verbose
- Log destination:
  - `wp-content/uploads/comarine-booking/logs/booking.log`
- Log key events:
  - lock acquired/rejected
  - booking created
  - order status transitions handled
  - errors / unexpected states

### 15.2 Admin Debug Panel (Optional)
Show last 100 log lines in admin.

---

## 16) Performance & Caching

- Availability endpoints must be uncached
- Use AJAX/REST endpoints with:
  - Nonce
  - no-cache headers
- Avoid heavy WP_Query loops on every request
- Index booking table on:
  - `unit_post_id`
  - `order_id`
  - `status`
  - `created_ts`

---

## 17) Security

- Nonces for all frontend actions
- Capability checks for admin actions
- Sanitize/validate:
  - unit id
  - duration key
  - add-ons
- Prevent tampering:
  - lock_token must match server record
  - price snapshot must be server authoritative
- Rate limit lock attempts per IP/session (basic throttling)

---

## 18) Plugin Structure (Suggested)

```
comarine-storage-booking/
  comarine-storage-booking.php
  /includes
    class-plugin.php
    /admin
      class-admin-menu.php
      class-admin-bookings.php
      class-admin-settings.php
    /frontend
      class-shortcodes.php
      class-ajax.php
      class-checkout.php
    /booking
      class-lock-manager.php
      class-booking-repo.php
      class-pricing.php
    /integrations
      class-wc-hooks.php
      class-wpml.php
    /notifications
      class-email.php
      interface-sms-provider.php
      class-sms-twilio.php (optional)
  /assets
    /css
    /js
  /templates
    emails/
  /languages
```

---

## 19) REST/AJAX Endpoints (Recommended)

### 19.1 Public
- `GET /wp-json/comarine/v1/units` — list/filter units
- `GET /wp-json/comarine/v1/unit/{id}` — unit details
- `POST /wp-json/comarine/v1/lock` — attempt lock
- `POST /wp-json/comarine/v1/release` — release lock (optional)
- `POST /wp-json/comarine/v1/price` — compute price (server-side)

All POST endpoints require nonce.

---

## 20) Edge Cases & Handling

- Two users click Book on same unit at same time:
  - One lock wins; other receives “unit just reserved” message
- User abandons checkout:
  - lock expires automatically (TTL)
- Payment gateway returns to site late:
  - still confirm booking if order paid; lock token can be expired but booking should finalize
- Duplicate payment/order attempt:
  - detect existing booking by order meta; do not create a second booking
- Admin changes unit status manually while a lock exists:
  - show warning and require confirmation

---

## 21) Acceptance Tests (Definition of Done)

### Functional
- ✅ Unit can be booked once; second user blocked
- ✅ Duration changes update price instantly
- ✅ Checkout works with JCC plugin enabled
- ✅ Order paid marks booking paid + unit reserved
- ✅ Failed/cancelled order releases lock
- ✅ Emails include unit details and instructions
- ✅ Strings are translatable + WPML compatible

### Non-Functional
- ✅ No PHP warnings/notices
- ✅ Meets WP coding standards
- ✅ Secure sanitization + capability checks
- ✅ Logs available when debug enabled

---

## 22) Implementation Notes About JCC Plugin

- Our plugin must treat `processing` and `completed` as successful payment states, because gateways (including JCC) may set the paid status differently depending on product type (physical vs virtual).
- Do not hardcode gateway IDs. Use WC order status transitions instead.

---

## 23) Configuration (Admin Settings)

Create settings page:
- Lock TTL minutes (default 15)
- Booking container product (select WC product)
- Default unit status after payment: reserved/occupied
- Email templates: access instructions template
- SMS enable + provider keys (optional)
- Debug logging level

---

## 24) Future Enhancements (Post-MVP)

- Renewal payments / subscriptions
- Automated end-date transitions
- Customer portal (“My Storage”) inside My Account
- Logistics module: pickup/delivery booking, inventory tracking, API integrations
- Unit availability calendar for “move-in date” selection

---

## Appendix A — Terminology

- **Unit**: a storage space identified by Unit Code and post ID
- **Booking**: a reservation record linking a unit + duration + WC order
- **Lock**: time-limited claim to prevent concurrent booking

---

## Appendix B — Developer Checklist

- [ ] Use `dbDelta()` for table creation on activation
- [ ] Add uninstall cleanup option (keep data by default)
- [ ] Use prepared statements for all SQL
- [ ] Use `wp_timezone()` for date handling
- [ ] Ensure no caching on availability endpoints
- [ ] Use translatable strings everywhere
