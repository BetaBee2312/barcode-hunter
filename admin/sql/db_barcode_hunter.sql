-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 10 Nov 2026 pada 09.11
-- Versi server: 8.0.30
-- Versi PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_barcode_hunter`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_barcode`
--

CREATE TABLE `tb_barcode` (
  `id_brc` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode_serial` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang` int NOT NULL,
  `number_barcode` int NOT NULL,
  `hint` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_barcode`
--

INSERT INTO `tb_barcode` (`id_brc`, `barcode_serial`, `cabang`, `number_barcode`, `hint`) VALUES
('BRC-34BF1171EAB9B33AD26B38CB6AC78FE8ACAA4FB870074D81DA5C84B5F5173', '963687477', 23212, 4, 'Biasanya Aku Diletakkan di Depan pintu Masuk'),
('BRC-8AF1BD7BEE0EA9DAC017AFBFB24202DCB5201BFDE2DD8D7A1B02D81EEE0D3', '03636404', 23212, 6, 'Minum'),
('BRC-96B548D09304A8494796A4E9B6225C89FD16792F39494D6DF705F0B459F18', '989317372', 23212, 3, 'Biasanya Aku Digantung Di ruang tamu'),
('BRC-C664CA3F2453FEB16235D05BF3D55849E3757B013466E5C2504DFBFECA44A', '1231231231', 23212, 5, 'Aku dipakai untuk menulis'),
('BRC-DFA6F96CC6E43D1807823BE0F48C7F9D9FBBF1811A2C0B74D82A38032CE38', '834837412', 23212, 2, 'Bentukku Bulat Biasanya Aku dimakan Bayi'),
('BRC-E21FEF90F1EFE8259C27D7DEFC0EC71014A99E942A82A86DD09E38D755992', '785546744', 23212, 1, 'Aku Sebuah Minuman Kaleng dan Rasanya Segar Siapakah Aku?');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_cabang`
--

CREATE TABLE `tb_cabang` (
  `kode_cabang` int NOT NULL,
  `nama_cabang` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `singkatan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dc_support` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_cabang`
--

INSERT INTO `tb_cabang` (`kode_cabang`, `nama_cabang`, `singkatan`, `dc_support`, `created_at`, `updated_at`) VALUES
(23212, 'Yomart Cibaduyut', 'MHO', 'D53', '2026-11-10 11:07:39', '2026-11-10 11:07:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_game`
--

CREATE TABLE `tb_game` (
  `id_game` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cabang` int NOT NULL,
  `id_user` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode_list` int NOT NULL,
  `status_game` enum('active','paused','completed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `durasi_detik` int UNSIGNED NOT NULL DEFAULT '600',
  `sisa_detik` int UNSIGNED NOT NULL DEFAULT '600',
  `deadline_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `paused_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_game`
--

INSERT INTO `tb_game` (`id_game`, `cabang`, `id_user`, `barcode_list`, `status_game`, `durasi_detik`, `sisa_detik`, `deadline_at`, `last_activity_at`, `started_at`, `paused_at`, `completed_at`) VALUES
('GAME-E9E58476512C8333E440427687A423D4EEE1774CE72F49801DFEF9744B52', 23212, NULL, 6, 'active', 600, 600, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_game_barcode`
--

CREATE TABLE `tb_game_barcode` (
  `id_game` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_brc` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int UNSIGNED NOT NULL,
  `status_scan` enum('pending','scanned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_game_session`
--

CREATE TABLE `tb_game_session` (
  `id_session` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_game` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_user` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_session` enum('active','paused','completed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `durasi_detik` int UNSIGNED NOT NULL DEFAULT '600',
  `sisa_detik` int UNSIGNED NOT NULL DEFAULT '600',
  `deadline_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paused_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_game_session`
--

INSERT INTO `tb_game_session` (`id_session`, `id_game`, `id_user`, `status_session`, `durasi_detik`, `sisa_detik`, `deadline_at`, `last_activity_at`, `started_at`, `paused_at`, `completed_at`, `created_at`, `updated_at`) VALUES
('SES414361DF2D6E66A9825A13F1A91D18', 'GAME-E9E58476512C8333E440427687A423D4EEE1774CE72F49801DFEF9744B52', 'USRD80DEF2C5915', 'paused', 600, 391, NULL, '2026-11-10 16:06:38', '2026-11-10 16:00:29', '2026-11-10 16:06:42', NULL, '2026-11-10 16:00:29', '2026-11-10 16:06:42'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'GAME-E9E58476512C8333E440427687A423D4EEE1774CE72F49801DFEF9744B52', 'USRD6F73B9B0A80', 'paused', 600, 349, NULL, '2026-11-10 15:59:13', '2026-11-10 15:54:51', '2026-11-10 15:59:14', NULL, '2026-11-10 15:54:51', '2026-11-10 15:59:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_game_setting`
--

CREATE TABLE `tb_game_setting` (
  `id_game` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_game` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Barcode Hunting',
  `durasi_detik` int UNSIGNED NOT NULL DEFAULT '600',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_game_setting`
--

INSERT INTO `tb_game_setting` (`id_game`, `nama_game`, `durasi_detik`, `aktif`, `created_at`, `updated_at`) VALUES
('GAME-E9E58476512C8333E440427687A423D4EEE1774CE72F49801DFEF9744B52', 'Barcode Hunting', 600, 1, '2026-11-10 15:12:52', '2026-11-10 15:12:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_session_barcode`
--

CREATE TABLE `tb_session_barcode` (
  `id_session` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_brc` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urutan` int UNSIGNED NOT NULL,
  `status_scan` enum('pending','scanned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scanned_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_session_barcode`
--

INSERT INTO `tb_session_barcode` (`id_session`, `id_brc`, `urutan`, `status_scan`, `scanned_at`, `created_at`) VALUES
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-34BF1171EAB9B33AD26B38CB6AC78FE8ACAA4FB870074D81DA5C84B5F5173', 3, 'scanned', '2026-11-10 16:01:19', '2026-11-10 16:00:29'),
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-8AF1BD7BEE0EA9DAC017AFBFB24202DCB5201BFDE2DD8D7A1B02D81EEE0D3', 5, 'scanned', '2026-11-10 16:01:33', '2026-11-10 16:00:29'),
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-96B548D09304A8494796A4E9B6225C89FD16792F39494D6DF705F0B459F18', 6, 'pending', NULL, '2026-11-10 16:00:29'),
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-C664CA3F2453FEB16235D05BF3D55849E3757B013466E5C2504DFBFECA44A', 4, 'scanned', '2026-11-10 16:01:28', '2026-11-10 16:00:29'),
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-DFA6F96CC6E43D1807823BE0F48C7F9D9FBBF1811A2C0B74D82A38032CE38', 2, 'scanned', '2026-11-10 16:01:07', '2026-11-10 16:00:29'),
('SES414361DF2D6E66A9825A13F1A91D18', 'BRC-E21FEF90F1EFE8259C27D7DEFC0EC71014A99E942A82A86DD09E38D755992', 1, 'scanned', '2026-11-10 16:00:55', '2026-11-10 16:00:29'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'BRC-34BF1171EAB9B33AD26B38CB6AC78FE8ACAA4FB870074D81DA5C84B5F5173', 2, 'scanned', '2026-11-10 15:56:03', '2026-11-10 15:54:51'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'BRC-96B548D09304A8494796A4E9B6225C89FD16792F39494D6DF705F0B459F18', 4, 'pending', NULL, '2026-11-10 15:54:51'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'BRC-C664CA3F2453FEB16235D05BF3D55849E3757B013466E5C2504DFBFECA44A', 5, 'pending', NULL, '2026-11-10 15:54:51'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'BRC-DFA6F96CC6E43D1807823BE0F48C7F9D9FBBF1811A2C0B74D82A38032CE38', 1, 'scanned', '2026-11-10 15:55:39', '2026-11-10 15:54:51'),
('SESD0F032B773DCC599FD5B0FF7158E9B', 'BRC-E21FEF90F1EFE8259C27D7DEFC0EC71014A99E942A82A86DD09E38D755992', 3, 'pending', NULL, '2026-11-10 15:54:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_user`
--

CREATE TABLE `tb_user` (
  `id_user` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tb_user`
--

INSERT INTO `tb_user` (`id_user`, `nama`, `no_hp`, `created_at`) VALUES
('USRD6F73B9B0A80', 'Dal', '08569393658', '2026-11-10 15:54:51'),
('USRD80DEF2C5915', 'Andika Pratama', '085136297565', '2026-11-10 16:00:29');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `tb_barcode`
--
ALTER TABLE `tb_barcode`
  ADD PRIMARY KEY (`id_brc`);

--
-- Indeks untuk tabel `tb_cabang`
--
ALTER TABLE `tb_cabang`
  ADD PRIMARY KEY (`kode_cabang`),
  ADD KEY `idx_tb_cabang_nama` (`nama_cabang`);

--
-- Indeks untuk tabel `tb_game`
--
ALTER TABLE `tb_game`
  ADD PRIMARY KEY (`id_game`);

--
-- Indeks untuk tabel `tb_game_barcode`
--
ALTER TABLE `tb_game_barcode`
  ADD PRIMARY KEY (`id_game`,`id_brc`),
  ADD UNIQUE KEY `uq_game_urutan` (`id_game`,`urutan`),
  ADD KEY `fk_gb_barcode` (`id_brc`);

--
-- Indeks untuk tabel `tb_game_session`
--
ALTER TABLE `tb_game_session`
  ADD PRIMARY KEY (`id_session`),
  ADD UNIQUE KEY `uq_game_session_user` (`id_game`,`id_user`),
  ADD KEY `idx_game_session_status` (`status_session`),
  ADD KEY `idx_game_session_user` (`id_user`);

--
-- Indeks untuk tabel `tb_game_setting`
--
ALTER TABLE `tb_game_setting`
  ADD PRIMARY KEY (`id_game`);

--
-- Indeks untuk tabel `tb_session_barcode`
--
ALTER TABLE `tb_session_barcode`
  ADD PRIMARY KEY (`id_session`,`id_brc`),
  ADD UNIQUE KEY `uq_session_barcode_order` (`id_session`,`urutan`),
  ADD KEY `idx_session_barcode_brc` (`id_brc`);

--
-- Indeks untuk tabel `tb_user`
--
ALTER TABLE `tb_user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uq_no_hp` (`no_hp`);

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `tb_game_barcode`
--
ALTER TABLE `tb_game_barcode`
  ADD CONSTRAINT `fk_gb_barcode` FOREIGN KEY (`id_brc`) REFERENCES `tb_barcode` (`id_brc`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_gb_game` FOREIGN KEY (`id_game`) REFERENCES `tb_game` (`id_game`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tb_game_session`
--
ALTER TABLE `tb_game_session`
  ADD CONSTRAINT `fk_game_session_game` FOREIGN KEY (`id_game`) REFERENCES `tb_game` (`id_game`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_game_session_user` FOREIGN KEY (`id_user`) REFERENCES `tb_user` (`id_user`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tb_game_setting`
--
ALTER TABLE `tb_game_setting`
  ADD CONSTRAINT `fk_game_setting_game` FOREIGN KEY (`id_game`) REFERENCES `tb_game` (`id_game`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tb_session_barcode`
--
ALTER TABLE `tb_session_barcode`
  ADD CONSTRAINT `fk_session_barcode_master` FOREIGN KEY (`id_brc`) REFERENCES `tb_barcode` (`id_brc`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_session_barcode_session` FOREIGN KEY (`id_session`) REFERENCES `tb_game_session` (`id_session`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
