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