<?php
// Test error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_safe.php';

echo "<h2>Test Dashboard - Error Check</h2>";

// Cek session
if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning'>Session user_id tidak ditemukan</div>";
    $_SESSION['user_id'] = 1;
    $_SESSION['nama'] = 'Test User';
    $_SESSION['absen_id'] = 'TEST001';
    $_SESSION['role'] = 'karyawan';
    echo "<div class='alert alert-info'>Session dibuat untuk testing</div>";
}

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

echo "<p>User ID: $user_id</p>";
echo "<p>Tanggal: $tanggal_sekarang</p>";

// Test query absensi
try {
    echo "<h4>Testing Query Absensi...</h4>";
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $absensi_hari_ini = $stmt->fetch();
    
    if ($absensi_hari_ini) {
        echo "<div class='alert alert-success'>✅ Absensi ditemukan: " . $absensi_hari_ini['jam_masuk'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Belum ada absensi hari ini</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error absensi: " . $e->getMessage() . "</div>";
}

// Test query lembur
try {
    echo "<h4>Testing Query Lembur...</h4>";
    $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? AND jam_selesai IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $lembur_aktif = $stmt->fetch();
    
    if ($lembur_aktif) {
        echo "<div class='alert alert-success'>✅ Lembur aktif: " . $lembur_aktif['jam_mulai'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Tidak ada lembur aktif</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error lembur: " . $e->getMessage() . "</div>";
}

echo "<hr><p><a href='dashboard.php' class='btn btn-primary'>Ke Dashboard</a></p>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Error Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Content PHP di atas -->
    </div>
</body>
</html>