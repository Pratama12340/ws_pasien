USE db_pasien;

-- Tambahkan role 'pasien' ke tabel roles agar genap 6 role di tabel roles (selain akun_pasien)
INSERT IGNORE INTO roles (id_role, nama_role, deskripsi) VALUES
(1, 'super_admin', 'Akses penuh pengelolaan sistem'),
(2, 'admin', 'Mengelola data operasional klinik'),
(3, 'pendaftaran', 'Mengelola pasien dan pendaftaran kunjungan'),
(4, 'dokter', 'Mengakses kunjungan dan mengelola rekam medis'),
(5, 'kasir', 'Mengelola tagihan dan pembayaran'),
(6, 'pasien', 'Akses portal pasien & pendaftaran mandiri');

-- =========================================================
-- SEED 6 AKUN PENGGUNA SESUAI 6 ROLE DATABASE (Password: admin123)
-- Hash bcrypt untuk 'admin123': $2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6
-- =========================================================
INSERT INTO pengguna (id_pengguna, nama_lengkap, username, email, password_hash, no_hp, status) VALUES
(1, 'Super Administrator SIMRS', 'superadmin', 'superadmin@klinik-umb.ac.id', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '08117300001', 'aktif'),
(2, 'Admin Operasional Klinik', 'admin', 'admin@klinik-umb.ac.id', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '08117300002', 'aktif'),
(3, 'Petugas Loket Pendaftaran', 'pendaftaran', 'pendaftaran@klinik-umb.ac.id', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '08117300003', 'aktif'),
(4, 'dr. Hendra Wijaya, Sp.PD', 'dokter', 'hendra.sppd@klinik-umb.ac.id', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '08117300004', 'aktif'),
(5, 'Petugas Kasir & Billing', 'kasir', 'kasir@klinik-umb.ac.id', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '08117300005', 'aktif'),
(6, 'Ahmad Pratama (Pasien)', 'pasien', 'ahmad.pratama@gmail.com', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', '081278901234', 'aktif')
ON DUPLICATE KEY UPDATE
  nama_lengkap = VALUES(nama_lengkap),
  password_hash = VALUES(password_hash),
  status = VALUES(status);

INSERT IGNORE INTO pengguna_roles (id_pengguna, id_role) VALUES
(1, 1), -- superadmin -> super_admin
(2, 2), -- admin      -> admin
(3, 3), -- pendaftaran -> pendaftaran
(4, 4), -- dokter     -> dokter
(5, 5), -- kasir      -> kasir
(6, 6); -- pasien     -> pasien

-- =========================================================
-- SEED MASTER POLI (poliklinik)
-- =========================================================
INSERT INTO poli (id_poli, kode_poli, nama_poli, keterangan, status) VALUES
(1, 'POLI-UMUM', 'Poliklinik Umum', 'Pelayanan pemeriksaan medis dasar dan konsultasi kesehatan umum (Gedung A Lt. 1)', 'aktif'),
(2, 'POLI-GIGI', 'Poliklinik Gigi & Mulut', 'Pelayanan kesehatan gigi, mulut, scaling, dan konservasi gigi (Gedung A Lt. 1)', 'aktif'),
(3, 'POLI-ANAK', 'Poliklinik Kesehatan Anak', 'Pelayanan imunisasi, tumbuh kembang, dan kesehatan anak (Gedung B Lt. 2)', 'aktif'),
(4, 'POLI-DALAM', 'Poliklinik Penyakit Dalam', 'Pelayanan spesialis penyakit dalam dan metabolik (Gedung B Lt. 2)', 'aktif'),
(5, 'POLI-MATA', 'Poliklinik Mata', 'Pelayanan pemeriksaan refraksi dan penyakit mata (Gedung C Lt. 1)', 'aktif')
ON DUPLICATE KEY UPDATE nama_poli = VALUES(nama_poli), keterangan = VALUES(keterangan);

-- =========================================================
-- SEED MASTER ASURANSI
-- =========================================================
INSERT INTO asuransi (id_asuransi, nama_asuransi, jenis_asuransi, nomor_kontak, alamat, status) VALUES
(1, 'BPJS Kesehatan', 'BPJS', '165 / 0736-21123', 'Jl. Pembangunan No. 14, Kota Bengkulu', 'aktif'),
(2, 'Mandiri Inhealth', 'swasta', '14078', 'Jl. S. Parman No. 55, Kota Bengkulu', 'aktif'),
(3, 'Prudential PRUHospital', 'swasta', '1500085', 'Jl. Sudirman Kav. 10, Jakarta / Bengkulu', 'aktif'),
(4, 'Pasien Umum / Mandiri', 'lainnya', '-', 'Pembayaran Tunai / Mandiri di Kasir Klinik', 'aktif')
ON DUPLICATE KEY UPDATE nomor_kontak = VALUES(nomor_kontak), status = VALUES(status);

-- =========================================================
-- SEED MASTER DOKTER
-- =========================================================
INSERT INTO dokter (id_dokter, id_pengguna, id_poli, kode_dokter, nama_dokter, no_sip, spesialisasi, no_hp, alamat, status) VALUES
(1, 4, 4, 'DOK-001', 'dr. Hendra Wijaya, Sp.PD', 'SIP.19850101.001', 'Spesialis Penyakit Dalam', '08117300004', 'Jl. Indraprasta No. 10, Bengkulu', 'aktif'),
(2, NULL, 3, 'DOK-002', 'dr. Rina Kartika, Sp.A', 'SIP.19890412.002', 'Spesialis Kesehatan Anak', '081271003344', 'Jl. Mahoni No. 22, Bengkulu', 'aktif'),
(3, NULL, 2, 'DOK-003', 'drg. Budi Santoso', 'SIP.19910820.003', 'Dokter Gigi', '081366554433', 'Jl. Cimanuk No. 8, Bengkulu', 'aktif'),
(4, NULL, 1, 'DOK-004', 'dr. Siti Rahmawati', 'SIP.19931105.004', 'Dokter Umum', '081377889900', 'Jl. Bali No. 40, Bengkulu', 'aktif'),
(5, NULL, 5, 'DOK-005', 'dr. Ahmad Fauzan, Sp.M', 'SIP.19870615.005', 'Spesialis Mata', '081299887766', 'Jl. Rafflesia No. 15, Bengkulu', 'aktif')
ON DUPLICATE KEY UPDATE nama_dokter = VALUES(nama_dokter), spesialisasi = VALUES(spesialisasi);

-- =========================================================
-- SEED PASIEN & AKUN PASIEN
-- =========================================================
INSERT INTO pasien (nik, id_asuransi, nomor_peserta_asuransi, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, golongan_darah, no_hp, email, alamat, kelurahan, kecamatan, kota_kabupaten, provinsi, kode_pos, nama_kontak_darurat, no_kontak_darurat, hubungan_kontak_darurat) VALUES
('1771011505900001', 1, '0001283948571', 'Ahmad Pratama Putra', 'Bengkulu', '1990-05-15', 'L', 'O', '081278901234', 'ahmad.pratama@gmail.com', 'Jl. Suprapto No. 45', 'Anggut Atas', 'Ratu Samban', 'Kota Bengkulu', 'Bengkulu', '38222', 'Siti Aminah', '081278900000', 'Istri'),
('1771022008980002', 2, 'MIH-99283741', 'Siti Aisyah Putri', 'Curup', '1998-08-20', 'P', 'A', '081367892345', 'aisyah.putri@gmail.com', 'Jl. Bali No. 12', 'Kampung Bali', 'Teluk Segara', 'Kota Bengkulu', 'Bengkulu', '38119', 'Hasan Basri', '081367890000', 'Orang Tua'),
('1771031012180003', 1, '0001928374655', 'Muhammad Rizky Pratama', 'Bengkulu', '2004-12-10', 'L', 'B', '085267812390', 'rizky.p@gmail.com', 'Jl. S. Parman No. 88', 'Padang Jati', 'Ratu Agung', 'Kota Bengkulu', 'Bengkulu', '38227', 'Budi Pratama', '085267810000', 'Ayah'),
('1771040203820004', 3, 'PRU-77123980', 'Dewi Lestari', 'Manna', '1982-03-02', 'P', 'AB', '081173098760', 'dewi.lestari@gmail.com', 'Jl. Hibrida Raya No. 19', 'Sidomulyo', 'Gading Cempaka', 'Kota Bengkulu', 'Bengkulu', '38229', 'Andi Wijaya', '081173090000', 'Suami')
ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), id_asuransi = VALUES(id_asuransi);

INSERT INTO akun_pasien (nik, username, email_login, password_hash, status) VALUES
('1771011505900001', 'pasien', 'ahmad.pratama@gmail.com', '$2y$10$H7BIO.roilxWggMnNN5Y6O7D3LiWGPlYiKtDE1fKLvXx7EGFhqLT6', 'aktif')
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), status = 'aktif';
