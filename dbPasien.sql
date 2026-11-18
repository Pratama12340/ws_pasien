-- ============================================================================
-- DATABASE PASIEN (dbPasien) - SESUAI PAPAN TULIS PRAKTIKUM SOA
-- 1. Tabel Poliklinik
-- 2. Tabel Pasien
-- 3. Tabel Asuransi
-- 4. Tabel Pengguna (RBAC)
-- 5. Tabel Dokter
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `dbPasien` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dbPasien`;

-- --------------------------------------------------------
-- 1. Tabel `poliklinik`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `poliklinik` (
  `id_poliklinik` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_poliklinik` varchar(20) NOT NULL,
  `nama_poliklinik` varchar(150) NOT NULL,
  `lokasi_ruangan` varchar(100) DEFAULT 'Gedung Utama Lt. 1',
  `jam_pelayanan` varchar(100) DEFAULT '08:00 - 16:00 WIB',
  `status_poli` enum('Buka','Tutup') NOT NULL DEFAULT 'Buka',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_poliklinik`),
  UNIQUE KEY `uk_kode_poli` (`kode_poliklinik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `poliklinik` (`id_poliklinik`, `kode_poliklinik`, `nama_poliklinik`, `lokasi_ruangan`, `jam_pelayanan`, `status_poli`) VALUES
(1, 'POLI-UMUM', 'Poliklinik Umum', 'Gedung A - Lantai 1 (R.101)', '08:00 - 20:00 WIB', 'Buka'),
(2, 'POLI-GIGI', 'Poliklinik Gigi & Mulut', 'Gedung A - Lantai 1 (R.102)', '08:00 - 16:00 WIB', 'Buka'),
(3, 'POLI-ANAK', 'Poliklinik Kesehatan Anak', 'Gedung B - Lantai 2 (R.201)', '08:00 - 15:00 WIB', 'Buka'),
(4, 'POLI-DALAM', 'Poliklinik Penyakit Dalam', 'Gedung B - Lantai 2 (R.204)', '09:00 - 16:00 WIB', 'Buka'),
(5, 'POLI-MATA', 'Poliklinik Mata', 'Gedung C - Lantai 1 (R.105)', '08:00 - 15:00 WIB', 'Buka')
ON DUPLICATE KEY UPDATE `nama_poliklinik` = VALUES(`nama_poliklinik`);

-- --------------------------------------------------------
-- 2. Tabel `asuransi`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asuransi` (
  `id_asuransi` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_asuransi` varchar(30) NOT NULL,
  `nama_asuransi` varchar(150) NOT NULL,
  `jenis_asuransi` enum('Pemerintah (BPJS)','Swasta','Perusahaan','Umum / Mandiri') NOT NULL DEFAULT 'Pemerintah (BPJS)',
  `kelas_tanggungan` varchar(50) DEFAULT 'Semua Kelas',
  `kontak_darurat` varchar(50) DEFAULT NULL,
  `status_asuransi` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_asuransi`),
  UNIQUE KEY `uk_kode_asuransi` (`kode_asuransi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `asuransi` (`id_asuransi`, `kode_asuransi`, `nama_asuransi`, `jenis_asuransi`, `kelas_tanggungan`, `kontak_darurat`, `status_asuransi`) VALUES
(1, 'BPJS-KES', 'BPJS Kesehatan', 'Pemerintah (BPJS)', 'Kelas 1 / 2 / 3', '165', 'Aktif'),
(2, 'INHEALTH', 'Mandiri Inhealth', 'Swasta', 'Gold / Platinum', '14078', 'Aktif'),
(3, 'PRUDENTIAL', 'Prudential PRUHospital', 'Swasta', 'VIP / Kelas 1', '1500085', 'Aktif'),
(4, 'UMUM', 'Pasien Umum (Mandiri)', 'Umum / Mandiri', 'Sesuai Layanan', '-', 'Aktif')
ON DUPLICATE KEY UPDATE `nama_asuransi` = VALUES(`nama_asuransi`);

