<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();
$stats = vaultora_admin_stats();
$recentLogs = vaultora_fetch_activity(10);
$recentUsers = vaultora_admin_list_users('', 5, 0);

$pageTitle = 'Admin dashboard';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Admin console</h1>
        <p>System health, users, and security activity.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><i class="fa-solid fa-users"></i><div class="stat-label">Total users</div><div class="stat-value"><?= (int) $stats['users'] ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-user-check"></i><div class="stat-label">Active</div><div class="stat-value"><?= (int) $stats['active'] ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-user-slash"></i><div class="stat-label">Inactive</div><div class="stat-value"><?= (int) $stats['inactive'] ?></div></div>
    <div class="stat-card"><i class="fa-solid fa-file"></i><div class="stat-label">Files</div><div class="stat-value"><?= (int) $stats['files'] ?></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="panel-card">
            <h2>Storage</h2>
            <p class="mb-1"><?= e(vaultora_format_bytes($stats['storage'])) ?> used across all accounts</p>
            <p class="text-secondary small mb-3"><?= e(vaultora_format_bytes($stats['capacity'])) ?> total allocated capacity</p>
            <?php
            $pct = $stats['capacity'] > 0 ? min(100, round(($stats['storage'] / $stats['capacity']) * 100, 1)) : 0;
            ?>
            <div class="progress storage-progress"><div class="progress-bar" style="width:<?= e((string) $pct) ?>%"></div></div>
        </div>
        <div class="panel-card">
            <h2>Recent users</h2>
            <ul class="list-unstyled mb-0">
                <?php foreach ($recentUsers as $u): ?>
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span><?= e($u['display_name']) ?><br><span class="small text-secondary"><?= e($u['email']) ?></span></span>
                        <span class="badge text-bg-light"><?= e($u['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel-card">
            <div class="d-flex justify-content-between mb-3"><h2 class="mb-0">Security &amp; activity</h2><a href="<?= e(vaultora_url('admin/logs.php')) ?>">View logs</a></div>
            <?php if (!$recentLogs): ?>
                <p class="text-secondary mb-0">No activity recorded yet.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($recentLogs as $log): ?>
                        <li class="py-2 border-bottom">
                            <strong><?= e($log['action']) ?></strong>
                            <div class="small text-secondary"><?= e($log['display_name'] ?? 'System') ?> · <?= e($log['created_at']) ?></div>
                            <div class="small"><?= e($log['description'] ?? '') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
