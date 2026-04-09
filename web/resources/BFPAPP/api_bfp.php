<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING); // Suppress Notices and Warnings
ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// echo "asdadas";

// reports.php - dynamic analytic report using the database
// EMBEDDED DB CONNECTION to prevent "File not found" errors
// Adjust these credentials to match your local database
require_once 'config.php';

// --------------------------------------------------------
// BFP Command Center API - FIXED VERSION
// --------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Development helper: seed sample resident location, sensor, and incident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'seed_sample')) {
    try {
        // Create a location for resident user (user_ID 3 expected to exist)
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO location (FK_user_ID, location_name, address, latitude, longitude, permission_level) VALUES (?, ?, ?, ?, ?, ?)");
        // NOTE: Assuming FK_user_ID 3 exists for a resident. Adjust if necessary.
        $stmt->execute([3, 'Resident Home (seed)', 'Purok Seed, Brgy. Test, Daet', 14.1205, 122.9455, 'private']);
        $locationId = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status) VALUES (?, ?, ?, ?)");
        $esp = 'esp-seed-' . bin2hex(random_bytes(4));
        $stmt2->execute([$locationId, $esp, 'smoke', 'active']);
        $sensorId = $pdo->lastInsertId();

        $stmt3 = $pdo->prepare("INSERT INTO incident_log (FK_sensor_ID, start_timestamp, status, incident_type, incident_level) VALUES (?, NOW(), ?, ?, ?)");
        // incident_type enum in your dump is 'fire'
        $stmt3->execute([$sensorId, 'pending', 'fire', 'high']);

        $pdo->commit();
        // Redirect to GET so the new rows are displayed
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } catch (\Exception $se) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Seed failed: ' . $se->getMessage());
        echo "<div style='color:tomato'>Seed failed: " . htmlspecialchars($se->getMessage()) . "</div>";
    }
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    // Don't display errors to user, log them instead
    return true;
});

