-- "Completed" is replaced by a separate "Paid" tick that can sit alongside "Confirmed".
ALTER TABLE bookings ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- completed bookings become confirmed
UPDATE bookings SET status = 'confirmed' WHERE status = 'completed';

-- bookings whose receipts already cover the agreed amount are marked paid
UPDATE bookings b
LEFT JOIN packages p ON p.id = b.package_id
SET b.is_paid = 1
WHERE COALESCE(b.quoted_amount, p.price) IS NOT NULL
  AND (SELECT COALESCE(SUM(r.amount), 0) FROM receipts r WHERE r.booking_id = b.id AND r.is_void = 0) >= COALESCE(b.quoted_amount, p.price)
  AND EXISTS (SELECT 1 FROM receipts r2 WHERE r2.booking_id = b.id AND r2.is_void = 0);

ALTER TABLE bookings MODIFY status ENUM('new','contacted','confirmed','cancelled') NOT NULL DEFAULT 'new';
