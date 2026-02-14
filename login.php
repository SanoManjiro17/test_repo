<?php
require_once 'config_safe.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit();
}

// Cek cookie "remember_me" untuk mengisi otomatis Absen ID
$remembered_id = $_COOKIE['remember_absen_id'] ?? '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $absen_id = $_POST['absen_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($absen_id) || empty($password)) {
        $error = 'Silakan isi Absen ID dan Password!';
    } else {
        $role_selected = $_POST['role'] ?? 'staf';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE absen_id = ?");
        $stmt->execute([$absen_id]);
        $user = $stmt->fetch();
        
        // Cek password (mendukung password_hash baru atau md5 lama untuk migrasi)
        $password_valid = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            } elseif (md5($password) === $user['password']) {
                $password_valid = true;
                // Opsional: Update ke password_hash secara otomatis di sini jika ingin migrasi total
            }
        }

        if ($user && $password_valid) {
            // Validasi role yang dipilih
            if ($user['role'] !== $role_selected) {
                $error = 'Role yang dipilih tidak sesuai dengan akun Anda!';
            } else {
                // Jika "Ingat Saya" dicentang, simpan cookie selama 30 hari
                if ($remember) {
                    setcookie('remember_absen_id', $absen_id, time() + (86400 * 30), "/");
                } else {
                    setcookie('remember_absen_id', '', time() - 3600, "/");
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['absen_id'] = $user['absen_id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];
                
                // Initialize session security
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                // Redirect ke URL yang disimpan atau dashboard default
                $redirect_url = $_SESSION['redirect_url'] ?? getDashboardUrl($user['role']);
                unset($_SESSION['redirect_url']);
                
                header('Location: ' . $redirect_url);
                exit();
            }
        } else {
            $error = 'Absen ID atau Password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #004AAD;
            --bg-gradient: linear-gradient(135deg, #0d1221 0%, #0d1221 25%, #911b2a 55%, #e58e7d 100%);
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-logo: 'Playfair Display', serif;
        }
        body {
            background: linear-gradient(to bottom, rgb(0, 21, 246), rgb(0, 0, 0));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: var(--font-main);
            background-attachment: fixed;
            padding: 20px;
        }
        .login-card {
            border-radius: 30px;
            display: flex;
            width: 100%;
            max-width: 950px;
            min-height: 550px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            background: white;
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
                max-width: 100%;
                margin: 10px;
                min-height: auto;
            }
            .login-left {
                padding: 30px 20px;
            }
            .login-right {
                padding: 30px 20px;
            }
        }
        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #004AAD;
            color: white;
        }
        .login-left img {
            width: 120px;
            max-width: 100%;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        .login-left h3 {
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .login-right {
            flex: 1;
            background-color: white;
            padding: 40px;
            color: black;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-right h2 {
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 2rem;
            color: #333;
        }
        .form-label {
            color: #555;
            margin-bottom: 5px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .form-control, .form-select {
            background-color: #f8f9fa !important;
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
            color: #333 !important;
            border-radius: 10px !important;
            padding: 10px 15px !important;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        .btn-login {
            background-color: #004AAD;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background-color: #003070;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 74, 173, 0.3);
            color: white;
        }
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            max-width: 350px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .login-card {
                flex-direction: column;
                height: auto;
                min-height: auto;
                border-radius: 20px;
            }
            .login-left {
                padding: 40px 20px;
            }
            .login-right {
                padding: 40px 25px;
            }
            .login-right h2 {
                font-size: 1.75rem;
                text-align: center;
            }
        }
        .copyright-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }
        .copyright-footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        .copyright-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="login-card">
        <div class="login-left">
            <div class="d-flex flex-column justify-content-center align-items-center flex-grow-1 w-200">
                <img src="LOGO1.png" alt="Minven-Absensi" onerror="this.src='https://ptemmarwasehati.com/wp-content/uploads/2023/06/LOGO2.png'">
                <div class="text text-white" style="font-family: 'Playfair Display', serif; font-size: 1rem; letter-spacing: 3px;"> A B S E N S I </div>    
            </div>
            <div class="copyright-footer">
                &copy; <?php echo date("2025"); ?> <a href="#" class="text-white text-decoration-none">MINVEN</a>. All Rights Reserved.
            </div>
        </div>

        <h1>MAAFF SALAHAHHHHHHHHHH</h1>
        
        <div class="login-right">
            <h2>Log in</h2>
            <form method="POST" action="">
                <div class="mb-1">
                    <label for="absen_id" class="form-label">Username</label>
                    <input type="text" class="form-control" id="absen_id" name="absen_id" required 
                           value="<?php echo htmlspecialchars($_POST['absen_id'] ?? $remembered_id); ?>">
                </div>
                <div class="mb-1">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-1">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Pilih Role</option>
                        <option value="staf">Staff</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    Log in
                </button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>