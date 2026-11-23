-- =====================================================================
-- DonorNexa - Clean Production Database Initialization
-- Sets up schema, reference tables, cities, and settings.
-- Contains ZERO dummy users, dummy requests, or test records.
-- =====================================================================

-- 1. Import base schema (tables, foreign keys, triggers, view)
SOURCE schema.sql;

-- 2. Populate Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('cooldown_days',      '90',  'Days a donor must wait between donations before being eligible again'),
('response_hours',     '2',   'Hours a notified donor has to accept an urgent request before expiry'),
('max_open_critical',  '3',   'Maximum number of open Critical requests a single hospital may have'),
('min_trust_to_post',  '20',  'Minimum hospital trust score required to raise new requests'),
('cancel_penalty',     '5',   'Trust score points deducted when a hospital cancels a request'),
('default_pool_size',  '5',   'Base number of donors notified per batch (multiplied by urgency)');

-- 3. Populate Medical ABO/Rh Compatibility Matrix
INSERT INTO blood_compatibility (donor_group, recipient_group) VALUES
('O-', 'O-'), ('O-', 'O+'), ('O-', 'A-'), ('O-', 'A+'), ('O-', 'B-'), ('O-', 'B+'), ('O-', 'AB-'), ('O-', 'AB+'),
('O+', 'O+'), ('O+', 'A+'), ('O+', 'B+'), ('O+', 'AB+'),
('A-', 'A-'), ('A-', 'A+'), ('A-', 'AB-'), ('A-', 'AB+'),
('A+', 'A+'), ('A+', 'AB+'),
('B-', 'B-'), ('B-', 'B+'), ('B-', 'AB-'), ('B-', 'AB+'),
('B+', 'B+'), ('B+', 'AB+'),
('AB-', 'AB-'), ('AB-', 'AB+'),
('AB+', 'AB+');

-- 4. Populate Standard Geographic Reference (Pakistan Cities & Provinces)
INSERT INTO cities (city_name, province) VALUES
('Hyderabad',   'Sindh'),
('Karachi',     'Sindh'),
('Jamshoro',    'Sindh'),
('Sukkur',      'Sindh'),
('Larkana',     'Sindh'),
('Mirpur Khas', 'Sindh'),
('Nawabshah',   'Sindh'),
('Lahore',      'Punjab'),
('Rawalpindi',  'Punjab'),
('Faisalabad',  'Punjab'),
('Multan',      'Punjab'),
('Gujranwala',  'Punjab'),
('Islamabad',   'Islamabad Capital Territory'),
('Peshawar',    'Khyber Pakhtunkhwa'),
('Mardan',      'Khyber Pakhtunkhwa'),
('Abbottabad',  'Khyber Pakhtunkhwa'),
('Quetta',      'Balochistan'),
('Gwadar',      'Balochistan');

-- End of clean production setup.
-- Next step: run 'php cli/create_admin.php admin@yourdomain.com YourPassword' to set up your primary administrator account.
