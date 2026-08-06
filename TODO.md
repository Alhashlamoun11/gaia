# Customer Dashboard — Implementation TODO

## Booking Ownership Enforcement
- [x] `process_booking.php` — add auth gate + require authenticated user_id
- [x] `weroad/process_booking.php` — add auth gate + require authenticated user_id
- [x] `checkout.php` — add login gate (redirect to login, return after)
- [x] `weroad/booking.php` — add login gate (redirect to login, return after)
- [x] `login.php` — honor validated internal `?redirect=` after successful login

## Routes
- [x] `router.php` — add /account/dashboard, /account/security, /account/bookings/{ref}
- [x] `.htaccess` — mirror the new account routes

## Dashboard Homepage
- [x] `account/index.php` — quick stats, recent activity, upcoming reservations, quick actions

## My Bookings
- [x] `account/bookings.php` — unified table, filters, pagination

## Booking Details
- [x] `account/booking.php` — load by booking_reference via BookingSummaryService

## My Payments
- [x] `account/payments.php` — status filters + pagination

## My Invoices
- [ ] `account/invoices.php` — view/print actions + printable layout
- [ ] `account/invoice-print.php` — printable invoice (browser print view)

## Profile
- [ ] `account/profile.php` — avatar file upload + validation

## Security Page
- [x] `account/security.php` — change password + security info (NEW)

## Navigation
- [x] `account/_layout.php` — sidebar (Dashboard/Bookings/Payments/Invoices/Profile/Security/Logout) + active highlighting

## Localization
- [ ] `schema-migration-12.sql` — add all missing EN/AR translation keys

## Security Audit
- [ ] Verify ownership, CSRF, XSS escaping, PDO prepared statements
- [ ] Produce security verification report + implementation report
