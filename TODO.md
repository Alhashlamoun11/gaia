# GAIA Authentication & Account System — Implementation TODO

## Steps
- [x] Analyze existing codebase (config, db, bootstrap, router, header, schemas)
- [x] Create implementation plan (approved)

## Database
- [ ] Create `schema-migration-auth.sql` (roles, permissions, users, hotels_users, payments, booking tables, ALTERs)
- [ ] Register migration in `_run_migrations.php`
- [ ] Run migration against DB

## Auth Core
- [ ] Create `auth/Auth.php` (register, login, logout, throttle, remember-me, hashing)
- [ ] Create `auth/csrf.php` (tokens)
- [ ] Create `auth/middleware.php` (require login/role/permission)
- [ ] Create `auth/helpers.php` (auth_user, auth_check, auth_role, escaping)

## Auth Pages
- [ ] Create `login.php`
- [ ] Create `register.php`
- [ ] Create `forgot-password.php`
- [ ] Create `reset-password.php`
- [ ] Create `logout.php`

## Account Pages
- [ ] Create `account/index.php` (dashboard)
- [ ] Create `account/profile.php`
- [ ] Create `account/my-bookings.php`
- [ ] Create `account/claim-booking.php`

## Header + Routing + i18n
- [ ] Update `components/gaia-header.php` (auth-aware nav)
- [ ] Update `.htaccess` routes
- [ ] Update `router.php` routes
- [ ] Add EN/AR translations for auth/account

## Verification
- [ ] Run migration
- [ ] Smoke-test auth flow
