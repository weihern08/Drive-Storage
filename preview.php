<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];
$fileId = vaultora_get_int('id');
$file = vaultora_get_file($fileId, $userId);

if (!$file) {
    vaultora_flash('danger', 'File not found.');
    vaultora_redirect('files.php');
}

$inline = vaultora_get_string('raw') === '1';
if ($inline) {
    if (!vaultora_is_previewable($file['extension'], $file['mime_type'])) {
        http_response_code(415);
        echo 'Preview unavailable.';
        exit;
    }
    vaultora_send_file_download($file, $userId, true);
}

$pageTitle = 'Preview · ' . $file['original_name'];
$folderIdForModals = $file['folder_id'] !== null ? (int) $file['folder_id'] : null;
require __DIR__ . '/includes/layout/header.php';

$ext = strtolower((string) $file['extension']);
$rawUrl = vaultora_url('preview.php?id=' . $fileId . '&raw=1');
$canPreview = vaultora_is_previewable($ext, $file['mime_type']);
?>

<div class="page-hero">
    <div>
        <h1><?= e($file['original_name']) ?></h1>
        <p><?= e(vaultora_format_bytes((int) $file['size_bytes'])) ?> · <?= e($file['mime_type']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-vault" href="<?= e(vaultora_url('download.php?id=' . $fileId)) ?>">Download</a>
        <a class="btn btn-outline-vault" href="<?= e(vaultora_url('files.php' . ($folderIdForModals ? '?folder=' . $folderIdForModals : ''))) ?>">Back</a>
    </div>
</div>

<div class="panel-card">
<?php if (!$canPreview): ?>
    <div class="empty-state">
        <i class="fa-regular fa-eye-slash"></i>
        <h3>Preview unavailable</h3>
        <p>You can download this file to open it on your device.</p>
        <a class="btn btn-vault" href="<?= e(vaultora_url('download.php?id=' . $fileId)) ?>">Download file</a>
    </div>
<?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) || str_starts_with($file['mime_type'], 'image/')): ?>
    <div class="text-center"><img class="preview-image" src="<?= e($rawUrl) ?>" alt="<?= e($file['original_name']) ?>"></div>
<?php elseif ($ext === 'pdf' || $file['mime_type'] === 'application/pdf'): ?>
    <iframe class="preview-frame" src="<?= e($rawUrl) ?>" title="PDF preview"></iframe>
<?php elseif (in_array($ext, ['txt', 'md', 'csv', 'json', 'xml', 'log'], true) || str_starts_with($file['mime_type'], 'text/')): ?>
    <?php
    $path = vaultora_resolve_stored_path($userId, $file['stored_name']);
    $content = ($path && is_file($path)) ? file_get_contents($path, false, null, 0, 200000) : '';
    vaultora_touch_file_access($fileId, $userId);
    ?>
    <pre class="p-3 bg-dark text-light rounded-4" style="max-height:70vh;overflow:auto;white-space:pre-wrap"><?= e((string) $content) ?></pre>
<?php elseif (in_array($ext, ['mp4', 'webm'], true) || str_starts_with($file['mime_type'], 'video/')): ?>
    <video class="w-100 rounded-4" controls src="<?= e($rawUrl) ?>"></video>
<?php elseif (in_array($ext, ['mp3', 'wav', 'ogg'], true) || str_starts_with($file['mime_type'], 'audio/')): ?>
    <audio class="w-100" controls src="<?= e($rawUrl) ?>"></audio>
<?php else: ?>
    <div class="empty-state">
        <h3>Preview unavailable</h3>
        <p>You can download this file to open it on your device.</p>
    </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
