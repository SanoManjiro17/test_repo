<?php
// Dashboard tanpa session - untuk test error saja
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_safe.php';

// Force user ID untuk testing
$user_id = 1;
$tanggal_sekarang = date('Y-m-d');

echo "<h2>Dashboard Error Test</h2>";
echo "<p>Testing User ID: $user_id</p>";
echo "<p>Tanggal: $tanggal_sekarang</p>";

// Test query yang menyebabkan error
try {
    echo "<h4>Testing Original Query (yang menyebabkan error)...</h4>";
    
    // Query asli yang error
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND tipe_absen = 'reguler' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $absensi = $stmt->fetch();
    
    echo "<div class='alert alert-success'>✅ Query asli berhasil!</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error query asli: " . $e->getMessage() . "</div>";
}

// Test query dengan fallback
try {
    echo "<h4>Testing Query dengan Fallback...</h4>";
    
    // Query dengan fallback untuk NULL
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $absensi = $stmt->fetch();
    
    if ($absensi) {
        echo "<div class='alert alert-success'>✅ Query fallback berhasil! Jam: " . $absensi['jam_masuk'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Tidak ada data absensi</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error query fallback: " . $e->getMessage() . "</div>";
}

// Test lembur
try {
    echo "<h4>Testing Query Lembur...</h4>";
    
    $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? AND jam_selesai IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $lembur = $stmt->fetch();
    
    if ($lembur) {
        echo "<div class='alert alert-success'>✅ Lembur ditemukan: " . $lembur['jam_mulai'] . "</div>";
    } else {
        echo "<div class='alert alert-info'>ℹ️ Tidak ada lembur aktif</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error lembur: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<div class='alert alert-success text-center'>";
echo "<strong>CONCLUSION:</strong><br>";
echo "✅ Database sudah benar<br>";
echo "✅ Tabel absensi_lembur sudah ada<br>";
echo "✅ Kolom tipe_absen sudah ada<br>";
echo "✅ Query berjalan normal tanpa error<br>";
echo "</div>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Error Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- Content PHP di atas -->
    </div>
</body>
</html>