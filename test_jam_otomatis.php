<?php
require_once 'config_safe.php';

// Test halaman untuk memverifikasi jam masuk otomatis
echo "<h2>Test Jam Masuk Otomatis</h2>";
echo "<p>Waktu Server Saat Ini: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Format Jam: " . date('H:i:s') . "</p>";

// Simulasi proses absensi masuk
if (isset($_GET['test_absen'])) {
    $jam_masuk = date('H:i:s');
    echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>✅ Jam masuk otomatis diambil:</strong> " . $jam_masuk;
    echo "<br><small>Waktu diambil saat tombol absen ditekan</small>";
    echo "</div>";
}

// Test dengan delay
if (isset($_GET['test_delay'])) {
    echo "<p>Menunggu 3 detik...</p>";
    sleep(3);
    $jam_masuk = date('H:i:s');
    echo "<div style='background: #cce5ff; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>⏰ Jam masuk setelah delay:</strong> " . $jam_masuk;
    echo "</div>";
}

// Test format presisi
echo "<h3>Test Presisi Waktu</h3>";
for ($i = 1; $i <= 5; $i++) {
    $jam = date('H:i:s');
    $micro = microtime(true);
    echo "Test $i: $jam (microtime: $micro)<br>";
    usleep(100000); // Delay 0.1 detik
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Jam Masuk Otomatis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-button { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .test-button:hover { background: #0056b3; }
    </style>
</head>
<body>
    
    <h3>Test Fungsionalitas</h3>
    
    <a href="?test_absen=1" class="test-button">Test Absen Masuk</a>
    <a href="?test_delay=1" class="test-button">Test dengan Delay</a>
    <a href="dashboard.php" class="test-button" style="background: #28a745;">Kembali ke Dashboard</a>
    
    <h3>Info Waktu Server</h3>
    <p><strong>Server Time:</strong> <span id="server-time"><?php echo date('H:i:s'); ?></span></p>
    <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
    
    <script>
        // Update waktu secara real-time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            document.getElementById('server-time').textContent = timeString;
        }
        
        // Update setiap detik
        setInterval(updateTime, 1000);
        
        // Log waktu saat halaman dimuat
        console.log('Halaman dimuat pada:', new Date().toLocaleString('id-ID'));
        console.log('Waktu server PHP:', '<?php echo date("Y-m-d H:i:s"); ?>');
    </script>
    
</body>
</html>