-- Tosmic Events database schema + starter content (taken from the booking packages flyer)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(64) PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS slides (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  eyebrow VARCHAR(120) NULL,
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(400) NULL,
  image VARCHAR(255) NULL,
  layout ENUM('split','full') NOT NULL DEFAULT 'split',
  button_text VARCHAR(60) NULL,
  button_link VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  tagline VARCHAR(255) NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0,
  events TEXT NULL,
  image VARCHAR(255) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'other',
  image VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190) NULL,
  package_id INT UNSIGNED NULL,
  event_type VARCHAR(120) NULL,
  event_date DATE NULL,
  start_time VARCHAR(20) NULL,
  duration_hours DECIMAL(4,1) NULL,
  venue VARCHAR(255) NULL,
  guests INT UNSIGNED NULL,
  message TEXT NULL,
  status ENUM('new','contacted','confirmed','cancelled') NOT NULL DEFAULT 'new',
  is_paid TINYINT(1) NOT NULL DEFAULT 0,
  quoted_amount INT UNSIGNED NULL,
  admin_notes TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_event_date (event_date),
  CONSTRAINT fk_booking_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(160) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  booking_id INT UNSIGNED NULL,
  source ENUM('admin','client') NOT NULL DEFAULT 'admin',
  message TEXT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  reviewed TINYINT(1) NOT NULL DEFAULT 1,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_review_queue (source, reviewed),
  CONSTRAINT fk_testimonial_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at),
  KEY idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS admin_logins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 1,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_time (admin_id, created_at),
  CONSTRAINT fk_login_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Starter content ----------
INSERT IGNORE INTO settings (skey, svalue) VALUES
('site_name', 'Tosmic Events'),
('tagline', 'DJ Tosmic · Professional DJ services for every kind of event'),
('phone1', '+256 750 547 775'),
('phone2', '+256 784 081 742'),
('whatsapp', '256750547775'),
('email', ''),
('website', 'tosmicevents.com'),
('instagram', 'tosmicevents'),
('tiktok', 'tosmicevents'),
('location', 'Uganda'),
('package_includes', 'Professional DJ equipment\nA DJ booth\nDynamic basic lighting\nSmoke effect'),
('package_note', 'Our packages include professional DJ equipment, a DJ booth, dynamic basic lighting, and a smoke effect (sound equipment is excluded). Final rates are adaptable depending on event distance and duration. For larger setups or full event production, feel free to reach out to Tosmic Events for a detailed, custom quotation.'),
('about_title', 'Meet DJ Tosmic'),
('about_text', 'Tosmic Events is the home of DJ Tosmic. We bring professional DJ equipment, a DJ booth, dynamic lighting and a smoke effect to corporate events, weddings, introductions, graduations, concerts, lounges, campus bashes and private parties.\n\nEvery crowd is different, so every set is planned around your guests, your programme and the mood you want, from the first entrance to the last dance.'),
('hero_cta_text', 'Book DJ Tosmic');

INSERT INTO packages (id, name, tagline, price, events, image, is_featured, sort_order) VALUES
(1, 'Corporate Events', 'Polished sets for brands, teams and formal evenings.', 1500000, 'End of year events\nBrand/company launch\nGala dinners\nAward nights\nTeam building & expos', 'seed/corporate.jpg', 0, 1),
(2, 'Social Gatherings', 'The soundtrack to your biggest family moments.', 1000000, 'Wedding reception & after party\nKwanjula/Kuhingira\nGraduation/House parties\nRotary Events', 'seed/social.jpg', 1, 2),
(3, 'Other Events', 'High energy for clubs, campuses and private parties.', 600000, 'Lounge, bar & clubs\nConcerts\nPrivate & pool parties\nSchool parties\nCampus & beach bashes', 'seed/other.jpg', 0, 3)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO slides (eyebrow, title, subtitle, image, layout, button_text, button_link, sort_order)
SELECT * FROM (
  SELECT 'Tosmic Events presents' AS eyebrow, 'DJ Tosmic' AS title, 'Professional DJ equipment, a DJ booth, dynamic lighting and a smoke effect, all in one booking.' AS subtitle, 'seed/dj.jpg' AS image, 'split' AS layout, 'Book DJ Tosmic' AS button_text, 'booking.php' AS button_link, 1 AS sort_order
  UNION ALL SELECT 'Corporate events', 'Make your brand the talk of the night', 'End of year parties, launches, gala dinners, award nights, team building and expos.', 'seed/corporate.jpg', 'split', 'See corporate package', 'packages.php#package-1', 2
  UNION ALL SELECT 'Social gatherings', 'Weddings, Kwanjula and every celebration', 'Wedding receptions and after parties, Kwanjula and Kuhingira, graduations and house parties.', 'seed/social.jpg', 'split', 'See social package', 'packages.php#package-2', 3
  UNION ALL SELECT 'Lounges · Concerts · Campus', 'Turn up the energy', 'Clubs, concerts, pool parties, school parties and campus or beach bashes.', 'seed/other.jpg', 'split', 'See all packages', 'packages.php', 4
) s WHERE NOT EXISTS (SELECT 1 FROM slides);

INSERT INTO gallery (title, category, image, sort_order)
SELECT * FROM (
  SELECT 'DJ Tosmic' AS title, 'other' AS category, 'seed/dj.jpg' AS image, 1 AS sort_order
  UNION ALL SELECT 'On the decks at a corporate dinner', 'corporate', 'seed/gallery-corporate.png', 2
  UNION ALL SELECT 'Wedding reception', 'weddings', 'seed/gallery-social.png', 3
  UNION ALL SELECT 'Ready for the party', 'parties', 'seed/gallery-other.png', 4
) g WHERE NOT EXISTS (SELECT 1 FROM gallery);
