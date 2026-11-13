-- Client feedback: reviews submitted from the public Reviews page wait for admin approval.
ALTER TABLE testimonials
  ADD COLUMN phone VARCHAR(40) NULL AFTER role,
  ADD COLUMN email VARCHAR(190) NULL AFTER phone,
  ADD COLUMN booking_id INT UNSIGNED NULL AFTER email,
  ADD COLUMN source ENUM('admin','client') NOT NULL DEFAULT 'admin' AFTER booking_id,
  ADD COLUMN reviewed TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active,
  ADD COLUMN ip VARCHAR(45) NULL AFTER reviewed,
  ADD KEY idx_review_queue (source, reviewed),
  ADD CONSTRAINT fk_testimonial_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL;