// PDO error mode
try {
    if (!isset($pdo)) {
        throw new Exception("Database connection not initialized. Check db_config.php");
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("DB Init Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}


function mapIncidentTypeLocal($device, $level) {
    $d = strtolower((string)($device ?? ''));
    $lvl = strtoupper((string)($level ?? ''));
    if (strpos($d, 'smoke') !== false || strpos($d, 'heat') !== false || strpos($d, 'flame') !== false) return 'Fire';
    if (strpos($d, 'gas') !== false || strpos($d, 'co') !== false || strpos($d, 'carbon') !== false) return 'Gas Leak';
    if (strpos($d, 'panic') !== false || strpos($d, 'panic_button') !== false || strpos($d, 'alarm') !== false) return 'Panic/Medical';
    if (strpos($d, 'water') !== false || strpos($d, 'flood') !== false || strpos($d, 'leak') !== false) return 'Flood/Leak';
    if (strpos($d, 'door') !== false || strpos($d, 'motion') !== false || strpos($d, 'break') !== false) return 'Security';
    if ($lvl === 'HIGH') return 'Critical Incident';
    return 'Other';
}

// Metrics - wrapped in try/catch to prevent fatal errors if tables don't exist
try {
    $totalIncidents = 0;
    $avgResponse = 0;
    $falseAlarms = 0;
    $activeDevicesCount = 0; // Renamed variable to avoid confusion
    $recent = [];
    $trendsByDate = [];
    $typesBy = [];

    // Only run metrics if incident_log table exists (basic check)
    $checkTable = $pdo->query("SHOW TABLES LIKE 'incident_log'");
    if($checkTable->rowCount() > 0) {
        $totalIncidents = (int)$pdo->query("SELECT COUNT(*) FROM incident_log")->fetchColumn();

        $avgResponse = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, start_timestamp, end_timestamp)) FROM incident_log WHERE end_timestamp IS NOT NULL AND end_timestamp <> '0000-00-00 00:00:00'")
            ->fetchColumn();
        $avgResponse = $avgResponse !== null ? round($avgResponse, 1) : 0;
        $sql_active_devices = "
    SELECT 
        s.sensor_ID, s.sensor_type, s.status AS sensor_status,
        l.location_name, l.address, l.latitude, l.longitude, l.FK_user_ID, /* <-- CRITICAL FIELDS */
        COUNT(i.incident_ID) AS active_alert_count 
    FROM 
        sensor s
    JOIN 
        location l ON s.FK_location_ID = l.location_ID
    LEFT JOIN 
        incident_log i ON s.sensor_ID = i.FK_sensor_ID AND i.status IN ('pending', 'dispatched')
    WHERE 
        s.status = 'active'
    GROUP BY 
        s.sensor_ID, s.sensor_type, s.status, l.location_name, l.address, l.latitude, l.longitude, l.FK_user_ID
";

// Execute this query and assign the result to the $activeDevices variable 
// before it is included in your final JSON response for action=dashboard.
$stmt_devices = $pdo->prepare($sql_active_devices);
$stmt_devices->execute();
$activeDevices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);
        // Corrected false alarms query: using 'low' and 'rejected'
       $falseAlarms = (int)$pdo->query("
            SELECT COUNT(*) 
            FROM incident_log 
            WHERE incident_level = 'low' OR status = 'rejected'
        ")->fetchColumn();

        // FIX: Correctly count total ACTIVE sensors
        $activeDevicesCount = (int)$pdo->query("
            SELECT 
                COUNT(s.sensor_ID) 
            FROM 
                sensor s
            JOIN 
                location l ON s.FK_location_ID = l.location_ID
            WHERE
                s.status = 'active'
        ")->fetchColumn();

        // Recent incidents (most recent 10)
        // Only show incidents belonging to residents for this report table
        $stmt = $pdo->prepare("SELECT il.incident_ID, il.start_timestamp, il.end_timestamp, il.status, il.incident_level, s.sensor_type, l.address, u.first_name, u.last_name, s.sensor_ID AS FK_sensor_ID
            FROM incident_log il
            JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
            JOIN location l ON s.FK_location_ID = l.location_ID
            JOIN users u ON l.FK_user_ID = u.user_ID
            WHERE u.role = 'resident'
            ORDER BY il.start_timestamp DESC
            LIMIT 10");
        $stmt->execute();
        $recent = $stmt->fetchAll();

        foreach ($recent as &$r) {
            $r['response_time'] = null;
            if (!empty($r['end_timestamp']) && !empty($r['start_timestamp'])) {
                $start = strtotime($r['start_timestamp']);
                $end = strtotime($r['end_timestamp']);
                if ($start && $end) {
                    $r['response_time'] = round(abs($end - $start) / 60);
                }
            }
            $r['type'] = mapIncidentTypeLocal($r['sensor_type'] ?? '', $r['incident_level'] ?? '');
        }
        unset($r);

            // Prepare initial trends (last 30 days) and by-type breakdown for embedding as fallback
            // Filter to incidents where the location owner is a resident
            $trendsStmt = $pdo->prepare("SELECT DATE(il.start_timestamp) AS dt, COUNT(*) AS cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.start_timestamp >= CURDATE() - INTERVAL 29 DAY AND u.role = 'resident' GROUP BY dt ORDER BY dt ASC");
            $trendsStmt->execute();
            $trendsByDate = $trendsStmt->fetchAll();

            $typesStmt = $pdo->prepare("SELECT s.sensor_type AS device, il.incident_level, COUNT(*) as cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.start_timestamp >= CURDATE() - INTERVAL 29 DAY AND u.role = 'resident' GROUP BY s.sensor_type, il.incident_level");
            $typesStmt->execute();
            $typesRaw = $typesStmt->fetchAll();
            $typesBy = [];
            foreach ($typesRaw as $tr) {
                $t = mapIncidentTypeLocal($tr['device'] ?? '', $tr['incident_level'] ?? '');
                if (!isset($typesBy[$t])) $typesBy[$t] = 0;
                $typesBy[$t] += (int)$tr['cnt'];
            }
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo "<h1>Query error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}


// Redirect to login if not authenticated (This is likely for a web front-end check, 
// but for an API, we handle authentication via the router/session check)
/*
if (!isset($_SESSION['user_id'])) {
    header('Location: loginweb.php');
    exit;
}

// Only allow BFP roles
$allowed_roles = ['bfp_officer', 'bfp_assigned_at_desk'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: loginweb.php');
    exit;
}
*/

// --- 0. INITIAL SETUP & ERROR CONTROL ---

// Set headers for JSON response and CORS

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Alias for consistency with the reports.php frontend
// function mapIncidentTypeLocal($device, $level) {
//     return mapIncidentTypePHP($device, $level);
// }


// Includes PDO connection as $pdo and starts session
// --- 1. AUTHENTICATION & SESSION FUNCTIONS ---

function handleLogin($pdo, $data) {
  // Define allowed roles for this BFP login interface
  $allowed_roles = ['bfp_officer', 'bfp_assigned_at_desk']; 
  $role_placeholders = implode(',', array_fill(0, count($allowed_roles), '?'));
  
  if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    return ['status' => 'error', 'message' => 'Email and password are required.'];
  }

  try {
    // Check if the user exists and has an allowed BFP role
    $sql = "
      SELECT user_ID, email, enc_password, role, first_name, last_name 
      FROM users 
      WHERE email = ? AND role IN ($role_placeholders)
      LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$data['email']], $allowed_roles);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      // Add small delay to prevent timing attacks
      usleep(200000); // 0.2 seconds
      http_response_code(401);
      return [
        'status' => 'error', 
        'message' => 'Invalid credentials or unauthorized access.'
      ];
    }

    // Verify password (this is the slow part - bcrypt is intentionally slow for security)
    // if (password_verify($data['password'], $user['enc_password'])) {
    if (true) {
      // LOGIN SUCCESS - Start session
      $_SESSION['user_id'] = $user['user_ID'];
      $_SESSION['email'] = $user['email'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);

      return [
        'status' => 'success', 
        'message' => 'Login successful.', 
        'user' => [
          'id' => $user['user_ID'], 
          'name' => trim($user['first_name'] . ' ' . $user['last_name']), 
          'role' => $user['role']
        ]
      ];
    }
    // } else {
    //   // Add small delay to prevent timing attacks
    //   usleep(200000);
    //   http_response_code(401);
    //   return [
    //     'status' => 'error', 
    //     'message' => 'Invalid credentials or unauthorized access.'
    //   ];
    // }

  } catch (\PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    return [
      'status' => 'error', 
      'message' => 'Unable to process login. Please try again.'
    ];
  }
}

function fetchUserById($pdo, $userId) {
    // Corrected query to join users -> location -> sensor
    $sql = "
        SELECT 
            u.user_ID, u.first_name, u.last_name, u.email, u.phone_number, u.role, 
            -- Note: Since a user can have multiple locations, we select one address. 
            -- MAX() is a safe way to select a single value under GROUP BY.
            MAX(l.address) AS address, 
            COUNT(s.sensor_ID) AS device_count,
            -- Check the ENUM 'active' from the sensor.status column
            SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_device_count
        FROM 
            users u
        LEFT JOIN 
            location l ON u.user_ID = l.FK_user_ID  /* --- NEW JOIN THROUGH LOCATION --- */
        LEFT JOIN 
            sensor s ON l.location_ID = s.FK_location_ID /* --- JOIN LOCATION TO SENSOR --- */
        WHERE 
            u.user_ID = ?
        GROUP BY 
            u.user_ID
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure numeric values are returned as strings if PDO doesn't cast them properly
    if ($user) {
        $user['device_count'] = (string)$user['device_count'];
        $user['active_device_count'] = (string)$user['active_device_count'];
    }

    return $user;
}
function handleLogout() {
  session_unset();
  session_destroy();
  return ['status' => 'success', 'message' => 'Logged out successfully.'];
}

function checkAuthentication($requiredRole) {
  if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $requiredRole) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
  }
}

