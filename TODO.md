# GAIA — Unified Booking & Payment Architecture — Implementation Todo

Task: Prepare a scalable unified booking + payment workflow for Tours, Hotel
Rooms, Events and Transfers, ready for future CyberSource — WITHOUT DB redesign,
NO CyberSource integration, NO real payment processing.

## Status of existing work (already in repo & passing `_verify_architecture.php`)
- [x] Tables created: `payments`, `payment_transactions`, `invoices`, `booking_logs`, `room_bookings`
- [x] Tables modified: `booking_reference` + expanded status ENUM + user_id on all booking tables
- [x] Services: BookingReferenceService, BookingTimelineService, BookingSummaryService, PaymentService, InvoiceService, GatewayManager, BookingService, ReportingService
- [x] Helpers: booking_status_list, payment_status_list, invoice_status_list, normalize_*, *_label, booking_reference_prefix, *_badge_class, timeline_action_label
- [x] Customer details page uses reusable `components/booking-summary.php`
- [x] My Payments / My Invoices pages + nav

## Integration work
- [x] 1. `process_booking.php` (transfer) wired to: generate GAIA reference, store `booking_reference`, log "Booking Created" timeline, create payment
- [x] 2. `weroad/process_booking.php` (tour) wired to: generate GAIA reference, store `booking_reference`, log "Booking Created" timeline, create payment
- [x] 3. `BookingSummaryService::findByReference()` + `tableForType()` added (IDOR-scoped)
- [x] 4. `_verify_flow.php` smoke test created — exercises booking → payment → invoice → timeline flow end-to-end
- [x] 5. `php -l` passed on all changed/key files; migration-11 applied; flow verify script green
- [x] 6. Final implementation report produced (deliverables)

## Verification
- `_verify_architecture.php` — schema/service architecture checks (passing)
- `_verify_flow.php` — end-to-end flow smoke test (ALL FLOW CHECKS PASSED)
- `php -l` — no syntax errors in any service or booking-processing file
