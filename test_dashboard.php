<?php
// Test dashboard - bypass login untuk testing
require_once 'config_safe.php';

// Set session untuk testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['nama'] = 'Test User';
    $_SESSION['absen_id'] = 'TEST001';
    $_SESSION['role'] = 'karyawan';
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
<html>
<head>
    <title>Test Dashboard - Status Sistem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Status Sistem Absensi</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Info User</h5>
            </div>
            <div class="card-body">
                <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                <p><strong>Nama:</strong> <?php echo $_SESSION['nama']; ?></p>
                <p><strong>Absen ID:</strong> <?php echo $_SESSION['absen_id']; ?></p>
                <p><strong>Tanggal:</strong> <?php echo $tanggal_sekarang; ?></p>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Status Absensi</h5>
            </div>
            <div class="card-body">
                <?php if ($absensi_hari_ini): ?>
                    <p><strong>Status:</strong> Sudah absensi hari ini</p>
                    <p><strong>Jam Masuk:</strong> <?php echo $absensi_hari_ini['jam_masuk']; ?></p>
                    <?php if ($absensi_hari_ini['jam_keluar']): ?>
                        <p><strong>Jam Keluar:</strong> <?php echo $absensi_hari_ini['jam_keluar']; ?></p>
                        <p><strong>Durasi:</strong> <?php echo $absensi_hari_ini['durasi_kerja']; ?></p>
                    <?php else: ?>
                        <p><strong>Status:</strong> Masih dalam absensi</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Status:</strong> Belum absensi hari ini</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Status Lembur</h5>
            </div>
            <div class="card-body">
                <?php if ($lembur_aktif): ?>
                    <p><strong>Status:</strong> Sedang lembur</p>
                    <p><strong>Jam Mulai:</strong> <?php echo $lembur_aktif['jam_mulai']; ?></p>
                <?php else: ?>
                    <p><strong>Status:</strong> Tidak ada lembur aktif</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alert alert-success">
            <strong>Sistem berjalan normal!</strong> Tidak ada error database.
        </div>
        
        <a href="dashboard.php" class="btn btn-primary">Ke Dashboard Utama</a>
        <a href="multiple_absensi.php" class="btn btn-secondary">Test Multiple Absensi</a>
    </div>
</body>
</html>