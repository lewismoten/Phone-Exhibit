<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
$profileId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($profileId <= 0) {
    http_response_code(400);
    exit('Invalid number profile.');
}

function get_number_profile_for_user(int $profileId, int $userId): ?array
{
    $stmt = db()->prepare("
        SELECT
            np.*,
            tn.display_number,
            tn.full_number,
            tn.number_format
        FROM number_profiles np
        LEFT JOIN telephone_numbers tn ON tn.id = np.telephone_number_id
        WHERE np.id = ?
          AND np.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$profileId, $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function get_number_types(): array
{
    $stmt = db()->query("
        SELECT id, code, label, description
        FROM number_types
        WHERE is_active = 1
        ORDER BY sort_order ASC, label ASC
    ");

    return $stmt->fetchAll();
}

function get_playback_modes(): array
{
    $stmt = db()->query("
        SELECT id, code, label, description
        FROM playback_modes
        WHERE is_active = 1
        ORDER BY sort_order ASC, label ASC
    ");

    return $stmt->fetchAll();
}

function get_business_categories(): array
{
    $stmt = db()->query("
        SELECT id, name, description
        FROM business_categories
        WHERE is_active = 1
        ORDER BY sort_order ASC, name ASC
    ");

    return $stmt->fetchAll();
}

function get_forwardable_numbers(): array
{
    $stmt = db()->query("
        SELECT id, display_number
        FROM telephone_numbers
        WHERE is_active = 1
        ORDER BY display_number ASC
        LIMIT 500
    ");

    return $stmt->fetchAll();
}

$profile = get_number_profile_for_user($profileId, (int)$user['id']);
if (!$profile) {
    http_response_code(404);
    exit('Number profile not found.');
}

$error = '';
$success = flash_get('success') ?? '';

$numberTypes = get_number_types();
$playbackModes = get_playback_modes();
$businessCategories = get_business_categories();
$forwardableNumbers = get_forwardable_numbers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $numberTypeId = (int)($_POST['number_type_id'] ?? 0);
    $playbackModeId = (int)($_POST['playback_mode_id'] ?? 0);
    $listingText = trim((string)($_POST['listing_text'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $publishPhysical = isset($_POST['publish_physical_directory']) ? 1 : 0;
    $publishWeb = isset($_POST['publish_web_directory']) ? 1 : 0;
    $isRealContactNumber = isset($_POST['is_real_contact_number']) ? 1 : 0;
    $explicitContentFlag = isset($_POST['explicit_content_flag']) ? 1 : 0;
    $familyFriendlyVersionAvailable = isset($_POST['family_friendly_version_available']) ? 1 : 0;
    $isVoicemailBox = isset($_POST['is_voicemail_box']) ? 1 : 0;
    $impersonationWarningAcknowledged = isset($_POST['impersonation_warning_acknowledged']) ? 1 : 0;

    $businessCategoryId = (int)($_POST['business_category_id'] ?? 0);
    $forwardToNumberId = (int)($_POST['forward_to_number_id'] ?? 0);
    $voicemailPin = trim((string)($_POST['voicemail_pin'] ?? ''));

    if ($numberTypeId <= 0) {
        $error = 'Please choose a number type.';
    } elseif ($playbackModeId <= 0) {
        $error = 'Please choose a playback mode.';
    } elseif ($listingText === '') {
        $error = 'Please enter the text that should appear with the number.';
    } elseif (mb_strlen($listingText) > 255) {
        $error = 'Listing text is too long.';
    } elseif ($isRealContactNumber === 1 && $impersonationWarningAcknowledged !== 1) {
        $error = 'You must acknowledge the real-number impersonation warning.';
    } elseif ($isVoicemailBox === 1 && $voicemailPin !== '' && !preg_match('/^\d{3,20}$/', $voicemailPin)) {
        $error = 'Voicemail PIN must be 3 to 20 digits.';
    } else {
        $voicemailPinHash = $profile['voicemail_pin_hash'];

        if ($isVoicemailBox === 1 && $voicemailPin !== '') {
            $voicemailPinHash = password_hash($voicemailPin, PASSWORD_DEFAULT);
        }

        if ($isVoicemailBox === 0) {
            $voicemailPinHash = null;
        }

        if ($businessCategoryId <= 0) {
            $businessCategoryId = null;
        }

        if ($forwardToNumberId <= 0) {
            $forwardToNumberId = null;
        }

        $stmt = db()->prepare("
            UPDATE number_profiles
            SET
                number_type_id = ?,
                playback_mode_id = ?,
                listing_text = ?,
                notes = ?,
                is_voicemail_box = ?,
                voicemail_pin_hash = ?,
                forward_to_number_id = ?,
                publish_physical_directory = ?,
                publish_web_directory = ?,
                explicit_content_flag = ?,
                family_friendly_version_available = ?,
                business_category_id = ?,
                is_real_contact_number = ?,
                impersonation_warning_acknowledged = ?,
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
        ");
        $stmt->execute([
            $numberTypeId,
            $playbackModeId,
            $listingText,
            $notes !== '' ? $notes : null,
            $isVoicemailBox,
            $voicemailPinHash,
            $forwardToNumberId,
            $publishPhysical,
            $publishWeb,
            $explicitContentFlag,
            $familyFriendlyVersionAvailable,
            $businessCategoryId,
            $isRealContactNumber,
            $impersonationWarningAcknowledged,
            $profileId,
            $user['id'],
        ]);

        flash_set('success', 'Number profile updated.');
        header('Location: edit-number-profile.php?id=' . urlencode((string)$profileId));
        exit;
    }

    $profile = get_number_profile_for_user($profileId, (int)$user['id']);
}

$displayNumber = $profile['display_number']
    ?: $profile['custom_requested_number']
    ?: 'Unassigned number';

html_header('Edit Number Profile');
?>

<h1>Edit number profile</h1>

<?php if ($success): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<div style="border:1px solid #ddd;padding:16px;border-radius:12px;margin-bottom:16px;">
    <p style="margin:0 0 8px 0;">
        <strong>Number:</strong> <?= e((string)$displayNumber) ?>
    </p>

    <?php if (!empty($profile['custom_requested_number'])): ?>
        <p style="margin:0 0 8px 0;">
            <strong>Requested custom number:</strong> <?= e((string)$profile['custom_requested_number']) ?><br>
            <strong>Assignment status:</strong> <?= e((string)$profile['number_assignment_status']) ?>
        </p>

        <?php if (!empty($profile['custom_request_notes'])): ?>
            <p style="margin:0;">
                <strong>Your request notes:</strong><br>
                <?= nl2br(e((string)$profile['custom_request_notes'])) ?>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <p style="margin:0;">
            <strong>Assignment status:</strong> <?= e((string)$profile['number_assignment_status']) ?>
        </p>
    <?php endif; ?>
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$profileId ?>">

    <label for="number_type_id">Number type</label>
    <select id="number_type_id" name="number_type_id" required>
        <option value="">Choose one</option>
        <?php foreach ($numberTypes as $type): ?>
            <option value="<?= (int)$type['id'] ?>" <?= (int)$profile['number_type_id'] === (int)$type['id'] ? 'selected' : '' ?>>
                <?= e((string)$type['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="playback_mode_id">Playback mode</label>
    <select id="playback_mode_id" name="playback_mode_id" required>
        <option value="">Choose one</option>
        <?php foreach ($playbackModes as $mode): ?>
            <option value="<?= (int)$mode['id'] ?>" <?= (int)$profile['playback_mode_id'] === (int)$mode['id'] ? 'selected' : '' ?>>
                <?= e((string)$mode['label']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="listing_text">Directory / listing text</label>
    <input
        id="listing_text"
        name="listing_text"
        maxlength="255"
        required
        value="<?= e((string)($profile['listing_text'] ?? '')) ?>"
        placeholder="Text shown with the number"
    >

    <label for="notes">Notes</label>
    <input
        id="notes"
        name="notes"
        maxlength="1000"
        value="<?= e((string)($profile['notes'] ?? '')) ?>"
        placeholder="Internal notes or context"
    >

    <label for="business_category_id">Business category</label>
    <select id="business_category_id" name="business_category_id">
        <option value="">None</option>
        <?php foreach ($businessCategories as $category): ?>
            <option value="<?= (int)$category['id'] ?>" <?= (int)$profile['business_category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                <?= e((string)$category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="forward_to_number_id">Forward to number</label>
    <select id="forward_to_number_id" name="forward_to_number_id">
        <option value="">Do not forward</option>
        <?php foreach ($forwardableNumbers as $number): ?>
            <option value="<?= (int)$number['id'] ?>" <?= (int)$profile['forward_to_number_id'] === (int)$number['id'] ? 'selected' : '' ?>>
                <?= e((string)$number['display_number']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div style="margin-top:16px;">
        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="publish_physical_directory" value="1" <?= !empty($profile['publish_physical_directory']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>Publish in the physical directory</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="publish_web_directory" value="1" <?= !empty($profile['publish_web_directory']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>Publish in the website directory</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="explicit_content_flag" value="1" <?= !empty($profile['explicit_content_flag']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>This content contains explicit or restricted material</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="family_friendly_version_available" value="1" <?= !empty($profile['family_friendly_version_available']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>A family-friendly version is available</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="is_voicemail_box" value="1" <?= !empty($profile['is_voicemail_box']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>This number is a voice mailbox</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="is_real_contact_number" value="1" <?= !empty($profile['is_real_contact_number']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>This is a real contact number for a person or business</span>
        </label>

        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
            <input type="checkbox" name="impersonation_warning_acknowledged" value="1" <?= !empty($profile['impersonation_warning_acknowledged']) ? 'checked' : '' ?> style="width:auto;margin-top:4px;">
            <span>
                I understand that intentionally impersonating another person, business, or organization is prohibited.
            </span>
        </label>
    </div>

    <label for="voicemail_pin">Voicemail PIN</label>
    <input
        id="voicemail_pin"
        name="voicemail_pin"
        type="password"
        inputmode="numeric"
        placeholder="Leave blank to keep current PIN"
    >

    <button type="submit">Save profile</button>
</form>

<div style="margin-top:24px;">
    <a href="reserve-number.php">Choose another number</a>
</div>

<?php html_footer(); ?>