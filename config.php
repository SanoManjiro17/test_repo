<?php
// Konfigurasi Database
$host = 'localhost';
$dbname = 'absensi_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Session start
session_start();

// Fungsi untuk menghitung durasi kerja
function hitungDurasi($jam_masuk, $jam_keluar) {
    if (!$jam_keluar) return null;
    
    $masuk = new DateTime($jam_masuk);
    $keluar = new DateTime($jam_keluar);
    
    $durasi = $masuk->diff($keluar);
    
    return $durasi->format('%H:%I:%S');
}

// Fungsi untuk format waktu Indonesia
function formatWaktuIndonesia($datetime) {
    return date('d/m/Y H:i:s', strtotime($datetime));
}

// Fungsi untuk cek login
function cekLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk cek role admin/manager
function cekAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'manager') {
        header('Location: dashboard.php');
        exit();
    }
}
?>