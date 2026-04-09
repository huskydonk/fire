<?php
// ====================================================================
// CONSOLIDATED AND FIXED API ENDPOINT (api.php)
// This file merges the logic from both submitted blocks into one coherent structure.
// ====================================================================
$PRODUCTION = false;
// --- 0. INITIAL SETUP & ERROR CONTROL ---
ini_set('display_errors', 1); // Enable for debugging
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for JSON response and CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

define('SMS_HOST', '10.194.217.244:8080');
// define('SMS_PORT', '8080');
define('SMS_USER', 'sms');
define('SMS_PASS', '8ZrM1BXc');
define('SMS_PATH', '/messages');

define('CALL_HOST', '10.194.217.244:8084');
// define('CALL_PORT', '8084');
define('CALL_USER', 'call');
define('CALL_PASS', 'UZ685aOv');

if ($PRODUCTION) {
    ini_set('display_errors', 0);
    error_reporting(0);
    define('SMS_HOST', 'api.sms-gate.app');
    define('SMS_PATH', '/3rdparty/v1/messages');
}



function sendGatewaySMS($phone, $message) {
    // $url = "http://" . SMS_HOST . ":" . SMS_PORT . "/message";
    $url = "http://" . SMS_HOST . SMS_PATH;
    $data = [
        "textMessage" => ["text" => $message],
        "phoneNumbers" => [$phone]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, SMS_USER . ":" . SMS_PASS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// function sendGatewayCall($phone) {
//     $url = "http://" . CALL_HOST . "/api/v1/calls";
//     $data = [
//         "call" => ["phoneNumber" => $phone]
//     ];

//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
//     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
//     curl_setopt($ch, CURLOPT_USERPWD, CALL_USER . ":" . CALL_PASS);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     $response = curl_exec($ch);
//     curl_close($ch);
//     return $response;
// }

function sendGatewayCall($phone) {
    // Ensure the PORT matches your Android App settings (usually 8080 or 8084)
    $url = "http://" . CALL_HOST . "/api/v1/calls"; 
    
    // Ensure the JSON structure matches what your Gateway App expects
    $data = [
        "call" => ["phoneNumber" => $phone]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, CALL_USER . ":" . CALL_PASS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Add timeout to prevent hanging if phone is offline
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- 1. Connection (Uses PDO from config.php) and Global Helpers ---
try {
    // Assumes 'config.php' includes PDO connection as $pdo and starts session
    require_once 'config.php'; 
    if (!isset($pdo)) {
        throw new Exception("PDO connection not available.");
    }
} catch (Exception $e) {
    error_log("Initialization failed: " . $e->getMessage());
    // Use die() before helper functions are defined
    die(json_encode(["success" => false, "message" => "Server initialization error. Please check server configuration."]));
}

// Global functions for responding
function respond($data) {
    echo json_encode(["success" => true, "data" => $data]);
    exit();
}

function error_response($message, $code = 400) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}

function get_json_input() {
    $input = file_get_contents("php://input");
    return json_decode($input, true);
}


// --- 2. HELPER & DATA FUNCTIONS (Consolidated) ---

function timeDiffInMinutes($start, $end) {
    $start_ts = strtotime($start);
    $end_ts = strtotime($end);
    if ($start_ts && $end_ts) {
        return round(abs($end_ts - $start_ts) / 60);
    }
    return 0;
}

function getUserIdByEmail(PDO $pdo, string $email): ?int {
    $stmt = $pdo->prepare("SELECT user_ID FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $result = $stmt->fetch();
    return $result ? (int)$result['user_ID'] : null;
}

function fetchMetrics(PDO $pdo): array {
    $current_month_start = date('Y-m-01 00:00:00');

    // Total Users
    $total_users = $pdo->query("SELECT COUNT(user_ID) FROM users")->fetchColumn();
    
    // Total Active Devices (sensors with status 'active')
    $active_devices = $pdo->query("SELECT COUNT(sensor_ID) FROM sensor WHERE status = 'active'")->fetchColumn();

    // Active Alerts (pending/dispatched incidents)
    $active_alerts = $pdo->query("SELECT COUNT(incident_ID) FROM incident_log WHERE status IN ('pending', 'dispatched')")->fetchColumn();

    // Total Responses (resolved incidents)
    $total_responses = $pdo->query("SELECT COUNT(incident_ID) FROM incident_log WHERE status = 'resolved'")->fetchColumn();
    
    // Monthly Responses (resolved incidents this month)
    $monthly_responses = $pdo->prepare("SELECT COUNT(incident_ID) FROM incident_log WHERE status = 'resolved' AND start_timestamp >= ?");
    $monthly_responses->execute([$current_month_start]);
    $monthly_responses_count = $monthly_responses->fetchColumn();

    // Avg Response Time (in minutes) - Simplified: assume response time is diff between start and end
    $avg_response_time = 0;
    if ($total_responses > 0) {
        $stmt_avg = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, start_timestamp, end_timestamp) as duration FROM incident_log WHERE status = 'resolved'");
        $durations = $stmt_avg->fetchAll(PDO::FETCH_COLUMN);
        $total_duration = array_sum($durations);
        $avg_response_time = round($total_duration / count($durations));
    }
    
    return [
        'total_users' => (int)$total_users,
        'active_devices' => (int)$active_devices,
        'active_alerts' => (int)$active_alerts,
        'monthly_responses' => (int)$monthly_responses_count,
        'total_responses' => (int)$total_responses,
        'avg_response_time' => $avg_response_time,
        // Zero casualty is often reported as total resolved incidents by BFP-type orgs in absence of casualty data
        'zero_casualty' => (int)$total_responses, 
    ];
}

function fetchUsers(PDO $pdo): array {
    $query = "
        SELECT 
            u.user_ID, u.first_name, u.last_name, u.email, u.phone_number, u.role, 
            l.address, 
            (SELECT COUNT(s.sensor_ID) FROM sensor s JOIN location l2 ON s.FK_location_ID = l2.location_ID WHERE l2.FK_user_ID = u.user_ID) as device_count,
            (SELECT COUNT(s.sensor_ID) FROM sensor s JOIN location l3 ON s.FK_location_ID = l3.location_ID WHERE l3.FK_user_ID = u.user_ID AND s.status = 'active') as active_device_count
        FROM users u
        LEFT JOIN location l ON u.user_ID = l.FK_user_ID
        GROUP BY u.user_ID
        ORDER BY u.user_ID DESC
    ";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll();
}

function fetchUserById($pdo, $userId) {
    $sql = "SELECT u.*, MAX(l.address) AS address FROM users u LEFT JOIN location l ON u.user_ID = l.FK_user_ID WHERE u.user_ID = ? GROUP BY u.user_ID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchIncidents(PDO $pdo): array {
    $query = "
        SELECT 
            i.incident_ID, i.FK_sensor_ID, i.start_timestamp, i.end_timestamp, i.status, i.incident_type, i.incident_level,
            s.sensor_ID, s.sensor_type as device,
            l.address, l.latitude, l.longitude,
            u.first_name, u.last_name, u.phone_number
        FROM incident_log i
        JOIN sensor s ON i.FK_sensor_ID = s.sensor_ID
        JOIN location l ON s.FK_location_ID = l.location_ID
        JOIN users u ON l.FK_user_ID = u.user_ID
        ORDER BY i.start_timestamp DESC
    ";
    $stmt = $pdo->query($query);
    $incidents = $stmt->fetchAll();
    
    return array_map(function($inc) {
        $inc['coordinates'] = "{$inc['latitude']}, {$inc['longitude']}";
        if ($inc['end_timestamp']) {
            $start = new DateTime($inc['start_timestamp']);
            $end = new DateTime($inc['end_timestamp']);
            $diff = $start->diff($end);
            $inc['response_time'] = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        } else {
            $inc['response_time'] = null;
        }
        return $inc;
    }, $incidents);
}

function fetchMapData(PDO $pdo): array {
    // Active Devices with Location Data
    $devices_query = "
        SELECT 
            s.sensor_ID, s.sensor_type, s.status, l.latitude, l.longitude, l.location_name, l.address, l.FK_user_ID,
            (SELECT COUNT(i.incident_ID) FROM incident_log i WHERE i.FK_sensor_ID = s.sensor_ID AND i.status IN ('pending', 'dispatched')) as active_alert_count
        FROM sensor s
        JOIN location l ON s.FK_location_ID = l.location_ID
        WHERE l.latitude IS NOT NULL AND l.longitude IS NOT NULL
    ";
    $devices = $pdo->query($devices_query)->fetchAll();
    
    // BFP Stations
    $stations = $pdo->query("SELECT station_id, station_name, latitude, longitude, contact_number FROM bfp_stations")->fetchAll();

    return [
        'activeDevices' => $devices,
        'stations' => $stations,
    ];
}

function mapIncidentTypePHP($device, $level) {
    $d = strtolower((string)($device ?? ''));
    $lvl = strtoupper((string)($level ?? ''));

    if (strpos($d, 'smoke') !== false || strpos($d, 'heat') !== false || strpos($d, 'flame') !== false || strpos($d, 'fire') !== false) return 'Fire';
    if (strpos($d, 'gas') !== false || strpos($d, 'co') !== false || strpos($d, 'carbon') !== false) return 'Gas Leak';
    if ($lvl === 'HIGH' || $lvl === 'CRITICAL') return 'Critical Incident';
    return 'Other';
}
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
          cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
          sin(deg2rad($dLon)/2) * sin(deg2rad($dLon)/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    return $distance;
}

// --- Push subscription helpers (store subscriptions in a JSON file) ---
function getPushStoragePath() {
  return __DIR__ . DIRECTORY_SEPARATOR . 'push_subscriptions.json';
}

function readPushSubscriptions() {
  $path = getPushStoragePath();
  if (!file_exists($path)) return [];
  $json = file_get_contents($path);
  $data = json_decode($json, true);
  return is_array($data) ? $data : [];
}

function writePushSubscriptions($list) {
  $path = getPushStoragePath();
  file_put_contents($path, json_encode(array_values($list), JSON_PRETTY_PRINT));
}

function addPushSubscription($subscription) {
  if (!$subscription || !is_array($subscription)) return false;
  $subs = readPushSubscriptions();
  // Ensure unique by endpoint
  $endpoint = $subscription['endpoint'] ?? null;
  if (!$endpoint) return false;

  $found = false;
  foreach ($subs as $i => $s) {
    if (isset($s['endpoint']) && $s['endpoint'] === $endpoint) {
      // Update existing
      $subs[$i] = array_merge($s, $subscription);
      $found = true;
      break;
    }
  }
  if (!$found) $subs[] = $subscription;
  writePushSubscriptions($subs);
  return true;
}

function listPushSubscriptions() {
  return readPushSubscriptions();
}


// --- 3. CORE LOGIC FUNCTIONS (BFP Command Center Focused) ---

function handleLogin($pdo, $data) {
  $allowed_roles = ['bfp_officer', 'bfp_assigned_at_desk', 'resident']; 
  $role_placeholders = implode(',', array_fill(0, count($allowed_roles), '?'));
  
  if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    return ['status' => 'error', 'message' => 'Email and password are required.'];
  }

  try {
    // Check if the user exists
    $sql = "
      SELECT user_ID, email, enc_password, role, first_name, last_name 
      FROM users 
      WHERE email = ? AND role IN ($role_placeholders)
      LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$data['username']], $allowed_roles);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      usleep(200000); // Prevent timing attacks
      http_response_code(401);
      return [
        'status' => 'error', 
        'message' => 'Invalid credentials or unauthorized access.'
      ];
    }

    // if (password_verify($data['password'], $user['enc_password'])) {
    if (true) { // Password verification bypassed for testing
      // LOGIN SUCCESS - Start session
      $_SESSION['user_id'] = $user['user_ID'];
      $_SESSION['user_role'] = $user['role'];
      $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);

      return [
        'status' => 'success', 
        'message' => 'Login successful.', 
        'user' => [
          'id' => $user['user_ID'], 
          'name' => $_SESSION['name'], 
          'role' => $user['role']
        ]
      ];
    } else {
      usleep(200000);
      http_response_code(401);
      return [
        'status' => 'error', 
        'message' => 'Invalid credentials or unauthorized access.'
      ];
    }

  } catch (\PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    return [
      'status' => 'error', 
      'message' => 'Unable to process login. Please try again.'
    ];
  }
}

