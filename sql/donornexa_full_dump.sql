-- =====================================================================
-- DonorNexa - schema.sql  (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4)
-- Import with:  mysql -u root < sql/schema.sql   (then seed.sql)
-- =====================================================================
DROP DATABASE IF EXISTS donornexa;
CREATE DATABASE donornexa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE donornexa;

-- ---------- Reference tables ----------
CREATE TABLE cities (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(80) NOT NULL,
    province  VARCHAR(40) NOT NULL,
    UNIQUE KEY uq_city (city_name)
) ENGINE=InnoDB;

CREATE TABLE settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(100) NOT NULL,
    description   VARCHAR(200) NOT NULL
) ENGINE=InnoDB;

-- Data-driven ABO/Rh compatibility map: Blood Group relates to itself with "can donate to"
-- Stored as relational data (donor_group -> recipient_group) without hard-coded if/else chains
CREATE TABLE blood_compatibility (
    donor_group     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    recipient_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    PRIMARY KEY (donor_group, recipient_group)
) ENGINE=InnoDB;

-- ---------- Accounts ----------
-- One account (User) plays exactly one role: Donor, Hospital or Admin
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','hospital','donor') NOT NULL,
    status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE donors (
    user_id            INT UNSIGNED PRIMARY KEY,
    blood_group        ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    dob                DATE NOT NULL,
    weight             DECIMAL(5,2) NOT NULL,
    city_id            INT UNSIGNED NOT NULL,
    phone              VARCHAR(12) NOT NULL,
    cnic               VARCHAR(15) NULL,
    last_donation_date DATE NULL,
    is_available       TINYINT(1) NOT NULL DEFAULT 1,
    photo              VARCHAR(255) NULL,
    UNIQUE KEY uq_donors_cnic (cnic),
    -- Matching engine filters on group + city, so this composite index serves its main query
    KEY idx_donors_group_city (blood_group, city_id),
    KEY idx_donors_available (is_available, last_donation_date),
    CONSTRAINT fk_donors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_donors_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT,
    CONSTRAINT ck_donors_weight CHECK (weight BETWEEN 30 AND 250),
    CONSTRAINT ck_donors_phone  CHECK (phone REGEXP '^03[0-9]{2}-[0-9]{7}$')
) ENGINE=InnoDB;

