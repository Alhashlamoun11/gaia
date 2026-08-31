-- ============================================================
-- GAIA TOURS & TRAVEL
-- Phase: Hotel Partner Onboarding & Role Separation
-- Schema migration #19
-- ============================================================
USE gaia_tours;

-- Add hotel_id to track which hotel was created from this application
ALTER TABLE partner_applications 
ADD COLUMN hotel_id INT UNSIGNED NULL AFTER user_id,
ADD CONSTRAINT fk_partner_app_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE SET NULL;
