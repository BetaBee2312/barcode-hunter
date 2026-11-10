<?php
// Salin file ini menjadi config.php lalu isi sesuai koneksi MySQL lokal/hosting.
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'db_barcode_hunting_multicabang';
$dbUser = 'root';
$dbPass = '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
