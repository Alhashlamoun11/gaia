ALTER TABLE hotels ADD COLUMN admin_commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price_from;