function handleLogout() {
  session_unset();
  session_destroy();
  return ['status' => 'success', 'message' => 'Logged out successfully.'];
}

function fetchDashboardData($pdo) {
  // A. Users
  $stmt_users = $pdo->query("
    SELECT 
      u.user_ID, u.first_name, u.last_name, u.phone_number, 
      l.address, l.location_name,
      (SELECT COUNT(sensor_ID) FROM sensor s JOIN location l_sub ON s.FK_location_ID = l_sub.location_ID WHERE l_sub.FK_user_ID = u.user_ID) as device_count,
      (SELECT COUNT(sensor_ID) FROM sensor s JOIN location l_sub ON s.FK_location_ID = l_sub.location_ID WHERE l_sub.FK_user_ID = u.user_ID AND s.status = 'active') > 0 as status_active_devices
    FROM users u
    LEFT JOIN location l ON u.user_ID = l.FK_user_ID
    WHERE u.role = 'resident' OR u.role = 'bfp_officer'
    GROUP BY u.user_ID
    ORDER BY u.last_name
  ");
  $users = $stmt_users->fetchAll();

  foreach ($users as &$user) {
    $user['status'] = $user['status_active_devices'] ? 'ACTIVE' : 'INACTIVE';
    unset($user['status_active_devices']); 
  }

  // B. Active Alerts
  $stmt_alerts = $pdo->query("
    SELECT 
      il.incident_ID, il.start_timestamp, il.status, il.incident_level, 
      s.sensor_type AS device, l.address, 
      u.first_name, u.last_name, u.phone_number,
      l.latitude AS latitude, l.longitude AS longitude,
      CONCAT(l.latitude, '° N, ', l.longitude, '° E') AS coordinates
    FROM incident_log il
    JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
    JOIN location l ON s.FK_location_ID = l.location_ID
    JOIN users u ON l.FK_user_ID = u.user_ID
    WHERE il.status IN ('PENDING', 'DISPATCHED')
    ORDER BY il.start_timestamp DESC
  ");
  $activeIncidents = $stmt_alerts->fetchAll();

  foreach ($activeIncidents as &$ai) {
    $ai['incident_type'] = mapIncidentTypePHP($ai['device'] ?? '', $ai['incident_level'] ?? '');
  }
  unset($ai);

  // C. Response History
  $stmt_history = $pdo->query("
    SELECT 
      il.incident_ID, il.start_timestamp, il.end_timestamp, il.incident_level,
      s.sensor_type AS device, u.first_name, u.last_name, l.address
    FROM incident_log il
    JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
    JOIN location l ON s.FK_location_ID = l.location_ID
    JOIN users u ON l.FK_user_ID = u.user_ID
    WHERE il.status = 'RESOLVED'
    ORDER BY il.end_timestamp DESC
  ");
  $resolvedIncidents = $stmt_history->fetchAll();

  // D. BFP Stations
  $stations = $pdo->query("SELECT * FROM bfp_stations")->fetchAll();

  // E. Metrics
  $total_users = count($users);
  $active_alerts = count($activeIncidents);
  $total_responses = count($resolvedIncidents);
  $total_devices = array_sum(array_column($users, 'device_count'));

  $total_response_time = 0;
  $zero_casualty = $total_responses; // Placeholder metric

  foreach ($resolvedIncidents as &$i) {
    $i['response_time'] = timeDiffInMinutes($i['start_timestamp'], $i['end_timestamp']);
    $total_response_time += $i['response_time'];
    $i['date'] = date('M d', strtotime($i['end_timestamp']));
    $i['incident_type'] = mapIncidentTypePHP($i['device'] ?? '', $i['incident_level'] ?? '');
  }
  unset($i);

  $avg_response_time = $total_responses > 0 ? round($total_response_time / $total_responses) : 0;

  return [
    'users' => $users,
    'activeIncidents' => $activeIncidents,
    'resolvedIncidents' => $resolvedIncidents,
    'stations' => $stations,
    'metrics' => [
      'total_users' => $total_users,
      'active_devices' => $total_devices,
      'active_alerts' => $active_alerts,
      'total_responses' => $total_responses,
      'avg_response_time' => $avg_response_time,
      'zero_casualty' => $zero_casualty,
      'monthly_responses' => $active_alerts + $total_responses
    ]
  ];
}

function getIncidentTrends($pdo, $period = 'monthly', $ownerRole = null) {
  $period = strtolower($period);
  $rows = [];

  try {
    if ($period === 'weekly') {
      $dateCond = "start_timestamp >= CURDATE() - INTERVAL 6 DAY";
      $since = (new DateTime())->modify('-6 days')->format('Y-m-d');
      $dateSelect = "DATE(start_timestamp) AS dt";
    } else if ($period === 'yearly') {
      $dateCond = "start_timestamp >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
      $since = (new DateTime())->modify('-12 months')->format('Y-m-d');
      $dateSelect = "DATE_FORMAT(start_timestamp, '%Y-%m') AS dt";
    } else {
      $dateCond = "start_timestamp >= CURDATE() - INTERVAL 29 DAY";
      $since = (new DateTime())->modify('-29 days')->format('Y-m-d');
      $dateSelect = "DATE(start_timestamp) AS dt";
    }

    $sql_base = "SELECT $dateSelect, COUNT(*) AS cnt FROM incident_log il 
                 JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID 
                 JOIN location l ON s.FK_location_ID = l.location_ID 
                 JOIN users u ON l.FK_user_ID = u.user_ID 
                 WHERE $dateCond";
    
    if ($ownerRole) {
      $sql = $sql_base . " AND u.role = :owner GROUP BY dt ORDER BY dt ASC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':owner' => $ownerRole]);
    } else {
      $sql = $sql_base . " GROUP BY dt ORDER BY dt ASC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
    }
    $rows = $stmt->fetchAll();

    $sql_type_base = "SELECT s.sensor_type AS device, il.incident_level, COUNT(*) as cnt FROM incident_log il 
                      JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID 
                      JOIN location l ON s.FK_location_ID = l.location_ID 
                      JOIN users u ON l.FK_user_ID = u.user_ID 
                      WHERE il.start_timestamp >= :since";
                      
    if ($ownerRole) {
      $stmt2 = $pdo->prepare($sql_type_base . " AND u.role = :owner GROUP BY s.sensor_type, il.incident_level");
      $stmt2->execute([':since' => $since, ':owner' => $ownerRole]);
    } else {
      $stmt2 = $pdo->prepare($sql_type_base . " GROUP BY s.sensor_type, il.incident_level");
      $stmt2->execute([':since' => $since]);
    }
    $byTypeRaw = $stmt2->fetchAll();

    $by_type = [];
    foreach ($byTypeRaw as $r) {
      $type = mapIncidentTypePHP($r['device'] ?? '', $r['incident_level'] ?? '');
      if (!isset($by_type[$type])) $by_type[$type] = 0;
      $by_type[$type] += (int)$r['cnt'];
    }

    return ['status' => 'success', 'period' => $period, 'by_date' => $rows, 'by_type' => $by_type];
  } catch (\PDOException $e) {
    http_response_code(500);
    return ['status' => 'error', 'message' => 'Failed to compute trends.', 'detail' => $e->getMessage()];
  }
}

function handleIncidentsCrud($pdo, $method, $input) {
  try {
    if ($method === 'GET') {
      $id = $_GET['id'] ?? null;
      if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM incident_log WHERE incident_ID = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); return ['status'=>'error','message'=>'Not found']; }
        return ['status'=>'success','data'=>$row];
      }
      $owner = $_GET['owner'] ?? null;
      $sql = "SELECT il.incident_ID, il.start_timestamp, il.end_timestamp, il.status, il.incident_level, il.FK_sensor_ID, s.sensor_type, l.address FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID";
      $params = [];
      if ($owner) {
        $sql .= " WHERE u.role = ?";
        $params[] = $owner;
      }
      $sql .= " ORDER BY il.start_timestamp DESC LIMIT 200";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll();
      
      foreach ($rows as &$r) { $r['incident_type'] = mapIncidentTypePHP($r['sensor_type'] ?? '', $r['incident_level'] ?? ''); }
      unset($r);
      return ['status'=>'success','data'=>$rows];
    }

        if ($method === 'POST') {
        $sensor = $input['FK_sensor_ID'] ?? null;
        $level = $input['incident_level'] ?? 'LOW';
        $status = $input['status'] ?? 'PENDING';
        $incident_type_param = $input['incident_type'] ?? 'fire';

        if (!$sensor) return ['status' => 'error', 'message' => 'Sensor ID required'];

        // 1. Insert Incident
        $stmt = $pdo->prepare("INSERT INTO incident_log (FK_sensor_ID, start_timestamp, status, incident_level, incident_type) VALUES (?, NOW(), ?, ?, ?)");
        $stmt->execute([$sensor, $status, $level, $incident_type_param]);
        $id = $pdo->lastInsertId();

        // 2. TRIGGER SMS IF CRITICAL (HIGH)
        if (strtoupper($level) === 'HIGH') {
            // Find the user who owns this sensor
            $userStmt = $pdo->prepare("
                SELECT u.phone_number, u.first_name, l.address 
                FROM sensor s
                JOIN location l ON s.FK_location_ID = l.location_ID
                JOIN users u ON l.FK_user_ID = u.user_ID
                WHERE s.sensor_ID = ? LIMIT 1
            ");
            $userStmt->execute([$sensor]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['phone_number'])) {
                $msg = "BFP ALERT: Fire/Hazard detected at " . $user['address'] . ". Authorities notified.";
                
                // Send SMS via Gateway
                sendGatewaySMS($user['phone_number'], $msg);
                
                // Optional: Make Call
                sendGatewayCall($user['phone_number']);
            }
        }

        return ['status' => 'success', 'message' => 'Incident created & Alert sent', 'incident_ID' => $id];
    }

    if ($method === 'PUT') {
      $id = $input['incident_ID'] ?? null;
      if (!$id) { http_response_code(400); return ['status'=>'error','message'=>'incident_ID required']; }
      $fields = [];
      $params = [];
      if (isset($input['status'])) { $fields[] = 'status = ?'; $params[] = $input['status']; }
      if (isset($input['incident_level'])) { $fields[] = 'incident_level = ?'; $params[] = $input['incident_level']; }
      if (isset($input['end_timestamp'])) { $fields[] = 'end_timestamp = ?'; $params[] = $input['end_timestamp']; }
      if (empty($fields)) { http_response_code(400); return ['status'=>'error','message'=>'No fields to update']; }
      $params[] = $id;
      $sql = "UPDATE incident_log SET " . implode(', ', $fields) . " WHERE incident_ID = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
     return ['status' => 'success', 'message' => 'Operation handled']; 
    }

    if ($method === 'DELETE') {
      $id = $input['incident_ID'] ?? null;
      if (!$id) { http_response_code(400); return ['status'=>'error','message'=>'incident_ID required']; }
      $stmt = $pdo->prepare("DELETE FROM incident_log WHERE incident_ID = ?");
      $stmt->execute([$id]);
     return ['status' => 'success', 'message' => 'Operation handled']; 
    }

    http_response_code(405);
    return ['status'=>'error','message'=>'Method not supported'];
  } catch (\PDOException $e) {
    http_response_code(500);
    return ['status'=>'error','message'=>'Database error','detail'=>$e->getMessage()];
  }
}

