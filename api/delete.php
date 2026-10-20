<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$input = vaultora_json_body() ?: $_POST;
$mode = (string) ($input['mode'] ?? 'soft');
$type = (string) ($input['type'] ?? '');
$id = (int) ($input['id'] ?? 0);
$userId = (int) $user['id'];

if ($mode === 'empty') {
    vaultora_json(vaultora_empty_trash($userId));
}

if ($type === 'file') {
    $result = match ($mode) {
        'restore' => vaultora_restore_file($id, $userId),
        'purge' => vaultora_purge_file($id, $userId),
        default => vaultora_soft_delete_file($id, $userId),
    };
    vaultora_json($result);
}

if ($type === 'folder') {
    $result = match ($mode) {
        'restore' => vaultora_restore_folder($id, $userId),
        'purge' => vaultora_purge_folder($id, $userId),
        default => vaultora_soft_delete_folder($id, $userId),
    };
    vaultora_json($result);
}

vaultora_json(['success' => false, 'message' => 'Invalid request.'], 400);
