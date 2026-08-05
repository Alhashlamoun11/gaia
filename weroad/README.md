# WeRoad Tour Booking Platform — Native PHP + MySQL

A complete, dynamic, production-ready tour booking platform transformed from the
WeRoad frontend prototype. Built with **plain PHP 8+ (PDO)** and **MySQL** — no frameworks,
no Composer, no Laravel.

## Features
- **Homepage** (`index.php`) — deals, categories, loved trips, and reviews all pulled from MySQL
- **Search & Filter** (`search.php`) — search by keyword, category, price, duration, effort
- **Trip Detail** (`trip.php`) — itinerary, included/excluded, optional activities, FAQs, departures
- **Booking / Checkout** (`booking.php`) — gender counters, room preferences, extras, dynamic order summary, promo code AJAX
- **Promo validation** (`validate_promo.php`) — JSON endpoint
- **Booking processing** (`process_booking.php`) — computes price + uses PDO transactions

## Pricing Formula
```
Total = Departure price + Flexible cancellation (€99) + Private room (€247) − Promo discount
```

## File Structure
```
weroad/
├── schema.sql            → DDL + seed data (trips, itineraries, inclusions, activities, FAQs, departures, reviews, promos, bookings)
├── config.php            → DB credentials + pricing constants
├── db.php                → PDO connection factory
├── index.php             → dynamic homepage
├── search.php            → search + filters
├── trip.php              → trip detail
├── booking.php           → checkout
├── process_booking.php   → booking insert + pricing + transactions
├── validate_promo.php    → promo validation (JSON)
├── styles.css            → shared styles (from prototype)
└── script.js             → shared scripts (from prototype)
```

## Setup (XAMPP / WAMP / native MySQL)

1. **Start MySQL** (e.g. via XAMPP control panel).

2. **Import the schema** into MySQL:
   ```bash
   mysql -u root -p < schema.sql
   ```
   (Omit `-p` if your root user has no password.)
   This uses the same `gaia_tours` database as the KiwiTaxi system but all tables
   are prefixed with `weroad_` to avoid name clashes.

3. **Verify DB credentials** in `config.php` (defaults: host `127.0.0.1`, user `root`, empty password).

4. **Run the app** with the PHP built-in server from the `weroad` folder:
   ```bash
   php -S localhost:8001
   ```
   Then open `http://localhost:8001/index.php`.

## Testing the endpoints

- **Search page**: `search.php?q=Jordan&category=Middle East`
- **Promo validation**:
  ```
  validate_promo.php?code=WEROAD10
  ```
- **Create a booking** (POST to `process_booking.php`):
  ```
  trip_id=1&departure_id=1&full_name=John Watson&email=john@mail.com
  &travellers_female=1&travellers_male=1&mixed_room=1
  &flexible_cancellation=1&private_room=1&promo_code=WEROAD10
  ```

## Notes
- All dynamic input is handled with `PDO::prepare()` to prevent SQL injection.
- `process_booking.php` wraps inserts in a transaction
  (`beginTransaction()`, `commit()`, `rollBack()`).
- Edit the pricing constants in `config.php` to adjust extras / promo behaviour.

