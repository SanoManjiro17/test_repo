<?php
// Cek apakah database sudah setup, jika belum redirect ke setup
require_once 'config_safe.php';

// Jika sudah login, redirect ke dashboard yang sesuai
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit();
}

try {
    // Coba koneksi dan cek tabel
    $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
    // Jika berhasil, redirect ke login
    header('Location: login.php');
} catch (Exception $e) {
    // Jika database belum setup, redirect ke setup
    header('Location: setup_database.php');
}
exit();
?>