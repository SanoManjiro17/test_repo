<?php
// Dashboard dengan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_safe.php';

// Force session untuk testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['nama'] = 'Admin';
    $_SESSION['absen_id'] = 'ADMIN001';
    $_SESSION['role'] = 'manager';
}

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

echo "<h2>Dashboard Debug</h2>";
echo "<p>User ID: $user_id</p>";
echo "<p>Nama: " . $_SESSION['nama'] . "</p>";
echo "<p>Tanggal: $tanggal_sekarang</p>";

// Test query
try {
    echo "<h4>Testing Query...</h4>";
    
    // Cek absensi hari ini
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $absensi_hari_ini = $stmt->fetch();
    
    echo "<p>Absensi query executed</p>";
    
    if ($absensi_hari_ini) {
        echo "<div class='alert alert-success'>✅ Absensi ditemukan: " . $absensi_hari_ini['jam_masuk'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Belum ada absensi hari ini</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='multiple_absensi.php' class='btn btn-secondary'>Test Multiple Absensi</a></p>";
echo "<p><a href='laporan.php' class='btn btn-info'>Test Laporan</a></p>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Content PHP di atas -->
    </div>
</body>
</html>