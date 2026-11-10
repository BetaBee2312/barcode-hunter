<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('GET');

try {
    if (bh_table_exists($pdo, 'tb_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
        $rows = $pdo->query('SELECT kode_cabang, nama_cabang FROM tb_cabang ORDER BY nama_cabang ASC')->fetchAll();
    } else {
        bh_api_require_barcode_schema($pdo);
        $rows = $pdo->query("SELECT DISTINCT cabang AS kode_cabang, CONCAT('Cabang ', cabang) AS nama_cabang FROM tb_barcode ORDER BY cabang ASC")->fetchAll();
    }
    bh_api_json(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    bh_api_json(['success' => false, 'message' => 'Gagal mengambil daftar cabang.'], 500);
}
