CREATE DATABASE IF NOT EXISTS phone_exhibits CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phone_exhibits;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_resets_user_id (user_id),
    INDEX idx_password_resets_token_hash (token_hash),
    INDEX idx_password_resets_expires_at (expires_at)
) ENGINE=InnoDB;

CREATE TABLE audio_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    relative_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_ext VARCHAR(20) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    duration_seconds DECIMAL(10,3) NULL,
    audio_format VARCHAR(50) NULL,
    audio_type VARCHAR(100) NULL,
    channels TINYINT UNSIGNED NULL,
    channel_mode VARCHAR(20) NULL,
    sample_rate_hz INT UNSIGNED NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_audio_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_audio_files_user_id (user_id),
    INDEX idx_audio_files_created_at (created_at),
    INDEX idx_audio_files_original_filename (original_filename)
) ENGINE=InnoDB;

ALTER TABLE audio_files
    ADD COLUMN converted_filename VARCHAR(255) NULL AFTER stored_filename,
    ADD COLUMN converted_relative_path VARCHAR(500) NULL AFTER relative_path,
    ADD COLUMN converted_mime_type VARCHAR(100) NULL AFTER mime_type,
    ADD COLUMN converted_file_ext VARCHAR(20) NULL AFTER file_ext,
    ADD COLUMN converted_file_size_bytes BIGINT UNSIGNED NULL AFTER file_size_bytes,
    ADD COLUMN converted_duration_seconds DECIMAL(10,3) NULL AFTER duration_seconds,
    ADD COLUMN converted_audio_format VARCHAR(50) NULL AFTER audio_format,
    ADD COLUMN converted_audio_type VARCHAR(100) NULL AFTER audio_type,
    ADD COLUMN converted_channels TINYINT UNSIGNED NULL AFTER channels,
    ADD COLUMN converted_channel_mode VARCHAR(20) NULL AFTER channel_mode,
    ADD COLUMN converted_sample_rate_hz INT UNSIGNED NULL AFTER sample_rate_hz,
    ADD COLUMN conversion_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER updated_at,
    ADD COLUMN conversion_error TEXT NULL AFTER conversion_status;

ALTER TABLE users
ADD COLUMN agreed_to_terms_at DATETIME NULL;

CREATE TABLE terms_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
ADD COLUMN agreed_terms_version VARCHAR(20) NULL,
ADD COLUMN last_terms_seen_at DATETIME NULL;

INSERT INTO terms_versions (version, content, is_active)
VALUES (
'1.0',
'Audio Contribution and Public Exhibition Agreement

By creating an account or uploading audio content through this system, you agree to the following terms:

1. Description of Exhibit
This system is part of a public exhibit consisting of analog telephones connected to a private branch exchange (PBX). Participants interact with the exhibit by dialing telephone numbers using various devices, including but not limited to rotary, crank, cordless, and touch-tone telephones connected via VoIP hardware.

2. Use of Submitted Audio
Audio files submitted through this system may be assigned to telephone numbers and made publicly accessible through the exhibit. Numbers may be published in directories or distributed through other means. Number selection or assignment is not guaranteed.

3. Grant of Rights
By submitting audio content, you grant Lewis Edward Moten III a non-exclusive, perpetual, irrevocable, royalty-free license to use, reproduce, publicly perform, broadcast, and display the audio as part of this exhibit and any related demonstrations or presentations.

4. Media and Recording
You acknowledge that demonstrations of the exhibit may be recorded, photographed, or broadcast by Lewis Edward Moten III or third parties, including local media. Your submitted audio may be included in such recordings.

5. Audio Processing
You understand and agree that submitted audio will be processed and converted into a telephone-compatible format, specifically 8 kHz, 16-bit, mono PCM WAV, and may be filtered to a frequency range of approximately 300–3000 Hz, resulting in reduced fidelity consistent with analog telephone systems.

6. Representations and Warranties
You represent and warrant that you have the legal right to submit the audio content and that it does not infringe upon the rights of any third party.

7. Acceptance
By creating an account, logging in, or uploading content, you acknowledge that you have read, understood, and agree to these terms.',
1
);

