<?php
require_once 'config_safe.php';
cekLogin();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Ambil data user untuk foto profil
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

$type = $_GET['type'] ?? 'harian';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_week = $_GET['filter_week'] ?? '';
$filter_month = $_GET['filter_month'] ?? '';
$filter_year = $_GET['filter_year'] ?? '';
$filter_tipe = $_GET['filter_tipe'] ?? '';

// Handler untuk Ekspor CSV (Excel compatible)
if (isset($_GET['export'])) {
    $laporan_export = getLaporanData($pdo, $user_id, $type, $role, $search, $start_date, $end_date, $filter_week, $filter_month, $filter_year, $filter_tipe);
    $filename = "rekap_absensi_" . $type . "_" . date('Ymd') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Header CSV berdasarkan tipe
    if ($type == 'harian' || $type == 'mingguan') {
        $headers = ['Tanggal', 'Nama', 'ID Login', 'Tipe', 'Jam Masuk', 'Jam Keluar', 'Durasi'];
    } elseif ($type == 'bulanan') {
        $headers = ['Bulan', 'Nama', 'ID Login', 'Tipe', 'Hari Kerja', 'Total Durasi'];
    } else {
        $headers = ['Tahun', 'Nama', 'ID Login', 'Tipe', 'Hari Kerja', 'Total Durasi'];
    }
    
    fputcsv($output, $headers);
    
    foreach ($laporan_export as $row) {
        $data_csv = [];
        if ($type == 'harian' || $type == 'mingguan') {
            $data_csv = [
                $row['tanggal'],
                $row['nama'],
                "'" . $row['absen_id'],
                ucfirst($row['tipe_absensi']),
                $row['jam_masuk'],
                $row['jam_keluar'] ?: '-',
                $row['durasi_kerja'] ?: '-'
            ];
        } elseif ($type == 'bulanan') {
            $data_csv = [
                $row['nama_bulan'],
                $row['nama'],
                "'" . $row['absen_id'],
                ucfirst($row['tipe_absensi'] ?? 'reguler'),
                $row['total_hari_kerja'],
                $row['total_durasi']
            ];
        } else {
            $data_csv = [
                $row['tahun'],
                $row['nama'],
                "'" . $row['absen_id'],
                ucfirst($row['tipe_absensi'] ?? 'reguler'),
                $row['total_hari_kerja'],
                $row['total_durasi']
            ];
        }
        fputcsv($output, $data_csv);
    }
    fclose($output);
    exit();
}

