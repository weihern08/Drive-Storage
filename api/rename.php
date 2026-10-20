<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$type = vaultora_post_string('type');
$id = vaultora_post_int('id');
$name = vaultora_post_string('name');

if ($type === 'file') {
    vaultora_json(vaultora_rename_file($id, (int) $user['id'], $name));
}
if ($type === 'folder') {
    vaultora_json(vaultora_rename_folder($id, (int) $user['id'], $name));
}

vaultora_json(['success' => false, 'message' => 'Invalid request.'], 400);
