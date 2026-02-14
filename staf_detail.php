<?php
require_once 'config_safe.php';
cekLogin();

$role = $_SESSION['role'];
$user_id = $_GET['user_id'] ?? null;
$view = $_GET['view'] ?? 'detail'; // 'detail' or 'list'
$logged_user_id = $_SESSION['user_id'];

// Ambil data user yang sedang login untuk foto profil di sidebar
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$logged_user_id]);
$user = $stmt_user->fetch();

// Handler untuk Tambah Staf Baru
if (isset($_POST['create_user']) && $role == 'manager') {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $absen_id = $_POST['absen_id'];
    $email = $_POST['email'];
    $telepon = $_POST['telepon'];
    $alamat = $_POST['alamat'];
    $password_input = $_POST['password'];
    $user_role = $_POST['role'] ?? 'staf';
    $can_overtime = isset($_POST['can_overtime']) ? 1 : 0;

    if (empty($nama) || empty($nip) || empty($absen_id) || empty($email) || empty($telepon) || empty($alamat) || empty($password_input)) {
        $_SESSION['msg_error'] = "Semua kolom wajib diisi!";
        header("Location: staf_detail.php?view=list");
        exit();
    }
    if (strlen($password_input) < 6) {
        $_SESSION['msg_error'] = "Password minimal harus 6 karakter!";
        header("Location: staf_detail.php?view=list");
        exit();
    }
    $password = md5($password_input);

    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE absen_id = ?");
        $check->execute([$absen_id]);
        if ($check->fetch()) {
            $_SESSION['msg_error'] = "ID Login sudah digunakan!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (nama, nip, absen_id, email, telepon, alamat, password, role, can_overtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $nip, $absen_id, $email, $telepon, $alamat, $password, $user_role, $can_overtime]);
            $_SESSION['msg_success'] = "Staf baru berhasil ditambahkan: " . $nama;
        }
    } catch (Exception $e) { 
        $_SESSION['msg_error'] = "Gagal menambah staf: " . $e->getMessage(); 
    }
    header("Location: staf_detail.php?view=list");
    exit();
}

// Handler untuk Update Biodata Staf
if (isset($_POST['update_user']) && $role == 'manager') {
    $target_id = $_POST['target_id'];
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $absen_id = $_POST['absen_id'];
    $email = $_POST['email'] ?? null;
    $telepon = $_POST['telepon'] ?? null;
    $alamat = $_POST['alamat'] ?? null;
    $user_role = $_POST['role'];
    $can_overtime = isset($_POST['can_overtime']) ? 1 : 0;
    
    try {
        // Cek ID Absensi duplikat (kecuali milik sendiri)
        $check = $pdo->prepare("SELECT id FROM users WHERE absen_id = ? AND id != ?");
        $check->execute([$absen_id, $target_id]);
        if ($check->fetch()) {
            $_SESSION['msg_error'] = "ID Login sudah digunakan oleh staf lain!";
        } else {
            $sql = "UPDATE users SET nama = ?, nip = ?, absen_id = ?, email = ?, telepon = ?, alamat = ?, role = ?, can_overtime = ?";
            $params = [$nama, $nip, $absen_id, $email, $telepon, $alamat, $user_role, $can_overtime];
            
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) {
                    $_SESSION['msg_error'] = "Password baru minimal harus 6 karakter!";
                    header("Location: staf_detail.php?user_id=" . $target_id);
                    exit();
                }
                $sql .= ", password = ?";
                $params[] = md5($_POST['password']);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $target_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $_SESSION['msg_success'] = "Data staf berhasil diperbarui.";
        }
    } catch (Exception $e) { $_SESSION['msg_error'] = "Gagal memperbarui staf: " . $e->getMessage(); }
    header("Location: staf_detail.php?user_id=" . $target_id);
    exit();
}

