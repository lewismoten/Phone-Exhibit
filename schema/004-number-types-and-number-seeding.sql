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

