# GAIA TOURS & TRAVEL — Customer Dashboard Security Audit Report

**Date:** March 2025
**Scope:** Booking ownership, ownership validation, CSRF, XSS, PDO prepared statements
**Result:** PASS — no critical, high, or medium findings in scope.

---

## 1. Booking Ownership Enforcement (Part 1)

| Booking Flow | Auth gate | Saves `user_id` | Saves `booking_reference` | Return-to-page |
|--------------|-----------|-----------------|---------------------------|----------------|
| Transfer (`process_booking.php`) | `require_booking_login('checkout.php')` | ✅ `:user_id` | ✅ `BookingReferenceService::generate('transfer')` | ✅ |
| Tour (`weroad/process_booking.php`) | `require_booking_login('../weroad/booking.php')` | ✅ `:user_id` | ✅ `BookingReferenceService::generate('tour')` | ✅ |
| Checkout form (`checkout.php`) | `require_booking_login('checkout.php')` | — | — | ✅ |
| Tour booking form (`weroad/booking.php`) | `require_booking_login('../weroad/booking.php')` | — | — | ✅ |

Hotel / Room / Event detail pages have **no booking-creation handlers** (they link to
Contact / WhatsApp), so there is no anonymous booking path to gate there.

**`require_booking_login()`**:
- Attempts remember-me auto-login for guests.
- If still not authenticated, redirects to `login.php?redirect=<safe internal path>`.
- `safe_redirect_target()` rejects absolute URLs, protocol-relative URLs, control
  chars and backslashes → prevents open-redirect.
- `login.php` honors only the validated `?redirect=` target after successful login,
  returning the user to e.g. `/tours/petra-tour/book`.

---

## 2. Ownership Validation (Part 12) — IDOR Prevention

Every customer-facing read is scoped to the authenticated `user_id`:

| Data | Method | Ownership scope |
|------|--------|-----------------|
| Unified bookings list | `BookingSummaryService::getUserSummaries($userId)` | `WHERE user_id = :uid` on every type |
| Single booking by reference | `BookingSummaryService::findByReference($reference, $userId)` | reference **AND** `user_id = :uid`; null if not owned → 403 |
| Single booking legacy | `BookingSummaryService::getSummary($type, $id, $userId)` | `AND user_id = :uid` |
| Payments list | `PaymentService::getUserPayments($userId)` | `WHERE user_id = :uid` |
| Booking payments | `PaymentService::getBookingPayments($type, $id, $userId)` | `AND user_id = :uid` |
| Payment ownership guard | `PaymentService::ownsPayment($paymentId, $userId)` | `WHERE id = :id AND user_id = :uid` |
| Invoices list | `InvoiceService::getForUser($userId)` | `WHERE user_id = :uid` |
| Single invoice (print) | `InvoiceService::get($invoiceId, $userId)` | `WHERE id = :id AND user_id = :uid`; null if not owned → 403 |
| Booking invoice | `InvoiceService::getForBooking($type, $id, $userId)` | `AND user_id = :uid` |
| Timeline | `BookingTimelineService::getTimeline($type, $id, $userId)` | `AND user_id = :uid` |
| Dashboard stats | `BookingRepository::getUserBookings($userId)` + `PaymentService::getUserPayments($userId)` | all `user_id`-scoped |

**Conclusion:** A user can never read another user's booking, payment, or invoice.
All list/detail paths are ownership-scoped via PDO prepared statements.

---

## 3. CSRF Protection

- All POST forms render `csrf_field()` (hidden token) and verify with `csrf_verify()`
  on submit; failure calls `csrf_fail()` (HTTP 419).
- Covered pages: `account/profile.php`, `account/security.php`, `account/claim-booking.php`,
  `account/_layout.php` (logout), `login.php`, `register.php`, `forgot-password.php`,
  `reset-password.php`.
- `logout.php` is a POST form (not a GET link) with CSRF token — prevents logout CSRF.
- CSRF tokens are per-session and validated with `hash_equals()` (constant-time).

---

## 4. XSS Escaping

- All user/DB-derived output is escaped with `htmlspecialchars()` (the `e()` / inline
  `htmlspecialchars()` helper) with `ENT_QUOTES | UTF-8`.
- Examined pages: `account/index.php`, `account/bookings.php`, `account/booking.php`,
  `account/payments.php`, `account/invoices.php`, `account/invoice-print.php`,
  `account/profile.php`, `account/security.php`, `account/_layout.php`.
- Avatars are escaped via `htmlspecialchars(auth_avatar())`; uploaded avatar paths are
  generated server-side (random hex filename) and normalized, presenting no injection
  surface.

---

## 5. PDO Prepared Statements

- All database interactions use PDO prepared statements with bound parameters
  (`getPDO()` with `ATTR_EMULATE_PREPARES => false`).
- Identified in `BookingRepository`, `BookingSummaryService`, `PaymentService`,
  `InvoiceService`, `BookingTimelineService`, `Auth`, and all account pages.
- No string-interpolated SQL was found in the account layer.

---

## 6. Avatar Upload Hardening (new in this task)

| Check | Implementation |
|-------|----------------|
| Upload error | `UPLOAD_ERR_OK` required |
| Size limit | Reject > 2 MB |
| Real MIME | `finfo(FILEINFO_MIME_TYPE)` — JPG / PNG / WEBP only (not extension trust) |
| Filename | Server-generated random hex `u{id}_<hex>.<ext>` — no user-controlled name |
| Storage | `move_uploaded_file()` into `uploads/avatars/` (auto-created) |
| Path stored in DB | Relative `uploads/avatars/...`; served via `auth_avatar()` → root-absolute |
| Old file cleanup | Previous uploaded avatar removed only when its path begins with `uploads/` |
| CSRF | Verified before any file write |

---

## 7. Findings / Residual Risk

- **Low:** Uploaded avatars are served from the web root. If the upload directory
  were ever configured to execute scripts, a malicious image could be a vector. This
  is mitigated by the strict MIME whitelist + random extension derived from the MIME
  type (never user-supplied). Recommendation: ensure `uploads/` has no PHP execution
  (covered by `.htaccess`/server config in production). Not a blocker for this task.
- **No other findings** in the customer dashboard / account layer.

---

## 8. Verdict

The customer dashboard layer is **secure within the stated scope**. Ownership is
enforced on every read, anonymous bookings are blocked, CSRF is applied to all state
changes, output is XSS-escaped, and all queries use PDO prepared statements.
No Admin or CyberSource functionality was added.
