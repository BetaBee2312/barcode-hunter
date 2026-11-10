<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('POST');
$data = bh_api_input();
bh_api_csrf_check($data);
bh_api_require_barcode_schema($pdo);

$idBrc = trim((string) ($data['id_brc'] ?? ''));
$serial = trim((string) ($data['barcode_serial'] ?? ''));
$hint = trim((string) ($data['hint'] ?? ''));
if ($hint === '' || mb_strlen($hint) > 2000) {
    bh_api_json(['success' => false, 'message' => 'Petunjuk wajib diisi, maksimal 2.000 karakter.'], 422);
}
$number = filter_var($data['number_barcode'] ?? null, FILTER_VALIDATE_INT);
if ($idBrc === '' || mb_strlen($idBrc) > 65 || $number === false || $number === null) {
    bh_api_json(['success' => false, 'message' => 'Data barcode tidak lengkap atau tidak valid.'], 422);
}

$stmt = $pdo->prepare('SELECT cabang FROM tb_barcode WHERE id_brc = ? LIMIT 1');
$stmt->execute([$idBrc]);
$existing = $stmt->fetch();
if (!$existing) bh_api_json(['success' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
$branch = (int) $existing['cabang'];
bh_api_validate_barcode_values($pdo, $serial, $branch, (int) $number, $idBrc);

try {
    $update = $pdo->prepare('UPDATE tb_barcode SET barcode_serial = ?, number_barcode = ?, hint = ? WHERE id_brc = ?');
    $update->execute([$serial, (int) $number, $hint, $idBrc]);
    bh_api_json(['success' => true, 'message' => 'Data barcode berhasil diperbarui.']);
} catch (PDOException $e) {
    bh_api_json(['success' => false, 'message' => 'Gagal memperbarui barcode.'], 409);
}
