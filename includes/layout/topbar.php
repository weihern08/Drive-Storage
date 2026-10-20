<?php
declare(strict_types=1);
?>
<header class="app-topbar">
    <div class="topbar-left">
        <button type="button" class="btn btn-icon d-lg-none" id="sidebarOpen" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <form class="topbar-search d-none d-md-flex" action="<?= e(vaultora_url('search.php')) ?>" method="get" role="search">
            <label class="visually-hidden" for="topSearch">Search files</label>
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="topSearch" type="search" name="q" placeholder="Search your vault..." value="<?= e(vaultora_get_string('q')) ?>">
        </form>
    </div>
    <div class="topbar-right">
        <?php if (!$isAdminArea): ?>
            <button type="button" class="btn btn-vault btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
            </button>
            <button type="button" class="btn btn-outline-vault btn-sm" data-bs-toggle="modal" data-bs-target="#folderModal">
                <i class="fa-solid fa-folder-plus"></i> New folder
            </button>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn user-chip dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-avatar"><?= e(mb_strtoupper(mb_substr($vaultUser['display_name'] ?? 'U', 0, 1))) ?></span>
                <span class="d-none d-sm-inline"><?= e($vaultUser['display_name'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if (!($isAdminArea ?? false)): ?>
                    <li><a class="dropdown-item" href="<?= e(vaultora_url('profile.php')) ?>"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><a class="dropdown-item" href="<?= e(vaultora_url('logout.php')) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</header>
