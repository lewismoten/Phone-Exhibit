ALTER TABLE audio_files

    ADD COLUMN requested_phone_number VARCHAR(32) NULL
        AFTER exhibit_phone_number,

    ADD COLUMN directory_title VARCHAR(150) NULL
        AFTER short_name,

    ADD COLUMN rolodex_title VARCHAR(150) NULL
        AFTER directory_title,

    ADD COLUMN rolodex_details TEXT NULL
        AFTER rolodex_title,

    ADD COLUMN ai_transcription_opt_in TINYINT(1) NOT NULL DEFAULT 0
        AFTER transcription_status,

    ADD COLUMN tty_transcription_text MEDIUMTEXT NULL
        AFTER transcription_text,

    ADD COLUMN is_listed TINYINT(1) NOT NULL DEFAULT 1
        AFTER tty_transcription_text,

    ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0
        AFTER is_listed,

    ADD COLUMN moderation_status ENUM(
        'draft',
        'pending_review',
        'approved',
        'rejected',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft'
        AFTER is_hidden,

    ADD COLUMN explicit_content_flag TINYINT(1) NOT NULL DEFAULT 0
        AFTER moderation_status,

    ADD COLUMN family_friendly_version_available TINYINT(1)
        NOT NULL DEFAULT 0
        AFTER explicit_content_flag,

    ADD COLUMN publish_physical_directory TINYINT(1)
        NOT NULL DEFAULT 0
        AFTER family_friendly_version_available,

    ADD COLUMN publish_web_directory TINYINT(1)
        NOT NULL DEFAULT 0
        AFTER publish_physical_directory,

    ADD COLUMN business_category_id INT UNSIGNED NULL
        AFTER publish_web_directory,

    ADD COLUMN number_type_id INT UNSIGNED NULL
        AFTER business_category_id,

    ADD COLUMN archived_at DATETIME NULL
        AFTER updated_at,
    ADD COLUMN soft_deleted_at DATETIME NULL
        AFTER archived_at;

ALTER TABLE audio_files
ADD CONSTRAINT fk_audio_files_business_category
FOREIGN KEY (business_category_id)
REFERENCES business_categories(id);

ALTER TABLE audio_files
ADD CONSTRAINT fk_audio_files_number_type
FOREIGN KEY (number_type_id)
REFERENCES number_types(id);

CREATE INDEX idx_audio_files_directory_title
    ON audio_files (directory_title);

CREATE INDEX idx_audio_files_moderation
    ON audio_files (moderation_status);

CREATE INDEX idx_audio_files_listing
    ON audio_files (
        is_hidden,
        is_listed,
        publish_web_directory,
        publish_physical_directory
    );

