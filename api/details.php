<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
$fileId = vaultora_get_int('id');
$details = vaultora_file_details($fileId, (int) $user['id']);
if (!$details) {
    vaultora_json(['success' => false, 'message' => 'File not found.'], 404);
}

ob_start();
require dirname(__DIR__) . '/includes/partials/file_details.php';
$html = (string) ob_get_clean();

vaultora_json(['success' => true, 'html' => $html]);
