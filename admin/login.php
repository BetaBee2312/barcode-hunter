<?php
/** Login administrator Barcode Hunting. */
declare(strict_types=1);

define('BH_ADMIN_ALLOW_GUEST', true);
require_once __DIR__ . '/includes/admin_init.php';

$adminBase = bh_admin_base();
$error = '';
$notice = isset($_GET['logout']) ? 'Kamu sudah keluar dari panel admin.' : '';
$tableReady = bh_table_exists($pdo, 'tb_admin');

if (!empty($_SESSION['bh_admin_id']) && $tableReady) {
    $last = (int) ($_SESSION['bh_admin_last_activity'] ?? 0);
    $authenticatedAt = (int) ($_SESSION['bh_admin_authenticated_at'] ?? 0);
    $stillValid = $last > 0 && $authenticatedAt > 0
        && (time() - $last) <= 1800 && (time() - $authenticatedAt) <= 28800;
    if ($stillValid) {
        $check = $pdo->prepare('SELECT id_admin FROM tb_admin WHERE id_admin = ? AND is_active = 1 LIMIT 1');
        $check->execute([(int) $_SESSION['bh_admin_id']]);
        if ($check->fetchColumn()) bh_redirect($adminBase . '/index.php');
    }
    $_SESSION = [];
    session_regenerate_id(true);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        bh_csrf_check();
        if (!$tableReady) {
            throw new RuntimeException('Tabel admin belum tersedia. Import admin/sql/admin_auth.sql terlebih dahulu.');
        }

        $lockedUntil = (int) ($_SESSION['bh_admin_login_locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            throw new RuntimeException('Terlalu banyak percobaan login. Coba lagi dalam ' . max(1, (int) ceil(($lockedUntil - time()) / 60)) . ' menit.');
        }

        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('Username dan password wajib diisi.');
        }

        $stmt = $pdo->prepare(
            'SELECT id_admin, username, nama_lengkap, password_hash, role, is_active
             FROM tb_admin WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || (int) $admin['is_active'] !== 1 || !password_verify($password, (string) $admin['password_hash'])) {
            $attempts = (int) ($_SESSION['bh_admin_login_attempts'] ?? 0) + 1;
            $_SESSION['bh_admin_login_attempts'] = $attempts;
            if ($attempts >= 5) {
                $_SESSION['bh_admin_login_locked_until'] = time() + 900;
                $_SESSION['bh_admin_login_attempts'] = 0;
                throw new RuntimeException('Terlalu banyak percobaan login. Akses login dikunci selama 15 menit.');
            }
            throw new RuntimeException('Username atau password salah.');
        }

        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['bh_admin_id'] = (int) $admin['id_admin'];
        $_SESSION['bh_admin_username'] = (string) $admin['username'];
        $_SESSION['bh_admin_name'] = (string) $admin['nama_lengkap'];
        $_SESSION['bh_admin_role'] = (string) $admin['role'];
        $_SESSION['bh_admin_authenticated_at'] = time();
        $_SESSION['bh_admin_last_activity'] = time();
        $_SESSION['bh_csrf'] = bin2hex(random_bytes(32));

        $lastLogin = $pdo->prepare('UPDATE tb_admin SET last_login_at = NOW() WHERE id_admin = ?');
        $lastLogin->execute([(int) $admin['id_admin']]);
        bh_redirect($adminBase . '/index.php');
    } catch (Throwable $e) {
        $error = $e instanceof PDOException
            ? 'Login belum dapat diproses. Periksa struktur tabel admin.'
            : $e->getMessage();
    }
}

$csrf = bh_csrf_token();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f2c9">
    <title>Login Administrator | Barcode Hunting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= bh_h($adminBase) ?>/assets/auth.css">
</head>
<body class="admin-auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <img src="../assets/image/logo.png" alt="Barcode Hunting" class="auth-logo">
            <span class="auth-kicker">ADMINISTRATOR</span>
            <h1>Barcode Hunting</h1>
            <p>Masuk untuk mengelola cabang, barcode, peserta, dan permainan.</p>
        </section>
        <section class="auth-card">
            <div class="auth-card-heading">
                <span class="auth-icon"><i class="bi bi-shield-lock"></i></span>
                <div><h2>Selamat datang</h2><p>Masuk ke panel administrator</p></div>
            </div>
            <?php if (!$tableReady): ?>
                <div class="alert alert-warning small">
                    Tabel admin belum dibuat. Import <code>admin/sql/admin_auth.sql</code>, lalu jalankan
                    <code>php admin/create_admin.php</code> dari terminal proyek.
                </div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?><div class="alert alert-success small"><?= bh_h($notice) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger small"><?= bh_h($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= bh_h($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="auth-input-wrap"><i class="bi bi-person"></i><input class="form-control" id="username" name="username" type="text" maxlength="50" autocomplete="username" placeholder="Masukkan username" required autofocus></div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input-wrap"><i class="bi bi-lock"></i><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required></div>
                </div>
                <button class="btn auth-submit w-100" type="submit" <?= !$tableReady ? 'disabled' : '' ?>>Masuk <i class="bi bi-arrow-right ms-1"></i></button>
            </form>
            <div class="auth-footnote"><i class="bi bi-shield-check me-1"></i> Akses khusus administrator.</div>
        </section>
        <footer>BARCODE HUNTING ADMIN &copy; <?= date('Y') ?></footer>
    </main>
</body>
</html>
