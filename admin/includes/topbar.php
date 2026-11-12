<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? 'Barcode Hunting Administrator';
$adminBase = $adminBase ?? bh_admin_base();
$adminDisplayName = (string) ($_SESSION['bh_admin_name'] ?? 'Administrator');
$adminRole = (string) ($_SESSION['bh_admin_role'] ?? 'admin');
?>
<header class="admin-topbar">
    <div class="topbar-left">
        <button class="mobile-menu-button" id="mobileMenuButton" type="button"
                aria-label="Buka menu navigasi" aria-controls="adminSidebar" aria-expanded="false">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div>
            <div class="topbar-breadcrumb">Administrator <span>/</span> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></div>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
    </div>
    <div class="topbar-right">
        <span class="admin-avatar"><i class="bi bi-person-fill"></i></span>
        <span class="admin-account"><strong><?= htmlspecialchars($adminDisplayName, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(ucfirst($adminRole), ENT_QUOTES, 'UTF-8') ?></small></span>
        <form method="post" action="<?= bh_h($adminBase) ?>/logout.php" class="admin-logout-form">
            <input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit" title="Keluar dari admin">
                <i class="bi bi-box-arrow-right me-1"></i><span>Keluar</span>
            </button>
        </form>
    </div>
</header>
