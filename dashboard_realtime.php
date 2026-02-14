<?php
// Proses absensi dengan waktu server real-time via AJAX
require_once 'config_safe.php';
cekLogin();

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

// Handle AJAX request untuk absensi masuk
if (isset($_POST['action']) && $_POST['action'] == 'absen_masuk_ajax') {
    header('Content-Type: application/json');
    
    // Dapatkan waktu server saat ini (real-time)
    $jam_masuk = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    try {
        // Insert absensi dengan waktu server real-time
        $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, tipe_absen) VALUES (?, ?, ?, 'reguler')");
        $stmt->execute([$user_id, $tanggal, $jam_masuk]);
        
        echo json_encode([
            'success' => true,
            'message' => "Absen masuk berhasil pada jam " . $jam_masuk,
            'jam_masuk' => $jam_masuk,
            'tanggal' => $tanggal
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ]);
    }
    exit();
}

// Handle AJAX request untuk absensi keluar
if (isset($_POST['action']) && $_POST['action'] == 'absen_keluar_ajax' && isset($_POST['absensi_id'])) {
    header('Content-Type: application/json');
    
    $absensi_id = $_POST['absensi_id'];
    $jam_keluar = date('H:i:s');
    
    try {
        // Ambil data absensi
        $stmt = $pdo->prepare("SELECT * FROM absensi WHERE id = ? AND user_id = ?");
        $stmt->execute([$absensi_id, $user_id]);
        $absensi = $stmt->fetch();
        
        if ($absensi && !$absensi['jam_keluar']) {
            $durasi = hitungDurasi($absensi['jam_masuk'], $jam_keluar);
            
            $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
            $stmt->execute([$jam_keluar, $durasi, $absensi_id]);
            
            echo json_encode([
                'success' => true,
                'message' => "Absen keluar berhasil pada jam " . $jam_keluar . ". Total durasi: " . $durasi,
                'jam_keluar' => $jam_keluar,
                'durasi' => $durasi
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Absensi tidak ditemukan atau sudah selesai"
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ]);
    }
    exit();
}

// Cek absensi hari ini (untuk display)
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetch();

// Cek lembur yang sedang berlangsung
try {
    $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? AND jam_selesai IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $lembur_aktif = $stmt->fetch();
} catch (PDOException $e) {
    $lembur_aktif = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Absensi - Real-time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.9rem; }
        .loading { opacity: 0.6; pointer-events: none; }
        .real-time-display { font-family: 'Courier New', monospace; font-size: 1.2em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard_realtime.php">
                <i class="fas fa-clock"></i> Absensi Real-time
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Halo, <?php echo $_SESSION['nama']; ?> (<?php echo $_SESSION['absen_id']; ?>)
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Notifikasi -->
        <div id="notifikasi"></div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-clock"></i> Status Absensi
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Tanggal:</strong> <?php echo date('d/m/Y'); ?></p>
                        <p><strong>Jam Server:</strong> <span id="jam_server" class="real-time-display text-primary"><?php echo date('H:i:s'); ?></span></p>
                        <p><small class="text-muted">Timezone: <?php echo date_default_timezone_get(); ?></small></p>
                        
                        <div id="status-absensi">
                            <?php if (!$absensi_hari_ini): ?>
                                <div class="alert alert-light border">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Belum absen hari ini
                                    </small>
                                </div>
                                <button id="btn-absen-masuk" class="btn btn-success w-100">
                                    <i class="fas fa-sign-in-alt"></i> Absen Masuk Sekarang
                                </button>
                            <?php elseif (!$absensi_hari_ini['jam_keluar']): ?>
                                <div class="alert alert-info">
                                    <strong>Status:</strong> Sudah Absen Masuk<br>
                                    <strong>Jam Masuk:</strong> <?php echo $absensi_hari_ini['jam_masuk']; ?>
                                </div>
                                <button id="btn-absen-keluar" class="btn btn-warning w-100" data-absensi-id="<?php echo $absensi_hari_ini['id']; ?>">
                                    <i class="fas fa-sign-out-alt"></i> Absen Keluar
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <strong>Status:</strong> Absensi Selesai<br>
                                    <strong>Jam Masuk:</strong> <?php echo $absensi_hari_ini['jam_masuk']; ?><br>
                                    <strong>Jam Keluar:</strong> <?php echo $absensi_hari_ini['jam_keluar']; ?><br>
                                    <strong>Durasi:</strong> <?php echo $absensi_hari_ini['durasi_kerja']; ?>
                                </div>
                                <div class="d-grid gap-2">
                                    <button id="btn-absen-baru" class="btn btn-outline-secondary">
                                        <i class="fas fa-plus"></i> Absen Baru/Lagi
                                    </button>
                                    <a href="multiple_absensi.php" class="btn btn-outline-info">
                                        <i class="fas fa-list"></i> Multiple Absensi
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Informasi Sistem
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light">
                            <h6><i class="fas fa-clock"></i> Waktu Server Real-time</h6>
                            <p class="mb-0">Semua waktu absensi menggunakan waktu server yang tidak dapat dimanipulasi.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-server fa-2x text-primary mb-2"></i>
                                        <h6>Server Time</h6>
                                        <p class="mb-0 real-time-display" id="server-time-display"><?php echo date('H:i:s'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-light">
                                    <div class="card-body text-center">
                                        <i class="fas fa-sync-alt fa-2x text-success mb-2"></i>
                                        <h6>Last Sync</h6>
                                        <p class="mb-0" id="last-sync"><?php echo date('H:i:s'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk update waktu server display
        function updateServerTimeDisplay() {
            fetch('get_server_time.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('server-time-display').textContent = data.formatted_time;
                    document.getElementById('last-sync').textContent = data.formatted_time;
                })
                .catch(error => {
                    console.error('Error updating server time:', error);
                });
        }

        // Fungsi untuk show notifikasi
        function showNotifikasi(message, type = 'success') {
            const notifikasi = document.getElementById('notifikasi');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            notifikasi.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas ${icon}"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                const alert = notifikasi.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Fungsi untuk absen masuk dengan waktu server real-time
        async function absenMasuk() {
            const btn = document.getElementById('btn-absen-masuk');
            const originalText = btn.innerHTML;
            
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.classList.add('loading');
                
                const response = await fetch('dashboard_realtime.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=absen_masuk_ajax'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotifikasi(data.message);
                    // Reload page after 2 seconds to update UI
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotifikasi(data.message, 'danger');
                }
            } catch (error) {
                showNotifikasi('Error: ' + error.message, 'danger');
            } finally {
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
            }
        }

        // Fungsi untuk absen keluar
        async function absenKeluar(absensiId) {
            const btn = document.getElementById('btn-absen-keluar');
            const originalText = btn.innerHTML;
            
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.classList.add('loading');
                
                const response = await fetch('dashboard_realtime.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=absen_keluar_ajax&absensi_id=${absensiId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotifikasi(data.message);
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotifikasi(data.message, 'danger');
                }
            } catch (error) {
                showNotifikasi('Error: ' + error.message, 'danger');
            } finally {
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Absen masuk
            const btnAbsenMasuk = document.getElementById('btn-absen-masuk');
            if (btnAbsenMasuk) {
                btnAbsenMasuk.addEventListener('click', absenMasuk);
            }
            
            // Absen keluar
            const btnAbsenKeluar = document.getElementById('btn-absen-keluar');
            if (btnAbsenKeluar) {
                btnAbsenKeluar.addEventListener('click', function() {
                    const absensiId = this.getAttribute('data-absensi-id');
                    absenKeluar(absensiId);
                });
            }
            
            // Update server time setiap detik
            updateServerTimeDisplay();
            setInterval(updateServerTimeDisplay, 1000);
        });
    </script>
</body>
</html>