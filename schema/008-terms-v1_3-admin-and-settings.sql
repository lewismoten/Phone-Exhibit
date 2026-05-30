UPDATE terms_versions SET is_active = 0 WHERE is_active = 1;

INSERT INTO terms_versions (version, content, is_active)
VALUES ('1.3', 'Audio Contribution and Public Exhibition Agreement

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

9. Accessibility and Assistive Technologies
Transcribed content may be used to generate alternative representations, including TTY (teletypewriter) tone-based signals compatible with assistive communication devices. These representations may be played through the exhibit or printed by such devices, allowing individuals with hearing impairments to interact with and experience submitted content. These representations may differ from the original audio in timing, format, or structure. These features are intended both to improve accessibility and to demonstrate historical and modern telecommunications technologies.

10. Content Variants and Family-Friendly Modifications
Submitted content may be edited, filtered, or modified to create alternate versions for different audiences or exhibit contexts, including family-friendly versions. Modifications may include muting, removal, substitution, or insertion of tones (such as beeps) or other indicators in place of explicit material. Multiple versions may exist and may be associated with different telephone numbers, access paths, or playback conditions.

11. Content Segmentation and Sequential Playback
Submitted content may be divided into segments for usability, navigation, technical constraints, or exhibit design. These segments may be arranged, reordered, or distributed across one or more telephone numbers or access points. The system may allow listeners to access content non-linearly, including continuing playback from a later segment by dialing the same or a different number at a later time.

12. Number Assignment and Availability
Telephone numbers associated with submitted content are subject to availability, technical limitations, exhibit design, administrative decisions, and future changes. Assignment of a requested number is not guaranteed and may be temporary or modified at any time.

13. Payphone Use and Collected Funds
The exhibit may include payphones or similar devices that require coins, currency, tokens, or other payment. Any funds collected may, at the sole discretion of Lewis Edward Moten III or the hosting organization, be donated to a nonprofit or gallery, returned to users, or used for maintenance or operation of the exhibit. No refund is guaranteed unless explicitly stated.

14. System Operation and Modification
Lewis Edward Moten III reserves the right to modify, suspend, relocate, reconfigure, maintain, repair, or discontinue any part of the exhibit, including playback methods, devices, numbering schemes, and access mechanisms.

15. Testing and Staging Systems
Submitted content may be processed, stored, and played on secondary or staging systems, including testing PBX environments, to verify functionality and compatibility before deployment.

16. Storage, Transmission, and Future Access
Content may be stored, transmitted electronically, or physically transferred for operation and maintenance of the system. While the system is not currently accessible via public networks or the internet, future versions may allow broader public access through web or telecommunications systems.

17. Administration, Review, and Moderation
Lewis Edward Moten III and authorized administrators may access, review, listen to, evaluate, modify, edit, reformat, analyze, transcribe, classify, or remove content at their sole discretion for moderation, quality control, technical compatibility, and exhibit curation.

18. Content Removal and Takedown
Content may be removed, restricted, or refused publication at any time, with or without notice, for any reason, including legal concerns, policy violations, or curation decisions. No guarantee is made that submitted content will be published or remain available.

19. User Responsibilities and Rights Clearance
You represent and warrant that you have the legal right to submit all content and that it does not infringe upon the rights of any third party. You are responsible for obtaining all necessary permissions, licenses, and consents.

20. Legal Capacity
You represent that you are at least eighteen (18) years of age or otherwise have the legal capacity to enter into this agreement, or that you are using the system with appropriate parental or guardian consent.

21. Account Responsibility
You are responsible for maintaining the confidentiality of your account credentials and for all activity occurring under your account.

22. Use of Cookies and Data Storage
This system may use cookies or similar technologies to maintain sessions and functionality. Information stored may include account credentials, contact information, submitted content, and usage activity. Reasonable measures are taken to protect data; however, no system can be guaranteed to be completely secure.

23. Availability and Continuity
The system is provided on an evolving and experimental basis. No guarantee is made regarding uptime, availability, continued operation, or preservation of content.

24. No Emergency or Critical Use
This system is not intended for emergency or critical use and should not be relied upon for time-sensitive or safety-related purposes.

25. Disclaimer of Warranties and Limitation of Liability
The system is provided "as is" without warranties of any kind. Submitted content may be altered or transformed in ways that affect quality or presentation. To the fullest extent permitted by law, Lewis Edward Moten III shall not be liable for any damages or losses arising from use of the system or submission of content.

26. Indemnification
You agree to indemnify and hold harmless Lewis Edward Moten III and associated parties from any claims, liabilities, damages, or expenses arising from your content or use of the system.

27. Severability
If any provision of this Agreement is found to be invalid or unenforceable, the remaining provisions shall remain in full force and effect.

28. Governing Law and Venue
This Agreement is governed by the laws of the Commonwealth of Virginia. Any legal proceedings shall take place in Warren County, Virginia.

29. Acceptance
By creating an account, logging in, submitting content, or continuing to use the system, you acknowledge that you have read, understood, and agree to these terms.', 1);

ALTER TABLE number_profiles
    ADD COLUMN is_real_contact_number TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE audio_files
ADD COLUMN exhibit_phone_number VARCHAR(20) NULL AFTER tty_status;

CREATE INDEX idx_audio_files_exhibit_phone_number
ON audio_files (exhibit_phone_number);

ALTER TABLE audio_files
ADD COLUMN short_name VARCHAR(120) NULL AFTER original_filename;

ALTER TABLE users
ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user' AFTER password_hash;

UPDATE users
SET role = 'admin'
WHERE id = 1;

CREATE TABLE exhibit_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO exhibit_settings (setting_key, setting_value) VALUES
('recording_extension', '7000'),
('director_pin', '123456'),
('recording_pin_digits', '6'),
('target_number_digits', '7'),
('recording_min_silence_seconds', '3'),
('recording_max_seconds', '300'),
('recordings_pending_dir', '/var/spool/asterisk/recordings/pending'),
('recording_enabled', '1');

ALTER TABLE audio_files
ADD COLUMN tty_phone_number VARCHAR(20) NULL AFTER exhibit_phone_number;

CREATE INDEX idx_audio_files_tty_phone_number
ON audio_files (tty_phone_number);

INSERT INTO exhibit_settings (setting_key, setting_value) VALUES
('live_recording_enabled', '1'),
('live_recording_extension', '7100'),
('live_director_pin', '654321'),
('live_recording_pin_digits', '6'),
('live_target_number_digits', '7'),
('live_recording_min_silence_seconds', '3'),
('live_recording_max_seconds', '300'),
('live_recordings_dir', '/var/lib/asterisk/sounds/phone-exhibit-live')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

