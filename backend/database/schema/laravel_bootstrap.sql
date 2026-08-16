-- BISM4RCK/KUN3H0 2026
-- BISM4RCK/KUN3H0 2026



-- Drop dependent tables before the tables they reference.
-- gate_logs references walk_in_visitors, visitor_requests, vehicles, residents, and users.
-- This order makes a fresh migration/reset safe on MySQL 8.x.

DROP TABLE IF EXISTS failed_jobs;
DROP TABLE IF EXISTS job_batches;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS cache_locks;
DROP TABLE IF EXISTS cache;
DROP TABLE IF EXISTS concerns;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS account_activity_logs;
DROP TABLE IF EXISTS gate_commands;
DROP TABLE IF EXISTS rfid_scan_sessions;

DROP TABLE IF EXISTS gate_logs;

DROP TABLE IF EXISTS walk_in_visitor_vehicles;
DROP TABLE IF EXISTS walk_in_visitors;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS visitor_attachments;
DROP TABLE IF EXISTS visitor_credentials;
DROP TABLE IF EXISTS visitor_request_vehicles;
DROP TABLE IF EXISTS visitor_requests;

DROP TABLE IF EXISTS blacklist;

DROP TABLE IF EXISTS rfid_cards;
DROP TABLE IF EXISTS rfid_tags;
DROP TABLE IF EXISTS user_vehicles;

DROP TABLE IF EXISTS vehicles;

DROP TABLE IF EXISTS ui_settings;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS guards;
DROP TABLE IF EXISTS residents;
DROP TABLE IF EXISTS users;


