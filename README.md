# Car Rental / Transfer System — Native PHP + MySQL

A simple, dependency-free Car Rental / Transfer backend built with **plain PHP 8+** and **MySQL (PDO)**.
No frameworks, no Laravel, no Composer.

## Features
- Search & availability (origin, destination, date, passenger count)
- Car class selection (Economy, Business, SUV, Minivan) with passenger/luggage limits
- Checkout & booking with flight details, return-trip toggle, passenger details, extras & add-ons
- Promo code validation
- Dynamic pricing: `Base price × Car multiplier + Return fee + Extras − Discount`
- PDO prepared statements (SQL-injection safe) + transactions for booking inserts

## File Structure
```
schema.sql            → DDL + seed data (routes, car classes, promo codes,
                        reviews, faq, services, partners)
config.php            → DB credentials + pricing constants
db.php                → PDO connection factory
search.php            → search routes + car classes (JSON)
validate_promo.php    → promo code validation (JSON)
process_booking.php   → booking insert + pricing + transactions (JSON)
index.php             → dynamic home page (car classes, services, reviews, FAQ, partners)
kiwitaxi-clone.php    → dynamic duplicate home page
route.php             → dynamic route + car class selection with prices
checkout.php          → dynamic booking form (live pricing + promo + booking)
```

## Pages / Flow
- `index.php` — home page. All data (car classes, services, reviews, FAQ, partners) loaded from MySQL. The search form posts to `route.php`.
- `route.php` — shows the selected route and available car classes with prices computed from the DB. Each car card links to `checkout.php`.
- `checkout.php` — booking form. Loads route + car class from query params, shows a live price breakdown, validates promo codes via AJAX (`validate_promo.php`), and submits to `process_booking.php`.
- `process_booking.php` — the JSON API that saves the booking inside a transaction.

Open the app via a PHP server (e.g. XAMPP/htdocs) or `php -S localhost:8000`.

## Setup (XAMPP / WAMP / native MySQL)

1. **Start MySQL** (e.g. via XAMPP control panel).

2. **Import the schema** into MySQL:
   ```bash
   mysql -u root -p < schema.sql
   ```
   (If your root user has no password, omit `-p`.)
   This creates the `gaia_tours` database, all tables, and seed data.

3. **Verify DB credentials** in `config.php` (defaults: host `127.0.0.1`, user `root`, empty password).

4. **Test the endpoints** — the scripts return JSON:

   - Search:
     ```
     search.php?origin=Milan&destination=Milan&passengers=2
     ```
   - Validate promo:
     ```
     validate_promo.php?code=WELCOME10
     ```
   - Create a booking (POST):
     ```
     process_booking.php
     ```
     with a POST body such as:
     ```php
     route_id=1&car_class_id=1&origin=Milan%20Bergamo%20Airport
     &destination=Milan&pickup_date=2026-08-11&pickup_time=18:30
     &passenger_count=2&flight_number=AA123&arrival_time=18:00
     &destination_address=Laguna%20Hotel&return_trip=1
     &full_name=John%20Watson&email=john@mail.com&phone=%2B12345
     &child_seats=1&water_bottles=2&pet_carrier=1&women_driver=1
     &promo_code=WELCOME10
     ```

## Notes
- All dynamic input is handled with `PDO::prepare()` to prevent SQL injection.
- `process_booking.php` wraps the booking + extras inserts in a transaction
  (`beginTransaction()`, `commit()`, `rollBack()`).
- Edit the pricing constants in `config.php` to adjust fares for extras and the return trip.