-- --------------------------------------------------------
-- 3. Tabel `dokter`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dokter` (
  `id_dokter` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nip_sip` varchar(40) NOT NULL,
  `nama_dokter` varchar(150) NOT NULL,
  `spesialisasi` varchar(100) NOT NULL,
  `id_poliklinik` int(11) UNSIGNED NOT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `jadwal_praktik` varchar(120) DEFAULT 'Senin - Jumat (08:00 - 14:00)',
  `status_dokter` enum('Aktif','Cuti','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_dokter`),
  UNIQUE KEY `uk_nip_sip` (`nip_sip`),
  KEY `idx_dokter_poli` (`id_poliklinik`),
  CONSTRAINT `fk_dokter_poliklinik` FOREIGN KEY (`id_poliklinik`) REFERENCES `poliklinik` (`id_poliklinik`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dokter` (`id_dokter`, `nip_sip`, `nama_dokter`, `spesialisasi`, `id_poliklinik`, `no_hp`, `jadwal_praktik`, `status_dokter`) VALUES
(1, 'SIP.19850101.001', 'dr. Hendra Wijaya, Sp.PD', 'Spesialis Penyakit Dalam', 4, '081271001122', 'Senin - Kamis (09:00 - 15:00)', 'Aktif'),
(2, 'SIP.19890412.002', 'dr. Rina Kartika, Sp.A', 'Spesialis Anak', 3, '081271003344', 'Senin - Jumat (08:00 - 14:00)', 'Aktif'),
(3, 'SIP.19910820.003', 'drg. Budi Santoso', 'Dokter Gigi', 2, '081366554433', 'Senin - Sabtu (08:00 - 15:00)', 'Aktif'),
(4, 'SIP.19931105.004', 'dr. Siti Rahmawati', 'Dokter Umum', 1, '081377889900', 'Setiap Hari (08:00 - 20:00)', 'Aktif'),
(5, 'SIP.19870615.005', 'dr. Ahmad Fauzan, Sp.M', 'Spesialis Mata', 5, '081299887766', 'Selasa - Jumat (09:00 - 15:00)', 'Aktif')
ON DUPLICATE KEY UPDATE `nama_dokter` = VALUES(`nama_dokter`);

-- --------------------------------------------------------
-- 4. Tabel `pengguna` (Login & RBAC)
-- Password default semua akun: `admin123`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama_lengkap` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('ADMIN','PETUGAS','DOKTER','KEPALA_RS') NOT NULL DEFAULT 'PETUGAS',
  `status_aktif` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pengguna`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pengguna` (`id_pengguna`, `username`, `password_hash`, `nama_lengkap`, `email`, `role`, `status_aktif`) VALUES
(1, 'admin', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', 'Administrator SIMRS', 'admin@rs-umb.ac.id', 'ADMIN', 'Aktif'),
(2, 'petugas', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', 'Petugas Pendaftaran & Rekam Medis', 'admisi@rs-umb.ac.id', 'PETUGAS', 'Aktif'),
(3, 'dokter', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', 'dr. Hendra Wijaya, Sp.PD', 'hendra.sppd@rs-umb.ac.id', 'DOKTER', 'Aktif'),
(4, 'kepala', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', 'Direktur Rumah Sakit', 'direktur@rs-umb.ac.id', 'KEPALA_RS', 'Aktif')
ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`), `password_hash` = VALUES(`password_hash`);

-- --------------------------------------------------------
-- 5. Tabel `pasien`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pasien` (
  `id_pasien` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `no_rm` varchar(30) NOT NULL COMMENT 'Nomor Rekam Medis',
  `nik` varchar(20) NOT NULL,
  `nama_pasien` varchar(150) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL DEFAULT 'L',
  `tanggal_lahir` date NOT NULL,
  `golongan_darah` enum('A','B','AB','O','-') NOT NULL DEFAULT 'O',
  `alamat` text DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `id_poliklinik` int(11) UNSIGNED NOT NULL,
  `id_dokter` int(11) UNSIGNED DEFAULT NULL,
  `id_asuransi` int(11) UNSIGNED NOT NULL,
  `no_kartu_asuransi` varchar(50) DEFAULT NULL,
  `keluhan` text DEFAULT NULL,
  `status_kunjungan` enum('Menunggu','Diperiksa','Selesai','Rawat Inap') NOT NULL DEFAULT 'Menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pasien`),
  UNIQUE KEY `uk_no_rm` (`no_rm`),
  KEY `idx_pasien_poli` (`id_poliklinik`),
  KEY `idx_pasien_dokter` (`id_dokter`),
  KEY `idx_pasien_asuransi` (`id_asuransi`),
  CONSTRAINT `fk_pasien_poli` FOREIGN KEY (`id_poliklinik`) REFERENCES `poliklinik` (`id_poliklinik`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pasien_dokter` FOREIGN KEY (`id_dokter`) REFERENCES `dokter` (`id_dokter`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pasien_asuransi` FOREIGN KEY (`id_asuransi`) REFERENCES `asuransi` (`id_asuransi`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pasien` (`id_pasien`, `no_rm`, `nik`, `nama_pasien`, `jenis_kelamin`, `tanggal_lahir`, `golongan_darah`, `alamat`, `no_hp`, `id_poliklinik`, `id_dokter`, `id_asuransi`, `no_kartu_asuransi`, `keluhan`, `status_kunjungan`) VALUES
(1, 'RM-2026-0001', '1771011505900001', 'Ahmad Pratama Putra', 'L', '1990-05-15', 'O', 'Jl. Suprapto No. 45, Kota Bengkulu', '081278901234', 4, 1, 1, '0001283948571', 'Demam tinggi 3 hari disertai nyeri ulu hati', 'Diperiksa'),
(2, 'RM-2026-0002', '1771022008980002', 'Siti Aisyah Putri', 'P', '1998-08-20', 'A', 'Jl. Bali No. 12, Kampung Bali, Bengkulu', '081367892345', 2, 3, 2, 'MIH-99283741', 'Pemeriksaan dan pembersihan karang gigi rutin', 'Menunggu'),
(3, 'RM-2026-0003', '1771031012180003', 'Muhammad Rizky Pratama', 'L', '2018-12-10', 'B', 'Jl. S. Parman No. 88, Padang Jati, Bengkulu', '085267812390', 3, 2, 1, '0001928374655', 'Batuk pilek dan pemeriksaan tumbuh kembang anak', 'Selesai'),
(4, 'RM-2026-0004', '1771040203820004', 'Dewi Lestari', 'P', '1982-03-02', 'AB', 'Jl. Hibrida Raya No. 19, Sidomulyo, Bengkulu', '08117309876', 5, 5, 3, 'PRU-77123980', 'Pandangan kabur saat membaca jarak dekat', 'Menunggu')
ON DUPLICATE KEY UPDATE `nama_pasien` = VALUES(`nama_pasien`);

COMMIT;
