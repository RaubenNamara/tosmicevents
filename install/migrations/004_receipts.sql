-- Payment receipts: one row per payment received for a confirmed booking.
CREATE TABLE IF NOT EXISTS receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(20) NOT NULL UNIQUE,
  token CHAR(40) NOT NULL UNIQUE,
  booking_id INT UNSIGNED NOT NULL,
  amount INT UNSIGNED NOT NULL,
  method VARCHAR(40) NOT NULL,
  payment_ref VARCHAR(80) NULL,
  paid_on DATE NOT NULL,
  received_by VARCHAR(120) NOT NULL,
  note VARCHAR(255) NULL,
  admin_id INT UNSIGNED NULL,
  is_void TINYINT(1) NOT NULL DEFAULT 0,
  void_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_booking (booking_id),
  CONSTRAINT fk_receipt_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
