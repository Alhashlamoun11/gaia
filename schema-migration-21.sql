-- ALTER TABLE hotel_bookings to persist commission percent
ALTER TABLE hotel_bookings ADD COLUMN admin_commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER total_price;