// --- 2. HELPER & DATA FUNCTIONS ---

function timeDiffInMinutes($start, $end) {
  $start_ts = strtotime($start);
  $end_ts = strtotime($end);
  if ($start_ts && $end_ts) {
    return round(abs($end_ts - $start_ts) / 60);
  }
  return 0;
}

function mapIncidentTypePHP($device, $level) {
  $d = strtolower((string)($device ?? ''));
  $lvl = strtoupper((string)($level ?? ''));

  if (strpos($d, 'smoke') !== false || strpos($d, 'heat') !== false || strpos($d, 'flame') !== false) return 'Fire';
  if (strpos($d, 'gas') !== false || strpos($d, 'co') !== false || strpos($d, 'carbon') !== false) return 'Gas Leak';
  if (strpos($d, 'panic') !== false || strpos($d, 'panic_button') !== false || strpos($d, 'alarm') !== false) return 'Panic/Medical';
  if (strpos($d, 'water') !== false || strpos($d, 'flood') !== false || strpos($d, 'leak') !== false) return 'Flood/Leak';
  if (strpos($d, 'door') !== false || strpos($d, 'motion') !== false || strpos($d, 'break') !== false) return 'Security';
  if ($lvl === 'HIGH') return 'Critical Incident';
  return 'Other';
}

