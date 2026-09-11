-- ==========================================================
-- DRIVE24 - Online used car marketplace
-- MySQL 8.x schema + demo data
-- Import:  mysql -u root -p < database/schema.sql
-- ==========================================================
DROP DATABASE IF EXISTS drive24;
CREATE DATABASE drive24 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drive24;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  mobile VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('buyer','seller','dealer','admin','support') NOT NULL DEFAULT 'buyer',
  city VARCHAR(80) DEFAULT NULL,
  company VARCHAR(120) DEFAULT NULL,
  kyc_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  mobile_verified TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  make VARCHAR(60) NOT NULL,
  model VARCHAR(80) NOT NULL,
  variant VARCHAR(80) DEFAULT NULL,
  year SMALLINT NOT NULL,
  body_type VARCHAR(40) NOT NULL,
  fuel_type ENUM('Petrol','Diesel','CNG','Electric','Hybrid') NOT NULL,
  transmission ENUM('Manual','Automatic') NOT NULL,
  km_driven INT NOT NULL DEFAULT 0,
  owners TINYINT NOT NULL DEFAULT 1,
  color VARCHAR(40) DEFAULT NULL,
  reg_number VARCHAR(20) DEFAULT NULL,
  reg_state VARCHAR(60) DEFAULT NULL,
  vin VARCHAR(40) DEFAULT NULL,
  engine_cc INT DEFAULT NULL,
  power_bhp VARCHAR(30) DEFAULT NULL,
  mileage_kmpl DECIMAL(4,1) DEFAULT NULL,
  seats TINYINT DEFAULT 5,
  insurance_valid_till DATE DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  area VARCHAR(80) DEFAULT NULL,
  image VARCHAR(120) DEFAULT 'car1.jpg',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_make (make), INDEX idx_body (body_type), INDEX idx_fuel (fuel_type)
) ENGINE=InnoDB;

CREATE TABLE listings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  seller_id INT NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  original_price DECIMAL(12,2) DEFAULT NULL,
  status ENUM('draft','pending','approved','rejected','reserved','sold') NOT NULL DEFAULT 'pending',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  certified TINYINT(1) NOT NULL DEFAULT 1,
  inspection_score TINYINT DEFAULT 0,
  views INT NOT NULL DEFAULT 0,
  auction_enabled TINYINT(1) NOT NULL DEFAULT 0,
  auction_ends_at DATETIME DEFAULT NULL,
  starting_bid DECIMAL(12,2) DEFAULT NULL,
  rental_enabled TINYINT(1) NOT NULL DEFAULT 0,
  price_per_day DECIMAL(10,2) DEFAULT NULL,
  km_limit_day INT NOT NULL DEFAULT 250,
  extra_km_rate DECIMAL(8,2) NOT NULL DEFAULT 12.00,
  security_deposit DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
  model_3d VARCHAR(160) DEFAULT NULL,
  hold_buyer_id INT DEFAULT NULL,
  hold_until DATETIME DEFAULT NULL,
  rejection_note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_listing_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
  CONSTRAINT fk_listing_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status), INDEX idx_price (price)
) ENGINE=InnoDB;

