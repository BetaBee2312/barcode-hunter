-- =============================================================
-- BARCODE HUNTING V2: GAME CONFIG PER CABANG + SESI PESERTA
-- SQL ini sengaja tidak memakai PREPARE/EXECUTE agar lebih ramah phpMyAdmin.
-- Jalankan satu kali pada database yang berisi tb_barcode dan tb_game.
-- =============================================================

-- 1. Konsumen dibuat otomatis oleh endpoint registrasi game.
CREATE TABLE IF NOT EXISTS tb_user (
    id_user VARCHAR(15) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user),
    UNIQUE KEY uq_tb_user_no_hp (no_hp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. tb_game adalah konfigurasi permainan per cabang, bukan sesi milik seorang konsumen.
-- id_user lama dibuat nullable dan tidak dipakai untuk registrasi pemain.
ALTER TABLE tb_game MODIFY COLUMN id_user VARCHAR(15) NULL;

-- 3. Pengaturan tambahan untuk setiap konfigurasi game.
CREATE TABLE IF NOT EXISTS tb_game_setting (
    id_game VARCHAR(65) NOT NULL,
    nama_game VARCHAR(100) NOT NULL DEFAULT 'Barcode Hunting',
    durasi_detik INT UNSIGNED NOT NULL DEFAULT 600,
    aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_game),
    CONSTRAINT fk_game_setting_game FOREIGN KEY (id_game)
        REFERENCES tb_game (id_game) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Satu baris adalah satu sesi seorang konsumen pada satu konfigurasi game.
CREATE TABLE IF NOT EXISTS tb_game_session (
    id_session VARCHAR(65) NOT NULL,
    id_game VARCHAR(65) NOT NULL,
    id_user VARCHAR(15) NOT NULL,
    status_session ENUM('active','paused','completed','expired') NOT NULL DEFAULT 'active',
    durasi_detik INT UNSIGNED NOT NULL DEFAULT 600,
    sisa_detik INT UNSIGNED NOT NULL DEFAULT 600,
    deadline_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paused_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_session),
    UNIQUE KEY uq_game_session_user (id_game, id_user),
    KEY idx_game_session_status (status_session),
    KEY idx_game_session_user (id_user),
    CONSTRAINT fk_game_session_game FOREIGN KEY (id_game)
        REFERENCES tb_game (id_game) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_game_session_user FOREIGN KEY (id_user)
        REFERENCES tb_user (id_user) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Barcode yang diacak untuk setiap pemain. Daftar ini tidak berubah saat resume.
CREATE TABLE IF NOT EXISTS tb_session_barcode (
    id_session VARCHAR(65) NOT NULL,
    id_brc VARCHAR(65) NOT NULL,
    urutan INT UNSIGNED NOT NULL,
    status_scan ENUM('pending','scanned') NOT NULL DEFAULT 'pending',
    scanned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_session, id_brc),
    UNIQUE KEY uq_session_barcode_order (id_session, urutan),
    KEY idx_session_barcode_brc (id_brc),
    CONSTRAINT fk_session_barcode_session FOREIGN KEY (id_session)
        REFERENCES tb_game_session (id_session) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_session_barcode_master FOREIGN KEY (id_brc)
        REFERENCES tb_barcode (id_brc) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Selesai. Tidak ada konsumen yang perlu dimasukkan dari admin.
-- tb_user akan terisi otomatis saat konsumen mengirim form registrasi di halaman game.
