CREATE DATABASE IF NOT EXISTS phone_exhibits CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phone_exhibits;

CREATE TABLE IF NOT EXISTS schema_script_deployments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    script_name VARCHAR(255) NOT NULL,
    script_order INT UNSIGNED NULL,
    script_checksum CHAR(64) NULL,
    status ENUM('started', 'succeeded', 'failed') NOT NULL DEFAULT 'started',
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    error_message TEXT NULL,
    notes TEXT NULL,
    UNIQUE KEY uq_schema_script_name (script_name),
    INDEX idx_schema_script_name (script_name),
    INDEX idx_schema_script_status (status),
    INDEX idx_schema_script_attempted_at (attempted_at)
) ENGINE=InnoDB;

INSERT INTO schema_script_deployments (
    script_name,
    script_order,
    status,
    attempted_at,
    completed_at,
    notes
) VALUES
('001-core-users-audio.sql', 1, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('002-terms-versions-v1_0-v1_2.sql', 2, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('003-telephone-playback-and-initial-profiles.sql', 3, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('004-number-types-and-number-seeding.sql', 4, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('005-system-profiles-and-custom-number-requests.sql', 5, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('006-audio-rules-and-site-content.sql', 6, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('007-audio-processing-and-transcription.sql', 7, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('008-terms-v1_3-admin-and-settings.sql', 8, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('009-audio-directory-listing-and-moderation.sql', 9, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('010-drop-legacy-number-profile-tables.sql', 10, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded after splitting legacy schema.sql into ordered files.'),
('011-schema-script-deployments.sql', 11, 'succeeded', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Seeded by the deployment-tracking schema itself.')
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    completed_at = VALUES(completed_at),
    notes = VALUES(notes);
