<?php
declare(strict_types=1);
$vaultUser = $vaultUser ?? vaultora_current_user();
$pageTitle = $pageTitle ?? vaultora_app_name();
$bodyClass = $bodyClass ?? '';
$isAdminArea = $isAdminArea ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(vaultora_csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e(vaultora_app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(vaultora_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="app-body <?= e($bodyClass) ?>" data-base="<?= e(vaultora_url('')) ?>">
<div class="app-frame">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="app-main">
<?php require __DIR__ . '/topbar.php'; ?>
<main class="app-content" id="main-content">
<?php foreach (vaultora_get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
