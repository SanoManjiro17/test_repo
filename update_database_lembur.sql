-- Update database untuk fitur lembur dan multiple absensi
USE absensi_system;

-- Tambahkan kolom lembur ke tabel absensi
ALTER TABLE absensi 
ADD COLUMN jam_mulai_lembur TIME NULL AFTER durasi_kerja,
ADD COLUMN jam_selesai_lembur TIME NULL AFTER jam_mulai_lembur,
ADD COLUMN durasi_lembur VARCHAR(20) NULL AFTER jam_selesai_lembur,
ADD COLUMN tipe_absen ENUM('reguler', 'lembur') DEFAULT 'reguler' AFTER durasi_lembur;

-- Buat tabel absensi_lembur untuk multiple absensi/lembur
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
);

-- Update data existing (set tipe_absen = 'reguler' untuk data lama)
UPDATE absensi SET tipe_absen = 'reguler' WHERE tipe_absen IS NULL;

-- Tambahkan kolom status_lembur untuk tracking lembur aktif
ALTER TABLE absensi 
ADD COLUMN status_lembur ENUM('tidak_lembur', 'sedang_lembur', 'selesai_lembur') DEFAULT 'tidak_lembur' AFTER tipe_absen;

-- Insert sample data lembur untuk testing (optional)
-- INSERT INTO absensi_lembur (user_id, tanggal, jam_mulai, jam_selesai, durasi_lembur, keterangan) 
-- VALUES (2, CURDATE(), '19:00:00', '21:00:00', '02:00:00', 'Lembur project deadline');