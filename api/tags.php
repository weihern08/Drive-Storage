<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = vaultora_require_login();
vaultora_require_csrf();

$input = vaultora_json_body() ?: $_POST;
$action = (string) ($input['action'] ?? '');
$userId = (int) $user['id'];
$fileId = (int) ($input['file_id'] ?? 0);
$tagId = (int) ($input['tag_id'] ?? 0);

$result = match ($action) {
    'create' => vaultora_create_tag($userId, (string) ($input['name'] ?? ''), (string) ($input['color'] ?? '#0d9488')),
    'delete' => vaultora_delete_tag($tagId, $userId),
    'attach' => vaultora_attach_tag($fileId, $tagId, $userId),
    'detach' => vaultora_detach_tag($fileId, $tagId, $userId),
    default => ['success' => false, 'message' => 'Unknown action.'],
};

if ($result['success'] && $fileId > 0 && in_array($action, ['attach', 'detach', 'create'], true)) {
    if ($action === 'create' && !empty($result['id'])) {
        vaultora_attach_tag($fileId, (int) $result['id'], $userId);
    }
    $result['html'] = vaultora_render_file_details_html($fileId, $userId);
}

vaultora_json($result);

function vaultora_render_file_details_html(int $fileId, int $userId): string
{
    ob_start();
    $details = vaultora_file_details($fileId, $userId);
    if (!$details) {
        echo 'Not found.';
        return (string) ob_get_clean();
    }
    require dirname(__DIR__) . '/includes/partials/file_details.php';
    return (string) ob_get_clean();
}
