<?php
// Konfigurasi Database dengan error handling yang lebih baik
$host = 'localhost';
$dbname = 'absensi_system';
$username = 'root';
$password = '';

define('DB_HOST', $host);
define('DB_NAME', $dbname);
define('DB_USER', $username);
define('DB_PASS', $password);

// Fungsi untuk cek koneksi database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Auto-migration: Pastikan kolom database lengkap
    try {
        // 1. Cek & Tambah kolom di tabel users
        $check_users = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
        $needed_users = [
            'nip' => "VARCHAR(30) AFTER nama",
            'email' => "VARCHAR(100) AFTER absen_id",
            'telepon' => "VARCHAR(20) AFTER email",
            'alamat' => "TEXT AFTER telepon",
            'foto' => "VARCHAR(255) AFTER role"
        ];
        foreach ($needed_users as $col => $def) {
            if (!in_array($col, $check_users)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
        }

        // 2. Cek & Tambah kolom di tabel absensi
        $check_absensi = $pdo->query("DESCRIBE absensi")->fetchAll(PDO::FETCH_COLUMN);
        
        // Update Enum tipe_absen
        $stmt = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'tipe_absen'");
        $col_info = $stmt->fetch();
        if ($col_info && strpos($col_info['Type'], "'sakit'") === false) {
            $pdo->exec("ALTER TABLE absensi MODIFY COLUMN tipe_absen ENUM('reguler', 'sakit', 'cuti') DEFAULT 'reguler'");
        }

        // Tambah kolom keterangan & bukti_izin jika belum ada
        if (!in_array('keterangan', $check_absensi)) {
            $pdo->exec("ALTER TABLE absensi ADD COLUMN keterangan TEXT AFTER status_lembur");
        }
        if (!in_array('bukti_izin', $check_absensi)) {
            $pdo->exec("ALTER TABLE absensi ADD COLUMN bukti_izin VARCHAR(255) AFTER keterangan");
        }
    } catch (Exception $e) {
        // Gagal migrasi diam-diam
    }
} catch(PDOException $e) {
    // Jika database belum ada, coba buat koneksi tanpa database
    try {
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e2) {
        // Jika tetap gagal, tampilkan error
        die("
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <h2 style='color: #dc3545;'>Koneksi Database Gagal</h2>
            <p><strong>Error:</strong> " . $e2->getMessage() . "</p>
            <hr>
            <h4>Langkah Penyelesaian:</h4>
            <ol>
                <li>Pastikan MySQL/MariaDB sudah berjalan</li>
                <li>Cek apakah XAMPP sudah dinyalakan</li>
                <li>Verifikasi username dan password database di file config.php</li>
                <li>Klik tombol di bawah untuk setup otomatis:</li>
            </ol>
            <p><a href='setup_database.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Setup Database</a></p>
        </div>
        ");
    }
}

// Set zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Session start dengan konfigurasi keamanan
if (session_status() === PHP_SESSION_NONE) {
    // Konfigurasi session keamanan
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_secure', 0); // Ubah ke 1 jika menggunakan HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Session timeout (30 menit)
    $session_timeout = 1800; // 30 menit dalam detik
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $session_timeout) {
            // Session expired, destroy and redirect to login
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically untuk keamanan
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) {
        // Regenerate session ID setiap 30 menit
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Fungsi untuk menghitung durasi kerja
function hitungDurasi($jam_masuk, $jam_keluar) {
    if (!$jam_keluar) return null;
    
    $masuk = new DateTime($jam_masuk);
    $keluar = new DateTime($jam_keluar);
    
    $durasi = $masuk->diff($keluar);
    
    return $durasi->format('%H:%I:%S');
}

// Fungsi untuk format waktu Indonesia
function formatWaktuIndonesia($datetime) {
    return date('d/m/Y H:i:s', strtotime($datetime));
}

// Fungsi untuk cek login
function cekLogin() {
    // Cek apakah session user_id ada
    if (!isset($_SESSION['user_id'])) {
        // Simpan URL yang sedang diakses untuk redirect setelah login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
    
    // Cek apakah session role ada (untuk keamanan tambahan)
    if (!isset($_SESSION['role'])) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=invalid_session');
        exit();
    }
    
    // Update last activity untuk timeout
    $_SESSION['last_activity'] = time();
}

// Fungsi untuk mendapatkan dashboard URL berdasarkan role
function getDashboardUrl($role) {
    if ($role == 'manager') {
        return 'admin_dashboard.php';
    }
    return 'dashboard.php';
}

// Fungsi untuk cek role admin/manager
function cekAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] != 'manager') {
        header('Location: dashboard.php');
        exit();
    }
}

// Logika Shift Otomatis
try {
    // Auto-migration untuk tabel tasklist
    try {
        $pdo->query("SELECT start_date FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN start_date DATE DEFAULT NULL AFTER absensi_id"); } catch (Exception $e2) {}
    }

    try {
        $pdo->query("SELECT start_time FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN start_time TIME DEFAULT NULL"); } catch (Exception $e2) {}
    }
    try {
        $pdo->query("SELECT end_time FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN end_time TIME DEFAULT NULL"); } catch (Exception $e2) {}
    }
    try {
        $pdo->query("SELECT assigned_to FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN assigned_to INT DEFAULT NULL"); } catch (Exception $e2) {}
    }

    try {
        $pdo->query("SELECT is_overtime FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN is_overtime TINYINT(1) DEFAULT 0"); } catch (Exception $e2) {}
    }

    try {
        $pdo->query("SELECT is_overtime FROM tasklist LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN is_overtime TINYINT(1) DEFAULT 0"); } catch (Exception $e2) {}
    }

    // Auto-migration for users table
    try {
        $pdo->query("SELECT can_overtime FROM users LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE users ADD COLUMN can_overtime TINYINT(1) DEFAULT 0 AFTER role"); } catch (Exception $e2) {}
    }

    // Ambil pengaturan shift
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('shift_status', 'enable_auto_shift', 'auto_open_time', 'auto_close_time')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $shift_status = $settings['shift_status'] ?? 'closed';
    $enable_auto = $settings['enable_auto_shift'] ?? '0';
    $auto_open = $settings['auto_open_time'] ?? '08:00';
    $auto_close = $settings['auto_close_time'] ?? '17:00';
    
    if ($enable_auto == '1') {
        $now = date('H:i');
        $new_status = null;
        
        // Cek apakah waktu sekarang berada dalam rentang shift
        if ($now >= $auto_open && $now < $auto_close) {
            // Jika dalam rentang shift tapi status masih closed atau overtime, buka otomatis
            if ($shift_status == 'closed' || $shift_status == 'overtime') {
                $new_status = 'open';
            }
        } else {
            // Jika di luar rentang shift dan status masih open, ubah ke overtime otomatis
            // Ini akan langsung membuka opsi lembur bagi semua akun setelah check out
            if ($shift_status == 'open') {
                $new_status = 'overtime';
            }
        }
        
        if ($new_status) {
            $stmt_update = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'shift_status'");
            $stmt_update->execute([$new_status]);
            // Refresh variable untuk penggunaan di halaman yang memanggil config ini
            $shift_status = $new_status;
        }
    }
} catch (Exception $e) {
    // Abaikan error jika tabel settings belum siap
}
?>