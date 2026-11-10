<?php
require_once __DIR__ . '/game-bootstrap.php';

$cabang = filter_input(INPUT_GET, 'cabang', FILTER_VALIDATE_INT);
$game = null;
$branchName = '';
$pageError = '';

if (!$cabang || $cabang < 1) {
    $pageError = 'Kode cabang pada URL tidak valid. Silakan gunakan tautan permainan dari cabang.';
} else {
    try {
        bhg_require_consumer_schema($pdo);
        $game = bhg_active_game($pdo, (int) $cabang);
        $branchName = bhg_game_branch_name($pdo, (int) $cabang);
        if (!$game) $pageError = 'Permainan untuk cabang ini belum diaktifkan oleh admin.';
    } catch (Throwable $e) {
        error_log('[Barcode Hunting] ' . $e->getMessage());
        $pageError = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan saat memuat permainan.';
    }
}

$baseUrl = bhg_base_url();
$csrf = (string) $_SESSION['bh_game_csrf'];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f2c9">
    <title>Barcode Hunting</title>
    <link rel="stylesheet" href="<?= bhg_h($baseUrl) ?>/assets/game.css">
</head>
<body class="consumer-page register-page">
<main class="game-shell register-shell">
    <header class="brand-area">
        <!-- Logo/branding bisa diganti melalui CSS .brand-logo -->
        <div class="brand-logo" aria-label="Logo event"></div>
    </header>

    <section class="register-content">
        <p class="eyebrow">BARCODE HUNTING</p>
        <h1>Mulai Permainan</h1>
        <p class="branch-name"><?= bhg_h($branchName ?: ($cabang ? 'Cabang ' . $cabang : '')) ?></p>

        <?php if ($pageError !== ''): ?>
            <div class="notice notice-error" role="alert"><?= bhg_h($pageError) ?></div>
        <?php else: ?>
            <form id="registerForm" class="register-form" autocomplete="on">
                <input type="hidden" name="cabang" value="<?= (int) $cabang ?>">
                <input type="hidden" name="csrf_token" value="<?= bhg_h($csrf) ?>">

                <label for="nama">Nama konsumen</label>
                <input id="nama" name="nama" type="text" maxlength="100" placeholder="Masukkan nama lengkap" autocomplete="name" required>

                <label for="no_hp">Nomor HP</label>
                <input id="no_hp" name="no_hp" type="tel" inputmode="tel" maxlength="20" placeholder="Contoh: 081234567890" autocomplete="tel" required>

                <button class="primary-button" type="submit" id="startButton">Mulai Permainan</button>
                <p class="form-note">Sudah pernah bermain? Masukkan nama dan nomor HP yang sama untuk melanjutkan permainan.</p>
            </form>
            <div id="formMessage" class="notice" hidden role="status"></div>
        <?php endif; ?>
    </section>

    <footer class="brand-footer">
        <!-- Ganti teks/link footer sesuai branding event melalui index.php dan CSS -->
        <span class="footer-left" aria-hidden="true"></span>
        <span class="footer-right" aria-hidden="true"></span>
    </footer>
</main>
<script>
window.BH_GAME_PAGE = {
    baseUrl: <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode($csrf) ?>,
    inactivitySeconds: 10
};
</script>
<script src="<?= bhg_h($baseUrl) ?>/assets/register.js" defer></script>
</body>
</html>
