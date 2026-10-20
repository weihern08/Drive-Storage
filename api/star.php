<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$input = vaultora_json_body() ?: $_POST;
$type = (string) ($input['type'] ?? '');
$id = (int) ($input['id'] ?? 0);
$starred = !empty($input['starred']);

if ($type === 'file') {
    vaultora_json(vaultora_set_file_star($id, (int) $user['id'], $starred));
}
if ($type === 'folder') {
    vaultora_json(vaultora_set_folder_star($id, (int) $user['id'], $starred));
}

vaultora_json(['success' => false, 'message' => 'Invalid request.'], 400);
