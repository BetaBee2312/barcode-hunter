<?php
/** Leaderboard versi satu baris per sesi peserta. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';
require_once __DIR__ . '/includes/game_data.php';

$adminBase = bh_admin_base();
$activeMenu = 'leaderboard';
$pageTitle = 'Leaderboard';
$pageSubtitle = 'Peringkat pemain berdasarkan hasil scan';
$errors = [];
$ranked = [];
$branches = [];
$selectedBranch = null;
try {
    $branches = bh_game_branch_options($pdo);
    if (isset($_GET['cabang']) && $_GET['cabang'] !== '') {
        $parsed = filter_var($_GET['cabang'], FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) throw new InvalidArgumentException('Kode cabang tidak valid.');
        $selectedBranch = (int) $parsed;
    }
    $rows = bh_game_result_rows($pdo, $selectedBranch);
    $rankByBranch = [];
    foreach ($rows as $row) {
        $branchKey = (string) $row['cabang'];
        $rankByBranch[$branchKey] = ($rankByBranch[$branchKey] ?? 0) + 1;
        $row['peringkat'] = $rankByBranch[$branchKey];
        $ranked[] = $row;
    }
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Gagal membaca leaderboard. Periksa database V2.' : $e->getMessage();
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= bh_h($pageTitle) ?> | Barcode Hunting</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/admin.css"><link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/modules.css"></head><body>
<?php require __DIR__ . '/includes/sidebar.php'; ?><main class="admin-main"><?php require __DIR__ . '/includes/topbar.php'; ?><section class="page-content">
    <div class="module-heading"><div><h2>Peringkat pemain</h2><p>Peringkat dihitung per cabang: jumlah barcode berhasil terbanyak, lalu waktu aktif tercepat.</p></div><div class="module-stat"><i class="bi bi-trophy"></i><span><strong><?= number_format(count($ranked)) ?></strong><small>Sesi peserta</small></span></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= bh_h($error) ?></div><?php endforeach; ?>
    <?php if (!$errors): ?>
    <form method="get" class="module-toolbar"><div class="module-filters"><select class="form-select" name="cabang"><option value="">Semua cabang</option><?php foreach ($branches as $branch): ?><option value="<?= bh_h($branch['kode_cabang']) ?>" <?= (string) $selectedBranch === (string) $branch['kode_cabang'] ? 'selected' : '' ?>><?= bh_h($branch['nama_cabang']) ?> (<?= bh_h($branch['kode_cabang']) ?>)</option><?php endforeach; ?></select><button class="btn btn-outline-primary" type="submit"><i class="bi bi-filter"></i> Filter</button><a class="btn btn-light" href="<?= bh_h($adminBase) ?>/leaderboard.php">Reset</a></div></form>
    <section class="module-card module-list-card"><div class="table-responsive"><table class="table module-table leaderboard-table align-middle"><thead><tr><th>Rank</th><th>Pemain</th><th>Game</th><th>Cabang</th><th>Barcode</th><th>Waktu aktif</th><th>Status</th></tr></thead><tbody>
    <?php if (!$ranked): ?><tr><td colspan="7"><div class="module-empty"><i class="bi bi-trophy"></i><strong>Belum ada hasil permainan</strong><span>Leaderboard terisi setelah peserta berhasil scan barcode.</span></div></td></tr><?php else: foreach ($ranked as $row): $time = (int) $row['waktu_main_detik']; $timeLabel = sprintf('%02d:%02d', intdiv($time, 60), $time % 60); ?>
    <tr><td><span class="rank-number <?= (int) $row['peringkat'] === 1 ? 'rank-first' : '' ?>"><?= (int) $row['peringkat'] === 1 ? '<i class="bi bi-trophy-fill"></i>' : '#' . (int) $row['peringkat'] ?></span></td><td><strong class="d-block"><?= bh_h($row['nama']) ?></strong><small class="text-secondary"><?= bh_h($row['no_hp']) ?></small></td><td><?= bh_h($row['nama_game']) ?><small class="d-block text-secondary"><?= bh_h($row['id_game']) ?></small></td><td><?= bh_h($row['nama_cabang']) ?><small class="d-block text-secondary"><?= bh_h($row['cabang']) ?></small></td><td><strong><?= number_format((int) $row['total_scan']) ?>/<?= number_format((int) $row['barcode_list']) ?></strong><small class="d-block text-secondary"><?= number_format((int) $row['assigned_count']) ?> dialokasikan</small></td><td><?= bh_h($timeLabel) ?></td><td><span class="status-pill status-<?= bh_h($row['status_session']) ?>"><?= bh_h(ucfirst($row['status_session'])) ?></span></td></tr>
    <?php endforeach; endif; ?></tbody></table></div></section>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
