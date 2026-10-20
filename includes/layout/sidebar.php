<?php
declare(strict_types=1);
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdminArea = $isAdminArea ?? false;
$storage = $vaultUser ? vaultora_storage_stats($vaultUser) : null;

function vaultora_nav_active(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Primary">
    <div class="sidebar-brand">
        <a href="<?= e(vaultora_url($isAdminArea ? 'admin/index.php' : 'dashboard.php')) ?>" class="brand-mark compact">
            <span class="brand-glyph"><i class="fa-solid fa-cube"></i></span>
            <span>
                <span class="brand-name"><?= e(vaultora_app_name()) ?></span>
                <span class="brand-tag"><?= $isAdminArea ? 'Admin console' : 'Personal vault' ?></span>
            </span>
        </a>
        <button type="button" class="sidebar-close d-lg-none" id="sidebarClose" aria-label="Close menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php if ($isAdminArea): ?>
            <a class="nav-link <?= vaultora_nav_active('index.php', $current) ?>" href="<?= e(vaultora_url('admin/index.php')) ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
            <a class="nav-link <?= vaultora_nav_active('users.php', $current) || vaultora_nav_active('user_create.php', $current) || vaultora_nav_active('user_edit.php', $current) ? 'active' : '' ?>" href="<?= e(vaultora_url('admin/users.php')) ?>"><i class="fa-solid fa-users"></i> Users</a>
            <a class="nav-link <?= vaultora_nav_active('files.php', $current) ?>" href="<?= e(vaultora_url('admin/files.php')) ?>"><i class="fa-solid fa-folder-tree"></i> System files</a>
            <a class="nav-link <?= vaultora_nav_active('logs.php', $current) ?>" href="<?= e(vaultora_url('admin/logs.php')) ?>"><i class="fa-solid fa-clipboard-list"></i> Activity logs</a>
            <a class="nav-link <?= vaultora_nav_active('settings.php', $current) ?>" href="<?= e(vaultora_url('admin/settings.php')) ?>"><i class="fa-solid fa-sliders"></i> Settings</a>
            <div class="nav-divider"></div>
            <a class="nav-link" href="<?= e(vaultora_url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
        <?php else: ?>
            <a class="nav-link <?= vaultora_nav_active('dashboard.php', $current) ?>" href="<?= e(vaultora_url('dashboard.php')) ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a class="nav-link <?= vaultora_nav_active('files.php', $current) ?>" href="<?= e(vaultora_url('files.php')) ?>"><i class="fa-solid fa-folder-open"></i> My files</a>
            <a class="nav-link <?= vaultora_nav_active('recent.php', $current) ?>" href="<?= e(vaultora_url('recent.php')) ?>"><i class="fa-solid fa-clock-rotate-left"></i> Recent</a>
            <a class="nav-link <?= vaultora_nav_active('starred.php', $current) ?>" href="<?= e(vaultora_url('starred.php')) ?>"><i class="fa-solid fa-star"></i> Starred</a>
            <a class="nav-link <?= vaultora_nav_active('tags.php', $current) ?>" href="<?= e(vaultora_url('tags.php')) ?>"><i class="fa-solid fa-tags"></i> Tags</a>
            <a class="nav-link <?= vaultora_nav_active('trash.php', $current) ?>" href="<?= e(vaultora_url('trash.php')) ?>"><i class="fa-solid fa-trash"></i> Trash</a>
            <a class="nav-link <?= vaultora_nav_active('search.php', $current) ?>" href="<?= e(vaultora_url('search.php')) ?>"><i class="fa-solid fa-magnifying-glass"></i> Search</a>
            <div class="nav-divider"></div>
            <a class="nav-link <?= vaultora_nav_active('profile.php', $current) ?>" href="<?= e(vaultora_url('profile.php')) ?>"><i class="fa-solid fa-user"></i> Profile</a>
        <?php endif; ?>
    </nav>

    <?php if ($storage && !$isAdminArea): ?>
    <div class="sidebar-storage">
        <div class="d-flex justify-content-between small mb-1">
            <span>Storage</span>
            <span><?= e((string) $storage['percent']) ?>%</span>
        </div>
        <div class="progress storage-progress" role="progressbar" aria-valuenow="<?= e((string) $storage['percent']) ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= e((string) $storage['percent']) ?>%"></div>
        </div>
        <div class="small text-secondary mt-1"><?= e($storage['used_label']) ?> / <?= e($storage['quota_label']) ?></div>
    </div>
    <?php endif; ?>
</aside>
<div class="sidebar-backdrop d-lg-none" id="sidebarBackdrop" hidden></div>
