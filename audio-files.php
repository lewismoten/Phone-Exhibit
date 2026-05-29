<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('My Audio Files');
?>

<h1>My audio files</h1>

<div class="audio-action-panels">
    <div class="card secondary audio-action-card">
        <?php html_border_pieces(); ?>
        <h1 class="center">Upload</h1>
        <hr>

        <div class="audio-action-card-body center">
            <div id="audio-upload-status"></div>
            <form id="audio-upload-form" class="audio-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input id="audio_upload_file" name="audio_file" type="file" accept="audio/*" required>
                <p><a id="audio-upload-submit" class="button" href="#">Upload audio file</a></p>
            </form>
        </div>
    </div>

    <div class="card secondary audio-action-card audio-record-card">
        <?php html_border_pieces(); ?>
        <h1 class="center">Record</h1>
        <hr>

        <div class="audio-action-card-body center">
            <div id="audio-record-status"></div>

            <div id="audio-record-intro">
                <p><a id="audio-record-reveal" class="button" href="#">Record Audio</a></p>
            </div>

            <div id="audio-record-panel" class="audio-record-panel" hidden>
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
        <h1 class="center">Search</h1>
        <hr>

        <form id="audio-search-form" class="audio-search-form">
            <div class="audio-search-form-row">
                <input id="q" name="q" placeholder="Search filename, title, phone number, or transcript">
                <a id="audio-search-submit" class="button" href="#">Search</a>
            </div>
        </form>
    </div>
</div>

<div id="audio-results">Loading…</div>
<nav id="audio-pagination" style="margin-top:16px;"></nav>

<script src="/common.js"></script>
<script defer src="/audio-files.js"></script>

<?php html_footer(); ?>
