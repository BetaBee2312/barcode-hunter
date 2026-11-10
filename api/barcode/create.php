<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('POST');
$data = bh_api_input();
bh_api_csrf_check($data);
bh_api_require_barcode_schema($pdo);

$serial = trim((string) ($data['barcode_serial'] ?? ''));
$hint = trim((string) ($data['hint'] ?? ''));
if ($hint === '' || mb_strlen($hint) > 2000) {
    bh_api_json(['success' => false, 'message' => 'Petunjuk wajib diisi, maksimal 2.000 karakter.'], 422);
}
$branch = filter_var($data['cabang'] ?? null, FILTER_VALIDATE_INT);
$number = filter_var($data['number_barcode'] ?? null, FILTER_VALIDATE_INT);
if ($branch === false || $branch === null || $number === false || $number === null) {
    bh_api_json(['success' => false, 'message' => 'Cabang dan nomor barcode wajib diisi dengan angka yang valid.'], 422);
}
bh_api_validate_barcode_values($pdo, $serial, (int) $branch, (int) $number);

$idBrc = trim((string) ($data['id_brc'] ?? ''));
if ($idBrc === '') $idBrc = bh_random_id('BRC-', 65);
if (mb_strlen($idBrc) > 65) bh_api_json(['success' => false, 'message' => 'ID barcode maksimal 65 karakter.'], 422);

try {
    $stmt = $pdo->prepare('INSERT INTO tb_barcode (id_brc, barcode_serial, cabang, number_barcode, hint) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$idBrc, $serial, (int) $branch, (int) $number, $hint]);
    bh_api_json(['success' => true, 'message' => 'Barcode berhasil ditambahkan ke cabang.', 'data' => ['id_brc' => $idBrc]], 201);
} catch (PDOException $e) {
    bh_api_json(['success' => false, 'message' => 'Gagal menyimpan barcode. Periksa kemungkinan ID barcode duplikat.'], 409);
}
