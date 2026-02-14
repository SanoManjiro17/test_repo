<?php
echo "<h1>Installation Check</h1>";
echo "<hr>";

// Check PHP version
echo "<h3>PHP Version Check</h3>";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "<p style='color: green;'>✓ PHP Version: " . PHP_VERSION . " (Compatible)</p>";
} else {
    echo "<p style='color: red;'>✗ PHP Version: " . PHP_VERSION . " (Requires 7.4+)</p>";
}

echo "<hr>";

// Check MySQL connection
echo "<h3>Database Connection Check</h3>";
try {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ MySQL connection successful</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'absensi_system'");
    if ($stmt->fetch()) {
        echo "<p style='color: green;'>✓ Database 'absensi_system' exists</p>";
        
        // Check tables
        $pdo->exec("USE absensi_system");
        
        $tables = ['users', 'absensi', 'tasklist'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Table '$table' not found</p>";
            }
        }
        
        // Check users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch();
        echo "<p>Total users: " . $result['total'] . "</p>";
        
        // List users
        $stmt = $pdo->query("SELECT absen_id, nama, role FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        echo "<h4>Available Users:</h4>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>" . htmlspecialchars($user['absen_id']) . " - " . htmlspecialchars($user['nama']) . " (" . $user['role'] . ") - Password: password</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color: orange;'>⚠ Database 'absensi_system' not found</p>";
        echo "<p>Please run <a href='setup_database.php'>setup_database.php</a> first</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>✗ MySQL connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Make sure MySQL is running and check credentials in config.php</p>";
}

echo "<hr>";
echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>If all checks pass, go to <a href='login.php'>Login Page</a></li>";
echo "<li>If database issues, run <a href='setup_database.php'>Setup Database</a></li>";
echo "<li>For testing, use these accounts:</li>";
echo "<ul>";
echo "<li>Admin: ADMIN001 / password</li>";
echo "<li>Staf: STAF001 / password</li>";
echo "</ul>";
echo "</ol>";
?>