<?php
/** Shared bootstrap for Barcode Hunting admin modules. */
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

$configCandidates = [
    dirname(__DIR__, 2) . '/config/config.php',
    dirname(__DIR__, 2) . '/config.php',
];
$configPath = null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $configPath = $candidate;
        break;
    }
}
if ($configPath === null) {
    http_response_code(500);
    exit('File koneksi tidak ditemukan. Letakkan koneksi PDO di /config/config.php.');
}
require_once $configPath;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Koneksi database belum tersedia. Pastikan config.php membuat variabel $pdo.');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

function bh_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bh_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function bh_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function bh_csrf_token(): string
{
    if (empty($_SESSION['bh_csrf'])) {
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['bh_csrf'];
}

function bh_csrf_check(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(bh_csrf_token(), $token)) {
        throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function bh_flash(string $type, string $message): void
{
    $_SESSION['bh_flash'] = ['type' => $type, 'message' => $message];
}

function bh_take_flash(): ?array
{
    $flash = $_SESSION['bh_flash'] ?? null;
    unset($_SESSION['bh_flash']);
    return is_array($flash) ? $flash : null;
}

function bh_redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function bh_random_id(string $prefix, int $maxLength = 65): string
{
    $suffixLength = max(8, $maxLength - strlen($prefix));
    $hex = strtoupper(bin2hex(random_bytes((int) ceil($suffixLength / 2))));
    return substr($prefix . $hex, 0, $maxLength);
}

function bh_admin_base(): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'), '/');
    return $base !== '' ? $base : '/admin';
}

function bh_render_flash_script(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $type = in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
        ? $flash['type'] : 'info';
    $message = (string) ($flash['message'] ?? '');
    echo '<script>window.BH_FLASH = ' . json_encode(
        ['type' => $type, 'message' => $message],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) . ';</script>';
}
