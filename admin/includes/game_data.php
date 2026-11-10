<?php
/** Query helper untuk sesi pemain dan leaderboard versi game-session. */
declare(strict_types=1);

function bh_game_runtime_ready(PDO $pdo): bool
{
    foreach (['tb_game', 'tb_game_setting', 'tb_user', 'tb_game_session', 'tb_session_barcode'] as $table) {
        if (!bh_table_exists($pdo, $table)) return false;
    }
    return true;
}

function bh_game_branch_options(PDO $pdo): array
{
    if (bh_table_exists($pdo, 'tb_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang')) {
        return $pdo->query('SELECT kode_cabang, nama_cabang FROM tb_cabang ORDER BY nama_cabang')->fetchAll();
    }
    return $pdo->query("SELECT DISTINCT cabang AS kode_cabang, CONCAT('Cabang ', cabang) AS nama_cabang FROM tb_game ORDER BY cabang")->fetchAll();
}

function bh_game_result_rows(PDO $pdo, ?int $branch = null): array
{
    if (!bh_game_runtime_ready($pdo)) {
        throw new RuntimeException('Struktur database V2 belum lengkap. Import admin/sql/upgrade_v2.sql terlebih dahulu.');
    }
    $hasBranches = bh_table_exists($pdo, 'tb_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'kode_cabang')
        && bh_column_exists($pdo, 'tb_cabang', 'nama_cabang');
    $branchName = $hasBranches ? 'c.nama_cabang' : "CONCAT('Cabang ', g.cabang)";
    $branchJoin = $hasBranches ? 'LEFT JOIN tb_cabang c ON c.kode_cabang = g.cabang' : '';
    $where = '';
    $params = [];
    if ($branch !== null) { $where = 'WHERE g.cabang = ?'; $params[] = $branch; }

    $sql = "SELECT
                s.id_session, s.id_game, s.id_user, s.status_session,
                s.durasi_detik, s.sisa_detik, s.deadline_at, s.started_at,
                g.cabang, g.barcode_list,
                COALESCE(cfg.nama_game, 'Barcode Hunting') AS nama_game,
                {$branchName} AS nama_cabang,
                u.nama, u.no_hp,
                COALESCE(sc.total_scan, 0) AS total_scan,
                COALESCE(sc.assigned_count, 0) AS assigned_count,
                CASE
                    WHEN s.status_session = 'active' AND s.deadline_at IS NOT NULL
                    THEN GREATEST(s.durasi_detik - GREATEST(TIMESTAMPDIFF(SECOND, NOW(), s.deadline_at), 0), 0)
                    ELSE GREATEST(s.durasi_detik - COALESCE(s.sisa_detik, s.durasi_detik), 0)
                END AS waktu_main_detik
            FROM tb_game_session s
            INNER JOIN tb_game g ON g.id_game = s.id_game
            LEFT JOIN tb_game_setting cfg ON cfg.id_game = g.id_game
            INNER JOIN tb_user u ON u.id_user = s.id_user
            {$branchJoin}
            LEFT JOIN (
                SELECT id_session,
                    COUNT(*) AS assigned_count,
                    SUM(CASE WHEN status_scan = 'scanned' THEN 1 ELSE 0 END) AS total_scan
                FROM tb_session_barcode
                GROUP BY id_session
            ) sc ON sc.id_session = s.id_session
            {$where}
            ORDER BY g.cabang ASC, total_scan DESC, waktu_main_detik ASC, s.started_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
