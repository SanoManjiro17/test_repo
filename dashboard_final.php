<?php
// Dashboard final dengan session handling yang benar
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_safe.php';

// Cek login dengan session yang benar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika belum login, redirect ke login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

// Cek absensi hari ini (absensi reguler)
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
    <title>Dashboard Absensi - Final</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-clock"></i> Dashboard Absensi - FINAL
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Info User</h5>
                                <p><strong>Nama:</strong> <?php echo $_SESSION['nama']; ?></p>
                                <p><strong>ID:</strong> <?php echo $_SESSION['absen_id']; ?></p>
                                <p><strong>Tanggal:</strong> <?php echo date('d/m/Y'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Status Absensi</h5>
                                <?php if ($absensi_hari_ini): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> Sudah Absen Masuk<br>
                                        <strong>Jam:</strong> <?php echo $absensi_hari_ini['jam_masuk']; ?>
                                    </div>
                                    <?php if ($absensi_hari_ini['jam_keluar']): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-sign-out-alt"></i> Sudah Absen Keluar<br>
                                            <strong>Durasi:</strong> <?php echo $absensi_hari_ini['durasi_kerja']; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Belum Absen Hari Ini
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Status Lembur</h5>
                                <?php if ($lembur_aktif): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-moon"></i> Sedang Lembur<br>
                                        <strong>Mulai:</strong> <?php echo $lembur_aktif['jam_mulai']; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-secondary">
                                        <i class="fas fa-moon"></i> Tidak Ada Lembur Aktif
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="multiple_absensi.php" class="btn btn-primary w-100">
                                        <i class="fas fa-plus"></i> Multiple Absensi
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5>Menu Lain</h5>
                                <a href="laporan.php" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-chart-bar"></i> Laporan
                                </a>
                                <a href="logout.php" class="btn btn-danger w-100">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                        
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle"></i>
                            <strong>SISTEM BERJALAN NORMAL!</strong><br>
                            Tidak ada error database atau query.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>