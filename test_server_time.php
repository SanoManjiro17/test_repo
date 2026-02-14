<?php
// Test waktu server real-time
require_once 'config_safe.php';

// Dapatkan waktu server saat ini
$waktu_server = date('H:i:s');
$waktu_server_detailed = date('Y-m-d H:i:s');
$tanggal_server = date('Y-m-d');
$timezone = date_default_timezone_get();

echo "<h2>Server Time Test</h2>";
echo "<div class='alert alert-info'>";
echo "<strong>Waktu Server Saat Ini:</strong><br>";
echo "Jam: $waktu_server<br>";
echo "Tanggal & Jam: $waktu_server_detailed<br>";
echo "Timezone: $timezone<br>";
echo "</div>";

// Test insert dengan waktu server
try {
    echo "<h4>Testing Insert dengan Waktu Server...</h4>";
    
    // Dapatkan waktu server untuk jam masuk
    $jam_masuk_server = date('H:i:s');
    $tanggal_hari_ini = date('Y-m-d');
    
    echo "<div class='alert alert-light'>";
    echo "Jam masuk yang akan disimpan: <strong>$jam_masuk_server</strong><br>";
    echo "Tanggal: <strong>$tanggal_hari_ini</strong><br>";
    echo "</div>";
    
    // Simulasi insert (tanpa benar-benar insert)
    echo "<div class='alert alert-success'>";
    echo "✅ Waktu server siap digunakan untuk absensi masuk!<br>";
    echo "✅ Format: HH:MM:SS (24 jam)<br>";
    echo "✅ Contoh: $jam_masuk_server<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

// Update waktu secara real-time dengan JavaScript
echo "<script>";
echo "function updateTime() {";
echo "    var now = new Date();";
echo "    var timeString = now.toLocaleTimeString('id-ID', {hour12: false});";
echo "    document.getElementById('realtime-clock').innerHTML = timeString;";
echo "}";
echo "setInterval(updateTime, 1000);";
echo "updateTime();"; // Jalankan sekali
echo "</script>";

echo "<div class='card mt-3'>";
echo "<div class='card-body'>";
echo "<h5>Waktu Real-time (Browser):</h5>";
echo "<div id='realtime-clock' class='h4 text-primary'></div>";
echo "<small class='text-muted'>Bandingkan dengan waktu server di atas</small>";
echo "</div>";
echo "</div>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Server Time Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- Content PHP di atas -->
        
        <div class="mt-4">
            <h5>Informasi Waktu Server:</h5>
            <ul>
                <li>Waktu server diambil dari fungsi PHP <code>date()</code></li>
                <li>Format waktu: 24 jam (HH:MM:SS)</li>
                <li>Update otomatis saat tombol absen ditekan</li>
                <li>Tidak tergantung pada waktu browser/client</li>
            </ul>
        </div>
        
        <div class="mt-3">
            <a href="dashboard.php" class="btn btn-primary">Kembali ke Dashboard</a>
            <button onclick="location.reload()" class="btn btn-secondary">Refresh</button>
        </div>
    </div>
</body>
</html>