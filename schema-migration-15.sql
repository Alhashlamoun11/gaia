-- ============================================================
-- GAIA TOURS & TRAVEL — Admin Panel Integration
-- Migration #15
-- ------------------------------------------------------------
-- Objective: Create the missing `hotel_amenities` table that
-- is referenced by the admin hotel detail page.
-- Safe to run repeatedly (idempotent).
-- ============================================================
USE gaia_tours;

CREATE TABLE IF NOT EXISTS hotel_amenities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hotel_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  icon VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ha_hotel FOREIGN KEY (hotel_id) REFERENCES hotels (id) ON DELETE CASCADE,
  INDEX idx_ha_hotel (hotel_id),
  INDEX idx_ha_active (is_active)
) ENGINE=InnoDB;