function handleRecordReading($pdo, $input) {
    // Expected Input: { "sensor_id": 1, "reading": "1200 ppm" }
    $sensor_id = $input['sensor_id'] ?? null;
    $reading = $input['reading'] ?? null;

    if (!$sensor_id || !$reading) {
        return ['status' => 'error', 'message' => 'Missing data'];
    }

    try {
        // 1. Check if a record already exists for this sensor
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sensor_reading WHERE FK_sensor_ID = ?");
        $checkStmt->execute([$sensor_id]);
        $exists = $checkStmt->fetchColumn() > 0;

        if ($exists) {
            // 2. UPDATE: If record exists, update the value and the timestamp
            $stmt = $pdo->prepare("UPDATE sensor_reading SET reading_value = ?, timestamp = NOW() WHERE FK_sensor_ID = ?");
            $stmt->execute([$reading, $sensor_id]);
            $message = "Reading updated successfully";
        } else {
            // 3. INSERT: If this is the first time this sensor is sending data, insert it
            $stmt = $pdo->prepare("INSERT INTO sensor_reading (FK_sensor_ID, reading_value, timestamp) VALUES (?, ?, NOW())");
            $stmt->execute([$sensor_id, $reading]);
            $message = "Initial reading created";
        }

        return ['status' => 'success', 'message' => $message];

    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

function handleStationsCrud($pdo, $method, $input) {
  try {
    if ($method === 'GET') {
      $id = $_GET['id'] ?? null;
      $search = $_GET['search'] ?? null;
      $sql = "SELECT station_ID, station_name, latitude, longitude, contact_number FROM bfp_stations";
      $params = [];

      if ($id) {
        $sql .= " WHERE station_ID = ?";
        $params[] = $id;
      } elseif ($search) {
        $sql .= " WHERE station_name LIKE ?";
        $params[] = '%' . $search . '%';
      }
      
      $sql .= " ORDER BY station_name ASC";
      
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $rows = $stmt->fetchAll();

      if ($id && empty($rows)) {
        http_response_code(404);
        return ['status' => 'error', 'message' => 'Station not found.'];
      }
      return ['status' => 'success', 'data' => $rows];
    }

    if ($method === 'POST') {
      $name = $input['station_name'] ?? null;
      $lat = $input['latitude'] ?? null;
      $lon = $input['longitude'] ?? null;
      $contact = $input['contact_number'] ?? null;

      if (!$name || !$lat || !$lon) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Name, latitude, and longitude are required.'];
      }

      $stmt = $pdo->prepare("
        INSERT INTO bfp_stations (station_name, latitude, longitude, contact_number)
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([$name, $lat, $lon, $contact]);
      $id = $pdo->lastInsertId();
      return ['status' => 'success', 'message' => 'Station added successfully.', 'station_ID' => $id];
    }

    if ($method === 'PUT') {
      $id = $input['station_ID'] ?? null;
      if (!$id) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Station ID required for update.'];
      }
      
      $fields = [];
      $params = [];
      if (isset($input['station_name'])) { $fields[] = 'station_name = ?'; $params[] = $input['station_name']; }
      if (isset($input['latitude'])) { $fields[] = 'latitude = ?'; $params[] = $input['latitude']; }
      if (isset($input['longitude'])) { $fields[] = 'longitude = ?'; $params[] = $input['longitude']; }
      if (isset($input['contact_number'])) { $fields[] = 'contact_number = ?'; $params[] = $input['contact_number']; }

      if (empty($fields)) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'No fields provided for update.'];
      }

      $params[] = $id;
      $sql = "UPDATE bfp_stations SET " . implode(', ', $fields) . " WHERE station_ID = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return ['status' => 'success', 'message' => 'Station updated successfully.'];
    }

    if ($method === 'DELETE') {
      $id = $input['station_ID'] ?? null;
      if (!$id) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Station ID required for deletion.'];
      }
      $stmt = $pdo->prepare("DELETE FROM bfp_stations WHERE station_ID = ?");
      $stmt->execute([$id]);
      return ['status' => 'success', 'message' => 'Station deleted successfully.'];
    }

    http_response_code(405);
    return ['status' => 'error', 'message' => 'Method not supported for this endpoint.'];
  } catch (\PDOException $e) {
    http_response_code(500);
    return ['status' => 'error', 'message' => 'Database error', 'detail' => $e->getMessage()];
  }
}

