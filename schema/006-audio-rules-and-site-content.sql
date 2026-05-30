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