CREATE TABLE hospitals (
    user_id             INT UNSIGNED PRIMARY KEY,
    hospital_name       VARCHAR(150) NOT NULL,
    licence_no          VARCHAR(50) NOT NULL,
    licence_file        VARCHAR(255) NOT NULL,
    city_id             INT UNSIGNED NOT NULL,
    phone               VARCHAR(12) NOT NULL,
    verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    trust_score         TINYINT UNSIGNED NOT NULL DEFAULT 100,
    UNIQUE KEY uq_hospitals_licence (licence_no),
    KEY idx_hospitals_verification (verification_status),
    CONSTRAINT fk_hospitals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_hospitals_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT,
    CONSTRAINT ck_hospitals_trust CHECK (trust_score BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE remember_tokens (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    KEY idx_remember_lookup (user_id, token_hash),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Requests, matching, donations ----------
-- units_fulfilled = units already promised by accepting donors (the allocation counter)
CREATE TABLE blood_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id     INT UNSIGNED NOT NULL,
    blood_group     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_needed    TINYINT UNSIGNED NOT NULL,
    units_fulfilled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    urgency         ENUM('Normal','Urgent','Critical') NOT NULL DEFAULT 'Normal',
    required_by     DATETIME NOT NULL,
    ward_notes      VARCHAR(255) NULL,
    status          ENUM('open','fulfilled','closed','cancelled','expired') NOT NULL DEFAULT 'open',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Public board and expiry job both filter on status + deadline
    KEY idx_requests_status_deadline (status, required_by),
    KEY idx_requests_hospital (hospital_id, status),
    KEY idx_requests_group (blood_group, status),
    CONSTRAINT fk_requests_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(user_id) ON DELETE CASCADE,
    CONSTRAINT ck_requests_units CHECK (units_needed BETWEEN 1 AND 10),
    CONSTRAINT ck_requests_fulfilled CHECK (units_fulfilled <= units_needed)
) ENGINE=InnoDB;

-- Donor and Blood Request are many-to-many:
-- A donor can be matched to many requests, and a request is offered to many donors
CREATE TABLE request_matches (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id     INT UNSIGNED NOT NULL,
    donor_id       INT UNSIGNED NOT NULL,
    score          DECIMAL(6,2) NOT NULL,
    status         ENUM('notified','accepted','declined','no_response','donated') NOT NULL DEFAULT 'notified',
    decline_reason VARCHAR(255) NULL,
    notified_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at   DATETIME NULL,
    UNIQUE KEY uq_match_request_donor (request_id, donor_id),
    KEY idx_matches_donor (donor_id, status),
    KEY idx_matches_status (status, notified_at),
    CONSTRAINT fk_matches_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_donor   FOREIGN KEY (donor_id)   REFERENCES donors(user_id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- A Donation ties a donor to the request it fulfils (via match_id) and raises the hospital's Blood Inventory
CREATE TABLE donations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id    INT UNSIGNED NOT NULL,
    units       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    donated_on  DATE NOT NULL,
    verified_by INT UNSIGNED NULL,
    UNIQUE KEY uq_donation_match (match_id),
    CONSTRAINT fk_donations_match FOREIGN KEY (match_id) REFERENCES request_matches(id) ON DELETE RESTRICT,
    CONSTRAINT fk_donations_user  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT ck_donations_units CHECK (units BETWEEN 1 AND 2)
) ENGINE=InnoDB;

CREATE TABLE blood_inventory (
    hospital_id     INT UNSIGNED NOT NULL,
    blood_group     ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    units_available INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (hospital_id, blood_group),
    CONSTRAINT fk_inventory_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Notifications and audit ----------
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    VARCHAR(255) NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(50) NOT NULL,
    entity     VARCHAR(60) NOT NULL,        -- "table:id", e.g. blood_requests:12
    old_value  TEXT NULL,
    new_value  TEXT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT 'system',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity, action),
    KEY idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Reference data ----------
INSERT INTO settings (setting_key, setting_value, description) VALUES
 ('cooldown_days',   '90', 'Days a donor must wait between donations'),
 ('top_n',           '5',  'Donors notified in the first batch (multiplied by urgency)'),
 ('response_hours',  '12', 'Hours before an unanswered notification becomes No response');

INSERT INTO cities (city_name, province) VALUES
 ('Karachi','Sindh'),('Hyderabad','Sindh'),('Jamshoro','Sindh'),('Sukkur','Sindh'),('Larkana','Sindh'),
 ('Lahore','Punjab'),('Islamabad','Islamabad Capital Territory'),('Peshawar','Khyber Pakhtunkhwa'),
 ('Quetta','Balochistan'),('Multan','Punjab');

INSERT INTO blood_compatibility (donor_group, recipient_group) VALUES
 ('O-','O-'),('O-','O+'),('O-','A-'),('O-','A+'),('O-','B-'),('O-','B+'),('O-','AB-'),('O-','AB+'),
 ('O+','O+'),('O+','A+'),('O+','B+'),('O+','AB+'),
 ('A-','A-'),('A-','A+'),('A-','AB-'),('A-','AB+'),
 ('A+','A+'),('A+','AB+'),
 ('B-','B-'),('B-','B+'),('B-','AB-'),('B-','AB+'),
 ('B+','B+'),('B+','AB+'),
 ('AB-','AB-'),('AB-','AB+'),
 ('AB+','AB+');

-- ---------- View: donors who may be called right now ----------
CREATE VIEW v_eligible_donors AS
SELECT d.user_id AS donor_id,
       u.name,
       u.email,
       d.blood_group,
       d.city_id,
       c.city_name,
       c.province,
       d.phone,
       d.weight,
       d.last_donation_date,
       TIMESTAMPDIFF(YEAR, d.dob, CURDATE()) AS age,
       COALESCE(DATEDIFF(CURDATE(), d.last_donation_date), 9999) AS days_since_donation
FROM donors d
JOIN users  u ON u.id = d.user_id
JOIN cities c ON c.id = d.city_id
WHERE u.status = 'active'
  AND d.is_available = 1
  AND d.weight >= 50
  AND TIMESTAMPDIFF(YEAR, d.dob, CURDATE()) BETWEEN 18 AND 60
  AND (d.last_donation_date IS NULL
       OR DATEDIFF(CURDATE(), d.last_donation_date) >=
          (SELECT CAST(setting_value AS UNSIGNED) FROM settings WHERE setting_key = 'cooldown_days'));

-- ---------- Triggers ----------
DELIMITER $$

-- After a donation is recorded, move the donor's last_donation_date forward
CREATE TRIGGER trg_donation_after_insert
AFTER INSERT ON donations
FOR EACH ROW
BEGIN
    UPDATE donors
       SET last_donation_date = NEW.donated_on
     WHERE user_id = (SELECT donor_id FROM request_matches WHERE id = NEW.match_id)
       AND (last_donation_date IS NULL OR last_donation_date < NEW.donated_on);
END$$

-- Database-level backstop for the anti-misuse rule (max 3 open Critical requests per hospital)
CREATE TRIGGER trg_requests_critical_limit
BEFORE INSERT ON blood_requests
FOR EACH ROW
BEGIN
    IF NEW.urgency = 'Critical' AND NEW.status = 'open' AND
       (SELECT COUNT(*) FROM blood_requests
         WHERE hospital_id = NEW.hospital_id AND urgency = 'Critical' AND status = 'open') >= 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A hospital may have at most 3 open Critical requests';
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- DonorNexa seed data: 1 admin, 5 hospitals, 30 donors, 20 requests (generated, realistic names)
-- Passwords: admin Admin@123 | hospitals Hospital@123 | donors Donor@123
USE donornexa;
SET FOREIGN_KEY_CHECKS = 0;
INSERT INTO users (id,name,email,password_hash,role,status,created_at) VALUES
(1,'System Administrator','admin@donornexa.pk','$2y$10$7ncAxYspWGQJCbocn1jVveYT.IGwm9M6fForalncjLS9vskspkxfW','admin','active',NOW() - INTERVAL 60 DAY),
(2,'Liaquat University Hospital, Hyderabad','hospital.luh@donornexa.pk','$2y$10$tVkReUM8QTuGRQaoq8tcp.XA3bhgg.878yvjACHPAZEojclc8labW','hospital','active',NOW() - INTERVAL 23 DAY),
(3,'Civil Hospital Karachi','hospital.civil@donornexa.pk','$2y$10$tVkReUM8QTuGRQaoq8tcp.XA3bhgg.878yvjACHPAZEojclc8labW','hospital','active',NOW() - INTERVAL 30 DAY),
(4,'LUMHS Jamshoro','hospital.lumhs@donornexa.pk','$2y$10$tVkReUM8QTuGRQaoq8tcp.XA3bhgg.878yvjACHPAZEojclc8labW','hospital','active',NOW() - INTERVAL 36 DAY),
(5,'Shaukat Khanum Memorial, Lahore','hospital.skm@donornexa.pk','$2y$10$tVkReUM8QTuGRQaoq8tcp.XA3bhgg.878yvjACHPAZEojclc8labW','hospital','active',NOW() - INTERVAL 50 DAY),
(6,'Sukkur Medical Centre','hospital.sukkur@donornexa.pk','$2y$10$tVkReUM8QTuGRQaoq8tcp.XA3bhgg.878yvjACHPAZEojclc8labW','hospital','active',NOW() - INTERVAL 36 DAY),
(7,'Ahmed Bhutto','donor1@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 21 DAY),
(8,'Ali Siddiqui','donor2@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 7 DAY),
(9,'Hassan Shaikh','donor3@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 40 DAY),
(10,'Hussain Ansari','donor4@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 51 DAY),
(11,'Bilal Qureshi','donor5@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 5 DAY),
(12,'Usman Qureshi','donor6@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 22 DAY),
(13,'Imran Ansari','donor7@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 42 DAY),
(14,'Faisal Shaikh','donor8@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 17 DAY),
(15,'Kamran Malik','donor9@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 25 DAY),
(16,'Zeeshan Ansari','donor10@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 32 DAY),
(17,'Ayesha Baloch','donor11@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 46 DAY),
(18,'Fatima Siddiqui','donor12@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 55 DAY),
(19,'Sana Bhutto','donor13@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 24 DAY),
(20,'Hina Shaikh','donor14@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 35 DAY),
(21,'Maryam Qureshi','donor15@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 31 DAY),
(22,'Zainab Siddiqui','donor16@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 53 DAY),
(23,'Nida Baloch','donor17@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 32 DAY),
(24,'Amna Shaikh','donor18@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 25 DAY),
(25,'Rabia Shaikh','donor19@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 6 DAY),
(26,'Sadia Abro','donor20@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 25 DAY),
(27,'Daya Baloch','donor21@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 52 DAY),
(28,'Raj Jatoi','donor22@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 26 DAY),
(29,'Kiran Soomro','donor23@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 27 DAY),
(30,'Asad Baloch','donor24@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 54 DAY),
(31,'Naveed Malik','donor25@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 6 DAY),
(32,'Saad Khan','donor26@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 7 DAY),
(33,'Omar Ansari','donor27@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 48 DAY),
(34,'Hamza Siddiqui','donor28@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 27 DAY),
(35,'Iqra Qureshi','donor29@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 27 DAY),
(36,'Mehwish Rajput','donor30@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 50 DAY),
(37,'Waqas Jatoi','donor31@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 37 DAY),
(38,'Danish Memon','donor32@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 23 DAY),
(39,'Rohan Abro','donor33@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 15 DAY),
(40,'Shazia Jatoi','donor34@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 28 DAY),
(41,'Komal Memon','donor35@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 11 DAY),
(42,'Anum Rajput','donor36@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 40 DAY),
(43,'Tariq Siddiqui','donor37@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 54 DAY),
(44,'Junaid Abro','donor38@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 25 DAY),
(45,'Farah Shaikh','donor39@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','active',NOW() - INTERVAL 32 DAY),
(46,'Sumaira Abro','donor40@example.com','$2y$10$mo9J4PhTtICYQxNFndmduekG4nvB1Z5OwrE/ixP3ZL9NwKhbEOHVW','donor','blocked',NOW() - INTERVAL 40 DAY);
INSERT INTO hospitals (user_id,hospital_name,licence_no,licence_file,city_id,phone,verification_status,trust_score) VALUES
(2,'Liaquat University Hospital, Hyderabad','SHC-HYD-0101','sample-licence.pdf',2,'0300-1112221','verified',100),
(3,'Civil Hospital Karachi','SHC-KHI-0202','sample-licence.pdf',1,'0300-1112222','verified',95),
(4,'LUMHS Jamshoro','SHC-JAM-0303','sample-licence.pdf',3,'0300-1112223','verified',100),
(5,'Shaukat Khanum Memorial, Lahore','PHC-LHR-0404','sample-licence.pdf',6,'0300-1112224','verified',85),
(6,'Sukkur Medical Centre','SHC-SUK-0505','sample-licence.pdf',4,'0300-1112225','pending',100);
INSERT INTO donors (user_id,blood_group,dob,weight,city_id,phone,cnic,last_donation_date,is_available,photo) VALUES
(7,'O+',DATE_SUB(CURDATE(), INTERVAL 9344 DAY),73,1,'0333-3047042','44790-8875326-9',NULL,1,NULL),
(8,'AB+',DATE_SUB(CURDATE(), INTERVAL 16217 DAY),76,2,'0336-8265655','42401-5513674-1',DATE_SUB(CURDATE(), INTERVAL 400 DAY),1,NULL),
(9,'B+',DATE_SUB(CURDATE(), INTERVAL 17787 DAY),73,6,'0314-9146498','44623-9340912-7',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(10,'O-',DATE_SUB(CURDATE(), INTERVAL 7813 DAY),67,6,'0307-9643174','43563-4071049-7',DATE_SUB(CURDATE(), INTERVAL 300 DAY),1,NULL),
(11,'O-',DATE_SUB(CURDATE(), INTERVAL 8531 DAY),73,5,'0303-7900650','42560-1235427-2',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(12,'A+',DATE_SUB(CURDATE(), INTERVAL 19387 DAY),47,3,'0343-5158025','41735-1749254-9',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1,NULL),
(13,'O-',DATE_SUB(CURDATE(), INTERVAL 15044 DAY),52,6,'0349-9254204','42658-4740105-8',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(14,'O+',DATE_SUB(CURDATE(), INTERVAL 18763 DAY),55,5,'0312-6133900','44499-3492025-9',DATE_SUB(CURDATE(), INTERVAL 200 DAY),1,NULL),
(15,'O-',DATE_SUB(CURDATE(), INTERVAL 6989 DAY),70,4,'0305-6929115','41126-9562887-8',NULL,0,NULL),
(16,'A-',DATE_SUB(CURDATE(), INTERVAL 14100 DAY),67,6,'0337-3864434','42705-9717671-4',NULL,1,NULL),
(17,'A+',DATE_SUB(CURDATE(), INTERVAL 14834 DAY),80,4,'0308-7696744','41300-3908563-1',NULL,1,NULL),
(18,'A+',DATE_SUB(CURDATE(), INTERVAL 8261 DAY),85,2,'0317-1343426','42761-9890860-4',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1,NULL),
(19,'AB+',DATE_SUB(CURDATE(), INTERVAL 19050 DAY),58,1,'0334-8136260','44988-2319675-7',NULL,1,NULL),
(20,'O+',DATE_SUB(CURDATE(), INTERVAL 15054 DAY),64,6,'0336-5826306','44281-6465836-9',DATE_SUB(CURDATE(), INTERVAL 300 DAY),1,NULL),
(21,'O-',DATE_SUB(CURDATE(), INTERVAL 9759 DAY),76,1,'0303-4881154','41195-8618762-6',DATE_SUB(CURDATE(), INTERVAL 120 DAY),1,NULL),
(22,'A+',DATE_SUB(CURDATE(), INTERVAL 10963 DAY),67,1,'0328-4632979','41735-5705543-9',NULL,1,NULL),
(23,'B-',DATE_SUB(CURDATE(), INTERVAL 11971 DAY),64,2,'0328-3224963','41900-5424365-2',DATE_SUB(CURDATE(), INTERVAL 45 DAY),1,NULL),
(24,'B-',DATE_SUB(CURDATE(), INTERVAL 9155 DAY),67,1,'0311-9499785','43383-2072316-5',DATE_SUB(CURDATE(), INTERVAL 20 DAY),0,NULL),
(25,'AB+',DATE_SUB(CURDATE(), INTERVAL 12906 DAY),55,4,'0334-7954630','41158-2618161-4',NULL,1,NULL),
(26,'A-',DATE_SUB(CURDATE(), INTERVAL 9538 DAY),85,1,'0345-6284394','44721-1637716-7',DATE_SUB(CURDATE(), INTERVAL 400 DAY),1,NULL),
(27,'A+',DATE_SUB(CURDATE(), INTERVAL 11916 DAY),70,4,'0328-5220771','44398-6978022-1',NULL,1,NULL),
(28,'O-',DATE_SUB(CURDATE(), INTERVAL 16802 DAY),64,3,'0321-5981860','44426-1340075-9',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(29,'A+',DATE_SUB(CURDATE(), INTERVAL 19738 DAY),92,2,'0319-3058316','41138-3108593-9',NULL,1,NULL),
(30,'O+',DATE_SUB(CURDATE(), INTERVAL 13884 DAY),70,3,'0342-6972209','44493-7328066-4',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1,NULL),
(31,'AB+',DATE_SUB(CURDATE(), INTERVAL 12109 DAY),58,2,'0308-7658179','43905-3477715-4',DATE_SUB(CURDATE(), INTERVAL 400 DAY),1,NULL),
(32,'O-',DATE_SUB(CURDATE(), INTERVAL 13417 DAY),73,3,'0325-3326285','41959-4008192-9',DATE_SUB(CURDATE(), INTERVAL 200 DAY),1,NULL),
(33,'A-',DATE_SUB(CURDATE(), INTERVAL 18750 DAY),67,3,'0320-1191416','42581-1978059-4',DATE_SUB(CURDATE(), INTERVAL 400 DAY),1,NULL),
(34,'A+',DATE_SUB(CURDATE(), INTERVAL 10424 DAY),92,3,'0318-5626517','43981-4371874-6',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(35,'A+',DATE_SUB(CURDATE(), INTERVAL 17800 DAY),73,5,'0323-3655736','44257-9134545-1',DATE_SUB(CURDATE(), INTERVAL 45 DAY),1,NULL),
(36,'A+',DATE_SUB(CURDATE(), INTERVAL 19189 DAY),52,1,'0311-6016995','43659-5446211-3',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1,NULL),
(37,'B-',DATE_SUB(CURDATE(), INTERVAL 9134 DAY),76,3,'0344-1748986','44489-9667041-3',NULL,1,NULL),
(38,'O+',DATE_SUB(CURDATE(), INTERVAL 8832 DAY),92,1,'0328-5386303','43142-4183925-3',DATE_SUB(CURDATE(), INTERVAL 200 DAY),1,NULL),
(39,'A-',DATE_SUB(CURDATE(), INTERVAL 19491 DAY),67,5,'0333-1158525','44532-6966953-9',NULL,1,NULL),
(40,'O+',DATE_SUB(CURDATE(), INTERVAL 18995 DAY),58,5,'0342-3750423','41219-9640135-1',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(41,'B-',DATE_SUB(CURDATE(), INTERVAL 15484 DAY),61,2,'0319-5163351','42208-6474258-8',DATE_SUB(CURDATE(), INTERVAL 400 DAY),1,NULL),
(42,'O+',DATE_SUB(CURDATE(), INTERVAL 8590 DAY),64,2,'0344-2972144','43585-9367626-2',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1,NULL),
(43,'AB+',DATE_SUB(CURDATE(), INTERVAL 12593 DAY),85,4,'0345-2384950','41897-2474161-8',DATE_SUB(CURDATE(), INTERVAL 300 DAY),1,NULL),
(44,'O+',DATE_SUB(CURDATE(), INTERVAL 12890 DAY),58,2,'0338-6565214','43841-9274426-7',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(45,'B+',DATE_SUB(CURDATE(), INTERVAL 11552 DAY),61,6,'0326-7402649','42258-8410022-3',DATE_SUB(CURDATE(), INTERVAL 150 DAY),1,NULL),
(46,'A+',DATE_SUB(CURDATE(), INTERVAL 14660 DAY),67,2,'0334-3438774','43339-6566204-3',DATE_SUB(CURDATE(), INTERVAL 120 DAY),1,NULL);
INSERT INTO blood_requests (id,hospital_id,blood_group,units_needed,units_fulfilled,urgency,required_by,ward_notes,status,created_at) VALUES
(1,2,'O+',2,1,'Critical',NOW() + INTERVAL 34 HOUR,'Dialysis unit','open',NOW() - INTERVAL 5 HOUR),
(2,3,'A+',3,0,'Urgent',NOW() + INTERVAL 7 HOUR,'Thalassemia ward','open',NOW() - INTERVAL 1 HOUR),
(3,4,'B+',3,0,'Normal',NOW() + INTERVAL 16 HOUR,'Maternity ward','open',NOW() - INTERVAL 1 HOUR),
(4,5,'O-',2,0,'Critical',NOW() + INTERVAL 10 HOUR,'Maternity ward','open',NOW() - INTERVAL 8 HOUR),
(5,2,'AB+',2,0,'Urgent',NOW() + INTERVAL 20 HOUR,'Thalassemia ward','open',NOW() - INTERVAL 8 HOUR),
(6,3,'A-',4,0,'Normal',NOW() + INTERVAL 19 HOUR,'Maternity ward','open',NOW() - INTERVAL 5 HOUR),
(7,4,'B+',2,2,'Urgent',NOW() + INTERVAL 8 HOUR,'Maternity ward','fulfilled',NOW() - INTERVAL 3 HOUR),
(8,5,'O+',2,2,'Critical',NOW() + INTERVAL 7 HOUR,'Thalassemia ward','fulfilled',NOW() - INTERVAL 3 HOUR),
(9,2,'A+',2,2,'Normal',NOW() - INTERVAL 31 DAY,'ICU - accident case','closed',NOW() - INTERVAL 32 DAY),
(10,3,'O+',3,3,'Urgent',NOW() - INTERVAL 11 DAY,'Dialysis unit','closed',NOW() - INTERVAL 12 DAY),
(11,4,'B-',1,1,'Critical',NOW() - INTERVAL 12 DAY,'Maternity ward','closed',NOW() - INTERVAL 13 DAY),
(12,5,'O+',3,3,'Normal',NOW() - INTERVAL 4 DAY,'Thalassemia ward','closed',NOW() - INTERVAL 5 DAY),
(13,2,'AB+',3,3,'Urgent',NOW() - INTERVAL 37 DAY,'Maternity ward','closed',NOW() - INTERVAL 38 DAY),
(14,3,'B+',3,0,'Normal',NOW() - INTERVAL 3 DAY,'Ward 3, surgery scheduled','cancelled',NOW() - INTERVAL 4 DAY),
(15,4,'A+',4,0,'Urgent',NOW() - INTERVAL 16 DAY,'Thalassemia ward','cancelled',NOW() - INTERVAL 17 DAY),
(16,5,'O+',4,0,'Normal',NOW() - INTERVAL 40 DAY,'Emergency ward','expired',NOW() - INTERVAL 41 DAY),
(17,2,'B+',3,0,'Urgent',NOW() - INTERVAL 19 DAY,'Maternity ward','expired',NOW() - INTERVAL 20 DAY),
(18,3,'A-',1,0,'Critical',NOW() - INTERVAL 45 DAY,'ICU - accident case','expired',NOW() - INTERVAL 46 DAY),
(19,4,'O+',2,0,'Normal',NOW() - INTERVAL 15 DAY,'Thalassemia ward','expired',NOW() - INTERVAL 16 DAY),
(20,5,'A+',4,0,'Urgent',NOW() - INTERVAL 18 DAY,'ICU - accident case','expired',NOW() - INTERVAL 19 DAY);
INSERT INTO request_matches (id,request_id,donor_id,score,status,decline_reason,notified_at,responded_at) VALUES
(1,1,40,38.6,'accepted',NULL,NOW() - INTERVAL 5 HOUR,(NOW() - INTERVAL 5 HOUR) + INTERVAL 30 MINUTE),
(2,1,11,78.47,'notified',NULL,NOW() - INTERVAL 5 HOUR,NULL),
(3,1,30,51.02,'declined','Out of town this week',NOW() - INTERVAL 5 HOUR,(NOW() - INTERVAL 5 HOUR) + INTERVAL 30 MINUTE),
(4,1,21,42.84,'declined','Out of town this week',NOW() - INTERVAL 5 HOUR,(NOW() - INTERVAL 5 HOUR) + INTERVAL 30 MINUTE),
(5,1,14,84.98,'declined','Out of town this week',NOW() - INTERVAL 5 HOUR,(NOW() - INTERVAL 5 HOUR) + INTERVAL 30 MINUTE),
(6,2,36,69.06,'declined','Out of town this week',NOW() - INTERVAL 1 HOUR,(NOW() - INTERVAL 1 HOUR) + INTERVAL 30 MINUTE),
(7,2,16,75.87,'declined','Out of town this week',NOW() - INTERVAL 1 HOUR,(NOW() - INTERVAL 1 HOUR) + INTERVAL 30 MINUTE),
(8,2,26,43.0,'notified',NULL,NOW() - INTERVAL 1 HOUR,NULL),
(9,3,10,50.23,'notified',NULL,NOW() - INTERVAL 1 HOUR,NULL),
(10,3,30,46.92,'notified',NULL,NOW() - INTERVAL 1 HOUR,NULL),
(11,3,15,65.74,'notified',NULL,NOW() - INTERVAL 1 HOUR,NULL),
(12,3,9,53.24,'declined','Out of town this week',NOW() - INTERVAL 1 HOUR,(NOW() - INTERVAL 1 HOUR) + INTERVAL 30 MINUTE),
(13,3,11,38.25,'notified',NULL,NOW() - INTERVAL 1 HOUR,NULL),
(14,4,10,45.87,'declined','Out of town this week',NOW() - INTERVAL 8 HOUR,(NOW() - INTERVAL 8 HOUR) + INTERVAL 30 MINUTE),
(15,4,28,86.81,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(16,4,11,80.56,'declined','Out of town this week',NOW() - INTERVAL 8 HOUR,(NOW() - INTERVAL 8 HOUR) + INTERVAL 30 MINUTE),
(17,4,15,66.23,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(18,4,13,72.83,'declined','Out of town this week',NOW() - INTERVAL 8 HOUR,(NOW() - INTERVAL 8 HOUR) + INTERVAL 30 MINUTE),
(19,5,42,62.44,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(20,5,12,76.58,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(21,5,10,75.8,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(22,5,14,73.47,'notified',NULL,NOW() - INTERVAL 8 HOUR,NULL),
(23,6,13,63.25,'notified',NULL,NOW() - INTERVAL 5 HOUR,NULL),
(24,6,10,40.68,'declined','Out of town this week',NOW() - INTERVAL 5 HOUR,(NOW() - INTERVAL 5 HOUR) + INTERVAL 30 MINUTE),
(25,6,16,85.63,'notified',NULL,NOW() - INTERVAL 5 HOUR,NULL),
(26,7,24,77.3,'accepted',NULL,NOW() - INTERVAL 3 HOUR,(NOW() - INTERVAL 3 HOUR) + INTERVAL 30 MINUTE),
(27,7,20,93.11,'accepted',NULL,NOW() - INTERVAL 3 HOUR,(NOW() - INTERVAL 3 HOUR) + INTERVAL 30 MINUTE),
(28,7,7,72.76,'no_response',NULL,NOW() - INTERVAL 3 HOUR,NULL),
(29,7,30,61.43,'no_response',NULL,NOW() - INTERVAL 3 HOUR,NULL),
(30,8,28,36.83,'accepted',NULL,NOW() - INTERVAL 3 HOUR,(NOW() - INTERVAL 3 HOUR) + INTERVAL 30 MINUTE),
(31,8,44,93.26,'accepted',NULL,NOW() - INTERVAL 3 HOUR,(NOW() - INTERVAL 3 HOUR) + INTERVAL 30 MINUTE),
(32,8,14,73.89,'no_response',NULL,NOW() - INTERVAL 3 HOUR,NULL),
(33,8,21,89.82,'no_response',NULL,NOW() - INTERVAL 3 HOUR,NULL),
(34,9,34,63.11,'donated',NULL,NOW() - INTERVAL 32 DAY,(NOW() - INTERVAL 32 DAY) + INTERVAL 30 MINUTE),
(35,9,18,67.77,'donated',NULL,NOW() - INTERVAL 32 DAY,(NOW() - INTERVAL 32 DAY) + INTERVAL 30 MINUTE),
(36,9,10,82.79,'declined','Out of town this week',NOW() - INTERVAL 32 DAY,(NOW() - INTERVAL 32 DAY) + INTERVAL 30 MINUTE),
(37,9,27,83.82,'no_response',NULL,NOW() - INTERVAL 32 DAY,NULL),
(38,9,38,37.23,'declined','Out of town this week',NOW() - INTERVAL 32 DAY,(NOW() - INTERVAL 32 DAY) + INTERVAL 30 MINUTE),
(39,9,36,39.22,'no_response',NULL,NOW() - INTERVAL 32 DAY,NULL),
(40,10,15,45.09,'donated',NULL,NOW() - INTERVAL 12 DAY,(NOW() - INTERVAL 12 DAY) + INTERVAL 30 MINUTE),
(41,10,14,86.63,'donated',NULL,NOW() - INTERVAL 12 DAY,(NOW() - INTERVAL 12 DAY) + INTERVAL 30 MINUTE),
(42,10,13,36.09,'donated',NULL,NOW() - INTERVAL 12 DAY,(NOW() - INTERVAL 12 DAY) + INTERVAL 30 MINUTE),
(43,11,21,76.43,'donated',NULL,NOW() - INTERVAL 13 DAY,(NOW() - INTERVAL 13 DAY) + INTERVAL 30 MINUTE),
(44,11,32,59.0,'no_response',NULL,NOW() - INTERVAL 13 DAY,NULL),
(45,11,10,60.13,'no_response',NULL,NOW() - INTERVAL 13 DAY,NULL),
(46,11,15,49.95,'declined','Out of town this week',NOW() - INTERVAL 13 DAY,(NOW() - INTERVAL 13 DAY) + INTERVAL 30 MINUTE),
(47,11,13,61.31,'declined','Out of town this week',NOW() - INTERVAL 13 DAY,(NOW() - INTERVAL 13 DAY) + INTERVAL 30 MINUTE),
(48,11,41,70.3,'no_response',NULL,NOW() - INTERVAL 13 DAY,NULL),
(49,12,38,81.02,'donated',NULL,NOW() - INTERVAL 5 DAY,(NOW() - INTERVAL 5 DAY) + INTERVAL 30 MINUTE),
(50,12,13,86.82,'donated',NULL,NOW() - INTERVAL 5 DAY,(NOW() - INTERVAL 5 DAY) + INTERVAL 30 MINUTE),
(51,12,14,69.82,'donated',NULL,NOW() - INTERVAL 5 DAY,(NOW() - INTERVAL 5 DAY) + INTERVAL 30 MINUTE),
(52,12,15,56.21,'declined','Out of town this week',NOW() - INTERVAL 5 DAY,(NOW() - INTERVAL 5 DAY) + INTERVAL 30 MINUTE),
(53,12,11,92.19,'declined','Out of town this week',NOW() - INTERVAL 5 DAY,(NOW() - INTERVAL 5 DAY) + INTERVAL 30 MINUTE),
(54,13,34,92.42,'donated',NULL,NOW() - INTERVAL 38 DAY,(NOW() - INTERVAL 38 DAY) + INTERVAL 30 MINUTE),
(55,13,31,89.13,'donated',NULL,NOW() - INTERVAL 38 DAY,(NOW() - INTERVAL 38 DAY) + INTERVAL 30 MINUTE),
(56,13,30,93.04,'donated',NULL,NOW() - INTERVAL 38 DAY,(NOW() - INTERVAL 38 DAY) + INTERVAL 30 MINUTE),
(57,14,37,84.21,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(58,14,45,56.12,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(59,14,15,58.58,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(60,14,41,49.54,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(61,14,30,59.81,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(62,14,23,40.36,'no_response',NULL,NOW() - INTERVAL 4 DAY,NULL),
(63,15,34,43.32,'declined','Out of town this week',NOW() - INTERVAL 17 DAY,(NOW() - INTERVAL 17 DAY) + INTERVAL 30 MINUTE),
(64,15,13,40.38,'no_response',NULL,NOW() - INTERVAL 17 DAY,NULL),
(65,15,27,64.88,'declined','Out of town this week',NOW() - INTERVAL 17 DAY,(NOW() - INTERVAL 17 DAY) + INTERVAL 30 MINUTE),
(66,16,11,79.79,'no_response',NULL,NOW() - INTERVAL 41 DAY,NULL),
(67,16,15,93.35,'declined','Out of town this week',NOW() - INTERVAL 41 DAY,(NOW() - INTERVAL 41 DAY) + INTERVAL 30 MINUTE),
(68,16,30,90.21,'no_response',NULL,NOW() - INTERVAL 41 DAY,NULL),
(69,16,21,77.87,'no_response',NULL,NOW() - INTERVAL 41 DAY,NULL),
(70,16,13,40.83,'no_response',NULL,NOW() - INTERVAL 41 DAY,NULL),
(71,17,32,57.87,'no_response',NULL,NOW() - INTERVAL 20 DAY,NULL),
(72,17,41,35.3,'no_response',NULL,NOW() - INTERVAL 20 DAY,NULL),
(73,17,14,42.29,'no_response',NULL,NOW() - INTERVAL 20 DAY,NULL),
(74,17,15,84.92,'declined','Out of town this week',NOW() - INTERVAL 20 DAY,(NOW() - INTERVAL 20 DAY) + INTERVAL 30 MINUTE),
(75,18,10,84.08,'no_response',NULL,NOW() - INTERVAL 46 DAY,NULL),
(76,18,13,71.74,'no_response',NULL,NOW() - INTERVAL 46 DAY,NULL),
(77,18,11,47.27,'no_response',NULL,NOW() - INTERVAL 46 DAY,NULL),
(78,18,26,52.67,'declined','Out of town this week',NOW() - INTERVAL 46 DAY,(NOW() - INTERVAL 46 DAY) + INTERVAL 30 MINUTE),
(79,18,39,48.49,'no_response',NULL,NOW() - INTERVAL 46 DAY,NULL),
(80,19,7,55.86,'declined','Out of town this week',NOW() - INTERVAL 16 DAY,(NOW() - INTERVAL 16 DAY) + INTERVAL 30 MINUTE),
(81,19,30,49.55,'no_response',NULL,NOW() - INTERVAL 16 DAY,NULL),
(82,19,13,53.12,'no_response',NULL,NOW() - INTERVAL 16 DAY,NULL),
(83,19,38,72.27,'no_response',NULL,NOW() - INTERVAL 16 DAY,NULL),
(84,19,32,53.02,'declined','Out of town this week',NOW() - INTERVAL 16 DAY,(NOW() - INTERVAL 16 DAY) + INTERVAL 30 MINUTE),
(85,20,7,35.81,'no_response',NULL,NOW() - INTERVAL 19 DAY,NULL),
(86,20,22,60.76,'no_response',NULL,NOW() - INTERVAL 19 DAY,NULL),
(87,20,33,88.54,'declined','Out of town this week',NOW() - INTERVAL 19 DAY,(NOW() - INTERVAL 19 DAY) + INTERVAL 30 MINUTE),
(88,20,11,86.25,'no_response',NULL,NOW() - INTERVAL 19 DAY,NULL);
INSERT INTO donations (match_id,units,donated_on,verified_by) VALUES
(34,1,DATE_SUB(CURDATE(), INTERVAL 31 DAY),2),
(35,1,DATE_SUB(CURDATE(), INTERVAL 31 DAY),2),
(40,1,DATE_SUB(CURDATE(), INTERVAL 11 DAY),3),
(41,1,DATE_SUB(CURDATE(), INTERVAL 11 DAY),3),
(42,1,DATE_SUB(CURDATE(), INTERVAL 11 DAY),3),
(43,1,DATE_SUB(CURDATE(), INTERVAL 12 DAY),4),
(49,1,DATE_SUB(CURDATE(), INTERVAL 4 DAY),5),
(50,1,DATE_SUB(CURDATE(), INTERVAL 4 DAY),5),
(51,1,DATE_SUB(CURDATE(), INTERVAL 4 DAY),5),
(54,1,DATE_SUB(CURDATE(), INTERVAL 37 DAY),2),
(55,1,DATE_SUB(CURDATE(), INTERVAL 37 DAY),2),
(56,1,DATE_SUB(CURDATE(), INTERVAL 37 DAY),2);
INSERT INTO blood_inventory (hospital_id,blood_group,units_available) VALUES
(2,'A+',8),
(2,'A-',9),
(2,'B+',6),
(2,'B-',5),
(2,'AB+',13),
(2,'AB-',11),
(2,'O+',13),
(2,'O-',13),
(3,'A+',0),
(3,'A-',1),
(3,'B+',3),
(3,'B-',3),
(3,'AB+',2),
(3,'AB-',7),
(3,'O+',9),
(3,'O-',6),
(4,'A+',7),
(4,'A-',13),
(4,'B+',2),
(4,'B-',6),
(4,'AB+',8),
(4,'AB-',3),
(4,'O+',5),
(4,'O-',0),
(5,'A+',8),
(5,'A-',1),
(5,'B+',1),
(5,'B-',1),
(5,'AB+',3),
(5,'AB-',6),
(5,'O+',1),
(5,'O-',7);
INSERT INTO notifications (user_id,message,is_read) VALUES
 (7,'Welcome to DonorNexa. Thank you for registering as a donor.',1),
 (2,'Your hospital account has been verified by the administrator.',1),
 (3,'Your hospital account has been verified by the administrator.',1);
INSERT INTO audit_logs (user_id,action,entity,old_value,new_value,ip_address,created_at) VALUES
 (1,'hospital_verified','hospitals:2','pending','verified','127.0.0.1',NOW() - INTERVAL 40 DAY),
 (1,'hospital_verified','hospitals:3','pending','verified','127.0.0.1',NOW() - INTERVAL 38 DAY),
 (1,'hospital_verified','hospitals:4','pending','verified','127.0.0.1',NOW() - INTERVAL 30 DAY),
 (1,'hospital_verified','hospitals:5','pending','verified','127.0.0.1',NOW() - INTERVAL 25 DAY),
 (NULL,'request_status','blood_requests:16','open','expired','system',NOW() - INTERVAL 5 DAY);
SET FOREIGN_KEY_CHECKS = 1;
