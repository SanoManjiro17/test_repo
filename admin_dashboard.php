<?php
require_once 'config_safe.php';
cekLogin();
cekAdmin();

$user_id = $_SESSION['user_id'];

// Ambil data user untuk foto profil
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// Pastikan database sesuai dengan kebutuhan sistem
try {
    // 1. Periksa dan tambahkan kolom ke tabel users jika belum ada
    $check_users = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
    $needed_columns = [
        'nip' => "VARCHAR(30) AFTER nama",
        'email' => "VARCHAR(100) AFTER absen_id",
        'telepon' => "VARCHAR(20) AFTER email",
        'alamat' => "TEXT AFTER telepon",
        'foto' => "VARCHAR(255) AFTER role"
    ];
    
    foreach ($needed_columns as $col => $definition) {
        if (!in_array($col, $check_users)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col $definition");
        }
    }

    // 2. Periksa dan update enum tipe_absen di tabel absensi serta tambahkan kolom pendukung
    $stmt = $pdo->query("SHOW COLUMNS FROM absensi LIKE 'tipe_absen'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], "'sakit'") === false) {
        $pdo->exec("ALTER TABLE absensi MODIFY COLUMN tipe_absen ENUM('reguler', 'lembur', 'sakit', 'cuti') DEFAULT 'reguler'");
    }

    // Tambahkan kolom keterangan dan bukti_izin jika belum ada
    $check_absensi = $pdo->query("DESCRIBE absensi")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('keterangan', $check_absensi)) {
        $pdo->exec("ALTER TABLE absensi ADD COLUMN keterangan TEXT AFTER tipe_absen");
    }
    if (!in_array('bukti_izin', $check_absensi)) {
        $pdo->exec("ALTER TABLE absensi ADD COLUMN bukti_izin VARCHAR(255) AFTER keterangan");
    }
} catch (Exception $e) {
    // Abaikan jika error saat migrasi ringan
}

