-- schema-migration-22.sql
-- Forgot Password OTP flow
-- Adds otp_code column to password_resets for 6-digit email verification codes

ALTER TABLE password_resets
  ADD COLUMN otp_code VARCHAR(6) NULL DEFAULT NULL AFTER token_hash;
