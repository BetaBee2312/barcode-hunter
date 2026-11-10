<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('POST');
$data = bh_api_input();
bh_api_csrf_check($data);
bh_api_require_barcode_schema($pdo);

$idBrc = trim((string) ($data['id_brc'] ?? ''));
$branch = filter_var($data['cabang'] ?? null, FILTER_VALIDATE_INT);
$number = filter_var($data['number_barcode'] ?? null, FILTER_VALIDATE_INT);
if ($idBrc === '' || mb_strlen($idBrc) > 65 || $branch === false || $branch === null || $number === false || $number === null) {
    bh_api_json(['success' => false, 'message' => 'ID barcode, cabang tujuan, dan nomor barcode wajib valid.'], 422);
}
if (!bh_api_branch_exists($pdo, (int) $branch)) {
    bh_api_json(['success' => false, 'message' => 'Cabang tujuan tidak ditemukan.'], 422);
}
$stmt = $pdo->prepare('SELECT barcode_serial, cabang, number_barcode FROM tb_barcode WHERE id_brc = ? LIMIT 1');
$stmt->execute([$idBrc]);
$current = $stmt->fetch();
if (!$current) bh_api_json(['success' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
if ((int) $current['cabang'] === (int) $branch && (int) $number === (int) $current['number_barcode']) {
    bh_api_json(['success' => true, 'message' => 'Barcode sudah berada di cabang tersebut.']);
}
if (bh_api_barcode_is_allocated($pdo, $idBrc)) {
    bh_api_json(['success' => false, 'message' => 'Barcode sudah dialokasikan ke sesi permainan. Barcode tidak dapat dipindahkan agar riwayat pemain tidak berubah.'], 409);
}
bh_api_validate_barcode_values($pdo, (string) $current['barcode_serial'], (int) $branch, (int) $number, $idBrc);

try {
    $update = $pdo->prepare('UPDATE tb_barcode SET cabang = ?, number_barcode = ? WHERE id_brc = ?');
    $update->execute([(int) $branch, (int) $number, $idBrc]);
    bh_api_json(['success' => true, 'message' => 'Barcode berhasil dipindahkan ke cabang tujuan.']);
} catch (PDOException $e) {
    bh_api_json(['success' => false, 'message' => 'Gagal memindahkan barcode.'], 409);
}
