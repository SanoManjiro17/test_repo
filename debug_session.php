<?php
// Pastikan session dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session
echo "<h2>Session Debug</h2>";
echo "<pre>";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: ";
print_r($_SESSION);
echo "</pre>";

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning'>Belum login - redirect ke login</div>";
    // Tidak redirect dulu, biar kita lihat debug
} else {
    echo "<div class='alert alert-success'>Sudah login sebagai: " . $_SESSION['nama'] . "</div>";
}

// Test database
try {
    require_once 'config_safe.php';
    
    $user_id = $_SESSION['user_id'] ?? 1;
    $tanggal_sekarang = date('Y-m-d');
    
    echo "<h4>Test Database</h4>";
    echo "<p>User ID: $user_id</p>";
    echo "<p>Tanggal: $tanggal_sekarang</p>";
    
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $absensi = $stmt->fetch();
    
    if ($absensi) {
        echo "<div class='alert alert-success'>✅ Absensi ditemukan: " . $absensi['jam_masuk'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Belum ada absensi hari ini</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Session</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Content PHP di atas -->
        <hr>
        <a href="dashboard.php" class="btn btn-primary">Coba Dashboard</a>
        <a href="multiple_absensi.php" class="btn btn-secondary">Coba Multiple Absensi</a>
        <a href="login_simple.php" class="btn btn-info">Login Ulang</a>
    </div>
</body>
</html>