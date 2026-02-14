<?php
// Login sederhana untuk testing - bypass password
session_start();
require_once 'config_safe.php';

// Cek apakah user dengan ID 1 ada
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = 1");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['absen_id'] = $user['absen_id'];
    $_SESSION['role'] = $user['role'];
    
    echo "<div class='alert alert-success'>Login berhasil sebagai: " . $user['nama'] . " (" . $user['absen_id'] . ")</div>";
    echo "<p><a href='dashboard.php' class='btn btn-primary'>Ke Dashboard</a></p>";
    echo "<p><a href='multiple_absensi.php' class='btn btn-secondary'>Ke Multiple Absensi</a></p>";
} else {
    echo "<div class='alert alert-danger'>User dengan ID 1 tidak ditemukan</div>";
    echo "<p>Silakan buat user terlebih dahulu di database</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Login Testing</h2>
        <!-- Content PHP di atas -->
    </div>
</body>
</html>