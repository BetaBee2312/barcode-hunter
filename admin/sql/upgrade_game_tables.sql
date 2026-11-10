-- =============================================================
-- BARCODE HUNTING: UPGRADE RELASI SESI-BARCODE + TIMER
-- Sesuai struktur tabel yang sudah dibuat:
-- tb_game(id_game VARCHAR(65), cabang INT, id_user VARCHAR(15), barcode_list INT)
-- tb_barcode(id_brc VARCHAR(65), barcode_serial VARCHAR(65), cabang INT, number_barcode INT)
-- Jalankan pada database Barcode Hunting yang sedang digunakan.
-- =============================================================

-- 1) Jika tb_user belum ada, buat dengan struktur yang disepakati.
CREATE TABLE IF NOT EXISTS tb_user (
    id_user VARCHAR(15) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_user),
    UNIQUE KEY uq_tb_user_no_hp (no_hp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Satu game menyimpan daftar barcode acak milik pemain.
CREATE TABLE IF NOT EXISTS tb_game_barcode (
    id_game VARCHAR(65) NOT NULL,
    id_brc VARCHAR(65) NOT NULL,
    urutan INT UNSIGNED NOT NULL,
    status_scan ENUM('pending', 'scanned') NOT NULL DEFAULT 'pending',
    scanned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_game, id_brc),
    UNIQUE KEY uq_game_urutan (id_game, urutan),
    KEY idx_game_barcode_brc (id_brc),
    CONSTRAINT fk_game_barcode_game FOREIGN KEY (id_game)
        REFERENCES tb_game (id_game) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_game_barcode_barcode FOREIGN KEY (id_brc)
        REFERENCES tb_barcode (id_brc) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Tambahkan kolom timer hanya jika belum ada.
-- Script ini aman dijalankan ulang pada MySQL 5.7+/MariaDB yang mendukung PREPARE.
SET @db_name = DATABASE();

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'status_game';
SET @ddl = IF(@col_exists = 0,
    "ALTER TABLE tb_game ADD COLUMN status_game ENUM('active','paused','completed','expired') NOT NULL DEFAULT 'paused'",
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'durasi_detik';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN durasi_detik INT UNSIGNED NOT NULL DEFAULT 600',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'sisa_detik';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN sisa_detik INT UNSIGNED NOT NULL DEFAULT 600',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'deadline_at';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN deadline_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'last_activity_at';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN last_activity_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'started_at';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN started_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'paused_at';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN paused_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns
WHERE table_schema = @db_name AND table_name = 'tb_game' AND column_name = 'completed_at';
SET @ddl = IF(@col_exists = 0,
    'ALTER TABLE tb_game ADD COLUMN completed_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Selesai. Periksa struktur tb_game_barcode dan kolom timer di phpMyAdmin.
