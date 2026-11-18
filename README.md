# Web Service & Database Pasien — Server B (`ws_pasien`)

Backend REST API (**Service Provider**) dan skema database MySQL `db_pasien` untuk Sistem Informasi Manajemen Pasien & Poliklinik Rumah Sakit.

## Daftar Endpoint Utama
- `GET /api/health` — Cek status server Web Service
- `POST /api/auth/login` & `POST /api/auth/login-pasien` — Autentikasi Login RBAC 6 Role
- `GET /api/dashboard` — Statistik ringkasan rumah sakit
- `GET | POST | PUT | DELETE /api/poli` — CRUD Tabel Poliklinik
- `GET | POST | PUT | DELETE /api/pasien` — CRUD Tabel Pasien
- `GET | POST | PUT | DELETE /api/asuransi` — CRUD Tabel Asuransi
- `GET | POST | PUT | DELETE /api/dokter` — CRUD Tabel Dokter
- `GET | POST | PUT | DELETE /api/pengguna` — CRUD Tabel Pengguna & Role RBAC
