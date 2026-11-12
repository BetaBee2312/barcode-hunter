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

// Aksi edit dan hapus diproses di server dengan CSRF dan transaksi database.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        bh_csrf_check();
        $action = (string) ($_POST['action'] ?? '');
        $userId = trim((string) ($_POST['id_user'] ?? ''));
        if ($userId === '' || strlen($userId) > 15) {
            throw new RuntimeException('ID peserta tidak valid.');
        }

        if ($action === 'update') {
            $name = trim((string) ($_POST['nama'] ?? ''));
            $phone = preg_replace('/\\D+/', '', (string) ($_POST['no_hp'] ?? '')) ?? '';
            $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
            if ($name === '' || $nameLength > 100) {
                throw new RuntimeException('Nama wajib diisi dan maksimal 100 karakter.');
            }
            if (strlen($phone) < 8 || strlen($phone) > 20) {
                throw new RuntimeException('Nomor HP harus terdiri dari 8 sampai 20 digit.');
            }

            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id_user FROM tb_user WHERE id_user = ? LIMIT 1 FOR UPDATE');
            $lock->execute([$userId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Peserta tidak ditemukan.');

            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM tb_user WHERE no_hp = ? AND id_user <> ?');
            $duplicate->execute([$phone, $userId]);
            if ((int) $duplicate->fetchColumn() > 0) {
                throw new RuntimeException('Nomor HP sudah digunakan oleh peserta lain.');
            }

            $update = $pdo->prepare('UPDATE tb_user SET nama = ?, no_hp = ? WHERE id_user = ?');
            $update->execute([$name, $phone, $userId]);
            $pdo->commit();
            bh_flash('success', 'Data peserta berhasil diperbarui.');
        } elseif ($action === 'delete') {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id_user, nama FROM tb_user WHERE id_user = ? LIMIT 1 FOR UPDATE');
            $lock->execute([$userId]);
            $user = $lock->fetch();
            if (!$user) throw new RuntimeException('Peserta tidak ditemukan.');

            // Hapus sesi terlebih dahulu karena FK tb_game_session ke tb_user memakai ON DELETE RESTRICT.
            // Detail barcode per sesi akan ikut terhapus melalui ON DELETE CASCADE.
            $removedSessions = 0;
            if (bh_table_exists($pdo, 'tb_game_session')) {
                $deleteSessions = $pdo->prepare('DELETE FROM tb_game_session WHERE id_user = ?');
                $deleteSessions->execute([$userId]);
                $removedSessions = $deleteSessions->rowCount();
            }

            // Bersihkan relasi legacy pada tb_game apabila kolom lama masih tersedia.
            if (bh_table_exists($pdo, 'tb_game') && bh_column_exists($pdo, 'tb_game', 'id_user')) {
                $clearLegacy = $pdo->prepare('UPDATE tb_game SET id_user = NULL WHERE id_user = ?');
                $clearLegacy->execute([$userId]);
            }

            $deleteUser = $pdo->prepare('DELETE FROM tb_user WHERE id_user = ?');
            $deleteUser->execute([$userId]);
            if ($deleteUser->rowCount() !== 1) throw new RuntimeException('Data peserta gagal dihapus.');

            $pdo->commit();
            bh_flash('success', 'Peserta berhasil dihapus. ' . $removedSessions . ' sesi permainan beserta progresnya ikut dihapus.');
        } else {
            throw new RuntimeException('Aksi peserta tidak dikenali.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
            bh_flash('error', 'Nomor HP sudah digunakan peserta lain atau data masih terhubung dengan catatan lain.');
        } else {
            bh_flash('error', $e->getMessage());
        }
    }

    $redirect = $adminBase . '/users.php';
    if ($search !== '') $redirect .= '?q=' . rawurlencode($search);
    bh_redirect($redirect);
}

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
        <div class="table-responsive"><table class="table module-table align-middle"><thead><tr><th>Peserta</th><th>Nomor HP</th><th>Terdaftar</th><th>Total sesi</th><th>Sesi selesai</th><th class="text-end">Aksi</th></tr></thead><tbody>
        <?php if (!$users): ?><tr><td colspan="6"><div class="module-empty"><i class="bi bi-people"></i><strong>Belum ada peserta</strong><span>Data akan muncul otomatis setelah seseorang mendaftar dari halaman game.</span></div></td></tr><?php else: foreach ($users as $user): ?>
            <tr>
                <td><strong class="d-block"><?= bh_h($user['nama']) ?></strong><small class="text-secondary"><?= bh_h($user['id_user']) ?></small></td>
                <td><?= bh_h($user['no_hp']) ?></td>
                <td><?= $user['created_at'] ? date('d M Y H:i', strtotime((string) $user['created_at'])) : '-' ?></td>
                <td><span class="number-pill"><?= number_format((int) $user['total_sesi']) ?></span></td>
                <td><?= number_format((int) $user['sesi_selesai']) ?></td>
                <td>
                    <div class="d-flex justify-content-end gap-1">
                        <button class="btn btn-sm btn-outline-primary" type="button"
                            data-user-edit
                            data-id="<?= bh_h($user['id_user']) ?>"
                            data-name="<?= bh_h($user['nama']) ?>"
                            data-phone="<?= bh_h($user['no_hp']) ?>"
                            title="Edit peserta" aria-label="Edit peserta">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" class="d-inline" data-confirm="Hapus peserta <?= bh_h($user['nama']) ?>? Semua sesi permainan, progres barcode, dan riwayat penyelesaiannya akan ikut dihapus. Tindakan ini tidak dapat dibatalkan.">
                            <input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id_user" value="<?= bh_h($user['id_user']) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus peserta" aria-label="Hapus peserta">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?></tbody></table></div>
    </section>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section></main>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editUserForm">
                <div class="modal-header">
                    <div><h5 class="modal-title" id="editUserTitle">Edit peserta</h5><small class="text-secondary" id="editUserIdLabel"></small></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= bh_h(bh_csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_user" id="editUserId">
                    <div class="mb-3">
                        <label class="form-label" for="editUserName">Nama peserta</label>
                        <input class="form-control" type="text" name="nama" id="editUserName" maxlength="100" required>
                    </div>
                    <div>
                        <label class="form-label" for="editUserPhone">Nomor HP</label>
                        <input class="form-control" type="tel" name="no_hp" id="editUserPhone" inputmode="numeric" maxlength="20" required>
                        <div class="form-text">Nomor HP hanya boleh terdiri dari 8 sampai 20 digit.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Simpan perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
