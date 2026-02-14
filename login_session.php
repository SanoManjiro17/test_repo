<?php
// Login sederhana dengan session handling yang benar
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai session dengan benar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config_safe.php';

echo "<h2>Login Process</h2>";

// Cek apakah user dengan ID 1 ada
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = 1");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    echo "<p>User ditemukan: " . $user['nama'] . "</p>";
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nama'] = $user['nama'];
    $_SESSION['absen_id'] = $user['absen_id'];
    $_SESSION['role'] = $user['role'];
    
    echo "<p>Session disimpan:</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    // Pastikan session disimpan
    session_write_close();
    
    echo "<div class='alert alert-success'>✅ Login berhasil!</div>";
    echo "<p><a href='dashboard.php' class='btn btn-primary'>Lanjut ke Dashboard</a></p>";
    echo "<p><a href='debug_session.php' class='btn btn-secondary'>Cek Session</a></p>";
    
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
        <!-- Content PHP di atas -->
    </div>
</body>
</html>