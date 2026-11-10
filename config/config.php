<?php
/**
 * Konfigurasi koneksi database Barcode Hunting.
 *
 * Letakkan file ini di root project, sejajar dengan folder admin/.
 * Contoh struktur:
 *   barcode-hunting/
 *   ├── admin/index.php
 *   ├── config.php
 *   └── ...
 *
 * Default disiapkan untuk MySQL lokal (mis. Laragon):
 * host 127.0.0.1, port 3306, user root, password kosong.
 * Ubah nilai default atau gunakan environment variable DB_HOST,
 * DB_PORT, DB_NAME, DB_USER, dan DB_PASS.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'db_barcode_hunter';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';
$dbCharset = 'utf8mb4';

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $dbHost,
    $dbPort,
    $dbName,
    $dbCharset
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $exception) {
    // Detail koneksi hanya masuk log server, bukan ditampilkan ke browser.
    error_log('[Barcode Hunting] Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa pengaturan database di config.php.');
}