// Fungsi untuk mendapatkan data laporan berdasarkan tipe dan pencarian
function getLaporanData($pdo, $user_id, $type, $role, $search = '', $start_date = '', $end_date = '', $filter_week = '', $filter_month = '', $filter_year = '', $filter_tipe = '') {
    $data = [];
    $search_param = "%$search%";
    $date_params = [];
    $has_range = !empty($start_date) && !empty($end_date);
    
    switch ($type) {
        case 'harian':
            $condition = $has_range ? "a.tanggal BETWEEN ? AND ?" : "a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $condition_lembur = $has_range ? "al.tanggal BETWEEN ? AND ?" : "al.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            if ($has_range) $date_params = [$start_date, $end_date];

            $where_tipe = "";
            $where_tipe_lembur = "";
            if (!empty($filter_tipe)) {
                if ($filter_tipe == 'lembur') {
                    $where_tipe = " AND 1=0";
                    $where_tipe_lembur = "";
                } else {
                    $where_tipe = " AND COALESCE(a.tipe_absen, 'reguler') = " . $pdo->quote($filter_tipe);
                    $where_tipe_lembur = " AND 1=0";
                }
            }

            // Laporan harian - 7 hari terakhir atau range tanggal (termasuk lembur)
            $query = "(
                        SELECT 
                            a.tanggal,
                            a.jam_masuk,
                            a.jam_keluar,
                            a.durasi_kerja,
                            u.nama,
                            u.absen_id,
                            COALESCE(a.tipe_absen, 'reguler') as tipe_absensi,
                            a.keterangan as keterangan_lembur
                        FROM absensi a
                        JOIN users u ON a.user_id = u.id
                        WHERE $condition $where_tipe
                        AND (a.tipe_absen IN ('reguler', 'sakit', 'cuti') OR a.tipe_absen IS NULL)
                      ) UNION ALL (
                        SELECT 
                            al.tanggal,
                            al.jam_mulai as jam_masuk,
                            al.jam_selesai as jam_keluar,
                            al.durasi_lembur as durasi_kerja,
                            u.nama,
                            u.absen_id,
                            'lembur' as tipe_absensi,
                            al.keterangan as keterangan_lembur
                        FROM absensi_lembur al
                        JOIN users u ON al.user_id = u.id
                        WHERE $condition_lembur $where_tipe_lembur
                      )";
            
            if ($role != 'manager') {
                $query = "SELECT * FROM (" . $query . ") as combined WHERE absen_id = ?";
                $params = array_merge($date_params, $date_params, [$_SESSION['absen_id']]);
            } else {
                if (!empty($search)) {
                    $query = "SELECT * FROM (" . $query . ") as combined WHERE (nama LIKE ? OR absen_id LIKE ?)";
                    $params = array_merge($date_params, $date_params, [$search_param, $search_param]);
                } else {
                    $params = array_merge($date_params, $date_params);
                }
            }
            
            $query .= " ORDER BY tanggal DESC, jam_masuk DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            $data = $stmt->fetchAll();
            break;
        
        case 'mingguan':
            if (!empty($filter_week)) {
                // filter_week format: 2023-W12
                list($year, $week) = explode('-W', $filter_week);
                $condition = "YEAR(a.tanggal) = ? AND WEEK(a.tanggal, 1) = ?";
                $date_params = [$year, $week];
            } else {
                $condition = $has_range ? "a.tanggal BETWEEN ? AND ?" : "a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)";
                if ($has_range) $date_params = [$start_date, $end_date];
            }

            $where_tipe = "";
            if (!empty($filter_tipe)) {
                if ($filter_tipe != 'lembur') {
                    $where_tipe = " AND COALESCE(a.tipe_absen, 'reguler') = " . $pdo->quote($filter_tipe);
                } else {
                    // Laporan mingguan lembur dialihkan ke tabel absensi_lembur
                    $query = "SELECT 
                                YEARWEEK(a.tanggal, 1) as tahun_minggu,
                                DATE(DATE_SUB(a.tanggal, INTERVAL WEEKDAY(a.tanggal) DAY)) as minggu_mulai,
                                DATE(DATE_ADD(a.tanggal, INTERVAL (6 - WEEKDAY(a.tanggal)) DAY)) as minggu_selesai,
                                COUNT(*) as total_hari_kerja,
                                SEC_TO_TIME(SUM(TIME_TO_SEC(a.durasi_lembur))) as total_durasi,
                                u.nama,
                                u.absen_id
                              FROM absensi_lembur a
                              JOIN users u ON a.user_id = u.id
                              WHERE 1=1 AND " . str_replace('a.tanggal', 'a.tanggal', $condition);
                    $params = $date_params;
                    if ($role != 'manager') {
                        $query .= " AND a.user_id = ?";
                        $params[] = $user_id;
                    } elseif (!empty($search)) {
                        $query .= " AND (u.nama LIKE ? OR u.absen_id LIKE ?)";
                        $params[] = $search_param;
                        $params[] = $search_param;
                    }
                    $query .= " GROUP BY YEARWEEK(a.tanggal, 1), a.user_id ORDER BY tahun_minggu DESC";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $data = $stmt->fetchAll();
                    break;
                }
            }

            $query = "SELECT 
                        YEARWEEK(a.tanggal, 1) as tahun_minggu,
                        DATE(DATE_SUB(a.tanggal, INTERVAL WEEKDAY(a.tanggal) DAY)) as minggu_mulai,
                        DATE(DATE_ADD(a.tanggal, INTERVAL (6 - WEEKDAY(a.tanggal)) DAY)) as minggu_selesai,
                        COUNT(*) as total_hari_kerja,
                        SEC_TO_TIME(SUM(TIME_TO_SEC(a.durasi_kerja))) as total_durasi,
                        u.nama,
                        u.absen_id,
                        COALESCE(a.tipe_absen, 'reguler') as tipe_absensi
                      FROM absensi a
                      JOIN users u ON a.user_id = u.id
                      WHERE a.durasi_kerja IS NOT NULL 
                      AND $condition $where_tipe";
            
            $params = $date_params;
            if ($role != 'manager') {
                $query .= " AND a.user_id = ?";
                $params[] = $user_id;
            } elseif (!empty($search)) {
                $query .= " AND (u.nama LIKE ? OR u.absen_id LIKE ?)";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " GROUP BY YEARWEEK(a.tanggal, 1), a.user_id ORDER BY tahun_minggu DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            break;

        case 'bulanan':
            if (!empty($filter_month)) {
                // filter_month format: 2023-05
                $condition = "DATE_FORMAT(a.tanggal, '%Y-%m') = ?";
                $date_params = [$filter_month];
            } else {
                $condition = $has_range ? "a.tanggal BETWEEN ? AND ?" : "a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                if ($has_range) $date_params = [$start_date, $end_date];
            }

            $query = "SELECT 
                        DATE_FORMAT(a.tanggal, '%Y-%m') as bulan,
                        DATE_FORMAT(a.tanggal, '%M %Y') as nama_bulan,
                        COUNT(*) as total_hari_kerja,
                        SEC_TO_TIME(SUM(TIME_TO_SEC(a.durasi_kerja))) as total_durasi,
                        u.nama,
                        u.absen_id,
                        COALESCE(a.tipe_absen, 'reguler') as tipe_absensi
                      FROM absensi a
                      JOIN users u ON a.user_id = u.id
                      WHERE a.durasi_kerja IS NOT NULL 
                      AND $condition";
            
            $params = $date_params;
            if ($role != 'manager') {
                $query .= " AND a.user_id = ?";
                $params[] = $user_id;
            } elseif (!empty($search)) {
                $query .= " AND (u.nama LIKE ? OR u.absen_id LIKE ?)";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " GROUP BY DATE_FORMAT(a.tanggal, '%Y-%m'), a.user_id ORDER BY bulan DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            break;

        case 'tahunan':
            if (!empty($filter_year)) {
                $condition = "YEAR(a.tanggal) = ?";
                $date_params = [$filter_year];
            } else {
                $condition = $has_range ? "a.tanggal BETWEEN ? AND ?" : "a.tanggal >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)";
                if ($has_range) $date_params = [$start_date, $end_date];
            }

            $query = "SELECT 
                        YEAR(a.tanggal) as tahun,
                        COUNT(*) as total_hari_kerja,
                        SEC_TO_TIME(SUM(TIME_TO_SEC(a.durasi_kerja))) as total_durasi,
                        u.nama,
                        u.absen_id,
                        COALESCE(a.tipe_absen, 'reguler') as tipe_absensi
                      FROM absensi a
                      JOIN users u ON a.user_id = u.id
                      WHERE a.durasi_kerja IS NOT NULL 
                      AND $condition";
            
            $params = $date_params;
            if ($role != 'manager') {
                $query .= " AND a.user_id = ?";
                $params[] = $user_id;
            } elseif (!empty($search)) {
                $query .= " AND (u.nama LIKE ? OR u.absen_id LIKE ?)";
                $params[] = $search_param;
                $params[] = $search_param;
            }
            
            $query .= " GROUP BY YEAR(a.tanggal), a.user_id ORDER BY tahun DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            break;
    }
    return $data;
}

$laporan_data = getLaporanData($pdo, $user_id, $type, $role, $search, $start_date, $end_date, $filter_week, $filter_month, $filter_year, $filter_tipe);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Absensi - Absensi System</title>
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
            font-weight: 700;
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

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            gap: 12px;
            min-height: 48px;
        }

        .nav-item.active {
            background: white !important;
            color: var(--primary-color) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-weight: 700;
        }

        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .page-title {
            color: var(--primary-color); 
            font-weight: 800; 
            margin-bottom: 45px; 
            font-size: 28px;
            position: relative;
            display: block;
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
        .table-custom thead th:last-child, .table-custom tbody td:last-child { border-right: none; }

        .btn-filter {
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: #7f8c8d;
            background: white;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .btn-filter:hover {
            border-color: #3e4a89;
            color: #3e4a89;
            background: rgba(62, 74, 137, 0.05);
        }
        .btn-filter.active {
            background: #3e4a89;
            color: white !important;
            border-color: #3e4a89;
            box-shadow: 0 4px 10px rgba(62, 74, 137, 0.2);
        }
        .btn-filter i {
            font-size: 14px;
        }
        
        .badge-custom { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .bg-navy { background-color: #000080 !important; color: white !important; }
        
        /* Mobile & Tablet Responsiveness */
        @media (max-width: 991.98px) {
            :root {
                --sidebar-width: 280px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex !important;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
                padding-top: 80px !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .sidebar-toggle {
                left: 20px !important;
                top: 20px !important;
                width: 45px;
                height: 45px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .content-card {
                padding: 15px !important;
            }
            
            .nav-item {
                padding: 16px 25px;
            }

            .btn, .btn-filter {
                padding: 12px 20px;
                min-height: 44px;
            }
            
            .table-responsive {
                border: 0;
            }
            .table-custom thead {
                display: none;
            }
            .table-custom tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .table-custom tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
                border-bottom: 1px solid #f8f9fa;
                padding: 10px 15px;
                text-align: right;
            }
            .table-custom tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                text-align: left;
                color: #6c757d;
                font-size: 11px;
                text-transform: uppercase;
            }
        }

        .sidebar, .main-content, .table-scroll {
            -webkit-overflow-scrolling: touch;
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
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 15mm;
            }
            
            .sidebar, .sidebar-toggle, .btn-filter, .input-group, .btn-success, .btn-danger, 
            .page-title, .nav-menu, .sign-out-btn, .justify-content-end.gap-3,
            button[type="submit"], .btn-outline-secondary, .mt-auto, .profile-section,
            .alert, .page-header, .content-card p-3.mb-4, #sidebarToggle, .sidebar-overlay {
                display: none !important;
            }

            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                color: black !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .content-card {
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .card-header-custom {
                background: none !important;
                padding: 0 0 15px 0 !important;
                border-bottom: 4px solid #004AAD !important;
                margin-bottom: 25px !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-end !important;
            }

            .card-header-custom h5 {
                color: #004AAD !important;
                font-size: 24pt !important;
                font-weight: 900 !important;
                margin: 0 !important;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .card-header-custom h5::after {
                content: " - LAPORAN KEHADIRAN";
                font-size: 14pt;
                font-weight: 500;
                color: #444;
            }

            .card-header-custom::after {
                content: "Waktu Cetak: <?php echo date('d/m/Y H:i'); ?>";
                font-size: 10pt;
                color: #666;
                display: block;
            }

            .table-responsive, .table-scroll {
                overflow: visible !important;
                max-height: none !important;
                border: none !important;
            }

            .table-custom {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 10pt !important;
                margin-top: 10px !important;
            }

            .table-custom thead {
                display: table-header-group !important;
            }

            .table-custom thead th {
                background-color: #f0f4f8 !important;
                color: #004AAD !important;
                border: 1px solid #cdd6e0 !important;
                padding: 12px 10px !important;
                text-align: left !important;
                font-weight: 700 !important;
                text-transform: uppercase;
            }

            .table-custom tbody tr {
                display: table-row !important;
                page-break-inside: avoid !important;
                background-color: transparent !important;
            }

            .table-custom tbody tr:nth-child(even) {
                background-color: #fafbfc !important;
            }

            .table-custom tbody td {
                display: table-cell !important;
                border: 1px solid #dee2e6 !important;
                padding: 10px !important;
                vertical-align: middle !important;
                text-align: left !important;
            }

            .table-custom tbody td::before {
                display: none !important;
            }

            .badge-custom, .badge {
                border: 1px solid #ccc !important;
                padding: 4px 10px !important;
                border-radius: 4px !important;
                font-size: 8pt !important;
                font-weight: 700 !important;
                text-transform: uppercase;
                background: transparent !important;
                color: black !important;
            }

            .print-footer {
                display: flex !important;
                justify-content: flex-end;
                margin-top: 50px;
                width: 100%;
            }

            .signature-box {
                text-align: center;
                width: 250px;
            }

            .signature-space {
            height: 80px;
            border-bottom: 1px solid #000;
            margin-bottom: 10px;
        }
        }

        .print-footer {
            display: none;
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
                <a href="laporan.php" class="nav-item active">
                    <i class="fas fa-file-invoice"></i> Laporan 
                </a>
                <a href="tasklist.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i> Tugas
                </a>
                <a href="staf_detail.php?view=list" class="nav-item">
                    <i class="fas fa-user-friends"></i> Staff
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="laporan.php" class="nav-item active">
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
        <h2 class="page-title">Laporan Presensi Karyawan</h2>

        <!-- Baris 1: Filter Tipe (Harian, Mingguan, dll) -->
        <div class="d-flex flex-wrap gap-3 mb-4">
            <a href="laporan.php?type=harian&search=<?php echo urlencode($search); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-filter <?php echo $type == 'harian' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Harian
            </a>
            <a href="laporan.php?type=mingguan&search=<?php echo urlencode($search); ?>&filter_week=<?php echo $filter_week; ?>" class="btn-filter <?php echo $type == 'mingguan' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> Mingguan
            </a>
            <a href="laporan.php?type=bulanan&search=<?php echo urlencode($search); ?>&filter_month=<?php echo $filter_month; ?>" class="btn-filter <?php echo $type == 'bulanan' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Bulanan
            </a>
            <a href="laporan.php?type=tahunan&search=<?php echo urlencode($search); ?>&filter_year=<?php echo $filter_year; ?>" class="btn-filter <?php echo $type == 'tahunan' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Tahunan
            </a>
        </div>
        
        <!-- Baris 2: Filter Tanggal dan Pencarian -->
        <div class="content-card p-3 mb-4">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <?php if ($type == 'harian'): ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Dari</span>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Sampai</span>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                    </div>
                <?php elseif ($type == 'mingguan'): ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Pilih Minggu</span>
                            <input type="week" name="filter_week" class="form-control" value="<?php echo htmlspecialchars($filter_week); ?>">
                        </div>
                    </div>
                <?php elseif ($type == 'bulanan'): ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Pilih Bulan</span>
                            <input type="month" name="filter_month" class="form-control" value="<?php echo htmlspecialchars($filter_month); ?>">
                        </div>
                    </div>
                <?php elseif ($type == 'tahunan'): ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Pilih Tahun</span>
                            <select name="filter_year" class="form-select form-select-sm">
                                <option value="">-- Pilih Tahun --</option>
                                <?php 
                                $currentYear = date('Y');
                                for($i = $currentYear; $i >= $currentYear - 5; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $filter_year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($type == 'harian' || $type == 'mingguan'): ?>
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">Tipe Absen</span>
                            <select name="filter_tipe" class="form-select">
                                <option value="">Semua Tipe</option>
                                <option value="reguler" <?php echo $filter_tipe == 'reguler' ? 'selected' : ''; ?>>Reguler</option>
                                <option value="lembur" <?php echo $filter_tipe == 'lembur' ? 'selected' : ''; ?>>Lembur</option>
                                <option value="sakit" <?php echo $filter_tipe == 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                                <option value="cuti" <?php echo $filter_tipe == 'cuti' ? 'selected' : ''; ?>>Izin/Cuti</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($role == 'manager'): ?>
                <div class="col-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control ps-0" placeholder="Cari nama/ID..." value="<?php echo htmlspecialchars($search); ?>" style="width: 150px;">
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="laporan.php?type=<?php echo $type; ?>" class="btn btn-outline-secondary btn-sm px-4 rounded-pill">
                        <i class="fas fa-undo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-end gap-3 align-items-center mb-4">
            <a href="laporan.php?type=<?php echo $type; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&filter_week=<?php echo $filter_week; ?>&filter_month=<?php echo $filter_month; ?>&filter_year=<?php echo $filter_year; ?>&filter_tipe=<?php echo $filter_tipe; ?>&export=1" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-file-excel me-2"></i> Export ke Excel
            </a>
            <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-file-pdf me-2"></i> Export ke PDF
            </button>
        </div>

        <div class="content-card">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-table me-2"></i>Data Laporan <?php echo ucfirst($type); ?></h5>
                <?php if ($role != 'manager'): ?>
                    <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($user['nama']); ?> (<?php echo htmlspecialchars($user['absen_id']); ?>)</span>
                <?php endif; ?>
            </div>
            <div class="table-scroll">
                    <table class="table table-custom">
                    <thead>
                        <tr>
                            <?php if ($role == 'manager'): ?>
                                <th>Staff</th>
                            <?php endif; ?>
                            
                            <?php if ($type == 'harian'): ?>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Durasi</th>
                            <?php elseif ($type == 'mingguan'): ?>
                                <th>Periode</th>
                                <th>Tipe</th>
                                <th>Hari Kerja</th>
                                <th>Total Durasi</th>
                                <th>Rata-rata</th>
                            <?php elseif ($type == 'bulanan'): ?>
                                <th>Bulan</th>
                                <th>Tipe</th>
                                <th>Hari Kerja</th>
                                <th>Total Durasi</th>
                                <th>Rata-rata</th>
                            <?php elseif ($type == 'tahunan'): ?>
                                <th>Tahun</th>
                                <th>Tipe</th>
                                <th>Hari Kerja</th>
                                <th>Total Durasi</th>
                                <th>Rata-rata</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($laporan_data)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Belum ada data untuk periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($laporan_data as $data): ?>
                            <tr>
                                <?php if ($role == 'manager'): ?>
                                    <td class="fw-bold" data-label="Karyawan"><?php echo htmlspecialchars($data['nama']); ?></td>
                                <?php endif; ?>
                                
                                <?php if ($type == 'harian'): ?>
                                    <td data-label="Tanggal"><?php echo date('M j, Y', strtotime($data['tanggal'])); ?></td>
                                    <td data-label="Tipe">
                                        <?php if ($data['tipe_absensi'] == 'sakit'): ?>
                                            <span class="badge-custom bg-danger text-white">SAKIT</span>
                                        <?php elseif ($data['tipe_absensi'] == 'cuti'): ?>
                                            <span class="badge-custom bg-warning text-dark">IZIN</span>
                                        <?php elseif ($data['tipe_absensi'] == 'lembur'): ?>
                                            <span class="badge-custom bg-navy">LEMBUR</span>
                                        <?php else: ?>
                                            <span class="badge-custom bg-success text-white">REGULER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Masuk"><?php echo $data['jam_masuk']; ?></td>
                                    <td data-label="Keluar"><?php echo $data['jam_keluar'] ?: '-'; ?></td>
                                    <td class="fw-bold" data-label="Durasi"><?php echo $data['durasi_kerja'] ?: '-'; ?></td>
                                <?php else: ?>
                                    <!-- Logika untuk Mingguan/Bulanan/Tahunan tetap sama namun dengan styling baru -->
                                    <td class="fw-bold" data-label="<?php echo $type == 'mingguan' ? 'Periode' : ($type == 'bulanan' ? 'Bulan' : 'Tahun'); ?>">
                                        <?php 
                                        if($type == 'mingguan') echo date('d/m/Y', strtotime($data['minggu_mulai'])) . ' - ' . date('d/m/Y', strtotime($data['minggu_selesai']));
                                        else if($type == 'bulanan') echo $data['nama_bulan'];
                                        else echo $data['tahun'];
                                        ?>
                                    </td>
                                    <td data-label="Tipe">
                                        <?php if ($data['tipe_absensi'] == 'sakit'): ?>
                                            <span class="badge-custom bg-danger text-white">SAKIT</span>
                                        <?php elseif ($data['tipe_absensi'] == 'cuti'): ?>
                                            <span class="badge-custom bg-warning text-dark">IZIN</span>
                                        <?php elseif ($data['tipe_absensi'] == 'lembur'): ?>
                                            <span class="badge-custom bg-navy">LEMBUR</span>
                                        <?php else: ?>
                                            <span class="badge-custom bg-success text-white">REGULER</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Hari Kerja"><?php echo $data['total_hari_kerja']; ?> hari</td>
                                    <td data-label="Total Durasi"><?php echo $data['total_durasi']; ?></td>
                                    <td class="fw-bold" data-label="Rata-rata">
                                        <?php 
                                        $rata_rata = '00:00:00';
                                        if ($data['total_hari_kerja'] > 0 && $data['total_durasi']) {
                                            $total_seconds = array_sum(array_map(function($time) {
                                                list($h, $m, $s) = explode(':', $time);
                                                return $h * 3600 + $m * 60 + $s;
                                            }, [$data['total_durasi']])) / $data['total_hari_kerja'];
                                            $hours = floor($total_seconds / 3600);
                                            $minutes = floor(($total_seconds % 3600) / 60);
                                            $rata_rata = sprintf('%02d:%02d:%02d', $hours, $minutes, $total_seconds % 60);
                                        }
                                        echo $rata_rata;
                                        ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody></table>
                </div>
            </div>

            <!-- Footer Tanda Tangan (Hanya Muncul di PDF/Print) -->
            <div class="print-footer">
                <div class="signature-box">
                    <p>Mengetahui,</p>
                    <p class="fw-bold" style="margin-top: -10px;">Manager Operasional</p>
                    <div class="signature-space"></div>
                    <p class="fw-bold">( __________________________ )</p>
                    <p style="font-size: 9pt; color: #666; margin-top: -10px;">NIP. ...........................</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
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

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }

            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

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