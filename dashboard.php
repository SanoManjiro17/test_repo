<?php
require_once 'config_safe.php';
cekLogin();

// Validasi session tambahan - cek timeout
if (isset($_SESSION['last_activity'])) {
    $session_timeout = 1800; // 30 menit
    if (time() - $_SESSION['last_activity'] > $session_timeout) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit();
    }
}

if (isset($_SESSION['role']) && $_SESSION['role'] == 'manager') {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle AJAX untuk keep session alive
if (isset($_POST['action']) && $_POST['action'] == 'keep_session') {
    ob_clean();
    header('Content-Type: application/json');
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session updated'
    ]);
    exit();
}

// Handle AJAX requests untuk waktu server real-time
if (isset($_POST['action']) && $_POST['action'] == 'get_server_time') {
    ob_clean(); // Bersihkan output buffer
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Jakarta');
    echo json_encode([
        'success' => true,
        'server_time' => date('H:i:s'),
        'server_date' => date('Y-m-d'),
        'timestamp' => time()
    ]);
    exit();
}

// Handle AJAX absen masuk dengan waktu server real-time
if (isset($_POST['action']) && $_POST['action'] == 'absen_masuk_ajax') {
    ob_clean();
    header('Content-Type: application/json');
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $jam_masuk = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    // Status shift global sudah ada di $shift_status dari config_safe.php
    
    if ($shift_status == 'closed') {
        echo json_encode([
            'success' => false,
            'message' => "Maaf, shift saat ini sedang ditutup oleh Manager."
        ]);
        exit();
    }
    
    // Tipe absen selalu reguler di sini karena lembur punya handler sendiri (mulai_lembur_ajax)
    $tipe_absen = 'reguler';
    
    // Cek apakah sudah ada absensi hari ini (reguler/sakit/cuti)
    $stmt_status = $pdo->prepare("SELECT tipe_absen FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen IN ('reguler', 'sakit', 'cuti') OR tipe_absen IS NULL)");
    $stmt_status->execute([$user_id, $tanggal]);
    $status_hari_ini = $stmt_status->fetch();
    
    if ($status_hari_ini) {
        if (in_array($status_hari_ini['tipe_absen'], ['sakit', 'cuti'])) {
            echo json_encode(['success' => false, 'message' => "Status hari ini: " . strtoupper($status_hari_ini['tipe_absen']) . ". Tidak dapat absen masuk."]);
            exit();
        }
        echo json_encode(['success' => false, 'message' => "Anda sudah melakukan presensi masuk hari ini."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, tipe_absen) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $tanggal, $jam_masuk, $tipe_absen]);
        
        echo json_encode([
            'success' => true,
            'message' => "Absen masuk berhasil pada jam " . $jam_masuk,
            'jam_masuk' => $jam_masuk,
            'tanggal' => $tanggal
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ]);
    }
    exit();
}

// Handle AJAX absen keluar dengan waktu server real-time
if (isset($_POST['action']) && $_POST['action'] == 'absen_keluar_ajax') {
    ob_clean();
    header('Content-Type: application/json');
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $jam_keluar = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    try {
        // Get absensi masuk hari ini
        $stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id, $tanggal]);
        $absensi = $stmt->fetch();
        
        if ($absensi && !$absensi['jam_keluar']) {
            // Hitung durasi
            $durasi = hitungDurasi($absensi['jam_masuk'], $jam_keluar);
            
            // Update absensi keluar
            $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
            $stmt->execute([$jam_keluar, $durasi, $absensi['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => "Absen keluar berhasil pada jam " . $jam_keluar . ". Total durasi: " . $durasi,
                'jam_keluar' => $jam_keluar,
                'durasi' => $durasi
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Tidak ada absensi masuk yang ditemukan atau sudah absen keluar"
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ]);
    }
    exit();
}


