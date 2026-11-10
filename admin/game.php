<?php
/** Konfigurasi permainan per cabang. Peserta mendaftar sendiri dari halaman game publik. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';

$adminBase = bh_admin_base();
$activeMenu = 'game';
$pageTitle = 'Konfigurasi Game';
$pageSubtitle = 'Atur permainan per cabang, tanpa mendaftarkan konsumen dari admin';
$errors = [];
$branches = [];
$games = [];
$editing = null;
$selectedBranch = isset($_GET['cabang']) && $_GET['cabang'] !== '' ? filter_var($_GET['cabang'], FILTER_VALIDATE_INT) : null;
$settingsReady = bh_table_exists($pdo, 'tb_game_setting');
$sessionsReady = bh_table_exists($pdo, 'tb_game_session');

try {
    foreach (['tb_game', 'tb_barcode'] as $table) {
        if (!bh_table_exists($pdo, $table)) throw new RuntimeException("Tabel {$table} belum ditemukan.");
    }
    foreach ([['tb_game','id_game'], ['tb_game','cabang'], ['tb_game','barcode_list'], ['tb_barcode','cabang']] as [$table, $column]) {
        if (!bh_column_exists($pdo, $table, $column)) throw new RuntimeException("Kolom {$table}.{$column} belum ditemukan.");
    }
    if (!bh_column_exists($pdo, 'tb_game', 'id_user')) {
        throw new RuntimeException('Kolom tb_game.id_user belum ditemukan. Periksa struktur tabel tb_game.');
    }
    if (!$settingsReady) throw new RuntimeException('Tabel tb_game_setting belum tersedia. Import admin/sql/upgrade_v2.sql terlebih dahulu.');

    if (bh_table_exists($pdo, 'tb_cabang') && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang') && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
        $branches = $pdo->query('SELECT kode_cabang, nama_cabang FROM tb_cabang ORDER BY nama_cabang')->fetchAll();
    } else {
        $branches = $pdo->query("SELECT DISTINCT cabang AS kode_cabang, CONCAT('Cabang ', cabang) AS nama_cabang FROM tb_barcode ORDER BY cabang")->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        bh_csrf_check();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save') {
            $originalId = trim((string) ($_POST['original_id_game'] ?? ''));
            $name = trim((string) ($_POST['nama_game'] ?? 'Barcode Hunting'));
            $branch = filter_var($_POST['cabang'] ?? null, FILTER_VALIDATE_INT);
            $target = filter_var($_POST['barcode_list'] ?? null, FILTER_VALIDATE_INT);
            $minutes = filter_var($_POST['durasi_menit'] ?? 10, FILTER_VALIDATE_INT);
            $active = isset($_POST['aktif']) && $_POST['aktif'] === '1' ? 1 : 0;

            if ($name === '' || mb_strlen($name) > 100) throw new InvalidArgumentException('Nama permainan wajib diisi, maksimal 100 karakter.');
            if ($branch === false || $branch < 1) throw new InvalidArgumentException('Pilih cabang yang valid.');
            if ($target === false || $target < 1 || $target > 100) throw new InvalidArgumentException('Target barcode harus antara 1 sampai 100.');
            if ($minutes === false || $minutes < 1 || $minutes > 1440) throw new InvalidArgumentException('Durasi harus antara 1 sampai 1440 menit.');

            if (bh_table_exists($pdo, 'tb_cabang') && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')) {
                $branchCheck = $pdo->prepare('SELECT 1 FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
                $branchCheck->execute([$branch]);
                if (!$branchCheck->fetchColumn()) throw new InvalidArgumentException('Cabang tidak ditemukan di tb_cabang.');
            }

            $availableStmt = $pdo->prepare('SELECT COUNT(*) FROM tb_barcode WHERE cabang = ?');
            $availableStmt->execute([$branch]);
            $available = (int) $availableStmt->fetchColumn();
            if ($available < $target) throw new InvalidArgumentException("Barcode di cabang {$branch} hanya tersedia {$available}; target permainan {$target}.");

            if ($originalId !== '') {
                $currentStmt = $pdo->prepare('SELECT cabang FROM tb_game WHERE id_game = ? LIMIT 1');
                $currentStmt->execute([$originalId]);
                $current = $currentStmt->fetch();
                if (!$current) throw new RuntimeException('Konfigurasi game tidak ditemukan.');
                if ($sessionsReady && (int) $current['cabang'] !== (int) $branch) {
                    $sessionCount = $pdo->prepare('SELECT COUNT(*) FROM tb_game_session WHERE id_game = ?');
                    $sessionCount->execute([$originalId]);
                    if ((int) $sessionCount->fetchColumn() > 0) {
                        throw new RuntimeException('Cabang tidak dapat dipindahkan karena konfigurasi ini sudah memiliki sesi peserta. Buat konfigurasi baru untuk cabang tujuan.');
                    }
                }
                $pdo->beginTransaction();
                try {
                    $updateGame = $pdo->prepare('UPDATE tb_game SET cabang = ?, barcode_list = ? WHERE id_game = ?');
                    $updateGame->execute([$branch, $target, $originalId]);
                    if ($active === 1) {
                        $deactivateOthers = $pdo->prepare('UPDATE tb_game_setting cfg INNER JOIN tb_game g ON g.id_game = cfg.id_game SET cfg.aktif = 0 WHERE g.cabang = ? AND g.id_game <> ?');
                        $deactivateOthers->execute([$branch, $originalId]);
                    }
                    $updateSetting = $pdo->prepare('UPDATE tb_game_setting SET nama_game = ?, durasi_detik = ?, aktif = ? WHERE id_game = ?');
                    $updateSetting->execute([$name, $minutes * 60, $active, $originalId]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
                bh_flash('success', 'Konfigurasi permainan berhasil diperbarui. Hanya satu game aktif per cabang.');
                bh_redirect($adminBase . '/game.php?cabang=' . rawurlencode((string) $branch));
            }

            $gameId = bh_random_id('GAME-', 65);
            $pdo->beginTransaction();
            try {
                if ($active === 1) {
                    $deactivateOthers = $pdo->prepare('UPDATE tb_game_setting cfg INNER JOIN tb_game g ON g.id_game = cfg.id_game SET cfg.aktif = 0 WHERE g.cabang = ?');
                    $deactivateOthers->execute([$branch]);
                }
                $insertGame = $pdo->prepare('INSERT INTO tb_game (id_game, cabang, id_user, barcode_list) VALUES (?, ?, NULL, ?)');
                $insertGame->execute([$gameId, $branch, $target]);
                $insertSetting = $pdo->prepare('INSERT INTO tb_game_setting (id_game, nama_game, durasi_detik, aktif) VALUES (?, ?, ?, ?)');
                $insertSetting->execute([$gameId, $name, $minutes * 60, $active]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            bh_flash('success', 'Konfigurasi game berhasil dibuat. Konsumen dapat mendaftar sendiri dari halaman game cabang.');
            bh_redirect($adminBase . '/game.php?cabang=' . rawurlencode((string) $branch));
        }

        if ($action === 'delete') {
            $gameId = trim((string) ($_POST['id_game'] ?? ''));
            if ($gameId === '') throw new InvalidArgumentException('ID game tidak valid.');
            if ($sessionsReady) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM tb_game_session WHERE id_game = ?');
                $check->execute([$gameId]);
                if ((int) $check->fetchColumn() > 0) throw new RuntimeException('Konfigurasi tidak dapat dihapus karena sudah memiliki riwayat peserta. Nonaktifkan saja konfigurasi ini.');
            }
            $stmt = $pdo->prepare('DELETE FROM tb_game WHERE id_game = ?');
            $stmt->execute([$gameId]);
            bh_flash($stmt->rowCount() > 0 ? 'success' : 'warning', $stmt->rowCount() > 0 ? 'Konfigurasi game berhasil dihapus.' : 'Konfigurasi tidak ditemukan.');
            bh_redirect($adminBase . '/game.php');
        }

        if ($action === 'toggle') {
            $gameId = trim((string) ($_POST['id_game'] ?? ''));
            $active = (int) ($_POST['aktif'] ?? 0) === 1 ? 1 : 0;
            if ($active === 1) {
                $branchStmt = $pdo->prepare('SELECT cabang FROM tb_game WHERE id_game = ? LIMIT 1');
                $branchStmt->execute([$gameId]);
                $branch = $branchStmt->fetchColumn();
                if ($branch === false) throw new RuntimeException('Konfigurasi game tidak ditemukan.');
                $deactivateOthers = $pdo->prepare('UPDATE tb_game_setting cfg INNER JOIN tb_game g ON g.id_game = cfg.id_game SET cfg.aktif = 0 WHERE g.cabang = ? AND g.id_game <> ?');
                $deactivateOthers->execute([(int) $branch, $gameId]);
            }
            $stmt = $pdo->prepare('UPDATE tb_game_setting SET aktif = ? WHERE id_game = ?');
            $stmt->execute([$active, $gameId]);
            bh_flash('success', $active ? 'Game diaktifkan. Game aktif lain di cabang yang sama dinonaktifkan.' : 'Game dinonaktifkan.');
            bh_redirect($adminBase . '/game.php');
        }

        throw new InvalidArgumentException('Aksi tidak dikenal.');
    }

    if (!empty($_GET['edit'])) {
        $stmt = $pdo->prepare('SELECT g.id_game, g.cabang, g.barcode_list, cfg.nama_game, cfg.durasi_detik, cfg.aktif FROM tb_game g INNER JOIN tb_game_setting cfg ON cfg.id_game = g.id_game WHERE g.id_game = ? LIMIT 1');
        $stmt->execute([(string) $_GET['edit']]);
        $editing = $stmt->fetch() ?: null;
    }

    $where = '';
    $params = [];
    if ($selectedBranch !== null && $selectedBranch !== false) { $where = 'WHERE g.cabang = ?'; $params[] = $selectedBranch; }
    $branchName = (bh_table_exists($pdo, 'tb_cabang') && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) ? 'c.nama_cabang' : "CONCAT('Cabang ', g.cabang)";
    $branchJoin = $branchName === 'c.nama_cabang' ? 'LEFT JOIN tb_cabang c ON c.kode_cabang = g.cabang' : '';
    $participantCount = $sessionsReady ? '(SELECT COUNT(*) FROM tb_game_session s WHERE s.id_game = g.id_game)' : '0';
    $sql = "SELECT g.id_game, g.cabang, g.barcode_list, {$branchName} AS nama_cabang,
                   cfg.nama_game, cfg.durasi_detik, cfg.aktif, {$participantCount} AS total_peserta,
                   (SELECT COUNT(*) FROM tb_barcode b WHERE b.cabang = g.cabang) AS barcode_tersedia
            FROM tb_game g INNER JOIN tb_game_setting cfg ON cfg.id_game = g.id_game {$branchJoin} {$where}
            ORDER BY g.cabang ASC, cfg.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Database menolak perubahan. Periksa struktur tb_game dan tabel upgrade V2.' : $e->getMessage();
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
    <div class="module-heading"><div><h2>Konfigurasi permainan</h2><p>Admin mengatur game per cabang. Konsumen mendaftar sendiri dari halaman publik.</p></div><div class="module-stat"><i class="bi bi-controller"></i><span><strong><?= number_format(count($games)) ?></strong><small>Konfigurasi game</small></span></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= bh_h($error) ?></div><?php endforeach; ?>
    <?php if (!$errors): ?>
    <div class="module-grid module-grid-game">
        <section class="module-card module-form-card">
            <div class="module-card-heading"><span class="module-icon"><i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-controller' ?>"></i></span><div><h3><?= $editing ? 'Ubah konfigurasi' : 'Buat game untuk cabang' ?></h3><p>Belum ada data konsumen yang perlu dimasukkan di sini.</p></div></div>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="original_id_game" value="<?= bh_h($editing['id_game'] ?? '') ?>">
                <?php if ($editing): ?><div class="col-12"><label class="form-label">ID Game</label><input class="form-control" value="<?= bh_h($editing['id_game']) ?>" readonly></div><?php endif; ?>
                <div class="col-12"><label class="form-label" for="nama_game">Nama permainan</label><input class="form-control" id="nama_game" name="nama_game" maxlength="100" value="<?= bh_h($editing['nama_game'] ?? 'Barcode Hunting') ?>" required></div>
                <div class="col-12"><label class="form-label" for="cabang">Cabang</label><select class="form-select" id="cabang" name="cabang" required><option value="">Pilih cabang</option><?php foreach ($branches as $branch): ?><option value="<?= bh_h($branch['kode_cabang']) ?>" <?= (string) ($editing['cabang'] ?? $selectedBranch) === (string) $branch['kode_cabang'] ? 'selected' : '' ?>><?= bh_h($branch['nama_cabang']) ?> (<?= bh_h($branch['kode_cabang']) ?>)</option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="barcode_list">Target barcode</label><input class="form-control" type="number" id="barcode_list" name="barcode_list" min="1" max="100" value="<?= bh_h($editing['barcode_list'] ?? 10) ?>" required><div class="form-text">Jumlah barcode yang harus dikumpulkan setiap pemain.</div></div>
                <div class="col-md-6"><label class="form-label" for="durasi_menit">Durasi (menit)</label><input class="form-control" type="number" id="durasi_menit" name="durasi_menit" min="1" max="1440" value="<?= isset($editing['durasi_detik']) ? (int) round((int) $editing['durasi_detik'] / 60) : 10 ?>" required></div>
                <div class="col-12"><label class="form-label" for="aktif">Status</label><select class="form-select" name="aktif" id="aktif"><option value="1" <?= (int) ($editing['aktif'] ?? 1) === 1 ? 'selected' : '' ?>>Aktif, dapat dimainkan konsumen</option><option value="0" <?= (int) ($editing['aktif'] ?? 1) === 0 ? 'selected' : '' ?>>Nonaktif</option></select></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i><?= $editing ? 'Simpan perubahan' : 'Buat konfigurasi game' ?></button><?php if ($editing): ?><a class="btn btn-light" href="<?= bh_h($adminBase) ?>/game.php">Batal</a><?php endif; ?></div>
            </form>
        </section>
        <section class="module-card module-list-card">
            <div class="module-card-heading"><span class="module-icon"><i class="bi bi-list-ul"></i></span><div><h3>Daftar konfigurasi</h3><p>Game yang sudah disiapkan per cabang.</p></div></div>
            <form method="get" class="module-filters"><select class="form-select" name="cabang"><option value="">Semua cabang</option><?php foreach ($branches as $branch): ?><option value="<?= bh_h($branch['kode_cabang']) ?>" <?= (string) $selectedBranch === (string) $branch['kode_cabang'] ? 'selected' : '' ?>><?= bh_h($branch['nama_cabang']) ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary" type="submit"><i class="bi bi-filter"></i></button><a class="btn btn-light" href="<?= bh_h($adminBase) ?>/game.php"><i class="bi bi-arrow-counterclockwise"></i></a></form>
            <div class="table-responsive"><table class="table module-table align-middle"><thead><tr><th>Permainan</th><th>Cabang</th><th>Target</th><th>Durasi</th><th>Peserta</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
            <?php if (!$games): ?><tr><td colspan="7"><div class="module-empty"><i class="bi bi-controller"></i><strong>Belum ada konfigurasi permainan</strong><span>Buat konfigurasi untuk cabang yang dipilih.</span></div></td></tr><?php else: foreach ($games as $game): ?>
                <tr><td><strong class="d-block"><?= bh_h($game['nama_game']) ?></strong><small class="text-secondary"><?= bh_h($game['id_game']) ?></small></td><td><?= bh_h($game['nama_cabang']) ?><small class="d-block text-secondary"><?= bh_h($game['cabang']) ?></small></td><td><strong><?= number_format((int) $game['barcode_list']) ?></strong><small class="d-block text-secondary"><?= number_format((int) $game['barcode_tersedia']) ?> tersedia</small></td><td><?= number_format((int) round((int) $game['durasi_detik'] / 60)) ?> menit</td><td><?= number_format((int) $game['total_peserta']) ?></td><td><span class="status-pill status-<?= (int) $game['aktif'] === 1 ? 'active' : 'expired' ?>"><?= (int) $game['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td><td><div class="table-actions"><a class="btn btn-sm btn-outline-secondary" href="<?= bh_h($adminBase) ?>/game.php?edit=<?= rawurlencode((string) $game['id_game']) ?>" title="Ubah"><i class="bi bi-pencil"></i></a><form method="post"><input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id_game" value="<?= bh_h($game['id_game']) ?>"><input type="hidden" name="aktif" value="<?= (int) $game['aktif'] === 1 ? '0' : '1' ?>"><button class="btn btn-sm btn-outline-<?= (int) $game['aktif'] === 1 ? 'warning' : 'success' ?>" type="submit" title="<?= (int) $game['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"><i class="bi bi-power"></i></button></form><form method="post" data-confirm="Hapus konfigurasi ini? Konfigurasi yang sudah memiliki peserta tidak dapat dihapus."><input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id_game" value="<?= bh_h($game['id_game']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash3"></i></button></form></div></td></tr>
            <?php endforeach; endif; ?></tbody></table></div>
        </section>
    </div>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main><?php require __DIR__ . '/includes/footer.php'; ?>
