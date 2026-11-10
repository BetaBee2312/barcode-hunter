<?php
/** Dashboard: satu juara sementara/akhir untuk setiap cabang. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';
require_once __DIR__ . '/includes/game_data.php';

$adminBase = bh_admin_base();
$activeMenu = 'dashboard';
$pageTitle = 'Juara Cabang';
$pageSubtitle = 'Peringkat terbaik dari setiap cabang';
$errors = [];
$warnings = [];
$branchOptions = [];
$winners = [];
$selectedBranch = null;

try {
    if (!bh_table_exists($pdo, 'tb_game')) throw new RuntimeException('Tabel tb_game belum ditemukan.');
    $branchOptions = bh_game_branch_options($pdo);
    if (isset($_GET['cabang']) && $_GET['cabang'] !== '') {
        $parsed = filter_var($_GET['cabang'], FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) throw new InvalidArgumentException('Kode cabang pada URL tidak valid.');
        $selectedBranch = (int) $parsed;
    }
    $rows = bh_game_result_rows($pdo, $selectedBranch);
    foreach ($rows as $row) {
        if ((int) $row['total_scan'] < 1) continue;
        $key = (string) $row['cabang'];
        if (!isset($winners[$key])) $winners[$key] = $row;
    }
    if (!$rows) $warnings[] = 'Belum ada sesi peserta. Juara akan muncul otomatis setelah konsumen mendaftar dan mulai memindai barcode.';
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Gagal membaca data juara. Periksa struktur database V2.' : $e->getMessage();
}
$branchCount = count($branchOptions);
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= bh_h($pageTitle) ?> | Barcode Hunting</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/admin.css">
</head><body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main"><?php require __DIR__ . '/includes/topbar.php'; ?><section class="page-content">
    <div class="page-heading"><div><h2>Juara 1 setiap cabang</h2><p>Prioritas: jumlah barcode terbanyak, lalu waktu aktif tercepat.</p></div>
        <form class="branch-filter" method="get"><label for="branchFilter"><i class="bi bi-funnel me-1"></i>Cabang</label><select name="cabang" id="branchFilter" onchange="this.form.submit()"><option value="">Semua cabang</option><?php foreach ($branchOptions as $branch): ?><option value="<?= bh_h($branch['kode_cabang']) ?>" <?= (string) $selectedBranch === (string) $branch['kode_cabang'] ? 'selected' : '' ?>><?= bh_h($branch['nama_cabang']) ?> (<?= bh_h($branch['kode_cabang']) ?>)</option><?php endforeach; ?></select></form>
    </div>
    <?php foreach ($errors as $message): ?><div class="admin-alert danger"><i class="bi bi-exclamation-triangle me-2"></i><?= bh_h($message) ?></div><?php endforeach; ?>
    <?php foreach ($warnings as $message): ?><div class="admin-alert"><i class="bi bi-info-circle me-2"></i><?= bh_h($message) ?></div><?php endforeach; ?>
    <?php if ($errors): ?>
        <div class="empty-state"><i class="bi bi-database-exclamation"></i><strong>Dashboard belum bisa membaca hasil permainan</strong><p>Periksa SQL upgrade V2 dan struktur tabel.</p></div>
    <?php elseif (!$winners): ?>
        <div class="empty-state"><i class="bi bi-trophy"></i><strong>Belum ada juara</strong><p>Juara cabang akan tampil setelah peserta berhasil memindai barcode.</p></div>
    <?php else: ?>
    <div class="winner-grid">
        <?php foreach ($winners as $branch => $winner):
            $seconds = (int) $winner['waktu_main_detik'];
            $timeText = sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
            $isCompleted = $winner['status_session'] === 'completed';
        ?>
        <article class="winner-card">
            <div class="winner-card-top"><div><div class="branch-name"><?= bh_h($winner['nama_cabang']) ?></div><div class="branch-code">KODE CABANG: <?= bh_h($branch) ?> · <?= bh_h($winner['nama_game']) ?></div></div><span class="winner-rank"><i class="bi bi-trophy-fill"></i> Juara 1</span></div>
            <div class="winner-person"><span class="winner-medal"><i class="bi bi-award-fill"></i></span><div class="winner-person-copy"><strong><?= bh_h($winner['nama']) ?></strong><small><?= bh_h($winner['id_user']) ?> · <?= bh_h($winner['no_hp']) ?></small></div></div>
            <div class="winner-metrics"><div><span class="metric-label">Barcode terkumpul</span><strong class="metric-value"><?= number_format((int) $winner['total_scan']) ?> / <?= number_format((int) $winner['barcode_list']) ?></strong></div><div><span class="metric-label">Waktu bermain</span><strong class="metric-value"><?= bh_h($timeText) ?></strong></div><div><span class="metric-label">Status</span><strong class="metric-value"><?= bh_h(ucfirst($winner['status_session'])) ?></strong></div></div>
            <span class="winner-status <?= $isCompleted ? 'completed' : '' ?>"><?= $isCompleted ? 'Hasil akhir' : 'Pemimpin sementara' ?></span>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
