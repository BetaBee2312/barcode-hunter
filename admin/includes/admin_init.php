<?php
/** Shared bootstrap for Barcode Hunting admin modules. */
declare(strict_types=1);

// Sesi admin dipisahkan dari BH_GAMESESSID milik konsumen.
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'BH_ADMINSESSID') {
        session_name('BH_ADMINSESSID');
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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

// Semua halaman admin dan endpoint /api admin wajib melewati guard ini.
// Hanya login.php dan utilitas setup CLI yang boleh mengaktifkan BH_ADMIN_ALLOW_GUEST.
if (!defined('BH_ADMIN_ALLOW_GUEST') || BH_ADMIN_ALLOW_GUEST !== true) {
    $adminId = (int) ($_SESSION['bh_admin_id'] ?? 0);
    $now = time();
    $lastActivity = (int) ($_SESSION['bh_admin_last_activity'] ?? 0);
    $authenticatedAt = (int) ($_SESSION['bh_admin_authenticated_at'] ?? 0);
    $validSession = $adminId > 0
        && $lastActivity > 0
        && $authenticatedAt > 0
        && ($now - $lastActivity) <= 1800
        && ($now - $authenticatedAt) <= 28800;

    if ($validSession && bh_table_exists($pdo, 'tb_admin')) {
        $adminStmt = $pdo->prepare(
            'SELECT id_admin, username, nama_lengkap, role
             FROM tb_admin
             WHERE id_admin = ? AND is_active = 1
             LIMIT 1'
        );
        $adminStmt->execute([$adminId]);
        $adminRecord = $adminStmt->fetch();
        $validSession = is_array($adminRecord);
    } else {
        $adminRecord = null;
        $validSession = false;
    }

    if (!$validSession) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'] ?: '/',
                'domain' => $cookie['domain'],
                'secure' => (bool) $cookie['secure'],
                'httponly' => (bool) $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        header('Location: ' . bh_admin_base() . '/login.php');
        exit;
    }

    $_SESSION['bh_admin_last_activity'] = $now;
    $_SESSION['bh_admin_username'] = (string) $adminRecord['username'];
    $_SESSION['bh_admin_name'] = (string) $adminRecord['nama_lengkap'];
    $_SESSION['bh_admin_role'] = (string) $adminRecord['role'];
}
