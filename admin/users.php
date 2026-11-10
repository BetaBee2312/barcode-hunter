<?php
/** Daftar konsumen. Data dibuat otomatis saat konsumen mengirim form registrasi game. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';

$adminBase = bh_admin_base();
$activeMenu = 'users';
$pageTitle = 'Peserta';
$pageSubtitle = 'Konsumen yang mendaftar melalui halaman permainan';
$errors = [];
$users = [];
$search = trim((string) ($_GET['q'] ?? ''));

try {
    if (!bh_table_exists($pdo, 'tb_user')) {
        throw new RuntimeException('Tabel tb_user belum tersedia. Import admin/sql/upgrade_v2.sql terlebih dahulu.');
    }
    foreach (['id_user', 'nama', 'no_hp'] as $column) {
        if (!bh_column_exists($pdo, 'tb_user', $column)) throw new RuntimeException("Kolom tb_user.{$column} belum ditemukan.");
    }
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE u.id_user LIKE ? OR u.nama LIKE ? OR u.no_hp LIKE ?';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like];
    }
    $hasCreated = bh_column_exists($pdo, 'tb_user', 'created_at');
    $sessionCount = bh_table_exists($pdo, 'tb_game_session')
        ? '(SELECT COUNT(*) FROM tb_game_session s WHERE s.id_user = u.id_user)'
        : '0';
    $completedCount = bh_table_exists($pdo, 'tb_game_session')
        ? "(SELECT COUNT(*) FROM tb_game_session s WHERE s.id_user = u.id_user AND s.status_session = 'completed')"
        : '0';
    $sql = "SELECT u.id_user, u.nama, u.no_hp, "
        . ($hasCreated ? 'u.created_at' : 'NULL AS created_at')
        . ", {$sessionCount} AS total_sesi, {$completedCount} AS sesi_selesai
           FROM tb_user u {$where} ORDER BY "
        . ($hasCreated ? 'u.created_at DESC' : 'u.nama ASC') . ' LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Gagal membaca data peserta. Periksa struktur tb_user dan tb_game_session.' : $e->getMessage();
}

$flash = bh_take_flash();
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= bh_h($pageTitle) ?> | Barcode Hunting</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/admin.css"><link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/modules.css">
</head><body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main"><?php require __DIR__ . '/includes/topbar.php'; ?><section class="page-content">
    <div class="module-heading"><div><h2>Data peserta</h2><p>Peserta masuk sendiri melalui form publik. Admin tidak perlu menambahkan konsumen secara manual.</p></div><div class="module-stat"><i class="bi bi-people"></i><span><strong><?= number_format(count($users)) ?></strong><small>Peserta ditampilkan</small></span></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= bh_h($error) ?></div><?php endforeach; ?>
    <?php if (!$errors): ?>
    <section class="module-card module-list-card">
        <div class="module-card-heading"><span class="module-icon"><i class="bi bi-person-lines-fill"></i></span><div><h3>Peserta terdaftar</h3><p>Maksimal 500 peserta terbaru.</p></div></div>
        <form method="get" class="module-filters"><input class="form-control" type="search" name="q" value="<?= bh_h($search) ?>" placeholder="Cari nama, nomor HP, atau ID peserta"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button><a class="btn btn-light" href="<?= bh_h($adminBase) ?>/users.php"><i class="bi bi-arrow-counterclockwise"></i></a></form>
        <div class="table-responsive"><table class="table module-table align-middle"><thead><tr><th>Peserta</th><th>Nomor HP</th><th>Terdaftar</th><th>Total sesi</th><th>Sesi selesai</th></tr></thead><tbody>
        <?php if (!$users): ?><tr><td colspan="5"><div class="module-empty"><i class="bi bi-people"></i><strong>Belum ada peserta</strong><span>Data akan muncul otomatis setelah seseorang mendaftar dari halaman game.</span></div></td></tr><?php else: foreach ($users as $user): ?>
            <tr><td><strong class="d-block"><?= bh_h($user['nama']) ?></strong><small class="text-secondary"><?= bh_h($user['id_user']) ?></small></td><td><?= bh_h($user['no_hp']) ?></td><td><?= $user['created_at'] ? date('d M Y H:i', strtotime((string) $user['created_at'])) : '-' ?></td><td><span class="number-pill"><?= number_format((int) $user['total_sesi']) ?></span></td><td><?= number_format((int) $user['sesi_selesai']) ?></td></tr>
        <?php endforeach; endif; ?></tbody></table></div>
    </section>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
