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
