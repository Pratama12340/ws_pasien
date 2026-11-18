<?php
/**
 * index.php - SERVER B: WEB SERVICE & DATABASE PASIEN (`db_pasien`)
 * Sesuai skema `rumah_sakit/db_pasien.sql` (6 Role User: super_admin, admin, pendaftaran, dokter, kasir, pasien)
 *
 * Melayani:
 *   1. W.S. Login  (POST /api/login) -> mendukung tabel `pengguna` (5 role staf) & `akun_pasien` (role pasien)
 *   2. W.S. CRUD Semua Tabel:
 *      - /api/poli atau /api/poliklinik   (GET, POST, PUT, DELETE)
 *      - /api/pasien                      (GET, POST, PUT, DELETE berdasarkan NIK)
 *      - /api/asuransi                    (GET, POST, PUT, DELETE)
 *      - /api/pengguna                    (GET, POST, PUT, DELETE + relasi `pengguna_roles` & `roles`)
 *      - /api/dokter                      (GET, POST, PUT, DELETE)
 *      - /api/roles                       (GET daftar 6 role)
 *      - /api/statistik                   (GET ringkasan dashboard)
 */

require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function send_json(string $status, $data = null, string $message = '', int $code = 200): void
{
    http_response_code($code);
    $res = ['status' => $status, 'success' => ($status === 'success')];
    if ($message !== '') $res['message'] = $message;
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function read_body(): array
{
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_POST ?: [];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body   = read_body();
if ($method === 'POST' && !empty($body['_method'])) {
    $method = strtoupper($body['_method']);
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
    $path = substr($uri, strlen($scriptDir));
} else {
    $path = $uri;
}
if (!empty($_GET['endpoint'])) {
    $path = $_GET['endpoint'];
}
$path = '/' . trim($path, '/');
if ($path === '/index.php') $path = '/';

$cleanPath = preg_replace('#^/api(/v1)?#', '', $path);
$cleanPath = '/' . trim($cleanPath, '/');
$cleanPath = preg_replace('#^/auth/(login|login-pasien)$#', '/login', $cleanPath);
if ($cleanPath === '/dashboard') {
    $cleanPath = '/statistik';
}
if ($cleanPath === '/health') {
    send_json('success', ['status' => 'ok', 'time' => date('c')]);
}

// ============================================================================
// 0. INFO WEB SERVICE
// ============================================================================
if ($cleanPath === '/') {
    send_json('success', [
        'service'   => 'Web Service db_pasien (Server B)',
        'database'  => 'db_pasien',
        'roles'     => ['super_admin', 'admin', 'pendaftaran', 'dokter', 'kasir', 'pasien'],
        'endpoints' => [
            'POST /api/login',
            'GET  /api/statistik',
            'GET  /api/roles',
            'CRUD /api/poli (atau /api/poliklinik)',
            'CRUD /api/pasien',
            'CRUD /api/asuransi',
            'CRUD /api/pengguna',
            'CRUD /api/dokter'
        ]
    ], 'Web Service db_pasien aktif.');
}

// ============================================================================
// 1. W.S. LOGIN (POST /api/login) — Mendukung 6 Role (pengguna + akun_pasien)
// ============================================================================
if ($cleanPath === '/login' && $method === 'POST') {
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        send_json('error', null, 'Username dan password wajib diisi.', 422);
    }

    // 1. Cari di tabel `pengguna` + `pengguna_roles` + `roles`
    $sql = "SELECT p.*, r.id_role, r.nama_role, r.deskripsi AS deskripsi_role
            FROM pengguna p
            LEFT JOIN pengguna_roles pr ON p.id_pengguna = pr.id_pengguna
            LEFT JOIN roles r ON pr.id_role = r.id_role
            WHERE p.username = ? OR p.email = ?
            LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'aktif') {
            send_json('error', null, 'Akun pengguna berstatus ' . $user['status'] . '.', 403);
        }
        mysqli_query($koneksi, "UPDATE pengguna SET last_login_at = NOW() WHERE id_pengguna = " . (int)$user['id_pengguna']);
        unset($user['password_hash']);

        $roleName = $user['nama_role'] ?? 'admin';
        // Ambil daftar permissions untuk role ini
        $perms = [];
        if (!empty($user['id_role'])) {
            $qPerm = mysqli_query($koneksi, "SELECT pm.kode_permission FROM role_permissions rp INNER JOIN permissions pm ON rp.id_permission = pm.id_permission WHERE rp.id_role = " . (int)$user['id_role']);
            while ($pr = mysqli_fetch_assoc($qPerm)) {
                $perms[] = $pr['kode_permission'];
            }
        }

        $user['role']        = $roleName;
        $user['permissions'] = $perms;
        if ($roleName === 'pasien' && empty($user['nik'])) {
            $ap = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nik FROM akun_pasien WHERE username = '" . mysqli_real_escape_string($koneksi, $user['username']) . "' LIMIT 1"));
            $user['nik'] = $ap['nik'] ?? '1771011505900001';
        }

        $token = base64_encode(json_encode([
            'id_pengguna' => (int)$user['id_pengguna'],
            'username'    => $user['username'],
            'role'        => $roleName,
            'exp'         => time() + 86400
        ]));

        send_json('success', ['token' => $token, 'user' => $user], 'Login berhasil.');
    }

    // 2. Jika tidak ada di `pengguna`, cek di tabel `akun_pasien` (Role ke-6: `pasien`)
    $sqlPasien = "SELECT ap.*, ps.nama_lengkap, ps.no_hp, ps.alamat
                  FROM akun_pasien ap
                  INNER JOIN pasien ps ON ap.nik = ps.nik
                  WHERE ap.username = ? OR ap.email_login = ? OR ap.nik = ?
                  LIMIT 1";
    $stmtP = mysqli_prepare($koneksi, $sqlPasien);
    mysqli_stmt_bind_param($stmtP, "sss", $username, $username, $username);
    mysqli_stmt_execute($stmtP);
    $akunPasien = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtP));
    mysqli_stmt_close($stmtP);

    if ($akunPasien && password_verify($password, $akunPasien['password_hash'])) {
        if ($akunPasien['status'] !== 'aktif') {
            send_json('error', null, 'Akun pasien berstatus ' . $akunPasien['status'] . '.', 403);
        }
        mysqli_query($koneksi, "UPDATE akun_pasien SET last_login_at = NOW() WHERE nik = '" . mysqli_real_escape_string($koneksi, $akunPasien['nik']) . "'");
        unset($akunPasien['password_hash']);

        $userPayload = [
            'id_pengguna'  => 0,
            'nik'          => $akunPasien['nik'],
            'username'     => $akunPasien['username'],
            'nama_lengkap' => $akunPasien['nama_lengkap'],
            'email'        => $akunPasien['email_login'],
            'no_hp'        => $akunPasien['no_hp'],
            'status'       => $akunPasien['status'],
            'nama_role'    => 'pasien',
            'role'         => 'pasien',
            'permissions'  => ['dashboard.view', 'poli.view', 'dokter.view', 'pasien.view']
        ];

        $token = base64_encode(json_encode([
            'nik'      => $akunPasien['nik'],
            'username' => $akunPasien['username'],
            'role'     => 'pasien',
            'exp'      => time() + 86400
        ]));

        send_json('success', ['token' => $token, 'user' => $userPayload], 'Login pasien berhasil.');
    }

    send_json('error', null, 'Username atau password salah.', 401);
}

