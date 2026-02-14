<?php
// File untuk mendapatkan waktu server dalam format JSON
header('Content-Type: application/json');

// Set timezone yang sama dengan aplikasi
date_default_timezone_set('Asia/Jakarta'); // Sesuaikan dengan timezone server Anda

// Dapatkan waktu server
$server_time = date('Y-m-d H:i:s');
$timezone = date_default_timezone_get();

// Return sebagai JSON
echo json_encode([
    'server_time' => $server_time,
    'timezone' => $timezone,
    'timestamp' => time(),
    'formatted_time' => date('H:i:s'),
    'formatted_date' => date('d/m/Y')
]);
?>