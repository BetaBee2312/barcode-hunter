<?php
require_once __DIR__ . '/game-bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');

try {
    bhg_require_consumer_schema($pdo);

    if ($action === 'register') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            bhg_json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
        }
        $input = bhg_read_input();
        bhg_csrf_check($input);

        $branch = filter_var($input['cabang'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($input['nama'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($input['no_hp'] ?? ''));

        if ($branch === false || $branch < 1) bhg_json(['success' => false, 'message' => 'Cabang tidak valid.'], 422);
        if ($name === '' || bhg_length($name) > 100) bhg_json(['success' => false, 'message' => 'Nama wajib diisi, maksimal 100 karakter.'], 422);
        if ($phone === '' || strlen($phone) < 8 || strlen($phone) > 20) bhg_json(['success' => false, 'message' => 'Nomor HP tidak valid.'], 422);

        $pdo->beginTransaction();
        try {
            $game = bhg_active_game($pdo, (int) $branch);
            if (!$game) throw new RuntimeException('Permainan cabang ini sedang tidak aktif.');

            $target = (int) $game['barcode_list'];
            $duration = (int) $game['durasi_detik'];
            if ($target < 1 || $duration < 1) throw new RuntimeException('Konfigurasi permainan belum benar. Hubungi petugas.');

            $availableStmt = $pdo->prepare('SELECT COUNT(*) FROM tb_barcode WHERE cabang = ?');
            $availableStmt->execute([(int) $branch]);
            $available = (int) $availableStmt->fetchColumn();
            if ($available < $target) throw new RuntimeException('Barcode untuk cabang ini belum mencukupi. Hubungi petugas.');

            $userStmt = $pdo->prepare('SELECT id_user, nama FROM tb_user WHERE no_hp = ? LIMIT 1 FOR UPDATE');
            $userStmt->execute([$phone]);
            $user = $userStmt->fetch();

            if ($user) {
                if (bhg_lower(trim((string) $user['nama'])) !== bhg_lower($name)) {
                    throw new RuntimeException('Nomor HP sudah terdaftar dengan nama berbeda. Periksa kembali nama yang dimasukkan.');
                }
                $userId = (string) $user['id_user'];
            } else {
                $userId = 'USR' . strtoupper(bin2hex(random_bytes(6)));
                $insertUser = $pdo->prepare('INSERT INTO tb_user (id_user, nama, no_hp) VALUES (?, ?, ?)');
                $insertUser->execute([$userId, $name, $phone]);
            }

            $sessionStmt = $pdo->prepare('SELECT * FROM tb_game_session WHERE id_game = ? AND id_user = ? LIMIT 1 FOR UPDATE');
            $sessionStmt->execute([(string) $game['id_game'], $userId]);
            $session = $sessionStmt->fetch();

            if ($session) {
                $sessionId = (string) $session['id_session'];
                $status = (string) $session['status_session'];
                $remaining = (int) $session['sisa_detik'];

                if ($status === 'active') {
                    $timeStmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), ?), 0)');
                    $timeStmt->execute([$session['deadline_at']]);
                    $remaining = (int) $timeStmt->fetchColumn();
                    if ($remaining <= 0) {
                        $expire = $pdo->prepare("UPDATE tb_game_session SET status_session = 'expired', sisa_detik = 0, deadline_at = NULL WHERE id_session = ?");
                        $expire->execute([$sessionId]);
                        $pdo->commit();
                        bhg_json(['success' => false, 'message' => 'Waktu permainan kamu sudah habis.'], 422);
                    }

                    // Bila pemain kembali setelah tab ditutup/ditinggalkan lebih dari 10 detik, bekukan timer.
                    $staleStmt = $pdo->prepare('SELECT (last_activity_at IS NULL OR last_activity_at <= DATE_SUB(NOW(), INTERVAL 10 SECOND)) FROM tb_game_session WHERE id_session = ?');
                    $staleStmt->execute([$sessionId]);
                    if ((int) $staleStmt->fetchColumn() === 1) {
                        $pause = $pdo->prepare("UPDATE tb_game_session SET status_session = 'paused', sisa_detik = ?, deadline_at = NULL, paused_at = NOW() WHERE id_session = ?");
                        $pause->execute([$remaining, $sessionId]);
                        $status = 'paused';
                    }
                }

                if ($status === 'completed') throw new RuntimeException('Permainan kamu sudah selesai. Satu nomor HP hanya dapat bermain satu kali pada event ini.');
                if ($status === 'expired') throw new RuntimeException('Sesi permainan kamu sudah kedaluwarsa.');

                if ($status === 'paused') {
                    if ($remaining <= 0) $remaining = (int) $session['sisa_detik'];
                    if ($remaining <= 0) {
                        $expire = $pdo->prepare("UPDATE tb_game_session SET status_session = 'expired', sisa_detik = 0, deadline_at = NULL WHERE id_session = ?");
                        $expire->execute([$sessionId]);
                        $pdo->commit();
                        bhg_json(['success' => false, 'message' => 'Sisa waktu permainan kamu sudah habis.'], 422);
                    }
                    $resume = $pdo->prepare("UPDATE tb_game_session SET status_session = 'active', deadline_at = DATE_ADD(NOW(), INTERVAL sisa_detik SECOND), last_activity_at = NOW(), paused_at = NULL WHERE id_session = ? AND status_session = 'paused'");
                    $resume->execute([$sessionId]);
                } elseif ($status === 'active') {
                    $touch = $pdo->prepare('UPDATE tb_game_session SET last_activity_at = NOW() WHERE id_session = ?');
                    $touch->execute([$sessionId]);
                } else {
                    throw new RuntimeException('Status permainan tidak dikenali. Hubungi petugas.');
                }
            } else {
                $sessionId = 'SES' . strtoupper(bin2hex(random_bytes(15)));
                $create = $pdo->prepare("INSERT INTO tb_game_session (id_session, id_game, id_user, status_session, durasi_detik, sisa_detik, deadline_at, last_activity_at, started_at) VALUES (?, ?, ?, 'active', ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), NOW())");
                $create->execute([$sessionId, (string) $game['id_game'], $userId, $duration, $duration, $duration]);

                $pick = $pdo->prepare('SELECT id_brc FROM tb_barcode WHERE cabang = ? ORDER BY RAND() LIMIT ' . (int) $target);
                $pick->execute([(int) $branch]);
                $barcodeIds = $pick->fetchAll(PDO::FETCH_COLUMN);
                if (count($barcodeIds) !== $target) throw new RuntimeException('Barcode untuk permainan tidak berhasil disiapkan.');

                $insertAssignment = $pdo->prepare("INSERT INTO tb_session_barcode (id_session, id_brc, urutan, status_scan) VALUES (?, ?, ?, 'pending')");
                foreach ($barcodeIds as $index => $barcodeId) {
                    $insertAssignment->execute([$sessionId, (string) $barcodeId, $index + 1]);
                }
            }

            $pdo->commit();
            $_SESSION['bh_game_session_id'] = $sessionId;
            $_SESSION['bh_game_user_id'] = $userId;
            $_SESSION['bh_game_id'] = (string) $game['id_game'];
            $_SESSION['bh_game_branch'] = (int) $branch;
            bhg_json(['success' => true, 'message' => 'Permainan siap.', 'redirect' => bhg_base_url() . '/game.php']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $sessionId = bhg_current_session_id();
    if ($sessionId === '') bhg_json(['success' => false, 'message' => 'Sesi permainan tidak ditemukan. Silakan isi form kembali.'], 401);

    if ($action === 'state') {
        $stmt = $pdo->prepare(
            'SELECT s.id_session, s.status_session, s.durasi_detik, s.sisa_detik, s.deadline_at,
                    g.id_game, g.cabang, g.barcode_list
             FROM tb_game_session s
             INNER JOIN tb_game g ON g.id_game = s.id_game
             WHERE s.id_session = ? AND s.id_user = ? LIMIT 1'
        );
        $stmt->execute([$sessionId, (string) ($_SESSION['bh_game_user_id'] ?? '')]);
        $session = $stmt->fetch();
        if (!$session) bhg_json(['success' => false, 'message' => 'Sesi permainan tidak ditemukan.'], 404);

        $status = (string) $session['status_session'];
        $serverRemaining = (int) $session['sisa_detik'];
        if ($status === 'active') {
            $timeStmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), ?), 0)');
            $timeStmt->execute([$session['deadline_at']]);
            $serverRemaining = (int) $timeStmt->fetchColumn();

            $staleStmt = $pdo->prepare('SELECT (last_activity_at IS NULL OR last_activity_at <= DATE_SUB(NOW(), INTERVAL 10 SECOND)) FROM tb_game_session WHERE id_session = ?');
            $staleStmt->execute([$sessionId]);
            if ((int) $staleStmt->fetchColumn() === 1) {
                $newStatus = $serverRemaining > 0 ? 'paused' : 'expired';
                $pause = $pdo->prepare("UPDATE tb_game_session SET status_session = ?, sisa_detik = ?, deadline_at = NULL, paused_at = NOW() WHERE id_session = ? AND status_session = 'active'");
                $pause->execute([$newStatus, $serverRemaining, $sessionId]);
                $status = $newStatus;
            } elseif ($serverRemaining <= 0) {
                $expire = $pdo->prepare("UPDATE tb_game_session SET status_session = 'expired', sisa_detik = 0, deadline_at = NULL WHERE id_session = ?");
                $expire->execute([$sessionId]);
                $status = 'expired';
                $serverRemaining = 0;
            }
        }

        if ($status === 'paused' && $serverRemaining <= 0) {
            $expire = $pdo->prepare("UPDATE tb_game_session SET status_session = 'expired', sisa_detik = 0, deadline_at = NULL WHERE id_session = ?");
            $expire->execute([$sessionId]);
            $status = 'expired';
        }

        $progressStmt = $pdo->prepare(
            "SELECT COUNT(*) AS assigned_count,
                    SUM(CASE WHEN status_scan = 'scanned' THEN 1 ELSE 0 END) AS scanned_count
             FROM tb_session_barcode WHERE id_session = ?"
        );
        $progressStmt->execute([$sessionId]);
        $progress = $progressStmt->fetch() ?: ['assigned_count' => 0, 'scanned_count' => 0];
        $assigned = (int) $progress['assigned_count'];
        $scanned = (int) $progress['scanned_count'];

        if ($assigned > 0 && $scanned >= $assigned && $status !== 'completed') {
            $complete = $pdo->prepare("UPDATE tb_game_session SET status_session = 'completed', sisa_detik = GREATEST(TIMESTAMPDIFF(SECOND, NOW(), deadline_at), 0), deadline_at = NULL, completed_at = NOW() WHERE id_session = ?");
            $complete->execute([$sessionId]);
            $status = 'completed';
        }

        $hint = null;
        if ($status === 'active') {
            $hintStmt = $pdo->prepare(
                "SELECT b.hint
                 FROM tb_session_barcode sb
                 INNER JOIN tb_barcode b ON b.id_brc = sb.id_brc
                 WHERE sb.id_session = ? AND sb.status_scan = 'pending'
                 ORDER BY sb.urutan ASC LIMIT 1"
            );
            $hintStmt->execute([$sessionId]);
            $hint = $hintStmt->fetchColumn();
        }

        $remainingSeconds = 0;
        if ($status === 'active') {
            $timeStmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), ?), 0)');
            $timeStmt->execute([$session['deadline_at']]);
            $remainingSeconds = (int) $timeStmt->fetchColumn();
        } elseif (in_array($status, ['paused', 'expired'], true)) {
            $remainingSeconds = $status === 'expired' ? 0 : $serverRemaining;
        }

        bhg_json([
            'success' => true,
            'status' => $status,
            'progress' => $scanned,
            'target' => $assigned,
            'remaining' => $remainingSeconds,
            'hint' => is_string($hint) && trim($hint) !== '' ? $hint : ($status === 'active' ? 'Cari barcode berikutnya di area toko.' : null),
            'completed' => $status === 'completed',
        ]);
    }

    $input = bhg_read_input();
    bhg_csrf_check($input);

    if ($action === 'heartbeat') {
        $stmt = $pdo->prepare("UPDATE tb_game_session SET last_activity_at = NOW() WHERE id_session = ? AND id_user = ? AND status_session = 'active'");
        $stmt->execute([$sessionId, (string) ($_SESSION['bh_game_user_id'] ?? '')]);
        bhg_json(['success' => true]);
    }

    if ($action === 'pause') {
        $stmt = $pdo->prepare('SELECT status_session, deadline_at FROM tb_game_session WHERE id_session = ? AND id_user = ? LIMIT 1 FOR UPDATE');
        $pdo->beginTransaction();
        $stmt->execute([$sessionId, (string) ($_SESSION['bh_game_user_id'] ?? '')]);
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('Sesi permainan tidak ditemukan.');
        if ($row['status_session'] === 'active') {
            $timeStmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), ?), 0)');
            $timeStmt->execute([$row['deadline_at']]);
            $remaining = (int) $timeStmt->fetchColumn();
            $status = $remaining > 0 ? 'paused' : 'expired';
            $update = $pdo->prepare('UPDATE tb_game_session SET status_session = ?, sisa_detik = ?, deadline_at = NULL, paused_at = NOW() WHERE id_session = ?');
            $update->execute([$status, $remaining, $sessionId]);
        }
        $pdo->commit();
        bhg_json(['success' => true]);
    }

    if ($action === 'resume') {
        $stmt = $pdo->prepare("UPDATE tb_game_session SET status_session = 'active', deadline_at = DATE_ADD(NOW(), INTERVAL sisa_detik SECOND), last_activity_at = NOW(), paused_at = NULL WHERE id_session = ? AND id_user = ? AND status_session = 'paused' AND sisa_detik > 0");
        $stmt->execute([$sessionId, (string) ($_SESSION['bh_game_user_id'] ?? '')]);
        if ($stmt->rowCount() < 1) bhg_json(['success' => false, 'message' => 'Permainan tidak dapat dilanjutkan. Sisa waktu mungkin sudah habis.'], 409);
        bhg_json(['success' => true]);
    }

    if ($action === 'scan') {
        $serial = trim((string) ($input['serial'] ?? ''));
        if ($serial === '' || bhg_length($serial) > 65) bhg_json(['success' => false, 'message' => 'Data barcode tidak terbaca.'], 422);

        $pdo->beginTransaction();
        try {
            $sessionStmt = $pdo->prepare(
                "SELECT s.status_session, s.deadline_at, g.cabang
                 FROM tb_game_session s INNER JOIN tb_game g ON g.id_game = s.id_game
                 WHERE s.id_session = ? AND s.id_user = ? LIMIT 1 FOR UPDATE"
            );
            $sessionStmt->execute([$sessionId, (string) ($_SESSION['bh_game_user_id'] ?? '')]);
            $session = $sessionStmt->fetch();
            if (!$session || $session['status_session'] !== 'active') throw new RuntimeException('Permainan sedang dijeda atau sudah selesai.');

            $remainingStmt = $pdo->prepare('SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), ?), 0)');
            $remainingStmt->execute([$session['deadline_at']]);
            if ((int) $remainingStmt->fetchColumn() <= 0) {
                $expire = $pdo->prepare("UPDATE tb_game_session SET status_session = 'expired', sisa_detik = 0, deadline_at = NULL WHERE id_session = ?");
                $expire->execute([$sessionId]);
                throw new RuntimeException('Waktu permainan sudah habis.');
            }

            $barcodeStmt = $pdo->prepare(
                'SELECT b.id_brc, sb.status_scan, sb.urutan
                 FROM tb_barcode b
                 INNER JOIN tb_session_barcode sb ON sb.id_brc = b.id_brc AND sb.id_session = ?
                 WHERE b.cabang = ? AND b.barcode_serial = ?
                 LIMIT 1 FOR UPDATE'
            );
            $barcodeStmt->execute([$sessionId, (int) $session['cabang'], $serial]);
            $barcode = $barcodeStmt->fetch();
            if (!$barcode) throw new RuntimeException('Barcode tidak sesuai dengan petunjuk permainan kamu.');
            if ($barcode['status_scan'] === 'scanned') throw new RuntimeException('Barcode ini sudah pernah kamu pindai.');

            $nextStmt = $pdo->prepare("SELECT id_brc FROM tb_session_barcode WHERE id_session = ? AND status_scan = 'pending' ORDER BY urutan ASC LIMIT 1 FOR UPDATE");
            $nextStmt->execute([$sessionId]);
            $nextBarcodeId = (string) $nextStmt->fetchColumn();
            if ($nextBarcodeId === '' || $nextBarcodeId !== (string) $barcode['id_brc']) {
                throw new RuntimeException('Barcode belum sesuai dengan hint yang sedang aktif. Cari petunjuk berikutnya terlebih dahulu.');
            }

            $mark = $pdo->prepare("UPDATE tb_session_barcode SET status_scan = 'scanned', scanned_at = NOW() WHERE id_session = ? AND id_brc = ? AND status_scan = 'pending'");
            $mark->execute([$sessionId, (string) $barcode['id_brc']]);
            $touch = $pdo->prepare('UPDATE tb_game_session SET last_activity_at = NOW() WHERE id_session = ?');
            $touch->execute([$sessionId]);

            $countStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status_scan = 'scanned' THEN 1 ELSE 0 END) AS scanned FROM tb_session_barcode WHERE id_session = ?");
            $countStmt->execute([$sessionId]);
            $counts = $countStmt->fetch();
            $total = (int) $counts['total'];
            $scanned = (int) $counts['scanned'];
            $completed = $total > 0 && $scanned >= $total;
            if ($completed) {
                $finish = $pdo->prepare("UPDATE tb_game_session SET status_session = 'completed', sisa_detik = GREATEST(TIMESTAMPDIFF(SECOND, NOW(), deadline_at), 0), deadline_at = NULL, completed_at = NOW() WHERE id_session = ?");
                $finish->execute([$sessionId]);
            }

            $pdo->commit();
            bhg_json([
                'success' => true,
                'message' => $completed ? 'Selamat! Semua barcode berhasil dikumpulkan.' : 'Barcode berhasil ditemukan!',
                'progress' => $scanned,
                'target' => $total,
                'completed' => $completed,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    bhg_json(['success' => false, 'message' => 'Aksi tidak dikenal.'], 404);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[Barcode Hunting Session] ' . $e->getMessage());
    $status = $e instanceof RuntimeException ? 422 : 500;
    bhg_json(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan server. Periksa log PHP.'], $status);
}
