<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

if (vaultora_request_method() !== 'POST') {
    vaultora_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$folderId = vaultora_post_int('folder_id') ?: null;
$replaceId = vaultora_post_int('replace_file_id') ?: null;
$results = [];

$files = $_FILES['files'] ?? null;
if (!$files) {
    vaultora_json(['success' => false, 'message' => 'No files received.', 'results' => []]);
}

// Normalize multi-upload structure
$items = [];
if (is_array($files['name'])) {
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $items[] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }
} else {
    $items[] = $files;
}

$ok = true;
foreach ($items as $item) {
    // Refresh user for quota after each upload
    $fresh = vaultora_current_user() ?: $user;
    // Force refresh storage used
    $fresh['storage_used_bytes'] = vaultora_recalculate_storage((int) $fresh['id']);
    $result = vaultora_upload_file($fresh, $item, $folderId, $replaceId);
    $results[] = [
        'name' => $item['name'] ?? 'file',
        'success' => $result['success'],
        'message' => $result['message'],
        'id' => $result['id'] ?? null,
    ];
    if (!$result['success']) {
        $ok = false;
    }
    // Only first file can be a revision target
    $replaceId = null;
}

vaultora_json([
    'success' => $ok,
    'message' => $ok ? 'Upload complete.' : 'Some uploads failed.',
    'results' => $results,
]);