function handleUserCrud($pdo, $method, $data) {
  // GET - Show Single User
  if ($method === 'GET' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("
      SELECT 
        u.user_ID, u.first_name, u.last_name, u.phone_number, u.email, u.role, u.status,
        l.location_ID, l.address, l.location_name
      FROM users u   
      LEFT JOIN location l ON u.user_ID = l.FK_user_ID
      WHERE u.user_ID = ?
    ");
    $stmt->execute([$_GET['id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
      http_response_code(404);
      return ['status' => 'error', 'message' => 'User not found.'];
    }
    return ['status' => 'success', 'data' => $user_data];
  }

  // POST - Create User (Placeholder)
  if ($method === 'POST') {
    http_response_code(201);
    return ['status' => 'success', 'message' => 'User created (Placeholder).'];
  }

  // PUT - Update User
  if ($method === 'PUT') {
    $user_id = $data['user_ID'] ?? null;
    if (!$user_id) {
      http_response_code(400);
      return ['status' => 'error', 'message' => 'User ID is missing.'];
    }

    try {
      $stmt_user = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, phone_number = ?, email = ?, role = ?
        WHERE user_ID = ?
      ");
      $stmt_user->execute([
        $data['first_name'] ?? null,
        $data['last_name'] ?? null,
        $data['phone_number'] ?? null,
        $data['email'] ?? null,
        $data['role'] ?? 'resident',
        $user_id
      ]);

      if (isset($data['address']) || isset($data['location_name'])) {
           $stmt_location = $pdo->prepare("
              UPDATE location 
              SET address = ?, location_name = ?
              WHERE FK_user_ID = ?
              LIMIT 1
            ");
            $stmt_location->execute([
              $data['address'] ?? null,
              $data['location_name'] ?? null,
              $user_id
            ]);
      }

      return ['status' => 'success', 'message' => "User {$user_id} updated successfully."];

    } catch (\PDOException $e) {
      http_response_code(500);
      return ['status' => 'error', 'message' => 'Update failed.', 'detail' => $e->getMessage()];
    }
  }

  // DELETE - Delete User
  if ($method === 'DELETE') {
    $user_id = $data['user_ID'] ?? null;
    if (!$user_id) {
      http_response_code(400);
      return ['status' => 'error', 'message' => 'User ID is missing.'];
    }
    $pdo->prepare("DELETE FROM location WHERE FK_user_ID = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE user_ID = ?")->execute([$user_id]);
    return ['status' => 'success', 'message' => "User {$user_id} deleted successfully."];
  }

  // GET - List all users (For dashboard UI)
  if ($method === 'GET') {
     $stmt = $pdo->query("
      SELECT 
        u.user_ID, u.first_name, u.last_name, u.phone_number, u.email, u.role,
        l.address, l.location_name
      FROM users u
      LEFT JOIN location l ON u.user_ID = l.FK_user_ID
      ORDER BY u.user_ID DESC
      LIMIT 200
    ");
     return ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
  }

  http_response_code(405);
  return ['status' => 'error', 'message' => 'Method not supported.'];
}
function fetchDeviceDetails($pdo, $userId) {
    if (!$userId) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'User ID is required.'];
    }

    try {
        // 1. Fetch User & Location Info
        $stmt_user_loc = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.phone_number, 
                   l.location_name, l.address
            FROM users u
            JOIN location l ON u.user_ID = l.FK_user_ID
            WHERE u.user_ID = ?
            LIMIT 1
        ");
        $stmt_user_loc->execute([$userId]);
        $user_data = $stmt_user_loc->fetch();

        if (!$user_data) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'User or primary location not found.'];
        }

        $response_data = [
            'user' => [
                'name' => trim($user_data['first_name'] . ' ' . $user_data['last_name']),
                'phone' => $user_data['phone_number'],
            ],
            'location' => [
                'name' => $user_data['location_name'],
                'address' => $user_data['address'],
            ],
            'sensors' => []
        ];

        // 2. Fetch All Sensors for this User
        $stmt_sensors = $pdo->prepare("
            SELECT s.sensor_ID, s.sensor_type, s.status
            FROM sensor s
            JOIN location l ON s.FK_location_ID = l.location_ID
            WHERE l.FK_user_ID = ?
            ORDER BY s.sensor_ID ASC
        ");
        $stmt_sensors->execute([$userId]);
        $sensors = $stmt_sensors->fetchAll();

        // 3. Loop through sensors and get SPECIFIC readings for each
        foreach ($sensors as $sensor) {
            $s_id = $sensor['sensor_ID'];

            // Helper function to extract numbers (e.g., "SMOKE 500" -> 500)
            $extractVal = function($str) {
                if(preg_match('/(\d+\.?\d*)/', $str, $m)) return floatval($m[1]);
                return null;
            };

            // A. Get Latest SMOKE/GAS reading
            $stmtGas = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? 
                AND (reading_value LIKE 'SMOKE%' OR reading_value LIKE 'GAS%' OR reading_value LIKE 'PPM%') 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtGas->execute([$s_id]);
            $gasRow = $stmtGas->fetchColumn();
            $gasVal = $gasRow ? $extractVal($gasRow) : null;

            // B. Get Latest TEMPERATURE reading
            $stmtTemp = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? 
                AND (reading_value LIKE 'TEMP%' OR reading_value LIKE 'CELSIUS%') 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtTemp->execute([$s_id]);
            $tempRow = $stmtTemp->fetchColumn();
            $tempVal = $tempRow ? $extractVal($tempRow) : null;

            // C. Get Latest FIRE reading
            $stmtFire = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? 
                AND reading_value LIKE 'FIRE%' 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtFire->execute([$s_id]);
            $fireRow = $stmtFire->fetchColumn();
            $fireVal = $fireRow ? $extractVal($fireRow) : 4095; // Default safe (High value = No Fire)

            // D. Determine Fire Status Logic
            $fireStatus = 'SAFE';
            
            // Check Flame Sensor (Low value means fire)
            if ($fireVal < 500) {
                $fireStatus = 'CRITICAL';
            }
            // Check Thresholds
            elseif (($gasVal && $gasVal > 1200) || ($tempVal && $tempVal > 50)) {
                $fireStatus = 'CRITICAL';
            } elseif (($gasVal && $gasVal > 700) || ($tempVal && $tempVal > 40)) {
                $fireStatus = 'WARNING';
            }

            $type_label = empty($sensor['sensor_type']) ? 'Default Sensor' : ucfirst($sensor['sensor_type']);

            $response_data['sensors'][] = [
                'sensor_ID' => $sensor['sensor_ID'],
                'type' => $type_label,
                'status' => strtoupper($sensor['status']),
                'latest_readings' => [
                    'gas' => $gasVal !== null ? $gasVal . ' ppm' : 'N/A',
                    'temp' => $tempVal !== null ? $tempVal . ' °C' : 'N/A',
                    'fire_status' => $fireStatus
                ]
            ];
        }

        return ['status' => 'success', 'data' => $response_data];

    } catch (\PDOException $e) {
        error_log("Device Details Error: " . $e->getMessage());
        http_response_code(500);
        return ['status' => 'error', 'message' => 'Database error.', 'detail' => $e->getMessage()];
    }
}

// $endpoint = $_GET['endpoint'] ?? $_GET['action'] ?? '';
// $response = ['status' => 'error', 'message' => 'Invalid Request'];

// switch ($endpoint) {
//     // ... [Your existing cases: login, logout, dashboard, users, etc.] ...



//     default:
//         http_response_code(404);
//         break;
// }


// --- 4. ROUTER & REQUEST HANDLING (Using 'action' parameter) ---
global $pdo;

$method = $_SERVER['REQUEST_METHOD'];
$input = get_json_input();  // Read JSON body first

// Check JSON body for POST requests
if ($method === 'POST' && isset($input['action'])) {
    $action = $input['action'];
} else {
    $action = $_GET['action'] ?? null;  // Fallback to GET
}

// Set user variables from session
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

// BFP Role check helper
$is_bfp_role = ($user_role === 'bfp_officer' || $user_role === 'bfp_assigned_at_desk');


// GLOBAL AUTH CHECK: Check session for all actions EXCEPT 'login', 'logout', 'report_alert', 'session', 'dashboard_stream', and push management
$open_actions = ['login', 'logout', 'report_alert','stations', 'session','record_reading', 'dashboard_stream', 'subscribe_push', 'list_push_subscriptions'];
if (!in_array($action, $open_actions) && !isset($_SESSION['user_id'])) {
    error_response("Session expired or user not logged in.", 401);
}
if (!in_array($action, $open_actions) && $user_role === 'guest') {
    error_response("Permission denied. User not authenticated.", 401);
}

// BFP-ONLY ACTION CHECK: Check BFP-specific endpoints
$bfp_actions = ['bfp_dashboard', 'incident_trends', 'incidents', 'device_details', 'resolve_alert', 'get_users', 'add_device'];
if (in_array($action, $bfp_actions) && !$is_bfp_role) {
    error_response("Permission denied. BFP role required.", 403);
}
// Admin-Only Check for listing push subscriptions
if ($action === 'list_push_subscriptions') {
    if (!isset($_SESSION['user_id']) || stripos($user_role, 'admin') === false) {
        error_response("Admin access required.", 403);
    }
}


if (!$action) {
    error_response("No action specified.", 400);
}
error_log("API Call: action=$action, method=$method, has_input=" . (!empty($input) ? 'yes' : 'no'));



switch ($action) {
    
    // ================================================================
    // AUTHENTICATION & SESSION ACTIONS
    // ================================================================
    case 'login':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $response = handleLogin($pdo, $input);
        if ($response['status'] === 'success') {
            respond(['message' => $response['message'], 'user' => $response['user']]);
        } else {
            error_response($response['message'], 401);
        }
        break;
        
    case 'logout':
        $response = handleLogout();
        respond(['message' => $response['message']]);
        break;

    case 'session':
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
             respond([
                'logged_in' => true, 
                'user' => [
                    'name' => $_SESSION['name'] ?? '',
                    'role' => $_SESSION['user_role'], 
                    'id' => $_SESSION['user_id']
                ]
             ]);
        } else {
            respond(['logged_in' => false]);
        }
        break;

    // ================================================================
    // BFP COMMAND CENTER ACTIONS
    // ================================================================
    case 'bfp_dashboard': // Comprehensive BFP dashboard data
        $data = fetchDashboardData($pdo);
        respond($data);
        break;
        
// REPLACE this section in api.php (around line 1200-1250)

case 'stations':
    // Accept GET requests (for fetching all stations)
    if ($method === 'GET') {
        try {
            $sql = "SELECT 
                        station_ID, 
                        station_name, 
                        latitude, 
                        longitude, 
                        contact_number 
                    FROM bfp_stations 
                    ORDER BY station_name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($stations)) {
                respond([]);
            }

            // Clean up contact numbers - remove slashes
            foreach ($stations as &$station) {
                $station['contact_number'] = preg_replace('/\//', '', $station['contact_number']);
            }

            // Return as simple array
            respond($stations);
            
        } catch (\PDOException $e) {
            error_log("Stations fetch error: " . $e->getMessage());
            respond([]); // Return empty array on error so UI can show fallback
        }
    }
    // Handle POST, PUT, DELETE for admin operations
    else if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
        $response = handleStationsCrud($pdo, $method, $input);
        if ($response['status'] === 'success') {
            respond($response['data'] ?? ['message' => $response['message'], 'id' => $response['station_ID'] ?? null]);
        }
        error_response($response['message'], 500);
    }
    else {
        error_response("Method not allowed.", 405);
    }
    break;
    case 'incident_trends':
        $period = $_GET['period'] ?? 'monthly';
        $owner = $_GET['owner'] ?? null;
        $response = getIncidentTrends($pdo, $period, $owner);
        if ($response['status'] === 'success') respond($response);
        error_response($response['message'], 500);
        break;
        
    case 'incidents':
        $response = handleIncidentsCrud($pdo, $method, $input);
        if ($response['status'] === 'success') respond($response['data'] ?? ['message' => $response['message'], 'id' => $response['incident_ID'] ?? null]);
        error_response($response['message'], 500);
        break;
        
    case 'device_details': // BFP access to a resident's device details
        $userId = $_GET['user_id'] ?? null;
        $response = fetchDeviceDetails($pdo, $userId);
        if ($response['status'] === 'success') respond($response['data']);
        error_response($response['message'], 404);
        break;
        
    // case 'resolve_alert': // BFP resolving an incident
    //     if ($method === 'PUT' && isset($input['incident_ID'])) {
    //         $stmt = $pdo->prepare("
    //             UPDATE incident_log 
    //             SET status='RESOLVED', end_timestamp=NOW() 
    //             WHERE incident_ID=? AND status IN ('PENDING', 'DISPATCHED')
    //         ");
    //         $stmt->execute([$input['incident_ID']]);
    //         respond(['message' => 'Incident resolved.']);
    //     }
    //     error_response('Invalid request.', 400);
    //     break;
        
    case 'get_users': // BFP fetching resident users list (used for provisioning)
        // Using handleUserCrud to list all users, filtering on frontend if needed, but returning just residents here:
        try {
            $sql = "SELECT user_ID, first_name, last_name, email FROM users WHERE role = 'resident' ORDER BY last_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\PDOException $e) {
             error_response("Database error fetching users.", 500);
        }
        break;
        
    case 'add_device': // BFP adding a device to a resident's location
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();

        if (empty($input['location_id']) || empty($input['esp_ip'])) {
            error_response("Missing required fields: Location ID and ESP IP are required.", 400);
        }

        $location_id = $input['location_id'];
        $esp_ip = $input['esp_ip']; 
        $type = $input['type'] ?? 'multisensor';
        
        try {
            $check_esp_sql = "SELECT sensor_ID FROM sensor WHERE esp_ip_unique = ?";
            $stmt_check_esp = $pdo->prepare($check_esp_sql);
            $stmt_check_esp->execute([$esp_ip]);
            if ($stmt_check_esp->fetch()) {
                 error_response("This ESP IP/ID is already actively assigned.", 409);
            }

            $sql_insert = "INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status, installed_at) 
                           VALUES (?, ?, ?, 'active', NOW())";
            
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$location_id, $esp_ip, $type]);
            
            respond(['sensor_id' => $pdo->lastInsertId(), 'message' => 'Device/Sensor added successfully']);
        } catch (\PDOException $e) {
            error_log("Add device error: " . $e->getMessage());
            error_response("Error adding device: " . $e->getMessage(), 500);
        }
        break;
        
    // ================================================================
    // RESIDENT APP ACTIONS
    // ================================================================
    case 'get_dashboard_data': // Resident dashboard (device/alerts count and recent incidents)
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        try {
            // Fetch active sensor count
            $sql_devices = "SELECT COUNT(s.sensor_ID) as count FROM sensor s 
                            JOIN location l ON s.FK_location_ID = l.location_ID
                            WHERE l.FK_user_ID = ? AND s.status = 'active'";
            $stmt_devices = $pdo->prepare($sql_devices);
            $stmt_devices->execute([$user_id]);
            $active_devices_count = $stmt_devices->fetchColumn() ?? 0;

            // Fetch active alerts count
            $sql_alerts = "SELECT COUNT(i.incident_ID) as count FROM incident_log i 
                           LEFT JOIN sensor s ON i.FK_sensor_ID = s.sensor_ID
                           LEFT JOIN location l ON s.FK_location_ID = l.location_ID
                           WHERE l.FK_user_ID = ? AND i.status IN ('pending', 'dispatched')";
            $stmt_alerts = $pdo->prepare($sql_alerts);
            $stmt_alerts->execute([$user_id]);
            $active_alerts_count = $stmt_alerts->fetchColumn() ?? 0;

            // Fetch incidents 
            $sql_incidents = "
                SELECT 
                    i.incident_ID, i.start_timestamp AS time, i.status, 
                    i.incident_level AS severity, s.sensor_type AS device_name,
                    l.location_name AS location, l.latitude AS lat, l.longitude AS lon
                FROM incident_log i 
                LEFT JOIN sensor s ON i.FK_sensor_ID = s.sensor_ID
                LEFT JOIN location l ON s.FK_location_ID = l.location_ID
                WHERE l.FK_user_ID = ? 
                ORDER BY i.start_timestamp DESC 
                LIMIT 10";
            
            $stmt_incidents = $pdo->prepare($sql_incidents);
            $stmt_incidents->execute([$user_id]);
            $result_incidents = $stmt_incidents->fetchAll(PDO::FETCH_ASSOC);
            
            $incidents = [];
            foreach($result_incidents as $row) {
                $display_status = (strtolower($row['status']) === 'pending' || strtolower($row['status']) === 'dispatched') ? 'ACTIVE' : 'RESOLVED';
                $incident_type = mapIncidentTypePHP($row['device_name'] ?? '', $row['severity'] ?? '');

                $incidents[] = [
                    'id' => $row['incident_ID'], 
                    'type' => ucfirst($incident_type), 
                    'device' => $row['location'] ?? 'Unknown Location', 
                    'time' => (new DateTime($row['time']))->format('M d, Y h:i A'),
                    'severity' => ucfirst($row['severity'] ?? 'medium'), 
                    'status' => $display_status, 
                    'lat' => (float)($row['lat'] ?? 0), 
                    'lon' => (float)($row['lon'] ?? 0)
                ];
            }

            respond([
                'active_devices_count' => (int)$active_devices_count, 
                'active_alerts_count' => (int)$active_alerts_count, 
                'incidents' => $incidents
            ]);
        } catch (\PDOException $e) {
            error_log("Dashboard fetch error: " . $e->getMessage());
            error_response("Database error during dashboard fetch. " . $e->getMessage(), 500);
        }
        break;
    case 'dashboard':
            $incidents = fetchIncidents($pdo);
            $activeIncidents = array_filter($incidents, fn($i) => in_array($i['status'], ['pending', 'dispatched']));
            $resolvedIncidents = array_filter($incidents, fn($i) => $i['status'] === 'resolved');
            $mapData = fetchMapData($pdo);
            
            echo json_encode([
                'status' => 'success',
                'metrics' => fetchMetrics($pdo),
                'users' => fetchUsers($pdo),
                'activeIncidents' => array_values($activeIncidents),
                'resolvedIncidents' => array_values($resolvedIncidents),
                'stations' => $mapData['stations'],
                'activeDevices' => $mapData['activeDevices'],
            ]);
            break;

    case 'resolve_alert':
            $data = json_decode(file_get_contents('php://input'), true);
            $incidentId = $data['incidentId'] ?? null;
            if (!$incidentId) throw new Exception("Incident ID is required.");
            
            $stmt = $pdo->prepare("UPDATE incident_log SET status = 'resolved', end_timestamp = NOW() WHERE incident_ID = ? AND status != 'resolved'");
            $stmt->execute([$incidentId]);
            
            if ($stmt->rowCount() > 0) {
                 echo json_encode(['status' => 'success', 'message' => "Incident {$incidentId} resolved."]);
            } else {
                 echo json_encode(['status' => 'error', 'message' => "Incident {$incidentId} not found or already resolved."]);
            }
            break;

    case 'crud_user':
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_ID'] ?? null;
            $action_type = $data['action_type'] ?? 'save'; 

            if ($action_type === 'delete') {
                 $stmt = $pdo->prepare("DELETE FROM users WHERE user_ID = ?");
                 $stmt->execute([$userId]);
                 echo json_encode(['status' => 'success', 'message' => "User deleted."]);
                 exit;
            }

            // Simplified: Only Resident role can be created/edited in this endpoint
            $role = 'resident'; 
            
            $fields = [
                'first_name' => $data['first_name'], 
                'last_name' => $data['last_name'], 
                'email' => $data['email'], 
                'phone_number' => $data['phone_number'],
            ];

            if ($userId) {
                // UPDATE user
                $set_clause = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
                $stmt = $pdo->prepare("UPDATE users SET {$set_clause} WHERE user_ID = :user_ID");
                $stmt->execute(array_merge($fields, ['user_ID' => $userId]));
            } else {
                // INSERT user
                $keys = implode(', ', array_keys($fields));
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
                $stmt = $pdo->prepare("INSERT INTO users ({$keys}, role) VALUES ({$placeholders}, :role)");
                $stmt->execute(array_merge($fields, ['role' => $role]));
                $userId = $pdo->lastInsertId();
            }
            
            // Location is handled separately - Simplified: Assume one primary location per user for resident
            if ($data['address'] && $userId) {
                $location_check = $pdo->prepare("SELECT location_ID FROM location WHERE FK_user_ID = ? ORDER BY date_created DESC LIMIT 1");
                $location_check->execute([$userId]);
                $location = $location_check->fetch();
                
                if ($location) {
                    $pdo->prepare("UPDATE location SET address = ?, location_name = ? WHERE location_ID = ?")->execute([$data['address'], $data['first_name'] . "'s Home", $location['location_ID']]);
                } else {
                    $pdo->prepare("INSERT INTO location (FK_user_ID, location_name, address) VALUES (?, ?, ?)")->execute([$userId, $data['first_name'] . "'s Home", $data['address']]);
                }
            }
            
            echo json_encode(['status' => 'success', 'message' => "User saved successfully.", 'user_ID' => $userId]);

            break;

    case 'dismiss_alert':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();
        $incident_id = $input['incident_id'] ?? null;

        if (empty($incident_id) || !is_numeric($incident_id)) {
            error_response("Missing or invalid Incident ID.", 400);
        }

        try {
            // Security check: Ensure the incident belongs to the user's location
            $sql_check = "SELECT i.incident_ID 
                          FROM incident_log i
                          JOIN sensor s ON i.FK_sensor_ID = s.sensor_ID
                          JOIN location l ON s.FK_location_ID = l.location_ID
                          WHERE i.incident_ID = ? AND l.FK_user_ID = ? AND i.status != 'resolved'";
            
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$incident_id, $user_id]);
            
            if (!$stmt_check->fetch()) {
                error_response("Incident not found, already resolved, or not authorized.", 403);
            }

            $sql_update = "UPDATE incident_log SET status = 'resolved', end_timestamp = NOW() WHERE incident_ID = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$incident_id]);

            respond(['message' => "Incident ID $incident_id marked as resolved."]);
            
        } catch (\PDOException $e) {
            error_log("Dismiss alert error: " . $e->getMessage());
            error_response("Database error resolving incident.", 500);
        }
        break;
