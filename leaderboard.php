<?php
/** Leaderboard publik per cabang untuk layar TV. */
declare(strict_types=1);
require_once __DIR__ . '/game-bootstrap.php';

date_default_timezone_set('Asia/Jakarta');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isJson = (string) ($_GET['format'] ?? '') === 'json';
$cabangRaw = trim((string) ($_GET['cabang'] ?? ''));
$cabang = filter_var($cabangRaw, FILTER_VALIDATE_INT);
$branchName = '';
$pageError = '';
$rows = [];

if ($cabang === false || $cabang < 1) {
    $pageError = 'Kode cabang belum valid. Tambahkan kode cabang pada URL, misalnya ?cabang=23212.';
    if ($isJson) bhg_json(['success' => false, 'message' => $pageError], 422);
} else {
    try {
        bhg_require_consumer_schema($pdo);
        $branchName = 'Cabang ' . (int) $cabang;
        if (bhg_table_exists($pdo, 'tb_cabang')
            && bhg_column_exists($pdo, 'tb_cabang', 'kode_cabang')
            && bhg_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
            $branchStmt = $pdo->prepare('SELECT nama_cabang FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
            $branchStmt->execute([(int) $cabang]);
            $foundName = $branchStmt->fetchColumn();
            if ($foundName !== false && $foundName !== '') $branchName = (string) $foundName;
        }

        $sql = "SELECT
                    s.id_session, s.started_at, s.durasi_detik, s.sisa_detik,
                    s.deadline_at, s.status_session, u.nama, u.no_hp,
                    g.barcode_list AS target_barcode,
                    COALESCE(sc.total_scan, 0) AS total_scan,
                    CASE
                        WHEN s.status_session = 'active' AND s.deadline_at IS NOT NULL
                        THEN GREATEST(s.durasi_detik - GREATEST(TIMESTAMPDIFF(SECOND, NOW(), s.deadline_at), 0), 0)
                        ELSE GREATEST(s.durasi_detik - COALESCE(s.sisa_detik, s.durasi_detik), 0)
                    END AS waktu_main_detik
                FROM tb_game_session s
                INNER JOIN tb_game g ON g.id_game = s.id_game
                INNER JOIN tb_user u ON u.id_user = s.id_user
                LEFT JOIN (
                    SELECT id_session,
                        SUM(CASE WHEN status_scan = 'scanned' THEN 1 ELSE 0 END) AS total_scan
                    FROM tb_session_barcode
                    GROUP BY id_session
                ) sc ON sc.id_session = s.id_session
                WHERE g.cabang = ?
                  AND COALESCE(sc.total_scan, 0) > 0
                ORDER BY total_scan DESC, waktu_main_detik ASC, s.started_at ASC, s.id_session ASC
                LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int) $cabang]);

        foreach ($stmt->fetchAll() as $index => $record) {
            $digits = preg_replace('/\D+/', '', (string) $record['no_hp']) ?? '';
            $target = max(1, (int) $record['target_barcode']);
            $scanned = (int) $record['total_scan'];
            // Nomor asli tidak pernah dimasukkan ke payload publik.
            $rows[] = [
                'rank' => $index + 1,
                'nama' => (string) $record['nama'],
                'telepon' => 'xxxx xxxx ' . substr($digits, -4),
                'point' => $scanned . '/' . $target,
            ];
        }

        if ($isJson) {
            bhg_json([
                'success' => true,
                'cabang' => (int) $cabang,
                'nama_cabang' => $branchName,
                'total_peserta' => count($rows),
                'updated_at' => date(DATE_ATOM),
                'data' => $rows,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[Barcode Hunting Leaderboard] ' . $e->getMessage());
        if ($isJson) bhg_json(['success' => false, 'message' => 'Leaderboard belum dapat dimuat. Periksa konfigurasi database.'], 500);
        $pageError = 'Leaderboard belum dapat dimuat. Periksa konfigurasi database dan tabel game.';
    }
}

$baseUrl = bhg_base_url();
$cssPath = __DIR__ . '/assets/leaderboard.css';
$jsPath = __DIR__ . '/assets/leaderboard.js';
$cssVersion = is_file($cssPath) ? (string) filemtime($cssPath) : (string) time();
$jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : (string) time();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101827">
    <meta name="robots" content="noindex, nofollow">
    <title>Leaderboard <?= bhg_h($branchName ?: 'Barcode Hunting') ?></title>
    <link rel="stylesheet" href="<?= bhg_h($baseUrl) ?>/assets/leaderboard.css?v=<?= rawurlencode($cssVersion) ?>">
</head>
<body class="leaderboard-page">
    <main class="leaderboard-board" id="leaderboardBoard">
        <header class="leaderboard-header">
            <div class="leaderboard-brand">
                <img src="<?= bhg_h($baseUrl) ?>/assets/image/logo.png" alt="" class="leaderboard-logo">
                <div>
                    <p class="eyebrow">BARCODE HUNTING</p>
                    <h1>Leaderboard</h1>
                    <div class="branch-name"><?= bhg_h($branchName ?: 'Peringkat Peserta') ?></div>
                </div>
            </div>
            <div class="leaderboard-live">
                <span class="live-indicator"><i></i> LIVE</span>
                <span class="last-updated" id="lastUpdated">Menghubungkan...</span>
            </div>
        </header>

        <?php if ($pageError !== ''): ?>
            <section class="leaderboard-message leaderboard-error">
                <strong>Leaderboard belum tersedia</strong>
                <p><?= bhg_h($pageError) ?></p>
            </section>
        <?php else: ?>
            <section class="leaderboard-content">
                <div class="leaderboard-intro">
                    <div>
                        <span class="section-kicker">PERINGKAT CABANG</span>
                        <h2>Siapa yang paling unggul?</h2>
                        <p>Jumlah barcode berhasil terbanyak diutamakan. Jika poin sama, waktu bermain tercepat berada di atas.</p>
                    </div>
                    <div class="participant-total"><strong id="participantTotal">0</strong><span>Peserta</span></div>
                </div>
                <div class="leaderboard-columns" aria-hidden="true">
                    <span>RANK</span><span>NAMA PESERTA</span><span>POINT</span>
                </div>
                <div class="leaderboard-list" id="leaderboardList" aria-live="polite" aria-busy="true">
                    <div class="leaderboard-message">Memuat leaderboard...</div>
                </div>
            </section>
            <footer class="leaderboard-footer">
                <span>Leaderboard diperbarui otomatis setiap 5 detik</span>
                <span>BARCODE HUNTING <b>•</b> <?= date('Y') ?></span>
            </footer>
            <script>
                window.BH_LEADERBOARD_CONFIG = {
                    baseUrl: <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>,
                    cabang: <?= (int) $cabang ?>
                };
            </script>
            <script src="<?= bhg_h($baseUrl) ?>/assets/leaderboard.js?v=<?= rawurlencode($jsVersion) ?>" defer></script>
        <?php endif; ?>
    </main>
</body>
</html>