CREATE TABLE cache (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_locks (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    KEY sessions_user_id_index (user_id),
    KEY sessions_last_activity_index (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    KEY jobs_queue_index (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_batches (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
full_name VARCHAR(150) NOT NULL,
email VARCHAR(150) NOT NULL UNIQUE,
password VARCHAR(255) NOT NULL,
role ENUM('resident',
'guard',
'admin') NOT NULL,
status ENUM('active',
'inactive',
'suspended') NOT NULL DEFAULT 'active',
last_login_at DATETIME NULL,
is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE residents (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL UNIQUE,
house_number VARCHAR(50) NOT NULL UNIQUE,
block_number VARCHAR(50) NULL,
lot_number VARCHAR(50) NULL,
household_letter VARCHAR(5) NULL,
contact_number VARCHAR(30) NULL,
emergency_contact VARCHAR(30) NULL,
status ENUM('active',
'inactive') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_residents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE guards (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL UNIQUE,
guard_code VARCHAR(50) NULL,
shift_name VARCHAR(50) NULL,
contact_number VARCHAR(30) NULL,
status ENUM('active',
'inactive') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_guards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admins (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL UNIQUE,
admin_code VARCHAR(50) NULL,
status ENUM('active',
'inactive') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicles (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
resident_id BIGINT UNSIGNED NOT NULL,
plate_number VARCHAR(30) NOT NULL UNIQUE,
vehicle_type ENUM('car',
'motorcycle',
'truck',
'other') NOT NULL,
brand VARCHAR(100) NULL,
model VARCHAR(100) NULL,
color VARCHAR(50) NULL,
status ENUM('active',
'inactive') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_vehicles_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rfid_tags (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
vehicle_id BIGINT UNSIGNED NOT NULL UNIQUE,
uid VARCHAR(100) NOT NULL UNIQUE,
tag_type ENUM('windshield',
'card',
'fob',
'other') NOT NULL DEFAULT 'windshield',
issued_at DATETIME NULL,
status ENUM('active',
'inactive',
'lost',
'revoked') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_rfid_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rfid_cards (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
vehicle_id BIGINT UNSIGNED NULL,
staff_vehicle_id BIGINT UNSIGNED NULL,
uid VARCHAR(100) NULL UNIQUE,
credential_code VARCHAR(100) NULL,
status ENUM('active',
'void') NOT NULL DEFAULT 'active',
issued_by BIGINT UNSIGNED NULL,
issued_at DATETIME NULL,
voided_by BIGINT UNSIGNED NULL,
voided_at DATETIME NULL,
notes VARCHAR(255) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
KEY idx_rfid_cards_user (user_id),
KEY idx_rfid_cards_vehicle (vehicle_id),
KEY idx_rfid_cards_staff_vehicle (staff_vehicle_id),
KEY idx_rfid_cards_status (status),
KEY idx_rfid_cards_credential_code (credential_code),
CONSTRAINT fk_rfid_cards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
CONSTRAINT fk_rfid_cards_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
CONSTRAINT fk_rfid_cards_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_rfid_cards_voided_by FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rfid_scan_sessions (
id CHAR(32) PRIMARY KEY,
device_id VARCHAR(64) NOT NULL,
actor_user_id BIGINT UNSIGNED NOT NULL,
actor_role ENUM('guard',
'admin') NOT NULL,
purpose ENUM('gate',
'burn') NOT NULL DEFAULT 'gate',
target_user_id BIGINT UNSIGNED NULL,
target_vehicle_id BIGINT UNSIGNED NULL,
target_staff_vehicle_id BIGINT UNSIGNED NULL,
notes VARCHAR(255) NULL,
status ENUM('waiting',
'submitted',
'approved',
'error',
'expired') NOT NULL DEFAULT 'waiting',
rfid_uid VARCHAR(100) NULL,
result_json LONGTEXT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
expires_at DATETIME NOT NULL,
KEY idx_rfid_scan_device_status(device_id,
status,
expires_at),
KEY idx_rfid_scan_target(target_user_id),
KEY idx_rfid_scan_vehicle(target_vehicle_id),
CONSTRAINT fk_rfid_scan_target FOREIGN KEY(target_user_id) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_rfid_scan_vehicle FOREIGN KEY(target_vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
CONSTRAINT fk_rfid_scan_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visitor_requests (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
resident_id BIGINT UNSIGNED NOT NULL,
house_number VARCHAR(50) NOT NULL,
visitor_name VARCHAR(150) NOT NULL,
contact_number VARCHAR(30) NULL,
plate_number VARCHAR(30) NOT NULL,
vehicle_type ENUM('car',
'motorcycle',
'truck',
'other') NOT NULL DEFAULT 'other',
purpose_of_visit VARCHAR(255) NOT NULL,
people_count INT UNSIGNED NOT NULL DEFAULT 1,
id_not_available TINYINT(1) NOT NULL DEFAULT 0,
government_id_type VARCHAR(100) NULL,
government_id_number VARCHAR(100) NULL,
status ENUM('pending',
'approved',
'rejected',
'expired',
'cancelled') NOT NULL DEFAULT 'pending',
requested_visit_date DATE NULL,
requested_arrival_time TIME NULL,
requested_departure_time TIME NULL,
approved_by BIGINT UNSIGNED NULL,
approved_at DATETIME NULL,
rejected_by BIGINT UNSIGNED NULL,
rejected_at DATETIME NULL,
rejection_reason VARCHAR(255) NULL,
qr_reference VARCHAR(120) NULL UNIQUE,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_visitor_requests_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
CONSTRAINT fk_visitor_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_visitor_requests_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE visitor_request_vehicles (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
visitor_request_id BIGINT UNSIGNED NOT NULL,
plate_number VARCHAR(30) NOT NULL,
vehicle_type ENUM('car',
'motorcycle',
'truck',
'other') NOT NULL DEFAULT 'other',
people_count INT UNSIGNED NOT NULL DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_vrv_request FOREIGN KEY (visitor_request_id) REFERENCES visitor_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE visitor_attachments (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
visitor_request_id BIGINT UNSIGNED NOT NULL,
file_type ENUM('government_id',
'other') NOT NULL DEFAULT 'government_id',
file_path VARCHAR(255) NOT NULL,
original_filename VARCHAR(255) NULL,
mime_type VARCHAR(100) NULL,
file_size BIGINT UNSIGNED NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_attachments_request FOREIGN KEY (visitor_request_id) REFERENCES visitor_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
visitor_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
booking_code VARCHAR(100) NOT NULL UNIQUE,
qr_code_text VARCHAR(255) NOT NULL UNIQUE,
status ENUM('active',
'used',
'expired',
'cancelled') NOT NULL DEFAULT 'active',
check_in_at DATETIME NULL,
check_out_at DATETIME NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_bookings_request FOREIGN KEY (visitor_request_id) REFERENCES visitor_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE walk_in_visitors (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
visitor_id CHAR(6) NOT NULL UNIQUE,
visitor_name VARCHAR(150) NOT NULL,
contact_number VARCHAR(30) NULL,
purpose_of_visit VARCHAR(255) NOT NULL,
plate_number VARCHAR(30) NULL,
vehicle_type VARCHAR(50) NOT NULL DEFAULT 'other',
barcode_token_hash CHAR(64) NOT NULL UNIQUE,
barcode_token VARCHAR(255) NOT NULL,
created_by BIGINT UNSIGNED NULL,
status ENUM('active',
'completed',
'cancelled') NOT NULL DEFAULT 'active',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_walkin_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE walk_in_visitor_vehicles (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
walk_in_id BIGINT UNSIGNED NOT NULL,
plate_number VARCHAR(30) NOT NULL,
vehicle_type VARCHAR(50) NOT NULL DEFAULT 'other',
people_count INT UNSIGNED NOT NULL DEFAULT 1,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
CONSTRAINT fk_walkin_vehicle_walkin FOREIGN KEY (walk_in_id) REFERENCES walk_in_visitors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE gate_logs (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
resident_id BIGINT UNSIGNED NULL,
vehicle_id BIGINT UNSIGNED NULL,
visitor_request_id BIGINT UNSIGNED NULL,
walk_in_id BIGINT UNSIGNED NULL,
guard_id BIGINT UNSIGNED NULL,
actor_user_id BIGINT UNSIGNED NULL,
actor_role VARCHAR(20) NULL,
rfid_uid VARCHAR(100) NULL,
plate_number VARCHAR(30) NULL,
event_type VARCHAR(50) NOT NULL,
gate_status ENUM('approved',
'denied',
'pending',
'manual_override') NOT NULL DEFAULT 'pending',
source_device VARCHAR(100) NULL,
reader VARCHAR(10) NULL,
plate_photo_path VARCHAR(255) NULL,
vehicle_photo_path VARCHAR(255) NULL,
raw_payload LONGTEXT NULL,
log_notes VARCHAR(255) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_gate_logs_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
CONSTRAINT fk_gate_logs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
CONSTRAINT fk_gate_logs_guard FOREIGN KEY (guard_id) REFERENCES users(id) ON DELETE SET NULL,
CONSTRAINT fk_gate_logs_visitor_request FOREIGN KEY (visitor_request_id) REFERENCES visitor_requests(id) ON DELETE SET NULL,
CONSTRAINT fk_gate_logs_walk_in FOREIGN KEY (walk_in_id) REFERENCES walk_in_visitors(id) ON DELETE SET NULL,
CONSTRAINT fk_gate_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
title VARCHAR(150) NOT NULL,
message TEXT NOT NULL,
is_read TINYINT(1) NOT NULL DEFAULT 0,
reference_type VARCHAR(50) NULL,
reference_id BIGINT UNSIGNED NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ui_settings (
setting_key VARCHAR(80) PRIMARY KEY,
bg_color VARCHAR(7) NULL,
text_color VARCHAR(7) NULL,
width_px INT NULL,
height_px INT NULL,
radius_px INT NULL,
updated_by BIGINT UNSIGNED NULL,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_ui_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NULL,
action VARCHAR(150) NOT NULL,
module_name VARCHAR(100) NULL,
details TEXT NULL,
ip_address VARCHAR(45) NULL,
user_agent VARCHAR(255) NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE blacklist (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
resident_id BIGINT UNSIGNED NULL,
visitor_name VARCHAR(150) NULL,
plate_number VARCHAR(30) NULL,
reason VARCHAR(255) NOT NULL,
status ENUM('active',
'inactive') NOT NULL DEFAULT 'active',
start_date DATE NULL,
end_date DATE NULL,
created_by BIGINT UNSIGNED NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_blacklist_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
CONSTRAINT fk_blacklist_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE concerns (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
resident_id BIGINT UNSIGNED NULL,
sender_name VARCHAR(150) NOT NULL,
sender_role ENUM('resident',
'admin') NOT NULL DEFAULT 'resident',
house_number VARCHAR(50) NULL,
subject VARCHAR(150) NOT NULL,
message TEXT NOT NULL,
reply TEXT NULL,
status ENUM('open',
'closed') NOT NULL DEFAULT 'open',
replied_by BIGINT UNSIGNED NULL,
replied_at DATETIME NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_concerns_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
CONSTRAINT fk_concerns_replied_by FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_residents_house_number ON residents(house_number);

CREATE INDEX idx_visitor_requests_house_number ON visitor_requests(house_number);

CREATE INDEX idx_visitor_requests_status ON visitor_requests(status);

CREATE INDEX idx_gate_logs_created_at ON gate_logs(created_at);

CREATE INDEX idx_gate_logs_plate ON gate_logs(plate_number);

CREATE INDEX idx_blacklist_plate ON blacklist(plate_number);

CREATE INDEX idx_notifications_is_read ON notifications(is_read);

CREATE INDEX idx_concerns_status ON concerns(status);

INSERT INTO users (full_name,
email,
password,
role,
status) VALUES
('Golden Resident',
'resident@goldenhomes.local',
'$2y$12$8FfPm6d09mvevtK3as10UeLq3X.EX0Xv05vsgTX0kIg5POU7XuE/6',
'resident',
'active'),
('Gate Guard',
'guard@goldenhomes.local',
'$2y$12$8YEEawRXeKk19/k1fwZMtewlsjvOZN4x0VgvfOt5YEtcJWOeG3PAW',
'guard',
'active'),
('Subdivision Admin',
'admin@goldenhomes.local',
'$2y$12$HiPq2.RM1PGErw7L0t7lNuGwUzh5ESj5XFWt.UWe0QG4VEeJe6Mzi',
'admin',
'active');

SET @resident_user_id = (SELECT id FROM users WHERE email='resident@goldenhomes.local' LIMIT 1);

SET @guard_user_id = (SELECT id FROM users WHERE email='guard@goldenhomes.local' LIMIT 1);

SET @admin_user_id = (SELECT id FROM users WHERE email='admin@goldenhomes.local' LIMIT 1);

INSERT INTO residents (user_id,
house_number,
block_number,
contact_number,
emergency_contact)
VALUES (@resident_user_id,
'12-4-A',
'12',
'09171234567',
'09179876543');

INSERT INTO guards (user_id,
guard_code,
shift_name,
contact_number)
VALUES (@guard_user_id,
'GRD-001',
'Day Shift',
'09170001111');

INSERT INTO admins (user_id,
admin_code)
VALUES (@admin_user_id,
'ADM-001');

SET @resident_id = (SELECT id FROM residents WHERE house_number='12-4-A' LIMIT 1);

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color) VALUES
(@resident_id,
'ABC 1234',
'car',
'Toyota',
'Vios',
'White'),
(@resident_id,
'XYZ 7788',
'motorcycle',
'Honda',
'Click',
'Black');

INSERT INTO rfid_tags (vehicle_id,
uid,
tag_type,
issued_at,
status)
VALUES ((SELECT id FROM vehicles WHERE plate_number='ABC 1234' LIMIT 1),
'RFID-ABC1234',
'windshield',
NOW(),
'active');

INSERT INTO visitor_requests (
resident_id,
house_number,
visitor_name,
contact_number,
plate_number,
vehicle_type,
purpose_of_visit,
status,
requested_visit_date,
requested_arrival_time,
qr_reference
) VALUES
(@resident_id,
'12-A',
'Daniel Cruz',
'09181112222',
'KBA 9090',
'car',
'Family visit',
'pending',
CURDATE(),
'14:00:00',
'GH-REQ-0001'),
(@resident_id,
'12-A',
'Mika Santos',
'09183334444',
'NQZ 2211',
'motorcycle',
'Delivery',
'approved',
CURDATE(),
'15:00:00',
'GH-REQ-0002');

INSERT INTO concerns (resident_id,
sender_name,
sender_role,
house_number,
subject,
message,
status)
VALUES (@resident_id,
'Golden Resident',
'resident',
'12-A',
'Streetlight is flickering',
'The streetlight near our gate has been flickering since last night.',
'open');

INSERT INTO notifications (user_id,
title,
message,
is_read)
VALUES
(@resident_user_id,
'Visitor pending',
'You have a pending visitor request for House 12-A.',
0),
(@admin_user_id,
'New concern',
'A resident submitted a new concern.',
0),
(@guard_user_id,
'Guard notice',
'Your dashboard is ready.',
0);

-- BISM4RCK/KUN3H0 2026

-- CUMULATIVE FEATURE SUPPORT
-- Super admin and staff RFID vehicle support.
-- README intentionally untouched.
CREATE TABLE IF NOT EXISTS visitor_credentials(
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
visitor_request_id BIGINT UNSIGNED NOT NULL,
visitor_id CHAR(6) NOT NULL,
qr_token_hash CHAR(64) NOT NULL,
barcode_token_hash CHAR(64) NOT NULL,
qr_token VARCHAR(255) NOT NULL,
barcode_token VARCHAR(255) NOT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
UNIQUE KEY uq_vid(visitor_id),
UNIQUE KEY uq_vr(visitor_request_id),
UNIQUE KEY uq_qr(qr_token_hash),
UNIQUE KEY uq_barcode(barcode_token_hash),
CONSTRAINT fk_vc_request FOREIGN KEY(visitor_request_id) REFERENCES visitor_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS account_activity_logs(
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NULL,
account_type VARCHAR(20) NOT NULL,
account_identifier VARCHAR(120) NULL,
action VARCHAR(80) NOT NULL,
details TEXT NULL,
ip_address VARCHAR(45) NULL,
user_agent TEXT NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
KEY idx_type(account_type),
KEY idx_user(user_id),
KEY idx_action(action),
KEY idx_created(created_at),
CONSTRAINT fk_activity_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gate_commands(
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
issued_by BIGINT UNSIGNED NULL,
issued_by_role VARCHAR(20) NULL,
command VARCHAR(40) NOT NULL,
source VARCHAR(40) NOT NULL,
payload JSON NULL,
status ENUM('pending',
'completed',
'denied',
'expired') NOT NULL DEFAULT 'pending',
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
completed_at DATETIME NULL,
KEY idx_status(status),
KEY idx_created(created_at),
CONSTRAINT fk_gate_command_user FOREIGN KEY(issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_vehicles(
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
user_id BIGINT UNSIGNED NOT NULL,
plate_number VARCHAR(32) NOT NULL,
vehicle_type VARCHAR(50) NULL,
color VARCHAR(50) NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
KEY idx_user(user_id),
KEY idx_plate(plate_number),
CONSTRAINT fk_user_vehicle_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (full_name,
email,
password,
role,
status)
SELECT 'Golden Resident Two', 'resident2@goldenhomes.local', password, 'resident', 'active' FROM users WHERE email='resident@goldenhomes.local' LIMIT 1;

SET @resident2_user_id = (SELECT id FROM users WHERE email='resident2@goldenhomes.local' LIMIT 1);

INSERT INTO residents (user_id,
house_number,
block_number,
lot_number,
household_letter,
contact_number)
VALUES (@resident2_user_id,
'15-7-B',
'15',
'7',
'B',
'09171234568');

SET @resident2_id = (SELECT id FROM residents WHERE user_id=@resident2_user_id LIMIT 1);

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color) VALUES
(@resident2_id,
'DEF 2468',
'car',
'Mitsubishi',
'Mirage',
'Silver'),
(@resident2_id,
'GHI 1357',
'motorcycle',
'Yamaha',
'Mio',
'Blue');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @guard_user_id, 'GRD 1001', 'car', 'Black' WHERE NOT EXISTS (SELECT 1 FROM user_vehicles WHERE user_id=@guard_user_id AND plate_number='GRD 1001');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @guard_user_id, 'GRD 2002', 'motorcycle', 'Red' WHERE NOT EXISTS (SELECT 1 FROM user_vehicles WHERE user_id=@guard_user_id AND plate_number='GRD 2002');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @admin_user_id, 'ADM 3003', 'car', 'White' WHERE NOT EXISTS (SELECT 1 FROM user_vehicles WHERE user_id=@admin_user_id AND plate_number='ADM 3003');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @admin_user_id, 'ADM 4004', 'motorcycle', 'Gray' WHERE NOT EXISTS (SELECT 1 FROM user_vehicles WHERE user_id=@admin_user_id AND plate_number='ADM 4004');

INSERT INTO visitor_credentials (visitor_request_id,
visitor_id,
qr_token_hash,
barcode_token_hash,
qr_token,
barcode_token) VALUES
((SELECT id FROM visitor_requests WHERE qr_reference='GH-REQ-0001' LIMIT 1),
'DEMO01',
SHA2('GHQR-DEMO01',
256),
SHA2('GHBC-DEMO01',
256),
'GHQR-DEMO01',
'GHBC-DEMO01'),
((SELECT id FROM visitor_requests WHERE qr_reference='GH-REQ-0002' LIMIT 1),
'DEMO02',
SHA2('GHQR-DEMO02',
256),
SHA2('GHBC-DEMO02',
256),
'GHQR-DEMO02',
'GHBC-DEMO02');

-- BISM4RCK-KUN3H0 2026

-- RFID and gate-reader limits / reader location.
-- RFID resident vehicle association.
-- Demo expansion and protected KUN3H0 super admin.
INSERT INTO users (full_name, email, password, role, status, is_super_admin)
SELECT 'KUN3H0', 'kun3h0@goldenhomes.local', '$2y$12$aWCsIYAvqFexuRYPx.IiaOKsITxZjy/V1b2Vx7gRto45pUZpams/q', 'admin', 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE is_super_admin=1);

SET @kun3h0_user_id=(SELECT id FROM users WHERE is_super_admin=1 LIMIT 1);

INSERT INTO admins (user_id,
admin_code)
SELECT @kun3h0_user_id, 'KUN3H0'
WHERE @kun3h0_user_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM admins WHERE user_id=@kun3h0_user_id);

-- Additional demo residents.
INSERT INTO users (full_name,email,password,role,status)
SELECT 'Golden Resident Three','resident3@goldenhomes.local',password,'resident','active'
FROM users WHERE email='resident@goldenhomes.local'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='resident3@goldenhomes.local') LIMIT 1;

INSERT INTO users (full_name,
email,
password,
role,
status)
SELECT 'Golden Resident Four','resident4@goldenhomes.local',password,'resident','active'
FROM users WHERE email='resident@goldenhomes.local'
AND NOT EXISTS (SELECT 1 FROM users WHERE email='resident4@goldenhomes.local') LIMIT 1;

INSERT INTO users (full_name,
email,
password,
role,
status)
SELECT 'Golden Resident Five','resident5@goldenhomes.local',password,'resident','active'
FROM users WHERE email='resident@goldenhomes.local'
AND NOT EXISTS (SELECT 1 FROM users WHERE email='resident5@goldenhomes.local') LIMIT 1;

SET @resident3_user_id=(SELECT id FROM users WHERE email='resident3@goldenhomes.local' LIMIT 1);

SET @resident4_user_id=(SELECT id FROM users WHERE email='resident4@goldenhomes.local' LIMIT 1);

SET @resident5_user_id=(SELECT id FROM users WHERE email='resident5@goldenhomes.local' LIMIT 1);

INSERT INTO residents (user_id,
house_number,
block_number,
lot_number,
household_letter,
contact_number)
SELECT @resident3_user_id,'3-9-C','3','9','C','09170003003'
WHERE @resident3_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM residents WHERE user_id=@resident3_user_id);

INSERT INTO residents (user_id,
house_number,
block_number,
lot_number,
household_letter,
contact_number)
SELECT @resident4_user_id,'8-2-D','8','2','D','09170004004'
WHERE @resident4_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM residents WHERE user_id=@resident4_user_id);

INSERT INTO residents (user_id,
house_number,
block_number,
lot_number,
household_letter,
contact_number)
SELECT @resident5_user_id,'20-11-E','20','11','E','09170005005'
WHERE @resident5_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM residents WHERE user_id=@resident5_user_id);

SET @resident3_id=(SELECT id FROM residents WHERE user_id=@resident3_user_id LIMIT 1);

SET @resident4_id=(SELECT id FROM residents WHERE user_id=@resident4_user_id LIMIT 1);

SET @resident5_id=(SELECT id FROM residents WHERE user_id=@resident5_user_id LIMIT 1);

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident3_id,'RES3 1001','car','Toyota','Wigo','Pearl White'
WHERE @resident3_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES3 1001');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident3_id,'RES3 1002','motorcycle','Honda','Click','Matte Black'
WHERE @resident3_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES3 1002');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident3_id,'RES3 1003','car','Suzuki','Dzire','Ocean Blue'
WHERE @resident3_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES3 1003');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident4_id,'RES4 2001','car','Honda','City','Ruby Red'
WHERE @resident4_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES4 2001');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident5_id,'RES5 3001','car','Toyota','Fortuner','Midnight Blue'
WHERE @resident5_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES5 3001');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident5_id,'RES5 3002','motorcycle','Yamaha','NMAX','Graphite Gray'
WHERE @resident5_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES5 3002');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident5_id,'RES5 3003','car','Mitsubishi','Xpander','Forest Green'
WHERE @resident5_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES5 3003');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident5_id,'RES5 3004','car','Kia','Seltos','Champagne Gold'
WHERE @resident5_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES5 3004');

INSERT INTO vehicles (resident_id,
plate_number,
vehicle_type,
brand,
model,
color)
SELECT @resident5_id,'RES5 3005','motorcycle','Honda','ADV 160','Pearl Silver'
WHERE @resident5_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM vehicles WHERE plate_number='RES5 3005');

-- Additional demo staff.
INSERT INTO users (full_name,email,password,role,status)
SELECT 'Gate Guard Two','guard2@goldenhomes.local',password,'guard','active'
FROM users WHERE email='guard@goldenhomes.local'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email='guard2@goldenhomes.local') LIMIT 1;

INSERT INTO users (full_name,
email,
password,
role,
status)
SELECT 'Gate Guard Three','guard3@goldenhomes.local',password,'guard','active'
FROM users WHERE email='guard@goldenhomes.local'
AND NOT EXISTS (SELECT 1 FROM users WHERE email='guard3@goldenhomes.local') LIMIT 1;

INSERT INTO users (full_name,
email,
password,
role,
status)
SELECT 'Subdivision Admin Two','admin2@goldenhomes.local',password,'admin','active'
FROM users WHERE email='admin@goldenhomes.local'
AND NOT EXISTS (SELECT 1 FROM users WHERE email='admin2@goldenhomes.local') LIMIT 1;

SET @guard2_user_id=(SELECT id FROM users WHERE email='guard2@goldenhomes.local' LIMIT 1);

SET @guard3_user_id=(SELECT id FROM users WHERE email='guard3@goldenhomes.local' LIMIT 1);

SET @admin2_user_id=(SELECT id FROM users WHERE email='admin2@goldenhomes.local' LIMIT 1);

INSERT INTO guards (user_id,
guard_code,
shift_name)
SELECT @guard2_user_id,'GRD-002','Night Shift'
WHERE @guard2_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM guards WHERE user_id=@guard2_user_id);

INSERT INTO guards (user_id,
guard_code,
shift_name)
SELECT @guard3_user_id,'GRD-003','Day Shift'
WHERE @guard3_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM guards WHERE user_id=@guard3_user_id);

INSERT INTO admins (user_id,
admin_code)
SELECT @admin2_user_id,'ADM-002'
WHERE @admin2_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM admins WHERE user_id=@admin2_user_id);

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @guard2_user_id,'GRD2 1001','car','Navy Blue'
WHERE @guard2_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_vehicles WHERE plate_number='GRD2 1001');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @guard3_user_id,'GRD3 1001','motorcycle','Sunset Orange'
WHERE @guard3_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_vehicles WHERE plate_number='GRD3 1001');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @guard3_user_id,'GRD3 1002','car','Pearl White'
WHERE @guard3_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_vehicles WHERE plate_number='GRD3 1002');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @admin2_user_id,'ADM2 1001','car','Steel Gray'
WHERE @admin2_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_vehicles WHERE plate_number='ADM2 1001');

INSERT INTO user_vehicles (user_id,
plate_number,
vehicle_type,
color)
SELECT @admin2_user_id,'ADM2 1002','motorcycle','Deep Red'
WHERE @admin2_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM user_vehicles WHERE plate_number='ADM2 1002');

-- BISM4RCK-KUN3H0 2026


-- Demo credentials normalized for Smart Gate v47.
UPDATE users SET password='$2y$12$U/fVbHa5pOjlBTwFEP9.fOmxj4rdMaQkrIf2cxidLrNDNLBjEpgve' WHERE email IN (
    'resident@goldenhomes.local',
    'resident2@goldenhomes.local',
    'resident3@goldenhomes.local',
    'resident4@goldenhomes.local',
    'resident5@goldenhomes.local'
);

UPDATE users SET password='$2y$12$93sScaYynNzoUYKKjXl9k.5fhPqrnWjnv62DtNILLDVelcCGwiNfu' WHERE email IN (
    'guard@goldenhomes.local',
    'guard2@goldenhomes.local',
    'guard3@goldenhomes.local'
);

UPDATE users SET password='$2y$12$/Q0DaexlZwrfktGy8rYnL.WHXk8i1DV8DwUS5rENAiinXOXzUXgga' WHERE email IN (
    'admin@goldenhomes.local',
    'admin2@goldenhomes.local'
);

INSERT INTO users (full_name, email, password, role, status, is_super_admin)
SELECT 'KUN3H0', 'kun3h0@goldenhomes.local', '$2y$12$ecYq3mgF9q69SkoyUqrzpe0ZCPJL5hrl6No.ETei5mjm17YdXTaDO', 'admin', 'active', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='kun3h0@goldenhomes.local');

UPDATE users
SET full_name='KUN3H0', role='admin', status='active', is_super_admin=1, password='$2y$12$ecYq3mgF9q69SkoyUqrzpe0ZCPJL5hrl6No.ETei5mjm17YdXTaDO'
WHERE email='kun3h0@goldenhomes.local';
