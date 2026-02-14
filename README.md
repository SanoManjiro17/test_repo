# Aplikasi Absensi Karyawan

Aplikasi absensi berbasis web yang memungkinkan karyawan untuk melakukan absensi masuk dan keluar, mencatat tasklist harian, serta menghitung durasi kerja secara otomatis.

## Fitur Utama

### 1. Sistem Login
- Login menggunakan Absen ID dan Password
- Role-based access control (Staf dan Manager/Admin)
- Session management yang aman

### 2. Absensi Harian
- Absen masuk dan keluar dengan timestamp otomatis
- Perhitungan durasi kerja otomatis (jam:menit:detik)
- Validasi waktu kerja (contoh: 19:00 - 21:00 = 2 jam)
- Status absensi real-time

### 3. Tasklist Harian
- Input tasklist untuk setiap hari kerja
- Update status task (Pending, Progress, Completed)
- View tasklist oleh semua staf dan manager
- Statistik task harian

### 4. Laporan Rekap
- **Laporan Harian**: 7 hari terakhir
- **Laporan Mingguan**: 4 minggu terakhir dengan perhitungan rata-rata
- **Laporan Bulanan**: 6 bulan terakhir dengan perhitungan rata-rata
- **Laporan Tahunan**: 2 tahun terakhir dengan perhitungan rata-rata

### 5. Dashboard Admin/Manager
- Overview status absensi seluruh staf
- Manajemen data staf
- Akses laporan global
- Detail per-staf dengan statistik lengkap

## Teknologi yang Digunakan

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, Font Awesome
- **Server**: Apache/Nginx (XAMPP direkomendasikan untuk development)

## Instalasi

### 1. Clone/Download Project
```bash
git clone [url-repository]
cd absensi-system
```

### 2. Setup Database
1. Buat database baru di MySQL
2. Import file `database.sql` yang tersedia
3. Atau jalankan query SQL yang ada di file tersebut

### 3. Konfigurasi Database
Edit file `config.php` dan sesuaikan dengan setting database Anda:
```php
$host = 'localhost';
$dbname = 'absensi_system';
$username = 'root'; // sesuaikan
$password = ''; // sesuaikan
```

### 4. Jalankan Aplikasi
1. Pastikan Apache dan MySQL berjalan
2. Akses aplikasi melalui browser: `http://localhost/minven_absensi/`
3. Login dengan akun default:
   - **Admin**: ADMIN001 / password
   - **Staf**: STAF001 / password atau STAF002 / password

## Struktur Database

### Tabel `users`
- `id`: Primary key
- `absen_id`: Unique identifier untuk login
- `nama`: Nama lengkap
- `password`: Password terenkripsi
- `role`: 'staf' atau 'manager'
- `created_at`: Timestamp pendaftaran

### Tabel `absensi`
- `id`: Primary key
- `user_id`: Foreign key ke tabel users
- `tanggal`: Tanggal absensi
- `jam_masuk`: Waktu absen masuk
- `jam_keluar`: Waktu absen keluar
- `durasi_kerja`: Durasi kerja otomatis
- `created_at`: Timestamp pembuatan
- `updated_at`: Timestamp update

### Tabel `tasklist`
- `id`: Primary key
- `user_id`: Foreign key ke tabel users
- `absensi_id`: Foreign key ke tabel absensi
- `task_name`: Nama task
- `deskripsi`: Deskripsi task
- `status`: 'pending', 'progress', 'completed'
- `created_at`: Timestamp pembuatan
- `updated_at`: Timestamp update

## Cara Penggunaan

### Untuk Staf
1. Login dengan Absen ID dan password
2. Klik "Absen Masuk" saat mulai kerja
3. Tambahkan tasklist harian
4. Update status task sesuai progress
5. Klik "Absen Keluar" saat selesai kerja
6. Lihat laporan di menu Laporan

### Untuk Manager/Admin
1. Login dengan akun admin
2. Akses dashboard admin untuk overview
3. Lihat detail setiap staf
4. Monitor tasklist dan progress
5. Generate laporan periode tertentu

## Keamanan

- Password dienkripsi menggunakan bcrypt
- Session management yang aman
- Validasi input pada semua form
- SQL injection prevention dengan prepared statements
- Role-based access control

## Troubleshooting

### Login Gagal
- Pastikan Absen ID dan password benar
- Cek apakah akun sudah terdaftar di database
- Password default adalah "password"

### Database Error
- Pastikan MySQL berjalan
- Cek konfigurasi di `config.php`
- Import ulang database.sql jika perlu

### Durasi Kerja Tidak Muncul
- Pastikan sudah absen keluar
- Cek apakah ada data di tabel absensi
- Periksa fungsi `hitungDurasi()` di `config.php`

## Kontribusi

Silakan fork project ini dan buat pull request untuk kontribusi.

## Lisensi

MIT License - bebas digunakan untuk keperluan pribadi maupun komersial.