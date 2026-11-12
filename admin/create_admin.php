<?php
/**
 * Buat akun admin melalui CLI saja.
 * Jalankan dari root proyek: php admin/create_admin.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not Found');
}

define('BH_ADMIN_ALLOW_GUEST', true);
require_once __DIR__ . '/includes/admin_init.php';

function bh_cli_prompt(string $label): string
{
    echo $label;
    $value = fgets(STDIN);
    return trim($value === false ? '' : $value);
}

if (!bh_table_exists($pdo, 'tb_admin')) {
    fwrite(STDERR, "Tabel tb_admin belum ada. Import admin/sql/admin_auth.sql terlebih dahulu.\n");
    exit(1);
}

echo "=== Buat akun administrator Barcode Hunting ===\n";
$username = strtolower(bh_cli_prompt('Username (3-50 karakter): '));
if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
    fwrite(STDERR, "Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan strip.\n");
    exit(1);
}

$name = bh_cli_prompt('Nama lengkap: ');
$nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
if ($name === '' || $nameLength > 100) {
    fwrite(STDERR, "Nama wajib diisi dan maksimal 100 karakter.\n");
    exit(1);
}

echo "Input password akan terlihat di terminal. Jalankan dari terminal pribadi.\n";
$password = bh_cli_prompt('Password (minimal 12 karakter): ');
$confirm = bh_cli_prompt('Ulangi password: ');
if (strlen($password) < 12) {
    fwrite(STDERR, "Password minimal 12 karakter.\n");
    exit(1);
}
if (!hash_equals($password, $confirm)) {
    fwrite(STDERR, "Konfirmasi password tidak cocok.\n");
    exit(1);
}

$exists = $pdo->prepare('SELECT COUNT(*) FROM tb_admin WHERE username = ?');
$exists->execute([$username]);
if ((int) $exists->fetchColumn() > 0) {
    fwrite(STDERR, "Username sudah digunakan.\n");
    exit(1);
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM tb_admin')->fetchColumn();
$role = $total === 0 ? 'superadmin' : 'admin';
$insert = $pdo->prepare(
    'INSERT INTO tb_admin (username, nama_lengkap, password_hash, role) VALUES (?, ?, ?, ?)'
);
$insert->execute([$username, $name, password_hash($password, PASSWORD_DEFAULT), $role]);

echo "Akun berhasil dibuat.\nUsername: {$username}\nRole: {$role}\n";
echo "Login melalui /admin/login.php\n";
