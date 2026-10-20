<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$type = vaultora_post_string('type');
$id = vaultora_post_int('id');
$destRaw = $_POST['destination_id'] ?? '';
$dest = ($destRaw === '' || $destRaw === null) ? null : (int) $destRaw;

if ($type === 'file') {
    vaultora_json(vaultora_move_file($id, (int) $user['id'], $dest));
}
if ($type === 'folder') {
    vaultora_json(vaultora_move_folder($id, (int) $user['id'], $dest));
}

vaultora_json(['success' => false, 'message' => 'Invalid request.'], 400);
