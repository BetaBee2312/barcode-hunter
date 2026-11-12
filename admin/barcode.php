<?php
/** Data Barcode. Semua operasi CRUD dilakukan melalui API JSON. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';

$adminBase = bh_admin_base();
$apiBase = rtrim(dirname($adminBase), '/') . '/api';
if ($apiBase === '/api') $apiBase = '/api';
$activeMenu = 'barcode';
$pageTitle = 'Data Barcode';
$pageSubtitle = 'Master barcode dan penempatan per cabang';
$pageScript = 'barcode.js';
$barcodeCssPath = __DIR__ . '/assets/barcode.css';
$barcodeCssVersion = is_file($barcodeCssPath) ? (string) filemtime($barcodeCssPath) : (string) time();
$errors = [];
$branches = [];
$initialBranch = '';

try {
    if (!bh_table_exists($pdo, 'tb_barcode')) {
        throw new RuntimeException('Tabel tb_barcode belum ditemukan.');
    }
    foreach (['id_brc', 'barcode_serial', 'cabang', 'number_barcode', 'hint'] as $column) {
        if (!bh_column_exists($pdo, 'tb_barcode', $column)) {
            throw new RuntimeException("Kolom tb_barcode.{$column} tidak ditemukan.");
        }
    }
    if (bh_table_exists($pdo, 'tb_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
        $branches = $pdo->query('SELECT kode_cabang, nama_cabang FROM tb_cabang ORDER BY nama_cabang')->fetchAll();
    } else {
        $branches = $pdo->query("SELECT DISTINCT cabang AS kode_cabang, CONCAT('Cabang ', cabang) AS nama_cabang FROM tb_barcode ORDER BY cabang")->fetchAll();
    }
    $initialBranch = isset($_GET['cabang']) && ctype_digit((string) $_GET['cabang']) ? (string) $_GET['cabang'] : '';
} catch (Throwable $e) {
    $errors[] = $e instanceof PDOException ? 'Tidak dapat membaca struktur database.' : $e->getMessage();
}

$flash = bh_take_flash();
$config = [
    'apiBase' => $apiBase,
    'csrf' => bh_csrf_token(),
    'initialBranch' => $initialBranch,
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= bh_h($pageTitle) ?> | Barcode Hunting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/admin.css">
    <link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/modules.css">
    <link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/barcode.css?v=<?= rawurlencode($barcodeCssVersion) ?>">
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="admin-main">
<?php require __DIR__ . '/includes/topbar.php'; ?>
<section class="page-content">
    <div class="module-heading">
        <div><h2>Master barcode</h2><p>Tambahkan barcode ke cabang, ubah data, atau pindahkan barcode ke cabang lain.</p></div>
        <div class="module-stat"><i class="bi bi-upc-scan"></i><span><strong id="barcodeTotal">0</strong><small>Barcode ditemukan</small></span></div>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= bh_h($error) ?></div><?php endforeach; ?>
    <?php if (!$errors): ?>
    <div class="module-grid module-grid-barcode">
        <section class="module-card module-form-card">
            <div class="module-card-heading"><span class="module-icon"><i class="bi bi-upc-scan"></i></span><div><h3 id="formTitle">Tambah barcode</h3><p id="formSubtitle">Masukkan serial, cabang, nomor barcode, dan petunjuk produk.</p></div></div>
            <form id="barcodeForm" class="row g-3" autocomplete="off">
                <input type="hidden" id="barcodeId" value="">
                <div class="col-12"><label class="form-label" for="barcodeSerial">Serial barcode <span class="text-danger">*</span></label><input class="form-control" id="barcodeSerial" maxlength="65" required placeholder="Contoh: 8991000123456"></div>
                <div class="col-12"><label class="form-label" for="barcodeBranch">Cabang <span class="text-danger">*</span></label><select class="form-select" id="barcodeBranch" required><option value="">Memuat cabang...</option></select><div class="form-text" id="branchHelp">Barcode akan menjadi milik cabang yang dipilih.</div></div>
                <div class="col-12"><label class="form-label" for="barcodeNumber">Nomor barcode <span class="text-danger">*</span></label><input class="form-control" type="number" id="barcodeNumber" min="1" step="1" required placeholder="Contoh: 1"></div>
                <div class="col-12"><label class="form-label" for="barcodeHint">Petunjuk / hint <span class="text-danger">*</span></label><textarea class="form-control" id="barcodeHint" rows="3" maxlength="2000" required placeholder="Contoh: Aku adalah sebuah snack dengan bentuk segitiga."></textarea><div class="form-text">Petunjuk ini akan ditampilkan kepada konsumen. Jangan masukkan serial atau jawaban barcode ke dalam hint.</div></div>
                <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-primary" id="saveBarcodeButton" type="submit"><i class="bi bi-plus-circle me-1"></i> Simpan barcode</button><button class="btn btn-light d-none" id="cancelEditButton" type="button">Batal edit</button></div>
            </form>
            <div class="barcode-note"><i class="bi bi-info-circle"></i><span><strong>ID barcode</strong> dibuat otomatis. Serial barcode adalah nilai yang dibaca scanner.</span></div>
        </section>

        <section class="module-card module-list-card">
            <div class="module-card-heading"><span class="module-icon"><i class="bi bi-list-ul"></i></span><div><h3>Daftar barcode</h3><p>Gunakan filter untuk melihat barcode pada cabang tertentu.</p></div></div>
            <form id="barcodeFilterForm" class="module-filters" onsubmit="return false">
                <input class="form-control" type="search" id="barcodeSearch" placeholder="Cari serial, ID, atau nomor">
                <select class="form-select" id="filterBranch"><option value="">Semua cabang</option></select>
                <button class="btn btn-outline-primary" type="submit" id="refreshBarcodeButton" title="Cari"><i class="bi bi-search"></i></button>
                <button class="btn btn-light" type="button" id="resetBarcodeFilter" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <div class="barcode-download-toolbar">
                <div><strong><i class="bi bi-printer me-1"></i> Label fisik barcode</strong><small>Pilih satu cabang untuk mencetak semua label yang sedang tampil.</small></div>
                <button class="btn btn-outline-primary" type="button" id="printBranchLabels"><i class="bi bi-printer me-1"></i> Cetak label cabang</button>
            </div>
            <div class="table-responsive"><table class="table module-table align-middle barcode-table"><thead><tr><th>Barcode</th><th>Petunjuk</th><th>Cabang</th><th>Nomor</th><th class="text-end">Aksi</th></tr></thead><tbody id="barcodeTableBody"><tr><td colspan="5"><div class="module-empty"><i class="bi bi-arrow-repeat"></i><strong>Memuat data barcode...</strong></div></td></tr></tbody></table></div>
        </section>
    </div>
    <?php endif; ?>
    <div class="page-footer">Barcode Hunting Admin &copy; <?= date('Y') ?></div>
</section>
</main>

<div class="modal fade" id="assignBarcodeModal" tabindex="-1" aria-labelledby="assignBarcodeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="assignBarcodeForm">
            <div class="modal-header"><div><h5 class="modal-title" id="assignBarcodeTitle">Pindahkan barcode</h5><small class="text-secondary" id="assignBarcodeSerial"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <input type="hidden" id="assignBarcodeId">
                <div class="mb-3"><label class="form-label" for="assignBranch">Cabang tujuan</label><select class="form-select" id="assignBranch" required></select></div>
                <div><label class="form-label" for="assignNumber">Nomor barcode di cabang tujuan</label><input class="form-control" type="number" id="assignNumber" min="1" step="1" required></div>
                <div class="form-text mt-2">Barcode yang sudah masuk ke sesi pemain tidak dapat dipindahkan agar riwayat permainan tetap konsisten.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Pindahkan barcode</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="downloadBarcodeModal" tabindex="-1" aria-labelledby="downloadBarcodeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header">
            <div><h5 class="modal-title" id="downloadBarcodeTitle">Pilih format barcode</h5><small class="text-secondary" id="downloadBarcodeDescription">Pilih bentuk barcode yang ingin diunduh.</small></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
            <div class="barcode-format-options">
                <button type="button" class="barcode-format-option" data-format="linear">
                    <span class="barcode-format-preview barcode-format-preview-linear"><i class="bi bi-upc"></i></span>
                    <span><strong>Barcode panjang</strong><small>Linear CODE128, tampilannya seperti barcode produk / UPC.</small></span>
                    <i class="bi bi-download"></i>
                </button>
                <button type="button" class="barcode-format-option" data-format="qr">
                    <span class="barcode-format-preview barcode-format-preview-qr"><i class="bi bi-qr-code"></i></span>
                    <span><strong>QR Code kotak</strong><small>QR berbentuk persegi, bisa dipindai kamera HP.</small></span>
                    <i class="bi bi-download"></i>
                </button>
            </div>
            <div class="form-text mt-3" id="downloadBarcodeNote">Gambar hanya berisi pola barcode, tanpa tulisan nomor, serial, atau ID.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
    </div></div>
</div>

<script>window.BH_BARCODE_CONFIG = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
