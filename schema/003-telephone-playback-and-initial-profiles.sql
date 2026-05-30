CREATE TABLE telephone_numbers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_number VARCHAR(32) NOT NULL UNIQUE,
    area_code VARCHAR(10) NULL,
    central_office_code VARCHAR(10) NULL,
    station_code VARCHAR(10) NULL,
    display_number VARCHAR(32) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_reserved TINYINT(1) NOT NULL DEFAULT 0,
    reserved_by_user_id INT UNSIGNED NULL,
    reserved_at DATETIME NULL,
    published_physical_directory TINYINT(1) NOT NULL DEFAULT 0,
    published_web_directory TINYINT(1) NOT NULL DEFAULT 0,
    is_real_contact_number TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_telephone_numbers_user
        FOREIGN KEY (reserved_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_telephone_numbers_area_code (area_code),
    INDEX idx_telephone_numbers_central_office_code (central_office_code),
    INDEX idx_telephone_numbers_reserved (is_reserved),
    INDEX idx_telephone_numbers_display (display_number)
);

CREATE TABLE playback_modes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO playback_modes (code, label, description, sort_order) VALUES
('simple_file', 'Play Single File', 'Play one audio file', 10),
('random_files', 'Play Random File', 'Play one random file from selected files', 20),
('time_of_day', 'Time-Based Playback', 'Play selected files depending on schedule', 30),
('phone_tree', 'Phone Tree / IVR', 'Present menu options and route based on keypad input', 40),
('forward_number', 'Forward to Another Number', 'Route to another PBX number', 50),
('post_audio_options', 'Options After Playback', 'Offer follow-up options after playback ends', 60),
('voicemail_box', 'Voice Mailbox', 'Allow message retrieval and mailbox behavior', 70);

CREATE TABLE number_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telephone_number_id BIGINT UNSIGNED NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    playback_mode_id INT UNSIGNED NOT NULL,
    listing_text VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    is_voicemail_box TINYINT(1) NOT NULL DEFAULT 0,
    voicemail_pin_hash VARCHAR(255) NULL,
    forward_to_number_id BIGINT UNSIGNED NULL,
    publish_physical_directory TINYINT(1) NOT NULL DEFAULT 0,
    publish_web_directory TINYINT(1) NOT NULL DEFAULT 0,
    explicit_content_flag TINYINT(1) NOT NULL DEFAULT 0,
    family_friendly_version_available TINYINT(1) NOT NULL DEFAULT 0,
    business_category_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_number_profiles_number
        FOREIGN KEY (telephone_number_id) REFERENCES telephone_numbers(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_number_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_number_profiles_playback_mode
        FOREIGN KEY (playback_mode_id) REFERENCES playback_modes(id),
    CONSTRAINT fk_number_profiles_forward
        FOREIGN KEY (forward_to_number_id) REFERENCES telephone_numbers(id)
        ON DELETE SET NULL,
    INDEX idx_number_profiles_user (user_id),
    INDEX idx_number_profiles_status (status),
    INDEX idx_number_profiles_business_category (business_category_id)
);

CREATE TABLE number_profile_audio_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number_profile_id BIGINT UNSIGNED NOT NULL,
    audio_file_id BIGINT UNSIGNED NOT NULL,
    role_code VARCHAR(50) NOT NULL DEFAULT 'primary',
    sort_order INT NOT NULL DEFAULT 0,
    start_time TIME NULL,
    end_time TIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_number_profile_audio_profile
        FOREIGN KEY (number_profile_id) REFERENCES number_profiles(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_number_profile_audio_file
        FOREIGN KEY (audio_file_id) REFERENCES audio_files(id)
        ON DELETE CASCADE,
    INDEX idx_number_profile_audio_profile (number_profile_id),
    INDEX idx_number_profile_audio_role (role_code)
);

CREATE TABLE business_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE number_listing_ads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number_profile_id BIGINT UNSIGNED NOT NULL UNIQUE,
    original_filename VARCHAR(255) NULL,
    stored_filename VARCHAR(255) NULL,
    converted_filename VARCHAR(255) NULL,
    relative_path VARCHAR(500) NULL,
    converted_relative_path VARCHAR(500) NULL,
    mime_type VARCHAR(100) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    conversion_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    conversion_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_number_listing_ads_profile
        FOREIGN KEY (number_profile_id) REFERENCES number_profiles(id)
        ON DELETE CASCADE
);

ALTER TABLE number_profiles
ADD COLUMN impersonation_warning_acknowledged TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO business_categories (name, description, sort_order) VALUES
('Toys', 'Toy stores and hobby shops', 10),
('Auto Repair', 'Automotive services and repair shops', 20),
('Restaurants', 'Food and dining establishments', 30),
('Lodging', 'Hotels, motels, and accommodations', 40),
('Entertainment', 'Attractions, shows, and recreation', 50),
('Retail', 'General retail stores', 60),
('Services', 'Professional and personal services', 70),
('Community', 'Local organizations and community groups', 80),
('Government', 'Public offices and services', 90),
('Education', 'Schools and educational services', 100),
('Health', 'Medical and wellness services', 110),
('Emergency', 'Emergency-related listings (non-operational)', 120);

DELIMITER $$

CREATE PROCEDURE seed_numbers()
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 200 DO
        INSERT INTO telephone_numbers (
            full_number,
            area_code,
            central_office_code,
            station_code,
            display_number
        )
        VALUES (
            CONCAT('540555', LPAD(i,4,'0')),
            '540',
            '555',
            LPAD(i,4,'0'),
            CONCAT('(540) 555-', LPAD(i,4,'0'))
        );

        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

CALL seed_numbers();
DROP PROCEDURE seed_numbers;

INSERT INTO telephone_numbers (full_number, area_code, central_office_code, station_code, display_number)
VALUES
('5405551212','540','555','1212','(540) 555-1212'), -- directory vibe
('5405550000','540','555','0000','(540) 555-0000'),
('5405559999','540','555','9999','(540) 555-9999'),
('5405551234','540','555','1234','(540) 555-1234'),
('5405557777','540','555','7777','(540) 555-7777');

ALTER TABLE telephone_numbers
ADD COLUMN is_sensitive TINYINT(1) DEFAULT 0;

UPDATE telephone_numbers
SET is_sensitive = 1
WHERE central_office_code != '555';

INSERT INTO number_profiles (
    telephone_number_id,
    user_id,
    playback_mode_id,
    listing_text,
    publish_web_directory,
    status
)
VALUES (
    1,
    1,
    1,
    'Test Listing – Hello World',
    1,
    'active'
);