UPDATE terms_versions
SET is_active = 0
WHERE is_active = 1;

INSERT INTO terms_versions (version, content, is_active)
VALUES (
    '1.1',
    'Audio Contribution and Public Exhibition Agreement

By creating an account, logging in, submitting audio content, or otherwise using this system, you agree to the following terms:

1. Description of Exhibit
This system is part of a public exhibit consisting of analog telephones connected to a private branch exchange (PBX). Participants may interact with the exhibit by dialing telephone numbers using various devices, including but not limited to rotary, crank, cordless, touch-tone, and, where applicable, payphone-style telephones connected directly or indirectly through VoIP hardware or related telecommunications equipment.

2. Use of Submitted Audio
Audio files submitted through this system may be assigned to telephone numbers and made publicly accessible through the exhibit. Numbers may be published in a directory, phone book, placard, exhibit materials, or distributed through other means. A user may request or choose a custom number, but number selection, assignment, and continued availability are not guaranteed.

3. Grant of Rights
By submitting audio content, you grant Lewis Edward Moten III a non-exclusive, perpetual, irrevocable, worldwide, royalty-free license to use, reproduce, process, publicly perform, broadcast, transmit, demonstrate, display, and otherwise present the audio as part of this exhibit and any related demonstration, presentation, installation, or public event.

4. Media and Recording
You acknowledge that demonstrations or exhibitions involving this system may be recorded, photographed, filmed, streamed, or otherwise documented by Lewis Edward Moten III, hosting organizations, local media, or other third parties. Your submitted audio may be audible or otherwise included in such documentation.

5. Audio Processing and Fidelity Limitation
You understand and agree that submitted audio may be processed and converted into a telephone-compatible format, including but not limited to 8 kHz, 16-bit, mono PCM WAV, and may be filtered to a frequency range of approximately 300–3000 Hz or similar parameters that reduce fidelity in order to simulate or match the characteristics of analog telephone systems and related playback equipment.

6. User Responsibility and Rights Clearance
You represent and warrant that you have the legal right to submit the audio content, that the content does not infringe or violate the rights of any third party, and that the submission, public playback, and related use of the content as described in this agreement are authorized by all necessary rights holders.

7. Number Assignment and Availability
Telephone numbers associated with submitted audio are subject to availability, technical limitations, exhibit design, administrative decisions, and future changes. Assignment of a requested number is not guaranteed, may be temporary, and may be modified or withdrawn at any time.

8. Payphone Use and Collected Funds
You understand and agree that the exhibit may eventually include one or more payphones or similar devices that require the use of coins, currency, tokens, or other payment in order to place a call to the PBX and access audio content. Any funds collected through such use may, at the sole discretion of Lewis Edward Moten III or the hosting organization, be donated to a nonprofit or gallery hosting the exhibit, returned to the user, or applied toward the operation, repair, preservation, or maintenance of the exhibit. No user is guaranteed a refund, reimbursement, or return of funds unless expressly stated at the time of use.

9. Modification and Administration of the Exhibit
Lewis Edward Moten III reserves the right to modify, suspend, relocate, reconfigure, maintain, repair, or discontinue any part of the exhibit, including the technical method of playback, the devices used, the numbering scheme, or the method by which audio is accessed or distributed.

10. Acceptance
By creating an account, logging in, submitting content, or continuing to use this system, you acknowledge that you have read, understood, and agree to these terms.',
    1
);

UPDATE terms_versions SET is_active = 0 WHERE is_active = 1;

