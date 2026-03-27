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
