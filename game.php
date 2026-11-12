<?php
require_once __DIR__ . '/game-bootstrap.php';

$sessionId = bhg_current_session_id();
$userId = (string) ($_SESSION['bh_game_user_id'] ?? '');
$branch = (int) ($_SESSION['bh_game_branch'] ?? 0);
if ($sessionId === '' || $userId === '' || $branch < 1) {
    header('Location: ' . bhg_base_url() . '/index.php');
    exit;
}

try {
    bhg_require_consumer_schema($pdo);
    $check = $pdo->prepare(
        'SELECT
            s.id_session,
            s.id_game,
            s.id_user,
            s.status_session,
            s.durasi_detik,
            s.sisa_detik,
            s.deadline_at,
            s.last_activity_at,
            s.started_at,
            s.paused_at,
            s.completed_at,
            g.cabang,
            COALESCE(cfg.nama_game, \'Barcode Hunting\') AS nama_game
        FROM tb_game_session s
        INNER JOIN tb_game g
            ON g.id_game = s.id_game
        LEFT JOIN tb_game_setting cfg
            ON cfg.id_game = g.id_game
        WHERE
            s.id_session = ?
            AND s.id_user = ?
            AND g.cabang = ?
        LIMIT 1'
    );

    $check->execute([
        $sessionId,
        $userId,
        $branch
    ]);

    $session = $check->fetch();
    if (!$session) {
        unset($_SESSION['bh_game_session_id'], $_SESSION['bh_game_user_id'], $_SESSION['bh_game_id'], $_SESSION['bh_game_branch']);
        header('Location: ' . bhg_base_url() . '/index.php?cabang=' . $branch);
        exit;
    }
} catch (Throwable $e) {
    error_log('[Barcode Hunting Game] ' . $e->getMessage());
    http_response_code(500);
    exit('Permainan belum siap. Periksa konfigurasi database dan tabel game.');
}

$baseUrl = bhg_base_url();
$csrf = (string) $_SESSION['bh_game_csrf'];
$branchName = bhg_game_branch_name($pdo, $branch);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f2c9">
    <title><?= bhg_h($session['nama_game']) ?></title>
    <link rel="stylesheet" href="<?= bhg_h($baseUrl) ?>/assets/game.css">
</head>
<body class="consumer-page play-page">
<main class="game-shell play-shell" id="gameApp" data-inactivity-seconds="10">
    <header class="brand-area">
        <!-- Ganti logo/branding melalui CSS .brand-logo -->
        <div class="brand-logo" aria-label="Logo event"></div>
        <div class="branch-chip"><?= bhg_h($branchName) ?></div>
    </header>

    <section class="scanner-section" aria-label="Pemindai barcode">
        <div id="reader" class="scanner-box">
            <div class="scanner-placeholder" id="scannerPlaceholder">
                <div class="scanner-placeholder-icon" aria-hidden="true">▦</div>
                <p>Arahkan kamera ke barcode</p>
                <button type="button" class="scanner-button" id="startScanner">Buka Kamera</button>
            </div>
        </div>
        <p class="scanner-status" id="scannerStatus" aria-live="polite">Kamera belum diaktifkan</p>
    </section>

    <section class="timer-section" aria-live="off">
        <div class="section-label">TIMER</div>
        <div class="timer-value" id="timerValue">--:--</div>
        <div class="progress-line">
            <span id="progressText">0/0</span>
            <span id="progressCaption">Barcode terkumpul</span>
        </div>
        <div class="progress-track"><span id="progressBar"></span></div>
    </section>

    <section class="hint-section" aria-live="polite">
        <div class="section-label">Hint</div>
        <p class="hint-text" id="hintText">Memuat petunjuk...</p>
    </section>

    <footer class="brand-footer" aria-label="Media sosial Yomart">
        <img
            class="footer-social"
            src="<?= bhg_h($baseUrl) ?>/assets/image/sosmed.png"
            alt="Media sosial Yomart"
            width="1921"
            height="81"
        >
    </footer>
</main>

<div class="game-overlay" id="pauseOverlay" hidden>
    <div class="overlay-card">
        <div class="overlay-icon">Ⅱ</div>
        <h2 id="pauseTitle">Permainan dijeda</h2>
        <p id="pauseDescription">Tekan tombol di bawah untuk melanjutkan permainan.</p>
        <button type="button" class="primary-button" id="resumeButton">Lanjutkan Permainan</button>
    </div>
</div>

<div class="game-overlay" id="completeOverlay" hidden>
    <div class="overlay-card">
        <div class="overlay-icon">★</div>
        <h2>Permainan selesai!</h2>
        <p id="completeMessage">Semua barcode berhasil ditemukan.</p>
        <button type="button" class="primary-button" id="finishButton">Selesai</button>
    </div>
</div>

<div class="game-toast" id="gameToast" role="status" aria-live="polite"></div>

<script>
window.BH_GAME_PAGE = {
    baseUrl: <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode($csrf) ?>,
    branch: <?= (int) $branch ?>,
    inactivitySeconds: 10
};
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="<?= bhg_h($baseUrl) ?>/assets/game.js" defer></script>
</body>
</html>