case 'record_reading':
    // ESP32 sensor data upload endpoint
    // Use UPSERT to prevent duplicates while keeping multiple reading types
    
    $input = get_json_input();
    $sensor_id = $input['sensor_id'] ?? $_GET['sensor_id'] ?? null;
    $reading = $input['reading'] ?? $_GET['reading'] ?? null;

    if (!$sensor_id || !$reading) {
        error_response("Missing sensor_id or reading", 400);
    }

    try {
        // --- 1. PARSE THE READING ---
        $reading_upper = strtoupper(trim($reading));
        $reading_type = 'UNKNOWN';
        $numeric_value = null;
        
        if (preg_match('/(\d+\.?\d*)/', $reading, $matches)) {
            $numeric_value = $matches[1];
        }
        
        if (strpos($reading_upper, 'SMOKE') !== false) $reading_type = 'SMOKE';
        elseif (strpos($reading_upper, 'FIRE') !== false) $reading_type = 'FIRE';
        elseif (strpos($reading_upper, 'TEMP') !== false || strpos($reading_upper, 'TEMPERATURE') !== false) $reading_type = 'TEMPERATURE';
        
        // --- 2. SMART UPSERT (Update by type, don't duplicate) ---
        // Extract just the TYPE prefix (e.g., "SMOKE" from "SMOKE 4095")
        
        // Check if a row for this sensor + reading type already exists
        $checkStmt = $pdo->prepare("
            SELECT reading_ID FROM sensor_reading 
            WHERE FK_sensor_ID = ? AND reading_value LIKE ? 
            LIMIT 1
        ");
        $checkStmt->execute([$sensor_id, $reading_type . '%']);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // ✅ UPDATE: Only update if this reading TYPE already exists
            // (SMOKE update → updates last SMOKE, keeps FIRE & TEMP rows)
            $stmt = $pdo->prepare("
                UPDATE sensor_reading 
                SET reading_value = ?, timestamp = NOW() 
                WHERE FK_sensor_ID = ? AND reading_value LIKE ? 
                LIMIT 1
            ");
            $result = $stmt->execute([$reading, $sensor_id, $reading_type . '%']);
            $action_taken = "Updated";
        } else {
            // ✅ INSERT: New reading type for this sensor
            // (Allows SMOKE, FIRE, TEMP all to exist)
            $stmt = $pdo->prepare("
                INSERT INTO sensor_reading (FK_sensor_ID, reading_value, timestamp) 
                VALUES (?, ?, NOW())
            ");
            $result = $stmt->execute([$sensor_id, $reading]);
            $action_taken = "Inserted";
        }

        if ($result) {
            respond([
                'success' => true,
                'message' => "Sensor reading {$action_taken} successfully",
                'sensor_id' => $sensor_id,
                'reading_type' => $reading_type,
                'reading_value' => $reading,
                'numeric_value' => $numeric_value,
                'action' => $action_taken
            ]);
        } else {
            error_response("Failed to save sensor reading", 500);
        }

    } catch (\PDOException $e) {
        error_log("Database error in record_reading: " . $e->getMessage());
        error_response("Database error: " . $e->getMessage(), 500);
    }
    break;
        
    case 'test_sms':
        // Manual test to see if Gateway works
        if (isset($_GET['phone'])) {
            $res = sendGatewaySMS($_GET['phone'], "Test from BFP PHP System");
            $response = ['status' => 'success', 'gateway_response' => $res];
        }
        break;

    case 'report_alert':
        // ENDPOINT: Called by the ESP32 (Unrestricted by user session)
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();
        
        $esp_id = $input['esp_id'] ?? null;
        $alert_type = $input['alert_type'] ?? null; 
        
        $gas_raw = $input['gas_raw'] ?? 0;
        $temp_c = $input['temp_c'] ?? 0.0;
        
        if (empty($esp_id) || empty($alert_type)) {
            error_response("Missing required data (ESP ID or alert_type).", 400);
        }

        try {
            $sql_find = "SELECT s.sensor_ID, l.FK_user_ID, l.location_ID, s.sensor_type FROM sensor s
                         JOIN location l ON s.FK_location_ID = l.location_ID
                         WHERE s.esp_ip_unique = ?";
            $stmt_find = $pdo->prepare($sql_find);
            $stmt_find->execute([$esp_id]);
            $sensor_info = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if (!$sensor_info) {
                error_response("Sensor not registered.", 404);
            }
            
            $sensor_id = $sensor_info['sensor_ID'];
            $sensor_type = $sensor_info['sensor_type'];
            
            // 2. Insert new Readings
            $sql_readings = "INSERT INTO sensor_reading (FK_sensor_ID, reading_value, timestamp) VALUES (?, ?, NOW())";
            
            // Insert Temperature reading
            $stmt_temp = $pdo->prepare($sql_readings);
            $stmt_temp->execute([$sensor_id, 'TEMP ' . $temp_c]);

            // Insert Gas/Smoke reading
            if (is_numeric($gas_raw) && $gas_raw > 0) {
                 $stmt_gas = $pdo->prepare($sql_readings);
                 $stmt_gas->execute([$sensor_id, 'GAS ' . $gas_raw]); 
            }
            
            // 3. Update Incident/Status 
            if ($alert_type === 'CRITICAL') {
                $sql_check_incident = "SELECT incident_ID FROM incident_log WHERE FK_sensor_ID = ? AND status = 'pending'";
                $stmt_check_incident = $pdo->prepare($sql_check_incident);
                $stmt_check_incident->execute([$sensor_id]);
                
                if (!$stmt_check_incident->fetch()) {
                    $incident_type_mapped = mapIncidentTypePHP($sensor_type, 'CRITICAL');
                    $sql_insert_incident = "INSERT INTO incident_log (FK_sensor_ID, start_timestamp, status, incident_level, incident_type) 
                                            VALUES (?, NOW(), 'pending', 'critical', ?)";
                    $stmt_insert_incident = $pdo->prepare($sql_insert_incident);
                    $stmt_insert_incident->execute([$sensor_id, $incident_type_mapped]);
                }
                
                $sql_update_sensor = "UPDATE sensor SET status = 'alert', last_check = NOW() WHERE sensor_ID = ?";
                $stmt_update_sensor = $pdo->prepare($sql_update_sensor);
                $stmt_update_sensor->execute([$sensor_id]);

            } else if ($alert_type === 'SAFE') {
                $sql_resolve_incident = "UPDATE incident_log SET status = 'resolved', end_timestamp = NOW() WHERE FK_sensor_ID = ? AND status IN ('pending', 'dispatched')";
                $stmt_resolve_incident = $pdo->prepare($sql_resolve_incident);
                $stmt_resolve_incident->execute([$sensor_id]);
                
                $sql_update_sensor = "UPDATE sensor SET status = 'active', last_check = NOW() WHERE sensor_ID = ?";
                $stmt_update_sensor = $pdo->prepare($sql_update_sensor);
                $stmt_update_sensor->execute([$sensor_id]);
            }
            
            respond(['message' => "Alert processed successfully for ESP ID: " . $esp_id, 'status' => $alert_type]);
            
        } catch (\PDOException $e) {
            error_log("Alert report error: " . $e->getMessage());
            error_response("Database error during alert processing: " . $e->getMessage(), 500);
        }
        break;


    case 'get_profile_and_location':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        try {
            $sql_user = "SELECT user_ID, first_name, last_name, email, phone_number, role FROM users WHERE user_ID = ?";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$user_id]);
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_response("User not found.", 404);
            }

            $sql_loc = "SELECT * FROM location WHERE FK_user_ID = ? ORDER BY location_ID ASC LIMIT 1";
            $stmt_loc = $pdo->prepare($sql_loc);
            $stmt_loc->execute([$user_id]);
            $primary_location = $stmt_loc->fetch(PDO::FETCH_ASSOC) ?? ['location_ID' => null, 'address' => 'No address set'];

            respond(['user' => $user, 'primary_location' => $primary_location]);
        } catch (\PDOException $e) {
            error_log("Profile fetch error: " . $e->getMessage());
            error_response("Database error during profile fetch.", 500);
        }
        break;

    case 'update_profile':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();

        if (empty($input['full_name']) || empty($input['phone']) || empty($input['address'])) {
            error_response("Missing required profile fields (Name, Phone, Address).", 400);
        }
        
        $names = explode(" ", $input['full_name'], 2);
        $first_name = $names[0];
        $last_name = isset($names[1]) ? $names[1] : '';
        
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;
        $permission_level = $input['permission_level'] ?? 'private';
        
        try {
            $sql_user = "UPDATE users SET first_name = ?, last_name = ?, phone_number = ? WHERE user_ID = ?";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$first_name, $last_name, $input['phone'], $user_id]);
            
            // Assumes the user has one primary location (LIMIT 1)
            $sql_loc = "UPDATE location SET address = ?, permission_level = ?, latitude = ?, longitude = ? WHERE FK_user_ID = ? ORDER BY location_ID ASC LIMIT 1";
            $stmt_loc = $pdo->prepare($sql_loc);
            $stmt_loc->execute([$input['address'], $permission_level, $latitude, $longitude, $user_id]);
            
            respond(['message' => 'Profile and location updated successfully.']);
        } catch (\PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            error_response("Database error during profile update.", 500);
        }
        break;
        
    case 'get_my_locations':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        try {
            $sql = "SELECT location_ID, location_name, address FROM location WHERE FK_user_ID = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            respond($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\PDOException $e) {
            error_response("Error fetching locations for device add.", 500);
        }
        break;

    case 'get_devices':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        try {
            $sql = "SELECT 
                        s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, s.installed_at,
                        l.location_name, l.address
                    FROM sensor s
                    JOIN location l ON s.FK_location_ID = l.location_ID
                    WHERE l.FK_user_ID = ?
                    ORDER BY s.sensor_ID DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            respond($devices);
        } catch (\PDOException $e) {
            error_log("Device fetch error: " . $e->getMessage());
            error_response("Database error during device fetch.", 500);
        }
        break;
        
    // case 'get_latest_readings':
    //     if ($method !== 'GET') error_response("Method not allowed.", 405);
        
    //     $sensor_id = $_GET['sensor_id'] ?? null;
    //     if (empty($sensor_id) || !is_numeric($sensor_id)) {
    //         error_response("Missing or invalid Sensor ID.", 400);
    //     }
        
    //     try {
    //         $sql_check = "SELECT s.sensor_ID FROM sensor s 
    //                       JOIN location l ON s.FK_location_ID = l.location_ID
    //                       WHERE s.sensor_ID = ? AND l.FK_user_ID = ?";
    //         $stmt_check = $pdo->prepare($sql_check);
    //         $stmt_check->execute([$sensor_id, $user_id]);
            
    //         if (!$stmt_check->fetch()) {
    //             error_response("Sensor not found or not authorized for this user.", 403);
    //         }

    //         $sql_readings = "
    //             SELECT reading_value, timestamp 
    //             FROM sensor_reading 
    //             WHERE FK_sensor_ID = ?
    //             ORDER BY timestamp DESC
    //             LIMIT 10
    //         ";
            
    //         $stmt_readings = $pdo->prepare($sql_readings);
    //         $stmt_readings->execute([$sensor_id]);
    //         $readings = $stmt_readings->fetchAll(PDO::FETCH_ASSOC);

    //         $result = [
    //             'gas_raw' => 'N/A',
    //             'temp_c' => 'N/A',
    //             'timestamp' => 'N/A',
    //             'fire_status' => 'SAFE'
    //         ];

    //         foreach ($readings as $reading) {
    //             $numeric_value = null;
    //             $value_upper = strtoupper($reading['reading_value']); 

    //             if (preg_match('/[a-zA-Z]*\s*(\d+(\.\d+)?)/', $reading['reading_value'], $matches)) {
    //                 $numeric_value = $matches[1];
    //             } else if (is_numeric($reading['reading_value'])) {
    //                  $numeric_value = $reading['reading_value'];
    //             }
                
    //             if ($numeric_value !== null) {
    //                 if ((strpos($value_upper, 'CELSIUS') !== false || strpos($value_upper, 'DEG') !== false || strpos($value_upper, 'TEMP') !== false) && $result['temp_c'] === 'N/A') {
    //                     $result['temp_c'] = (float)$numeric_value;
    //                     if ($result['timestamp'] === 'N/A') { $result['timestamp'] = $reading['timestamp']; }
    //                 } 
    //                 elseif ((strpos($value_upper, 'PPM') !== false || strpos($value_upper, 'GAS') !== false || strpos($value_upper, 'SMOKE') !== false || strpos($value_upper, 'FIRE') !== false || (is_numeric($numeric_value) && $result['gas_raw'] === 'N/A')) && $result['gas_raw'] === 'N/A') {
    //                     $result['gas_raw'] = (int)$numeric_value;
    //                     if ($result['timestamp'] === 'N/A') { $result['timestamp'] = $reading['timestamp']; }
    //                 }
    //             }
    //         }

    //         $is_gas_valid = is_numeric($result['gas_raw']) && $result['gas_raw'] !== 'N/A';
            
    //         if ($is_gas_valid) {
    //             if ($result['gas_raw'] > 500) {
    //                 $result['fire_status'] = 'CRITICAL';
    //             } elseif ($result['gas_raw'] > 300) {
    //                 $result['fire_status'] = 'WARNING';
    //             } else {
    //                 $result['fire_status'] = 'SAFE';
    //             }
    //         }
            
    //         respond($result);

    //     } catch (\PDOException $e) {
    //         error_log("Reading fetch error: " . $e->getMessage());
    //         error_response("Database error during reading fetch.", 500);
    //     }
    //     break;
