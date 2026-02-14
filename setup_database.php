<?php
// Setup database otomatis
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Koneksi ke MySQL tanpa database tertentu
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buat database jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS absensi_system");
    echo "<p style='color: green;'>✓ Database 'absensi_system' berhasil dibuat atau sudah ada</p>";
    
    // Gunakan database
    $pdo->exec("USE absensi_system");
    
    // Buat tabel users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            absen_id VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            telepon VARCHAR(20) DEFAULT NULL,
            nama VARCHAR(100) NOT NULL,
            nip VARCHAR(30) DEFAULT NULL,
            alamat TEXT DEFAULT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('staf', 'manager') DEFAULT 'staf',
            foto VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Tabel 'users' berhasil dibuat</p>";
    
    // Buat tabel absensi
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS absensi (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            tanggal DATE NOT NULL,
            jam_masuk TIME NOT NULL,
            jam_keluar TIME,
            durasi_kerja TIME,
            tipe_absen ENUM('reguler', 'sakit', 'cuti') DEFAULT 'reguler',
            status_lembur ENUM('tidak_lembur', 'sedang_lembur', 'selesai_lembur') DEFAULT 'tidak_lembur',
            keterangan TEXT,
            bukti_izin VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "<p style='color: green;'>✓ Tabel 'absensi' berhasil dibuat</p>";
    
    // Buat tabel absensi_lembur
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS absensi_lembur (
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
    echo "<p style='color: green;'>✓ Tabel 'absensi_lembur' berhasil dibuat</p>";
    
    // Buat tabel tasklist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tasklist (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            assigned_to INT,
            absensi_id INT,
            task_name VARCHAR(255) NOT NULL,
            deskripsi TEXT,
            deadline DATE,
            start_time TIME,
            end_time TIME,
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            status ENUM('pending', 'progress', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (assigned_to) REFERENCES users(id),
            FOREIGN KEY (absensi_id) REFERENCES absensi(id)
        )
    ");
    echo "<p style='color: green;'>✓ Tabel 'tasklist' berhasil dibuat</p>";
    
    // Insert data default
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE absen_id = ?");
    $stmt->execute(['ADMIN001']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (absen_id, nama, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['ADMIN001', 'Admin', md5('password'), 'manager']);
        echo "<p style='color: green;'>✓ Admin user berhasil dibuat</p>";
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE absen_id = ?");
    $stmt->execute(['STAF001']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (absen_id, nama, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['STAF001', 'John Doe', md5('password'), 'staf']);
        echo "<p style='color: green;'>✓ Staf user 1 berhasil dibuat</p>";
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE absen_id = ?");
    $stmt->execute(['STAF002']);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (absen_id, nama, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['STAF002', 'Jane Smith', md5('password'), 'staf']);
        echo "<p style='color: green;'>✓ Staf user 2 berhasil dibuat</p>";
    }
    
    echo "<hr>";
    echo "<h4>Setup selesai! Aplikasi siap digunakan.</h4>";
    echo "<a href='login.php' class='btn btn-success'>Buka Aplikasi</a>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Pastikan MySQL berjalan dan kredensial database benar</p>";
}
?>