CREATE TABLE wishlists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, listing_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wish (user_id, listing_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE saved_searches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, title VARCHAR(160) NOT NULL, query_string VARCHAR(500) NOT NULL,
  alert_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL, buyer_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL, counter_amount DECIMAL(12,2) DEFAULT NULL,
  message VARCHAR(400) DEFAULT NULL,
  status ENUM('new','countered','accepted','rejected') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE test_drives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  listing_id INT NOT NULL, user_id INT NOT NULL,
  mode ENUM('home','hub') NOT NULL DEFAULT 'home',
  slot_date DATE NOT NULL, slot_time VARCHAR(20) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  executive VARCHAR(120) DEFAULT NULL,
  status ENUM('requested','confirmed','completed','cancelled') NOT NULL DEFAULT 'requested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inspections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL, inspector VARCHAR(120) DEFAULT NULL,
  score TINYINT NOT NULL DEFAULT 0,
  engine_score TINYINT DEFAULT 0, exterior_score TINYINT DEFAULT 0,
  interior_score TINYINT DEFAULT 0, electrical_score TINYINT DEFAULT 0, tyres_score TINYINT DEFAULT 0,
  accident_history VARCHAR(120) DEFAULT 'No major accident',
  remarks VARCHAR(400) DEFAULT NULL,
  status ENUM('scheduled','completed','failed') NOT NULL DEFAULT 'completed',
  inspected_on DATE DEFAULT NULL,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(40) NOT NULL UNIQUE,
  listing_id INT NOT NULL, buyer_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL, booking_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  finance_opted TINYINT(1) NOT NULL DEFAULT 0,
  loan_amount DECIMAL(12,2) DEFAULT 0, tenure_months SMALLINT DEFAULT 0,
  status ENUM('pending','confirmed','processing','in_transit','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  delivery_city VARCHAR(80) DEFAULT NULL, delivery_address VARCHAR(255) DEFAULT NULL,
  delivery_date DATE DEFAULT NULL,
  handover_otp VARCHAR(10) DEFAULT NULL,
  handover_odo INT DEFAULT NULL,
  handover_fuel TINYINT DEFAULT NULL,
  handover_notes VARCHAR(255) DEFAULT NULL,
  handover_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(id),
  FOREIGN KEY (buyer_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL, txn_ref VARCHAR(60) NOT NULL,
  method ENUM('upi','card','netbanking','finance','wallet') NOT NULL DEFAULT 'upi',
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  seller_id INT NOT NULL, order_id INT DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL, utr VARCHAR(40) DEFAULT NULL,
  status ENUM('pending','processing','paid','failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT NOT NULL
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

CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL, order_id INT DEFAULT NULL, listing_id INT DEFAULT NULL,
  doc_type VARCHAR(60) NOT NULL, doc_name VARCHAR(160) NOT NULL,
  file_url VARCHAR(160) DEFAULT NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL, mobile VARCHAR(20) NOT NULL, city VARCHAR(80) DEFAULT NULL,
  make VARCHAR(60) DEFAULT NULL, model VARCHAR(80) DEFAULT NULL, year SMALLINT DEFAULT NULL,
  km_driven INT DEFAULT NULL, fuel_type VARCHAR(20) DEFAULT NULL,
  quote_low DECIMAL(12,2) DEFAULT NULL, quote_high DECIMAL(12,2) DEFAULT NULL,
  status ENUM('new','contacted','inspection','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL, subject VARCHAR(160) NOT NULL, category VARCHAR(60) DEFAULT 'general',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  message TEXT NOT NULL, status ENUM('open','processing','completed') NOT NULL DEFAULT 'open',
  resolution TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL, action VARCHAR(80) NOT NULL, detail VARCHAR(255) DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

-- ---------------------------------------------------------
-- Demo data. Every demo account password is: Drive24@2026
-- (bcrypt hash below is pre-generated, so logins work right after import)
-- ---------------------------------------------------------
SET @pw := '$2b$10$lwPYxJAMLgTozbbew3lk1ulYTvF8HhDJenM5o4wc0SGyLhvkxpw4.';

INSERT INTO users (name,email,mobile,password_hash,role,city,company,kyc_status) VALUES
('DRIVE24 Admin','admin@drive24.in','9820000001',@pw,'admin','Mumbai',NULL,'verified'),
('Meet Panchal','meet@example.com','9820000002',@pw,'buyer','Ahmedabad',NULL,'verified'),
('Riya Sharma','riya@example.com','9820000003',@pw,'buyer','Pune',NULL,'pending'),
('AutoHub Motors','seller@drive24.in','9820000004',@pw,'dealer','Mumbai','AutoHub Motors LLP','verified'),
('Karan Mehta','karan@example.com','9820000005',@pw,'seller','Delhi',NULL,'pending'),
('DRIVE24 Support','support@drive24.in','9820000006',@pw,'support','Bengaluru',NULL,'verified');

INSERT INTO vehicles (make,model,variant,year,body_type,fuel_type,transmission,km_driven,owners,color,reg_number,reg_state,vin,engine_cc,power_bhp,mileage_kmpl,seats,insurance_valid_till,city,area,image,description) VALUES
('Hyundai','Creta','SX (O) Turbo',2022,'SUV','Petrol','Automatic',24500,1,'Titan Grey','MH01AB1234','Maharashtra','MALC281CLNM100231',1482,'138 bhp',16.8,5,'2027-03-31','Mumbai','Andheri West','car1.jpg','Top-end Creta with panoramic sunroof, ventilated seats and ADAS-ready camera pack.'),
('Maruti Suzuki','Swift','ZXi Plus',2021,'Hatchback','Petrol','Manual',31200,1,'Pearl Red','GJ01CD4567','Gujarat','MA3EYD81S00512233',1197,'89 bhp',22.3,5,'2026-11-20','Ahmedabad','Prahladnagar','car2.jpg','Single-owner Swift, full service history from an authorised workshop.'),
('Honda','City','VX CVT',2020,'Sedan','Petrol','Automatic',42800,2,'Platinum White','DL3CE7788','Delhi','MAKGM2569L1023344',1498,'119 bhp',18.4,5,'2026-08-15','Delhi','Dwarka','car3.jpg','Comfortable CVT sedan with sunroof, cruise control and Honda connect.'),
('Tata','Nexon','Fearless Plus DT',2023,'SUV','Petrol','Automatic',12800,1,'Flame Red','KA05MN2211','Karnataka','MAT625487PPL33445',1199,'118 bhp',17.4,5,'2028-01-10','Bengaluru','Whitefield','car4.jpg','5-star GNCAP rated Nexon facelift with 360 camera and JBL audio.'),
('Mahindra','XUV700','AX7 Luxury 7S',2021,'SUV','Diesel','Automatic',48900,1,'Midnight Black','MH12XY9090','Maharashtra','MA1TA2YS1M2A55667',2198,'182 bhp',15.2,7,'2026-12-05','Pune','Baner','car5.jpg','Loaded AX7 with ADAS, Sony 3D audio and panoramic skyroof.'),
('Toyota','Innova Crysta','2.4 ZX AT',2019,'MUV','Diesel','Automatic',72400,2,'Silver','TN10PQ4545','Tamil Nadu','MBJ11JV5007712233',2393,'148 bhp',13.7,7,'2026-06-18','Chennai','Velachery','car6.jpg','Fleet-free family Crysta, timing belt and suspension refreshed at 70k.'),
('Kia','Seltos','HTX IVT',2022,'SUV','Petrol','Automatic',27600,1,'Intense Red','TS09KL7676','Telangana','MZBBB81CLNM901122',1497,'113 bhp',16.5,5,'2027-05-22','Hyderabad','Gachibowli','car7.jpg','HTX trim with 10.25 inch display, ventilated seats and Bose speakers.'),
('Maruti Suzuki','Baleno','Alpha AMT',2021,'Hatchback','Petrol','Automatic',33500,1,'Nexa Blue','RJ14GH3131','Rajasthan','MBHEB81S00K122334',1197,'88 bhp',22.9,5,'2026-09-09','Jaipur','Malviya Nagar','car8.jpg','Premium hatch with heads-up display and 360 view camera.'),
('Volkswagen','Virtus','GT Plus 1.5 TSI',2023,'Sedan','Petrol','Automatic',15600,1,'Carbon Steel','MH02VW5151','Maharashtra','MEXA16603P1044556',1498,'147 bhp',18.7,5,'2028-02-14','Mumbai','Powai','car9.jpg','GT Plus with DSG gearbox, ventilated seats and sunroof.'),
('Renault','Kiger','RXZ Turbo CVT',2022,'SUV','Petrol','Automatic',21900,1,'Caspian Blue','UP16RS8080','Uttar Pradesh','MEEHSRAD3NB055667',999,'99 bhp',19.2,5,'2027-04-11','Noida','Sector 62','car10.jpg','Turbo CVT compact SUV, wireless charger and PM2.5 filter.'),
('MG','Hector','Sharp DCT',2020,'SUV','Petrol','Automatic',55400,2,'Starry Sky Blue','MH14MG6262','Maharashtra','MEEHEC5019P066778',1451,'141 bhp',13.9,5,'2026-10-30','Pune','Wakad','car11.jpg','Internet SUV with 14 inch portrait display and panoramic sunroof.'),
('Skoda','Slavia','Style 1.0 TSI AT',2022,'Sedan','Petrol','Automatic',26800,1,'Candy White','GJ05SK1212','Gujarat','MEXA25603N1077889',999,'113 bhp',19.4,5,'2027-07-19','Surat','Adajan','car12.jpg','Style AT with ventilated seats, subwoofer and wireless Android Auto.');

INSERT INTO listings (vehicle_id,seller_id,price,original_price,status,featured,certified,inspection_score,views,auction_enabled,auction_ends_at,starting_bid) VALUES
(1,4,1245000,1320000,'approved',1,1,94,412,0,NULL,NULL),
(2,4,685000,720000,'approved',1,1,91,318,0,NULL,NULL),
(3,5,975000,1030000,'approved',0,1,88,265,0,NULL,NULL),
(4,4,1020000,1080000,'approved',1,1,95,377,0,NULL,NULL),
(5,4,1890000,1990000,'approved',1,1,92,441,0,NULL,NULL),
(6,5,1680000,1750000,'approved',0,1,86,203,0,NULL,NULL),
(7,4,1395000,1450000,'approved',1,1,93,289,0,NULL,NULL),
(8,5,742000,780000,'approved',0,1,90,174,0,NULL,NULL),
(9,4,1520000,1600000,'approved',1,1,96,356,0,NULL,NULL),
(10,5,798000,840000,'approved',0,1,89,162,1,'2026-09-20 18:00:00',750000),
(11,5,1050000,1120000,'pending',0,0,0,0,0,NULL,NULL),
(12,4,1180000,1250000,'pending',0,0,0,0,0,NULL,NULL);

UPDATE listings SET rental_enabled = 1, price_per_day = GREATEST(999, ROUND(price/450, -2)), km_limit_day = 250, extra_km_rate = 12.00, security_deposit = 15000.00 WHERE status = 'approved' AND rental_enabled = 0 AND price_per_day IS NULL;

INSERT INTO inspections (vehicle_id,inspector,score,engine_score,exterior_score,interior_score,electrical_score,tyres_score,accident_history,remarks,status,inspected_on) VALUES
(1,'Ravi Kulkarni',94,96,92,95,93,90,'No major accident','Excellent condition, minor bumper scuff repainted.','completed','2026-08-12'),
(2,'Ankit Shah',91,93,88,92,91,89,'No major accident','Clutch and tyres replaced at 30k service.','completed','2026-08-14'),
(3,'Deepak Rana',88,89,86,88,90,84,'Minor rear panel repair','Rear bumper repainted, mechanically sound.','completed','2026-08-16'),
(4,'Sneha Iyer',95,97,94,96,95,92,'No major accident','Nearly new, factory warranty valid till 2026.','completed','2026-08-18'),
(5,'Ravi Kulkarni',92,94,90,92,93,88,'No major accident','Diesel injectors cleaned, suspension healthy.','completed','2026-08-20'),
(6,'Mohan Das',86,88,82,86,88,84,'No major accident','High running but full Toyota service history.','completed','2026-08-22'),
(7,'Sneha Iyer',93,95,92,94,93,90,'No major accident','Under manufacturer warranty till 2027.','completed','2026-08-24'),
(8,'Ankit Shah',90,91,89,90,92,86,'No major accident','Brake pads replaced during inspection.','completed','2026-08-26'),
(9,'Deepak Rana',96,97,96,96,95,94,'No major accident','Showroom condition, 1 owner, all records.','completed','2026-08-28'),
(10,'Mohan Das',89,90,88,89,91,86,'No major accident','Minor alloy kerb marks, otherwise clean.','completed','2026-08-30');

INSERT INTO wishlists (user_id,listing_id) VALUES (2,1),(2,4),(2,9),(3,5),(3,7);

INSERT INTO saved_searches (user_id,title,query_string,alert_enabled) VALUES
(2,'Automatic SUVs under 15 lakh in Mumbai','body=SUV&transmission=Automatic&max=1500000&city=Mumbai',1),
(3,'Petrol sedans below 12 lakh','body=Sedan&fuel=Petrol&max=1200000',1);

INSERT INTO offers (listing_id,buyer_id,amount,counter_amount,message,status) VALUES
(1,2,1190000,1225000,'Ready to book today if price is adjusted.','countered'),
(4,2,980000,NULL,'Can you include the extended warranty?','new'),
(5,3,1825000,NULL,'Paying full amount upfront, no finance.','new');

INSERT INTO test_drives (listing_id,user_id,mode,slot_date,slot_time,address,executive,status) VALUES
(1,2,'home','2026-09-14','11:00 AM','Prahladnagar, Ahmedabad','Ramesh Patel','confirmed'),
(7,3,'hub','2026-09-16','04:30 PM','DRIVE24 Hub, Baner, Pune',NULL,'requested');

INSERT INTO orders (order_no,listing_id,buyer_id,amount,booking_amount,finance_opted,loan_amount,tenure_months,status,delivery_city,delivery_address,delivery_date) VALUES
('D24-2026-0001',2,2,685000,25000,0,0,0,'delivered','Ahmedabad','12 Shivalik Residency, Ahmedabad','2026-07-28'),
('D24-2026-0002',3,3,975000,25000,1,780000,60,'processing','Pune','B-402 Rohan Nilay, Pune','2026-09-18');

INSERT INTO payments (order_id,txn_ref,method,amount,status) VALUES
(1,'TXN26072812001','upi',25000,'paid'),
(1,'TXN26072812002','netbanking',660000,'paid'),
(2,'TXN26090911003','card',25000,'paid');

INSERT INTO payouts (seller_id,order_id,amount,utr,status) VALUES
(4,1,657600,'UTR2607281099','paid'),
(5,2,936000,NULL,'processing');

INSERT INTO documents (user_id,order_id,listing_id,doc_type,doc_name,status) VALUES
(2,1,2,'rc','RC transfer application - Form 29/30','verified'),
(2,1,2,'invoice','DRIVE24 tax invoice D24-2026-0001','verified'),
(3,2,3,'kyc','Aadhaar + PAN verification','pending'),
(3,2,3,'loan','Loan sanction letter - HDFC Bank','pending');

INSERT INTO leads (name,mobile,city,make,model,year,km_driven,fuel_type,quote_low,quote_high,status) VALUES
('Nikhil Joshi','9876543210','Pune','Hyundai','i20',2019,48000,'Petrol',520000,585000,'new'),
('Farhan Qureshi','9812345678','Mumbai','Honda','Amaze',2018,61000,'Diesel',430000,485000,'contacted');

INSERT INTO support_tickets (user_id,subject,category,message,status) VALUES
(3,'RC transfer status for order D24-2026-0002','rc','Could you share the expected RC transfer completion date?','open');
------------
-- SRS extension tables (also in database/migrate.sql for
-- existing installs). Fresh installs get them + seed rows.
-- ---------------------------------------------------------

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
  loan_status VARCHAR(40) NOT NULL DEFAULT 'No active loan',
  odometer_verified TINYINT(1) NOT NULL DEFAULT 0,
  rc_verified TINYINT(1) NOT NULL DEFAULT 0,
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

-- Seed rows for the extension tables
INSERT INTO listing_images (listing_id, image, label, sort_order) VALUES
(1,'car4.jpg','Front three-quarter',1),(1,'car7.jpg','Rear three-quarter',2),(1,'car9.jpg','Interior',3),
(2,'car8.jpg','Front three-quarter',1),(2,'car2.jpg','Interior',2),
(4,'car1.jpg','Front three-quarter',1),(4,'car4.jpg','Interior',2),
(5,'car11.jpg','Front three-quarter',1),(5,'car5.jpg','Interior',2),
(9,'car3.jpg','Front three-quarter',1),(9,'car9.jpg','Interior',2);

INSERT INTO vehicle_history (vehicle_id,accidents,accident_details,insurance_claims,challans,challan_amount,service_records,flood_damage,theft_record,loan_status,odometer_verified,rc_verified,owners_history,report_summary,checked_on) VALUES
(1,0,'No accident record found',0,1,500,6,0,0,'No active loan',1,1,'1 owner since new','Clean history. One traffic challan settled. Full service history available.','2026-08-12'),
(2,0,'No accident record found',0,0,0,5,0,0,'No active loan',1,1,'1 owner since new','Clean history with authorised-service records.','2026-08-14'),
(3,1,'Rear bumper repaint in 2022, insurer approved',1,2,2000,7,0,0,'Loan closed in 2024',1,1,'2 owners','One minor insured repair. No structural damage reported.','2026-08-16'),
(4,0,'No accident record found',0,0,0,3,0,0,'No active loan',1,1,'1 owner since new','Clean history. Factory warranty valid.','2026-08-18'),
(5,0,'No accident record found',0,1,1000,6,0,0,'No active loan',1,1,'1 owner since new','Clean history. One challan settled.','2026-08-20'),
(6,0,'No accident record found',1,0,0,9,0,0,'No active loan',1,1,'2 owners','High running but full Toyota service history.','2026-08-22'),
(7,0,'No accident record found',0,0,0,4,0,0,'No active loan',1,1,'1 owner since new','Clean history. Under manufacturer warranty.','2026-08-24'),
(8,0,'No accident record found',0,0,0,4,0,0,'No active loan',1,1,'1 owner since new','Clean history.','2026-08-26'),
(9,0,'No accident record found',0,0,0,3,0,0,'No active loan',1,1,'1 owner since new','Showroom condition, all records available.','2026-08-28'),
(10,0,'No accident record found',0,1,500,3,0,0,'No active loan',1,1,'1 owner since new','Clean history. One challan settled.','2026-08-30');

INSERT INTO reviews (listing_id,order_id,author_id,target_user_id,reviewer_role,rating,title,comment,status) VALUES
(2,1,2,4,'buyer',5,'Exactly as inspected','Car matched the 280-point report perfectly. RC transfer took 24 days.','approved'),
(2,1,4,2,'seller',5,'Smooth buyer','Payment was instant and pickup was on schedule.','approved');

INSERT INTO chat_threads (listing_id,buyer_id,seller_id,subject,status) VALUES
(1,2,4,'2019 Hyundai Creta SX (O) Turbo enquiry','open');
INSERT INTO chat_messages (thread_id,sender_id,body) VALUES
(1,2,'Hi, is the Creta still available? Has the price room for negotiation?'),
(1,4,'Yes, it is available. The car is certified - happy to discuss a fair offer on the platform.');

INSERT INTO loan_applications (user_id,listing_id,lender,amount,tenure_months,rate,employment,monthly_income,status) VALUES
(3,3,'HDFC Bank',780000,60,8.95,'Salaried',95000,'approved');

INSERT INTO insurance_quotes (user_id,listing_id,insurer,idv,premium,addons,status) VALUES
(2,1,'ICICI Lombard',1180000,28450,'Zero-dep, RSA','quoted');

INSERT INTO escrow_ledger (order_id,kind,amount,note) VALUES
(1,'hold',25000,'Booking amount held in escrow'),
(1,'release',660000,'Balance released to seller after delivery'),
(2,'hold',25000,'Booking amount held in escrow');

INSERT INTO notifications (user_id,title,body,link) VALUES
(2,'Test drive confirmed','Your Creta home test drive is confirmed for 14 Sep.','account.php'),
(4,'New offer received','Meet offered Rs 11,90,000 on the Creta.','seller/offers.php');

INSERT INTO vehicle_features (vehicle_id,category,feature) VALUES
(1,'comfort','Air Conditioner'),
(1,'comfort','Power Steering'),
(1,'comfort','Power Windows'),
(1,'comfort','Automatic Climate Control'),
(1,'comfort','Rear AC Vents'),
(1,'comfort','Cruise Control'),
(1,'comfort','Keyless Entry'),
(1,'safety','ABS with EBD'),
(1,'safety','Dual Front Airbags'),
(1,'safety','Rear Parking Sensors'),
(1,'safety','Rear Parking Camera'),
(1,'safety','Hill Hold Assist'),
(1,'safety','Traction Control'),
(1,'safety','ISOFIX Child Mounts'),
(1,'entertainment','Touchscreen Infotainment'),
(1,'entertainment','Bluetooth Audio'),
(1,'entertainment','Steering Audio Controls'),
(1,'entertainment','Android Auto'),
(1,'entertainment','Apple CarPlay'),
(1,'exterior','Alloy Wheels'),
(1,'exterior','Electrically Adjustable ORVMs'),
(1,'exterior','Rear Spoiler'),
(1,'exterior','LED Headlamps'),
(2,'comfort','Air Conditioner'),
(2,'comfort','Power Steering'),
(2,'comfort','Power Windows'),
(2,'comfort','Automatic Climate Control'),
(2,'comfort','Rear AC Vents'),
(2,'comfort','Cruise Control'),
(2,'comfort','Keyless Entry'),
(2,'comfort','Push Button Start'),
(2,'safety','ABS with EBD'),
(2,'safety','Dual Front Airbags'),
(2,'safety','Rear Parking Sensors'),
(2,'safety','Rear Parking Camera'),
(2,'safety','Hill Hold Assist'),
(2,'entertainment','Touchscreen Infotainment'),
(2,'entertainment','Bluetooth Audio'),
(2,'entertainment','Steering Audio Controls'),
(2,'entertainment','Android Auto'),
(2,'entertainment','Apple CarPlay'),
(2,'entertainment','USB Fast Charging'),
(2,'exterior','Alloy Wheels'),
(2,'exterior','Electrically Adjustable ORVMs'),
(2,'exterior','Rear Spoiler'),
(2,'exterior','LED Headlamps'),
(2,'exterior','Fog Lamps'),
(3,'comfort','Air Conditioner'),
(3,'comfort','Power Steering'),
(3,'comfort','Power Windows'),
(3,'comfort','Automatic Climate Control'),
(3,'comfort','Rear AC Vents'),
(3,'comfort','Cruise Control'),
(3,'comfort','Keyless Entry'),
(3,'comfort','Push Button Start'),
(3,'comfort','Ventilated Seats'),
(3,'safety','ABS with EBD'),
(3,'safety','Dual Front Airbags'),
(3,'safety','Rear Parking Sensors'),
(3,'safety','Rear Parking Camera'),
(3,'safety','Hill Hold Assist'),
(3,'safety','Traction Control'),
(3,'entertainment','Touchscreen Infotainment'),
(3,'entertainment','Bluetooth Audio'),
(3,'entertainment','Steering Audio Controls'),
(3,'entertainment','Android Auto'),
(3,'exterior','Alloy Wheels'),
(3,'exterior','Electrically Adjustable ORVMs'),
(3,'exterior','Rear Spoiler'),
(3,'exterior','LED Headlamps'),
(3,'exterior','Fog Lamps'),
(3,'exterior','Roof Rails'),
(4,'comfort','Air Conditioner'),
(4,'comfort','Power Steering'),
(4,'comfort','Power Windows'),
(4,'comfort','Automatic Climate Control'),
(4,'comfort','Rear AC Vents'),
(4,'comfort','Cruise Control'),
(4,'safety','ABS with EBD'),
(4,'safety','Dual Front Airbags'),
(4,'safety','Rear Parking Sensors'),
(4,'safety','Rear Parking Camera'),
(4,'safety','Hill Hold Assist'),
(4,'safety','Traction Control'),
(4,'safety','ISOFIX Child Mounts'),
(4,'entertainment','Touchscreen Infotainment'),
(4,'entertainment','Bluetooth Audio'),
(4,'entertainment','Steering Audio Controls'),
(4,'entertainment','Android Auto'),
(4,'entertainment','Apple CarPlay'),
(4,'exterior','Alloy Wheels'),
(4,'exterior','Electrically Adjustable ORVMs'),
(4,'exterior','Rear Spoiler'),
(4,'exterior','LED Headlamps'),
(5,'comfort','Air Conditioner'),
(5,'comfort','Power Steering'),
(5,'comfort','Power Windows'),
(5,'comfort','Automatic Climate Control'),
(5,'comfort','Rear AC Vents'),
(5,'comfort','Cruise Control'),
(5,'comfort','Keyless Entry'),
(5,'safety','ABS with EBD'),
(5,'safety','Dual Front Airbags'),
(5,'safety','Rear Parking Sensors'),
(5,'safety','Rear Parking Camera'),
(5,'safety','Hill Hold Assist'),
(5,'entertainment','Touchscreen Infotainment'),
(5,'entertainment','Bluetooth Audio'),
(5,'entertainment','Steering Audio Controls'),
(5,'entertainment','Android Auto'),
(5,'entertainment','Apple CarPlay'),
(5,'entertainment','USB Fast Charging'),
(5,'exterior','Alloy Wheels'),
(5,'exterior','Electrically Adjustable ORVMs'),
(5,'exterior','Rear Spoiler'),
(5,'exterior','LED Headlamps'),
(5,'exterior','Fog Lamps'),
(6,'comfort','Air Conditioner'),
(6,'comfort','Power Steering'),
(6,'comfort','Power Windows'),
(6,'comfort','Automatic Climate Control'),
(6,'comfort','Rear AC Vents'),
(6,'comfort','Cruise Control'),
(6,'comfort','Keyless Entry'),
(6,'comfort','Push Button Start'),
(6,'safety','ABS with EBD'),
(6,'safety','Dual Front Airbags'),
(6,'safety','Rear Parking Sensors'),
(6,'safety','Rear Parking Camera'),
(6,'safety','Hill Hold Assist'),
(6,'safety','Traction Control'),
(6,'entertainment','Touchscreen Infotainment'),
(6,'entertainment','Bluetooth Audio'),
(6,'entertainment','Steering Audio Controls'),
(6,'entertainment','Android Auto'),
(6,'exterior','Alloy Wheels'),
(6,'exterior','Electrically Adjustable ORVMs'),
(6,'exterior','Rear Spoiler'),
(6,'exterior','LED Headlamps'),
(6,'exterior','Fog Lamps'),
(6,'exterior','Roof Rails'),
(7,'comfort','Air Conditioner'),
(7,'comfort','Power Steering'),
(7,'comfort','Power Windows'),
(7,'comfort','Automatic Climate Control'),
(7,'comfort','Rear AC Vents'),
(7,'comfort','Cruise Control'),
(7,'comfort','Keyless Entry'),
(7,'comfort','Push Button Start'),
(7,'comfort','Ventilated Seats'),
(7,'safety','ABS with EBD'),
(7,'safety','Dual Front Airbags'),
(7,'safety','Rear Parking Sensors'),
(7,'safety','Rear Parking Camera'),
(7,'safety','Hill Hold Assist'),
(7,'safety','Traction Control'),
(7,'safety','ISOFIX Child Mounts'),
(7,'entertainment','Touchscreen Infotainment'),
(7,'entertainment','Bluetooth Audio'),
(7,'entertainment','Steering Audio Controls'),
(7,'entertainment','Android Auto'),
(7,'entertainment','Apple CarPlay'),
(7,'exterior','Alloy Wheels'),
(7,'exterior','Electrically Adjustable ORVMs'),
(7,'exterior','Rear Spoiler'),
(7,'exterior','LED Headlamps'),
(8,'comfort','Air Conditioner'),
(8,'comfort','Power Steering'),
(8,'comfort','Power Windows'),
(8,'comfort','Automatic Climate Control'),
(8,'comfort','Rear AC Vents'),
(8,'comfort','Cruise Control'),
(8,'safety','ABS with EBD'),
(8,'safety','Dual Front Airbags'),
(8,'safety','Rear Parking Sensors'),
(8,'safety','Rear Parking Camera'),
(8,'safety','Hill Hold Assist'),
(8,'entertainment','Touchscreen Infotainment'),
(8,'entertainment','Bluetooth Audio'),
(8,'entertainment','Steering Audio Controls'),
(8,'entertainment','Android Auto'),
(8,'entertainment','Apple CarPlay'),
(8,'entertainment','USB Fast Charging'),
(8,'exterior','Alloy Wheels'),
(8,'exterior','Electrically Adjustable ORVMs'),
(8,'exterior','Rear Spoiler'),
(8,'exterior','LED Headlamps'),
(8,'exterior','Fog Lamps'),
(9,'comfort','Air Conditioner'),
(9,'comfort','Power Steering'),
(9,'comfort','Power Windows'),
(9,'comfort','Automatic Climate Control'),
(9,'comfort','Rear AC Vents'),
(9,'comfort','Cruise Control'),
(9,'comfort','Keyless Entry'),
(9,'safety','ABS with EBD'),
(9,'safety','Dual Front Airbags'),
(9,'safety','Rear Parking Sensors'),
(9,'safety','Rear Parking Camera'),
(9,'safety','Hill Hold Assist'),
(9,'safety','Traction Control'),
(9,'entertainment','Touchscreen Infotainment'),
(9,'entertainment','Bluetooth Audio'),
(9,'entertainment','Steering Audio Controls'),
(9,'entertainment','Android Auto'),
(9,'exterior','Alloy Wheels'),
(9,'exterior','Electrically Adjustable ORVMs'),
(9,'exterior','Rear Spoiler'),
(9,'exterior','LED Headlamps'),
(9,'exterior','Fog Lamps'),
(9,'exterior','Roof Rails'),
(10,'comfort','Air Conditioner'),
(10,'comfort','Power Steering'),
(10,'comfort','Power Windows'),
(10,'comfort','Automatic Climate Control'),
(10,'comfort','Rear AC Vents'),
(10,'comfort','Cruise Control'),
(10,'comfort','Keyless Entry'),
(10,'comfort','Push Button Start'),
(10,'safety','ABS with EBD'),
(10,'safety','Dual Front Airbags'),
(10,'safety','Rear Parking Sensors'),
(10,'safety','Rear Parking Camera'),
(10,'safety','Hill Hold Assist'),
(10,'safety','Traction Control'),
(10,'safety','ISOFIX Child Mounts'),
(10,'entertainment','Touchscreen Infotainment'),
(10,'entertainment','Bluetooth Audio'),
(10,'entertainment','Steering Audio Controls'),
(10,'entertainment','Android Auto'),
(10,'entertainment','Apple CarPlay'),
(10,'exterior','Alloy Wheels'),
(10,'exterior','Electrically Adjustable ORVMs'),
(10,'exterior','Rear Spoiler'),
(10,'exterior','LED Headlamps'),
(11,'comfort','Air Conditioner'),
(11,'comfort','Power Steering'),
(11,'comfort','Power Windows'),
(11,'comfort','Automatic Climate Control'),
(11,'comfort','Rear AC Vents'),
(11,'comfort','Cruise Control'),
(11,'comfort','Keyless Entry'),
(11,'comfort','Push Button Start'),
(11,'comfort','Ventilated Seats'),
(11,'safety','ABS with EBD'),
(11,'safety','Dual Front Airbags'),
(11,'safety','Rear Parking Sensors'),
(11,'safety','Rear Parking Camera'),
(11,'safety','Hill Hold Assist'),
(11,'entertainment','Touchscreen Infotainment'),
(11,'entertainment','Bluetooth Audio'),
(11,'entertainment','Steering Audio Controls'),
(11,'entertainment','Android Auto'),
(11,'entertainment','Apple CarPlay'),
(11,'entertainment','USB Fast Charging'),
(11,'exterior','Alloy Wheels'),
(11,'exterior','Electrically Adjustable ORVMs'),
(11,'exterior','Rear Spoiler'),
(11,'exterior','LED Headlamps'),
(11,'exterior','Fog Lamps'),
(12,'comfort','Air Conditioner'),
(12,'comfort','Power Steering'),
(12,'comfort','Power Windows'),
(12,'comfort','Automatic Climate Control'),
(12,'comfort','Rear AC Vents'),
(12,'comfort','Cruise Control'),
(12,'safety','ABS with EBD'),
(12,'safety','Dual Front Airbags'),
(12,'safety','Rear Parking Sensors'),
(12,'safety','Rear Parking Camera'),
(12,'safety','Hill Hold Assist'),
(12,'safety','Traction Control'),
(12,'entertainment','Touchscreen Infotainment'),
(12,'entertainment','Bluetooth Audio'),
(12,'entertainment','Steering Audio Controls'),
(12,'entertainment','Android Auto'),
(12,'exterior','Alloy Wheels'),
(12,'exterior','Electrically Adjustable ORVMs'),
(12,'exterior','Rear Spoiler'),
(12,'exterior','LED Headlamps'),
(12,'exterior','Fog Lamps'),
(12,'exterior','Roof Rails');

INSERT INTO rc_transfers (order_id,listing_id,buyer_id,seller_id,status,rto_office,application_no,remark) VALUES
(1,2,2,4,'transfer_completed','RTO Ahmedabad - GJ01','GJ01/TR/2026/88412','Smart-card RC dispatched to buyer'),
(2,3,3,5,'application_filed','RTO Pune - MH12','MH12/TR/2026/90177','Form 29/30 submitted to RTO');

INSERT INTO bids (listing_id,buyer_id,amount) VALUES
(10,2,752000),
(10,3,765000),
(10,2,778000);

INSERT INTO questions (listing_id,user_id,question,answer,answered_by,answered_at) VALUES
(1,2,'Is the second key available with the car?','Yes, both keys plus the service booklet are available. You can verify them at the test drive.',4,'2026-09-02 10:30:00'),
(1,3,'Are the tyres original or have they been replaced?','All four tyres were replaced at 22,000 km. The spare wheel is unused.',4,'2026-09-04 15:10:00'),
(4,3,'Is the factory warranty transferable to the new owner?','Yes, the warranty is fully transferable. We hand over the transfer form at delivery.',4,'2026-09-06 11:00:00'),
(9,2,'Has this car ever been used as a taxi or fleet vehicle?',NULL,NULL,NULL);
