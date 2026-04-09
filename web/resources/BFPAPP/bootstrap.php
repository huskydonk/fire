<?php
// bootstrap.php - Returns server configuration to ESP32

header('Content-Type: application/json');

// Get server configuration from database or config file
$serverConfig = array(
    "server_ip" => "192.168.1.15",      // Your server IP - UPDATE THIS
    "api_path" => "bfp/api_bfp.php",    // Path to your API
    "port" => 80,
    "timestamp" => time()
);

// Optional: Fetch from database if you want to manage it there
// $db = new mysqli("localhost", "user", "pass", "bfp_db");
// $result = $db->query("SELECT server_ip, api_path FROM system_config LIMIT 1");
// if ($result && $row = $result->fetch_assoc()) {
//     $serverConfig = $row;
// }

echo json_encode($serverConfig);
?>