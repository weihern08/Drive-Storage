<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();

if (vaultora_request_method() === 'POST') {
    vaultora_require_csrf();

    $appName = trim(vaultora_post_string('app_name'));
    $maxUpload = vaultora_parse_bytes(vaultora_post_string('max_upload'));
    $defaultQuota = vaultora_parse_bytes(vaultora_post_string('default_quota'));
    $trashDays = vaultora_post_int('trash_retention_days');
    $extensions = strtolower(vaultora_post_string('allowed_extensions'));

    $errors = [];
    if ($appName === '' || mb_strlen($appName) > 80) {
        $errors[] = 'Application name is invalid.';
    }
    if ($maxUpload < 1024 * 1024) {
        $errors[] = 'Maximum upload size must be at least 1 MB.';
    }
    if ($defaultQuota < 1024 * 1024) {
        $errors[] = 'Default quota must be at least 1 MB.';
    }
    if ($trashDays < 1 || $trashDays > 3650) {
        $errors[] = 'Trash retention must be between 1 and 3650 days.';
    }

    $extList = array_values(array_filter(array_map('trim', explode(',', $extensions))));
    foreach ($extList as $ext) {
        if (vaultora_is_blocked_extension($ext)) {
            $errors[] = 'Blocked extension cannot be allowed: .' . $ext;
            break;
        }
    }

    if ($errors) {
        foreach ($errors as $err) {
            vaultora_flash('danger', $err);
        }
    } else {
        vaultora_set_setting('app_name', $appName);
        vaultora_set_setting('max_upload_bytes', (string) $maxUpload);
        vaultora_set_setting('default_quota_bytes', (string) $defaultQuota);
        vaultora_set_setting('trash_retention_days', (string) $trashDays);
        vaultora_set_setting('allowed_extensions', implode(',', $extList));
        vaultora_log_activity((int) $vaultUser['id'], 'update_settings', 'settings', null, 'Updated system settings');
        vaultora_flash('success', 'Settings saved.');
    }
    vaultora_redirect('admin/settings.php');
}

$settings = vaultora_all_settings();
$pageTitle = 'Settings';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>System settings</h1>
        <p>Configure application defaults and upload policy.</p>
    </div>
</div>

<div class="panel-card" style="max-width:820px">
    <form method="post" class="vstack gap-3">
        <?= vaultora_csrf_field() ?>
        <div>
            <label class="form-label" for="app_name">Application name</label>
            <input class="form-control" id="app_name" name="app_name" value="<?= e($settings['app_name'] ?? 'Vaultora') ?>" required>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="max_upload">Maximum upload size</label>
                <input class="form-control" id="max_upload" name="max_upload" value="<?= e(vaultora_format_bytes((int) ($settings['max_upload_bytes'] ?? VAULTORA_DEFAULT_MAX_UPLOAD), 0)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_quota">Default user storage</label>
                <input class="form-control" id="default_quota" name="default_quota" value="<?= e(vaultora_format_bytes((int) ($settings['default_quota_bytes'] ?? VAULTORA_DEFAULT_QUOTA), 0)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="trash_retention_days">Trash retention (days)</label>
                <input class="form-control" type="number" min="1" max="3650" id="trash_retention_days" name="trash_retention_days" value="<?= e($settings['trash_retention_days'] ?? '30') ?>">
            </div>
        </div>
        <div>
            <label class="form-label" for="allowed_extensions">Allowed file extensions</label>
            <textarea class="form-control" id="allowed_extensions" name="allowed_extensions" rows="5"><?= e($settings['allowed_extensions'] ?? implode(',', VAULTORA_DEFAULT_ALLOWED_EXTENSIONS)) ?></textarea>
            <div class="form-text">Comma-separated. Dangerous executable/server extensions are always blocked.</div>
        </div>
        <button class="btn btn-vault align-self-start" type="submit">Save settings</button>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
