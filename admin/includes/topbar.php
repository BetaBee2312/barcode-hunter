<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? 'Barcode Hunting Administrator';
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
        <span class="admin-account"><strong>Administrator</strong><small>Admin Panel</small></span>
    </div>
</header>
