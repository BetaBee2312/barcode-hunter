<?php
/** Bootstrap API admin Barcode Hunting. Semua endpoint memakai sesi dan token CSRF admin. */
declare(strict_types=1);
require_once __DIR__ . '/../admin/includes/admin_init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function bh_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bh_api_method(string ...$allowed): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        bh_api_json(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.'], 405);
    }
}

function bh_api_input(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST;
}

function bh_api_csrf_check(array $data = []): void
{
    $headerToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $bodyToken = (string) ($data['csrf_token'] ?? '');
    $token = $headerToken !== '' ? $headerToken : $bodyToken;
    if ($token === '' || !hash_equals(bh_csrf_token(), $token)) {
        bh_api_json(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman admin.'], 419);
    }
}

function bh_api_branch_exists(PDO $pdo, int $branch): bool
{
    if (!bh_table_exists($pdo, 'tb_cabang')
        || !bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')) {
        // Instalasi lama dapat belum mempunyai master cabang. Kode cabang tetap divalidasi positif.
        return $branch > 0;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
    $stmt->execute([$branch]);
    return (bool) $stmt->fetchColumn();
}

function bh_api_barcode_is_allocated(PDO $pdo, string $idBrc): bool
{
    foreach (['tb_session_barcode', 'tb_game_barcode'] as $table) {
        if (bh_table_exists($pdo, $table) && bh_column_exists($pdo, $table, 'id_brc')) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE id_brc = ? LIMIT 1");
            $stmt->execute([$idBrc]);
            if ($stmt->fetchColumn()) return true;
        }
    }
    return false;
}

function bh_api_require_barcode_schema(PDO $pdo): void
{
    if (!bh_table_exists($pdo, 'tb_barcode')) {
        bh_api_json(['success' => false, 'message' => 'Tabel tb_barcode belum tersedia.'], 500);
    }
    foreach (['id_brc', 'barcode_serial', 'cabang', 'number_barcode', 'hint'] as $column) {
        if (!bh_column_exists($pdo, 'tb_barcode', $column)) {
            bh_api_json(['success' => false, 'message' => "Kolom tb_barcode.{$column} tidak ditemukan."], 500);
        }
    }
}

function bh_api_validate_barcode_values(PDO $pdo, string $serial, int $branch, int $number, string $excludeId = ''): void
{
    if ($serial === '' || mb_strlen($serial) > 65) {
        bh_api_json(['success' => false, 'message' => 'Serial barcode wajib diisi, maksimal 65 karakter.'], 422);
    }
    if ($branch < 1 || !bh_api_branch_exists($pdo, $branch)) {
        bh_api_json(['success' => false, 'message' => 'Cabang tidak ditemukan atau tidak valid.'], 422);
    }
    if ($number < 1) {
        bh_api_json(['success' => false, 'message' => 'Nomor barcode minimal 1.'], 422);
    }

    $serialSql = 'SELECT 1 FROM tb_barcode WHERE cabang = ? AND barcode_serial = ?';
    $serialParams = [$branch, $serial];
    if ($excludeId !== '') { $serialSql .= ' AND id_brc <> ?'; $serialParams[] = $excludeId; }
    $serialSql .= ' LIMIT 1';
    $stmt = $pdo->prepare($serialSql);
    $stmt->execute($serialParams);
    if ($stmt->fetchColumn()) {
        bh_api_json(['success' => false, 'message' => 'Serial barcode sudah terdaftar di cabang tujuan.'], 409);
    }

    $numberSql = 'SELECT 1 FROM tb_barcode WHERE cabang = ? AND number_barcode = ?';
    $numberParams = [$branch, $number];
    if ($excludeId !== '') { $numberSql .= ' AND id_brc <> ?'; $numberParams[] = $excludeId; }
    $numberSql .= ' LIMIT 1';
    $stmt = $pdo->prepare($numberSql);
    $stmt->execute($numberParams);
    if ($stmt->fetchColumn()) {
        bh_api_json(['success' => false, 'message' => 'Nomor barcode tersebut sudah dipakai di cabang tujuan.'], 409);
    }
}
