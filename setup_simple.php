<?php
// Setup sederhana untuk database
require_once 'config_safe.php';

try {
    echo "Memeriksa database...\n";
    
    // Cek tabel absensi_lembur
    $result = $pdo->query("SHOW TABLES LIKE 'absensi_lembur'");
    if ($result->rowCount() == 0) {
        echo "Membuat tabel absensi_lembur...\n";
        $pdo->exec("
            CREATE TABLE absensi_lembur (
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
            )
        ");
        echo "✅ Tabel absensi_lembur dibuat\n";
    } else {
        echo "✅ Tabel absensi_lembur sudah ada\n";
    }
    
    // Cek kolom di tabel absensi
    $result = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'tipe_absen'");
    if ($result->rowCount() == 0) {
        echo "Menambahkan kolom ke tabel absensi...\n";
        $pdo->exec("
            ALTER TABLE absensi 
            ADD COLUMN tipe_absen ENUM('reguler', 'lembur') DEFAULT 'reguler' AFTER durasi_kerja,
            ADD COLUMN status_lembur ENUM('tidak_lembur', 'sedang_lembur', 'selesai_lembur') DEFAULT 'tidak_lembur' AFTER tipe_absen
        ");
        echo "✅ Kolom ditambahkan\n";
    } else {
        echo "✅ Kolom sudah ada\n";
    }
    
    echo "🎉 Setup selesai!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}