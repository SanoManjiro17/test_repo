<?php
// Test waktu server saat tombol absen ditekan - tanpa login
require_once 'config_safe.php';

// Force user untuk testing
$user_id = 1;
$tanggal_sekarang = date('Y-m-d');

// Handle AJAX request untuk test waktu real
if (isset($_POST['action']) && $_POST['action'] == 'test_waktu_real') {
    header('Content-Type: application/json');
    
    // Dapatkan waktu server saat ini (real-time saat request)
    $jam_server = date('H:i:s');
    $tanggal_server = date('Y-m-d');
    $detailed_time = date('Y-m-d H:i:s');
    $timestamp = time();
    
    echo json_encode([
        'success' => true,
        'jam_server' => $jam_server,
        'tanggal_server' => $tanggal_server,
        'detailed_time' => $detailed_time,
        'timestamp' => $timestamp,
        'timezone' => date_default_timezone_get(),
        'message' => "Waktu server saat ini: $jam_server"
    ]);
    exit();
}

// Handle AJAX request untuk absen masuk test
if (isset($_POST['action']) && $_POST['action'] == 'absen_masuk_test') {
    header('Content-Type: application/json');
    
    // Dapatkan waktu server saat ini (real-time)
    $jam_masuk = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    echo json_encode([
        'success' => true,
        'jam_masuk' => $jam_masuk,
        'tanggal' => $tanggal,
        'message' => "Absen masuk berhasil pada jam " . $jam_masuk . " (waktu server real-time)",
        'note' => "Waktu diambil langsung dari server saat tombol ditekan"
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Waktu Server Real-time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .real-time-display { 
            font-family: 'Courier New', monospace; 
            font-size: 2em; 
            font-weight: bold;
            color: #0d6efd;
        }
        .timestamp-display {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #6c757d;
        }
        .loading { 
            opacity: 0.6; 
            pointer-events: none; 
        }
        .btn-test {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-clock"></i> Test Waktu Server Real-time
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Waktu Server Display -->
                        <div class="text-center mb-4">
                            <h6 class="text-muted">Waktu Server Saat Ini:</h6>
                            <div id="server-time-display" class="real-time-display">
                                <?php echo date('H:i:s'); ?>
                            </div>
                            <div id="server-date-display" class="timestamp-display">
                                <?php echo date('Y-m-d H:i:s'); ?> | <?php echo date_default_timezone_get(); ?>
                            </div>
                        </div>

                        <!-- Test Controls -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button id="btn-test-waktu" class="btn btn-primary btn-test mb-2">
                                        <i class="fas fa-sync-alt"></i> Test Waktu Real
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button id="btn-absen-test" class="btn btn-success btn-test mb-2">
                                        <i class="fas fa-sign-in-alt"></i> Test Absen Masuk
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Results Display -->
                        <div id="test-results" class="mt-4">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Cara Kerja:</h6>
                                <ul class="mb-0">
                                    <li>Waktu diambil langsung dari server saat tombol ditekan</li>
                                    <li>Tidak menggunakan waktu browser/client</li>
                                    <li>Setiap klik akan menghasilkan waktu yang berbeda</li>
                                    <li>Test menunjukkan bahwa waktu adalah real-time</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Last Absen Display -->
                        <div id="last-absen" class="mt-3" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h6><i class="fas fa-check-circle text-success"></i> Hasil Absen Terakhir:</h6>
                                    <div id="last-absen-result"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk update waktu server display
        function updateServerTime() {
            fetch('get_server_time.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('server-time-display').textContent = data.formatted_time;
                    document.getElementById('server-date-display').textContent = 
                        data.server_time + ' | ' + data.timezone;
                })
                .catch(error => {
                    console.error('Error updating server time:', error);
                });
        }

        // Fungsi untuk test waktu real
        async function testWaktuReal() {
            const btn = document.getElementById('btn-test-waktu');
            const originalHTML = btn.innerHTML;
            
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
                btn.classList.add('loading');
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=test_waktu_real'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const resultsDiv = document.getElementById('test-results');
                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle"></i> Waktu Server Real-time:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Jam:</strong> <span class="real-time-display">${data.jam_server}</span><br>
                                    <strong>Tanggal:</strong> ${data.tanggal_server}<br>
                                    <strong>Timezone:</strong> ${data.timezone}
                                </div>
                                <div class="col-md-6">
                                    <strong>Timestamp:</strong> ${data.timestamp}<br>
                                    <strong>Detail:</strong> ${data.detailed_time}<br>
                                    <small class="text-muted">Waktu diambil saat tombol ditekan</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Test lagi dalam 3 detik untuk menunjukkan perbedaan waktu
                    setTimeout(() => {
                        if (confirm('Test lagi untuk melihat perbedaan waktu?')) {
                            testWaktuReal();
                        }
                    }, 3000);
                }
            } catch (error) {
                showError('Error: ' + error.message);
            } finally {
                btn.innerHTML = originalHTML;
                btn.classList.remove('loading');
            }
        }

        // Fungsi untuk test absen masuk
        async function testAbsenMasuk() {
            const btn = document.getElementById('btn-absen-test');
            const originalHTML = btn.innerHTML;
            
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.classList.add('loading');
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=absen_masuk_test'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const lastAbsenDiv = document.getElementById('last-absen');
                    const resultDiv = document.getElementById('last-absen-result');
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-light mb-0">
                            <strong>Jam Masuk:</strong> <span class="text-primary">${data.jam_masuk}</span><br>
                            <strong>Tanggal:</strong> ${data.tanggal}<br>
                            <small class="text-muted">${data.note}</small>
                        </div>
                    `;
                    
                    lastAbsenDiv.style.display = 'block';
                    
                    // Auto hide after 10 seconds
                    setTimeout(() => {
                        lastAbsenDiv.style.display = 'none';
                    }, 10000);
                    
                    // Update waktu display
                    updateServerTime();
                }
            } catch (error) {
                showError('Error: ' + error.message);
            } finally {
                btn.innerHTML = originalHTML;
                btn.classList.remove('loading');
            }
        }

        // Fungsi untuk show error
        function showError(message) {
            document.getElementById('test-results').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> ${message}
                </div>
            `;
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btn-test-waktu').addEventListener('click', testWaktuReal);
            document.getElementById('btn-absen-test').addEventListener('click', testAbsenMasuk);
            
            // Update waktu setiap detik
            updateServerTime();
            setInterval(updateServerTime, 1000);
        });
    </script>
</body>
</html>