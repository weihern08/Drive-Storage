<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();
$page = max(1, vaultora_get_int('page', 1));
$perPage = 40;
$total = (int) vaultora_db()->query('SELECT COUNT(*) AS c FROM vault_activity_logs')->fetch()['c'];
$pager = vaultora_paginate($total, $page, $perPage);
$logs = vaultora_fetch_activity($pager['per_page'], $pager['offset']);

$pageTitle = 'Activity logs';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Activity logs</h1>
        <p>Audit trail for authentication, file operations, and admin changes.</p>
    </div>
</div>

<div class="panel-card">
    <div class="table-responsive">
        <table class="table-vault">
            <thead>
            <tr><th>When</th><th>User</th><th>Action</th><th>Resource</th><th>Description</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['created_at']) ?></td>
                    <td><?= e($log['display_name'] ?? '—') ?></td>
                    <td><strong><?= e($log['action']) ?></strong></td>
                    <td><?= e(trim(($log['resource_type'] ?? '') . ' ' . ($log['resource_id'] ?? ''))) ?></td>
                    <td><?= e($log['description'] ?? '') ?></td>
                    <td><?= e($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
