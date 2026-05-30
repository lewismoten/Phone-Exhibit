<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_login();
require_current_terms_acceptance();

$id = max(0, (int)($_GET['id'] ?? 0));

if ($id <= 0) {
    flash_set('error', 'Invalid audio file.');
    redirect('my-audio-files.php');
}

html_header('Edit Audio File');
?>

<h1>Edit audio file</h1>

<div id="edit-audio-status"></div>

<form id="edit-audio-form" style="max-width:760px;">
    <input type="hidden" name="id" id="edit-audio-id" value="<?= (int)$id ?>">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <fieldset>
        <legend>Directory listing</legend>

        <p>
            <label for="directory_title">Title to appear in directory</label><br>
            <input id="directory_title" name="directory_title" maxlength="150" style="width:100%;">
        </p>

        <p>
            <label for="requested_phone_number">Requested phone number</label><br>
            <input id="requested_phone_number" name="requested_phone_number" maxlength="32" style="width:100%;">
        </p>
    </fieldset>

    <fieldset>
        <legend>Rolodex card</legend>

        <div class="rolodex-editor">
            <div class="rolodex-fields">
                <p>
                    <label for="rolodex_title">Title to appear on Rolodex card</label><br>
                    <input id="rolodex_title" name="rolodex_title" maxlength="40" class="rolodex-input">
                    <small class="muted">Line 1 only, up to 40 characters.</small>
                </p>

                <?php if (is_admin()): ?>
                <p>
                    <label for="paper_classification_code">Paper / class</label><br>
                    <select id="paper_classification_code" name="paper_classification_code" class="rolodex-paper-select">
                        <option value="">Optional</option>
                    </select>
                    <small class="muted">W Residential, Y Business, B Government, G Information, O Arts/Stories, P Emergency.</small>
                </p>
                <?php endif; ?>

                <p>
                    <label for="rolodex_details">Additional details for Rolodex card</label><br>
                    <textarea id="rolodex_details" name="rolodex_details" rows="5" maxlength="204" class="rolodex-textarea"></textarea>
                    <span class="rolodex-key-buttons" aria-label="Special typewriter keys">
                        <button type="button" class="rolodex-key-button" data-insert="¼">¼</button>
                        <button type="button" class="rolodex-key-button" data-insert="½">½</button>
                        <button type="button" class="rolodex-key-button" data-insert="¢">¢</button>
                        <button type="button" class="rolodex-key-button" data-insert="£">£</button>
                    </span>
                    <small class="muted">Lines 3-7 only, up to 5 lines and 40 characters wide.</small>
                    <small class="muted">
                        Available keys from your Remington sample: A-Z, a-z, 0-9, and these symbols:
                        - ! &amp; ' ( ) ; : @ , . ? ¢ £ ½ ¼
                    </small>
                </p>
            </div>

            <div class="rolodex-preview-wrap">
                <label>Rolodex preview</label>
                <div id="rolodex-preview" class="rolodex-preview">Loading preview…</div>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Transcription and accessibility</legend>

        <p>
            <label>
                <input type="checkbox" id="ai_transcription_opt_in" name="ai_transcription_opt_in" value="1">
                Opt in to AI transcription
            </label>
        </p>

        <p id="ai-transcription-wrap" style="display:none;">
            <label for="transcription_text">AI transcription</label><br>
            <textarea id="transcription_text" rows="8" style="width:100%;" readonly></textarea>
        </p>

        <p id="ai-transcription-tty-wrap" style="display:none;">
            <label for="transcription_tty_preview">TTY format of AI transcription</label><br>
            <textarea id="transcription_tty_preview" class="tty-textarea" rows="8" readonly></textarea>
        </p>

        <p>
            <label for="tty_transcription_text">Separate TTY transcription</label><br>
            <textarea id="tty_transcription_text" name="tty_transcription_text" class="tty-textarea" rows="8"></textarea>
            <small class="muted">
                TTY text allows A-Z, 0-9, spaces, and these symbols: . , ? ! : ; - ( ) / "
                Lower-case becomes uppercase, & becomes AND, % becomes PERCENT, and apostrophes are removed.
            </small>
        </p>
    </fieldset>

    <fieldset>
        <legend>Playback</legend>

        <div id="audio-playback">Loading audio…</div>
    </fieldset>

    <p>
        <button type="submit">Save changes</button>
        <button type="button" id="delete-audio-button">Delete audio</button>
    </p>
</form>

<div id="delete-audio-modal" class="modal-backdrop" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-audio-title">
        <h2 id="delete-audio-title">Delete audio?</h2>
        <p>This will permanently remove this audio file from your list. This action cannot be undone.</p>
        <div class="modal-actions">
            <button type="button" id="delete-audio-cancel">Cancel</button>
            <button type="button" id="delete-audio-confirm">Delete audio</button>
        </div>
    </div>
</div>

<script defer src="/edit-audio.js"></script>

</script>

<?php html_footer(); ?>
