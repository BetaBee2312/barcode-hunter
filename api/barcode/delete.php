<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
bh_api_method('POST');
$data = bh_api_input();
bh_api_csrf_check($data);
bh_api_require_barcode_schema($pdo);

$idBrc = trim((string) ($data['id_brc'] ?? ''));
if ($idBrc === '' || mb_strlen($idBrc) > 65) bh_api_json(['success' => false, 'message' => 'ID barcode tidak valid.'], 422);
if (bh_api_barcode_is_allocated($pdo, $idBrc)) {
    bh_api_json(['success' => false, 'message' => 'Barcode sudah digunakan oleh sesi permainan dan tidak dapat dihapus.'], 409);
}
try {
    $stmt = $pdo->prepare('DELETE FROM tb_barcode WHERE id_brc = ?');
    $stmt->execute([$idBrc]);
    if ($stmt->rowCount() < 1) bh_api_json(['success' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
    bh_api_json(['success' => true, 'message' => 'Barcode berhasil dihapus.']);
} catch (PDOException $e) {
    bh_api_json(['success' => false, 'message' => 'Barcode tidak dapat dihapus karena masih memiliki relasi data.'], 409);
}
