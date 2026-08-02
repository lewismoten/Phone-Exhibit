<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('My Audio Files');
?>

<?php if (is_admin()): ?>
    <p><a class="button" href="/admin-audio-phone-list.php">Admin Phone List</a></p>
<?php endif; ?>

<div class="audio-action-panels">
    <div class="card secondary audio-action-card audio-capture-card">
        <?php html_border_pieces(); ?>
        <div class="audio-action-card-body center">
            <div id="audio-capture-home" class="audio-capture-home">
                <p><a id="audio-record-reveal" class="button error" href="#">Record Audio</a></p>
                <p><a id="audio-upload-reveal" class="button" href="#">Upload Audio</a></p>
            </div>

            <div id="audio-upload-panel" class="audio-mode-dialog audio-upload-panel" hidden>
                <a id="audio-upload-close" class="audio-dialog-close" href="#" aria-label="Close upload dialog">X</a>
                <div id="audio-upload-status"></div>
                <form id="audio-upload-form" class="audio-upload-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input id="audio_upload_file" name="audio_file" type="file" accept="audio/*" required hidden>
                    <a id="audio-upload-choose" class="button" href="#">Choose File</a>
                    <div id="audio-upload-preview" class="audio-upload-preview audio-record-preview-disabled" aria-label="Selected audio preview unavailable"></div>
                    <p><a id="audio-upload-submit" class="button warn is-disabled" href="#" aria-disabled="true">Upload</a></p>
                </form>
            </div>

            <div id="audio-record-panel" class="audio-mode-dialog audio-record-panel" hidden>
                <a id="audio-record-close" class="audio-dialog-close" href="#" aria-label="Close record dialog">X</a>
                <div id="audio-record-status"></div>
                <select id="audio-record-device"></select>

                <div class="audio-record-bars">
                    <div class="audio-record-progress-track">
                        <div id="audio-record-progress" class="audio-record-progress-fill"></div>
                    </div>
                </div>

                <div class="audio-record-timer-row">
                    <div class="audio-record-level-column">
                        <div class="audio-record-level-track">
                            <div id="audio-record-level" class="audio-record-level-fill"></div>
                        </div>
                    </div>

                    <svg id="audio-record-hourglass" viewBox="0 0 64 96" width="34" height="52" aria-hidden="true">
                        <defs>
                            <clipPath id="audio-record-hourglass-top-clip">
                                <rect id="audio-record-hourglass-top-clip-rect" x="20" y="14" width="24" height="19"></rect>
                            </clipPath>
                            <clipPath id="audio-record-hourglass-bottom-clip">
                                <rect id="audio-record-hourglass-bottom-clip-rect" x="20" y="63" width="24" height="19"></rect>
                            </clipPath>
                        </defs>
                        <path d="M14 6h36v8c0 12-8 21-18 28c10 7 18 16 18 28v20H14V70c0-12 8-21 18-28C22 35 14 26 14 14V6z" fill="none" stroke="#4a3c2e" stroke-width="4" stroke-linejoin="round"/>
                        <path d="M20 14h24c0 8-5 14-12 19c-7-5-12-11-12-19z" fill="#d8c08b"/>
                        <path id="audio-record-hourglass-top-sand" d="M20 14h24c0 8-5 14-12 19c-7-5-12-11-12-19z" fill="#c49b52" clip-path="url(#audio-record-hourglass-top-clip)"/>
                        <path d="M20 82h24c0-8-5-14-12-19c-7 5-12 11-12 19z" fill="#d8c08b"/>
                        <path id="audio-record-hourglass-bottom-sand" d="M20 82h24c0-8-5-14-12-19c-7 5-12 11-12 19z" fill="#c49b52" opacity="0.25" clip-path="url(#audio-record-hourglass-bottom-clip)"/>
                        <g id="audio-record-hourglass-stream" opacity="0">
                            <circle id="audio-record-hourglass-stream-dot-1" cx="32" cy="40" r="1.2" fill="#d8c08b"></circle>
                            <circle id="audio-record-hourglass-stream-dot-2" cx="31" cy="46" r="1" fill="#c49b52"></circle>
                            <circle id="audio-record-hourglass-stream-dot-3" cx="33" cy="52" r="1.1" fill="#e6cf97"></circle>
                            <circle id="audio-record-hourglass-stream-dot-4" cx="32" cy="58" r="0.9" fill="#c49b52"></circle>
                        </g>
                    </svg>

                    <div class="audio-record-timer">
                        <div class="audio-record-timer-copy">
                            <div><strong>Status:</strong> <span id="audio-record-state">Idle</span></div>
                            <div><strong>Duration:</strong> <span id="audio-record-duration">0:00</span></div>
                            <div><strong>Remaining:</strong> <span id="audio-record-remaining">3:00</span></div>
                        </div>
                        <div id="audio-record-preview" class="audio-record-preview audio-record-preview-disabled" aria-label="Recording preview unavailable"></div>
                    </div>
                </div>

                <div class="audio-record-controls audio-record-controls-bottom">
                    <a id="audio-record-upload" class="button is-disabled" href="#" aria-disabled="true">Upload</a>
                    <a id="audio-record-toggle" class="button error" href="#">Record</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card secondary audio-search-card">
        <?php html_border_pieces(); ?>
        <form id="audio-search-form" class="audio-search-form">
            <div id="audio-user-filter-wrap" class="audio-user-filter-row" hidden>
                <select id="audio-user-filter" name="user_id" aria-label="Filter by user account"></select>
            </div>
            <div class="audio-search-form-row">
                <input id="q" name="q" placeholder="Search filename, title, phone number, or transcript">
                <a id="audio-search-submit" class="button" href="#">Search</a>
            </div>
        </form>
    </div>
</div>

<div id="audio-results">Loading…</div>
<nav id="audio-pagination"></nav>

<dialog id="audio-assignment-dialog" class="assignment-dialog">
    <form id="audio-assignment-form" method="dialog" class="assignment-dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="">
        <div class="assignment-dialog-card card secondary">
            <?php html_border_pieces(); ?>
            <button type="button" id="audio-assignment-close" class="assignment-dialog-close" aria-label="Close assignment dialog">X</button>
            <h2 id="audio-assignment-title">Manage Listing</h2>
            <div id="audio-assignment-status"></div>

            <div class="assignment-dialog-grid">
                <label>
                    <strong>Requested number</strong><br>
                    <input id="audio-assignment-requested" type="text" readonly>
                </label>

                <label>
                    <strong>Page color</strong><br>
                    <select id="audio-assignment-paper" name="paper_classification_code"></select>
                </label>

                <label>
                    <strong>Exhibit number</strong><br>
                    <div class="assignment-inline-row">
                        <input id="audio-assignment-exhibit" name="exhibit_phone_number" type="text" inputmode="numeric">
                        <a id="audio-assignment-accept-requested" class="button" href="#">Accept Requested</a>
                    </div>
                </label>

                <label id="audio-assignment-tty-number-wrap" hidden>
                    <strong>TTY number</strong><br>
                    <input id="audio-assignment-tty-number" name="tty_phone_number" type="text" inputmode="numeric">
                </label>
            </div>

            <div id="audio-assignment-tty-wrap" class="assignment-tty-wrap" hidden>
                <strong>TTY content</strong>
                <div id="audio-assignment-tty-content" class="assignment-tty-content"></div>
            </div>

            <div class="assignment-dialog-actions">
                <button type="submit" class="button primary">Save</button>
            </div>
        </div>
    </form>
</dialog>

<script defer src="/dashboard.js"></script>

<?php html_footer(); ?>
