<?php
/**
 * Data Cabang - Barcode Hunting Admin
 * Menggunakan kode_cabang sebagai penghubung ke tb_barcode.cabang dan tb_game.cabang.
 */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

$configPath = dirname(__DIR__) . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('File koneksi tidak ditemukan. Pastikan file berada di /config/config.php.');
}
require_once $configPath;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Koneksi database tidak tersedia. Periksa /config/config.php.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function getColumnInfo(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->prepare(
        'SELECT IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $info = $stmt->fetch();
    return $info ?: null;
}

function redirectWithMessage(string $type, string $message): never
{
    $url = strtok($_SERVER['REQUEST_URI'] ?? 'cabang.php', '?');
    header('Location: ' . $url . '?' . http_build_query([$type => $message]));
    exit;
}

if (empty($_SESSION['csrf_cabang'])) {
    $_SESSION['csrf_cabang'] = bin2hex(random_bytes(32));
}

$adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/cabang.php'), '/');
if ($adminBase === '') {
    $adminBase = '/admin';
}
$activeMenu = 'branches';
$pageTitle = 'Data Cabang';
$pageSubtitle = 'Kelola daftar cabang yang mengikuti Barcode Hunting';

$errors = [];
$success = isset($_GET['success']) ? trim((string) $_GET['success']) : '';
$flashError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
$hasSingkatan = false;
$hasDcSupport = false;
$hasActive = false;
$singkatanRequired = false;
$dcSupportRequired = false;
$hasBarcodeTable = false;
$hasGameTable = false;
$branches = [];
$editBranch = null;
$search = trim((string) ($_GET['q'] ?? ''));
$tableReady = false;

try {
    if (!tableExists($pdo, 'tb_cabang')) {
        throw new RuntimeException('Tabel tb_cabang belum tersedia. Jalankan SQL pembuatan tabel cabang yang disertakan bersama file ini.');
    }

    foreach (['kode_cabang', 'nama_cabang'] as $requiredColumn) {
        if (!columnExists($pdo, 'tb_cabang', $requiredColumn)) {
            throw new RuntimeException("Kolom tb_cabang.{$requiredColumn} tidak ditemukan. Struktur yang dibutuhkan: kode_cabang dan nama_cabang.");
        }
    }

    $tableReady = true;
    $hasSingkatan = columnExists($pdo, 'tb_cabang', 'singkatan');
    $hasDcSupport = columnExists($pdo, 'tb_cabang', 'dc_support');
    $hasActive = columnExists($pdo, 'tb_cabang', 'is_active');
    $singkatanInfo = $hasSingkatan ? getColumnInfo($pdo, 'tb_cabang', 'singkatan') : null;
    $dcSupportInfo = $hasDcSupport ? getColumnInfo($pdo, 'tb_cabang', 'dc_support') : null;
    $singkatanRequired = $hasSingkatan && $singkatanInfo && $singkatanInfo['IS_NULLABLE'] === 'NO' && $singkatanInfo['COLUMN_DEFAULT'] === null;
    $dcSupportRequired = $hasDcSupport && $dcSupportInfo && $dcSupportInfo['IS_NULLABLE'] === 'NO' && $dcSupportInfo['COLUMN_DEFAULT'] === null;
    $hasBarcodeTable = tableExists($pdo, 'tb_barcode') && columnExists($pdo, 'tb_barcode', 'cabang');
    $hasGameTable = tableExists($pdo, 'tb_game') && columnExists($pdo, 'tb_game', 'cabang');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) $_SESSION['csrf_cabang'], $postedToken)) {
            throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');
        }

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save') {
            $originalCode = trim((string) ($_POST['original_kode_cabang'] ?? ''));
            $kodeCabang = trim((string) ($_POST['kode_cabang'] ?? ''));
            $namaCabang = trim((string) ($_POST['nama_cabang'] ?? ''));
            $singkatan = trim((string) ($_POST['singkatan'] ?? ''));
            $dcSupport = trim((string) ($_POST['dc_support'] ?? ''));

            // tb_barcode.cabang dan tb_game.cabang bertipe INT, jadi kode cabang harus numerik.
            if ($kodeCabang === '' || !preg_match('/^[0-9]{1,10}$/', $kodeCabang) || (float) $kodeCabang < 1 || (float) $kodeCabang > 2147483647) {
                throw new InvalidArgumentException('Kode cabang wajib berupa angka 1 sampai 2147483647 agar cocok dengan kolom INT di tb_barcode dan tb_game.');
            }
            if ($namaCabang === '') {
                throw new InvalidArgumentException('Nama cabang wajib diisi.');
            }
            if (mb_strlen($namaCabang) > 150) {
                throw new InvalidArgumentException('Nama cabang maksimal 150 karakter.');
            }
            if ($singkatanRequired && $singkatan === '') {
                throw new InvalidArgumentException('Singkatan wajib diisi sesuai struktur tb_cabang.');
            }
            if ($dcSupportRequired && $dcSupport === '') {
                throw new InvalidArgumentException('DC Support wajib diisi sesuai struktur tb_cabang.');
            }

            // Kode cabang dibuat tetap saat edit agar relasi barcode dan permainan tidak terputus.
            if ($originalCode !== '') {
                if ($kodeCabang !== $originalCode) {
                    throw new InvalidArgumentException('Kode cabang tidak dapat diubah setelah dibuat. Gunakan kode yang sama agar relasi data tetap aman.');
                }

                $setParts = ['nama_cabang = :nama_cabang'];
                $params = [':nama_cabang' => $namaCabang, ':kode_cabang' => $originalCode];
                if ($hasSingkatan) {
                    $setParts[] = 'singkatan = :singkatan';
                    $params[':singkatan'] = $singkatan !== '' ? $singkatan : null;
                }
                if ($hasDcSupport) {
                    $setParts[] = 'dc_support = :dc_support';
                    $params[':dc_support'] = $dcSupport !== '' ? $dcSupport : null;
                }

                $sql = 'UPDATE tb_cabang SET ' . implode(', ', $setParts) . ' WHERE kode_cabang = :kode_cabang';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                redirectWithMessage('success', 'Data cabang berhasil diperbarui.');
            }

            $columns = ['kode_cabang', 'nama_cabang'];
            $placeholders = [':kode_cabang', ':nama_cabang'];
            $params = [':kode_cabang' => $kodeCabang, ':nama_cabang' => $namaCabang];

            if ($hasSingkatan) {
                $columns[] = 'singkatan';
                $placeholders[] = ':singkatan';
                $params[':singkatan'] = $singkatan !== '' ? $singkatan : null;
            }
            if ($hasDcSupport) {
                $columns[] = 'dc_support';
                $placeholders[] = ':dc_support';
                $params[':dc_support'] = $dcSupport !== '' ? $dcSupport : null;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO tb_cabang (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            redirectWithMessage('success', 'Cabang baru berhasil ditambahkan.');
        }

        if ($action === 'delete') {
            $kodeCabang = trim((string) ($_POST['kode_cabang'] ?? ''));
            if ($kodeCabang === '' || !preg_match('/^[0-9]{1,10}$/', $kodeCabang)) {
                throw new InvalidArgumentException('Kode cabang tidak valid.');
            }

            $usage = [];
            if ($hasBarcodeTable) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_barcode WHERE cabang = ?');
                $stmt->execute([$kodeCabang]);
                $usage['barcode'] = (int) $stmt->fetchColumn();
            }
            if ($hasGameTable) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM tb_game WHERE cabang = ?');
                $stmt->execute([$kodeCabang]);
                $usage['permainan'] = (int) $stmt->fetchColumn();
            }

            if (array_sum($usage) > 0) {
                $details = [];
                if (($usage['barcode'] ?? 0) > 0) {
                    $details[] = number_format($usage['barcode']) . ' barcode';
                }
                if (($usage['permainan'] ?? 0) > 0) {
                    $details[] = number_format($usage['permainan']) . ' data permainan';
                }
                throw new RuntimeException('Cabang tidak dapat dihapus karena masih digunakan oleh ' . implode(' dan ', $details) . '. Hapus atau pindahkan data terkait terlebih dahulu.');
            }

            $stmt = $pdo->prepare('DELETE FROM tb_cabang WHERE kode_cabang = ?');
            $stmt->execute([$kodeCabang]);
            redirectWithMessage('success', $stmt->rowCount() > 0 ? 'Cabang berhasil dihapus.' : 'Data cabang tidak ditemukan.');
        }

        throw new InvalidArgumentException('Aksi tidak dikenal.');
    }

    if ($tableReady) {
        if (isset($_GET['edit']) && $_GET['edit'] !== '') {
            $editCode = trim((string) $_GET['edit']);
            $stmt = $pdo->prepare('SELECT * FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
            $stmt->execute([$editCode]);
            $editBranch = $stmt->fetch() ?: null;
        }

        $select = [
            'c.kode_cabang',
            'c.nama_cabang',
            $hasSingkatan ? 'c.singkatan' : 'NULL AS singkatan',
            $hasDcSupport ? 'c.dc_support' : 'NULL AS dc_support',
            $hasActive ? 'c.is_active' : '1 AS is_active',
            $hasBarcodeTable
                ? '(SELECT COUNT(*) FROM tb_barcode b WHERE b.cabang = c.kode_cabang) AS total_barcode'
                : '0 AS total_barcode',
            $hasGameTable
                ? '(SELECT COUNT(*) FROM tb_game g WHERE g.cabang = c.kode_cabang) AS total_game'
                : '0 AS total_game',
        ];

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM tb_cabang c';
        $params = [];
        if ($search !== '') {
            $searchParts = [
                'CAST(c.kode_cabang AS CHAR) LIKE :search_code',
                'c.nama_cabang LIKE :search_name',
            ];
            $params[':search_code'] = '%' . $search . '%';
            $params[':search_name'] = '%' . $search . '%';
            if ($hasSingkatan) {
                $searchParts[] = 'c.singkatan LIKE :search_alias';
                $params[':search_alias'] = '%' . $search . '%';
            }
            $sql .= ' WHERE (' . implode(' OR ', $searchParts) . ')';
        }
        $sql .= ' ORDER BY c.nama_cabang ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $branches = $stmt->fetchAll();
    }
} catch (Throwable $exception) {
    $errors[] = $exception instanceof PDOException
        ? 'Terjadi kesalahan database. Periksa struktur tabel dan pastikan kode cabang belum digunakan.'
        : $exception->getMessage();
}

