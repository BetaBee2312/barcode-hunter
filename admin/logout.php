<?php
/** Logout administrator dan hapus cookie session. */
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_init.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
try {
    bh_csrf_check();
} catch (Throwable $e) {
    http_response_code(419);
    exit('Token keamanan tidak valid. Muat ulang halaman admin.');
}

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
header('Location: ' . bh_admin_base() . '/login.php?logout=1');
exit;
