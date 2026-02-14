<?php
// File instalasi untuk setup awal aplikasi
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installasi Aplikasi Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-cog"></i> Installasi Aplikasi Absensi
                        </h4>
                    </div>
                    <div class="card-body">
                        <h5>Langkah-langkah Installasi:</h5>
                        
                        <ol>
                            <li>
                                <strong>Buat Database</strong>
                                <p>Buat database baru di MySQL dengan nama <code>absensi_system</code></p>
                                <div class="bg-light p-2 rounded">
                                    <code>CREATE DATABASE absensi_system;</code>
                                </div>
                            </li>
                            
                            <li class="mt-3">
                                <strong>Import Database</strong>
                                <p>Import file <code>database.sql</code> ke database yang sudah dibuat</p>
                                <div class="bg-light p-2 rounded">
                                    <small>
                                        Bisa menggunakan phpMyAdmin atau command line:<br>
                                        <code>mysql -u root -p absensi_system < database.sql</code>
                                    </small>
                                </div>
                            </li>
                            
                            <li class="mt-3">
                                <strong>Konfigurasi Database</strong>
                                <p>Edit file <code>config.php</code> dan sesuaikan setting database:</p>
                                <div class="bg-light p-2 rounded">
                                    <code>
                                        $host = 'localhost';<br>
                                        $dbname = 'absensi_system';<br>
                                        $username = 'root'; // sesuaikan<br>
                                        $password = ''; // sesuaikan
                                    </code>
                                </div>
                            </li>
                            
                            <li class="mt-3">
                                <strong>Test Koneksi</strong>
                                <p>Klik tombol di bawah untuk test koneksi database:</p>
                                <a href="test_connection.php" class="btn btn-info">
                                    <i class="fas fa-plug"></i> Test Koneksi Database
                                </a>
                            </li>
                            
                            <li class="mt-3">
                                <strong>Akses Aplikasi</strong>
                                <p>Setelah semua berhasil, akses aplikasi:</p>
                                <a href="login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Buka Aplikasi
                                </a>
                            </li>
                        </ol>
                        
                        <hr>
                        
                        <h6>Akun Default:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Admin/Manager</h6>
                                        <p class="mb-1"><strong>Absen ID:</strong> ADMIN001</p>
                                        <p class="mb-0"><strong>Password:</strong> password</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Staf</h6>
                                        <p class="mb-1"><strong>Absen ID:</strong> STAF001</p>
                                        <p class="mb-0"><strong>Password:</strong> password</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Pastikan Apache dan MySQL berjalan sebelum mengakses aplikasi.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>