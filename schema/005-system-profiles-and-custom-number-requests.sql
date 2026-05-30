INSERT IGNORE INTO telephone_numbers (
    full_number, number_format, area_code, central_office_code, station_code,
    display_number, is_active, is_reserved
) VALUES
('5405550000', 'full', '540', '555', '0000', '(540) 555-0000', 1, 1),
('5405551212', 'full', '540', '555', '1212', '(540) 555-1212', 1, 1),
('5405551234', 'full', '540', '555', '1234', '(540) 555-1234', 1, 1),
('5405552368', 'full', '540', '555', '2368', '(540) 555-2368', 1, 1),
('5405558675', 'full', '540', '555', '8675', '(540) 555-8675', 1, 0),

('8675309', 'short', NULL, NULL, NULL, '867-5309', 1, 1),
('5551212', 'short', NULL, NULL, NULL, '555-1212', 1, 1),
('5551234', 'short', NULL, NULL, NULL, '555-1234', 1, 1),
('5550000', 'short', NULL, NULL, NULL, '555-0000', 1, 1),
('5552368', 'short', NULL, NULL, NULL, '555-2368', 1, 1);

INSERT INTO number_profiles (
    telephone_number_id,
    user_id,
    number_type_id,
    playback_mode_id,
    listing_text,
    status
)
SELECT
    tn.id,
    1,
    nt.id,
    pm.id,
    CASE tn.full_number
        WHEN '0' THEN 'Operator'
        WHEN '411' THEN 'Directory Assistance'
        WHEN '611' THEN 'Service / Support'
        WHEN '711' THEN 'Relay / Accessibility'
        WHEN '811' THEN 'Utilities / Call Before You Dig'
        WHEN '911' THEN 'Emergency / Not for Actual Emergencies'
        WHEN '5405550000' THEN 'Main Line'
        WHEN '5405551212' THEN 'Directory Assistance'
        WHEN '5405551234' THEN 'Common Fake Number'
        WHEN '5405552368' THEN 'Ghostbusters'
        WHEN '8675309' THEN 'Jenny'
        WHEN '5551212' THEN 'Directory Assistance'
        WHEN '5551234' THEN 'Common Fake Number'
        WHEN '5550000' THEN 'Main Line'
        WHEN '5552368' THEN 'Ghostbusters'
        ELSE CONCAT('System Number ', tn.display_number)
    END,
    'active'
FROM telephone_numbers tn
JOIN number_types nt ON nt.code = 'system_number'
JOIN playback_modes pm ON pm.code = 'simple_file'
WHERE tn.full_number IN (
    '0','411','611','711','811','911',
    '5405550000','5405551212','5405551234','5405552368',
    '8675309','5551212','5551234','5550000','5552368'
)
AND NOT EXISTS (
    SELECT 1
    FROM number_profiles np
    WHERE np.telephone_number_id = tn.id
);

INSERT INTO number_profiles (
    telephone_number_id,
    user_id,
    number_type_id,
    playback_mode_id,
    listing_text,
    publish_physical_directory,
    publish_web_directory,
    status
)
SELECT
    tn.id,
    1,
    nt.id,
    pm.id,
    'Gallery Commentary',
    1,
    1,
    'draft'
FROM telephone_numbers tn
JOIN number_types nt ON nt.code = 'art_commentary'
JOIN playback_modes pm ON pm.code = 'simple_file'
WHERE tn.full_number = '1000'
AND NOT EXISTS (
    SELECT 1 FROM number_profiles np WHERE np.telephone_number_id = tn.id
);

INSERT INTO number_profiles (
    telephone_number_id,
    user_id,
    number_type_id,
    playback_mode_id,
    listing_text,
    publish_physical_directory,
    publish_web_directory,
    status
)
SELECT
    tn.id,
    1,
    nt.id,
    pm.id,
    'Public Voicemail Chain',
    1,
    1,
    'draft'
FROM telephone_numbers tn
JOIN number_types nt ON nt.code = 'public_voicemail'
JOIN playback_modes pm ON pm.code = 'voicemail_box'
WHERE tn.full_number = '1001'
AND NOT EXISTS (
    SELECT 1 FROM number_profiles np WHERE np.telephone_number_id = tn.id
);

INSERT INTO number_profiles (
    telephone_number_id,
    user_id,
    number_type_id,
    playback_mode_id,
    listing_text,
    publish_physical_directory,
    publish_web_directory,
    status
)
SELECT
    tn.id,
    1,
    nt.id,
    pm.id,
    'Riddle Hotline',
    0,
    0,
    'draft'
FROM telephone_numbers tn
JOIN number_types nt ON nt.code = 'puzzle'
JOIN playback_modes pm ON pm.code = 'phone_tree'
WHERE tn.full_number = '1002'
AND NOT EXISTS (
    SELECT 1 FROM number_profiles np WHERE np.telephone_number_id = tn.id
);

ALTER TABLE telephone_numbers
ADD INDEX idx_telephone_numbers_format_reserved (number_format, is_reserved, is_active),
ADD INDEX idx_telephone_numbers_area_exchange (area_code, central_office_code, is_reserved, is_active);

CREATE TABLE custom_number_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    requested_number VARCHAR(32) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_custom_number_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_custom_number_requests_reviewer
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_custom_number_requests_status (status),
    INDEX idx_custom_number_requests_requested_number (requested_number)
);

ALTER TABLE number_profiles
ADD COLUMN custom_requested_number VARCHAR(32) NULL AFTER telephone_number_id,
ADD COLUMN custom_request_notes TEXT NULL AFTER custom_requested_number,
ADD COLUMN number_assignment_status VARCHAR(30) NOT NULL DEFAULT 'assigned' AFTER custom_request_notes;

ALTER TABLE number_profiles
MODIFY COLUMN telephone_number_id BIGINT UNSIGNED NULL;

ALTER TABLE number_profiles
MODIFY COLUMN number_type_id INT UNSIGNED NULL,
MODIFY COLUMN playback_mode_id INT UNSIGNED NULL;