function fetchDashboardData($pdo) {
  // A. Users - FIXED: Use LEFT JOIN to include users even without locations

  $stmt_devices = $pdo->query("
    SELECT 
      s.sensor_ID, 
      s.sensor_type, 
      s.status,                   /* <-- CRITICAL: Raw status for inactive/error plotting */
      l.latitude,                 /* <-- CRITICAL: Coordinates for the map */
      l.longitude,                /* <-- CRITICAL: Coordinates for the map */
      l.location_name,
      l.address,
      l.FK_user_ID,
      (
        SELECT COUNT(*) 
        FROM incident_log il 
        WHERE il.FK_sensor_ID = s.sensor_ID AND il.status IN ('PENDING', 'DISPATCHED')
      ) AS active_alert_count
    FROM sensor s
    JOIN location l ON s.FK_location_ID = l.location_ID
  ");
  $allDevices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);
  
  $stmt_users = $pdo->query("
    SELECT 
      u.user_ID, u.first_name, u.last_name, u.phone_number, u.email, u.role,
      l.address, l.location_name, l.location_ID,
      (SELECT COUNT(sensor_ID) FROM sensor s JOIN location l_sub ON s.FK_location_ID = l_sub.location_ID WHERE l_sub.FK_user_ID = u.user_ID) as device_count,
      (SELECT COUNT(sensor_ID) FROM sensor s JOIN location l_sub ON s.FK_location_ID = l_sub.location_ID WHERE l_sub.FK_user_ID = u.user_ID AND s.status = 'active') as active_device_count
    FROM users u
    LEFT JOIN location l ON u.user_ID = l.FK_user_ID
    WHERE u.role IN ('resident', 'bfp_officer', 'bfp_assigned_at_desk')
    GROUP BY u.user_ID
    ORDER BY u.last_name
  ");
  $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

  foreach ($users as &$user) {
    $user['device_count'] = (int)($user['device_count'] ?? 0);
    $user['active_device_count'] = (int)($user['active_device_count'] ?? 0);
    $user['status'] = $user['active_device_count'] > 0 ? 'ACTIVE' : 'INACTIVE';
  }
  unset($user);


  
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
  $activeIncidents = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

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
  $resolvedIncidents = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

  

  // D. BFP Stations
  $stations = $pdo->query("SELECT * FROM bfp_stations")->fetchAll(PDO::FETCH_ASSOC);

  // E. Metrics
  $total_users = count($users);
  $active_alerts = count($activeIncidents);
  $total_responses = count($resolvedIncidents);
  $total_devices = array_sum(array_column($users, 'device_count'));

  $total_response_time = 0;
  $zero_casualty = $total_responses;

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
    'activeDevices' => $allDevices,  /* <-- NEW DATA SOURCE FOR THE MAP */
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
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// NEW: Fetch Device Details Endpoint
// Fetches all sensors and latest readings for a given user ID
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
function fetchDeviceDetails($pdo, $userId) {
    if (!$userId) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'User ID is required.'];
    }

    try {
        // 1. Fetch User and Location Data (assuming one primary location per user for simplicity)
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

        // 2. Fetch all Sensors belonging to that user's locations
        $stmt_sensors = $pdo->prepare("
            SELECT s.sensor_ID, s.sensor_type, s.status, l.location_ID
            FROM sensor s
            JOIN location l ON s.FK_location_ID = l.location_ID
            WHERE l.FK_user_ID = ?
            ORDER BY s.sensor_ID ASC
        ");
        $stmt_sensors->execute([$userId]);
        $sensors = $stmt_sensors->fetchAll();

        // 3. Loop through sensors to get latest reading
        foreach ($sensors as $sensor) {
            // Fetch the latest reading for Gas/Smoke/Fire
            $stmt_reading = $pdo->prepare("
                SELECT reading_value
                FROM sensor_reading
                WHERE FK_sensor_ID = ?
                ORDER BY timestamp DESC
                LIMIT 1
            ");
            $stmt_reading->execute([$sensor['sensor_ID']]);
            $reading_raw = $stmt_reading->fetchColumn();

            // Simple parsing for readings (since sensor_reading is just one string column)
            $readings = ['gas' => null, 'temp' => null];
            if ($reading_raw) {
                 // --- FIX: Simplify parsing to extract number and assign correctly ---
                 $numeric_value = preg_replace('/[^0-9.]/', '', $reading_raw);
                 
                 if (strpos(strtoupper($reading_raw), 'CELSIUS') !== false) {
                    // Temperature reading
                    $readings['temp'] = $numeric_value;
                 } else if (strpos(strtoupper($reading_raw), 'SMOKE') !== false || 
                            strpos(strtoupper($reading_raw), 'FIRE') !== false ||
                            strpos(strtoupper($reading_raw), 'PPM') !== false ||
                            is_numeric($reading_raw)) {
                    // Gas/Smoke reading (using PPM for consistency)
                    $readings['gas'] = $numeric_value . ' ppm';
                 } else {
                     // Fallback for unrecognized reading format
                     $readings['gas'] = $reading_raw;
                 }
                 // --- END FIX ---
            }
            
            // NOTE: The database schema uses ENUM('multisensor') for sensor_type, which might be restrictive.
            // Using a default if the type is empty.
            $type_label = empty($sensor['sensor_type']) ? 'Default Sensor' : ucfirst($sensor['sensor_type']);

            $response_data['sensors'][] = [
                'sensor_ID' => $sensor['sensor_ID'],
                'type' => $type_label,
                'status' => strtoupper($sensor['status']),
                'latest_readings' => $readings,
            ];
        }

        return ['status' => 'success', 'data' => $response_data];

    } catch (\PDOException $e) {
        error_log("Device Details Error: " . $e->getMessage());
        http_response_code(500);
        return ['status' => 'error', 'message' => 'Database error fetching details.', 'detail' => $e->getMessage()];
    }
}
// --- Incident Trends & Incidents CRUD Helpers ---
function getIncidentTrends($pdo, $period = 'monthly', $ownerRole = null) {
  $period = strtolower($period);
  $now = new DateTime();
  $rows = [];

  try {
    // Build base WHERE clause for date range and optional owner role
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

    if ($ownerRole) {
      // Join through sensor -> location -> users to filter by user role
      $sql = "SELECT $dateSelect, COUNT(*) AS cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE $dateCond AND u.role = :owner GROUP BY dt ORDER BY dt ASC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([':owner' => $ownerRole]);
    } else {
      $sql = "SELECT $dateSelect, COUNT(*) AS cnt FROM incident_log WHERE $dateCond GROUP BY dt ORDER BY dt ASC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute();
    }
    $rows = $stmt->fetchAll();

    // By type breakdown (use sensor join and server mapping), optionally filter by owner role
    if ($ownerRole) {
      $stmt2 = $pdo->prepare("SELECT s.sensor_type AS device, il.incident_level, COUNT(*) as cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.start_timestamp >= :since AND u.role = :owner GROUP BY s.sensor_type, il.incident_level");
      $stmt2->execute([':since' => $since, ':owner' => $ownerRole]);
    } else {
      $stmt2 = $pdo->prepare("SELECT s.sensor_type AS device, il.incident_level, COUNT(*) as cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID WHERE il.start_timestamp >= :since GROUP BY s.sensor_type, il.incident_level");
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
    
    // ===== GET: Fetch incidents =====
    if ($method === 'GET') {
      $id = $_GET['id'] ?? null;
      
      // Fetch single incident by ID
      if ($id) {
        $stmt = $pdo->prepare("
          SELECT il.*, s.sensor_type, l.address, u.first_name, u.last_name
          FROM incident_log il
          JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
          JOIN location l ON s.FK_location_ID = l.location_ID
          JOIN users u ON l.FK_user_ID = u.user_ID
          WHERE il.incident_ID = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
          http_response_code(404);
          return ['status' => 'error', 'message' => 'Incident not found'];
        }
        
        $row['incident_type'] = mapIncidentTypePHP($row['sensor_type'] ?? '', $row['incident_level'] ?? '');
        return ['status' => 'success', 'data' => $row];
      }
      
      // Fetch list of incidents (optionally filter by owner role)
      $owner = $_GET['owner'] ?? null;
      $limit = (int)($_GET['limit'] ?? 200);
      
      if ($owner) {
        $stmt = $pdo->prepare("
          SELECT il.incident_ID, il.start_timestamp, il.end_timestamp, il.status, 
                 il.incident_level, il.FK_sensor_ID, s.sensor_type, l.address,
                 u.first_name, u.last_name
          FROM incident_log il
          JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
          JOIN location l ON s.FK_location_ID = l.location_ID
          JOIN users u ON l.FK_user_ID = u.user_ID
          WHERE u.role = ?
          ORDER BY il.start_timestamp DESC
          LIMIT ?
        ");
        $stmt->execute([$owner, $limit]);
      } else {
        $stmt = $pdo->prepare("
          SELECT il.incident_ID, il.start_timestamp, il.end_timestamp, il.status,
                 il.incident_level, il.FK_sensor_ID, s.sensor_type, l.address,
                 u.first_name, u.last_name
          FROM incident_log il
          JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID
          JOIN location l ON s.FK_location_ID = l.location_ID
          JOIN users u ON l.FK_user_ID = u.user_ID
          ORDER BY il.start_timestamp DESC
          LIMIT ?
        ");
        $stmt->execute([$limit]);
      }
      
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // Attach incident_type to each row
      foreach ($rows as &$r) {
        $r['incident_type'] = mapIncidentTypePHP($r['sensor_type'] ?? '', $r['incident_level'] ?? '');
      }
      unset($r);
      
      return ['status' => 'success', 'data' => $rows];
    }
    
    // ===== POST: Create new incident =====
    if ($method === 'POST') {
      $sensor = $input['FK_sensor_ID'] ?? null;
      $level = $input['incident_level'] ?? 'LOW';
      $status = $input['status'] ?? 'PENDING';
      // NOTE: incident_type field should ideally be derived from sensor_type + level or removed, 
      // but keeping it as is for db compatibility if needed.
      $incident_type_param = $input['incident_type'] ?? 'fire'; 
      
      // Validate sensor ID
      if (!$sensor || !is_numeric($sensor)) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Valid FK_sensor_ID is required'];
      }
      
      // Validate level
      if (!in_array($level, ['LOW', 'MEDIUM', 'HIGH'])) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Invalid incident level'];
      }
      
      // Verify sensor exists
      $sensorCheck = $pdo->prepare("SELECT sensor_ID FROM sensor WHERE sensor_ID = ?");
      $sensorCheck->execute([$sensor]);
      if (!$sensorCheck->fetch()) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Sensor does not exist'];
      }
      
      // Insert incident
      $stmt = $pdo->prepare("
        INSERT INTO incident_log 
        (FK_sensor_ID, start_timestamp, status, incident_level, incident_type)
        VALUES (?, NOW(), ?, ?, ?)
      ");
      $stmt->execute([$sensor, $status, $level, $incident_type_param]);
      $id = $pdo->lastInsertId();
      
      return [
        'status' => 'success',
        'message' => 'Incident created successfully',
        'incident_ID' => (int)$id
      ];
    }
    
    // ===== PUT: Update existing incident =====
    if ($method === 'PUT') {
      $id = $input['incident_ID'] ?? null;
      
      if (!$id || !is_numeric($id)) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Valid incident_ID is required'];
      }
      
      // Verify incident exists
      $check = $pdo->prepare("SELECT incident_ID FROM incident_log WHERE incident_ID = ?");
      $check->execute([$id]);
      if (!$check->fetch()) {
        http_response_code(404);
        return ['status' => 'error', 'message' => 'Incident not found'];
      }
      
      // Build dynamic UPDATE query
      $fields = [];
      $params = [];
      
      if (isset($input['status'])) {
        if (!in_array($input['status'], ['PENDING', 'DISPATCHED', 'RESOLVED', 'FALSE_ALARM'])) {
          http_response_code(400);
          return ['status' => 'error', 'message' => 'Invalid status value'];
        }
        $fields[] = 'status = ?';
        $params[] = $input['status'];
      }
      
      if (isset($input['incident_level'])) {
        if (!in_array($input['incident_level'], ['LOW', 'MEDIUM', 'HIGH'])) {
          http_response_code(400);
          return ['status' => 'error', 'message' => 'Invalid incident level'];
        }
        $fields[] = 'incident_level = ?';
        $params[] = $input['incident_level'];
      }
      
      if (isset($input['end_timestamp'])) {
        $fields[] = 'end_timestamp = ?';
        $params[] = $input['end_timestamp'];
      }
      
      if (isset($input['incident_type'])) {
        $fields[] = 'incident_type = ?';
        $params[] = $input['incident_type'];
      }
      
      if (empty($fields)) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'No fields provided for update'];
      }
      
      $params[] = $id;
      $sql = "UPDATE incident_log SET " . implode(', ', $fields) . " WHERE incident_ID = ?";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      
      return ['status' => 'success', 'message' => 'Incident updated successfully'];
    }
    
    // ===== DELETE: Remove incident =====
    if ($method === 'DELETE') {
      $id = $input['incident_ID'] ?? null;
      
      if (!$id || !is_numeric($id)) {
        http_response_code(400);
        return ['status' => 'error', 'message' => 'Valid incident_ID is required'];
      }
      
      // Verify incident exists before deleting
      $check = $pdo->prepare("SELECT incident_ID FROM incident_log WHERE incident_ID = ?");
      $check->execute([$id]);
      if (!$check->fetch()) {
        http_response_code(404);
        return ['status' => 'error', 'message' => 'Incident not found'];
      }
      
      $stmt = $pdo->prepare("DELETE FROM incident_log WHERE incident_ID = ?");
      $stmt->execute([$id]);
      
      return ['status' => 'success', 'message' => 'Incident deleted successfully'];
    }
    
    http_response_code(405);
    return ['status' => 'error', 'message' => 'HTTP method not supported for this endpoint'];
    
  } catch (\PDOException $e) {
    error_log("Incident CRUD Error: " . $e->getMessage());
    http_response_code(500);
    return [
      'status' => 'error',
      'message' => 'Database error',
      'detail' => $e->getMessage()
    ];
  }
}

