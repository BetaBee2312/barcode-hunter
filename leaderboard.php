<?php
/** Leaderboard publik per cabang untuk layar TV. */
declare(strict_types=1);
require_once __DIR__ . '/game-bootstrap.php';

// Gunakan helper query yang sama dengan leaderboard admin agar aturan ranking konsisten.
if (!function_exists('bh_table_exists')) {
    function bh_table_exists(PDO $pdo, string $table): bool
    {
        return bhg_table_exists($pdo, $table);
    }
}
if (!function_exists('bh_column_exists')) {
    function bh_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
require_once __DIR__ . '/admin/includes/game_data.php';

date_default_timezone_set('Asia/Jakarta');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isJson = (string) ($_GET['format'] ?? '') === 'json';
$cabang = filter_var($_GET['cabang'] ?? null, FILTER_VALIDATE_INT);
$branchName = '';
$pageError = '';
$rows = [];
$totalPeserta = 0;

if ($cabang === false || $cabang === null || $cabang < 1) {
    $pageError = 'Kode cabang belum valid. Tambahkan kode cabang pada URL, misalnya ?cabang=23212.';
    if ($isJson) bhg_json(['success' => false, 'message' => $pageError], 422);
} else {
    try {
        $branchName = 'Cabang ' . (int) $cabang;

        // Nama cabang dibaca langsung dari master cabang.
        if (bh_table_exists($pdo, 'tb_cabang')
            && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
            && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
            $branchStmt = $pdo->prepare('SELECT nama_cabang FROM tb_cabang WHERE kode_cabang = ? LIMIT 1');
            $branchStmt->execute([(int) $cabang]);
            $foundName = $branchStmt->fetchColumn();
            if ($foundName !== false && trim((string) $foundName) !== '') {
                $branchName = (string) $foundName;
            }
        }

        // Helper mengurutkan jumlah scan terbanyak dahulu, lalu waktu bermain tercepat.
        $rankedRows = bh_game_result_rows($pdo, (int) $cabang);
        $rankedRows = array_values(array_filter(
            $rankedRows,
            static fn(array $row): bool => (int) ($row['total_scan'] ?? 0) > 0
        ));
        $totalPeserta = count($rankedRows);

        // Ambil maksimal lima peserta untuk layar TV.
        foreach (array_slice($rankedRows, 0, 5) as $index => $record) {
            $digits = preg_replace('/\D+/', '', (string) ($record['no_hp'] ?? '')) ?? '';
            $target = max(1, (int) ($record['barcode_list'] ?? 1));
            $scanned = (int) ($record['total_scan'] ?? 0);

            $rows[] = [
                'rank' => $index + 1,
                'nama' => (string) ($record['nama'] ?? ''),
                // Nomor asli tidak pernah dikirim ke browser.
                'telepon' => 'xxxx xxxx ' . substr($digits, -4),
                'point' => $scanned . '/' . $target,
            ];
        }

        if ($isJson) {
            bhg_json([
                'success' => true,
                'cabang' => (int) $cabang,
                'nama_cabang' => $branchName,
                'total_peserta' => $totalPeserta,
                'updated_at' => date(DATE_ATOM),
                'data' => $rows,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[Barcode Hunting Leaderboard] ' . $e->getMessage());
        if ($isJson) {
            bhg_json([
                'success' => false,
                'message' => 'Leaderboard belum dapat dimuat. Periksa struktur tabel V2 dan data master cabang.'
            ], 500);
        }
        $pageError = 'Leaderboard belum dapat dimuat. Periksa struktur tabel V2 dan data master cabang.';
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
    <meta name="theme-color" content="#f7f2c9">
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
                        <h2>Top 5 Pemburu Barcode</h2>
                        <p>Poin terbanyak berada di atas. Jika poin sama, waktu bermain tercepat menjadi penentu.</p>
                    </div>
                    <div class="participant-total"><strong id="participantTotal">0</strong><span>Peserta</span></div>
                </div>
                <div class="leaderboard-columns" aria-hidden="true">
                    <span>PERINGKAT</span><span>NAMA PESERTA</span><span>POINT</span>
                </div>
                <div class="leaderboard-list" id="leaderboardList" aria-live="polite" aria-busy="true">
                    <div class="leaderboard-message">Memuat leaderboard...</div>
                </div>
            </section>
            <footer class="leaderboard-footer">
                <span>Data diperbarui otomatis setiap 5 detik</span>
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