// Handler untuk Pembuatan Akun Baru
if (isset($_POST['create_user'])) {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $absen_id = $_POST['absen_id'];
    $email = $_POST['email'];
    $telepon = $_POST['telepon'];
    $alamat = $_POST['alamat'];
    $password_input = $_POST['password'];
    $role = $_POST['role'];

    if (empty($nama) || empty($nip) || empty($absen_id) || empty($email) || empty($telepon) || empty($alamat) || empty($password_input)) {
        $_SESSION['error'] = "Semua kolom wajib diisi!";
        header("Location: admin_dashboard.php");
        exit();
    }
    if (strlen($password_input) < 6) {
        $_SESSION['error'] = "Password minimal harus 6 karakter!";
        header("Location: admin_dashboard.php");
        exit();
    }
    // Menggunakan MD5 untuk enkripsi password sesuai instruksi
    $password = md5($password_input);
    $role = $_POST['role'];

    try {
        // Cek apakah absen_id sudah ada
        $check = $pdo->prepare("SELECT id FROM users WHERE absen_id = ?");
        $check->execute([$absen_id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "ID Login sudah digunakan!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (nama, nip, absen_id, email, telepon, alamat, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $nip, $absen_id, $email, $telepon, $alamat, $password, $role]);
            $_SESSION['msg'] = "Akun baru berhasil dibuat untuk: " . $nama;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal membuat akun: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Hapus Akun
if (isset($_POST['delete_user'])) {
    $target_id = $_POST['target_id'];
    if ($target_id == $user_id) {
        $_SESSION['error'] = "Anda tidak bisa menghapus akun sendiri!";
    } else {
        try {
            $pdo->beginTransaction();
            // Hapus data terkait terlebih dahulu karena kendala foreign key
            $pdo->prepare("DELETE FROM absensi_lembur WHERE user_id = ?")->execute([$target_id]);
            $pdo->prepare("DELETE FROM tasklist WHERE user_id = ? OR assigned_to = ?")->execute([$target_id, $target_id]);
            $pdo->prepare("DELETE FROM absensi WHERE user_id = ?")->execute([$target_id]);
            
            // Hapus user
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
            $pdo->commit();
            $_SESSION['msg'] = "Akun karyawan berhasil dihapus secara permanen.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Gagal menghapus akun: " . $e->getMessage();
        }
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Pembuatan Tugas (Terintegrasi dengan To Do List)
if (isset($_POST['create_task'])) {
    $task_name = $_POST['task_name'];
    $deskripsi = $_POST['deskripsi'] ?? '';
    $deadline = $_POST['deadline'] ?? date('Y-m-d');
    $priority = $_POST['priority'] ?? 'medium';
    $is_overtime = isset($_POST['is_overtime']) ? 1 : 0;
    $created_by = $_SESSION['user_id'];
    
    try {
        if (!empty($_POST['assigned_users'])) {
            $assigned_users = (array)$_POST['assigned_users'];
            
            foreach ($assigned_users as $target_id) {
                // Cek absensi target pada tanggal tersebut
                $stmt = $pdo->prepare("SELECT id FROM absensi WHERE user_id = ? AND tanggal = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$target_id, $deadline]);
                $target_absensi = $stmt->fetch();
                $target_absensi_id = $target_absensi ? $target_absensi['id'] : null;

                $stmt = $pdo->prepare("INSERT INTO tasklist (user_id, assigned_to, absensi_id, task_name, deskripsi, deadline, priority, is_overtime, status) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$created_by, $target_id, $target_absensi_id, $task_name, $deskripsi, $deadline, $priority, $is_overtime]);
            }
            $_SESSION['msg'] = "Tugas berhasil diberikan kepada " . count($assigned_users) . " staf.";
        } else {
            $_SESSION['error'] = "Pilih minimal satu staf untuk ditugaskan.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal membuat tugas: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Kontrol Shift Global
if (isset($_POST['update_shift'])) {
    $new_status = $_POST['shift_status'];
    try {
        // Jika sedang mode manual, biarkan manager mengubah status
        // Jika sedang mode otomatis, matikan mode otomatis dulu sebelum mengubah status manual
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = '0' WHERE setting_key = 'enable_auto_shift'");
        $stmt->execute();
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('shift_status', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_status, $new_status]);
        $_SESSION['msg'] = "Mode Manual Aktif: Status shift diubah menjadi " . strtoupper($new_status);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal mengubah status shift.";
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Pengaturan Shift Otomatis
if (isset($_POST['save_shift_settings'])) {
    $auto_open = $_POST['auto_open_time'];
    $auto_close = $_POST['auto_close_time'];
    $is_auto = isset($_POST['enable_auto_shift']) ? '1' : '0';

    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_open_time', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$auto_open, $auto_open]);
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('auto_close_time', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$auto_close, $auto_close]);

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('enable_auto_shift', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$is_auto, $is_auto]);

        $_SESSION['msg'] = $is_auto == '1' ? "Mode Otomatis Aktif: Shift akan mengikuti jadwal." : "Pengaturan shift disimpan.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menyimpan pengaturan shift.";
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Hapus Tugas (Global)
if (isset($_POST['delete_task_global'])) {
    $task_id = $_POST['task_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tasklist WHERE id = ?");
        $stmt->execute([$task_id]);
        $_SESSION['msg'] = "Tugas berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus tugas: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Handler untuk Review/Selesaikan Tugas (Global)
if (isset($_POST['review_task_global'])) {
    $task_id = $_POST['task_id'];
    try {
        $stmt = $pdo->prepare("UPDATE tasklist SET status = 'completed' WHERE id = ?");
        $stmt->execute([$task_id]);
        $_SESSION['msg'] = "Tugas telah ditandai sebagai selesai.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui status tugas: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit();
}

// Ambil pengaturan shift (sudah ada $shift_status dari config_safe.php)
$tanggal_sekarang = date('Y-m-d');
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('auto_open_time', 'auto_close_time', 'enable_auto_shift')");
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$auto_open_time = $settings_raw['auto_open_time'] ?? '08:00';
$auto_close_time = $settings_raw['auto_close_time'] ?? '17:00';
$enable_auto_shift = $settings_raw['enable_auto_shift'] ?? '0';

// Handler untuk Aksi Cepat Manager (Check-in, Check-out, Reset)
if (isset($_POST['quick_action'])) {
    $target_user_id = $_POST['user_id'];
    $action = $_POST['action'];
    $tanggal_sekarang = date('Y-m-d');
    $jam_sekarang = date('H:i');

    try {
        if ($action == 'checkin') {
            $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk) VALUES (?, ?, ?)");
            $stmt->execute([$target_user_id, $tanggal_sekarang, $jam_sekarang]);
            $_SESSION['msg'] = "Berhasil melakukan check-in paksa.";
        } 
        elseif ($action == 'checkout') {
            $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = TIMEDIFF(?, jam_masuk) WHERE user_id = ? AND tanggal = ?");
            $stmt->execute([$jam_sekarang, $jam_sekarang, $target_user_id, $tanggal_sekarang]);
            $_SESSION['msg'] = "Berhasil melakukan check-out paksa.";
        }
        elseif ($action == 'reset') {
            // Hapus data absensi hari ini untuk user tersebut
            $stmt = $pdo->prepare("DELETE FROM absensi WHERE user_id = ? AND tanggal = ?");
            $stmt->execute([$target_user_id, $tanggal_sekarang]);
            $_SESSION['msg'] = "Data absensi hari ini berhasil direset.";
        }
        elseif ($action == 'overtime_force') {
            // Cek apakah sudah lembur hari ini
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM absensi_lembur WHERE user_id = ? AND tanggal = ?");
            $stmt_check->execute([$target_user_id, $tanggal_sekarang]);
            if ($stmt_check->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO absensi_lembur (user_id, tanggal, jam_mulai) VALUES (?, ?, ?)");
                $stmt->execute([$target_user_id, $tanggal_sekarang, $jam_sekarang]);
                $_SESSION['msg'] = "Berhasil mengaktifkan lembur paksa untuk staf.";
            } else {
                $_SESSION['error'] = "Staf sudah dalam status lembur hari ini.";
            }
        }
        header("Location: admin_dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memproses permintaan: " . $e->getMessage();
    }
}

// Ambil data semua staf
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'staf' ORDER BY nama ASC");
$stmt->execute();
$staf_list = $stmt->fetchAll();

// Ambil statistik lembur hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi_lembur WHERE tanggal = ?");
$stmt->execute([$tanggal_sekarang]);
$staf_lembur = $stmt->fetchColumn();

// Ambil data absensi hari ini untuk semua staf (termasuk status lembur)
$stmt = $pdo->prepare("
    SELECT 
        u.id, u.nama, u.absen_id,
        a.jam_masuk, a.jam_keluar, a.durasi_kerja, a.tipe_absen, a.keterangan, a.bukti_izin,
        l.jam_mulai as lembur_mulai, l.jam_selesai as lembur_selesai,
        COUNT(t.id) as total_task,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as task_selesai
    FROM users u
    LEFT JOIN absensi a ON u.id = a.user_id AND a.tanggal = ?
    LEFT JOIN absensi_lembur l ON u.id = l.user_id AND l.tanggal = ?
    LEFT JOIN tasklist t ON a.id = t.absensi_id
    WHERE u.role = 'staf'
    GROUP BY u.id, a.id, l.id
    ORDER BY u.nama ASC
");
$stmt->execute([$tanggal_sekarang, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetchAll();

// Hitung statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'staf'");
$stmt->execute();
$total_staf = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as hadir FROM absensi WHERE tanggal = ? AND jam_masuk IS NOT NULL AND tipe_absen NOT IN ('sakit', 'cuti')");
$stmt->execute([$tanggal_sekarang]);
$staf_hadir = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as izin FROM absensi WHERE tanggal = ? AND tipe_absen IN ('sakit', 'cuti')");
$stmt->execute([$tanggal_sekarang]);
$staf_izin = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as selesai FROM absensi WHERE tanggal = ? AND jam_keluar IS NOT NULL");
$stmt->execute([$tanggal_sekarang]);
// Ambil statistik tugas hari ini
$stmt = $pdo->prepare("
    SELECT 
        COUNT(t.id) as total_task,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as task_selesai,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as task_proses
    FROM tasklist t
    JOIN absensi a ON t.absensi_id = a.id
    WHERE a.tanggal = ?
");
$stmt->execute([$tanggal_sekarang]);
$task_stats = $stmt->fetch();

// Ambil aktivitas terbaru
$stmt = $pdo->prepare("
    (SELECT u.id as user_id, u.nama, a.tipe_absen as tipe, a.jam_masuk as waktu, a.tanggal 
     FROM absensi a JOIN users u ON a.user_id = u.id 
     WHERE a.tanggal = ? AND a.tipe_absen IN ('reguler', 'sakit', 'cuti') AND a.jam_masuk IS NOT NULL
     ORDER BY a.jam_masuk DESC LIMIT 10)
    UNION ALL
    (SELECT u.id as user_id, u.nama, 'checkout' as tipe, a.jam_keluar as waktu, a.tanggal 
     FROM absensi a JOIN users u ON a.user_id = u.id 
     WHERE a.tanggal = ? AND a.jam_keluar IS NOT NULL 
     ORDER BY a.jam_keluar DESC LIMIT 10)
    UNION ALL
    (SELECT u.id as user_id, u.nama, 'lembur_mulai' as tipe, l.jam_mulai as waktu, l.tanggal 
     FROM absensi_lembur l JOIN users u ON l.user_id = u.id 
     WHERE l.tanggal = ? AND l.jam_mulai IS NOT NULL 
     ORDER BY l.jam_mulai DESC LIMIT 10)
    UNION ALL
    (SELECT u.id as user_id, u.nama, 'lembur_selesai' as tipe, l.jam_selesai as waktu, l.tanggal 
     FROM absensi_lembur l JOIN users u ON l.user_id = u.id 
     WHERE l.tanggal = ? AND l.jam_selesai IS NOT NULL 
     ORDER BY l.jam_selesai DESC LIMIT 10)
    ORDER BY waktu DESC LIMIT 15
");
$stmt->execute([$tanggal_sekarang, $tanggal_sekarang, $tanggal_sekarang, $tanggal_sekarang]);
$recent_activities = $stmt->fetchAll();

// Pastikan kolom tambahan ada di tabel tasklist
try {
    $pdo->query("SELECT priority FROM tasklist LIMIT 1");
} catch (Exception $e) {
    try { $pdo->exec("ALTER TABLE tasklist ADD COLUMN priority ENUM('low', 'medium', 'high') DEFAULT 'medium'"); } catch (Exception $e2) {}
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

// Ambil data tugas penting (Global untuk manager)
$stmt = $pdo->query("
    SELECT t.*, u_target.nama as penerima, u_maker.nama as pembuat 
    FROM tasklist t 
    JOIN users u_target ON t.assigned_to = u_target.id 
    JOIN users u_maker ON t.user_id = u_maker.id 
    WHERE t.status != 'completed'
    ORDER BY t.deadline ASC 
    LIMIT 5
");


$tasks_global = $stmt->fetchAll();

// Ambil riwayat tugas selesai terbaru (Global untuk manager)
$stmt = $pdo->query("
    SELECT t.*, u_target.nama as penerima, u_maker.nama as pembuat 
    FROM tasklist t 
    JOIN users u_target ON t.assigned_to = u_target.id 
    JOIN users u_maker ON t.user_id = u_maker.id 
    WHERE t.status = 'completed'
    ORDER BY t.updated_at DESC
");
$tasks_selesai_history = $stmt->fetchAll();

// Ambil riwayat lembur staf terbaru
$stmt = $pdo->query("
    SELECT l.*, u.nama 
    FROM absensi_lembur l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.tanggal DESC, l.jam_mulai DESC 
    LIMIT 5
");
$lembur_global = $stmt->fetchAll();

// Ambil daftar staf yang belum absen hari ini
$stmt = $pdo->prepare("
    SELECT nama FROM users 
    WHERE role = 'staf' 
    AND id NOT IN (SELECT user_id FROM absensi WHERE tanggal = ?)
    ORDER BY nama ASC
");
$stmt->execute([$tanggal_sekarang]);
$staf_belum_absen_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
$staf_belum_absen_names = implode(', ', $staf_belum_absen_list);

// Ambil data detail staf yang belum absen untuk modal
$stmt = $pdo->prepare("
    SELECT nama, absen_id FROM users 
    WHERE role = 'staf' 
    AND id NOT IN (SELECT user_id FROM absensi WHERE tanggal = ?)
    ORDER BY nama ASC
");
$stmt->execute([$tanggal_sekarang]);
$staf_belum_absen_details = $stmt->fetchAll();

// Ambil data detail untuk modal-modal lainnya
// 1. Total Staf
$stmt = $pdo->query("SELECT id, nama, absen_id, role FROM users WHERE role = 'staf' ORDER BY nama ASC");
$staf_total_details = $stmt->fetchAll();

// 2. Hadir Hari Ini
$stmt = $pdo->prepare("
    SELECT u.nama, u.absen_id, a.jam_masuk 
    FROM users u 
    JOIN absensi a ON u.id = a.user_id 
    WHERE a.tanggal = ? AND a.jam_masuk IS NOT NULL AND a.tipe_absen = 'reguler'
    ORDER BY a.jam_masuk DESC
");
$stmt->execute([$tanggal_sekarang]);
$staf_hadir_details = $stmt->fetchAll();

// 3. Tugas Selesai (Detail tugas yang sudah selesai hari ini)
$stmt = $pdo->prepare("
    SELECT t.task_name, u.nama as penerima, t.updated_at
    FROM tasklist t
    JOIN users u ON t.assigned_to = u.id
    JOIN absensi a ON t.absensi_id = a.id
    WHERE a.tanggal = ? AND t.status = 'completed'
    ORDER BY t.updated_at DESC
");
$stmt->execute([$tanggal_sekarang]);
$task_selesai_details = $stmt->fetchAll();

// 4. Staf Izin/Sakit
$stmt = $pdo->prepare("
    SELECT u.nama, u.absen_id, a.tipe_absen, a.keterangan, a.bukti_izin
    FROM users u 
    JOIN absensi a ON u.id = a.user_id 
    WHERE a.tanggal = ? AND a.tipe_absen IN ('sakit', 'cuti')
    ORDER BY a.jam_masuk DESC
");
$stmt->execute([$tanggal_sekarang]);
$staf_izin_details = $stmt->fetchAll();

// 5. Staf Lembur
$stmt = $pdo->prepare("
    SELECT u.nama, u.absen_id, l.jam_mulai 
    FROM users u 
    JOIN absensi_lembur l ON u.id = l.user_id 
    WHERE l.tanggal = ?
    ORDER BY l.jam_mulai DESC
");
$stmt->execute([$tanggal_sekarang]);
$staf_lembur_details = $stmt->fetchAll();

function getStatusBadge($status, $deadline = null) {
    if ($status == 'completed') {
        return '<span class="badge bg-soft-success text-success px-3">SELESAI</span>';
    }
    
    if ($deadline) {
        $today = new DateTime(date('Y-m-d'));
        $target = new DateTime($deadline);
        if ($today > $target) {
            return '<span class="badge bg-soft-danger text-danger px-3">TERLAMBAT</span>';
        }
    }
    
    return '<span class="badge bg-soft-info text-info px-3">PROSES</span>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Admin - Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #004AAD;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --accent-color: #8b5cf6;
            --light-bg: #f8fafc;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --card-radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            display: flex;
            color: #1e293b;
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar Styling Modern */
        .sidebar {
            width: var(--sidebar-width);
            background: #004AAD;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .profile-section {
            padding: 35px 25px;
            background: rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.25);
            margin-bottom: 15px;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .sidebar-avatar:hover {
            transform: scale(1.05);
            border-color: rgba(255,255,255,0.5);
        }

        .profile-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
            color: white;
        }

        .profile-role {
            font-size: 11px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            letter-spacing: 1.5px;
            margin-top: 5px;
            display: block;
            font-weight: 600;
        }

        .nav-menu {
            padding: 25px 0;
            flex-grow: 1;
        }

        .nav-item {
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            margin: 4px 15px;
            border-radius: 12px;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: white !important;
            color: var(--primary-color) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: fixed;
            left: 255px;
            top: 20px;
            z-index: 1001;
            background: white;
            color: var(--primary-color);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-toggle:hover {
            transform: scale(1.1) rotate(180deg);
            background: var(--primary-color);
            color: white;
        }

        .sidebar-toggle.collapsed {
            left: 20px;
        }

        /* Sidebar Hidden State */
        .sidebar.hidden {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Main Content Adjustments */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex-grow: 1; 
            padding: 40px 50px;
            max-width: calc(100vw - var(--sidebar-width));
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
            max-width: 100vw;
        }

        /* Mobile & Tablet Responsiveness */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
                padding-top: 80px !important;
                max-width: 100% !important;
            }
            
            .sidebar-toggle {
                left: 15px !important;
                top: 15px !important;
                display: flex !important;
            }
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-title {
            color: var(--primary-color); 
            font-weight: 800; 
            margin: 0;
            font-size: 28px;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .shift-control-panel {
            background: white;
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .content-card {
            background: white; 
            border: none; 
            border-radius: var(--card-radius); 
            overflow: hidden; 
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }

        .card-header-custom { 
            padding: 20px 25px; 
            border-bottom: 1px solid rgba(0,0,0,0.05); 
            background: white; 
        }

        .card-header-custom h5 { 
            margin: 0; 
            color: var(--primary-color); 
            font-weight: 700; 
            font-size: 18px;
        }

        .stat-card {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            padding: 25px;
            text-align: left;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
        }

        .stat-card i { 
            font-size: 32px; 
            padding: 15px;
            border-radius: 12px;
            background: rgba(0,74,173,0.05);
            color: var(--primary-color);
        }

        .stat-card h4 { 
            color: var(--primary-color); 
            font-weight: 800; 
            margin-bottom: 2px;
            font-size: 24px;
        }

        .stat-card p { 
            color: #888; 
            font-size: 11px; 
            margin: 0; 
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .table-custom { 
            margin-bottom: 0; 
        }

        .table-custom thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
        }

        .table-custom tbody td {
            padding: 16px 24px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
            transition: all 0.2s;
        }

        .table-custom tbody tr:hover td {
            background-color: #f1f5f9;
            color: #004AAD;
        }

        .badge {
            padding: 6px 12px;
            font-weight: 600;
            font-size: 10px;
            border-radius: 6px;
            letter-spacing: 0.3px;
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-wrapper .form-control {
            padding-left: 40px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-wrapper .form-control:focus {
            box-shadow: 0 0 0 4px rgba(0, 74, 173, 0.1);
            border-color: #004AAD;
        }

        .badge-custom {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .avatar-sm {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-action {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 12px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Mobile & Tablet Responsiveness */
        @media (max-width: 991.98px) {
            :root {
                --sidebar-width: 280px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1050;
                display: flex !important;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
                padding-top: 80px !important;
                width: 100% !important;
                max-width: 100% !important;
                transition: all 0.3s ease;
            }
            
            .sidebar-toggle {
                left: 15px !important;
                top: 15px !important;
                width: 45px;
                height: 45px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1060;
                display: flex !important;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .page-title {
                font-size: 22px;
            }

            .shift-control-panel {
                width: 100%;
                justify-content: space-between;
                border-radius: 12px;
                padding: 12px;
            }

            .stat-card {
                padding: 15px;
                gap: 12px;
            }

            .stat-card i {
                font-size: 24px;
                padding: 10px;
            }

            .stat-card h4 {
                font-size: 20px;
            }

            .row.g-3.mb-5 .col {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .content-card {
                padding: 0 !important;
                border-radius: 12px;
            }

            .nav-item {
                padding: 16px 20px;
                font-size: 15px;
            }

            .btn, .btn-action {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Modal Responsiveness */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-content {
                border-radius: 12px;
            }

            /* Table responsiveness */
            .table-responsive {
                border: 0;
            }
            
            .table-custom tbody td {
                padding: 12px 15px;
                font-size: 13px;
                white-space: nowrap;
            }
        }

        /* iOS Specific Smooth Scrolling */
        .sidebar, .main-content, .table-scroll, .modal-body, .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="profile-section">
            <?php
            $sidebar_foto = (isset($user['foto']) && $user['foto']) ? 'uploads/profiles/'.$user['foto'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['nama']).'&background=fff&color=004AAD&size=128';
            ?>
            <img src="<?php echo $sidebar_foto; ?>" class="sidebar-avatar" alt="Profile">
            <p class="profile-name"><?php echo strtolower(explode(' ', $_SESSION['nama'])[0]); ?></p>
            <span class="profile-role"><?php echo strtoupper($_SESSION['role']); ?></span>
        </div>
        
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item active">
                <i class="fas fa-th-large"></i> Management Admin
            </a>
            
            <a href="laporan.php" class="nav-item">
                <i class="fas fa-file-invoice"></i> Laporan
            </a>

             <a href="tasklist.php" class="nav-item">
                <i class="fas fa-clipboard-check"></i> Tugas
            </a>
            
            <a href="staf_detail.php?view=list" class="nav-item">
                <i class="fas fa-user-friends"></i> Staff
            </a>
            
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i> Profil
            </a>
        </nav>
        
        <div class="mt-auto p-4 border-top border-white-10">
            <a href="logout.php" class="nav-item m-0 rounded text-danger bg-danger-light" onclick="return confirm('Apakah Anda yakin ingin keluar dari aplikasi?')">
                <i class="fas fa-power-off"></i> Log-Out
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2 class="page-title">Pusat Manajemen</h2>
            
            <div class="shift-control-panel d-flex align-items-center gap-3">
                <div class="pe-3 border-end">
                    <span class="badge <?php echo $enable_auto_shift == '1' ? 'bg-info' : 'bg-secondary'; ?> rounded-pill" style="font-size: 10px;">
                        <i class="fas <?php echo $enable_auto_shift == '1' ? 'fa-robot' : 'fa-hand-paper'; ?> me-1"></i>
                        <?php echo $enable_auto_shift == '1' ? 'OTOMATIS' : 'MANUAL'; ?>
                    </span>
                </div>
                
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="update_shift" value="1">
                    <div class="btn-group btn-group-sm">
                        <button type="submit" name="shift_status" value="open" class="btn <?php echo ($shift_status == 'open' && $enable_auto_shift == '0') ? 'btn-success' : 'btn-outline-success'; ?> border-0 rounded-pill px-3">
                            <i class="fas fa-play me-1"></i> BUKA
                        </button>
                        <button type="submit" name="shift_status" value="overtime" class="btn <?php echo ($shift_status == 'overtime' && $enable_auto_shift == '0') ? 'btn-warning' : 'btn-outline-warning'; ?> border-0 rounded-pill px-3">
                            <i class="fas fa-moon me-1"></i> LEMBUR
                        </button>
                        <button type="submit" name="shift_status" value="closed" class="btn <?php echo ($shift_status == 'closed' && $enable_auto_shift == '0') ? 'btn-danger' : 'btn-outline-danger'; ?> border-0 rounded-pill px-3">
                            <i class="fas fa-stop me-1"></i> TUTUP
                        </button>
                    </div>
                </form>

                <div class="d-flex gap-2 ms-2">
                    <button class="btn btn-sm btn-primary rounded-circle" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#userModal" title="Tambah Karyawan">
                        <i class="fas fa-user-plus"></i>
                    <!--
                    <div>line 895 </div>
                    <button class="btn btn-sm btn-secondary rounded-circle" style="width: 32px; height: 32px; padding: 0;" data-bs-toggle="modal" data-bs-target="#shiftSettingsModal" title="Pengaturan">
                        <i class="fas fa-cog"></i>
                    </button>
                    <div>line 895 </div>
    -->
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-5">
            <div class="col">
                <div class="stat-card" data-bs-toggle="modal" data-bs-target="#totalStafModal" style="cursor: pointer;">
                    <i class="fas fa-users"></i>
                    <div>
                        <h4><?php echo $total_staf; ?></h4>
                        <p>Total Staff</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" data-bs-toggle="modal" data-bs-target="#hadirHariIniModal" style="cursor: pointer;">
                    <i class="fas fa-user-check" style="color: var(--success-color); background: rgba(46,204,113,0.1);"></i>
                    <div>
                        <h4 style="color: var(--success-color);"><?php echo $staf_hadir; ?></h4>
                        <p>Hadir</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" data-bs-toggle="modal" data-bs-target="#tugasSelesaiModal" style="cursor: pointer;">
                    <i class="fas fa-tasks" style="color: var(--info-color); background: rgba(52,152,219,0.1);"></i>
                    <div>
                        <h4 style="color: var(--info-color);"><?php echo $task_stats['task_selesai'] ?? 0; ?></h4>
                        <p>Tugas Selesai</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" data-bs-toggle="modal" data-bs-target="#stafIzinModal" style="cursor: pointer;">
                    <i class="fas fa-calendar-times" style="color: var(--danger-color); background: rgba(231,76,60,0.1);"></i>
                    <div>
                        <h4 style="color: var(--danger-color);"><?php echo $staf_izin; ?></h4>
                        <p>Izin/Sakit</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" data-bs-toggle="modal" data-bs-target="#stafLemburModal" style="cursor: pointer;">
                    <i class="fas fa-clock" style="color: var(--accent-color); background: rgba(241,196,15,0.1);"></i>
                    <div>
                        <h4 style="color: var(--accent-color);"><?php echo $staf_lembur; ?></h4>
                        <p>Lembur</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Kolom Tugas Penting (Global) -->
            <div class="col-md-7">
                <div class="content-card h-100">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Tugas Prioritas</h5>
                        <a href="tasklist.php" class="btn btn-sm btn-outline-primary rounded-pill px-3" style="font-size: 11px; font-weight: 700;">LIHAT SEMUA</a>
                    </div>
                    <div class="table-responsive table-scroll">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Penerima</th>
                                    <th>Nama Tugas</th>
                                    <th>Tenggat</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks_global)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">Tidak ada tugas aktif</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tasks_global as $task): ?>
                                        <tr>
                                            <td>
                                                <a href="staf_detail.php?user_id=<?php echo $task['assigned_to']; ?>" class="text-decoration-none text-dark d-flex align-items-center">
                                                    <div class="avatar-sm me-2 bg-light d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 11px; border-radius: 8px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                    <span class="fw-600 hover-primary"><?php echo htmlspecialchars($task['penerima']); ?></span>
                                                </a>
                                            </td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($task['task_name']); ?></td>
                                            <td><span class="badge bg-light text-muted fw-normal px-2"><?php echo date('d M', strtotime($task['deadline'])); ?></span></td>
                                            <td><?php echo getStatusBadge($task['status'], $task['deadline']); ?></td>
                                            <td>
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <button type="submit" name="review_task_global" class="btn btn-action btn-success text-white" title="Tandai Selesai">
                                                            <i class="fas fa-check" style="font-size: 10px;"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus tugas ini?')">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <button type="submit" name="delete_task_global" class="btn btn-action btn-danger text-white" title="Hapus">
                                                            <i class="fas fa-trash-alt" style="font-size: 10px;"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Kolom Aktivitas Lembur (Global) -->
            <div class="col-md-5">
                <div class="content-card h-100">
                    <div class="card-header-custom">
                        <h5 class="mb-0">Lembur Terbaru</h5>
                    </div>
                   <div class="table-responsive table-scroll">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Durasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lembur_global)): ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted">Tidak ada data lembur hari ini</td></tr>
                                <?php else: ?>
                                    <?php foreach ($lembur_global as $lg): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <a href="staf_detail.php?user_id=<?php echo $lg['user_id']; ?>" class="text-decoration-none text-dark hover-primary">
                                                    <?php echo htmlspecialchars($lg['nama']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($lg['tanggal'])); ?></td>
                                            <td class="text-end">
                                                <?php if ($lg['durasi_lembur']): ?>
                                                    <span class="badge bg-soft-primary text-primary rounded-pill px-3"><?php echo $lg['durasi_lembur']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark rounded-pill px-3">Berjalan</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Riwayat Tugas Selesai -->
            <div class="col-12">
                <div class="content-card">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Riwayat Tugas Selesai</h5>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalSemuaTugasSelesai" style="font-size: 11px; font-weight: 700;">LIHAT SEMUA</button>
                    </div>
                    <div class="table-responsive table-scroll" style="max-height: 300px;">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Penerima</th>
                                    <th>Nama Tugas</th>
                                    <th>Deadline</th>
                                    <th>Selesai Pada</th>
                                    <th>Pemberi Tugas</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks_selesai_history)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat tugas selesai</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $tasks_selesai_display = array_slice($tasks_selesai_history, 0, 10);
                                    foreach ($tasks_selesai_display as $th): 
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-600 text-dark"><?php echo htmlspecialchars($th['penerima']); ?></div>
                                            </td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($th['task_name']); ?></td>
                                            <td><span class="text-muted small"><?php echo date('d M Y', strtotime($th['deadline'])); ?></span></td>
                                            <td><span class="text-muted small"><?php echo date('d M Y, H:i', strtotime($th['updated_at'])); ?></span></td>
                                            <td><span class="text-secondary small"><?php echo htmlspecialchars($th['pembuat']); ?></span></td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-success text-success px-3">Selesai</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5>Status Kehadiran Harian</h5>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small"><?php echo date('l, d F Y'); ?></span>
                        </div>
                    </div>
                  <div class="table-responsive table-scroll">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Status</th>
                                    <th>Masuk</th>
                                    <th>Keluar</th>
                                    <th class="text-center">Tugas</th>
                                    <th class="text-center">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <tbody>
                                    <?php 
                                    foreach ($absensi_hari_ini as $staf): 
                                    ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle-sm me-3 bg-soft-primary d-flex align-items-center justify-content-center text-primary fw-600" style="width: 36px; height: 36px; border-radius: 10px; font-size: 14px;">
                                                <?php echo strtoupper(substr($staf['nama'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="staf_detail.php?user_id=<?php echo $staf['id']; ?>" class="text-decoration-none">
                                                    <div class="fw-600 text-dark hover-primary" style="font-size: 14px; line-height: 1.2;"><?php echo htmlspecialchars($staf['nama']); ?></div>
                                                </a>
                                                <small class="text-muted" style="font-size: 11px;"><?php echo $staf['absen_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($staf['tipe_absen'] == 'sakit'): ?>
                                            <span class="badge bg-soft-danger text-danger px-3">Sakit</span>
                                        <?php elseif ($staf['tipe_absen'] == 'cuti'): ?>
                                            <span class="badge bg-soft-warning text-warning px-3">Izin</span>
                                        <?php elseif ($staf['lembur_mulai']): ?>
                                            <span class="badge bg-soft-primary text-primary px-3">Lembur</span>
                                        <?php elseif ($staf['jam_keluar']): ?>
                                            <span class="badge bg-soft-success text-success px-3">Selesai</span>
                                        <?php elseif ($staf['jam_masuk']): ?>
                                            <span class="badge bg-soft-success text-success px-3">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted px-3">Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staf['tipe_absen'] == 'sakit' || $staf['tipe_absen'] == 'cuti'): ?>
                                            <span class="text-muted small"><?php echo htmlspecialchars($staf['keterangan'] ?: '-'); ?></span>
                                        <?php else: ?>
                                            <span class="small text-dark fw-500"><i class="far fa-clock text-primary me-1"></i> <?php echo $staf['jam_masuk'] ?: '--:--'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="small text-dark fw-500"><i class="far fa-clock text-danger me-1"></i> <?php echo $staf['jam_keluar'] ?: '--:--'; ?></span></td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <div class="progress" style="height: 6px; width: 50px; border-radius: 10px; background-color: #f0f0f0;">
                                                <?php 
                                                $percent = ($staf['total_task'] > 0) ? ($staf['task_selesai'] / $staf['total_task'] * 100) : 0;
                                                ?>
                                                <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                            <small class="text-muted fw-bold" style="font-size: 11px;"><?php echo $staf['task_selesai']; ?>/<?php echo $staf['total_task']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="staf_detail.php?user_id=<?php echo $staf['id']; ?>" class="btn btn-action btn-light border" title="Details">
                                                <i class="fas fa-eye text-primary"></i>
                                            </a>
                                            <?php if (!$staf['jam_masuk']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $staf['id']; ?>">
                                                    <input type="hidden" name="action" value="checkin">
                                                    <button type="submit" name="quick_action" class="btn btn-action btn-success text-white" title="Force Check-in">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <?php if (!$staf['jam_keluar']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $staf['id']; ?>">
                                                        <input type="hidden" name="action" value="checkout">
                                                        <button type="submit" name="quick_action" class="btn btn-action btn-warning text-dark" title="Force Check-out">
                                                            <i class="fas fa-sign-out-alt"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($staf['jam_keluar'] && !$staf['lembur_mulai']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $staf['id']; ?>">
                                                        <input type="hidden" name="action" value="overtime_force">
                                                        <button type="submit" name="quick_action" class="btn btn-action btn-primary text-white" title="Force Overtime">
                                                            <i class="fas fa-moon"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Reset attendance for this staff today?')">
                                                    <input type="hidden" name="user_id" value="<?php echo $staf['id']; ?>">
                                                    <input type="hidden" name="action" value="reset">
                                                    <button type="submit" name="quick_action" class="btn btn-action btn-danger text-white" title="Reset Today">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Live Activity</h5>
                        <span class="badge bg-soft-primary text-primary rounded-pill px-2" style="font-size: 9px;">REALTIME</span>
                    </div>
                    <div class="p-3">
                        <div class="table-scroll" style="max-height: 400px;">
                            <div class="timeline-small">
                            <?php foreach ($recent_activities as $act): ?>
                            <div class="activity-item d-flex mb-4">
                                <div class="activity-marker me-3">
                                    <?php 
                                    $marker_color = 'var(--primary-color)';
                                    if ($act['tipe'] == 'sakit') $marker_color = '#f59e0b'; // Amber
                                    elseif ($act['tipe'] == 'cuti') $marker_color = '#8b5cf6'; // Violet
                                    elseif ($act['tipe'] == 'reguler') $marker_color = '#10b981'; // Emerald
                                    elseif ($act['tipe'] == 'checkout') $marker_color = '#ef4444'; // Red
                                    elseif ($act['tipe'] == 'lembur_mulai') $marker_color = '#3b82f6'; // Blue
                                    elseif ($act['tipe'] == 'lembur_selesai') $marker_color = '#6366f1'; // Indigo
                                    ?>
                                    <div style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $marker_color; ?>; box-shadow: 0 0 0 4px <?php echo $marker_color; ?>20;"></div>
                                </div>
                                <div class="activity-content" style="border-left: 1px solid #f0f0f0; padding-left: 15px; margin-left: -20px; padding-bottom: 10px;">
                                    <div class="fw-bold text-dark" style="font-size: 13px;">
                                        <a href="staf_detail.php?user_id=<?php echo $act['user_id'] ?? '#'; ?>" class="text-decoration-none text-dark hover-primary">
                                            <?php echo htmlspecialchars($act['nama']); ?>
                                        </a>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size: 11px;">
                                        <i class="far fa-clock me-1"></i>
                                        <?php 
                                            if ($act['tipe'] == 'reguler') echo 'Absen Masuk';
                                            elseif ($act['tipe'] == 'sakit') echo 'Izin Sakit';
                                            elseif ($act['tipe'] == 'cuti') echo 'Izin / Cuti';
                                            elseif ($act['tipe'] == 'checkout') echo 'Absen Pulang';
                                            elseif ($act['tipe'] == 'lembur_mulai') echo 'Mulai Lembur';
                                            elseif ($act['tipe'] == 'lembur_selesai') echo 'Selesai Lembur';
                                            else echo 'Aktivitas';
                                        ?> pukul <?php echo date('H:i', strtotime($act['waktu'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history text-light mb-2" style="font-size: 30px;"></i>
                                    <p class="text-muted small">No activity recorded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Modal Tambah Akun -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buat Akun Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" required placeholder="">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">NIP <span class="text-danger">*</span></label>
                                <input type="text" name="nip" class="form-control" required placeholder="">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">ID Login <span class="text-danger">*</span></label>
                                <input type="text" name="absen_id" class="form-control" required placeholder="">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required placeholder="">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                                <input type="text" name="telepon" class="form-control" required placeholder="">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                            <textarea name="alamat" class="form-control" rows="2" required placeholder=""></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password Akun <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role / Peran <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="staf">Staff (Akses Terbatas)</option>
                                    <option value="manager">Manager (Akses Penuh)</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 mb-0 small text-center">
                            <i class="fas fa-shield-alt me-1"></i> Pastikan password dicatat dengan baik untuk diberikan kepada staff.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="create_user" class="btn btn-dark">Buat Akun</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pengaturan Shift Otomatis -->
    <div class="modal fade" id="shiftSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pengaturan Shift Otomatis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_auto_shift" id="enableAutoShift" <?php echo $enable_auto_shift == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enableAutoShift">Aktifkan Shift Otomatis</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Waktu Buka Shift <span class="text-danger">*</span></label>
                                <input type="time" name="auto_open_time" class="form-control" value="<?php echo $auto_open_time; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Waktu Tutup Shift <span class="text-danger">*</span></label>
                                <input type="time" name="auto_close_time" class="form-control" value="<?php echo $auto_close_time; ?>" required>
                            </div>
                        </div>
                        <p class="text-muted small mt-3 mb-0">
                            <i class="fas fa-info-circle me-1"></i> Jika diaktifkan, shift akan terbuka dan beralih ke mode lembur otomatis setelah waktu tutup tercapai.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="save_shift_settings" class="btn btn-primary">Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Otorisasi Lembur -->
    <div class="modal fade" id="overtimeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-clock me-2"><g/i>Otorisasi Lembur Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Aktifitas Lembur <span class="text-danger">*</span></label>
                            <input type="text" name="task_name" class="form-control" required value="Lembur Kerja">
                            <input type="hidden" name="is_overtime" value="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tanggal Lembur <span class="text-danger">*</span></label>
                            <input type="date" name="deadline" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold d-block">Pilih Karyawan yang Diizinkan Lembur</label>
                            <div class="border rounded p-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input" type="checkbox" id="checkAllLembur" onchange="toggleAllLembur(this)">
                                    <label class="form-check-label fw-bold text-danger" for="checkAllLembur">Pilih Semua Staff</label>
                                </div>
                                <div id="lemberCheckboxList">
                                    <?php foreach ($staf_list as $s): ?>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input lembur-checkbox" type="checkbox" name="assigned_users[]" 
                                                   value="<?php echo $s['id']; ?>" id="lembur_staf_<?php echo $s['id']; ?>">
                                            <label class="form-check-label" for="lembur_staf_<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['nama']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="create_task" class="btn btn-danger px-4 fw-bold">Berikan Izin Lembur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tugas Baru -->
    <div class="modal fade" id="taskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-primary fw-bold"><i class="fas fa-tasks me-2"></i>Berikan Tugas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Tugas <span class="text-danger">*</span></label>
                            <input type="text" name="task_name" class="form-control" required placeholder="">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Deskripsi / Catatan</label>
                            <textarea name="deskripsi" class="form-control" rows="2" placeholder=""></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Deadline <span class="text-danger">*</span></label>
                                <input type="date" name="deadline" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Prioritas</label>
                                <select name="priority" class="form-select">
                                    <option value="low">Rendah</option>
                                    <option value="medium" selected>Sedang</option>
                                    <option value="high">Tinggi</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch p-3 border rounded bg-light">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="is_overtime" id="isOvertimeCheck">
                                <label class="form-check-label fw-bold text-danger" for="isOvertimeCheck">
                                    <i class="fas fa-moon me-1"></i> Tandai sebagai Aktifitas Lembur
                                </label>
                                <div class="form-text small">Jika dicentang, pegawai yang dipilih akan diberikan akses untuk melakukan absensi lembur pada tanggal deadline tersebut.</div>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold d-block">Tugaskan Kepada</label>
                            <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input" type="checkbox" id="checkAllStaf" onchange="toggleAllStaf(this)">
                                    <label class="form-check-label fw-bold text-primary" for="checkAllStaf">Pilih Semua Pegawai</label>
                                </div>
                                <div id="stafCheckboxList">
                                    <?php foreach ($staf_list as $s): ?>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input staf-checkbox" type="checkbox" name="assigned_users[]" 
                                                   value="<?php echo $s['id']; ?>" id="staf_<?php echo $s['id']; ?>"
                                                   onchange="updateCheckAllState()">
                                            <label class="form-check-label" for="staf_<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['nama']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="create_task" class="btn btn-primary px-4 fw-bold">Kirim Tugas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Total Staf -->
    <div class="modal fade" id="totalStafModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: var(--blueblack);">
                    <h5 class="modal-title fw-bold"><i class="fas fa-users me-2"></i>Daftar Seluruh Staff</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($staf_total_details as $s): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-3 border-start border-4" style="border-left-color: var(--blueblack) !important;">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border: 1px solid #eee;">
                                        <i class="fas fa-user" style="color: var(--blueblack);"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold" style="color: var(--blueblack);"><?php echo htmlspecialchars($s['nama']); ?></h6>
                                        <small class="text-muted">ID: <?php echo $s['absen_id']; ?></small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge" style="background-color: var(--blueblack); font-size: 10px;">STAFF</span>
                                    <form method="POST" onsubmit="return confirm('Hapus karyawan ini secara permanen?')">
                                        <input type="hidden" name="target_id" value="<?php echo $s['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger border-0">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="staf_detail.php?view=list" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-cog me-2"></i> Kelola Seluruh Karyawan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hadir Hari Ini -->
    <div class="modal fade" id="hadirHariIniModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-check me-2"></i>Staff Hadir Hari Ini</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($staf_hadir_details)): ?>
                            <div class="p-4 text-center text-muted">Belum ada staff yang hadir.</div>
                        <?php else: ?>
                            <?php foreach ($staf_hadir_details as $s): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($s['nama']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $s['absen_id']; ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?php echo $s['jam_masuk']; ?></div>
                                        <small class="text-muted">Jam Masuk</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Semua Tugas Selesai -->
    <div class="modal fade" id="modalSemuaTugasSelesai" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header bg-white border-bottom py-3 px-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-soft-success p-2 rounded-3 me-3" style="background: rgba(16, 185, 129, 0.1);">
                            <i class="fas fa-history text-success fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-800 text-dark mb-0">Riwayat Tugas Selesai</h5>
                            <p class="text-muted small mb-0">Daftar lengkap semua tugas yang telah diselesaikan</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-4 bg-light border-bottom">
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="filterPenerima" class="form-control" placeholder="Filter berdasarkan nama penerima...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Penerima</th>
                                    <th>Nama Tugas</th>
                                    <th>Deadline</th>
                                    <th>Selesai Pada</th>
                                    <th>Pemberi Tugas</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="bodyTugasSelesai">
                                <?php if (empty($tasks_selesai_history)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada riwayat tugas selesai</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tasks_selesai_history as $th): ?>
                                        <tr class="task-row">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle-sm me-3 bg-soft-primary d-flex align-items-center justify-content-center text-primary fw-600" style="width: 32px; height: 32px; border-radius: 8px; font-size: 12px; background: rgba(0, 74, 173, 0.1);">
                                                        <?php echo strtoupper(substr($th['penerima'], 0, 1)); ?>
                                                    </div>
                                                    <div class="fw-600 text-dark nama-penerima"><?php echo htmlspecialchars($th['penerima']); ?></div>
                                                </div>
                                            </td>
                                            <td class="text-secondary fw-500"><?php echo htmlspecialchars($th['task_name']); ?></td>
                                            <td><span class="badge bg-light text-muted fw-normal px-2"><?php echo date('d M Y', strtotime($th['deadline'])); ?></span></td>
                                            <td><span class="text-success fw-600 small"><i class="far fa-check-circle me-1"></i><?php echo date('d M Y, H:i', strtotime($th['updated_at'])); ?></span></td>
                                            <td><span class="text-secondary small"><?php echo htmlspecialchars($th['pembuat']); ?></span></td>
                                            <td class="text-center"><span class="badge bg-soft-success text-success px-3">SELESAI</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tugas Selesai -->
    <div class="modal fade" id="tugasSelesaiModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-tasks me-2"></i>Tugas Selesai Hari Ini</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($task_selesai_details)): ?>
                            <div class="p-4 text-center text-muted">Belum ada tugas yang diselesaikan hari ini.</div>
                        <?php else: ?>
                            <?php foreach ($task_selesai_details as $t): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0 fw-bold text-primary"><?php echo htmlspecialchars($t['task_name']); ?></h6>
                                        <span class="badge bg-success">DONE</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Oleh: <?php echo htmlspecialchars($t['penerima']); ?></small>
                                        <small class="text-muted italic"><?php echo date('H:i', strtotime($t['updated_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Staf Izin/Sakit -->
    <div class="modal fade" id="stafIzinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-hospital-user me-2"></i>Staff Izin / Sakit Hari Ini</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($staf_izin_details)): ?>
                            <div class="p-4 text-center text-muted">Tidak ada staff yang izin hari ini.</div>
                        <?php else: ?>
                            <?php foreach ($staf_izin_details as $s): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($s['nama']); ?></div>
                                        <span class="badge <?php echo $s['tipe_absen'] == 'sakit' ? 'bg-danger' : 'bg-warning text-dark'; ?> rounded-pill">
                                            <?php echo strtoupper($s['tipe_absen']); ?>
                                        </span>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        <i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($s['keterangan'] ?: 'Tidak ada keterangan'); ?>
                                    </div>
                                    <?php if ($s['bukti_izin']): ?>
                                        <a href="uploads/bukti_izin/<?php echo $s['bukti_izin']; ?>" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 small" style="font-size: 11px;">
                                            <i class="fas fa-paperclip me-1"></i> Lihat Bukti
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Staf Lembur -->
    <div class="modal fade" id="stafLemburModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-moon me-2"></i>Staff Lembur Hari Ini</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($staf_lembur_details)): ?>
                            <div class="p-4 text-center text-muted">Tidak ada staff yang lembur hari ini.</div>
                        <?php else: ?>
                            <?php foreach ($staf_lembur_details as $s): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($s['nama']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $s['absen_id']; ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-warning"><?php echo $s['jam_mulai']; ?></div>
                                        <small class="text-muted">Mulai Lembur</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Staf Belum Absen -->
    <div class="modal fade" id="belumAbsenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-clock me-2"></i>Staff Belum Absen Hari Ini</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($staf_belum_absen_details)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                <p class="mb-0">Luar biasa! Semua staff sudah melakukan absensi hari ini.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($staf_belum_absen_details as $s): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-muted"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($s['nama']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $s['absen_id']; ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-danger border border-danger small">Belum Masuk</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle Functionality with Enhanced Mobile Support
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const isMobile = window.innerWidth <= 991.98;
            
            if (isMobile) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                if (sidebarToggle) sidebarToggle.classList.toggle('active');
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
                if (sidebarToggle) {
                    sidebarToggle.classList.toggle('collapsed');
                    const icon = sidebarToggle.querySelector('i');
                    if (sidebar.classList.contains('hidden')) {
                        icon.className = 'fas fa-bars';
                    } else {
                        icon.className = 'fas fa-arrow-left';
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
            if (overlay) overlay.addEventListener('click', toggleSidebar);

            // Handle window resize
            window.addEventListener('resize', () => {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                const overlay = document.getElementById('sidebarOverlay');
                const sidebarToggle = document.getElementById('sidebarToggle');
                
                if (window.innerWidth > 991.98) {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                    if (sidebarToggle) sidebarToggle.classList.remove('active');
                } else {
                    sidebar.classList.remove('hidden');
                    mainContent.classList.remove('expanded');
                    if (sidebarToggle) sidebarToggle.classList.remove('collapsed');
                }
            });

            // Inisialisasi semua popover
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            })

            function toggleAllStaf(source) {
                const checkboxes = document.querySelectorAll('.staf-checkbox');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }

            function toggleAllLembur(source) {
                const checkboxes = document.querySelectorAll('.lembur-checkbox');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }

            window.toggleAllStaf = toggleAllStaf;
            window.toggleAllLembur = toggleAllLembur;

            function updateCheckAllState() {
                const checkAll = document.getElementById('checkAllStaf');
                const checkboxes = document.querySelectorAll('.staf-checkbox');
                const checkedCount = document.querySelectorAll('.staf-checkbox:checked').length;
                
                if (checkAll) {
                    checkAll.checked = checkedCount === checkboxes.length;
                    checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
                }
            }
            window.updateCheckAllState = updateCheckAllState;

            // Filter Penerima Tugas Selesai
            const filterInput = document.getElementById('filterPenerima');
            if (filterInput) {
                filterInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#bodyTugasSelesai .task-row');
                    
                    rows.forEach(row => {
                        const namaPenerima = row.querySelector('.nama-penerima').textContent.toLowerCase();
                        if (namaPenerima.includes(searchValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>