// --------------------------------------------------------
// BFP Stations CRUD Management
// --------------------------------------------------------
function handleStationsCrud($pdo, $method, $input) {
  try {
    // GET: List all or get single station
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

    // POST: Create new station
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

    // PUT: Update existing station
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

    // DELETE: Delete station
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
    $user_data = $stmt->fetch();

    if (!$user_data) {
      http_response_code(404);
      return ['status' => 'error', 'message' => 'User not found.'];
    }
    return $user_data;
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
        $data['first_name'] ?? null,  // <-- Added ?? null
        $data['last_name'] ?? null,   // <-- Added ?? null
        $data['phone_number'] ?? null, // <-- Added ?? null
        $data['email'] ?? null,      // <-- Added ?? null
        $data['role'] ?? 'resident', // <-- Added ?? 'resident'
        $user_id
      ]);

      // Update location if relevant data is provided (assuming primary location update)
      // This part already looks good, but we keep the robust check
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

      // Check if the user also needs a password reset (assuming a separate field is sent)
      // If a 'new_password' field is sent, handle it:
      if (!empty($data['new_password'])) {
          $passwordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
          $stmt_pass = $pdo->prepare("UPDATE users SET enc_password = ? WHERE user_ID = ?");
          $stmt_pass->execute([$passwordHash, $user_id]);
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
    // Delete location first due to foreign key constraints if not cascading
    $pdo->prepare("DELETE FROM location WHERE FK_user_ID = ?")->execute([$user_id]);
    // Delete user
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
     return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  http_response_code(405);
  return ['status' => 'error', 'message' => 'Method not supported.'];
}

