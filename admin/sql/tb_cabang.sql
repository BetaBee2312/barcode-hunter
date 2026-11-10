-- Master cabang Barcode Hunting
-- Kode cabang dibuat INT agar cocok dengan tb_barcode.cabang dan tb_game.cabang.
-- Jalankan sekali di database Barcode Hunting jika tb_cabang belum tersedia.

CREATE TABLE IF NOT EXISTS tb_cabang (
    kode_cabang INT NOT NULL,
    nama_cabang VARCHAR(150) NOT NULL,
    singkatan VARCHAR(50) DEFAULT NULL,
    dc_support VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kode_cabang),
    KEY idx_tb_cabang_nama (nama_cabang)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
