# COMARINE -- Storage Units Booking System

## Functional & Technical Specification (v2)

------------------------------------------------------------------------

## 1. Project Objective

Development of a modern website for online viewing and booking of
storage units in Limassol, Cyprus.

Key goals:

-   Real-time availability
-   Duration-based pricing selection
-   Online payment integration (JCC)
-   Admin management of units and pricing
-   One-level facility (no floors)

------------------------------------------------------------------------

## 2. Storage Structure

### Category A -- 7m² (5 Units)

A1, A2, A3, A4, A5

### Category B -- 12m² (12 Units)

B1 -- B12

### Category C -- 15m² (6 Units)

C1 -- C6

### Category D -- 18m² (3 Units)

D1 -- D3

### Category E -- 30m² (12 Units)

E1 -- E12

### Category F -- 36m² (2 Units)

F1 -- F2

Total Storage Units: 40

------------------------------------------------------------------------

## 3. Pricing Structure (VAT Included)

  Size   Monthly   6-Month (per month)   Annual (per month)
  ------ --------- --------------------- --------------------
  7m²    €95       €90                   €87
  12m²   €160      €152                  €146
  15m²   €210      €200                  €190
  18m²   €240      €230                  €220
  30m²   €330      €315                  €305
  36m²   €360      €345                  €335

Note: - 6-month and annual prices represent discounted monthly rates. -
Final cost = rate × duration (6 or 12 months).

------------------------------------------------------------------------

## 4. Booking Logic

Each storage unit must provide three selectable pricing options:

1.  Monthly
2.  6 Months
3.  Annual

Frontend Flow:

1.  Select category
2.  Select specific unit
3.  Select duration
4.  View calculated total
5.  Checkout
6.  Payment via JCC
7.  Unit status updated to Reserved

------------------------------------------------------------------------

## 5. WooCommerce Integration Model

### Recommended Architecture

Each storage unit as individual product or custom post type.

Required Meta Fields:

-   unit_code
-   size_m2
-   category_letter
-   monthly_price
-   semester_price
-   yearly_price
-   availability_status

------------------------------------------------------------------------

## 6. Availability Rules

Status options:

-   Available
-   Reserved
-   Occupied

Only one active booking per storage unit.

------------------------------------------------------------------------

## 7. Payment Gateway

-   JCC WooCommerce Plugin
-   Visa / Mastercard
-   3D Secure enabled

On successful payment:

-   Unit marked as Reserved
-   Confirmation email sent

------------------------------------------------------------------------

## 8. Admin Capabilities

Admin must be able to:

-   Edit pricing
-   Update availability
-   Add/remove units
-   View bookings
-   Track lease expiry

------------------------------------------------------------------------

## 9. Business Rules

-   Single-level facility
-   VAT included in pricing
-   No double bookings
-   Pricing editable from backend

------------------------------------------------------------------------

## End of Specification