$branchCount = count($branches);
$editing = is_array($editBranch);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Barcode Hunting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= h($adminBase) ?>/assets/admin.css">
    <link rel="stylesheet" href="<?= h($adminBase) ?>/assets/cabang.css">
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="admin-main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <section class="page-content">
        <div class="page-heading branch-page-heading">
            <div>
                <h2>Kelola cabang</h2>
                <p>Tambah dan perbarui cabang yang digunakan untuk mengelompokkan barcode serta permainan.</p>
            </div>
            <div class="branch-total"><i class="bi bi-shop"></i><span><strong><?= number_format($branchCount) ?></strong><small>Total cabang</small></span></div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="admin-alert success"><i class="bi bi-check-circle me-2"></i><?= h($success) ?></div>
        <?php endif; ?>
        <?php if ($flashError !== ''): ?>
            <div class="admin-alert danger"><i class="bi bi-exclamation-triangle me-2"></i><?= h($flashError) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $message): ?>
            <div class="admin-alert danger"><i class="bi bi-exclamation-triangle me-2"></i><?= h($message) ?></div>
        <?php endforeach; ?>

        <?php if (!$tableReady): ?>
            <div class="branch-setup-note">
                <div class="branch-setup-icon"><i class="bi bi-database-exclamation"></i></div>
                <div>
                    <strong>Siapkan tabel master cabang terlebih dahulu</strong>
                    <p>Halaman ini membutuhkan <code>tb_cabang.kode_cabang</code> dan <code>tb_cabang.nama_cabang</code>. File SQL pendamping membuat struktur yang cocok dengan <code>tb_barcode.cabang</code> dan <code>tb_game.cabang</code>.</p>
                    <a class="btn btn-sm btn-primary" href="<?= h($adminBase) ?>/sql/tb_cabang.sql"><i class="bi bi-download me-1"></i>Unduh SQL tabel cabang</a>
                </div>
            </div>
        <?php else: ?>
            <div class="branch-layout">
                <section class="branch-panel branch-form-panel">
                    <div class="panel-heading">
                        <div class="panel-heading-icon"><i class="bi <?= $editing ? 'bi-pencil-square' : 'bi-plus-lg' ?>"></i></div>
                        <div><h3><?= $editing ? 'Ubah data cabang' : 'Tambah cabang' ?></h3><p><?= $editing ? 'Perbarui informasi cabang.' : 'Masukkan identitas cabang baru.' ?></p></div>
                    </div>

                    <form method="post" action="<?= h($adminBase) ?>/cabang.php<?= $editing ? '?edit=' . rawurlencode((string) $editBranch['kode_cabang']) : '' ?>" class="branch-form">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_cabang']) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="original_kode_cabang" value="<?= $editing ? h($editBranch['kode_cabang']) : '' ?>">

                        <div class="mb-3">
                            <label class="form-label" for="kode_cabang">Kode cabang <span class="required-mark">*</span></label>
                            <input class="form-control" id="kode_cabang" name="kode_cabang" type="text" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" placeholder="Contoh: 23212" value="<?= $editing ? h($editBranch['kode_cabang']) : '' ?>" <?= $editing ? 'readonly' : 'required' ?>>
                            <div class="form-text"><?= $editing ? 'Kode tidak dapat diubah karena menjadi penghubung data barcode dan permainan.' : 'Gunakan kode angka yang sama dengan nilai cabang pada tb_barcode dan tb_game.' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="nama_cabang">Nama cabang <span class="required-mark">*</span></label>
                            <input class="form-control" id="nama_cabang" name="nama_cabang" type="text" maxlength="150" placeholder="Contoh: Yomart Banjar" value="<?= $editing ? h($editBranch['nama_cabang']) : '' ?>" required>
                        </div>

                        <?php if ($hasSingkatan): ?>
                            <div class="mb-3">
                                <label class="form-label" for="singkatan">Singkatan <?= $singkatanRequired ? '<span class="required-mark">*</span>' : '' ?></label>
                                <input class="form-control" id="singkatan" name="singkatan" type="text" maxlength="50" placeholder="Contoh: BJR" value="<?= $editing ? h($editBranch['singkatan'] ?? '') : '' ?>" <?= $singkatanRequired ? 'required' : '' ?>>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasDcSupport): ?>
                            <div class="mb-3">
                                <label class="form-label" for="dc_support">DC Support <?= $dcSupportRequired ? '<span class="required-mark">*</span>' : '' ?></label>
                                <input class="form-control" id="dc_support" name="dc_support" type="text" maxlength="100" placeholder="Contoh: DC Bandung" value="<?= $editing ? h($editBranch['dc_support'] ?? '') : '' ?>" <?= $dcSupportRequired ? 'required' : '' ?>>
                            </div>
                        <?php endif; ?>

                        <div class="branch-form-actions">
                            <?php if ($editing): ?>
                                <a href="<?= h($adminBase) ?>/cabang.php" class="btn btn-light">Batal</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i><?= $editing ? 'Simpan perubahan' : 'Simpan cabang' ?></button>
                        </div>
                    </form>
                </section>

                <section class="branch-panel branch-list-panel">
                    <div class="branch-list-heading">
                        <div class="panel-heading-copy"><h3>Daftar cabang</h3><p>Cabang yang tersimpan di database.</p></div>
                        <form method="get" class="branch-search">
                            <i class="bi bi-search"></i>
                            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Cari kode atau nama..." aria-label="Cari cabang">
                            <?php if ($search !== ''): ?><a href="<?= h($adminBase) ?>/cabang.php" aria-label="Hapus pencarian"><i class="bi bi-x-circle"></i></a><?php endif; ?>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table branch-table align-middle mb-0">
                            <thead><tr><th>Cabang</th><th>Kode</th><th>Barcode</th><th>Permainan</th><th class="text-end">Tindakan</th></tr></thead>
                            <tbody>
                            <?php if (!$branches): ?>
                                <tr><td colspan="5"><div class="branch-empty"><i class="bi bi-shop-window"></i><strong><?= $search !== '' ? 'Cabang tidak ditemukan' : 'Belum ada data cabang' ?></strong><span><?= $search !== '' ? 'Coba kata kunci lain.' : 'Tambahkan cabang melalui formulir di samping.' ?></span></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td>
                                            <div class="branch-name-cell"><span class="branch-avatar"><i class="bi bi-shop"></i></span><span><strong><?= h($branch['nama_cabang']) ?></strong><small><?= h($branch['singkatan'] ?: 'Cabang') ?><?= !empty($branch['dc_support']) ? ' · ' . h($branch['dc_support']) : '' ?></small></span></div>
                                        </td>
                                        <td><span class="branch-code-pill"><?= h($branch['kode_cabang']) ?></span></td>
                                        <td><span class="branch-count"><?= number_format((int) $branch['total_barcode']) ?></span></td>
                                        <td><span class="branch-count"><?= number_format((int) $branch['total_game']) ?></span></td>
                                        <td>
                                            <div class="branch-actions">
                                                <a class="btn btn-sm btn-outline-secondary" href="<?= h($adminBase) ?>/cabang.php?edit=<?= rawurlencode((string) $branch['kode_cabang']) ?>" title="Ubah cabang"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="<?= h($adminBase) ?>/cabang.php" data-confirm="Hapus data cabang ini? Tindakan hanya dapat dilakukan jika belum ada barcode atau permainan yang terhubung.">
                                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_cabang']) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="kode_cabang" value="<?= h($branch['kode_cabang']) ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus cabang"><i class="bi bi-trash3"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="branch-list-footer">Menampilkan <?= number_format(count($branches)) ?> cabang</div>
                </section>
            </div>
        <?php endif; ?>

        <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.BH_FLASH = <?= json_encode(
    $success !== '' ? ['type' => 'success', 'message' => $success] : ($flashError !== '' ? ['type' => 'error', 'message' => $flashError] : null),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;
</script>
<script src="<?= h($adminBase) ?>/assets/admin.js"></script>
</body>
</html>
