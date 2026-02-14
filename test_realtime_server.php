<?php
// Test waktu server saat proses absen
require_once 'config_safe.php';

// Simulasi waktu server saat ini
$waktu_sekarang = date('H:i:s');
$detailed_time = date('Y-m-d H:i:s');
$timestamp = time();

echo "<h2>Test Waktu Server Real-time</h2>";
echo "<div class='alert alert-info'>";
echo "<strong>Waktu Server Saat Ini:</strong><br>";
echo "Jam: <span id='server-time'>$waktu_sekarang</span><br>";
echo "Detail: $detailed_time<br>";
echo "Timestamp: $timestamp<br>";
echo "Timezone: " . date_default_timezone_get() . "<br>";
echo "</div>";

// Test AJAX untuk waktu server
echo "<script>";
echo "function getServerTime() {";
echo "    fetch('get_server_time.php')";
echo "        .then(response => response.json())";
echo "        .then(data => {";
echo "            console.log('Server Time Response:', data);";
echo "            document.getElementById('ajax-time').innerHTML = ";
echo "                '<strong>Waktu dari Server:</strong><br>' +";
echo "                'Jam: ' + data.formatted_time + '<br>' +";
echo "                'Detail: ' + data.server_time + '<br>' +";
echo "                'Timezone: ' + data.timezone;";
echo "        })";
echo "        .catch(error => {";
echo "            console.error('Error:', error);";
echo "            document.getElementById('ajax-time').innerHTML = '<span class=\"text-danger\">Error: ' + error + '</span>';";
echo "        });";
echo "}";

echo "// Test setiap detik";
echo "setInterval(getServerTime, 1000);";
echo "getServerTime(); // Jalankan sekali";
echo "</script>";

echo "<div class='card mt-3'>";
echo "<div class='card-body'>";
echo "<h5>Waktu Server via AJAX:</h5>";
echo "<div id='ajax-time' class='text-primary'>Loading...</div>";
echo "</div>";
echo "</div>";

echo "<div class='mt-3'>";
echo "<button onclick='getServerTime()' class='btn btn-primary'>Refresh Manual</button>";
echo "<button onclick='location.reload()' class='btn btn-secondary'>Reload Page</button>";
echo "</div>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Waktu Server Real-time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- Content PHP di atas -->
        
        <div class="mt-4">
            <h5>Perbandingan Waktu:</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Waktu Saat Page Load (PHP):</h6>
                            <p class="text-primary"><?php echo $waktu_sekarang; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6>Waktu Real-time (AJAX):</h6>
                            <p id="real-time-compare" class="text-success">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="alert alert-info mt-3">
            <strong>Catatan:</strong>
            <ul>
                <li>Waktu PHP diambil saat halaman dimuat</li>
                <li>Waktu AJAX diperbarui setiap detik dari server</li>
                <li>Perbedaan menunjukkan delay antara request</li>
            </ul>
        </div>
    </div>
</body>
</html>