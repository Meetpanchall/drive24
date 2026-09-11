-- ==========================================================
-- DRIVE24 extension tables (SRS: chat, reviews, loans,
-- insurance, inspection bookings, history, notifications,
-- OTP, password resets, escrow ledger, listing gallery)
-- Idempotent: safe to run on an existing drive24 database.
-- ==========================================================

CREATE TABLE IF NOT EXISTS listing_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  image VARCHAR(160) NOT NULL,
  label VARCHAR(80) DEFAULT NULL,
  sort_order TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_li_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  author_id INT NOT NULL,
  target_user_id INT NOT NULL,
  reviewer_role ENUM('buyer','seller') NOT NULL DEFAULT 'buyer',
  rating TINYINT NOT NULL,
  title VARCHAR(160) DEFAULT NULL,
  comment TEXT,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rev_listing (listing_id),
  INDEX idx_rev_target (target_user_id),
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_threads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT DEFAULT NULL,
  buyer_id INT NOT NULL,
  seller_id INT NOT NULL,
  subject VARCHAR(160) DEFAULT NULL,
  status ENUM('open','closed','flagged') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_ct_parties (buyer_id, seller_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  sender_id INT NOT NULL,
  body VARCHAR(2000) NOT NULL,
  image VARCHAR(160) DEFAULT NULL,
  flagged TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_cm_thread (thread_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  lender VARCHAR(80) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  tenure_months SMALLINT NOT NULL DEFAULT 60,
  rate DECIMAL(5,2) NOT NULL DEFAULT 9.50,
  employment VARCHAR(40) DEFAULT NULL,
  monthly_income DECIMAL(12,2) DEFAULT NULL,
  status ENUM('new','under_review','approved','rejected','disbursed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_loan_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS insurance_quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  listing_id INT NOT NULL,
  insurer VARCHAR(80) NOT NULL,
  idv DECIMAL(12,2) NOT NULL,
  premium DECIMAL(12,2) NOT NULL,
  addons VARCHAR(255) DEFAULT NULL,
  status ENUM('quoted','purchased','expired') NOT NULL DEFAULT 'quoted',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  mode ENUM('home','hub') NOT NULL DEFAULT 'home',
  slot_date DATE NOT NULL,
  slot_time VARCHAR(20) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  status ENUM('requested','confirmed','completed','cancelled') NOT NULL DEFAULT 'requested',
  report_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_ib_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_history (
  vehicle_id INT PRIMARY KEY,
  accidents TINYINT NOT NULL DEFAULT 0,
  accident_details VARCHAR(400) DEFAULT NULL,
  insurance_claims TINYINT NOT NULL DEFAULT 0,
  challans TINYINT NOT NULL DEFAULT 0,
  challan_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_records TINYINT NOT NULL DEFAULT 0,
  flood_damage TINYINT(1) NOT NULL DEFAULT 0,
  theft_record TINYINT(1) NOT NULL DEFAULT 0,
  owners_history VARCHAR(255) DEFAULT NULL,
  report_summary VARCHAR(500) DEFAULT NULL,
  checked_on DATE DEFAULT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(400) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr_token (token_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  mobile VARCHAR(20) NOT NULL,
  code_hash VARCHAR(128) NOT NULL,
  purpose VARCHAR(30) NOT NULL DEFAULT 'verify',
  attempts TINYINT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_mobile (mobile)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS escrow_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  kind ENUM('hold','release','refund') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_esc_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_features (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  category ENUM('comfort','safety','entertainment','exterior') NOT NULL,
  feature VARCHAR(80) NOT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  UNIQUE KEY uq_vf (vehicle_id, feature),
  INDEX idx_vf_cat (category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rc_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  listing_id INT NOT NULL,
  buyer_id INT NOT NULL,
  seller_id INT NOT NULL,
  status ENUM('sale_completed','documents_verified','application_filed','rto_processing','transfer_completed') NOT NULL DEFAULT 'sale_completed',
  rto_office VARCHAR(120) DEFAULT NULL,
  application_no VARCHAR(60) DEFAULT NULL,
  remark VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  INDEX idx_rc_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  buyer_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_bid_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL,
  user_id INT NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer VARCHAR(1000) DEFAULT NULL,
  answered_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_q_listing (listing_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rentals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_no VARCHAR(40) NOT NULL UNIQUE,
  listing_id INT NOT NULL, user_id INT NOT NULL,
  pickup_location VARCHAR(160) NOT NULL, return_location VARCHAR(160) NOT NULL,
  pickup_at DATETIME NOT NULL, return_at DATETIME NOT NULL,
  days SMALLINT NOT NULL DEFAULT 1,
  price_per_day DECIMAL(10,2) NOT NULL,
  rental_amount DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_charged DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','confirmed','active','returned','settled','cancelled') NOT NULL DEFAULT 'pending',
  rzp_order_id VARCHAR(60) DEFAULT NULL, rzp_payment_id VARCHAR(60) DEFAULT NULL,
  pickup_otp VARCHAR(10) DEFAULT NULL,
  pickup_odo INT DEFAULT NULL, pickup_fuel TINYINT DEFAULT NULL,
  return_odo INT DEFAULT NULL, return_fuel TINYINT DEFAULT NULL,
  pickup_notes VARCHAR(255) DEFAULT NULL, return_notes VARCHAR(255) DEFAULT NULL,
  refund_amount DECIMAL(12,2) DEFAULT NULL, refund_status VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rent_listing (listing_id), INDEX idx_rent_user (user_id), INDEX idx_rent_dates (pickup_at, return_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rental_charges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rental_id INT NOT NULL,
  kind ENUM('extra_km','fuel','damage','cleaning','late','other') NOT NULL DEFAULT 'other',
  label VARCHAR(160) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rental_id) REFERENCES rentals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