// ============================================================================
// 2. W.S. STATISTIK & ROLES
// ============================================================================
if ($cleanPath === '/statistik' && $method === 'GET') {
    $cPoli     = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM poli"))['c'] ?? 0);
    $cPasien   = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM pasien"))['c'] ?? 0);
    $cAsuransi = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM asuransi"))['c'] ?? 0);
    $cDokter   = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM dokter"))['c'] ?? 0);
    $cPengguna = (int)(mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS c FROM pengguna"))['c'] ?? 0);

    send_json('success', [
        'total_poliklinik' => $cPoli,
        'total_poli'       => $cPoli,
        'total_pasien'     => $cPasien,
        'total_asuransi'   => $cAsuransi,
        'total_dokter'     => $cDokter,
        'total_pengguna'   => $cPengguna
    ]);
}

if ($cleanPath === '/roles' && $method === 'GET') {
    $res = mysqli_query($koneksi, "SELECT * FROM roles ORDER BY id_role ASC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

// ============================================================================
// 3. W.S. CRUD TABEL POLI (/api/poli & /api/poliklinik)
// ============================================================================
if (in_array($cleanPath, ['/poli', '/poliklinik'], true) && $method === 'GET') {
    $sql = "SELECT p.*,
                   p.id_poli AS id_poliklinik,
                   p.kode_poli AS kode_poliklinik,
                   p.nama_poli AS nama_poliklinik,
                   (SELECT COUNT(*) FROM dokter d WHERE d.id_poli = p.id_poli) AS jumlah_dokter
            FROM poli p
            ORDER BY p.id_poli ASC";
    $res = mysqli_query($koneksi, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

if (preg_match('#^/(poli|poliklinik)/(\d+)$#', $cleanPath, $m) && $method === 'GET') {
    $id = (int)$m[2];
    $row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT p.*, p.id_poli AS id_poliklinik, p.kode_poli AS kode_poliklinik, p.nama_poli AS nama_poliklinik FROM poli p WHERE p.id_poli = $id LIMIT 1"));
    if (!$row) send_json('error', null, 'Poli tidak ditemukan.', 404);
    send_json('success', $row);
}

if (in_array($cleanPath, ['/poli', '/poliklinik'], true) && $method === 'POST') {
    $kode       = strtoupper(trim($body['kode_poli'] ?? $body['kode_poliklinik'] ?? ''));
    $nama       = trim($body['nama_poli'] ?? $body['nama_poliklinik'] ?? '');
    $keterangan = trim($body['keterangan'] ?? $body['lokasi_ruangan'] ?? '');
    $status     = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    if ($kode === '' || $nama === '') send_json('error', null, 'Kode Poli dan Nama Poli wajib diisi.', 422);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO poli (kode_poli, nama_poli, keterangan, status) VALUES (?, ?, NULLIF(?, ''), ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $kode, $nama, $keterangan, $status);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal menambah poli: ' . mysqli_stmt_error($stmt), 400);
    $newId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_poli' => $newId], 'Data Poliklinik berhasil ditambahkan.', 201);
}

if (preg_match('#^/(poli|poliklinik)/(\d+)$#', $cleanPath, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $id         = (int)$m[2];
    $kode       = strtoupper(trim($body['kode_poli'] ?? $body['kode_poliklinik'] ?? ''));
    $nama       = trim($body['nama_poli'] ?? $body['nama_poliklinik'] ?? '');
    $keterangan = trim($body['keterangan'] ?? $body['lokasi_ruangan'] ?? '');
    $status     = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    $stmt = mysqli_prepare($koneksi, "UPDATE poli SET kode_poli=?, nama_poli=?, keterangan=NULLIF(?, ''), status=? WHERE id_poli=?");
    mysqli_stmt_bind_param($stmt, "ssssi", $kode, $nama, $keterangan, $status, $id);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal mengubah poli: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_poli' => $id], 'Data Poliklinik berhasil diperbarui.');
}

if (preg_match('#^/(poli|poliklinik)/(\d+)$#', $cleanPath, $m) && $method === 'DELETE') {
    $id = (int)$m[2];
    if (!@mysqli_query($koneksi, "DELETE FROM poli WHERE id_poli = $id")) {
        send_json('error', null, 'Poli tidak dapat dihapus karena masih digunakan oleh Dokter/Kunjungan.', 400);
    }
    send_json('success', null, 'Data Poliklinik berhasil dihapus.');
}

// ============================================================================
// 4. W.S. CRUD TABEL ASURANSI (/api/asuransi)
// ============================================================================
if ($cleanPath === '/asuransi' && $method === 'GET') {
    $res = mysqli_query($koneksi, "SELECT a.*, (SELECT COUNT(*) FROM pasien ps WHERE ps.id_asuransi = a.id_asuransi) AS jumlah_pasien FROM asuransi a ORDER BY a.id_asuransi ASC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

if (preg_match('#^/asuransi/(\d+)$#', $cleanPath, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM asuransi WHERE id_asuransi = $id LIMIT 1"));
    if (!$row) send_json('error', null, 'Asuransi tidak ditemukan.', 404);
    send_json('success', $row);
}

if ($cleanPath === '/asuransi' && $method === 'POST') {
    $nama   = trim($body['nama_asuransi'] ?? '');
    $jenis  = trim($body['jenis_asuransi'] ?? 'BPJS');
    if (!in_array($jenis, ['BPJS', 'swasta', 'lainnya'], true)) $jenis = 'lainnya';
    $kontak = trim($body['nomor_kontak'] ?? $body['kontak_darurat'] ?? '');
    $alamat = trim($body['alamat'] ?? '');
    $status = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    if ($nama === '') send_json('error', null, 'Nama Asuransi wajib diisi.', 422);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO asuransi (nama_asuransi, jenis_asuransi, nomor_kontak, alamat, status) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)");
    mysqli_stmt_bind_param($stmt, "sssss", $nama, $jenis, $kontak, $alamat, $status);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal menambah asuransi: ' . mysqli_stmt_error($stmt), 400);
    $newId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_asuransi' => $newId], 'Data Asuransi berhasil ditambahkan.', 201);
}

if (preg_match('#^/asuransi/(\d+)$#', $cleanPath, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $id     = (int)$m[1];
    $nama   = trim($body['nama_asuransi'] ?? '');
    $jenis  = trim($body['jenis_asuransi'] ?? 'BPJS');
    if (!in_array($jenis, ['BPJS', 'swasta', 'lainnya'], true)) $jenis = 'lainnya';
    $kontak = trim($body['nomor_kontak'] ?? $body['kontak_darurat'] ?? '');
    $alamat = trim($body['alamat'] ?? '');
    $status = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    $stmt = mysqli_prepare($koneksi, "UPDATE asuransi SET nama_asuransi=?, jenis_asuransi=?, nomor_kontak=NULLIF(?, ''), alamat=NULLIF(?, ''), status=? WHERE id_asuransi=?");
    mysqli_stmt_bind_param($stmt, "sssssi", $nama, $jenis, $kontak, $alamat, $status, $id);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal mengubah asuransi: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_asuransi' => $id], 'Data Asuransi berhasil diperbarui.');
}

if (preg_match('#^/asuransi/(\d+)$#', $cleanPath, $m) && $method === 'DELETE') {
    $id = (int)$m[1];
    mysqli_query($koneksi, "DELETE FROM asuransi WHERE id_asuransi = $id");
    send_json('success', null, 'Data Asuransi berhasil dihapus.');
}

// ============================================================================
// 5. W.S. CRUD TABEL DOKTER (/api/dokter)
// ============================================================================
if ($cleanPath === '/dokter' && $method === 'GET') {
    $sql = "SELECT d.*, p.kode_poli, p.nama_poli, p.nama_poli AS nama_poliklinik, u.username AS akun_username
            FROM dokter d
            INNER JOIN poli p ON d.id_poli = p.id_poli
            LEFT JOIN pengguna u ON d.id_pengguna = u.id_pengguna
            ORDER BY d.id_dokter ASC";
    $res = mysqli_query($koneksi, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

if (preg_match('#^/dokter/(\d+)$#', $cleanPath, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM dokter WHERE id_dokter = $id LIMIT 1"));
    if (!$row) send_json('error', null, 'Dokter tidak ditemukan.', 404);
    send_json('success', $row);
}

if ($cleanPath === '/dokter' && $method === 'POST') {
    $idPoli  = (int)($body['id_poli'] ?? $body['id_poliklinik'] ?? 1);
    $kode    = strtoupper(trim($body['kode_dokter'] ?? ('DOK-' . rand(100, 999))));
    $nama    = trim($body['nama_dokter'] ?? '');
    $noSip   = trim($body['no_sip'] ?? $body['nip_sip'] ?? '');
    $spes    = trim($body['spesialisasi'] ?? 'Dokter Umum');
    $hp      = trim($body['no_hp'] ?? '');
    $alamat  = trim($body['alamat'] ?? '');
    $status  = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    if ($kode === '' || $nama === '') send_json('error', null, 'Kode Dokter dan Nama Dokter wajib diisi.', 422);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO dokter (id_poli, kode_dokter, nama_dokter, no_sip, spesialisasi, no_hp, alamat, status) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");
    mysqli_stmt_bind_param($stmt, "isssssss", $idPoli, $kode, $nama, $noSip, $spes, $hp, $alamat, $status);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal menambah dokter: ' . mysqli_stmt_error($stmt), 400);
    $newId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_dokter' => $newId], 'Data Dokter berhasil ditambahkan.', 201);
}

if (preg_match('#^/dokter/(\d+)$#', $cleanPath, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $id      = (int)$m[1];
    $idPoli  = (int)($body['id_poli'] ?? $body['id_poliklinik'] ?? 1);
    $kode    = strtoupper(trim($body['kode_dokter'] ?? ''));
    $nama    = trim($body['nama_dokter'] ?? '');
    $noSip   = trim($body['no_sip'] ?? $body['nip_sip'] ?? '');
    $spes    = trim($body['spesialisasi'] ?? '');
    $hp      = trim($body['no_hp'] ?? '');
    $alamat  = trim($body['alamat'] ?? '');
    $status  = strtolower(trim($body['status'] ?? 'aktif'));
    if (!in_array($status, ['aktif', 'nonaktif'], true)) $status = 'aktif';

    $stmt = mysqli_prepare($koneksi, "UPDATE dokter SET id_poli=?, kode_dokter=?, nama_dokter=?, no_sip=NULLIF(?, ''), spesialisasi=NULLIF(?, ''), no_hp=NULLIF(?, ''), alamat=NULLIF(?, ''), status=? WHERE id_dokter=?");
    mysqli_stmt_bind_param($stmt, "isssssssi", $idPoli, $kode, $nama, $noSip, $spes, $hp, $alamat, $status, $id);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal mengubah dokter: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);
    send_json('success', ['id_dokter' => $id], 'Data Dokter berhasil diperbarui.');
}

if (preg_match('#^/dokter/(\d+)$#', $cleanPath, $m) && $method === 'DELETE') {
    $id = (int)$m[1];
    mysqli_query($koneksi, "DELETE FROM dokter WHERE id_dokter = $id");
    send_json('success', null, 'Data Dokter berhasil dihapus.');
}

// ============================================================================
// 6. W.S. CRUD TABEL PASIEN (/api/pasien — PK: `nik` CHAR(16))
// ============================================================================
if ($cleanPath === '/pasien' && $method === 'GET') {
    $sql = "SELECT ps.*, ps.nama_lengkap AS nama_pasien,
                   a.nama_asuransi, a.jenis_asuransi
            FROM pasien ps
            LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
            ORDER BY ps.created_at DESC";
    $res = mysqli_query($koneksi, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

if (preg_match('#^/pasien/([^/]+)$#', $cleanPath, $m) && $method === 'GET') {
    $nik = mysqli_real_escape_string($koneksi, $m[1]);
    $row = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT ps.*, ps.nama_lengkap AS nama_pasien FROM pasien ps WHERE ps.nik = '$nik' LIMIT 1"));
    if (!$row) send_json('error', null, 'Pasien tidak ditemukan.', 404);
    send_json('success', $row);
}

if ($cleanPath === '/pasien' && $method === 'POST') {
    $nik        = trim($body['nik'] ?? '');
    $idAsu      = !empty($body['id_asuransi']) ? (int)$body['id_asuransi'] : null;
    $noPeserta  = trim($body['nomor_peserta_asuransi'] ?? $body['no_kartu_asuransi'] ?? '');
    $nama       = trim($body['nama_lengkap'] ?? $body['nama_pasien'] ?? '');
    $tmpLahir   = trim($body['tempat_lahir'] ?? '');
    $tglLahir   = trim($body['tanggal_lahir'] ?? '') ?: null;
    $jk         = strtoupper(trim($body['jenis_kelamin'] ?? 'L'));
    $goldar     = trim($body['golongan_darah'] ?? 'O');
    $hp         = trim($body['no_hp'] ?? '');
    $email      = trim($body['email'] ?? '');
    $alamat     = trim($body['alamat'] ?? '');
    $kelurahan  = trim($body['kelurahan'] ?? '');
    $kecamatan  = trim($body['kecamatan'] ?? '');
    $kota       = trim($body['kota_kabupaten'] ?? 'Kota Bengkulu');
    $provinsi   = trim($body['provinsi'] ?? 'Bengkulu');
    $kodePos    = trim($body['kode_pos'] ?? '');
    $namaKontak = trim($body['nama_kontak_darurat'] ?? '');
    $noKontak   = trim($body['no_kontak_darurat'] ?? '');
    $hubKontak  = trim($body['hubungan_kontak_darurat'] ?? '');

    if (!preg_match('/^[0-9]{16}$/', $nik)) {
        send_json('error', null, 'NIK wajib berisi tepat 16 digit angka.', 422);
    }
    if ($nama === '' || $alamat === '') {
        send_json('error', null, 'Nama Lengkap dan Alamat wajib diisi.', 422);
    }

    $stmt = mysqli_prepare($koneksi, "INSERT INTO pasien (nik, id_asuransi, nomor_peserta_asuransi, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, golongan_darah, no_hp, email, alamat, kelurahan, kecamatan, kota_kabupaten, provinsi, kode_pos, nama_kontak_darurat, no_kontak_darurat, hubungan_kontak_darurat) VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))");
    mysqli_stmt_bind_param($stmt, "sisssssssssssssssss", $nik, $idAsu, $noPeserta, $nama, $tmpLahir, $tglLahir, $jk, $goldar, $hp, $email, $alamat, $kelurahan, $kecamatan, $kota, $provinsi, $kodePos, $namaKontak, $noKontak, $hubKontak);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal menambah pasien: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);
    send_json('success', ['nik' => $nik], 'Data Pasien berhasil ditambahkan.', 201);
}

if (preg_match('#^/pasien/([^/]+)$#', $cleanPath, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $oldNik     = $m[1];
    $nik        = trim($body['nik'] ?? $oldNik);
    $idAsu      = !empty($body['id_asuransi']) ? (int)$body['id_asuransi'] : null;
    $noPeserta  = trim($body['nomor_peserta_asuransi'] ?? $body['no_kartu_asuransi'] ?? '');
    $nama       = trim($body['nama_lengkap'] ?? $body['nama_pasien'] ?? '');
    $tmpLahir   = trim($body['tempat_lahir'] ?? '');
    $tglLahir   = trim($body['tanggal_lahir'] ?? '') ?: null;
    $jk         = strtoupper(trim($body['jenis_kelamin'] ?? 'L'));
    $goldar     = trim($body['golongan_darah'] ?? 'O');
    $hp         = trim($body['no_hp'] ?? '');
    $email      = trim($body['email'] ?? '');
    $alamat     = trim($body['alamat'] ?? '');
    $kelurahan  = trim($body['kelurahan'] ?? '');
    $kecamatan  = trim($body['kecamatan'] ?? '');
    $kota       = trim($body['kota_kabupaten'] ?? '');
    $provinsi   = trim($body['provinsi'] ?? '');
    $kodePos    = trim($body['kode_pos'] ?? '');
    $namaKontak = trim($body['nama_kontak_darurat'] ?? '');
    $noKontak   = trim($body['no_kontak_darurat'] ?? '');
    $hubKontak  = trim($body['hubungan_kontak_darurat'] ?? '');

    $stmt = mysqli_prepare($koneksi, "UPDATE pasien SET nik=?, id_asuransi=?, nomor_peserta_asuransi=NULLIF(?, ''), nama_lengkap=?, tempat_lahir=NULLIF(?, ''), tanggal_lahir=?, jenis_kelamin=?, golongan_darah=?, no_hp=NULLIF(?, ''), email=NULLIF(?, ''), alamat=?, kelurahan=NULLIF(?, ''), kecamatan=NULLIF(?, ''), kota_kabupaten=NULLIF(?, ''), provinsi=NULLIF(?, ''), kode_pos=NULLIF(?, ''), nama_kontak_darurat=NULLIF(?, ''), no_kontak_darurat=NULLIF(?, ''), hubungan_kontak_darurat=NULLIF(?, '') WHERE nik=?");
    mysqli_stmt_bind_param($stmt, "sissssssssssssssssss", $nik, $idAsu, $noPeserta, $nama, $tmpLahir, $tglLahir, $jk, $goldar, $hp, $email, $alamat, $kelurahan, $kecamatan, $kota, $provinsi, $kodePos, $namaKontak, $noKontak, $hubKontak, $oldNik);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal mengubah pasien: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);
    send_json('success', ['nik' => $nik], 'Data Pasien berhasil diperbarui.');
}

if (preg_match('#^/pasien/([^/]+)$#', $cleanPath, $m) && $method === 'DELETE') {
    $nik = mysqli_real_escape_string($koneksi, $m[1]);
    mysqli_query($koneksi, "DELETE FROM pasien WHERE nik = '$nik'");
    send_json('success', null, 'Data Pasien berhasil dihapus.');
}

// ============================================================================
// 7. W.S. CRUD TABEL PENGGUNA + PENGGUNA_ROLES (/api/pengguna)
// ============================================================================
if ($cleanPath === '/pengguna' && $method === 'GET') {
    $sql = "SELECT p.id_pengguna, p.nama_lengkap, p.username, p.email, p.no_hp, p.status, p.last_login_at, p.created_at,
                   r.id_role, COALESCE(r.nama_role, 'pendaftaran') AS nama_role,
                   COALESCE(r.nama_role, 'pendaftaran') AS role,
                   COALESCE(r.deskripsi, '-') AS deskripsi_role,
                   (SELECT COUNT(*) FROM role_permissions rp WHERE rp.id_role = r.id_role) AS total_permission
            FROM pengguna p
            LEFT JOIN pengguna_roles pr ON p.id_pengguna = pr.id_pengguna
            LEFT JOIN roles r ON pr.id_role = r.id_role
            ORDER BY p.id_pengguna ASC";
    $res = mysqli_query($koneksi, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    send_json('success', $rows);
}

if ($cleanPath === '/pengguna' && $method === 'POST') {
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? 'admin123';
    $nama     = trim($body['nama_lengkap'] ?? '');
    $email    = trim($body['email'] ?? '');
    $hp       = trim($body['no_hp'] ?? '');
    $status   = strtolower(trim($body['status'] ?? 'aktif'));
    $namaRole = strtolower(trim($body['nama_role'] ?? $body['role'] ?? 'pendaftaran'));

    if ($username === '' || $nama === '') send_json('error', null, 'Username dan Nama Lengkap wajib diisi.', 422);
    $hash = password_hash($password !== '' ? $password : 'admin123', PASSWORD_BCRYPT);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO pengguna (nama_lengkap, username, email, password_hash, no_hp, status) VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?)");
    mysqli_stmt_bind_param($stmt, "ssssss", $nama, $username, $email, $hash, $hp, $status);
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal menambah pengguna: ' . mysqli_stmt_error($stmt), 400);
    $newId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt);

    // Pasangkan ke tabel pengguna_roles
    $rRes = mysqli_query($koneksi, "SELECT id_role FROM roles WHERE nama_role = '" . mysqli_real_escape_string($koneksi, $namaRole) . "' LIMIT 1");
    if ($rRow = mysqli_fetch_assoc($rRes)) {
        $idRole = (int)$rRow['id_role'];
        mysqli_query($koneksi, "INSERT IGNORE INTO pengguna_roles (id_pengguna, id_role) VALUES ($newId, $idRole)");
    }

    send_json('success', ['id_pengguna' => $newId], 'Data Pengguna berhasil ditambahkan.', 201);
}

if (preg_match('#^/pengguna/(\d+)$#', $cleanPath, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $id       = (int)$m[1];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $nama     = trim($body['nama_lengkap'] ?? '');
    $email    = trim($body['email'] ?? '');
    $hp       = trim($body['no_hp'] ?? '');
    $status   = strtolower(trim($body['status'] ?? 'aktif'));
    $namaRole = strtolower(trim($body['nama_role'] ?? $body['role'] ?? 'pendaftaran'));

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($koneksi, "UPDATE pengguna SET nama_lengkap=?, username=?, email=NULLIF(?, ''), password_hash=?, no_hp=NULLIF(?, ''), status=? WHERE id_pengguna=?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $nama, $username, $email, $hash, $hp, $status, $id);
    } else {
        $stmt = mysqli_prepare($koneksi, "UPDATE pengguna SET nama_lengkap=?, username=?, email=NULLIF(?, ''), no_hp=NULLIF(?, ''), status=? WHERE id_pengguna=?");
        mysqli_stmt_bind_param($stmt, "sssssi", $nama, $username, $email, $hp, $status, $id);
    }
    if (!mysqli_stmt_execute($stmt)) send_json('error', null, 'Gagal mengubah pengguna: ' . mysqli_stmt_error($stmt), 400);
    mysqli_stmt_close($stmt);

    $rRes = mysqli_query($koneksi, "SELECT id_role FROM roles WHERE nama_role = '" . mysqli_real_escape_string($koneksi, $namaRole) . "' LIMIT 1");
    if ($rRow = mysqli_fetch_assoc($rRes)) {
        $idRole = (int)$rRow['id_role'];
        mysqli_query($koneksi, "DELETE FROM pengguna_roles WHERE id_pengguna = $id");
        mysqli_query($koneksi, "INSERT INTO pengguna_roles (id_pengguna, id_role) VALUES ($id, $idRole)");
    }

    send_json('success', ['id_pengguna' => $id], 'Data Pengguna berhasil diperbarui.');
}

if (preg_match('#^/pengguna/(\d+)$#', $cleanPath, $m) && $method === 'DELETE') {
    $id = (int)$m[1];
    mysqli_query($koneksi, "DELETE FROM pengguna WHERE id_pengguna = $id");
    send_json('success', null, 'Data Pengguna berhasil dihapus.');
}

send_json('error', null, 'Endpoint tidak ditemukan: ' . $method . ' ' . $cleanPath, 404);
