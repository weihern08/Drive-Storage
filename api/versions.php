<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$input = vaultora_json_body() ?: $_POST;
$action = (string) ($input['action'] ?? '');
$versionId = (int) ($input['version_id'] ?? 0);

if ($action === 'restore') {
    vaultora_json(vaultora_restore_version($versionId, (int) $user['id']));
}

vaultora_json(['success' => false, 'message' => 'Unknown action.'], 400);
