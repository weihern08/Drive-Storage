<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$vaultUser = vaultora_require_login();
$userId = (int) $vaultUser['id'];

$versionId = vaultora_get_int('version_id');
if ($versionId > 0) {
    vaultora_download_version($versionId, $userId);
}

$fileId = vaultora_get_int('id');
$file = vaultora_get_file($fileId, $userId);
if (!$file) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

vaultora_send_file_download($file, $userId, false);