// Handle AJAX mulai lembur
if (isset($_POST['action']) && $_POST['action'] == 'mulai_lembur_ajax') {
    ob_clean();
    header('Content-Type: application/json');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $jam_mulai = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    try {
        // Cek Otoritas Lembur (Restriksi Staff)
        $stmt_task = $pdo->prepare("SELECT COUNT(*) FROM tasklist WHERE (assigned_to = ? OR (user_id = ? AND assigned_to IS NULL)) AND deadline = ? AND is_overtime = 1 AND status != 'completed'");
        $stmt_task->execute([$user_id, $user_id, $tanggal]);
        $has_task = $stmt_task->fetchColumn() > 0;

        // Lembur hanya bisa dimulai jika Manager sudah membuka shift lembur atau memiliki tugas lembur
        if ($shift_status !== 'overtime' && !$has_task) {
            echo json_encode(['success' => false, 'message' => "Akses lembur belum dibuka oleh Manager."]);
            exit();
        }

        // Integrity Check: Pastikan sudah absen keluar dari shift reguler
        $stmt_reg = $pdo->prepare("SELECT id, jam_masuk, jam_keluar FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen = 'reguler' OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
        $stmt_reg->execute([$user_id, $tanggal]);
        $reg_absensi = $stmt_reg->fetch();

        if ($reg_absensi && !$reg_absensi['jam_keluar']) {
            // Auto-checkout dari shift reguler jika belum
            $jam_checkout_reg = date('H:i:s');
            $durasi_reg = hitungDurasi($reg_absensi['jam_masuk'], $jam_checkout_reg);

            $stmt_upd = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
            $stmt_upd->execute([$jam_checkout_reg, $durasi_reg, $reg_absensi['id']]);
        }

        // Cek apakah sudah pernah lembur hari ini
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM absensi_lembur WHERE user_id = ? AND tanggal = ?");
        $stmt_check->execute([$user_id, $tanggal]);
        if ($stmt_check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => "Anda sudah melakukan lembur hari ini."]);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO absensi_lembur (user_id, tanggal, jam_mulai) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $tanggal, $jam_mulai]);
        echo json_encode(['success' => true, 'message' => "Lembur dimulai pada jam " . $jam_mulai]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle AJAX selesai lembur
if (isset($_POST['action']) && $_POST['action'] == 'selesai_lembur_ajax') {
    ob_clean();
    header('Content-Type: application/json');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $jam_selesai = date('H:i:s');
    $tanggal = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? AND jam_selesai IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id, $tanggal]);
        $lembur = $stmt->fetch();
        
        if ($lembur) {
            $durasi = hitungDurasi($lembur['jam_mulai'], $jam_selesai);
            $stmt = $pdo->prepare("UPDATE absensi_lembur SET jam_selesai = ?, durasi_lembur = ? WHERE id = ?");
            $stmt->execute([$jam_selesai, $durasi, $lembur['id']]);
            echo json_encode(['success' => true, 'message' => "Lembur selesai. Durasi: " . $durasi]);
        } else {
            echo json_encode(['success' => false, 'message' => "Data lembur tidak ditemukan."]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$tanggal_sekarang = date('Y-m-d');

// Cek apakah kolom tipe_absen ada, jika tidak arahkan ke setup_lembur.php
try {
    $pdo->query("SELECT tipe_absen FROM absensi LIMIT 1");
} catch (Exception $e) {
    header('Location: setup_lembur.php');
    exit();
}

// Cek absensi hari ini (termasuk reguler, sakit, dan cuti)
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? AND (tipe_absen IN ('reguler', 'sakit', 'cuti') OR tipe_absen IS NULL) ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetch();

// Cek lembur yang sedang berlangsung
try {
    $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? AND jam_selesai IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $lembur_aktif = $stmt->fetch();
    
    // Cek riwayat lembur hari ini
    $stmt = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? AND tanggal = ? ORDER BY id DESC");
    $stmt->execute([$user_id, $tanggal_sekarang]);
    $riwayat_lembur = $stmt->fetchAll();
} catch (PDOException $e) {
    // Jika tabel absensi_lembur belum ada, set nilai default
    $lembur_aktif = false;
    $riwayat_lembur = [];
}

// Cek apakah ada tugas lembur untuk hari ini
$stmt_lembur_task = $pdo->prepare("SELECT COUNT(*) FROM tasklist WHERE (assigned_to = ? OR (user_id = ? AND assigned_to IS NULL)) AND deadline = ? AND is_overtime = 1 AND status != 'completed'");
$stmt_lembur_task->execute([$user_id, $user_id, $tanggal_sekarang]);
$has_overtime_task = $stmt_lembur_task->fetchColumn() > 0;

// Proses absensi masuk
if (isset($_POST['absen_masuk'])) {
    $jam_masuk = date('H:i:s');
    $jam_tampil = date('H:i:s');
    
    // Cek apakah sudah ada absensi hari ini
    $stmt_check = $pdo->prepare("SELECT id FROM absensi WHERE user_id = ? AND tanggal = ?");
    $stmt_check->execute([$user_id, $tanggal_sekarang]);
    if ($stmt_check->fetch()) {
        $_SESSION['error'] = "Anda sudah melakukan presensi hari ini.";
        header('Location: dashboard.php');
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, tipe_absen) VALUES (?, ?, ?, 'reguler')");
    $stmt->execute([$user_id, $tanggal_sekarang, $jam_masuk]);
    
    // Set session untuk notifikasi
    $_SESSION['absen_success'] = "Absen masuk berhasil pada jam " . $jam_tampil;
    
    header('Location: dashboard.php');
    exit();
}

// Proses absensi keluar
if (isset($_POST['absen_keluar']) && $absensi_hari_ini) {
    $jam_keluar = date('H:i:s');
    $durasi = hitungDurasi($absensi_hari_ini['jam_masuk'], $jam_keluar);
    
    $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
    $stmt->execute([$jam_keluar, $durasi, $absensi_hari_ini['id']]);
    
    $_SESSION['absen_success'] = "Absen keluar berhasil pada jam " . $jam_keluar . ". Total durasi: " . $durasi;
    
    header('Location: dashboard.php');
    exit();
}

// Proses mulai lembur
if (isset($_POST['mulai_lembur'])) {
    $jam_mulai = date('H:i:s');
    
    // Cek Otoritas Lembur (Restriksi Staff)
    // Lembur hanya bisa dimulai jika Manager sudah membuka shift lembur atau memiliki tugas lembur
    if ($shift_status !== 'overtime' && !$has_overtime_task) {
        $_SESSION['error'] = "Akses lembur belum dibuka oleh Manager.";
        header('Location: dashboard.php');
        exit();
    }

    // Cek apakah sudah pernah lembur hari ini
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM absensi_lembur WHERE user_id = ? AND tanggal = ?");
    $stmt_check->execute([$user_id, $tanggal_sekarang]);
    if ($stmt_check->fetchColumn() > 0) {
        $_SESSION['error'] = "Anda sudah melakukan lembur hari ini.";
        header('Location: dashboard.php');
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO absensi_lembur (user_id, tanggal, jam_mulai) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $tanggal_sekarang, $jam_mulai]);
    
    $_SESSION['absen_success'] = "Lembur dimulai pada jam " . $jam_mulai;
    
    header('Location: dashboard.php');
    exit();
}

// Proses selesai lembur
if (isset($_POST['selesai_lembur']) && $lembur_aktif) {
    $jam_selesai = date('H:i:s');
    $durasi_lembur = hitungDurasi($lembur_aktif['jam_mulai'], $jam_selesai);
    
    $stmt = $pdo->prepare("UPDATE absensi_lembur SET jam_selesai = ?, durasi_lembur = ? WHERE id = ?");
    $stmt->execute([$jam_selesai, $durasi_lembur, $lembur_aktif['id']]);
    
    $_SESSION['absen_success'] = "Lembur selesai pada jam " . $jam_selesai . ". Durasi lembur: " . $durasi_lembur;
    
    header('Location: dashboard.php');
    exit();
}

// Proses absensi baru (untuk multiple absensi)
if (isset($_POST['absen_baru'])) {
    // Reset absensi hari ini untuk memungkinkan absensi baru
    $_SESSION['absen_success'] = "Silakan lakukan absensi baru. Jam masuk akan otomatis diisi.";
    header('Location: tasklist.php');
    exit();
}

// Handler untuk Hapus Tugas dari Dashboard
if (isset($_POST['delete_task_dash'])) {
    $task_id = $_POST['task_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tasklist WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        $_SESSION['msg'] = "Tugas berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus tugas.";
    }
    header("Location: dashboard.php");
    exit();
}

// Handle Pengajuan Izin/Sakit
if (isset($_POST['aksi']) && $_POST['aksi'] == 'ajukan_izin') {
    $tipe_izin = $_POST['tipe_izin']; // 'sakit' atau 'cuti'
    $keterangan = $_POST['keterangan'];
    $tanggal = date('Y-m-d');
    $jam = date('H:i:s');
    
    $bukti_izin = null;
    if (isset($_FILES['bukti_izin']) && $_FILES['bukti_izin']['error'] == 0) {
        $ext = pathinfo($_FILES['bukti_izin']['name'], PATHINFO_EXTENSION);
        $filename = 'izin_' . $user_id . '_' . time() . '.' . $ext;
        $target = 'uploads/bukti_izin/' . $filename;
        
        if (!is_dir('uploads/bukti_izin/')) {
            mkdir('uploads/bukti_izin/', 0777, true);
        }
        
        if (move_uploaded_file($_FILES['bukti_izin']['tmp_name'], $target)) {
            $bukti_izin = $filename;
        }
    }
    
    try {
        // Cek apakah sudah ada absensi hari ini
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE user_id = ? AND tanggal = ?");
        $stmt_check->execute([$user_id, $tanggal]);
        
        if ($stmt_check->fetchColumn() > 0) {
            $_SESSION['error'] = "Anda sudah memiliki catatan kehadiran hari ini.";
        } else {
            // Masukkan data izin ke tabel absensi dengan durasi 0
            $stmt = $pdo->prepare("INSERT INTO absensi (user_id, tanggal, jam_masuk, jam_keluar, durasi_kerja, tipe_absen, keterangan, bukti_izin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $tanggal, $jam, '00:00:00', '00:00:00', $tipe_izin, $keterangan, $bukti_izin]);
            
            $_SESSION['absen_success'] = "Pengajuan " . ucfirst($tipe_izin) . " berhasil dikirim.";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal mengirim pengajuan: " . $e->getMessage();
    }
    
    header('Location: dashboard.php');
    exit();
}

// Handler untuk Review/Selesaikan Tugas dari Dashboard
if (isset($_POST['review_task_dash'])) {
    $task_id = $_POST['task_id'];
    try {
        $stmt = $pdo->prepare("UPDATE tasklist SET status = 'completed' WHERE id = ? AND (user_id = ? OR assigned_to = ?)");
        $stmt->execute([$task_id, $user_id, $user_id]);
        $_SESSION['msg'] = "Tugas selesai!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui tugas.";
    }
    header("Location: dashboard.php");
    exit();
}

// Ambil data user untuk profil
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Ambil data tugas (tasks) dari database
// Hanya ambil tugas yang ditujukan (assigned_to) untuk user ini
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.nama as pembuat 
        FROM tasklist t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.assigned_to = ? OR (t.user_id = ? AND t.assigned_to IS NULL)
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $tasks = $stmt->fetchAll();

    // Ambil daftar tugas yang sudah selesai untuk dashboard
    $stmt_done = $pdo->prepare("
        SELECT t.*, u.nama as pembuat 
        FROM tasklist t 
        JOIN users u ON t.user_id = u.id 
        WHERE (t.assigned_to = ? OR (t.user_id = ? AND t.assigned_to IS NULL)) 
        AND t.status = 'completed'
        ORDER BY t.deadline DESC, t.end_time DESC
        LIMIT 5
    ");
    $stmt_done->execute([$user_id, $user_id]);
    $completed_tasks = $stmt_done->fetchAll();
} catch (PDOException $e) {
    // Fallback jika query bermasalah (misal kolom belum ada)
    $tasks = [];
}

function getTaskDateRange($start, $end) {
    if (empty($start) || $start == $end) {
        return date('j M Y', strtotime($end));
    }
    return date('j M', strtotime($start)) . ' - ' . date('j M Y', strtotime($end));
}

function getStatusBadge($status, $deadline = null) {
    if ($status == 'completed') {
        return '<span class="status-badge badge-completed">SELESAI</span>';
    }
    
    if ($deadline) {
        $today = new DateTime(date('Y-m-d'));
        $target = new DateTime($deadline);
        if ($today > $target) {
            return '<span class="status-badge badge-overdue">TERLAMBAT</span>';
        }
    }
    
    return '<span class="status-badge badge-progress">PROSES</span>';
}

function getSisaWaktu($status, $deadline = null) {
    if ($status == 'completed' || !$deadline) return '-';
    
    $today = new DateTime(date('Y-m-d'));
    $target = new DateTime($deadline);
    
    if ($today > $target) return '0';
    
    $interval = $today->diff($target);
    $days = $interval->days;
    return $days > 0 ? $days . ' hari' : '0';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Absensi</title>
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
            z-index: 1050;
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
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
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

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-weight: 700;
        }

        /* Sidebar Action Buttons */
        .sidebar-actions {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .btn-sidebar-action {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            font-size: 14px;
        }

        .btn-checkin { background: var(--success-color); color: white !important; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-checkout { background: var(--warning-color); color: white !important; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); }
        .btn-checkin:hover, .btn-checkout:hover { transform: translateY(-2px); filter: brightness(1.1); }

        .sidebar-toggle {
            position: fixed;
            left: 255px;
            top: 20px;
            z-index: 1060;
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

        .sidebar-toggle.collapsed { left: 20px; }
        .sidebar.hidden { transform: translateX(-100%); }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }

        .sidebar-overlay.show { display: block; }

        .main-content { 
            margin-left: var(--sidebar-width); 
            flex-grow: 1; 
            padding: 40px 50px;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded { margin-left: 0; }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; padding-top: 80px; }
            .sidebar-toggle { left: 15px; top: 15px; }
            .sidebar-toggle.collapsed { left: 15px; }
        }
          

        .nav-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 18px;
            text-align: center;
            opacity: 0.8;
        }

        .nav-item:hover {
            color: white;
            background: rgba(255,255,255,0.08);
            border-left-color: rgba(255,255,255,0.4);
            padding-left: 30px;
        }

        .nav-item.active {
            color: white;
            background: rgba(255,255,255,0.15);
            border-left: 5px solid white;
            font-weight: 700;
            padding-left: 25px;
        }

        /* Main Content Styling */
        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px;
            min-height: 100vh;
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 800;
            margin-bottom: 40px;
            font-size: 28px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .content-card {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }

        .card-header-custom {
            padding: 20px 25px;
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header-custom h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: 700;
            font-size: 18px;
        }

        /* Table Custom Styling */
        .table-custom {
            margin-bottom: 0;
        }

        .table-custom thead th {
            background-color: #fcfdfe;
            color: #888;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 15px 20px;
        }

        .table-custom tbody td {
            padding: 12px 20px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            font-size: 13px;
            color: #444;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-overdue { background-color: rgba(231, 76, 60, 0.1); color: #e74c3c; }
        .badge-completed { background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; }
        .badge-progress { background-color: rgba(241, 196, 15, 0.1); color: #f39c12; }
        .bg-navy { background-color: var(--primary-color) !important; color: white !important; }
        .text-navy { color: var(--primary-color) !important; }

        .empty-card {
            border: 2px dashed #e0e6ed;
            border-radius: var(--card-radius);
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0aec0;
        }

        .modal-absensi .modal-content {
            border: none;
            border-radius: 25px;
            background: transparent;
            padding: 0;
            position: relative;
        }

        .absensi-card {
            background: white;
            border-radius: 20px;
            display: flex;
            overflow: hidden;
            min-height: 420px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .absensi-card-accent {
            width: 100px;
            background-color: #0052ad;
        }

        .absensi-card-content {
            flex-grow: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .absensi-title {
            position: absolute;
            top: 30px;
            left: 40px;
            color: #3e4a89;
            font-weight: 700;
            font-size: 28px;
        }

        .absensi-logo-container {
            position: absolute;
            top: 25px;
            right: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .absensi-logo {
            width: 50px;
            height: auto;
            mix-blend-mode: multiply;
        }

        .absensi-greeting {
            margin-top: 80px;
            color: #4a5568;
            font-size: 20px;
            text-align: center;
        }

        .absensi-avatar {
            width: 160px;
            height: 160px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 40px 0;
            border: 1px solid #e2e8f0;
        }

        .absensi-avatar i {
            font-size: 100px;
            color: #cbd5e0;
        }

        .btn-checkin-large {
            background-color: #006ce1;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 18px 0;
            width: 80%;
            font-weight: 700;
            font-size: 22px;
            text-transform: lowercase;
            transition: all 0.2s;
            box-shadow: 0 8px 20px rgba(0, 108, 225, 0.25);
        }

        .btn-checkin-large:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(0, 108, 225, 0.3);
            color: white;
        }

        /* Notifikasi Toast Atas Kiri */
        .toast-custom {
            position: fixed;
            top: 15px;
            left: 15px;
            background: #56ab7e;
            color: #0a3d21;
            padding: 8px 15px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 10000;
            font-weight: 600;
            font-size: 13px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .toast-custom .close-toast {
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        /* Toggle Button Styles */
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

        .sidebar {
            transition: transform 0.3s ease;
        }

        /* Adjust main content when sidebar is hidden */
        .main-content.expanded {
            margin-left: 0;
            max-width: 100vw;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0 !important;
                padding: 15px !important;
                padding-top: 80px !important;
                width: 100% !important;
                min-height: 100vh;
            }
            
            .sidebar-toggle {
                left: 15px !important;
                top: 15px !important;
                display: flex !important;
            }
        }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
                z-index: 1040;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            
            .sidebar-overlay.show {
                display: block;
                opacity: 1;
            }

            .page-title {
                font-size: 20px;
                margin-bottom: 30px !important;
            }

            .content-card {
                padding: 15px !important;
                border-radius: 12px;
            }

            /* Touch targets improvements */
            .nav-item {
                padding: 16px 20px;
                font-size: 15px;
            }

            .btn {
                padding: 10px 18px;
                font-size: 14px;
                border-radius: 10px;
            }
            
            /* Table responsiveness for mobile */
            .table-responsive {
                border: 0;
                margin-bottom: 0;
            }
            
            .table-custom tbody td {
                padding: 12px 10px;
                font-size: 13px;
                white-space: nowrap;
            }
            
            .table-custom thead th {
                padding: 12px 10px;
                font-size: 11px;
            }
        

        /* iOS Specific Smooth Scrolling */
        .sidebar, .main-content, .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        /* Adjustments for small phones */
        @media (max-width: 575.98px) {
            .page-title {
                font-size: 18px;
            }
            .absensi-card-content {
                padding: 20px 15px;
            }
            .btn-absen {
                width: 140px;
                height: 140px;
            }
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
            $sidebar_foto = (isset($user['foto']) && $user['foto']) ? 'uploads/profiles/'.$user['foto'] : 'https://ui-avatars.com/api/?name='.urlencode(isset($_SESSION['nama']) ? $_SESSION['nama'] : 'User').'&background=fff&color=004AAD&size=128';
            ?>
            <img src="<?php echo $sidebar_foto; ?>" class="sidebar-avatar" alt="Profile">
            <p class="profile-name"><?php echo isset($_SESSION['nama']) ? explode(' ', $_SESSION['nama'])[0] : 'User'; ?></p>
            <span class="profile-role"><?php echo isset($_SESSION['role']) ? strtoupper($_SESSION['role']) : 'USER'; ?></span>
        </div>
        
        <nav class="nav-menu">
            <?php if ($_SESSION['role'] == 'manager'): ?>
                <a href="admin_dashboard.php" class="nav-item">
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
            <?php else: ?>
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="laporan.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i> Laporan
                </a>
                <a href="tasklist.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i> Tugas
                </a>
            <?php endif; ?>
            
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="page-title m-0">Dashboard</h2>
            </div>
        </div>

        <!-- Attendance Status Card Modern -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="content-card overflow-hidden" style="border-radius: 20px;">
                    <div class="row g-0">
                        <div class="col-md-8 p-4 border-end">
                            <div class="d-flex align-items-center gap-4">
                                <div class="status-icon bg-soft-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px; background: rgba(0, 74, 173, 0.05);">
                                    <i class="fas fa-clock fa-2x" style="color: var(--primary-color);"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1 fw-bold text-dark" id="jam_sekarang" style="font-size: 32px; letter-spacing: 1px;">00:00:00</h3>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge rounded-pill bg-light text-dark border px-3 py-2" style="font-size: 11px;">
                                            <i class="far fa-calendar-alt me-1 text-primary"></i> <?php echo date('d F Y'); ?>
                                        </span>
                                        <?php if (!$absensi_hari_ini): ?>
                                            <span class="badge rounded-pill bg-soft-danger text-danger px-3 py-2" style="font-size: 11px; background: rgba(231, 76, 60, 0.1);">
                                                <i class="fas fa-times-circle me-1"></i> Belum absen
                                            </span>
                                        <?php elseif (in_array($absensi_hari_ini['tipe_absen'], ['sakit', 'cuti'])): ?>
                                            <span class="badge rounded-pill bg-soft-warning text-warning px-3 py-2" style="font-size: 11px; background: rgba(241, 196, 15, 0.1);">
                                                <i class="fas fa-hospital-user me-1"></i> IZIN <?php echo strtoupper($absensi_hari_ini['tipe_absen']); ?>
                                            </span>
                                        <?php elseif ($lembur_aktif): ?>
                                            <span class="badge rounded-pill bg-soft-info text-info px-3 py-2" style="font-size: 11px; background: rgba(52, 152, 219, 0.1);">
                                                <i class="fas fa-moon me-1"></i> Sedang Lembur
                                            </span>
                                        <?php elseif ($absensi_hari_ini['jam_keluar']): ?>
                                            <span class="badge rounded-pill bg-soft-success text-success px-3 py-2" style="font-size: 11px; background: rgba(46, 204, 113, 0.1);">
                                                <i class="fas fa-check-circle me-1"></i> Selesai Bekerja
                                            </span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-soft-primary text-primary px-3 py-2" style="font-size: 11px; background: rgba(0, 74, 173, 0.1);">
                                                <i class="fas fa-briefcase me-1"></i> Sedang Bekerja
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 p-4 bg-light d-flex flex-column justify-content-center align-items-center text-center">
                            <?php if ($absensi_hari_ini && in_array($absensi_hari_ini['tipe_absen'], ['sakit', 'cuti'])): ?>
                                <div class="text-center p-3">
                                    <i class="fas fa-user-lock fa-3x text-warning mb-3"></i>
                                    <h5 class="fw-bold">Akses Terbatas</h5>
                                    <p class="small text-muted">Anda sedang dalam status <strong><?php echo strtoupper($absensi_hari_ini['tipe_absen']); ?></strong>. Fitur absensi dinonaktifkan untuk hari ini.</p>
                                </div>
                            <?php elseif (!$absensi_hari_ini): ?>
                                <p class="small text-muted mb-3">Silakan lakukan presensi masuk untuk memulai hari kerja Anda.</p>
                                <div class="d-flex flex-column gap-2 w-100 px-3">
                                    <button onclick="absenMasuk()" class="btn btn-primary rounded-pill py-2 fw-bold shadow-sm mb-1" style="background: var(--primary-color); border: none;">
                                        <i class="fas fa-fingerprint me-2"></i> Absen Masuk
                                    </button>
                                    <div class="d-flex gap-2">
                                        <button onclick="showIzinModal('sakit')" class="btn btn-outline-danger btn-sm rounded-pill flex-grow-1 fw-bold">Sakit</button>
                                        <button onclick="showIzinModal('cuti')" class="btn btn-outline-warning btn-sm rounded-pill flex-grow-1 fw-bold">Cuti</button>
                                    </div>
                                </div>
                            <?php elseif (!$absensi_hari_ini['jam_keluar'] && !in_array($absensi_hari_ini['tipe_absen'], ['sakit', 'cuti'])): ?>
                                <div class="text-center">
                                    <p class="small text-muted mb-2">Masuk: <span class="fw-bold text-dark"><?php echo substr($absensi_hari_ini['jam_masuk'], 0, 5); ?></span></p>
                                    <button onclick="absenKeluar()" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm text-dark">
                                        <i class="fas fa-sign-out-alt me-1"></i> Absen Pulang
                                    </button>
                                </div>
                            <?php elseif ($lembur_aktif): ?>
                                <div class="text-center">
                                    <p class="small text-muted mb-2">Mulai: <span class="fw-bold text-dark"><?php echo substr($lembur_aktif['jam_mulai'], 0, 5); ?></span></p>
                                    <button onclick="selesaiLembur()" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                                        <i class="fas fa-stop-circle me-1"></i> Selesai Lembur
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php 
                                // Syarat tombol lembur muncul:
                                // 1. Belum ada riwayat lembur selesai hari ini
                                // 2. Tidak sedang dalam sesi lembur aktif
                                // 3. Otoritas Manager: Shift status global harus 'overtime'
                                //    ATAU memiliki tugas lembur hari ini ($has_overtime_task)
                                $can_see_overtime = (empty($riwayat_lembur) && !$lembur_aktif);
                                $manager_opened = ($shift_status == 'overtime' || $has_overtime_task);
                                
                                if ($can_see_overtime && $manager_opened): 
                                ?>
                                    <div class="text-center">
                                        <?php if ($has_overtime_task): ?>
                                            <p class="small text-muted mb-2">Anda memiliki tugas lembur hari ini.</p>
                                        <?php else: ?>
                                            <p class="small text-muted mb-2">Manager telah membuka akses lembur.</p>
                                        <?php endif; ?>
                                        <button onclick="mulaiLembur()" class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm">
                                            <i class="fas fa-moon me-2"></i> Mulai Lembur
                                        </button>
                                    </div>
                                <?php elseif ($absensi_hari_ini && $absensi_hari_ini['jam_keluar']): ?>
                                    <div class="text-center py-2">
                                        <?php 
                                        $icon = 'fa-mug-hot';
                                        if ($absensi_hari_ini['tipe_absen'] == 'sakit') {
                                            $icon = 'fa-hand-holding-medical';
                                        } elseif ($absensi_hari_ini['tipe_absen'] == 'cuti') {
                                            $icon = 'fa-umbrella-beach';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?> fa-2x text-muted mb-2"></i>
                                        <p class="small text-muted mb-1">
                                            <?php 
                                            if ($absensi_hari_ini['tipe_absen'] == 'sakit') {
                                                echo 'Semoga lekas sembuh!';
                                            } elseif ($absensi_hari_ini['tipe_absen'] == 'cuti') {
                                                echo 'Sampai jumpa kembali!';
                                            } else {
                                                echo 'Terima kasih atas kerja kerasnya hari ini!';
                                            }
                                            ?>
                                        </p>
                                        <p class="small text-muted mb-0">
                                            Keluar: <span class="fw-bold text-dark"><?php echo substr($absensi_hari_ini['jam_keluar'], 0, 5); ?></span> 
                                            <?php if ($absensi_hari_ini['durasi_kerja'] && $absensi_hari_ini['durasi_kerja'] != '00:00:00'): ?>
                                                | Durasi: <span class="fw-bold text-dark"><?php echo $absensi_hari_ini['durasi_kerja']; ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Absensi Sesuai Gambar -->
        <div class="modal fade modal-absensi" id="absenModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="absensi-card">
                        <div class="absensi-card-accent"></div>
                        <div class="absensi-card-content">
                            <div class="absensi-title">ABSENSI</div>
                            <div class="absensi-logo-container">
                                <img src="LOGO-Minven.png" class="absensi-logo" onerror="this.src='LOGO-Minven.jpg'">
                            </div>
                            
                            <div class="absensi-greeting">
                                Halo, <strong><?php echo isset($_SESSION['nama']) ? explode(' ', $_SESSION['nama'])[0] : 'User'; ?></strong>. Semoga harimu produktif!
                            </div>
                            
                            <div class="absensi-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            
                            <button type="button" onclick="confirmAbsenMasuk()" id="btn-confirm-checkin" class="btn-checkin-large" <?php echo ($shift_status == 'closed') ? 'disabled style="background-color: #ccc; cursor: not-allowed; box-shadow: none;"' : ''; ?>>
                                <?php 
                                if ($shift_status == 'closed') echo 'masuk (shift ditutup)';
                                elseif ($shift_status == 'overtime') echo 'masuk (lembur)';
                                else echo 'masuk';
                                ?>
                            </button>
                            
                            <div class="mt-4 d-flex gap-2 justify-content-center">
                                <button type="button" onclick="showIzinModal('sakit')" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
                                    <i class="fas fa-hand-holding-medical me-1"></i> Sakit
                                </button>
                                <button type="button" onclick="showIzinModal('cuti')" class="btn btn-outline-warning btn-sm rounded-pill px-3 fw-bold">
                                    <i class="fas fa-calendar-alt me-1"></i> Cuti
                                </button>
                            </div>

                            <?php if ($shift_status == 'closed'): ?>
                                <p class="text-danger mt-2 small fw-bold">Hubungi manager untuk membuka shift.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php if (isset($_SESSION['absen_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4 rounded-pill px-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['absen_success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['absen_success']); ?>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Kolom Tugas -->
        <div class="col-md-7">
            <div class="content-card mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center bg-light">
                    <h5 class="mb-0 text-success"><i class="fas fa-check-double me-2"></i>Tugas yang Selesai</h5>
                    <span class="badge bg-success rounded-pill"><?php echo count($completed_tasks); ?> Baru Selesai</span>
                </div>
                <div class="table-scroll" style="max-height: 250px;">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Tugas</th>
                                <th>Selesai Pada</th>
                                <th>Prioritas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($completed_tasks)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Belum ada tugas yang selesai</td></tr>
                            <?php else: ?>
                                <?php foreach ($completed_tasks as $ct): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($ct['task_name']); ?></div>
                                            <div class="text-muted" style="font-size: 10px;">Oleh: <?php echo htmlspecialchars($ct['pembuat']); ?></div>
                                        </td>
                                        <td class="small">
                                            <i class="far fa-calendar-alt me-1"></i><?php echo getTaskDateRange($ct['start_date'] ?? null, $ct['deadline']); ?>
                                            <div class="text-muted" style="font-size: 10px;"><?php echo $ct['end_time'] ?: '--:--'; ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $ct['priority'] == 'high' ? 'bg-danger' : ($ct['priority'] == 'medium' ? 'bg-warning' : 'bg-success'); ?>" style="font-size: 9px;">
                                                <?php echo strtoupper($ct['priority']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="content-card h-100">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tugas Penting (Proses)</h5>
                    <a href="tasklist.php" class="btn btn-sm btn-outline-primary rounded-pill">Lihat Semua</a>
                </div>
                <div class="table-scroll">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Tugas</th>
                                <th>Tenggat</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tasks)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Tidak ada tugas</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($tasks, 0, 5) as $task): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo htmlspecialchars($task['task_name']); ?></div>
                                            <div class="text-muted" style="font-size: 11px;">Oleh: <?php echo htmlspecialchars($task['pembuat']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <div class="fw-bold" style="font-size: 12px;"><i class="far fa-calendar-alt me-1"></i><?php echo isset($task['deadline']) ? getTaskDateRange($task['start_date'] ?? null, $task['deadline']) : '-'; ?></div>
                                            <div class="text-muted" style="font-size: 10px;">Sisa: <?php echo getSisaWaktu($task['status'], $task['deadline'] ?? null); ?></div>
                                        </td>
                                        <td class="text-center"><?php echo getStatusBadge($task['status'], $task['deadline'] ?? null); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex gap-2 justify-content-center">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <button type="submit" name="review_task_dash" class="btn btn-sm btn-soft-success rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; background: rgba(46, 204, 113, 0.1); color: #2ecc71; border: none;" title="Selesaikan">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php if ($task['user_id'] == $user_id): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus tugas ini?')">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <button type="submit" name="delete_task_dash" class="btn btn-sm btn-soft-danger rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: none;" title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
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

        <!-- Kolom Aktivitas Lembur -->
        <div class="col-md-5">
            <div class="content-card h-100">
                <div class="card-header-custom">
                    <h5 class="mb-0">Aktivitas Lembur</h5>
                </div>
                <div class="table-scroll">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt_lembur = $pdo->prepare("SELECT * FROM absensi_lembur WHERE user_id = ? ORDER BY tanggal DESC, jam_mulai DESC LIMIT 5");
                                $stmt_lembur->execute([$user_id]);
                                $riwayat_lembur = $stmt_lembur->fetchAll();
                                
                                if (empty($riwayat_lembur)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Belum ada riwayat lembur</td></tr>
                                <?php else: 
                                    foreach ($riwayat_lembur as $rl): ?>
                                        <tr>
                                            <td class="small"><?php echo date('d M Y', strtotime($rl['tanggal'])); ?></td>
                                            <td class="small"><?php echo substr($rl['jam_mulai'], 0, 5); ?> - <?php echo $rl['jam_selesai'] ? substr($rl['jam_selesai'], 0, 5) : '...'; ?></td>
                                            <td class="text-center"><span class="badge bg-light text-dark"><?php echo $rl['durasi_lembur'] ?: '-'; ?></span></td>
                                        </tr>
                                    <?php endforeach;
                                endif;
                            } catch (Exception $e) {
                                echo '<tr><td colspan="3">Error loading data</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="empty-card"></div>
</div>

    <!-- Modal Izin/Sakit -->
    <div class="modal fade" id="modalIzin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 15px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="izinModalTitle">Pengajuan Izin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="aksi" value="ajukan_izin">
                        <input type="hidden" name="tipe_izin" id="tipe_izin">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Keterangan / Alasan</label>
                            <textarea name="keterangan" class="form-control" rows="3" placeholder="Berikan alasan singkat..." required></textarea>
                        </div>
                        
                        <div class="mb-3" id="file_sakit_container" style="display: none;">
                            <label class="form-label small fw-bold" id="label_bukti">Bukti Surat Dokter (Opsional)</label>
                            <input type="file" name="bukti_izin" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" style="background: #3e4a89; border: none;">Kirim Pengajuan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let absenModal;
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi Modal Bootstrap
            const modalEl = document.getElementById('absenModal');
            if (modalEl) {
                absenModal = new bootstrap.Modal(modalEl);
                
                // Tampilkan modal absen otomatis saat halaman dimuat jika belum absen dan tidak sedang izin
                <?php if (!$absensi_hari_ini): ?>
                    absenModal.show();
                <?php endif; ?>
            }

            // Tampilkan pesan sukses dari session jika ada
            <?php if (isset($_SESSION['absen_success'])): ?>
                showNotifikasi("<?php echo $_SESSION['absen_success']; ?>");
            <?php endif; ?>
            
            // Session timeout monitoring (29 menit - 1 menit sebelum timeout)
            let sessionTimeout;
            const SESSION_TIMEOUT = 29 * 60 * 1000; // 29 menit dalam ms
            
            function resetSessionTimeout() {
                clearTimeout(sessionTimeout);
                sessionTimeout = setTimeout(() => {
                    if (confirm('Session Anda akan segera habis. Klik OK untuk tetap login.')) {
                        // Kirim request untuk memperbarui session
                        fetch('dashboard.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=keep_session'
                        }).then(response => {
                            if (response.ok) {
                                resetSessionTimeout();
                            } else {
                                window.location.href = 'login.php?timeout=1';
                            }
                        }).catch(() => {
                            window.location.href = 'login.php?timeout=1';
                        });
                    } else {
                        window.location.href = 'logout.php';
                    }
                }, SESSION_TIMEOUT);
            }
            
            // Reset timeout pada setiap aktivitas
            document.addEventListener('click', resetSessionTimeout);
            document.addEventListener('keypress', resetSessionTimeout);
            document.addEventListener('mousemove', resetSessionTimeout);
            
            // Mulai monitoring
            resetSessionTimeout();
        });

        // Fungsi untuk membuka modal absen masuk
        function absenMasuk() {
            if (absenModal) {
                absenModal.show();
            }
        }

        // Fungsi untuk menampilkan modal izin/sakit
        function showIzinModal(type) {
            const title = type === 'sakit' ? 'Pengajuan Izin Sakit' : 'Pengajuan Izin Cuti';
            const label = type === 'sakit' ? 'Bukti Surat Dokter (Opsional)' : 'Dokumen Pendukung / Form Cuti (Opsional)';
            document.getElementById('izinModalTitle').innerText = title;
            document.getElementById('tipe_izin').value = type;
            document.getElementById('label_bukti').innerText = label;
            
            // Tampilkan upload file untuk keduanya
            document.getElementById('file_sakit_container').style.display = 'block';
            
            const modal = new bootstrap.Modal(document.getElementById('modalIzin'));
            modal.show();
        }

        // Fungsi konfirmasi absen masuk dari dalam modal
        async function confirmAbsenMasuk() {
            const btn = document.getElementById('btn-confirm-checkin');
            const originalText = btn.innerHTML;
            
            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> memproses...';
                btn.disabled = true;
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=absen_masuk_ajax'
                });
                
                const data = await response.json();
                if (data.success) {
                    if (absenModal) absenModal.hide();
                    
                    showNotifikasi(data.message, 'success');
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showNotifikasi('Gagal: ' + data.message, 'danger');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showNotifikasi('Terjadi kesalahan koneksi.', 'danger');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // Fungsi AJAX untuk absen keluar
        async function absenKeluar() {
            if (!confirm('Peringatan: Absen Keluar hanya bisa dilakukan sekali hari ini. Pastikan pekerjaan Anda sudah selesai. Apakah Anda yakin ingin melakukan Absen Keluar sekarang?')) return;
            
            const btn = document.getElementById('btn-absen-keluar');
            const originalText = btn ? btn.innerText : 'Keluar';
            
            try {
                if(btn) {
                    btn.innerText = 'Memproses...';
                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.7';
                }
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=absen_keluar_ajax'
                });
                
                const data = await response.json();
                if (data.success) {
                    showNotifikasi(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotifikasi('Gagal: ' + data.message, 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotifikasi('Terjadi kesalahan koneksi.', 'danger');
            }
        }

        // Fungsi AJAX Mulai Lembur
        async function mulaiLembur() {
            if (!confirm('Apakah Anda yakin ingin mulai lembur sekarang?')) return;
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mulai_lembur_ajax'
                });
                const data = await response.json();
                if (data.success) {
                    showNotifikasi(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotifikasi(data.message, 'danger');
                }
            } catch (error) {
                showNotifikasi('Kesalahan koneksi', 'danger');
            }
        }

        // Fungsi AJAX Selesai Lembur
        async function selesaiLembur() {
            if (!confirm('Apakah Anda yakin ingin menyelesaikan lembur sekarang?')) return;
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=selesai_lembur_ajax'
                });
                const data = await response.json();
                if (data.success) {
                    showNotifikasi(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotifikasi(data.message, 'danger');
                }
            } catch (error) {
                showNotifikasi('Kesalahan koneksi', 'danger');
            }
        }

        // Waktu server (dari PHP)
        let serverTime = new Date('<?php echo date('Y/m/d H:i:s'); ?>');
        let localTimeAtStart = new Date();
        const serverTimezone = '<?php echo date_default_timezone_get(); ?>';
        
        function updateJam() {
            const isWorking = <?php echo ($absensi_hari_ini && !$absensi_hari_ini['jam_keluar']) ? 'true' : 'false'; ?>;
            const isOvertime = <?php echo ($lembur_aktif) ? 'true' : 'false'; ?>;
            const jamEl = document.getElementById('jam_sekarang');

            if (jamEl) {
                // Hitung selisih waktu server dengan waktu lokal sejak halaman dimuat
                const now = new Date();
                const elapsed = now.getTime() - localTimeAtStart.getTime();
                const currentServerTime = new Date(serverTime.getTime() + elapsed);
                
                const jam = currentServerTime.getHours().toString().padStart(2, '0');
                const menit = currentServerTime.getMinutes().toString().padStart(2, '0');
                const detik = currentServerTime.getSeconds().toString().padStart(2, '0');
                
                // Selalu tampilkan jam real-time
                jamEl.textContent = `${jam}:${menit}:${detik}`;
                
                // Update jam masuk otomatis
                if (document.getElementById('jam_masuk_otomatis')) {
                    document.getElementById('jam_masuk_otomatis').textContent = `${jam}:${menit}:${detik}`;
                }
            }
            
            // Update info timezone
            if (document.getElementById('server_timezone')) {
                document.getElementById('server_timezone').textContent = serverTimezone;
            }
        }
        
        // Fungsi untuk sinkronisasi waktu server
        function syncServerTime() {
            fetch('get_server_time.php')
                .then(response => response.json())
                .then(data => {
                    serverTime = new Date(data.server_time);
                    console.log('Waktu server disinkronisasi:', data.server_time);
                })
                .catch(error => {
                    console.log('Gagal sinkronisasi waktu server, menggunakan waktu lokal');
                });
        }
        
        // Sinkronisasi awal dan setiap 5 menit
        updateJam();
        setInterval(updateJam, 1000);
        setInterval(syncServerTime, 300000); // Sinkronisasi setiap 5 menit
        
        // Sinkronisasi pertama kali setelah 2 detik
        setTimeout(syncServerTime, 2000);
        
        // Fungsi untuk menampilkan notifikasi toast (hijau, pojok kiri atas)
        function showNotifikasi(pesan, tipe = 'success') {
            const toast = document.createElement('div');
            toast.className = 'toast-custom';
            if (tipe === 'danger') toast.style.background = '#e74c3c';
            
            toast.innerHTML = `
                <span>${pesan}</span>
                <span class="close-toast" onclick="this.parentElement.remove()">x</span>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove setelah 5 detik
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 5000);
        }
    </script>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const overlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const isMobile = window.innerWidth <= 991.98;

            if (isMobile) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
            }
            
            const icon = sidebarToggle.querySelector('i');
            if (sidebar.classList.contains('hidden') || (isMobile && !sidebar.classList.contains('show'))) {
                icon.className = isMobile ? 'fas fa-bars' : 'fas fa-arrow-right';
            } else {
                icon.className = isMobile ? 'fas fa-times' : 'fas fa-arrow-left';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');

            if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
            
            if (overlay) {
                overlay.addEventListener('click', function() {
                    if (window.innerWidth <= 991.98) {
                        toggleSidebar();
                    }
                });
            }

            // Handle window resize
            window.addEventListener('resize', function() {
                const isMobile = window.innerWidth <= 991.98;
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');

                if (!isMobile) {
                    if (sidebar) sidebar.classList.remove('show');
                    if (overlay) overlay.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>