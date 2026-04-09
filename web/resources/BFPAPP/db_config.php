<?php
// Database Configuration
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_40576238_bfp_ea');
define('DB_USER', 'if0_40576238');
define('DB_PASS', 'qi4tRCyGQTIbNk'); // Add your InfinityFree database password here
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// define('DB_HOST', 'localhost');
// define('DB_PORT', '3307');
// define('DB_NAME', 'bfp_ea');
// define('DB_USER', 'root');
// define('DB_PASS', ''); // Add your InfinityFree database password here

// Auto-connect function
function db_connect() {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

$pdo = db_connect();
$conm = $pdo;
$mPdo = $pdo;



define('RECORDS_PER_PAGE', 10); // Change 10 to however many rows you want per page

// Start session if not already started

?>