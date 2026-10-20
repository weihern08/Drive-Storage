<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$fileId = vaultora_get_int('id');
if ($fileId <= 0) {
    vaultora_redirect('files.php');
}
$file = vaultora_get_file($fileId, (int) $vaultUser['id']);
if (!$file) {
    vaultora_flash('danger', 'File not found.');
    vaultora_redirect('files.php');
}

$pageTitle = 'Versions';
$folderIdForModals = $file['folder_id'] !== null ? (int) $file['folder_id'] : null;
require __DIR__ . '/includes/layout/header.php';
$details = vaultora_file_details($fileId, (int) $vaultUser['id']);
?>

<div class="page-hero">
    <div>
        <h1>Version history</h1>
        <p><?= e($file['original_name']) ?></p>
    </div>
    <a class="btn btn-outline-vault" href="<?= e(vaultora_url('files.php' . ($folderIdForModals ? '?folder=' . $folderIdForModals : ''))) ?>">Back</a>
</div>

<div class="panel-card">
<?php require __DIR__ . '/includes/partials/file_details.php'; ?>
</div>

<?php require __DIR__ . '/includes/layout/footer.php'; ?>
