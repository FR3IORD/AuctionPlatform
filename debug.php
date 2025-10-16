<?php
echo "<h1>Debug Information</h1>";

// PHP version
echo "<h2>PHP Version</h2>";
echo phpversion();

// Extensions
echo "<h2>Required Extensions</h2>";
echo "PDO: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "<br>";

// Database connection test
echo "<h2>Database Connection Test</h2>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "Database connection: SUCCESS<br>";
    
    // Test query
    $stmt = $db->query("SELECT 1");
    echo "Test query: SUCCESS<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// File permissions
echo "<h2>File Permissions</h2>";
echo "Config directory readable: " . (is_readable(__DIR__ . '/config') ? 'Yes' : 'No') . "<br>";
echo "Database config readable: " . (is_readable(__DIR__ . '/config/database.php') ? 'Yes' : 'No') . "<br>";

// Session test
echo "<h2>Session Test</h2>";
session_start();
$_SESSION['test'] = 'working';
echo "Session working: " . ($_SESSION['test'] === 'working' ? 'Yes' : 'No') . "<br>";

// Error reporting
echo "<h2>Error Reporting</h2>";
echo "Error reporting level: " . error_reporting() . "<br>";
echo "Display errors: " . ini_get('display_errors') . "<br>";

phpinfo();
?>