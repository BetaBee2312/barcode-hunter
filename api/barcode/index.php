<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('GET');
bh_api_require_barcode_schema($pdo);

try {
    $branchRaw = $_GET['cabang'] ?? '';
    $branch = $branchRaw === '' ? null : filter_var($branchRaw, FILTER_VALIDATE_INT);
    if ($branchRaw !== '' && ($branch === false || $branch < 1)) {
        bh_api_json(['success' => false, 'message' => 'Filter cabang tidak valid.'], 422);
    }
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];
    if ($branch !== null) { $where[] = 'b.cabang = ?'; $params[] = $branch; }
    if ($q !== '') {
        $where[] = '(b.id_brc LIKE ? OR b.barcode_serial LIKE ? OR CAST(b.number_barcode AS CHAR) LIKE ? OR b.hint LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $hasBranch = bh_table_exists($pdo, 'tb_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang');
    $branchSelect = $hasBranch ? 'c.nama_cabang' : "CONCAT('Cabang ', b.cabang) AS nama_cabang";
    $join = $hasBranch ? 'LEFT JOIN tb_cabang c ON c.kode_cabang = b.cabang' : '';
    $sql = "SELECT b.id_brc, b.barcode_serial, b.cabang, b.number_barcode, b.hint, {$branchSelect}
            FROM tb_barcode b {$join}";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY b.cabang ASC, b.number_barcode ASC, b.id_brc ASC LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    bh_api_json(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    bh_api_json(['success' => false, 'message' => 'Gagal mengambil daftar barcode. Periksa struktur tb_barcode.'], 500);
}
