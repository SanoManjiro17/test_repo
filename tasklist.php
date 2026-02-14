<?php
require_once 'config_safe.php';
cekLogin();

$user_id = $_SESSION['user_id'];
$tanggal_sekarang = date('Y-m-d');

// Ambil data user untuk foto profil
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// Cari absensi hari ini (opsional untuk task)
$stmt = $pdo->prepare("SELECT id FROM absensi WHERE user_id = ? AND tanggal = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetch();
$absensi_id = $absensi_hari_ini ? $absensi_hari_ini['id'] : null;

// Proses tambah task
if (isset($_POST['tambah_task'])) {
    $task_name = $_POST['task_name'];
    $deskripsi = $_POST['deskripsi'] ?? '';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['deadline'] ?? $start_date;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $priority = $_POST['priority'] ?? 'low';
    $is_overtime = isset($_POST['is_overtime']) ? 1 : 0;
    
    // Buat rentang tanggal
    $begin = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Agar tanggal akhir ikut terhitung
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);

    if ($_SESSION['role'] == 'manager' && !empty($_POST['assigned_users'])) {
        $assigned_users = (array)$_POST['assigned_users'];
        
        foreach ($assigned_users as $target_id) {
            $stmt = $pdo->prepare("INSERT INTO tasklist (user_id, assigned_to, task_name, deskripsi, start_date, deadline, start_time, end_time, priority, is_overtime, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $target_id, $task_name, $deskripsi, $start_date, $end_date, $start_time, $end_time, $priority, $is_overtime]);
        }
    } else {
        // Task personal (untuk diri sendiri)
        $stmt = $pdo->prepare("INSERT INTO tasklist (user_id, task_name, deskripsi, start_date, deadline, start_time, end_time, priority, is_overtime, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $task_name, $deskripsi, $start_date, $end_date, $start_time, $end_time, $priority, $is_overtime]);
    }
    
    header('Location: tasklist.php');
    exit();
}

// Proses edit task
if (isset($_POST['edit_task'])) {
    $task_id = $_POST['task_id'];
    $task_name = $_POST['task_name'];
    $deskripsi = $_POST['deskripsi'] ?? '';
    $deadline = $_POST['deadline'];
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $priority = $_POST['priority'] ?? 'low';
    $status = $_POST['status'] ?? 'pending';
    $assigned_to = $_POST['assigned_to'] ?? null;
    $is_overtime = isset($_POST['is_overtime']) ? 1 : 0;
    
    if ($_SESSION['role'] == 'manager') {
        $stmt = $pdo->prepare("UPDATE tasklist SET task_name = ?, deskripsi = ?, deadline = ?, start_time = ?, end_time = ?, priority = ?, status = ?, assigned_to = ?, is_overtime = ? WHERE id = ?");
        $stmt->execute([$task_name, $deskripsi, $deadline, $start_time, $end_time, $priority, $status, $assigned_to, $is_overtime, $task_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE tasklist SET task_name = ?, deskripsi = ?, deadline = ?, start_time = ?, end_time = ?, priority = ?, status = ?, is_overtime = ? WHERE id = ? AND (user_id = ? OR assigned_to = ?)");
        $stmt->execute([$task_name, $deskripsi, $deadline, $start_time, $end_time, $priority, $status, $is_overtime, $task_id, $user_id, $user_id]);
    }
    
    header('Location: tasklist.php');
    exit();
}

// Proses update status task
if (isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    
    // Staf bisa update status tugas miliknya sendiri ATAU tugas yang ditugaskan kepadanya
    $stmt = $pdo->prepare("UPDATE tasklist SET status = ? WHERE id = ? AND (user_id = ? OR assigned_to = ?)");
    $stmt->execute([$status, $task_id, $user_id, $user_id]);
    
    header('Location: tasklist.php');
    exit();
}

// Proses hapus task
if (isset($_POST['hapus_task'])) {
    $task_id = $_POST['task_id'];
    
    // Staf hanya bisa hapus tugas yang mereka buat sendiri
    $stmt = $pdo->prepare("DELETE FROM tasklist WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    
    header('Location: tasklist.php');
    exit();
}

// Cek status absensi hari ini untuk navigasi sidebar
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_hari_ini = $stmt->fetch();

// Ambil semua task milik user, yang ditugaskan ke user, atau yang diberikan oleh manager kepada staf
if ($_SESSION['role'] == 'manager') {
    $stmt = $pdo->prepare("SELECT t.*, u.nama as assigned_name 
                         FROM tasklist t 
                         LEFT JOIN users u ON t.assigned_to = u.id 
                         WHERE t.user_id = ? OR t.assigned_to = ? 
                         ORDER BY t.created_at DESC");
    $stmt->execute([$user_id, $user_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM tasklist WHERE assigned_to = ? OR (user_id = ? AND assigned_to IS NULL) ORDER BY created_at DESC");
    $stmt->execute([$user_id, $user_id]);
}
$tasks = $stmt->fetchAll();

// Cek status absensi untuk sidebar
$stmt = $pdo->prepare("SELECT * FROM absensi WHERE user_id = ? AND tanggal = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id, $tanggal_sekarang]);
$absensi_sidebar = $stmt->fetch();

// Ambil data semua staf untuk pilihan penugasan (hanya untuk manager)
$staf_list = [];
if ($_SESSION['role'] == 'manager') {
    $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE role = 'staf' ORDER BY nama ASC");
    $stmt->execute();
    $staf_list = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daftar Tugas</title>
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

        .profile-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
            color: white;
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
            font-weight: 700;
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
            color: white;
            background: rgba(255,255,255,0.15);
            border-left: 5px solid white;
            font-weight: 700;
            padding-left: 25px;
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
            display: flex;
            align-items: center;
            padding: 14px 25px;
            color: rgba(255,255,255,0.75);
            text-decoration: none;
            font-weight: 500;
            font-size: 14.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 5px solid transparent;
            margin-bottom: 4px;
            min-height: 48px; /* Improved touch target */
        }

        .nav-item i {
            width: 24px;
            margin-right: 15px;
            font-size: 18px;
            text-align: center;
            opacity: 0.8;
        }

        .nav-item:hover, .nav-item.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border-left: 5px solid white;
            padding-left: 25px;
            font-weight: 700;
        }

        /* Main Content */
        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 40px; 
            background-color: var(--light-bg); 
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
            transition: all 0.3s ease;
            overflow-x: hidden;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
                padding-top: 80px;
                width: 100%;
                max-width: 100%;
            }
            .sidebar-toggle {
                display: flex !important;
                left: 15px !important;
                top: 15px !important;
                width: 45px;
                height: 45px;
                z-index: 1060;
                background: white;
                border: none;
                border-radius: 12px;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                cursor: pointer;
                position: fixed;
                color: var(--primary-color);
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .page-title {
                font-size: 22px;
            }
            .btn-add-task {
                width: 100%;
                justify-content: center;
            }
            
            /* Calendar mobile styling */
            .calendar-header {
                padding: 15px;
                font-size: 18px;
            }
            .calendar-nav {
                padding: 5px 15px;
                font-size: 18px;
            }
            .calendar-day {
                min-height: 80px;
                padding: 5px;
            }
            .day-number {
                font-size: 14px;
            }
            .task-badge {
                font-size: 10px;
                padding: 4px 6px;
                border-radius: 4px;
            }
            .calendar-day.today::after {
                display: none; /* Hide 'Hari Ini' text on mobile to save space */
            }
        }

        /* Adjustments for small phones */
        @media (max-width: 575.98px) {
            .calendar-day-label {
                font-size: 10px;
                letter-spacing: 0;
            }
            .calendar-day {
                min-height: 60px;
            }
            .task-badge {
                display: none; /* Hide task badges on very small screens, use dots or just numbers */
            }
            .calendar-day::after {
                content: attr(data-tasks);
                position: absolute;
                bottom: 5px;
                right: 5px;
                font-size: 10px;
                font-weight: 700;
                color: var(--primary-color);
            }
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
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

        /* Action Button Sidebar */
        .nav-item-action {
            margin: 15px 25px;
            padding: 12px 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s;
            border: none !important;
            font-size: 15px;
        }
        .btn-checkin {
            background-color: #48bb78; /* Hijau Sukses */
            color: white !important;
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
        }
        .btn-checkin:hover { background-color: #38a169; transform: translateY(-2px); }
        
        .btn-checkout {
            background-color: #ed8936; /* Oranye */
            color: white !important;
            box-shadow: 0 4px 12px rgba(237, 137, 54, 0.3);
        }
        .btn-checkout:hover { background-color: #dd6b20; transform: translateY(-2px); }

        .sign-out-btn { padding: 15px 25px; color: white; text-decoration: none; font-weight: 700; font-size: 18px; display: flex; align-items: center; gap: 10px; }

        .page-title {
            color: var(--primary-color);
            font-weight: 800;
            margin: 0;
            font-size: 28px;
            position: relative;
        }

        .btn-add-task {
            background: #004AAD; color: white; border-radius: 50px; padding: 10px 25px; 
            font-weight: 700; border: none; display: flex; align-items: center; gap: 10px;
            transition: all 0.3s; box-shadow: 0 4px 15px rgba(0, 74, 173, 0.2);
            font-size: 14px;
        }
        .btn-add-task:hover { background: #003a8a; transform: translateY(-2px); color: white; }

        /* Calendar Styling - Larger Version */
        .calendar-container {
            background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%; margin: 0 auto; border: 1px solid #e2e8f0;
        }
        .calendar-header {
            background: #004AAD; color: white; padding: 20px; text-align: center;
            font-size: 24px; font-weight: 800; display: flex; justify-content: space-between; align-items: center;
        }
        .calendar-nav {
            color: white; text-decoration: none; padding: 8px 18px; font-size: 20px; transition: all 0.2s;
            background: rgba(255,255,255,0.2); border-radius: 8px;
        }
        .calendar-nav:hover { background: rgba(255,255,255,0.2); color: white; }
        
        .calendar-grid {
            display: grid; 
            grid-template-columns: repeat(7, minmax(0, 1fr));
            width: 100%;
        }
        .calendar-day-label {
            padding: 15px 5px; text-align: center; font-weight: 700; color: #4a5568;
            border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0;
            background: #f8fafc; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;
        }
        .calendar-day-label:nth-child(7n) { border-right: none; }
        .calendar-day {
            min-height: 150px; padding: 10px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
            position: relative; background: white; cursor: pointer; transition: background 0.2s;
            min-width: 0; /* Penting agar grid item bisa shrink */
        }
        .calendar-day:hover:not(.other-month) { background-color: #f1f5f9; }
        .calendar-day:nth-child(7n) { border-right: none; }
        .day-number { font-weight: 700; color: #4a5568; margin-bottom: 8px; display: block; font-size: 18px; }
        .calendar-day.today { 
            background-color: #f0f7ff; 
            border: 2px solid #004AAD !important;
            z-index: 1;
        }
        .calendar-day.today .day-number {
            background: #004AAD;
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: -4px 0 0 -4px;
        }
        
        .task-badge {
            font-size: 13px; padding: 8px 12px; border-radius: 8px; margin-bottom: 8px;
            display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            color: white; font-weight: 700; text-decoration: none; border-left: 5px solid rgba(254, 8, 8, 0.2);
            transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .task-badge:hover {
            transform: scale(1.02); box-shadow: 0 4px 8px rgba(0,0,0,0.1); color: white;
        }
        .badge-pending { background-color: #c82424ff; color: #fff; }
        .badge-progress { background-color: #d3d831ff; color: #fff; }
        .badge-completed { background-color: #45bb6eff; color: #fff; }
        
        .task-badge small { font-size: 11px; opacity: 0.9; margin-right: 5px; font-weight: 600; }
        
        .priority-high { border-left-color: #f53939ff !important; }
        .priority-medium { border-left-color: #ed8936 !important; }
        
        /* Task Detail Pop-up Styling */
        .detail-label { font-size: 12px; color: #718096; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .detail-value { font-size: 16px; color: #2d3748; font-weight: 700; margin-bottom: 15px; }
        .detail-notes { background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #004AAD; font-style: italic; }

        /* Day Summary Modal Styling */
        .day-summary-item {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 6px solid #e2e8f0;
            transition: all 0.2s;
            cursor: pointer;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .day-summary-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .summary-pending { border-left-color: #f56565; }
        .summary-progress { border-left-color: #ecc94b; }
        .summary-completed { border-left-color: #48bb78; }
        .summary-task-name { font-weight: 800; color: #2d3748; margin-bottom: 5px; font-size: 16px; }
        .summary-task-meta { font-size: 12px; color: #718096; display: flex; gap: 15px; }
        .summary-task-meta i { margin-right: 5px; }
        
        .other-month { background-color: #f7fafc; color: #cbd5e0; }



        /* Mobile Responsiveness */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .sidebar-toggle {
                left: 20px !important;
                z-index: 1060;
            }
            .sidebar-toggle.active {
                left: 270px !important;
            }
        }

        @media (max-width: 768px) {
            .sidebar-toggle {
                width: 35px;
                height: 35px;
                top: 15px;
            }
            .sidebar-toggle.active {
                left: 265px !important;
            }
            .calendar-day {
                min-height: 100px !important;
            }
            .task-badge {
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
    <?php
    // Logika Kalender
    $month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
    $year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
    
    // Hitung bulan sebelumnya dan sesudahnya
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }
    
    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }

    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $numberDays = date('t', $firstDayOfMonth);
    $dateComponents = getdate($firstDayOfMonth);
    $monthName = date('F', $firstDayOfMonth);
    $dayOfWeek = $dateComponents['wday']; // 0 (Minggu) - 6 (Sabtu)
    
    // Nama bulan Indonesia
    $bulanIndo = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $displayMonth = $bulanIndo[$monthName];

    // Kelompokkan task berdasarkan rentang tanggal
    $tasksByDate = [];
    foreach ($tasks as $task) {
        $start = !empty($task['start_date']) ? $task['start_date'] : $task['deadline'];
        $end = $task['deadline'];
        
        $current = new DateTime($start);
        $last = new DateTime($end);
        $last->modify('+1 day');
        
        $period = new DatePeriod($current, new DateInterval('P1D'), $last);
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $tasksByDate[$dateStr][] = $task;
        }
    }
    ?>

    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-arrow-left"></i>
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
                <a href="tasklist.php" class="nav-item active">
                    <i class="fas fa-clipboard-check"></i> Tugas
                </a>
                <a href="staf_detail.php?view=list" class="nav-item">
                    <i class="fas fa-user-friends"></i> Staff
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="laporan.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i> Laporan
                </a>
                <a href="tasklist.php" class="nav-item active">
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
        <div class="d-flex justify-content-between align-items-center mb-4" style="width: 100%; margin: 0 auto 30px auto;">
            <h2 class="page-title m-0">Daftar Tugas</h2>
            <div class="d-flex gap-2">
                <?php if ($_SESSION['role'] == 'manager'): ?>
                    <button class="btn btn-danger rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#overtimeModal">
                        <i class="fas fa-user-clock me-2"></i> Otorisasi Lembur
                    </button>
                <?php endif; ?>
                <button class="btn-add-task" onclick="prepareAddModal()">
                    Tambah Tugas Baru <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>

        <div class="calendar-container">
            <div class="calendar-header">
                <a href="?m=<?php echo $prevMonth; ?>&y=<?php echo $prevYear; ?>" class="calendar-nav"><i class="fas fa-chevron-left"></i></a>
                <span><?php echo $displayMonth . ' ' . $year; ?></span>
                <a href="?m=<?php echo $nextMonth; ?>&y=<?php echo $nextYear; ?>" class="calendar-nav"><i class="fas fa-chevron-right"></i></a>
            </div>
            <div class="calendar-grid">
                <div class="calendar-day-label">Minggu</div>
                <div class="calendar-day-label">Senin</div>
                <div class="calendar-day-label">Selasa</div>
                <div class="calendar-day-label">Rabu</div>
                <div class="calendar-day-label">Kamis</div>
                <div class="calendar-day-label">Jumat</div>
                <div class="calendar-day-label">Sabtu</div>

                <?php
                // Baris Kosong Sebelum Tanggal 1
                for($i = 0; $i < $dayOfWeek; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }

                // Loop Tanggal
                for($currentDay = 1; $currentDay <= $numberDays; $currentDay++) {
                    $fullDate = sprintf('%04d-%02d-%02d', $year, $month, $currentDay);
                    $isToday = ($fullDate == date('Y-m-d')) ? 'today' : '';
                    
                    echo '<div class="calendar-day '.$isToday.'" onclick="handleDayClick(\''.$fullDate.'\', this)">';
                    echo '<span class="day-number">'.$currentDay.'</span>';
                    
                    if (isset($tasksByDate[$fullDate])) {
                        // Simpan data task untuk JS
                        $dayTasksJson = htmlspecialchars(json_encode($tasksByDate[$fullDate]));
                        echo '<div class="task-list-wrapper" data-tasks=\''.$dayTasksJson.'\'>';
                        foreach ($tasksByDate[$fullDate] as $t) {
                            $statusClass = 'badge-' . $t['status'];
                            $priorityClass = isset($t['priority']) ? 'priority-' . $t['priority'] : '';
                            $timeStr = !empty($t['end_time']) ? '<i class="far fa-clock me-1"></i>' . date('H:i', strtotime($t['end_time'])) . ' ' : '';
                            
                            // Menambahkan atribut data untuk JavaScript
                            $taskData = htmlspecialchars(json_encode($t));
                            echo '<a href="javascript:void(0)" class="task-badge '.$statusClass.' '.$priorityClass.'" 
                                    onclick="event.stopPropagation(); openViewModal('.$taskData.')"
                                    title="Klik untuk melihat detail">';
                            echo '<small>'.$timeStr.'</small>';
                            if ($_SESSION['role'] == 'manager' && !empty($t['assigned_name'])) {
                                echo '<span class="badge bg-light text-dark p-1 me-1" style="font-size: 9px;">@' . explode(' ', $t['assigned_name'])[0] . '</span>';
                            }
                            echo htmlspecialchars($t['task_name']);
                            echo '</a>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }

                // Sisa Grid
                $totalCells = $dayOfWeek + $numberDays;
                $remainingCells = (ceil($totalCells / 7) * 7) - $totalCells;
                for($i = 0; $i < $remainingCells; $i++) {
                    echo '<div class="calendar-day other-month"></div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Modal Ringkasan Harian -->
    <div class="modal fade" id="daySummaryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="modal-header" style="border-bottom: none; padding: 30px 30px 10px 30px;">
                    <div>
                        <h5 class="modal-title" style="font-weight: 900; color: #3e4a89; font-size: 24px;">Tugas Tanggal</h5>
                        <p id="summary_display_date" class="text-muted m-0 fw-bold" style="font-size: 14px;"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 10px 30px 30px 30px;">
                    <div id="day_tasks_container" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                        <!-- Daftar task akan diisi via JS -->
                    </div>
                    <div id="no_tasks_message" class="text-center py-4" style="display: none;">
                        <img src="https://cdn-icons-png.flaticon.com/512/6194/6194029.png" style="width: 80px; opacity: 0.5; margin-bottom: 15px;">
                        <p class="text-muted fw-bold">Tidak ada tugas untuk tanggal ini.</p>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: none; padding: 0 30px 30px 30px;">
                    <button type="button" class="btn btn-primary w-100 py-3" style="border-radius: 12px; font-weight: 800; background: #004AAD; border: none;" id="btn_add_from_summary">
                        <i class="fas fa-plus me-2"></i> Tambah Tugas Baru
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Task -->
    <div class="modal fade" id="addTaskModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <form method="POST">
                    <div class="modal-header border-0 pb-0" style="padding: 30px 30px 10px 30px;">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3" id="modal_icon_container">
                                <i class="fas fa-plus text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-800 text-dark mb-0" id="addTaskModalLabel" style="font-size: 22px;">Tambah Tugas Baru</h5>
                                <p class="text-muted mb-0" style="font-size: 13px;">Lengkapi detail tugas di bawah ini</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 20px 30px 30px 30px;">
                        <!-- Alert Info Kalender (Hidden by default) -->
                        <div id="calendar_info_alert" class="alert alert-primary border-0 mb-4" style="display: none; border-radius: 15px; background: linear-gradient(45deg, #eef2ff, #e0e7ff);">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-calendar-check fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <small class="text-primary fw-bold d-block">Penjadwalan Kalender</small>
                                    <span id="selected_date_display" class="text-dark fw-bold" style="font-size: 15px;"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Nama Tugas / Judul <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-tasks text-muted"></i></span>
                                <input type="text" name="task_name" class="form-control bg-light border-0" required placeholder="Apa yang ingin dikerjakan?" style="padding: 12px;">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6" id="start_date_container">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Tanggal Mulai <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-alt text-muted"></i></span>
                                    <input type="date" name="start_date" id="modal_start_date" class="form-control bg-light border-0" required value="<?php echo date('Y-m-d'); ?>" style="padding: 12px;">
                                </div>
                            </div>
                            <div class="col-md-6" id="deadline_container">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Tanggal Selesai <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-check text-muted"></i></span>
                                    <input type="date" name="deadline" id="modal_deadline" class="form-control bg-light border-0" required value="<?php echo date('Y-m-d'); ?>" style="padding: 12px;">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Jam Mulai</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="far fa-clock text-muted"></i></span>
                                    <input type="time" name="start_time" class="form-control bg-light border-0" style="padding: 12px;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Jam Selesai <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-clock text-muted"></i></span>
                                    <input type="time" name="end_time" class="form-control bg-light border-0" required value="17:00" style="padding: 12px;">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Prioritas Tugas</label>
                            <div class="d-flex gap-3">
                                <div class="form-check custom-radio">
                                    <input class="form-check-input d-none" type="radio" name="priority" id="p_low" value="low" checked>
                                    <label class="btn btn-outline-success w-100 py-2 fw-bold" for="p_low" style="border-radius: 10px; min-width: 90px;">Rendah</label>
                                </div>
                                <div class="form-check custom-radio">
                                    <input class="form-check-input d-none" type="radio" name="priority" id="p_medium" value="medium">
                                    <label class="btn btn-outline-warning w-100 py-2 fw-bold" for="p_medium" style="border-radius: 10px; min-width: 90px;">Sedang</label>
                                </div>
                                <div class="form-check custom-radio">
                                    <input class="form-check-input d-none" type="radio" name="priority" id="p_high" value="high">
                                    <label class="btn btn-outline-danger w-100 py-2 fw-bold" for="p_high" style="border-radius: 10px; min-width: 90px;">Tinggi</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Catatan Detail</label>
                            <textarea name="deskripsi" class="form-control bg-light border-0" rows="3" placeholder="Tambahkan instruksi atau detail tugas..." style="border-radius: 12px; padding: 12px;"></textarea>
                        </div>

                        <?php if ($_SESSION['role'] == 'manager'): ?>
                        <div class="mb-0">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2 d-block">Delegasikan Kepada</label>
                            <div class="border-0 rounded-4 p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                <div class="form-check mb-2 pb-2 border-bottom border-secondary border-opacity-10">
                                    <input class="form-check-input" type="checkbox" id="checkAllStaf" onchange="toggleAllStaf(this)">
                                    <label class="form-check-label fw-bold text-primary" for="checkAllStaf">Pilih Semua Pegawai</label>
                                </div>
                                <div id="stafCheckboxList">
                                    <?php foreach ($staf_list as $staf): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input staf-checkbox" type="checkbox" name="assigned_users[]" 
                                                   value="<?php echo $staf['id']; ?>" id="staf_<?php echo $staf['id']; ?>"
                                                   onchange="updateCheckAllState()">
                                            <label class="form-check-label" for="staf_<?php echo $staf['id']; ?>">
                                                <?php echo htmlspecialchars($staf['nama']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light px-4 py-2 fw-bold" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                        <button type="submit" name="tambah_task" id="btn_submit_task" class="btn btn-primary px-5 py-2 fw-bold" style="background: #004AAD; border: none; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 74, 173, 0.3);">
                            Simpan Tugas
                        </button>
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
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-clock me-2"></i>Otorisasi Lembur Staf</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Aktifitas Lembur</label>
                            <input type="text" name="task_name" class="form-control" required value="Lembur Kerja">
                            <input type="hidden" name="is_overtime" value="1">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tanggal Akhir</label>
                                <input type="date" name="deadline" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold d-block">Pilih Karyawan yang Diizinkan Lembur</label>
                            <div class="border rounded p-3 bg-light" style="max-height: 250px; overflow-y: auto;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input" type="checkbox" id="checkAllLembur" onchange="toggleAllLembur(this)">
                                    <label class="form-check-label fw-bold text-danger" for="checkAllLembur">Pilih Semua Staf</label>
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
                        <button type="submit" name="tambah_task" class="btn btn-danger px-4 fw-bold">Berikan Izin Lembur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal View Task (Pop-up Modern) -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="modal-header border-0 pb-0" style="padding: 30px 30px 10px 30px;">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                            <i class="fas fa-info-circle text-primary fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-800 text-dark mb-0" style="font-size: 22px;">Detail Tugas</h5>
                            <p class="text-muted mb-0" style="font-size: 13px;">Informasi lengkap mengenai tugas ini</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 20px 30px 30px 30px;">
                    <div class="mb-4">
                        <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Judul Tugas</label>
                        <h4 class="fw-800 text-dark" id="view_task_name" style="font-size: 1.25rem;"></h4>
                    </div>
                    
                    <div id="view_assigned_wrapper" class="mb-4" style="display: none;">
                        <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Ditugaskan Kepada</label>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle text-primary me-2"></i>
                            <span class="fw-700 text-primary" id="view_task_assigned"></span>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Waktu & Jam</label>
                            <div class="d-flex align-items-center">
                                <i class="far fa-clock text-muted me-2"></i>
                                <span class="fw-600 text-dark" id="view_task_time"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Prioritas</label>
                            <div id="view_task_priority"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Status Saat Ini</label>
                        <div class="d-flex align-items-center gap-2">
                            <span id="view_task_status" class="badge" style="font-size: 13px; padding: 8px 16px; border-radius: 8px;"></span>
                        </div>
                    </div>

                    <div id="quick_status_actions" class="mb-4 p-3 rounded-3 bg-light border-start border-primary border-4" style="display: none;">
                        <label class="text-dark small text-uppercase fw-800 mb-2 d-block">Aksi Cepat Status</label>
                        <div class="d-flex gap-2">
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="task_id" id="quick_task_id_progress">
                                <input type="hidden" name="status" value="progress">
                                <button type="submit" name="update_status" class="btn btn-primary w-100 fw-bold py-2" style="font-size: 13px; border-radius: 8px;">Mulai</button>
                            </form>
                            <form method="POST" class="flex-grow-1">
                                <input type="hidden" name="task_id" id="quick_task_id_completed">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" name="update_status" class="btn btn-success w-100 fw-bold py-2" style="font-size: 13px; border-radius: 8px;">Selesai</button>
                            </form>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="text-muted small text-uppercase fw-700 mb-1 d-block">Catatan / Deskripsi</label>
                        <div class="p-3 bg-light rounded-3 text-dark" id="view_task_notes" style="font-size: 14px; line-height: 1.6; border-radius: 12px;"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2 fw-bold" style="border-radius: 12px;" onclick="switchToEdit()">Edit Tugas</button>
                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold" style="background: #004AAD; border: none; border-radius: 12px;" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Edit Task (Professional Upgrade) -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <form method="POST">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    <div class="modal-header border-0 pb-0" style="padding: 30px 30px 10px 30px;">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-3 me-3">
                                <i class="fas fa-edit text-warning fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-800 text-dark mb-0" style="font-size: 22px;">Edit Tugas</h5>
                                <p class="text-muted mb-0" style="font-size: 13px;">Perbarui detail informasi tugas</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 20px 30px 30px 30px;">
                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Nama Tugas / Judul <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-tasks text-muted"></i></span>
                                <input type="text" name="task_name" id="edit_task_name" class="form-control bg-light border-0" required style="padding: 12px;">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Jam Mulai</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="far fa-clock text-muted"></i></span>
                                    <input type="time" name="start_time" id="edit_start_time" class="form-control bg-light border-0" style="padding: 12px;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Jam Selesai</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0"><i class="fas fa-clock text-muted"></i></span>
                                    <input type="time" name="end_time" id="edit_end_time" class="form-control bg-light border-0" style="padding: 12px;">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Prioritas</label>
                                <div class="d-flex gap-2">
                                    <div class="form-check custom-radio flex-grow-1">
                                        <input class="form-check-input d-none" type="radio" name="priority" id="edit_p_low" value="low">
                                        <label class="btn btn-outline-success w-100 py-2 fw-bold small" for="edit_p_low" style="border-radius: 8px;">Low</label>
                                    </div>
                                    <div class="form-check custom-radio flex-grow-1">
                                        <input class="form-check-input d-none" type="radio" name="priority" id="edit_p_medium" value="medium">
                                        <label class="btn btn-outline-warning w-100 py-2 fw-bold small" for="edit_p_medium" style="border-radius: 8px;">Med</label>
                                    </div>
                                    <div class="form-check custom-radio flex-grow-1">
                                        <input class="form-check-input d-none" type="radio" name="priority" id="edit_p_high" value="high">
                                        <label class="btn btn-outline-danger w-100 py-2 fw-bold small" for="edit_p_high" style="border-radius: 8px;">High</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-dark fw-700 small text-uppercase mb-2">Status</label>
                                <select name="status" id="edit_status" class="form-select bg-light border-0" style="padding: 10px; border-radius: 8px;">
                                    <option value="pending">Pending</option>
                                    <option value="progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Catatan Detail</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control bg-light border-0" rows="3" style="border-radius: 12px; padding: 12px;"></textarea>
                        </div>

                        <?php if ($_SESSION['role'] == 'manager'): ?>
                        <div class="mb-4">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Tugaskan Kepada / Pindah Tugas</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-user-friends text-muted"></i></span>
                                <select name="assigned_to" id="edit_assigned_to" class="form-select bg-light border-0" style="padding: 10px;">
                                    <option value="">Tugas Personal (Diri Sendiri)</option>
                                    <?php foreach ($staf_list as $staf): ?>
                                        <option value="<?php echo $staf['id']; ?>"><?php echo htmlspecialchars($staf['nama']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-0">
                            <label class="form-label text-dark fw-700 small text-uppercase mb-2">Tanggal Target <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0"><i class="fas fa-calendar-day text-muted"></i></span>
                                <input type="date" name="deadline" id="edit_deadline" class="form-control bg-light border-0" required style="padding: 12px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0 d-flex justify-content-between">
                        <button type="submit" name="hapus_task" class="btn btn-outline-danger px-4 py-2 fw-bold" style="border-radius: 12px; border-width: 2px;" onclick="return confirm('Hapus tugas ini?')">
                            <i class="fas fa-trash me-2"></i> Hapus
                        </button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light px-4 py-2 fw-bold" style="border-radius: 12px;" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="edit_task" class="btn btn-primary btn-simpan px-4 py-2 fw-bold">Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentActiveTask = null;

        function toggleAllLembur(source) {
            const checkboxes = document.querySelectorAll('.lembur-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }

        function toggleAllStaf(source) {
            const checkboxes = document.querySelectorAll('.staf-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }

        function updateCheckAllState() {
            const checkAll = document.getElementById('checkAllStaf');
            const checkboxes = document.querySelectorAll('.staf-checkbox');
            const checkedCount = document.querySelectorAll('.staf-checkbox:checked').length;
            
            checkAll.checked = checkedCount === checkboxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }

        function openAddModal(date, source = 'button') {
            const startDateContainer = document.getElementById('start_date_container');
            const calendarAlert = document.getElementById('calendar_info_alert');
            const selectedDateDisplay = document.getElementById('selected_date_display');
            const modalTitle = document.getElementById('addTaskModalLabel');
            const btnSubmit = document.getElementById('btn_submit_task');
            const modalIconContainer = document.getElementById('modal_icon_container');
            const modalIcon = modalIconContainer.querySelector('i');
            
            // Set value default
            document.getElementById('modal_start_date').value = date;
            document.getElementById('modal_deadline').value = date;

            if (source === 'calendar') {
                // Sembunyikan Input Tanggal Mulai karena sudah pasti dari tanggal yang diklik
                startDateContainer.style.display = 'none';
                
                // Tampilkan Alert Info Kalender
                calendarAlert.style.display = 'block';
                const dateObj = new Date(date);
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                selectedDateDisplay.innerText = dateObj.toLocaleDateString('id-ID', options);
                
                // Ubah Tampilan Modal agar lebih spesifik Kalender
                modalTitle.innerText = "Jadwalkan Tugas";
                btnSubmit.innerText = "Jadwalkan di Kalender";
                modalIconContainer.className = "bg-info bg-opacity-10 p-3 rounded-3 me-3";
                modalIcon.className = "fas fa-calendar-plus text-info fs-4";
            } else {
                // Tampilan Normal (Tombol Tambah Tugas Baru)
                startDateContainer.style.display = 'block';
                calendarAlert.style.display = 'none';
                modalTitle.innerText = "Tambah Tugas Baru";
                btnSubmit.innerText = "Simpan Tugas";
                modalIconContainer.className = "bg-primary bg-opacity-10 p-3 rounded-3 me-3";
                modalIcon.className = "fas fa-plus text-primary fs-4";
            }
            
            // Tutup modal summary jika terbuka
            const summaryModalEl = document.getElementById('daySummaryModal');
            const summaryModal = bootstrap.Modal.getInstance(summaryModalEl);
            if (summaryModal) summaryModal.hide();
            
            var myModal = new bootstrap.Modal(document.getElementById('addTaskModal'));
            myModal.show();
        }

        function prepareAddModal() {
            const today = new Date().toISOString().split('T')[0];
            openAddModal(today, 'button');
        }

        function handleDayClick(date, element) {
            // Jika yang diklik adalah link task-badge, jangan jalankan handleDayClick
            if (event.target.closest('.task-badge')) return;

            const taskWrapper = element.querySelector('.task-list-wrapper');
            const tasks = taskWrapper ? JSON.parse(taskWrapper.dataset.tasks) : [];
            
            if (tasks.length > 0) {
                openDaySummary(date, tasks);
            } else {
                openAddModal(date, 'calendar');
            }
        }

        function openDaySummary(date, tasks) {
            const container = document.getElementById('day_tasks_container');
            const noTasksMsg = document.getElementById('no_tasks_message');
            const displayDate = document.getElementById('summary_display_date');
            
            // Format tanggal untuk tampilan
            const dateObj = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            displayDate.innerText = dateObj.toLocaleDateString('id-ID', options);
            
            container.innerHTML = '';
            
            if (tasks.length > 0) {
                noTasksMsg.style.display = 'none';
                tasks.forEach(task => {
                    const item = document.createElement('div');
                    item.className = `day-summary-item summary-${task.status}`;
                    
                    const timeStr = (task.start_time || '--:--') + ' - ' + (task.end_time || '--:--');
                    const assignedInfo = task.assigned_name ? `<span class="ms-3"><i class="fas fa-user"></i> ${task.assigned_name}</span>` : '';
                    
                    item.innerHTML = `
                        <div class="summary-task-name">${task.task_name}</div>
                        <div class="summary-task-meta">
                            <span><i class="far fa-clock"></i> ${timeStr}</span>
                            <span><i class="fas fa-layer-group"></i> ${task.priority.toUpperCase()}</span>
                            ${assignedInfo}
                        </div>
                    `;
                    
                    item.onclick = () => {
                        const summaryModal = bootstrap.Modal.getInstance(document.getElementById('daySummaryModal'));
                        if (summaryModal) summaryModal.hide();
                        openViewModal(task);
                    };
                    
                    container.appendChild(item);
                });
            } else {
                noTasksMsg.style.display = 'block';
            }
            
            document.getElementById('btn_add_from_summary').onclick = () => openAddModal(date, 'calendar');
            
            var summaryModal = new bootstrap.Modal(document.getElementById('daySummaryModal'));
            summaryModal.show();
        }

        function openViewModal(task) {
            currentActiveTask = task;
            document.getElementById('view_task_name').innerText = task.task_name;
            document.getElementById('view_task_time').innerText = (task.start_time || '--:--') + ' - ' + (task.end_time || '--:--');
            
            // Tampilkan info penugasan jika ada
            const assignedWrapper = document.getElementById('view_assigned_wrapper');
            if (task.assigned_name) {
                assignedWrapper.style.display = 'block';
                document.getElementById('view_task_assigned').innerText = task.assigned_name;
            } else {
                assignedWrapper.style.display = 'none';
            }
            
            const priorityEl = document.getElementById('view_task_priority');
            const p = task.priority || 'low';
            let pClass = 'bg-success';
            if(p === 'medium') pClass = 'bg-warning text-dark';
            if(p === 'high') pClass = 'bg-danger';
            priorityEl.innerHTML = `<span class="badge ${pClass}" style="font-size: 11px; padding: 6px 12px; border-radius: 6px;">${p.toUpperCase()}</span>`;
            
            const statusEl = document.getElementById('view_task_status');
            const s = task.status;
            statusEl.innerText = s.toUpperCase();
            
            let statusClass = 'bg-secondary';
            if(s === 'progress') statusClass = 'bg-primary';
            if(s === 'completed') statusClass = 'bg-success';
            statusEl.className = 'badge ' + statusClass;

            // Tampilkan aksi cepat jika user adalah orang yang ditugaskan
            const quickActions = document.getElementById('quick_status_actions');
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            if (task.assigned_to == currentUserId && s !== 'completed') {
                quickActions.style.display = 'block';
                document.getElementById('quick_task_id_progress').value = task.id;
                document.getElementById('quick_task_id_completed').value = task.id;
            } else {
                quickActions.style.display = 'none';
            }

            const notesEl = document.getElementById('view_task_notes');
            if (task.deskripsi) {
                notesEl.innerText = task.deskripsi;
                notesEl.classList.remove('text-muted', 'font-italic');
            } else {
                notesEl.innerText = 'Tidak ada catatan tambahan.';
                notesEl.classList.add('text-muted', 'font-italic');
            }
            
            var viewModal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
            viewModal.show();
        }

        function switchToEdit() {
            // Tutup modal view
            bootstrap.Modal.getInstance(document.getElementById('viewTaskModal')).hide();
            // Buka modal edit dengan data yang sama
            openEditModal(currentActiveTask);
        }

        function openEditModal(task) {
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            const isManager = <?php echo $_SESSION['role'] == 'manager' ? 'true' : 'false'; ?>;
            const isCreator = task.user_id == currentUserId;
            
            document.getElementById('edit_task_id').value = task.id;
            document.getElementById('edit_task_name').value = task.task_name;
            document.getElementById('edit_start_time').value = task.start_time || '';
            document.getElementById('edit_end_time').value = task.end_time || '';
            
            // Set Priority Radio
            const priority = task.priority || 'low';
            const radioId = 'edit_p_' + priority;
            const radioBtn = document.getElementById(radioId);
            if (radioBtn) radioBtn.checked = true;

            document.getElementById('edit_status').value = task.status;
            document.getElementById('edit_deskripsi').value = task.deskripsi || '';
            document.getElementById('edit_deadline').value = task.deadline;

            // Jika tugas diberikan oleh Manager ke Staff, Staff hanya boleh ubah status & catatan
            const fieldsToDisable = ['edit_task_name', 'edit_deadline', 'edit_start_time', 'edit_end_time'];
            const radioToDisable = ['edit_p_low', 'edit_p_medium', 'edit_p_high'];
            
            if (!isManager && !isCreator && task.assigned_to == currentUserId) {
                fieldsToDisable.forEach(id => {
                    document.getElementById(id).readOnly = true;
                });
                radioToDisable.forEach(id => {
                    document.getElementById(id).disabled = true;
                });
                if (document.getElementById('edit_assigned_to')) {
                    document.getElementById('edit_assigned_to').disabled = true;
                }
            } else {
                fieldsToDisable.forEach(id => {
                    document.getElementById(id).readOnly = false;
                });
                radioToDisable.forEach(id => {
                    document.getElementById(id).disabled = false;
                });
                if (document.getElementById('edit_assigned_to')) {
                    document.getElementById('edit_assigned_to').disabled = false;
                }
            }
            
            // Set assigned_to if manager
            const editAssigned = document.getElementById('edit_assigned_to');
            if (editAssigned) {
                editAssigned.value = task.assigned_to || "";
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
            editModal.show();
        }
    </script>
    
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
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                const overlay = document.getElementById('sidebarOverlay');
                const sidebarToggle = document.getElementById('sidebarToggle');

                if (window.innerWidth > 991.98) {
                    if (sidebar) sidebar.classList.remove('show');
                    if (overlay) overlay.classList.remove('show');
                    if (sidebarToggle) sidebarToggle.classList.remove('active');
                } else {
                    if (sidebar) sidebar.classList.remove('hidden');
                    if (mainContent) mainContent.classList.remove('expanded');
                    if (sidebarToggle) sidebarToggle.classList.remove('collapsed');
                }
            });
        });
    </script>
</body>
</html>