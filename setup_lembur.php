<?php
// Halaman setup untuk update database fitur lembur
try {
    require_once 'config.php';
    
    echo "<h2>Setup Database - Fitur Lembur & Multiple Absensi</h2>";
    
    // Cek apakah tabel absensi_lembur sudah ada
    $check_table = $pdo->query("SHOW TABLES LIKE 'absensi_lembur'");
    
    if ($check_table->rowCount() > 0) {
        echo "<div class='alert alert-info'>✅ Tabel absensi_lembur sudah ada</div>";
    } else {
        echo "<div class='alert alert-warning'>⏳ Membuat tabel absensi_lembur...</div>";
        
        // Buat tabel absensi_lembur
        $sql = "CREATE TABLE absensi_lembur (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tanggal DATE NOT NULL,
            jam_mulai TIME NOT NULL,
            jam_selesai TIME NULL,
            durasi_lembur VARCHAR(20) NULL,
            keterangan TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_tanggal (user_id, tanggal)
        )";
        
        $pdo->exec($sql);
        echo "<div class='alert alert-success'>✅ Tabel absensi_lembur berhasil dibuat</div>";
    }
    
    // Cek apakah kolom sudah ada di tabel absensi
    $check_column = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'tipe_absen'");
    
    if ($check_column->rowCount() > 0) {
        echo "<div class='alert alert-info'>✅ Kolom tipe_absen sudah ada</div>";
    } else {
        echo "<div class='alert alert-warning'>⏳ Menambahkan kolom ke tabel absensi...</div>";
        
        // Tambahkan kolom ke tabel absensi
        $sql = "ALTER TABLE absensi 
                ADD COLUMN tipe_absen ENUM('reguler', 'lembur') DEFAULT 'reguler' AFTER durasi_kerja,
                ADD COLUMN status_lembur ENUM('tidak_lembur', 'sedang_lembur', 'selesai_lembur') DEFAULT 'tidak_lembur' AFTER tipe_absen";
        
        $pdo->exec($sql);
        echo "<div class='alert alert-success'>✅ Kolom berhasil ditambahkan</div>";
        
        // Update data existing
        $pdo->exec("UPDATE absensi SET tipe_absen = 'reguler' WHERE tipe_absen IS NULL");
        echo "<div class='alert alert-success'>✅ Data existing diupdate</div>";
    }
    
    echo "<div class='alert alert-success mt-4'>🎉 Setup selesai! Database sudah siap untuk fitur lembur dan multiple absensi.</div>";
    echo "<a href='dashboard.php' class='btn btn-primary'>Kembali ke Dashboard</a>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    echo "<a href='dashboard.php' class='btn btn-secondary'>Kembali ke Dashboard</a>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Setup Database - Lembur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <?php
        // Konten PHP di atas akan ditampilkan di sini
        ?>
    </div>
</body>
</html>