<?php
/** Rekap peserta dan aktivitas per cabang + export CSV. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';
require_once __DIR__ . '/includes/game_data.php';

$adminBase = bh_admin_base();
$activeMenu = 'reports';
$pageTitle = 'Laporan';
$pageSubtitle = 'Rekap peserta dan hasil permainan per cabang';
$errors = [];
$summary = [];
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
    $grouped = [];
    foreach ($rows as $row) {
        $key = (string) $row['cabang'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'cabang' => $row['cabang'], 'nama_cabang' => $row['nama_cabang'],
                'total_game' => 0, 'total_peserta' => 0, 'total_scan' => 0,
                'total_selesai' => 0, 'total_aktif' => 0, 'total_pause' => 0,
                'total_expired' => 0,
            ];
        }
        $grouped[$key]['total_game']++;
        $grouped[$key]['total_peserta']++;
        $grouped[$key]['total_scan'] += (int) $row['total_scan'];
        if ($row['status_session'] === 'completed') $grouped[$key]['total_selesai']++;
        if ($row['status_session'] === 'active') $grouped[$key]['total_aktif']++;
        if ($row['status_session'] === 'paused') $grouped[$key]['total_pause']++;
        if ($row['status_session'] === 'expired') $grouped[$key]['total_expired']++;
    }
    $summary = array_values($grouped);

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="barcode-hunting-report-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Kode Cabang','Nama Cabang','Nama Game','ID Game','ID Sesi','ID User','Nama Konsumen','Nomor HP','Target Barcode','Barcode Dialokasikan','Barcode Berhasil','Waktu Aktif (detik)','Status Sesi']);
        foreach ($rows as $row) {
            $safe = static function ($value): string {
                $value = (string) ($value ?? '');
                return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
            };
            fputcsv($out, array_map($safe, [$row['cabang'],$row['nama_cabang'],$row['nama_game'],$row['id_game'],$row['id_session'],$row['id_user'],$row['nama'],$row['no_hp'],$row['barcode_list'],$row['assigned_count'],$row['total_scan'],$row['waktu_main_detik'],$row['status_session']]));
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Gagal membuat rekap. Periksa database V2.' : $e->getMessage();
}
$totals = ['total_game'=>0,'total_peserta'=>0,'total_scan'=>0,'total_selesai'=>0,'total_aktif'=>0,'total_pause'=>0];
foreach ($summary as $item) foreach ($totals as $key => $_) $totals[$key] += (int) ($item[$key] ?? 0);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= bh_h($pageTitle) ?> | Barcode Hunting</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/admin.css"><link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/modules.css"></head><body>
<?php require __DIR__ . '/includes/sidebar.php'; ?><main class="admin-main"><?php require __DIR__ . '/includes/topbar.php'; ?><section class="page-content">
    <div class="module-heading"><div><h2>Rekap per cabang</h2><p>Ringkasan sesi peserta dan hasil pemindaian.</p></div><a class="btn btn-primary" href="<?= bh_h($adminBase) ?>/reports.php?<?= $selectedBranch !== null ? 'cabang=' . rawurlencode((string) $selectedBranch) . '&' : '' ?>export=csv"><i class="bi bi-download me-1"></i> Export CSV</a></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= bh_h($error) ?></div><?php endforeach; ?>
    <?php if (!$errors): ?>
    <form method="get" class="module-toolbar"><div class="module-filters"><select class="form-select" name="cabang"><option value="">Semua cabang</option><?php foreach ($branches as $branch): ?><option value="<?= bh_h($branch['kode_cabang']) ?>" <?= (string) $selectedBranch === (string) $branch['kode_cabang'] ? 'selected' : '' ?>><?= bh_h($branch['nama_cabang']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary" type="submit"><i class="bi bi-filter"></i> Filter</button></div></form>
    <div class="row g-3 mb-3">
        <?php foreach ([['Sesi peserta',$totals['total_peserta'],'bi-people'],['Barcode berhasil',$totals['total_scan'],'bi-upc-scan'],['Sesi selesai',$totals['total_selesai'],'bi-check-circle'],['Sedang aktif',$totals['total_aktif'],'bi-play-circle']] as [$label,$value,$icon]): ?><div class="col-6 col-xl-3"><div class="report-stat"><span><i class="bi <?= bh_h($icon) ?>"></i></span><div><strong><?= number_format($value) ?></strong><small><?= bh_h($label) ?></small></div></div></div><?php endforeach; ?>
    </div>
    <section class="module-card module-list-card"><div class="table-responsive"><table class="table module-table align-middle"><thead><tr><th>Cabang</th><th>Peserta</th><th>Barcode berhasil</th><th>Selesai</th><th>Aktif</th><th>Pause</th><th>Expired</th></tr></thead><tbody>
    <?php if (!$summary): ?><tr><td colspan="7"><div class="module-empty"><i class="bi bi-file-earmark-bar-graph"></i><strong>Belum ada data laporan</strong><span>Rekap muncul setelah konsumen memulai permainan.</span></div></td></tr><?php else: foreach ($summary as $row): ?><tr><td><strong><?= bh_h($row['nama_cabang']) ?></strong><small class="d-block text-secondary">Kode <?= bh_h($row['cabang']) ?></small></td><td><?= number_format((int) $row['total_peserta']) ?></td><td><strong><?= number_format((int) $row['total_scan']) ?></strong></td><td><?= number_format((int) $row['total_selesai']) ?></td><td><?= number_format((int) $row['total_aktif']) ?></td><td><?= number_format((int) $row['total_pause']) ?></td><td><?= number_format((int) $row['total_expired']) ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></div></section>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
