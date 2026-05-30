CREATE TABLE IF NOT EXISTS directory_paper_classifications (
    code CHAR(1) CHARACTER SET ascii COLLATE ascii_general_ci PRIMARY KEY,
    label VARCHAR(32) NOT NULL,
    description VARCHAR(120) NOT NULL,
    color_hex CHAR(7) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

ALTER TABLE directory_paper_classifications
MODIFY COLUMN code CHAR(1)
    CHARACTER SET ascii
    COLLATE ascii_general_ci
    NOT NULL;

INSERT INTO directory_paper_classifications (
    code,
    label,
    description,
    color_hex,
    sort_order,
    is_active
) VALUES
('W', 'White', 'Residential listings', '#f7f1e2', 10, 1),
('Y', 'Yellow', 'Business listings', '#f3dc6b', 20, 1),
('B', 'Blue', 'Government listings', '#b9d4ef', 30, 1),
('G', 'Green', 'Information listings', '#c8dfb4', 40, 1),
('P', 'Pink', 'Emergency and calling information', '#efc1cf', 50, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    color_hex = VALUES(color_hex),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

ALTER TABLE audio_files
ADD COLUMN IF NOT EXISTS paper_classification_code CHAR(1) NULL AFTER directory_title;

ALTER TABLE audio_files
MODIFY COLUMN paper_classification_code CHAR(1)
    CHARACTER SET ascii
    COLLATE ascii_general_ci
    NULL
    AFTER directory_title;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_audio_files_paper_classification_fk_if_missing$$
CREATE PROCEDURE add_audio_files_paper_classification_fk_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'audio_files'
          AND constraint_name = 'fk_audio_files_paper_classification'
    ) THEN
        ALTER TABLE audio_files
        ADD CONSTRAINT fk_audio_files_paper_classification
        FOREIGN KEY (paper_classification_code)
        REFERENCES directory_paper_classifications(code);
    END IF;
END$$

DROP PROCEDURE IF EXISTS add_audio_files_paper_classification_index_if_missing$$
CREATE PROCEDURE add_audio_files_paper_classification_index_if_missing()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'audio_files'
          AND index_name = 'idx_audio_files_paper_classification'
    ) THEN
        CREATE INDEX idx_audio_files_paper_classification
        ON audio_files (paper_classification_code);
    END IF;
END$$

DELIMITER ;

CALL add_audio_files_paper_classification_fk_if_missing();
CALL add_audio_files_paper_classification_index_if_missing();

DROP PROCEDURE add_audio_files_paper_classification_fk_if_missing;
DROP PROCEDURE add_audio_files_paper_classification_index_if_missing;
