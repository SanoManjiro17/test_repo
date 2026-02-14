<?php
// Login sederhana untuk testing
session_start();
require_once 'config_safe.php';

// Auto login untuk testing user dengan ID 1
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = 1");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['absen_id'] = $user['absen_id'];
    $_SESSION['role'] = $user['role'];
    
    header('Location: dashboard.php');
    exit();
} else {
    echo "User dengan ID 1 tidak ditemukan. Silakan buat user terlebih dahulu.";
}
?>