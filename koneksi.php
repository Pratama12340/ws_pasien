<?php
/**
 * koneksi.php - Server B (Database & Web Service db_pasien)
 */
$host     = "localhost";
$username = "root";
$password = "";
$database = "db_pasien";
$port     = 3306;

$koneksi = mysqli_connect($host, $username, $password, $database, $port);

if (!$koneksi) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Koneksi database db_pasien gagal: ' . mysqli_connect_error()
    ]);
    exit;
}

mysqli_set_charset($koneksi, "utf8mb4");
