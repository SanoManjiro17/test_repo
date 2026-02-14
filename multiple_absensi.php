<?php
require_once 'config_safe.php';
cekLogin();

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

// Ambil semua absensi hari ini
$stmt = $pdo->prepare("
    SELECT a.*, 'reguler' as tipe FROM absensi a 
    WHERE a.user_id = ? AND a.tanggal = ? AND a.tipe_absen = 'reguler'
    ORDER BY a.id DESC
");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetchAll();

// Proses absensi masuk baru
if (isset($_POST['absen_masuk_baru'])) {
    $jam_masuk = date('H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, tipe_absen) VALUES (?, ?, ?, 'reguler')");
    $stmt->execute([$user_id, $tanggal_sekarang, $jam_masuk]);
    
    $_SESSION['absen_success'] = "Absensi baru dimulai pada jam " . $jam_masuk;
    
    header('Location: multiple_absensi.php');
    exit();
}

// Proses absensi keluar untuk absensi tertentu
if (isset($_POST['absen_keluar_id'])) {
    $absensi_id = $_POST['absen_keluar_id'];
    $jam_keluar = date('H:i:s');
    
    // Ambil data absensi
    $stmt = $pdo->prepare("SELECT * FROM absensi WHERE id = ? AND user_id = ?");
    $stmt->execute([$absensi_id, $user_id]);
    $absensi = $stmt->fetch();
    
    if ($absensi && !$absensi['jam_keluar']) {
        $durasi = hitungDurasi($absensi['jam_masuk'], $jam_keluar);
        
        $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
        $stmt->execute([$jam_keluar, $durasi, $absensi_id]);
        
        $_SESSION['absen_success'] = "Absensi selesai pada jam " . $jam_keluar . ". Durasi: " . $durasi;
    }
    
    header('Location: multiple_absensi.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multiple Absensi - Lembur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .lembur-card { border-left: 4px solid #ffc107; }
        .active-lembur { background-color: #fff3cd; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-clock"></i> Multiple Absensi
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Halo, <?php echo $_SESSION['nama']; ?> (<?php echo $_SESSION['absen_id']; ?>)
                </span>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        
        <!-- Notifikasi -->
        <?php if (isset($_SESSION['absen_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['absen_success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['absen_success']); ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Daftar Absensi Hari Ini (<?php echo date('d/m/Y'); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        
                        <?php if (empty($absensi_hari_ini)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada absensi hari ini</p>
                                <form method="POST">
                                    <button type="submit" name="absen_masuk_baru" class="btn btn-success">
                                        <i class="fas fa-play"></i> Mulai Absensi Baru
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Jam Masuk</th>
                                            <th>Jam Keluar</th>
                                            <th>Durasi</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($absensi_hari_ini as $index => $absensi): 
                                            $is_active = !$absensi['jam_keluar'];
                                            $row_class = $is_active ? 'table-success' : '';
                                        ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo $absensi['jam_masuk']; ?></td>
                                            <td>
                                                <?php if ($absensi['jam_keluar']): ?>
                                                    <?php echo $absensi['jam_keluar']; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Sedang Berlangsung</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($absensi['durasi_kerja']): ?>
                                                    <span class="badge bg-primary"><?php echo $absensi['durasi_kerja']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_active): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Selesai</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_active): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="absen_keluar_id" value="<?php echo $absensi['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-stop"></i> Selesai
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Tombol untuk absensi baru -->
                            <?php 
                            // Cek apakah ada absensi yang masih berlangsung
                            $has_active = false;
                            foreach ($absensi_hari_ini as $absensi) {
                                if (!$absensi['jam_keluar']) {
                                    $has_active = true;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if (!$has_active): ?>
                            <div class="text-center mt-3">
                                <form method="POST">
                                    <button type="submit" name="absen_masuk_baru" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Tambah Absensi Baru
                                    </button>
                                </form>
                                <small class="text-muted">Anda dapat melakukan multiple absensi dalam sehari</small>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> Selesaikan absensi yang sedang berlangsung terlebih dahulu untuk menambah absensi baru.
                            </div>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Info Multiple Absensi -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb"></i> Informasi Multiple Absensi</h6>
                        <ul class="small mb-0">
                            <li>Anda dapat melakukan multiple absensi dalam satu hari</li>
                            <li>Setiap absensi akan dicatat secara terpisah</li>
                            <li>Fitur ini cocok untuk lembur atau shift ganda</li>
                            <li>Total durasi kerja adalah akumulasi dari semua absensi</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>