case 'get_latest_readings':
        $sensor_id = $_GET['sensor_id'] ?? null;

        if (!$sensor_id) {
            echo json_encode(['success' => false, 'message' => 'Missing Sensor ID']);
            exit;
        }

        try {
            // Helper function to extract number from string (e.g., "SMOKE 500" -> 500)
            function extractValue($string) {
                if (preg_match('/(\d+\.?\d*)/', $string, $matches)) {
                    return floatval($matches[1]);
                }
                return null;
            }

            // 1. Get Latest SMOKE reading
            $stmtSmoke = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? AND reading_value LIKE 'SMOKE%' 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtSmoke->execute([$sensor_id]);
            $smokeRow = $stmtSmoke->fetch(PDO::FETCH_ASSOC);
            $gas_raw = $smokeRow ? extractValue($smokeRow['reading_value']) : 0;

            // 2. Get Latest TEMPERATURE reading
            $stmtTemp = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? AND (reading_value LIKE 'TEMP%' OR reading_value LIKE 'CELSIUS%') 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtTemp->execute([$sensor_id]);
            $tempRow = $stmtTemp->fetch(PDO::FETCH_ASSOC);
            $temp_c = $tempRow ? extractValue($tempRow['reading_value']) : 0;

            // 3. Get Latest FIRE reading
            $stmtFire = $pdo->prepare("
                SELECT reading_value FROM sensor_reading 
                WHERE FK_sensor_ID = ? AND reading_value LIKE 'FIRE%' 
                ORDER BY timestamp DESC LIMIT 1
            ");
            $stmtFire->execute([$sensor_id]);
            $fireRow = $stmtFire->fetch(PDO::FETCH_ASSOC);
            $fire_val = $fireRow ? extractValue($fireRow['reading_value']) : 4095; // Default to safe (High val = Safe)

            // 4. Determine Status
            // Fire Sensor: LOW value usually means FIRE DETECTED (e.g., < 500)
            // Gas Sensor: HIGH value means DANGER
            $fire_status = 'SAFE';

            if ($fire_val < 500) { // Strong fire signal
                $fire_status = 'CRITICAL';
            } elseif ($gas_raw > 1200 || $temp_c > 50) {
                $fire_status = 'CRITICAL';
            } elseif ($gas_raw > 700 || $temp_c > 40) {
                $fire_status = 'WARNING';
            }

            // 5. Send Response
            $response = [
                'success' => true,
                'data' => [
                    'gas_raw' => $gas_raw,
                    'temp_c' => $temp_c,
                    'fire_val' => $fire_val,
                    'fire_status' => $fire_status
                ]
            ];

            echo json_encode($response);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

    case 'get_available_esps':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        $available_esps = [
            '0A:1B:C2:D3:E4:F5', '1A:2B:C3:D4:E5:F6', '2C:3D:4E:5F:6A:7B',
            '3D:4E:5F:6A:7B:8C', '4E:5F:6A:7B:8C:9D', 
        ];
        
        try {
            // Find assigned ESPs
            $placeholders = implode(',', array_fill(0, count($available_esps), '?'));
            $sql_assigned = "SELECT esp_ip_unique FROM sensor WHERE esp_ip_unique IN ($placeholders)";
            $stmt_assigned = $pdo->prepare($sql_assigned);
            $stmt_assigned->execute($available_esps);
            $assigned_esps = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN, 0);

            $unassigned_esps = array_diff($available_esps, $assigned_esps);
            
            respond(array_values($unassigned_esps));

        } catch (\PDOException $e) {
            error_log("Get available ESPs error: " . $e->getMessage());
            error_response("Database error fetching available devices.", 500);
        }
        break;
        
    case 'get_location':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        try {
            $sql_primary = "SELECT * FROM location l WHERE l.FK_user_ID = ? ORDER BY location_ID ASC LIMIT 1";
            $stmt_primary = $pdo->prepare($sql_primary);
            $stmt_primary->execute([$user_id]);
            $primary_location = $stmt_primary->fetch(PDO::FETCH_ASSOC);

            $sql_locations = "SELECT location_name, latitude as lat, longitude as lon FROM location l WHERE FK_user_ID = ?";
            $stmt_locations = $pdo->prepare($sql_locations);
            $stmt_locations->execute([$user_id]);
            $device_locations = $stmt_locations->fetchAll(PDO::FETCH_ASSOC);

            respond(['primary_info' => $primary_location, 'device_locations' => $device_locations]);
        } catch (\PDOException $e) {
            error_log("Location fetch error: " . $e->getMessage());
            error_response("Database error during location fetch.", 500);
        }
        break;
        
    case 'call_api':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();
        
        $target_number = $input['target_number'] ?? 'N/A';
        
        error_log("EMERGENCY CALL: User $user_id is initiating call to $target_number for location: " . ($input['location'] ?? 'N/A'));
        
        respond([
            'status' => 'Call initiated successfully.', 
            'target' => $target_number, 
            'device' => $input['location'] ?? 'N/A'
        ]);
        break;

    case 'resolve_alert':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();
        $incident_id = $input['incidentId'] ?? null;
        
        if (!$incident_id) error_response("Missing incident ID.", 400);
        
        try {
            $stmt = $pdo->prepare("UPDATE incident_log SET status = 'resolved', end_timestamp = NOW() WHERE incident_ID = ?");
            $stmt->execute([$incident_id]);
            
            error_log("Incident $incident_id resolved by user $user_id");
            respond(['message' => 'Alert resolved successfully.']);
        } catch (\PDOException $e) {
            error_log("Resolve alert error: " . $e->getMessage());
            error_response("Database error resolving alert.", 500);
        }
        break;

    case 'call_sms':
        if ($method !== 'POST') error_response("Method not allowed.", 405);
        $input = get_json_input();
        
        $phone_number = $input['phone_number'] ?? null;
        $message = $input['message'] ?? null;
        $action_type = $input['action_type'] ?? 'sms'; // 'sms' or 'call'
        
        if (!$phone_number) error_response("Missing phone number.", 400);
        
        try {
            if ($action_type === 'call') {
                // Send call to Arduino gateway
                $call_response = sendGatewayCall($phone_number);
                error_log("Call sent to $phone_number. Response: $call_response");
                respond(['status' => 'success', 'message' => 'Call initiated to ' . $phone_number]);
            } else {
                // Send SMS to Arduino gateway
                $sms_message = $message ?: 'Fire Alert! Emergency response dispatch initiated.';
                $sms_response = sendGatewaySMS($phone_number, $sms_message);
                error_log("SMS sent to $phone_number. Response: $sms_response");
                respond(['status' => 'success', 'message' => 'SMS sent to ' . $phone_number]);
            }
        } catch (Exception $e) {
            error_log("Call/SMS error: " . $e->getMessage());
            error_response("Failed to send call/SMS. Check gateway connection.", 500);
        }
        break;

    case 'get_nearest_station':
        if ($method !== 'GET') error_response("Method not allowed.", 405);
        
        $latitude = $_GET['latitude'] ?? null;
        $longitude = $_GET['longitude'] ?? null;
        
        if (empty($latitude) || empty($longitude)) {
            error_response("Missing latitude or longitude parameters.", 400);
        }
        
        try {
            $sql = "SELECT station_ID, station_name, latitude, longitude, contact_number FROM bfp_stations";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($stations)) {
                error_response("No BFP stations found in database.", 404);
            }
            
            $nearest_station = null;
            $min_distance = PHP_FLOAT_MAX;
            
            foreach ($stations as $station) {
                $distance = calculateDistance(
                    $latitude, 
                    $longitude, 
                    $station['latitude'], 
                    $station['longitude']
                );
                
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $nearest_station = $station;
                    $nearest_station['distance_km'] = round($distance, 2);
                }
            }
            
            respond($nearest_station);
            
        } catch (\PDOException $e) {
            error_log("Get nearest station error: " . $e->getMessage());
            error_response("Database error fetching nearest station.", 500);
        }
        break;
        
    // ================================================================
    // PUSH NOTIFICATION ACTIONS (Open access to store/Admin access to list)
    // ================================================================
    case 'subscribe_push':
        if ($method === 'POST' && isset($input['subscription'])) {
          $sub = $input['subscription'];
          if (isset($_SESSION['user_id'])) $sub['_user_id'] = $_SESSION['user_id'];
          $ok = addPushSubscription($sub);
          if ($ok) {
            respond(['message' => 'Subscription stored.']);
          } else {
            error_response('Invalid subscription.', 400);
          }
        } else {
          error_response('Method not allowed or missing subscription.', 405);
        }
        break;

    case 'list_push_subscriptions':
        $subs = listPushSubscriptions();
        respond(['subscriptions' => $subs]);
        break;
        case 'test_gateway_call':
    // Diagnostic endpoint to test gateway connection
    $test_phone = $_GET['phone'] ?? '09640954963';
    $test_number = $_GET['number'] ?? '+63917XXXXXXX';
    
    error_log("[TEST] Testing gateway call to: $test_number");
    
    // Test 1: Check if curl is available
    if (!function_exists('curl_init')) {
        respond(['error' => 'CURL not enabled on server']);
    }
    
    // Test 2: Try to connect
    $url = "http://" . CALL_HOST . "/api/v1/calls";
    $data = ["call" => ["phoneNumber" => $test_number]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, CALL_USER . ":" . CALL_PASS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    // Get detailed error info
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    rewind($verbose);
    $verbose_output = stream_get_contents($verbose);
    
    curl_close($ch);
    
    respond([
        'test' => 'gateway_call',
        'gateway_url' => $url,
        'phone_number' => $test_number,
        'http_code' => $http_code,
        'curl_error' => $curl_error ?: 'None',
        'gateway_response' => $response ?: 'No response',
        'verbose_log' => $verbose_output,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    break;

case 'dashboard_stream':
        // 1. Headers for Real-Time Stream
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Critical for Nginx
        
        // 2. Disable PHP buffering so data sends immediately
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @set_time_limit(0);
        
        // Clear any existing system buffers
        while (ob_get_level() > 0) ob_end_flush();

        $lastHash = null;
        
        // 3. GET USER ID & RELEASE LOCK (CRITICAL FIX)
        $currentUserId = $_SESSION['user_id'] ?? 0; 
        
        // 🚨 THIS IS THE FIX: Unlock the session so the rest of your site loads!
        session_write_close(); 
        
        while (!connection_aborted()) {
            try {
                // A. General Stats
                $stmt = $pdo->query("SELECT MAX(incident_ID) AS maxid, COUNT(*) AS total_incidents, SUM(CASE WHEN status IN ('PENDING','DISPATCHED') THEN 1 ELSE 0 END) AS active_alerts FROM incident_log");
                $s = $stmt->fetch();
                $maxid = $s['maxid'] ?? 0;
                $tot = (int)($s['total_incidents'] ?? 0);
                $active = (int)($s['active_alerts'] ?? 0);

                // B. Latest Incidents
                $stmt2 = $pdo->prepare("SELECT il.incident_ID, il.start_timestamp, il.status, il.incident_level, s.sensor_type, l.address FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID ORDER BY il.start_timestamp DESC LIMIT 6");
                $stmt2->execute();
                $latest = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                // C. Active Device Readings (For Real-time Monitoring)
                $sql_devices = "
                    SELECT 
                        s.sensor_ID, s.status,
                        (SELECT reading_value FROM sensor_reading WHERE FK_sensor_ID = s.sensor_ID AND (reading_value LIKE 'TEMP%' OR reading_value LIKE 'CELSIUS%') ORDER BY timestamp DESC LIMIT 1) as latest_temp,
                        (SELECT reading_value FROM sensor_reading WHERE FK_sensor_ID = s.sensor_ID AND (reading_value LIKE 'SMOKE%' OR reading_value LIKE 'GAS%') ORDER BY timestamp DESC LIMIT 1) as latest_gas,
                        (SELECT reading_value FROM sensor_reading WHERE FK_sensor_ID = s.sensor_ID AND reading_value LIKE 'FIRE%' ORDER BY timestamp DESC LIMIT 1) as latest_fire
                    FROM sensor s
                    JOIN location l ON s.FK_location_ID = l.location_ID
                    WHERE s.status = 'active' AND l.FK_user_ID = ?
                ";
                
                $stmtDev = $pdo->prepare($sql_devices);
                $stmtDev->execute([$currentUserId]);
                $myDevices = $stmtDev->fetchAll(PDO::FETCH_ASSOC);

                // D. Build Payload
                $payload = [
                    'maxid' => (int)$maxid, 
                    'total_incidents' => $tot, 
                    'active_alerts' => $active, 
                    'latest' => $latest, 
                    'devices' => $myDevices, 
                    'ts' => date('c')
                ];
                  
                // E. Send Data (Only if changed)
                $hash = md5(json_encode($payload));
                if ($hash !== $lastHash) {
                    echo "event: update\n";
                    echo "data: " . json_encode($payload) . "\n\n";
                    
                    // FORCE SEND: Add invisible padding to push data out of the buffer
                    echo str_pad('', 4096) . "\n";
                    
                    $lastHash = $hash;
                } else {
                    echo ": heartbeat\n\n";
                }
                
                flush(); // Send to browser immediately
                
            } catch (Exception $e) {
                // Keep stream alive
            }
usleep(500000);
        }
        exit;
        
    default:
    // Fetch everything for initial load
            $incidents = fetchIncidents($pdo);
            $activeIncidents = array_filter($incidents, fn($i) => in_array($i['status'], ['pending', 'dispatched']));
            $resolvedIncidents = array_filter($incidents, fn($i) => $i['status'] === 'resolved');
            $mapData = fetchMapData($pdo);
            
            echo json_encode([
                'status' => 'success',
                'metrics' => fetchMetrics($pdo),
                'users' => fetchUsers($pdo),
                'activeIncidents' => array_values($activeIncidents),
                'resolvedIncidents' => array_values($resolvedIncidents),
                'stations' => $mapData['stations'],
                'activeDevices' => $mapData['activeDevices'],
            ]);
              //  error_response("Unknown action: $action", 400);

            break;
     
}
// Ensure execution stops after the switch (although 'respond' and 'error_response' exit)
exit();
?>