// Handler untuk Hapus Staf
if (isset($_POST['delete_user']) && $role == 'manager') {
    $target_id = $_POST['target_id'];
    if ($target_id == $logged_user_id) {
        $_SESSION['msg_error'] = "Anda tidak bisa menghapus akun sendiri!";
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
            $_SESSION['msg_success'] = "Staf berhasil dihapus secara permanen.";
        } catch (Exception $e) { 
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['msg_error'] = "Gagal menghapus staf: " . $e->getMessage(); 
        }
    }
    header("Location: staf_detail.php?view=list");
    exit();
}

if (!$user_id && $role != 'manager') {
    header('Location: dashboard.php');
    exit();
}

// Jika manager membuka tanpa user_id, paksa ke view list
if (!$user_id && $role == 'manager') {
    $view = 'list';
}

if ($view == 'list' && $role == 'manager') {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'nama_asc';
    
    $query = "SELECT * FROM users";
    $params = [];
    
    if (!empty($search)) {
        $query .= " WHERE (nama LIKE ? OR nip LIKE ? OR absen_id LIKE ? OR email LIKE ?)";
        $search_param = "%$search%";
        $params = [$search_param, $search_param, $search_param, $search_param];
    }
    
    switch ($sort) {
        case 'nama_asc': $query .= " ORDER BY nama ASC"; break;
        case 'nama_desc': $query .= " ORDER BY nama DESC"; break;
        case 'role_asc': $query .= " ORDER BY role ASC, nama ASC"; break;
        case 'role_desc': $query .= " ORDER BY role DESC, nama ASC"; break;
        case 'absen_id_asc': $query .= " ORDER BY absen_id ASC"; break;
        case 'absen_id_desc': $query .= " ORDER BY absen_id DESC"; break;
        default: $query .= " ORDER BY nama ASC";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_staf = $stmt->fetchAll();
} else {
    if (!$user_id) {
        $user_id = $_SESSION['user_id'];
    }

    // Ambil data staf
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $staf = $stmt->fetch();

    if (!$staf) {
        header('Location: staf_detail.php?view=list');
        exit();
    }
}

// Proses Reset Shift (Hanya Manager)
if (isset($_POST['reset_shift']) && $_SESSION['role'] == 'manager') {
    $absensi_id = $_POST['absensi_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM absensi WHERE id = ? AND user_id = ?");
        $stmt->execute([$absensi_id, $user_id]);
        $_SESSION['msg_success'] = "Berhasil mereset shift.";
        header("Location: staf_detail.php?user_id=" . $user_id);
        exit();
    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// Proses Check Out Manual (Hanya Manager)
if (isset($_POST['manual_checkout']) && $_SESSION['role'] == 'manager') {
    $absensi_id = $_POST['absensi_id'];
    $jam_keluar = date('H:i:s');
    
    try {
        // Ambil jam masuk
        $stmt = $pdo->prepare("SELECT jam_masuk FROM absensi WHERE id = ?");
        $stmt->execute([$absensi_id]);
        $jam_masuk = $stmt->fetchColumn();
        
        $durasi = hitungDurasi($jam_masuk, $jam_keluar);
        
        $stmt = $pdo->prepare("UPDATE absensi SET jam_keluar = ?, durasi_kerja = ? WHERE id = ?");
        $stmt->execute([$jam_keluar, $durasi, $absensi_id]);
        
        $_SESSION['msg_success'] = "Berhasil melakukan Check Out manual pada jam $jam_keluar.";
        header("Location: staf_detail.php?user_id=" . $user_id);
        exit();
    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// Ambil data absensi dan lembur staf (digabung)
$stmt = $pdo->prepare("
    SELECT * FROM (
        SELECT 
            a.id,
            a.tanggal,
            a.jam_masuk,
            a.jam_keluar,
            a.durasi_kerja,
            COALESCE(a.tipe_absen, 'reguler') as tipe_absensi,
            COUNT(t.id) as total_task,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as task_selesai,
            NULL as keterangan_lembur
        FROM absensi a
        LEFT JOIN tasklist t ON a.id = t.absensi_id
        WHERE a.user_id = ?
        GROUP BY a.id

        UNION ALL

        SELECT 
            al.id,
            al.tanggal,
            al.jam_mulai as jam_masuk,
            al.jam_selesai as jam_keluar,
            al.durasi_lembur as durasi_kerja,
            'lembur' as tipe_absensi,
            0 as total_task,
            0 as task_selesai,
            al.keterangan as keterangan_lembur
        FROM absensi_lembur al
        WHERE al.user_id = ?
    ) as combined_history
    ORDER BY tanggal DESC, jam_masuk DESC 
    LIMIT 50
");
$stmt->execute([$user_id, $user_id]);
$history_list = $stmt->fetchAll();

// Hitung statistik
// total_hari dan hari_selesai memisahkan lembur (hanya menghitung hari kerja reguler/izin/sakit)
$stmt = $pdo->prepare("SELECT COUNT(*) as total_hari FROM absensi WHERE user_id = ? AND (tipe_absen != 'lembur' OR tipe_absen IS NULL)");
$stmt->execute([$user_id]);
$total_hari = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as hari_selesai FROM absensi WHERE user_id = ? AND (tipe_absen != 'lembur' OR tipe_absen IS NULL) AND jam_keluar IS NOT NULL");
$stmt->execute([$user_id]);
$hari_selesai = $stmt->fetchColumn();

// Total durasi menggabungkan durasi kerja reguler dan durasi lembur
$stmt = $pdo->prepare("
    SELECT SEC_TO_TIME(
        COALESCE((SELECT SUM(TIME_TO_SEC(durasi_kerja)) FROM absensi WHERE user_id = ? AND durasi_kerja IS NOT NULL), 0) +
        COALESCE((SELECT SUM(TIME_TO_SEC(durasi_lembur)) FROM absensi_lembur WHERE user_id = ? AND durasi_lembur IS NOT NULL), 0)
    ) as total_durasi
");
$stmt->execute([$user_id, $user_id]);
$total_durasi = $stmt->fetchColumn();

// Ambil daftar tugas staf dengan filter status dan prioritas
$task_filter = $_GET['task_status'] ?? 'all';
$priority_filter = $_GET['task_priority'] ?? 'all';
$task_query = "SELECT * FROM tasklist WHERE assigned_to = ?";
$task_params = [$user_id];

if ($task_filter !== 'all') {
    $task_query .= " AND status = ?";
    $task_params[] = $task_filter;
}

if ($priority_filter !== 'all') {
    $task_query .= " AND priority = ?";
    $task_params[] = $priority_filter;
}

$task_query .= " ORDER BY deadline DESC, end_time DESC";
$stmt_tasks = $pdo->prepare($task_query);
$stmt_tasks->execute($task_params);
$staff_tasks = $stmt_tasks->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Detail Staff</title>
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

        /* Additional Professional Styles */
        .fw-600 { font-weight: 600 !important; }
        .bg-soft-primary { background-color: rgba(0, 74, 173, 0.08) !important; color: #004AAD !important; }
        .bg-soft-success { background-color: rgba(46, 204, 113, 0.12) !important; color: #16a34a !important; }
        .bg-soft-danger { background-color: rgba(231, 76, 60, 0.1) !important; color: #dc2626 !important; }
        .bg-soft-warning { background-color: rgba(241, 196, 15, 0.1) !important; color: #d97706 !important; }
        .hover-primary:hover { color: var(--primary-color) !important; }

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

        .nav-item.active {
            background: white !important;
            color: var(--primary-color) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Main Content */
        .main-content { 
            margin-left: var(--sidebar-width); 
            flex-grow: 1; 
            padding: 40px 50px;
            max-width: calc(100vw - var(--sidebar-width));
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

        /* Stat Card Improvements */
        .stat-card {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .stat-card i {
            width: 45px;
            height: 45px;
            min-width: 45px;
            border-radius: 10px;
            background: rgba(0, 74, 173, 0.08);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-info {
            overflow: hidden;
        }

        .stat-card h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: var(--primary-color);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stat-card p {
            margin: 0;
            font-size: 10px;
            color: #888;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Badge and Table Styling */
        .badge-custom {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .table-custom thead th {
            background-color: #f8fbff;
            color: #888;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #edf2f9;
            padding: 15px 20px;
        }

        .table-custom tbody td {
            padding: 15px 20px;
            font-size: 13px;
        }

        .filter-group .form-select-sm {
            font-size: 12px;
            border-radius: 8px;
            border-color: #eef2f7;
            padding-right: 30px;
        }

        .content-card {
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-header-custom {
            border-bottom: 1px solid #edf2f9;
            padding: 15px 25px;
        }

        .bg-navy { background-color: var(--primary-color) !important; color: white !important; }

        .btn {
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Toggle Button Styles */
        .sidebar-toggle {
            position: fixed;
            left: 270px;
            top: 20px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
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
            background: #003070;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
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

        /* Mobile & Tablet Responsiveness */
        @media (max-width: 991.98px) {
            :root {
                --sidebar-width: 280px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1050;
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
                font-size: 20px;
            }

            .content-card {
                padding: 0 !important;
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
        .sidebar, .main-content, .table-responsive {
            -webkit-overflow-scrolling: touch;
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
                <a href="staf_detail.php?view=list" class="nav-item active">
                    <i class="fas fa-user-friends"></i> Staff
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="nav-item">
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
        <?php if (isset($_SESSION['msg_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['msg_success']; unset($_SESSION['msg_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['msg_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['msg_error']; unset($_SESSION['msg_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($view == 'list'): ?>
            <div class="page-header">
                <h2 class="page-title">Daftar Karyawan</h2>
                <div class="d-flex gap-2 align-items-center">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="view" value="list">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari staf..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary btn-pill px-3">Filter</button>
                        <a href="staf_detail.php?view=list" class="btn btn-sm btn-light btn-pill border px-3">Reset</a>
                    </form>
                    <?php if ($role == 'manager'): ?>
                    <button class="btn btn-primary btn-sm btn-pill px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i> Tambah Staf
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Nama Staf</th>
                                <th>NIP</th>
                                <th>ID Login</th>
                                <th>Role</th>
                                <th class="text-center">Lembur</th>
                                <th>Email / Telepon</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_staf as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $s['foto'] ? 'uploads/profiles/'.$s['foto'] : 'https://ui-avatars.com/api/?name='.urlencode($s['nama']).'&background=random&size=32'; ?>" 
                                             class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                        <span class="fw-bold"><?php echo htmlspecialchars($s['nama']); ?></span>
                                    </div>
                                </td>
                                <td><small class="text-muted fw-bold"><?php echo htmlspecialchars($s['nip'] ?? '-'); ?></small></td>
                                <td><code><?php echo htmlspecialchars($s['absen_id']); ?></code></td>
                                <td>
                                    <span class="badge-custom <?php echo $s['role'] == 'manager' ? 'bg-danger' : 'bg-primary'; ?> text-white">
                                        <?php echo strtoupper($s['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['can_overtime']): ?>
                                        <span class="badge bg-soft-success text-success rounded-pill px-2" style="font-size: 10px;">AKTIF</span>
                                    <?php else: ?>
                                        <span class="badge bg-soft-secondary text-muted rounded-pill px-2" style="font-size: 10px;">NON-AKTIF</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 11px;">
                                        <i class="fas fa-envelope me-1 text-muted"></i> <?php echo $s['email'] ?: '-'; ?><br>
                                        <i class="fas fa-phone me-1 text-muted"></i> <?php echo $s['telepon'] ?: '-'; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="staf_detail.php?user_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </a>
                                        <?php if ($s['id'] != $logged_user_id): ?>
                                        <form method="POST" onsubmit="return confirm('Hapus staf ini secara permanen?')">
                                            <input type="hidden" name="target_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                <i class="fas fa-trash"></i>
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

            <!-- Modal Tambah Staf dipindahkan ke luar agar global -->

        <?php else: ?>
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="staf_detail.php?view=list" class="btn btn-outline-secondary btn-sm rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="page-title m-0">Detail Karyawan</h2>
                </div>
                <div class="text-end d-flex align-items-center gap-3">
                    <div class="text-end">
                        <h5 class="m-0 text-dark fw-bold"><?php echo htmlspecialchars($staf['nama']); ?></h5>
                        <div class="small text-muted mb-1">NIP: <?php echo htmlspecialchars($staf['nip'] ?? '-'); ?></div>
                        <span class="badge-custom <?php echo $staf['role'] == 'manager' ? 'bg-danger' : 'bg-primary'; ?> text-white">
                            <?php echo strtoupper($staf['role']); ?>
                        </span>
                    </div>
                    <?php if ($role == 'manager'): ?>
                    <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#editBiodataModal">
                        <i class="fas fa-edit me-1"></i> Edit Biodata
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Edit Biodata -->
            <?php if ($role == 'manager'): ?>
            <div class="modal fade" id="editBiodataModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-primary">Edit Biodata Staf</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="target_id" value="<?php echo $staf['id']; ?>">
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nama Lengkap</label>
                                        <input type="text" name="nama" class="form-control" value="<?php echo htmlspecialchars($staf['nama']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">NIP (Wajib)</label>
                                        <input type="text" name="nip" class="form-control" value="<?php echo htmlspecialchars($staf['nip'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">ID Login</label>
                                        <input type="text" name="absen_id" class="form-control" value="<?php echo htmlspecialchars($staf['absen_id']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Role</label>
                                        <select name="role" class="form-select">
                                            <option value="staf" <?php echo $staf['role'] == 'staf' ? 'selected' : ''; ?>>Staf</option>
                                            <option value="manager" <?php echo $staf['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Password Baru</label>
                                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($staf['email']); ?>">
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="can_overtime" id="edit_can_overtime" <?php echo $staf['can_overtime'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label small fw-bold" for="edit_can_overtime">Otoritas Lembur</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Nomor Telepon</label>
                                        <input type="text" name="telepon" class="form-control" value="<?php echo htmlspecialchars($staf['telepon']); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Alamat</label>
                                        <textarea name="alamat" class="form-control" rows="2"><?php echo htmlspecialchars($staf['alamat']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="update_user" class="btn btn-primary px-4">Update Biodata</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-calendar-check"></i>
                    <div class="stat-info">
                        <h4><?php echo $total_hari; ?></h4>
                        <p>Total Hari Kerja</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-user-check"></i>
                    <div class="stat-info">
                        <h4><?php echo $hari_selesai; ?></h4>
                        <p>Hari Selesai</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <?php 
                        // Clean up duration format (remove microseconds if exist)
                        $clean_duration = $total_durasi ?: '00:00:00';
                        if (strpos($clean_duration, '.') !== false) {
                            $clean_duration = explode('.', $clean_duration)[0];
                        }
                        ?>
                        <h4><?php echo $clean_duration; ?></h4>
                        <p>Total Durasi Kerja</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card mb-4">
            <div class="card-header-custom bg-light d-flex justify-content-between align-items-center">
                <h5 class="text-primary mb-0"><i class="fas fa-tasks me-2"></i>Daftar Tugas Staf</h5>
                <div class="filter-group">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <select name="task_status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $task_filter == 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo $task_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $task_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                        <select name="task_priority" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>Semua Prioritas</option>
                            <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </form>
                </div>
            </div>
             <div class="table-responsive table-scroll">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Nama Tugas</th>
                            <th>Deadline</th>
                            <th>Waktu</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_tasks)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Tidak ada tugas ditemukan untuk filter ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_tasks as $task): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($task['task_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($task['deadline'])); ?></td>
                                <td><?php echo $task['start_time'] ?: '--:--'; ?> - <?php echo $task['end_time'] ?: '--:--'; ?></td>
                                <td>
                                    <span class="badge-custom <?php 
                                        echo $task['priority'] == 'high' ? 'bg-danger' : ($task['priority'] == 'medium' ? 'bg-warning text-dark' : 'bg-success'); 
                                    ?> text-white">
                                        <?php echo strtoupper($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['status'] == 'completed'): ?>
                                        <span class="badge-custom bg-success text-white">COMPLETED</span>
                                    <?php elseif ($task['status'] == 'in_progress'): ?>
                                        <span class="badge-custom bg-info text-white">IN PROGRESS</span>
                                    <?php else: ?>
                                        <span class="badge-custom bg-secondary text-white">PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($task['deskripsi'] ?: '-'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h5>Riwayat Kehadiran & Lembur</h5>
            </div>
             <div class="table-responsive table-scroll">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Jam Masuk/Mulai</th>
                            <th>Jam Keluar/Selesai</th>
                            <th>Durasi</th>
                            <th>Task</th>
                            <th>Status</th>
                            <?php if ($_SESSION['role'] == 'manager'): ?>
                            <th>Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_list)): ?>
                            <tr>
                                <td colspan="<?php echo $_SESSION['role'] == 'manager' ? '8' : '7'; ?>" class="text-center py-4 text-muted">Belum ada riwayat data.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history_list as $item): ?>
                            <tr>
                                <td class="fw-bold"><?php echo date('M j, Y', strtotime($item['tanggal'])); ?></td>
                                <td>
                                    <?php if ($item['tipe_absensi'] == 'sakit'): ?>
                                        <span class="badge-custom bg-danger text-white">SAKIT</span>
                                    <?php elseif ($item['tipe_absensi'] == 'cuti'): ?>
                                        <span class="badge-custom bg-warning text-dark">IZIN</span>
                                    <?php elseif ($item['tipe_absensi'] == 'lembur'): ?>
                                        <span class="badge-custom bg-navy">LEMBUR</span>
                                    <?php else: ?>
                                        <span class="badge-custom bg-success text-white">REGULER</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['jam_masuk']; ?></td>
                                <td><?php echo $item['jam_keluar'] ?: '-'; ?></td>
                                <td class="fw-bold <?php echo $item['tipe_absensi'] == 'lembur' ? 'text-danger' : 'text-primary'; ?>">
                                    <?php echo $item['durasi_kerja'] ?: '-'; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipe_absensi'] != 'lembur' && $item['total_task'] > 0): ?>
                                        <span class="fw-bold"><?php echo $item['task_selesai']; ?>/<?php echo $item['total_task']; ?></span>
                                    <?php elseif ($item['tipe_absensi'] == 'lembur'): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['keterangan_lembur'] ?: '-'); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['jam_keluar']): ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i>SELESAI</span>
                                    <?php else: ?>
                                        <span class="text-info small fw-bold animate-pulse"><i class="fas fa-spinner fa-spin me-1"></i>AKTIF</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['role'] == 'manager'): ?>
                                <td class="d-flex gap-2">
                                    <?php if ($item['tipe_absensi'] != 'lembur'): ?>
                                        <?php if (!$item['jam_keluar']): ?>
                                        <form method="POST" onsubmit="return confirm('Check Out staf ini secara manual?')">
                                            <input type="hidden" name="absensi_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="manual_checkout" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mereset shift ini? Staf harus absen ulang.')">
                                            <input type="hidden" name="absensi_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="reset_shift" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; // End of view check ?>
    </div>

    <!-- Modal Tambah Staf (Global) -->
    <?php if ($role == 'manager'): ?>
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-primary">Tambah Staf Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nama Lengkap<span class="text-danger">*</span><</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">NIP <span class="text-danger">*</span></label>
                                <input type="text" name="nip" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">ID Login <span class="text-danger">*</span></label>
                                <input type="text" name="absen_id" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="staf">Staf</option>
                                    <option value="manager">Manager</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="can_overtime" id="add_can_overtime" checked>
                                    <label class="form-check-label small fw-bold" for="add_can_overtime">Otoritas Lembur</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nomor Telepon <span class="text-danger">*</span></label>
                                <input type="text" name="telepon" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Alamat <span class="text-danger">*</span></label>
                                <textarea name="alamat" class="form-control" rows="2" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="create_user" class="btn btn-primary px-4">Simpan Staf</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script for Sidebar Toggle -->
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
        });
    </script>
</body>
</html>