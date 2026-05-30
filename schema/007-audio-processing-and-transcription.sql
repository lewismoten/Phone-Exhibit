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

ALTER TABLE audio_files
ADD COLUMN tty_status ENUM('pending','processing','complete','failed','skipped')
    NOT NULL DEFAULT 'pending' AFTER transcription_status,
ADD COLUMN tty_error TEXT NULL AFTER tty_status,
ADD COLUMN tty_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER tty_error,
ADD COLUMN tty_started_at DATETIME NULL AFTER tty_attempts,
ADD COLUMN tty_completed_at DATETIME NULL AFTER tty_started_at,
ADD COLUMN tty_file_path VARCHAR(512) NULL AFTER tty_completed_at;

ALTER TABLE audio_files
    ADD COLUMN tty_relative_path VARCHAR(500) NULL AFTER relative_path,
    ADD COLUMN tty_mime_type VARCHAR(100) NULL AFTER mime_type,
    ADD COLUMN tty_file_ext VARCHAR(20) NULL AFTER file_ext,
    ADD COLUMN tty_file_size_bytes BIGINT UNSIGNED NULL AFTER file_size_bytes,
    ADD COLUMN tty_duration_seconds DECIMAL(10,3) NULL AFTER duration_seconds,
    ADD COLUMN tty_audio_format VARCHAR(50) NULL AFTER audio_format,
    ADD COLUMN tty_audio_type VARCHAR(100) NULL AFTER audio_type,
    ADD COLUMN tty_channels TINYINT UNSIGNED NULL AFTER channels,
    ADD COLUMN tty_channel_mode VARCHAR(20) NULL AFTER channel_mode,
    ADD COLUMN tty_sample_rate_hz INT UNSIGNED NULL AFTER sample_rate_hz;

ALTER TABLE audio_files
    ADD COLUMN tty_filename VARCHAR(255) NULL AFTER stored_filename;


