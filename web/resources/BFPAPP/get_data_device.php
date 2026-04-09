<?php
// BFP Early Alert System - Device Data Fetcher
require_once 'db_config.php';
header('Content-Type: application/json');

// --- 1. Role Check (Security) ---
// Only BFP roles ('bfp_assigned_at_desk' or 'bfp_officer') should access this endpoint.
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'bfp_assigned_at_desk' && $_SESSION['role'] !== 'bfp_officer')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied: BFP role required.']);
    exit;
}

// --- 2. Database Connection (!!! UPDATE WITH YOUR CREDENTIALS !!!) ---
// $db_host = 'localhost:3307';
// $db_user = 'root'; // <- CHANGE THIS
// $db_pass = '';     // <- CHANGE THIS
// $db_name = 'bfp_ea';

// $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// if ($conn->connect_error) {
//     http_response_code(500);
//     echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
//     exit;
// }



// --- 3. SQL Query to get all devices, locations, and ACTIVE/PENDING incidents ---
// Joins location, sensor, and incident_log tables. Uses LEFT JOIN for incident_log
// so locations without active incidents are still included.
$sql = "SELECT 
            l.location_ID,
            l.location_name, 
            l.address, 
            l.latitude, 
            l.longitude, 
            s.sensor_ID,
            s.sensor_type,
            s.status AS sensor_status,
            il.status AS incident_status,
            il.incident_level
        FROM location l
        JOIN sensor s ON l.location_ID = s.FK_location_ID
        LEFT JOIN incident_log il ON s.sensor_ID = il.FK_sensor_ID 
            AND il.status IN ('pending', 'dispatched') 
        WHERE l.latitude IS NOT NULL AND l.longitude IS NOT NULL";

$result = $conn->query($sql);

$devices = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $devices[] = [
            'loc_name' => $row['location_name'],
            'address' => $row['address'],
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
            'sensor_type' => $row['sensor_type'],
            'sensor_status' => $row['sensor_status'],
            'incident_status' => $row['incident_status'],
            'incident_level' => $row['incident_level']
        ];
    }
}

$conn->close();

// --- 4. Output JSON ---
echo json_encode($devices);
?>