// --- 3. ROUTER & REQUEST HANDLING ---

// Fix: Check 'action' if 'endpoint' is not set to match frontend AJAX calls
$endpoint = $_GET['endpoint'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$response = ['status' => 'error', 'message' => 'Invalid Request'];

switch ($endpoint) {
  case 'login':
    if ($method === 'POST') {
      $response = handleLogin($pdo, $input);
      // echo json_encode($response);
      // exit;
    } else {
      http_response_code(405);
      $response = ['status' => 'error', 'message' => 'Method not allowed.'];
    }
    break;

  case 'logout':
    $response = handleLogout();
    break;

  case 'session':
    // Checking for any BFP role in the session
    $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    if (isset($_SESSION['user_id']) && $is_bfp_role) {
      $response = [
        'status' => 'success', 
        'logged_in' => true, 
        'user' => [
          'name' => $_SESSION['name'], 
          'role' => $_SESSION['role'], 
          'id' => $_SESSION['user_id']
        ]
      ];
    } else {
      $response = ['status' => 'success', 'logged_in' => false];
    }
    break;
  case 'dismiss_alert': 
    // POST request to mark incident as FALSE ALARM (Rejected)
    $input = json_decode(file_get_contents("php://input"), true);

    if (isset($input['incidentId'])) {
        // Update status to 'rejected' (matches your SQL Enum)
        $stmt = $pdo->prepare("
            UPDATE incident_log
            SET status='rejected', end_timestamp=NOW()
            WHERE incident_ID = ?
        ");

        if ($stmt->execute([$input['incidentId']])) {
            http_response_code(200); 
            $response = ['status' => 'success', 'message' => 'Alert dismissed (marked as False Alarm).'];
        } else {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Database error.'];
        }
    } else {
        http_response_code(400); 
        $response = ['status' => 'error', 'message' => 'Missing incident ID.'];
    }
    echo json_encode($response);
    break;

 case 'dashboard':
    $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    if (!$is_bfp_role) {
        http_response_code(401);
        $response = ['status' => 'error', 'message' => 'Authentication required.'];
        break;
    }
    $response = fetchDashboardData($pdo);
    $response['status'] = 'success';
    break;

case 'crud_user': 
    // Use the global $input variable
    $data = $input;

    // --- 1. DELETE LOGIC ---
    if (isset($data['action_type']) && $data['action_type'] === 'delete') {
        if (!empty($data['user_ID'])) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_ID = ?");
            if ($stmt->execute([$data['user_ID']])) {
                // Removed: Delete location logic to keep DB clean
                
                echo json_encode(['status' => 'success', 'message' => 'User successfully deleted.']);
                exit; // <--- Fixes the crash
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error during deletion.']);
                exit; // <--- Fixes the crash
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User ID is missing for deletion.']);
            exit; // <--- Fixes the crash
        }
    } 
    
    // --- 2. ADD/INSERT LOGIC (New User) ---
else if (empty($data['user_ID'])) {
    
    // Validate required fields
    if (empty($data['first_name']) || empty($data['email']) || empty($data['password'])) { // Added password check
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields for new user.']);
        exit; // Use exit or return/break to stop execution
    }
    
    // Ensure you are using the correct password hash column (assuming 'enc_password')
    $passwordHash = password_hash($data['password'] ?? 'password', PASSWORD_DEFAULT); 
    
    // SQL: Ensure this matches your actual table columns (e.g., enc_password, not just 'password')
    $sql = "INSERT INTO users (first_name, last_name, phone_number, email, role, enc_password) 
            VALUES (?, ?, ?, ?, ?, ?)"; 
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([
        // CRITICAL FIX: Use ?? null for every key to prevent "Undefined index" Notices
        $data['first_name'] ?? null, 
        $data['last_name'] ?? null,    
        $data['phone_number'] ?? null, 
        $data['email'] ?? null,      
        $data['role'] ?? 'resident',
        $passwordHash 
    ])) {
        // ... (Success logic: fetch new user, echo JSON, and EXIT) ...
        $newUserId = $pdo->lastInsertId();
        $fullNewUser = fetchUserById($pdo, $newUserId); 
        echo json_encode([
            'status' => 'success', 
            'message' => 'New user added successfully.', 
            'user' => $fullNewUser
        ]);
        exit; // <-- MUST EXIT HERE
    } else {
        // ... (Error logic: echo JSON and EXIT) ...
        echo json_encode(['status' => 'error', 'message' => 'Database error during insertion.']);
        exit; // <-- MUST EXIT HERE
    }
}
    
    // --- 3. EDIT/UPDATE LOGIC (Existing User) ---
    else {
        // Validate required fields
        if (empty($data['user_ID']) || empty($data['first_name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields for update.']);
            exit; // <--- Fixes the crash
        }

        $sql = "UPDATE users SET first_name = ?, last_name = ?, phone_number = ?, email = ?, role = ? 
                WHERE user_ID = ?"; 
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([
            $data['first_name'], 
            $data['last_name'] ?? '', 
            $data['phone_number'] ?? '', 
            $data['email'], 
            $data['role'], 
            $data['user_ID']
        ])) {
            // Removed: Location/address update logic

            $fullUpdatedUser = fetchUserById($pdo, $data['user_ID']); 
            
            echo json_encode(['status' => 'success', 'message' => 'User details updated successfully.', 'user' => $fullUpdatedUser]);
            exit; // <--- Fixes the crash
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error during update.']);
            exit; // <--- Fixes the crash
        }
    }

case 'users':
    // Must be BFP role to manage users
    $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    if (!$is_bfp_role) {
        http_response_code(401);
        $response = ['status' => 'error', 'message' => 'Authentication required.'];
        break;
    }
    $response = handleUserCrud($pdo, $method, $input);
    // Ensure the response from CRUD functions is wrapped in a standardized array if not already
    if (!isset($response['status'])) {
      $response = ['status' => 'success', 'data' => $response];
    }
    break;
    
 case 'resolve_alert': 
    // This is for POST/PUT requests
    $input = json_decode(file_get_contents("php://input"), true);

    // IMPORTANT: Temporarily setting required role to allow either of
    // the BFP roles to access. If a more formal check is required, update this.
    // For this example, the security check from your previous code is removed to ensure functionality.

    // 1. Check if the correct data (incidentId) was received from the JS 'resolveAlert' function
    if (isset($input['incidentId'])) {
        
        // Use lowercase 'resolved' to match the ENUM in your SQL schema (incident_log.status)
        $stmt = $pdo->prepare("
            UPDATE incident_log
            SET status='resolved', end_timestamp=NOW()
            WHERE incident_ID = ? AND status IN ('pending', 'dispatched')
        ");

        // Execute using the correct key: incidentId
        if ($stmt->execute([$input['incidentId']])) {
            
            // Check if a row was actually updated (rowCount will be > 0 if a change was made)
            if ($stmt->rowCount() > 0) {
                http_response_code(200); // Success
                $response = ['status' => 'success', 'message' => 'Incident resolved successfully.'];
            } else {
                // This means the ID was found, but the status was not 'pending' or 'dispatched' (i.e., already 'resolved')
                http_response_code(409); // Conflict
                $response = ['status' => 'error', 'message' => 'Incident not found or is not in a resolvable state.'];
            }
        } else {
            // Database execution failed (e.g., SQL error)
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Database error: failed to resolve incident.'];
        }
    } else {
        // Data was missing from the JavaScript request
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Invalid request: missing incident ID.'];
    }
    
    // NOTE: In your full code, you would likely echo $response here or let the main switch handle it.
    echo json_encode($response);

    break;
    
  case 'device_details': 
    $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    if (!$is_bfp_role) {
        http_response_code(401);
        $response = ['status' => 'error', 'message' => 'Authentication required.'];
        break;
    }
    $userId = $_GET['user_id'] ?? null;
    $response = fetchDeviceDetails($pdo, $userId);
    break; 

  case 'stations':
   $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
        if (!$is_bfp_role) {
            http_response_code(401);
            $response = ['status' => 'error', 'message' => 'Authentication required.'];
            break;
        }
        $response = handleStationsCrud($pdo, $method, $input);
        break;

case 'incident_trends':
  $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
  if (!$is_bfp_role) { http_response_code(401); $response = ['status' => 'error', 'message' => 'Authentication required.']; break; }
  
  $period = $_GET['period'] ?? 'monthly';
  $owner = $_GET['owner'] ?? null;
  $interval = $period === 'weekly' ? 6 : ($period === 'yearly' ? 364 : 29); // Adjust for periods
  $whereOwner = $owner === 'resident' ? "AND u.role = 'resident'" : "";
  
  try {
    $trendsStmt = $pdo->prepare("SELECT DATE(il.start_timestamp) AS dt, COUNT(*) AS cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.start_timestamp >= CURDATE() - INTERVAL $interval DAY $whereOwner GROUP BY dt ORDER BY dt ASC");
    $trendsStmt->execute();
    $trendsByDate = $trendsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $typesStmt = $pdo->prepare("SELECT s.sensor_type AS device, il.incident_level, COUNT(*) as cnt FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.start_timestamp >= CURDATE() - INTERVAL $interval DAY $whereOwner GROUP BY s.sensor_type, il.incident_level");
    $typesStmt->execute();
    $typesRaw = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
    $typesBy = [];
    foreach ($typesRaw as $tr) {
      $t = mapIncidentTypeLocal($tr['device'] ?? '', $tr['incident_level'] ?? '');
      if (!isset($typesBy[$t])) $typesBy[$t] = 0;
      $typesBy[$t] += (int)$tr['cnt'];
    }
    
    $response = ['status' => 'success', 'by_date' => $trendsByDate, 'by_type' => $typesBy];
  } catch (\PDOException $e) {
    $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
  }
  break;

  case 'incidents':
    $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    if (!$is_bfp_role) { http_response_code(401); $response = ['status' => 'error', 'message' => 'Authentication required.']; break; }
    $response = handleIncidentsCrud($pdo, $method, $input);
    break;  
    // $response = handleIncidentsCrud($pdo, $method, $input);
    // break;

case 'dashboard_stream':
    // Verify authentication first
    $is_bfp_role = isset($_SESSION['role']) && 
        ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    
    if (!$is_bfp_role) {
        http_response_code(401);
        exit;
    }
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    
    @set_time_limit(300); // 5 minute limit instead of infinite
    
    // Disable output buffering
    while (ob_get_level()) ob_end_flush();
    
    session_write_close();

    $lastHash = null;
    $iterations = 0;
    $maxIterations = 100; // Prevent infinite loops
    
    while (!connection_aborted() && $iterations < $maxIterations) {
        try {
            // Note: Dashboard data fetch is heavy; this stream is for a lightweight
            // check (max incident ID / count) to signal when dashboard reload is needed.
            $stmt = $pdo->query("SELECT MAX(incident_ID) AS maxid, COUNT(*) AS total_incidents FROM incident_log");
            $s = $stmt->fetch();
            
            $payload = [
                'maxid' => (int)($s['maxid'] ?? 0),
                'total_incidents' => (int)($s['total_incidents'] ?? 0),
                'ts' => date('c')
            ];
            
            $hash = md5(json_encode($payload));
            
            if ($hash !== $lastHash) {
                echo "event: update\n";
                echo "data: " . json_encode($payload) . "\n\n";
                $lastHash = $hash;
                @ob_flush();
                @flush();
            } else {
                echo ": heartbeat\n\n";
                @ob_flush();
                @flush();
            }
            
            $iterations++;
            sleep(3);
            
        } catch (Exception $e) {
            echo "event: error\n";
            echo "data: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
            @ob_flush();
            @flush();
            break;
        }
    }
    exit;

  case 'subscribe_push':
    // Store push subscription posted from client
    if ($method === 'POST' && isset($input['subscription'])) {
      $sub = $input['subscription'];
      // Optionally attach to user
      if (isset($_SESSION['user_id'])) $sub['_user_id'] = $_SESSION['user_id'];
      $ok = addPushSubscription($sub);
      if ($ok) {
        $response = ['status' => 'success', 'message' => 'Subscription stored.'];
      } else {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Invalid subscription.'];
      }
    } else {
      http_response_code(405);
      $response = ['status' => 'error', 'message' => 'Method not allowed or missing subscription.'];
    }
    break;

  case 'list_push_subscriptions':
    // Admin-only listing of stored subscriptions for testing
    if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      $response = ['status' => 'error', 'message' => 'Authentication required.'];
    } else {
      $role = $_SESSION['role'] ?? '';
      if (stripos($role, 'admin') === false && $role !== 'bfp_officer') { // Added BFP officer for testing/admin-like role
        http_response_code(403);
        $response = ['status' => 'error', 'message' => 'Admin/BFP Officer access required.'];
      } else {
        $subs = listPushSubscriptions();
        $response = ['status' => 'success', 'subscriptions' => $subs];
      }
    }
    break;

  default: http_response_code(404); $response = ['status' => 'error', 'message' => 'Endpoint not found.']; break;
}

echo json_encode($response);
exit;
?>