INSERT INTO terms_versions (version, content, is_active)
VALUES ('1.2', 'Audio Contribution and Public Exhibition Agreement

By creating an account, logging in, submitting audio or other content, or otherwise using this system, you agree to the following terms:

1. Description of Exhibit
This system is part of a public exhibit consisting of analog telephones connected to a private branch exchange (PBX). Participants may interact with the exhibit by dialing telephone numbers using various devices, including but not limited to rotary, crank, cordless, touch-tone, and, where applicable, payphone-style telephones connected directly or indirectly through VoIP hardware or related telecommunications equipment.

2. User Submissions and Participation
Audio files and other content submitted through this system may be assigned to telephone numbers and made publicly accessible through the exhibit. Numbers may be published in directories, phone books, placards, exhibit materials, or distributed through other means. Users may request or choose custom numbers; however, number selection, assignment, and continued availability are not guaranteed.

3. Grant of Rights
By submitting content, including audio and images, you grant Lewis Edward Moten III a non-exclusive, perpetual, irrevocable, worldwide, royalty-free license to use, reproduce, process, modify, publicly perform, broadcast, transmit, demonstrate, display, and otherwise present such content as part of this exhibit and any related demonstration, presentation, installation, or public event.

4. Media and Recording
You acknowledge that demonstrations or exhibitions involving this system may be recorded, photographed, filmed, streamed, or otherwise documented by Lewis Edward Moten III, hosting organizations, local media, or other third parties. Your submitted content may be audible or otherwise included in such documentation.

5. Uploaded Media and Image Rights
If you upload images, graphics, or other media for use within a directory, listing, or exhibit context, you represent that you have all necessary rights to do so and grant Lewis Edward Moten III a non-exclusive, royalty-free license to use, reproduce, display, and distribute such media as part of the exhibit and related materials.

6. No Compensation
You acknowledge that you will not receive compensation, royalties, or other payment for the use, modification, or presentation of your submitted content unless expressly agreed to in writing.

7. Audio Processing and Transformation
You understand and agree that submitted audio may be processed and converted into a telephone-compatible format, including but not limited to 8 kHz, 16-bit, mono PCM WAV, and may be filtered to a frequency range of approximately 300–3000 Hz or similar parameters that reduce fidelity in order to simulate or match the characteristics of analog telephone systems and related playback equipment.

8. Transcription, Analysis, and Content Classification
You acknowledge and agree that submitted content may be processed using automated or assisted technologies, including but not limited to artificial intelligence or machine learning systems, for purposes such as transcription, indexing, searchability, moderation, and classification of content.

Such processes may be used to determine whether content is appropriate for public or family-friendly environments or whether it may require content advisories or restrictions. These determinations may not be accurate or complete. You remain responsible for accurately indicating whether your submitted content contains explicit or restricted material.

9. Content Variants and Family-Friendly Modifications
Submitted content may be edited, filtered, or modified to create alternate versions for different audiences or exhibit contexts, including family-friendly versions. Modifications may include muting, removal, substitution, or insertion of tones (such as beeps) or other indicators in place of explicit material. Multiple versions may exist and may be associated with different telephone numbers, access paths, or playback conditions.

10. Content Segmentation and Sequential Playback
Submitted content may be divided into segments for usability, navigation, technical constraints, or exhibit design. These segments may be arranged, reordered, or distributed across one or more telephone numbers or access points. The system may allow listeners to access content non-linearly, including continuing playback from a later segment by dialing the same or a different number at a later time.

11. Number Assignment and Availability
Telephone numbers associated with submitted content are subject to availability, technical limitations, exhibit design, administrative decisions, and future changes. Assignment of a requested number is not guaranteed and may be temporary or modified at any time.

12. Payphone Use and Collected Funds
The exhibit may include payphones or similar devices that require coins, currency, tokens, or other payment. Any funds collected may, at the sole discretion of Lewis Edward Moten III or the hosting organization, be donated to a nonprofit or gallery, returned to users, or used for maintenance or operation of the exhibit. No refund is guaranteed unless explicitly stated.

13. System Operation and Modification
Lewis Edward Moten III reserves the right to modify, suspend, relocate, reconfigure, maintain, repair, or discontinue any part of the exhibit, including playback methods, devices, numbering schemes, and access mechanisms.

14. Testing and Staging Systems
Submitted content may be processed, stored, and played on secondary or staging systems, including testing PBX environments, to verify functionality and compatibility before deployment.

15. Storage, Transmission, and Future Access
Content may be stored, transmitted electronically, or physically transferred for operation and maintenance of the system. While the system is not currently accessible via public networks or the internet, future versions may allow broader public access through web or telecommunications systems.

16. Administration, Review, and Moderation
Lewis Edward Moten III and authorized administrators may access, review, listen to, evaluate, modify, edit, reformat, analyze, transcribe, classify, or remove content at their sole discretion for moderation, quality control, technical compatibility, and exhibit curation.

17. Content Removal and Takedown
Content may be removed, restricted, or refused publication at any time, with or without notice, for any reason, including legal concerns, policy violations, or curation decisions. No guarantee is made that submitted content will be published or remain available.

18. User Responsibilities and Rights Clearance
You represent and warrant that you have the legal right to submit all content and that it does not infringe upon the rights of any third party. You are responsible for obtaining all necessary permissions, licenses, and consents.

19. Legal Capacity
You represent that you are at least eighteen (18) years of age or otherwise have the legal capacity to enter into this agreement, or that you are using the system with appropriate parental or guardian consent.

20. Account Responsibility
You are responsible for maintaining the confidentiality of your account credentials and for all activity occurring under your account.

21. Use of Cookies and Data Storage
This system may use cookies or similar technologies to maintain sessions and functionality. Information stored may include account credentials, contact information, submitted content, and usage activity. Reasonable measures are taken to protect data; however, no system can be guaranteed to be completely secure.

22. Availability and Continuity
The system is provided on an evolving and experimental basis. No guarantee is made regarding uptime, availability, continued operation, or preservation of content.

23. No Emergency or Critical Use
This system is not intended for emergency or critical use and should not be relied upon for time-sensitive or safety-related purposes.

24. Disclaimer of Warranties and Limitation of Liability
The system is provided "as is" without warranties of any kind. Submitted content may be altered or transformed in ways that affect quality or presentation. To the fullest extent permitted by law, Lewis Edward Moten III shall not be liable for any damages or losses arising from use of the system or submission of content.

25. Indemnification
You agree to indemnify and hold harmless Lewis Edward Moten III and associated parties from any claims, liabilities, damages, or expenses arising from your content or use of the system.

26. Severability
If any provision of this Agreement is found to be invalid or unenforceable, the remaining provisions shall remain in full force and effect.

27. Governing Law and Venue
This Agreement is governed by the laws of the Commonwealth of Virginia. Any legal proceedings shall take place in Warren County, Virginia.

28. Acceptance
By creating an account, logging in, submitting content, or continuing to use the system, you acknowledge that you have read, understood, and agree to these terms.', 1);

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

CREATE TABLE number_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT INTO number_types (code, label, description, is_system, sort_order) VALUES

-- Core user content
('user_content', 'User Submitted Audio', 'Standard user-submitted audio playback', 0, 10),
('business_listing', 'Business Listing', 'Business or organization listing with optional advertisement', 0, 20),

-- Exhibit experiences
('art_commentary', 'Art Commentary', 'Audio describing artwork in the exhibit', 1, 30),
('phone_gallery', 'Phone Gallery', 'Audio tied to physical exhibit pieces', 1, 40),

-- System / utility
('operator', 'Operator Line', 'Operator or routing assistance line', 1, 50),
('directory', 'Directory Assistance', 'Lookup or navigation system', 1, 60),

-- Voicemail / interaction
('voicemail', 'Voicemail Box', 'Personal or shared voicemail box', 0, 70),
('public_voicemail', 'Public Voicemail Chain', 'Shared evolving voicemail experience', 1, 80),

-- Entertainment / creative
('story', 'Story / Narrative', 'Story or storytelling content', 0, 90),
('nonlinear_story', 'Nonlinear Story', 'Story spread across multiple numbers', 0, 100),
('puzzle', 'Puzzle / Riddle', 'Interactive puzzle or riddle system', 1, 110),
('game', 'Interactive Game', 'Choose-your-own-adventure or gameplay', 1, 120),

-- Audio experiences
('music', 'Music / Jukebox', 'Music playback or categorized selections', 0, 130),
('ambient', 'Ambient / Experimental Audio', 'Soundscapes or experimental audio', 0, 140),
('comedy', 'Comedy / Sketch', 'Humor or comedic content', 0, 150),

-- Utility simulations
('service', 'Service Simulation', 'Fake or real-world inspired service (weather, moviefone)', 1, 160),
('advertisement', 'Advertisement', 'Commercial or parody advertisement', 0, 170),

-- Special / hidden
('easter_egg', 'Easter Egg', 'Hidden or unlisted number', 1, 180),
('system_number', 'System Reserved', 'Internal system number (911, 411, etc)', 1, 190);

ALTER TABLE telephone_numbers
ADD COLUMN number_format ENUM('full','short','internal') NOT NULL DEFAULT 'full' AFTER full_number;

INSERT INTO telephone_numbers (
    full_number,
    number_format,
    display_number,
    is_active,
    is_reserved
)
VALUES
('911', 'short', '911', 1, 1),
('411', 'short', '411', 1, 1),
('611', 'short', '611', 1, 1),
('711', 'short', '711', 1, 1),
('811', 'short', '811', 1, 1),
('0',   'short', '0 (Operator)', 1, 1),
('1',   'short', '1', 1, 0),
('2',   'short', '2', 1, 0),
('3',   'short', '3', 1, 0),
('4',   'short', '4', 1, 0),
('5',   'short', '5', 1, 0),
('6',   'short', '6', 1, 0),
('7',   'short', '7', 1, 0),
('8',   'short', '8', 1, 0),
('9',   'short', '9', 1, 0);

UPDATE telephone_numbers
SET is_reserved = 1,
    is_active = 1
WHERE full_number IN ('911','411','611','711','811');

ALTER TABLE number_profiles
ADD COLUMN number_type_id INT UNSIGNED NULL AFTER user_id;

UPDATE number_profiles
SET number_type_id = 1
WHERE number_type_id IS NULL;

ALTER TABLE number_profiles
MODIFY COLUMN number_type_id INT UNSIGNED NOT NULL;

ALTER TABLE number_profiles
ADD CONSTRAINT fk_number_profiles_type
FOREIGN KEY (number_type_id) REFERENCES number_types(id);

ALTER TABLE number_profiles
ADD COLUMN is_listed TINYINT(1) DEFAULT 1,
ADD COLUMN is_hidden TINYINT(1) DEFAULT 0;

DELIMITER $$

CREATE PROCEDURE seed_exhibit_numbers()
BEGIN
    DECLARE i INT DEFAULT 1000;

    WHILE i <= 1199 DO
        INSERT INTO telephone_numbers (
            full_number, number_format, area_code, central_office_code, station_code,
            display_number, is_active, is_reserved
        )
        VALUES (
            CAST(i AS CHAR),
            'short',
            NULL,
            NULL,
            NULL,
            CAST(i AS CHAR),
            1,
            0
        );
        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

CALL seed_exhibit_numbers();
DROP PROCEDURE seed_exhibit_numbers;

DELIMITER $$

DELIMITER $$

CREATE PROCEDURE seed_540_555_numbers()
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 3000 DO
        INSERT IGNORE INTO telephone_numbers (
            full_number, number_format, area_code, central_office_code, station_code,
            display_number, is_active, is_reserved
        )
        VALUES (
            CONCAT('540555', LPAD(i, 4, '0')),
            'full',
            '540',
            '555',
            LPAD(i, 4, '0'),
            CONCAT('(540) 555-', LPAD(i, 4, '0')),
            1,
            0
        );
        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

CALL seed_540_555_numbers();
DROP PROCEDURE seed_540_555_numbers;

DELIMITER $$

CREATE PROCEDURE seed_826_555_numbers()
BEGIN
    DECLARE i INT DEFAULT 1;

    WHILE i <= 1000 DO
        INSERT INTO telephone_numbers (
            full_number, number_format, area_code, central_office_code, station_code,
            display_number, is_active, is_reserved
        )
        VALUES (
            CONCAT('826555', LPAD(i, 4, '0')),
            'full',
            '826',
            '555',
            LPAD(i, 4, '0'),
            CONCAT('(826) 555-', LPAD(i, 4, '0')),
            1,
            0
        );
        SET i = i + 1;
    END WHILE;
END$$

DELIMITER ;

CALL seed_826_555_numbers();
DROP PROCEDURE seed_826_555_numbers;

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

CREATE TABLE number_profile_audio_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number_profile_audio_file_id BIGINT UNSIGNED NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    weekday_mask VARCHAR(20) NULL,
    month_num TINYINT UNSIGNED NULL,
    month_day_start CHAR(5) NULL,
    month_day_end CHAR(5) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    prior_audio_attachment_id BIGINT UNSIGNED NULL,
    menu_digit VARCHAR(10) NULL,
    is_initial_choice TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_npaf_rules_attachment
        FOREIGN KEY (number_profile_audio_file_id)
        REFERENCES number_profile_audio_files(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_npaf_rules_prior_attachment
        FOREIGN KEY (prior_audio_attachment_id)
        REFERENCES number_profile_audio_files(id)
        ON DELETE SET NULL,

    INDEX idx_npaf_rules_attachment (number_profile_audio_file_id),
    INDEX idx_npaf_rules_type (rule_type),
    INDEX idx_npaf_rules_menu_digit (menu_digit),
    INDEX idx_npaf_rules_active_sort (is_active, sort_order)
) ENGINE=InnoDB;

UPDATE number_profile_audio_files
SET role_code = 'time_based'
WHERE role_code IN ('morning', 'afternoon', 'evening');

CREATE TABLE audio_role_codes (
    code VARCHAR(50) PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO audio_role_codes (code, label, description, sort_order) VALUES
('primary', 'Primary', 'Main audio for the number', 10),
('random_pool', 'Random Pool', 'One of several files selected at random', 20),
('time_based', 'Time-Based', 'Audio controlled by one or more time/date rules', 30),
('menu_prompt', 'Menu Prompt', 'Prompt that offers keypad choices', 40),
('menu_option', 'Menu Option', 'Audio triggered by a keypad choice', 50),
('after_playback_prompt', 'After Playback Prompt', 'Prompt that plays after a main file finishes', 60),
('voicemail_greeting', 'Voicemail Greeting', 'Greeting used for a voicemail box', 70);

CREATE TABLE audio_rule_types (
    code VARCHAR(50) PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO audio_rule_types (code, label, description, sort_order) VALUES
('time_range_daily', 'Daily Time Range', 'Plays during a daily start/end time range', 10),
('weekday', 'Weekday', 'Plays only on selected weekdays', 20),
('business_hours', 'Business Hours', 'Plays during weekdays within a start/end time range', 30),
('month', 'Month', 'Plays only during a specific month', 40),
('month_day_range', 'Month/Day Range', 'Plays between a month/day start and end', 50),
('date_range', 'Exact Date Range', 'Plays between specific start and end dates', 60),
('menu_option', 'Menu Option', 'Plays in response to a menu choice', 70);

ALTER TABLE number_profile_audio_files
ADD CONSTRAINT fk_number_profile_audio_role_code
FOREIGN KEY (role_code) REFERENCES audio_role_codes(code);

ALTER TABLE telephone_numbers
ADD COLUMN release_status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER is_reserved,
ADD COLUMN release_requested_at DATETIME NULL AFTER release_status,
ADD COLUMN release_message_mode VARCHAR(30) NULL AFTER release_requested_at,
ADD COLUMN released_at DATETIME NULL AFTER release_message_mode;

ALTER TABLE number_profiles
ADD COLUMN is_soft_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
ADD COLUMN soft_deleted_at DATETIME NULL AFTER is_soft_deleted,
ADD COLUMN soft_delete_reason VARCHAR(255) NULL AFTER soft_deleted_at;

ALTER TABLE number_profiles
ADD COLUMN archived_at DATETIME NULL AFTER soft_delete_reason;

CREATE TABLE site_content (
    key_name VARCHAR(100) PRIMARY KEY,
    html_content MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER DATABASE regaldra_phone
CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

ALTER TABLE site_content
CONVERT TO CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

INSERT INTO site_content (key_name, html_content)
VALUES ('homepage_callout', '<section class="callout">
    <h2>📞 A Dialable Audio Exhibit</h2>

    <p>
        An interactive audio exhibit is being developed for the Stone Branch Center for the Arts.
        Visitors will pick up rotary and analog telephones and dial into a collection of recordings—
        each one created by members of the community.
    </p>

    <p>
        We''re inviting artists, musicians, writers, storytellers, historians, comedians, experimental creators,
        and local businesses from Front Royal, Warren County, and across the Shenandoah Valley
        to contribute.
    </p>

    <h3>What you can contribute</h3>

    <ul>
        <li>🎶 Music — full songs or short clips</li>
        <li>📖 Poems, stories, or spoken word</li>
        <li>🎙️ Oral histories and local memories</li>
        <li>🎭 Comedy, characters, or playful performances</li>
        <li>🔊 Sound experiments, ASMR, or abstract audio</li>
        <li>🧩 Riddles, audio games, or interactive ideas</li>
        <li>💭 Reflections on your creative work</li>
        <li>👀 Anything unusual, unexpected, or uniquely yours</li>
    </ul>

    <h3>For local businesses</h3>

    <ul>
        <li>📣 Short audio advertisements (like a radio commercial)</li>
        <li>📅 Announcements for events, specials, or seasonal offerings</li>
        <li>🔄 The ability to update your message over time</li>
    </ul>

    <h3>How it works</h3>

    <p>
        Visitors will explore the exhibit by dialing phone numbers. Each number connects to a different
        recording—turning the space into a living, interactive sound gallery.
    </p>

    <p>
        All audio will be adapted to a vintage telephone sound (8 kHz, narrowband), so recordings
        don''t need to be perfect—clarity and authenticity matter most.
    </p>

    <p>
        This project is designed to grow over time, with new recordings, evolving content,
        and unexpected discoveries with every call.
    </p>

    <p class="call-to-action">
        <strong>Ready to participate?</strong><br>
        Create an account to upload your audio, reserve a number, and become part of the exhibit.
    </p>
</section>');

INSERT INTO site_content (key_name, html_content)
VALUES ('dashboard_callout', '<p>
    This platform powers a dialable audio exhibit at the Stone Branch Center for the Arts.
    Visitors use analog phones to explore recordings created by artists, storytellers,
    and local businesses.
</p>

<p>
    Upload your audio, reserve a number, and become part of a living sound gallery.
</p>');

ALTER TABLE audio_files
    MODIFY COLUMN conversion_status VARCHAR(20) NOT NULL DEFAULT 'pending';

ALTER TABLE audio_files
    ADD COLUMN conversion_started_at DATETIME NULL AFTER conversion_status,
    ADD COLUMN conversion_completed_at DATETIME NULL AFTER conversion_started_at,
    ADD COLUMN conversion_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER conversion_completed_at;

ALTER TABLE audio_files
    ADD INDEX idx_audio_files_conversion_status (conversion_status),
    ADD INDEX idx_audio_files_is_deleted_conversion_status (is_deleted, conversion_status);

ALTER TABLE audio_files
    ADD COLUMN transcription_status VARCHAR(20) DEFAULT 'pending',
    ADD COLUMN transcription_text MEDIUMTEXT NULL,
    ADD COLUMN transcription_error TEXT NULL;

ALTER TABLE audio_files
  ADD COLUMN transcription_attempts INT DEFAULT 0;

ALTER TABLE audio_files
  ADD COLUMN transcription_started_at DATETIME NULL,
  ADD COLUMN transcription_completed_at DATETIME NULL;

CREATE INDEX idx_audio_transcription_status ON audio_files (transcription_status);
CREATE INDEX idx_audio_conv_trans ON audio_files (conversion_status, transcription_status, is_deleted);
