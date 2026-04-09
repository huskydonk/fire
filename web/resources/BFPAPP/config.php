<?php
// config.php
// $host = 'localhost';
// $port = '3307'; // Separate port specification
// $db   = 'bfp_ea';
// $user = 'root';
// $pass = '';  // Add your password if you have one

// // Correct DSN format with port
// $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
// $options = [
//     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
//     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//     PDO::ATTR_EMULATE_PREPARES   => false,
// ];

// try {
//     $pdo = new PDO($dsn, $user, $pass, $options);
// } catch (\PDOException $e) {
//     // Log the error and throw a more user-friendly message
//     error_log("Database Connection Error: " . $e->getMessage());
//     throw new \PDOException("Database connection failed. Please check your configuration.", (int)$e->getCode());
// }

// // Start session if not already started
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

//require_once 'db_config.php';

require_once 'db_config.php';

// --- HELPER: Calculate Distance (Haversine Formula) ---
function getDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 0;
    $earth_radius = 6371; // in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earth_radius * $c, 2); // Returns distance in KM
}
?>