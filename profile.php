<?php
require_once 'config_safe.php';
cekLogin();

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Ambil data user terbaru
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Proses Update Profil
if (isset($_POST['update_profile'])) {
    $nama = $_POST['nama'];
    $absen_id = $_POST['absen_id'];
    $email = $_POST['email'];
    $telepon = $_POST['telepon'];
    $alamat = $_POST['alamat'];

    try {
        // Proses Upload Foto Profil
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $filename = $_FILES['foto_profil']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
                $upload_path = 'uploads/profiles/';
                
                if (!is_dir($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path . $new_filename)) {
                    // Ambil info foto lama sebelum diupdate
                    $old_foto_stmt = $pdo->prepare("SELECT foto FROM users WHERE id = ?");
                    $old_foto_stmt->execute([$user_id]);
                    $old_foto = $old_foto_stmt->fetchColumn();

                    // Hapus foto lama jika ada
                    if ($old_foto && file_exists($upload_path . $old_foto)) {
                        unlink($upload_path . $old_foto);
                    }
                    $stmt = $pdo->prepare("UPDATE users SET foto = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $user_id]);
                    
                    // Ambil data terbaru agar preview langsung sinkron jika ada error di form berikutnya
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                }
            }
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE absen_id = ? AND id != ?");
        $check->execute([$absen_id, $user_id]);
        if ($check->fetch()) {
            $error_msg = "Username sudah digunakan!";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, absen_id = ?, email = ?, telepon = ?, alamat = ? WHERE id = ?");
            $stmt->execute([$nama, $absen_id, $email, $telepon, $alamat, $user_id]);
            $_SESSION['nama'] = $nama;
            $success_msg = "Profil berhasil diperbarui!";

            // Ambil data terbaru setelah update agar UI langsung sinkron
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            header("Refresh:1");
            
        }
    } catch (PDOException $e) { $error_msg = $e->getMessage(); }
}

// Proses Update Password
if (isset($_POST['update_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    if (strlen($new_pass) < 6) {
        $error_msg = "Password baru minimal harus 6 karakter!";
    } elseif (md5($old_pass) === $user['password']) {
        $hashed = md5($new_pass);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        $success_msg = "Password berhasil diubah!";
    } else { $error_msg = "Password lama salah!"; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profil Saya</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        .sidebar.hidden {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .sidebar {
            transition: transform 0.3s ease;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 40px 50px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            max-width: calc(100vw - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            max-width: 100vw;
            transition: margin-left 0.3s ease;
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

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 1050;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .sidebar-overlay.show {
                display: block;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
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
        }
    </style>
</head>
<body>
    <!-- Toggle Button -->
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
                <a href="laporan.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i> Laporan
                </a>
                <a href="tasklist.php" class="nav-item">
                    <i class="fas fa-clipboard-check"></i> Tugas
                </a>
            <?php endif; ?>
            
            <a href="profile.php" class="nav-item active">
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
        <h2 class="page-title">Pengaturan Profil</h2>
        
        <?php if($success_msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <?php if($error_msg): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
        
        <div class="row">
            <div class="col-md-7">
                <div class="card-custom">
                    <h5 class="fw-bold mb-4" style="color: var(--primary-color); border-bottom: 2px solid #f8f9fa; padding-bottom: 15px;">
                        <i class="fas fa-user-edit me-2"></i>Informasi Profil
                    </h5>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-5 text-center">
                            <div class="position-relative d-inline-block">
                                <img src="<?php echo (isset($user['foto']) && $user['foto']) ? 'uploads/profiles/'.$user['foto'] : 'https://ui-avatars.com/api/?name='.urlencode($user['nama']).'&background=004AAD&color=fff&size=128'; ?>" 
                                     class="rounded-circle shadow-sm" 
                                     style="width: 140px; height: 140px; object-fit: cover; border: 5px solid white !important; box-shadow: 0 5px 15px rgba(0,0,0,0.15);" 
                                     id="preview_foto">
                                <label for="foto_profil" class="btn btn-primary position-absolute bottom-0 end-0 rounded-circle d-flex align-items-center justify-content-center shadow" 
                                       style="width: 42px; height: 42px; border: 3px solid white; cursor: pointer; transform: translate(5px, 5px);" title="Ubah Foto Profil">
                                    <i class="fas fa-camera" style="font-size: 16px;"></i>
                                </label>
                                <input type="file" name="foto_profil" id="foto_profil" class="d-none" onchange="previewImage(this)" accept="image/*">
                            </div>
                            <p class="text-muted small mt-3 fw-bold text-uppercase" style="letter-spacing: 1px;">Klik ikon kamera untuk mengganti foto</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" value="<?php echo $user['nama']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Login / Username <span class="text-danger">*</span></label>
                                <input type="text" name="absen_id" class="form-control" value="<?php echo $user['absen_id']; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $user['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Telepon</label>
                                <input type="text" name="telepon" class="form-control" value="<?php echo $user['telepon'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Alamat Lengkap</label>
                            <textarea name="alamat" class="form-control" rows="3"><?php echo $user['alamat'] ?? ''; ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary px-4 shadow-sm">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card-custom">
                    <h5 class="fw-bold mb-4" style="color: var(--primary-color); border-bottom: 2px solid #f8f9fa; padding-bottom: 15px;">
                        <i class="fas fa-key me-2"></i>Keamanan Akun
                    </h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
                            <input type="password" name="old_password" class="form-control" placeholder="Masukkan password lama" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                            <div class="form-text text-muted small mt-2">Pastikan password baru Anda kuat dan mudah diingat.</div>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-dark w-100 shadow-sm">
                            <i class="fas fa-shield-alt me-2"></i>Perbarui Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_foto').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
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
           
    </script>
</body>
</html>