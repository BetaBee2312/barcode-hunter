<?php
/** Sidebar navigasi admin. Variabel opsional: $activeMenu, $adminBase. */
$activeMenu = $activeMenu ?? 'dashboard';
$adminBase = $adminBase ?? '/admin';
$navigation = [
    'main' => [
        ['key' => 'dashboard', 'label' => 'Juara Cabang', 'icon' => 'bi-trophy', 'url' => $adminBase . '/index.php'],
    ],
    'manage' => [
        ['key' => 'branches', 'label' => 'Data Cabang', 'icon' => 'bi-shop', 'url' => $adminBase . '/cabang.php'],
        ['key' => 'barcode', 'label' => 'Data Barcode', 'icon' => 'bi-upc-scan', 'url' => $adminBase . '/barcode.php'],
        ['key' => 'game', 'label' => 'Konfigurasi Game', 'icon' => 'bi-controller', 'url' => $adminBase . '/game.php'],
        ['key' => 'users', 'label' => 'Peserta', 'icon' => 'bi-people', 'url' => $adminBase . '/users.php'],
    ],
    'report' => [
        ['key' => 'leaderboard', 'label' => 'Leaderboard', 'icon' => 'bi-bar-chart', 'url' => $adminBase . '/leaderboard.php'],
        ['key' => 'reports', 'label' => 'Laporan', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => $adminBase . '/reports.php'],
    ],
];
?>
<aside class="admin-sidebar" id="adminSidebar" aria-hidden="true" aria-label="Navigasi admin">
    <a class="admin-brand" href="<?= htmlspecialchars($adminBase . '/index.php', ENT_QUOTES, 'UTF-8') ?>">
        <span class="admin-brand-icon"><i class="bi bi-upc-scan"></i></span>
        <span class="admin-brand-copy">
            <strong>BARCODE HUNT</strong>
            <small>Administrator</small>
        </span>
    </a>

    <div class="sidebar-label">Overview</div>
    <?php foreach ($navigation['main'] as $item): ?>
        <?php $isAvailable = is_file(dirname(__DIR__) . '/' . basename($item['url'])); ?>
        <a class="sidebar-link <?= $activeMenu === $item['key'] ? 'active' : '' ?> <?= !$isAvailable ? 'disabled' : '' ?>"
           href="<?= $isAvailable ? htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') : '#' ?>"
           <?= !$isAvailable ? 'aria-disabled="true" title="Halaman belum dibuat"' : '' ?>>
            <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-label">Pengelolaan</div>
    <?php foreach ($navigation['manage'] as $item): ?>
        <?php $isAvailable = is_file(dirname(__DIR__) . '/' . basename($item['url'])); ?>
        <a class="sidebar-link <?= $activeMenu === $item['key'] ? 'active' : '' ?> <?= !$isAvailable ? 'disabled' : '' ?>"
           href="<?= $isAvailable ? htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') : '#' ?>"
           <?= !$isAvailable ? 'aria-disabled="true" title="Halaman belum dibuat"' : '' ?>>
            <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!is_file(dirname(__DIR__) . '/' . basename($item['url']))): ?>
                <span class="sidebar-soon">Soon</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-label">Monitoring</div>
    <?php foreach ($navigation['report'] as $item): ?>
        <?php $isAvailable = is_file(dirname(__DIR__) . '/' . basename($item['url'])); ?>
        <a class="sidebar-link <?= $activeMenu === $item['key'] ? 'active' : '' ?> <?= !$isAvailable ? 'disabled' : '' ?>"
           href="<?= $isAvailable ? htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') : '#' ?>"
           <?= !$isAvailable ? 'aria-disabled="true" title="Halaman belum dibuat"' : '' ?>>
            <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (!is_file(dirname(__DIR__) . '/' . basename($item['url']))): ?>
                <span class="sidebar-soon">Soon</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-footer">
        <span class="sidebar-footer-mark"><i class="bi bi-shield-check"></i></span>
        <span><strong>Barcode Hunting</strong><small>Multi-cabang system</small></span>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
