<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$vaultUser = vaultora_require_admin();
$search = vaultora_get_string('q');
$page = max(1, vaultora_get_int('page', 1));
$total = vaultora_admin_count_users($search);
$pager = vaultora_paginate($total, $page, 20);
$users = vaultora_admin_list_users($search, $pager['per_page'], $pager['offset']);

$pageTitle = 'Users';
$isAdminArea = true;
require dirname(__DIR__) . '/includes/layout/header.php';
?>

<div class="page-hero">
    <div>
        <h1>Users</h1>
        <p>Create accounts, manage quotas, and control access.</p>
    </div>
    <a class="btn btn-vault" href="<?= e(vaultora_url('admin/user_create.php')) ?>"><i class="fa-solid fa-user-plus"></i> Create user</a>
</div>

<div class="panel-card">
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-8"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search name or email"></div>
        <div class="col-md-4"><button class="btn btn-outline-vault w-100" type="submit">Search</button></div>
    </form>
    <div class="table-responsive">
        <table class="table-vault">
            <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Role</th><th>Status</th>
                <th>Storage used</th><th>Storage limit</th><th>Created</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['display_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['role']) ?></td>
                    <td><span class="badge text-bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($u['status']) ?></span></td>
                    <td><?= e(vaultora_format_bytes((int) $u['storage_used_bytes'])) ?></td>
                    <td><?= e(vaultora_format_bytes((int) $u['storage_quota_bytes'])) ?></td>
                    <td><?= e($u['created_at']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-vault" href="<?= e(vaultora_url('admin/user_edit.php?id=' . (int) $u['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/layout/footer.php'; ?>
