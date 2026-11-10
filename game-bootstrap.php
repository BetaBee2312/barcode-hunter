<?php
/** Bootstrap halaman konsumen Barcode Hunting. Letakkan file ini di root proyek. */
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

// Cookie sesi konsumen dipisah dari sesi admin agar tidak saling menimpa.
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== 'BH_GAMESESSID') {
        session_name('BH_GAMESESSID');
    }
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

$configCandidates = [
    __DIR__ . '/config/config.php',
    __DIR__ . '/config.php',
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
    exit('Konfigurasi database tidak ditemukan. Pastikan file config/config.php tersedia.');
}

require_once $configPath;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Koneksi database belum tersedia. Pastikan config.php menyediakan variabel $pdo.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

if (empty($_SESSION['bh_game_csrf'])) {
    $_SESSION['bh_game_csrf'] = bin2hex(random_bytes(32));
}

function bhg_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function bhg_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function bhg_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function bhg_base_url(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(dirname($script), '/.');
    return $base === '' ? '' : $base;
}

function bhg_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function bhg_csrf_check(array $input): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
    $expected = (string) ($_SESSION['bh_game_csrf'] ?? '');
    if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
        bhg_json(['success' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 419);
    }
}

function bhg_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bhg_read_input(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return $_POST;
}

function bhg_require_consumer_schema(PDO $pdo): void
{
    $requiredTables = ['tb_game', 'tb_game_setting', 'tb_barcode', 'tb_user', 'tb_game_session', 'tb_session_barcode'];
    $missing = [];
    foreach ($requiredTables as $table) {
        if (!bhg_table_exists($pdo, $table)) $missing[] = $table;
    }
    if ($missing) {
        throw new RuntimeException('Database belum lengkap. Tabel belum ditemukan: ' . implode(', ', $missing) . '. Periksa migrasi Admin V2.');
    }
}

function bhg_active_game(PDO $pdo, int $branch): ?array
{
    $stmt = $pdo->prepare(
        'SELECT g.id_game, g.cabang, g.barcode_list, cfg.nama_game, cfg.durasi_detik
         FROM tb_game g
         INNER JOIN tb_game_setting cfg ON cfg.id_game = g.id_game
         WHERE g.cabang = ? AND cfg.aktif = 1
         ORDER BY cfg.updated_at DESC
         LIMIT 1'
    );
    $stmt->execute([$branch]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function bhg_game_branch_name(PDO $pdo, int $branch): string
{
    if (bhg_table_exists($pdo, 'tb_cabang')) {
        $stmt = $pdo->prepare('SELECT nama_cabang FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
        $stmt->execute([$branch]);
        $name = $stmt->fetchColumn();
        if ($name !== false && $name !== '') return (string) $name;
    }
    return 'Cabang ' . $branch;
}

function bhg_current_session_id(): string
{
    return (string) ($_SESSION['bh_game_session_id'] ?? '');
}
