<?php
require_once 'config.php';

echo "<h1>Test Database Connection</h1>";

try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch();
    echo "<p>Total users: " . $result['total'] . "</p>";
    
    // Test admin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE absen_id = ?");
    $stmt->execute(['ADMIN001']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✓ Admin user found: " . htmlspecialchars($admin['nama']) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Admin user not found</p>";
    }
    
    // Test staf users
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ?");
    $stmt->execute(['staf']);
    $staf_list = $stmt->fetchAll();
    
    echo "<p>Total staf: " . count($staf_list) . "</p>";
    
    echo "<h2>Available Users:</h2>";
    echo "<ul>";
    foreach ($staf_list as $staf) {
        echo "<li>" . htmlspecialchars($staf['absen_id']) . " - " . htmlspecialchars($staf['nama']) . " (Password: password)</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Go to Login Page</a></p>";
?>