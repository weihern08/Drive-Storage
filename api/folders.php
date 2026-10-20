<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
$userId = (int) $user['id'];

if (vaultora_request_method() === 'GET' && vaultora_get_string('action') === 'tree') {
    $exclude = vaultora_get_int('exclude') ?: null;
    vaultora_json(['success' => true, 'tree' => vaultora_folder_tree($userId, $exclude)]);
}

vaultora_require_csrf();
if (vaultora_request_method() !== 'POST') {
    vaultora_json(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$action = vaultora_post_string('action', 'create');

if ($action === 'create') {
    $parent = vaultora_post_int('parent_id') ?: null;
    $result = vaultora_create_folder($userId, vaultora_post_string('name'), $parent, vaultora_post_string('color', '#0d9488'));
    vaultora_json($result, $result['success'] ? 200 : 422);
}

vaultora_json(['success' => false, 'message' => 'Unknown action.'], 400);
