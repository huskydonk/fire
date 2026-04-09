<?php
// Include configuration
session_start();
require_once 'config.php';

// --- Database Connection ---
//$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// Check connection
if ($mysqli->connect_error) {
    die("ERROR: Could not connect to the database. " . $mysqli->connect_error);
}

// --- Initialize Variables ---
$message = '';
$message_type = ''; // 'success' or 'error'

// =========================================================
// 1. HANDLE ESP32 BROADCAST (Discovery Mode)
//    Called by ESP32: /devicemanagement.php?action=broadcast_mac&mac=...
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'broadcast_mac') {
    header('Content-Type: application/json'); // Ensure JSON response
    $mac_address = $mysqli->real_escape_string($_GET['mac'] ?? '');
    
    if (empty($mac_address)) {
        http_response_code(400);
        echo json_encode(['error' => 'MAC required']);
        exit;
    }
    
    // Check if device already exists (either assigned or pending)
    $check = $mysqli->query("SELECT sensor_ID FROM sensor WHERE esp_ip_unique = '{$mac_address}'");
    
    if ($check && $check->num_rows > 0) {
        echo json_encode(['status' => 'registered', 'message' => 'Device already known']);
        exit;
    }
    
    // Insert new device as 'pending' with NO location (NULL)
    // Wrapped in TRY-CATCH to prevent Fatal Errors if DB is Strict
    try {
        $insert = "INSERT INTO sensor (esp_ip_unique, sensor_type, status, FK_location_ID) VALUES (?, 'multisensor', 'pending', NULL)";
        $stmt = $mysqli->prepare($insert);
        if ($stmt) {
            $stmt->bind_param("s", $mac_address);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['status' => 'available', 'message' => 'Device ready for setup']);
        } else {
            throw new Exception("Prepare failed: " . $mysqli->error);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database Error: ' . $e->getMessage(),
            'hint' => 'Ensure FK_location_ID column allows NULL values.'
        ]);
    }
    exit; 
}

// =========================================================
// AJAX: return available (unassigned) MACs as JSON
// Called by client-side JS: devicemanagement.php?action=list_available_macs
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'list_available_macs') {
    $macs = [];
    // Fetch sensors where location is NULL (or 0 if using dummy ID)
    $mac_sql = "SELECT esp_ip_unique AS mac_address FROM sensor WHERE FK_location_ID IS NULL OR FK_location_ID = 0 ORDER BY installed_at DESC";
    $mac_result = $mysqli->query($mac_sql);
    if ($mac_result) {
        while ($row = $mac_result->fetch_assoc()) {
            $macs[] = $row['mac_address'];
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['macs' => $macs]);
    exit;
}

// =========================================================
// 2. MAIN FORM HANDLER (Create/Update/Delete)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']))  {
    $action = $_POST['action'] ?? '';
    $sensor_id = (int)($_POST['sensor_id'] ?? 0);
    $location_id = (int)($_POST['location_id'] ?? 0);
    $user_id_selected = (int)($_POST['user_id_selected'] ?? 0);
    $create_new_location = isset($_POST['create_new_location']) ? 1 : 0;
    
    // New Location Fields
    $new_location_name = $mysqli->real_escape_string($_POST['new_location_name'] ?? '');
    $new_address = $mysqli->real_escape_string($_POST['new_address'] ?? '');
    $new_latitude = $_POST['new_latitude'] ?? null;
    $new_longitude = $_POST['new_longitude'] ?? null;
    
    // Device Fields
    $mac_address = $mysqli->real_escape_string($_POST['mac_address'] ?? '');
    // Fallback if dropdown is disabled (edit mode)
    if(empty($mac_address)) $mac_address = $mysqli->real_escape_string($_POST['esp_ip_unique'] ?? ''); 
    
    $sensor_type = $mysqli->real_escape_string($_POST['sensor_type'] ?? 'multisensor');
    $status = $mysqli->real_escape_string($_POST['status'] ?? 'inactive');
    
    $capabilities_post = $_POST['capabilities'] ?? [];

    try {
        if ($action === 'create' || $action === 'update') {
            
            // --- Validation ---
            if (empty($mac_address)) {
                throw new Exception("Please select a device (MAC address).");
            }
            if (empty($location_id) && empty($create_new_location)) {
                throw new Exception("Please select an existing location or create a new one.");
            }

            // --- Handle New Location Creation ---
            if (!empty($create_new_location)) {
                if (empty($new_location_name)) {
                    throw new Exception('New location name is required when creating a location.');
                }
                if (empty($user_id_selected)) {
                    throw new Exception('Please select a user to own the new location.');
                }

                $ins_sql = "INSERT INTO location (FK_user_ID, location_name, address, latitude, longitude, permission_level, date_created) VALUES (?, ?, ?, ?, ?, 'private', NOW())";
                $ins_stmt = $mysqli->prepare($ins_sql);
                if (!$ins_stmt) {
                    throw new Exception('Failed to prepare location insert statement.');
                }
                
                // Ensure null if empty string
                $lat_param = ($new_latitude !== '') ? $new_latitude : null;
                $lng_param = ($new_longitude !== '') ? $new_longitude : null;
                
                $ins_stmt->bind_param('issss', $user_id_selected, $new_location_name, $new_address, $lat_param, $lng_param);
                
                if (!$ins_stmt->execute()) {
                    $ins_stmt->close();
                    throw new Exception('Failed to create new location: ' . $ins_stmt->error);
                }
                $location_id = $ins_stmt->insert_id;
                $ins_stmt->close();
            }

            // --- Verify Location Ownership ---
            if (!empty($user_id_selected)) {
                $loc_check_sql = "SELECT FK_user_ID FROM location WHERE location_ID = ? LIMIT 1";
                $loc_check_stmt = $mysqli->prepare($loc_check_sql);
                if (!$loc_check_stmt) throw new Exception('Failed to prepare location validation.');
                
                $loc_check_stmt->bind_param('i', $location_id);
                $loc_check_stmt->execute();
                $loc_check_stmt->bind_result($fk_user_id);
                if ($loc_check_stmt->fetch()) {
                    if ((int)$fk_user_id !== (int)$user_id_selected) {
                        $loc_check_stmt->close();
                        throw new Exception('Selected location does not belong to the chosen user.');
                    }
                } else {
                    $loc_check_stmt->close();
                    throw new Exception('Selected location not found.');
                }
                $loc_check_stmt->close();
            }

            // --- CREATE (or CLAIM) ACTION ---
            if ($action === 'create') {
                // Check if this MAC already exists (e.g., from broadcast)
                $check_sql = "SELECT sensor_ID FROM sensor WHERE esp_ip_unique = ?";
                $check_stmt = $mysqli->prepare($check_sql);
                $check_stmt->bind_param("s", $mac_address);
                $check_stmt->execute();
                $check_stmt->store_result();
                
                if ($check_stmt->num_rows > 0) {
                    // CLAIM LOGIC: Update existing pending device
                    $check_stmt->bind_result($existing_sensor_id);
                    $check_stmt->fetch();
                    $check_stmt->close();

                    $sql = "UPDATE sensor SET FK_location_ID = ?, sensor_type = ?, status = ? WHERE sensor_ID = ?";
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param("issi", $location_id, $sensor_type, $status, $existing_sensor_id);
                    $sensor_insert_id = $existing_sensor_id; 
                } else {
                    // INSERT LOGIC: Brand new device
                    $check_stmt->close();
                    $sql = "INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status) VALUES (?, ?, ?, ?)";
                    $stmt = $mysqli->prepare($sql);
                    $stmt->bind_param("isss", $location_id, $mac_address, $sensor_type, $status);
                    $sensor_insert_id = 0; 
                }
                
                if ($stmt->execute()) {
                    if ($sensor_insert_id === 0) $sensor_insert_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Add Default Capabilities
                    $default_caps = ['smoke', 'ir_fire', 'thermistor'];
                    foreach ($default_caps as $cap_code) {
                        $cstmt = $mysqli->prepare("SELECT capability_id FROM sensor_capability WHERE code = ? LIMIT 1");
                        if ($cstmt) {
                            $cstmt->bind_param('s', $cap_code);
                            $cstmt->execute();
                            $cstmt->bind_result($cap_id);
                            if ($cstmt->fetch()) {
                                $cstmt->close();
                                $ins = $mysqli->prepare("INSERT IGNORE INTO sensor_has_capability (sensor_ID, capability_id) VALUES (?, ?)");
                                if ($ins) { $ins->bind_param('ii', $sensor_insert_id, $cap_id); $ins->execute(); $ins->close(); }
                            } else { $cstmt->close(); }
                        }
                    }

                    $message = "Device '{$mac_address}' configured successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }

            // --- UPDATE ACTION ---
            } elseif ($action === 'update' && $sensor_id > 0) {
                $sql = "UPDATE sensor SET FK_location_ID = ?, esp_ip_unique = ?, sensor_type = ?, status = ? WHERE sensor_ID = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("isssi", $location_id, $mac_address, $sensor_type, $status, $sensor_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    $message = "Device updated successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }
            }

        // --- DELETE ACTION ---
        } elseif ($action === 'delete' && $sensor_id > 0) {
            $sql = "DELETE FROM sensor WHERE sensor_ID = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $sensor_id);

            if ($stmt->execute()) {
                $stmt->close();
                $message = "Device deleted successfully.";
                $message_type = 'success';
            } else {
                throw new Exception("Error executing query: " . $stmt->error);
            }
        }
    } catch (Exception $e) {
        $message = "Operation failed: " . $e->getMessage();
        $message_type = 'error';
    }
}


// --- Pagination and Read Operation ---
if (!defined('RECORDS_PER_PAGE')) {
    define('RECORDS_PER_PAGE', 100);
}

$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;

// Get total number of records (REMOVED FILTER to show unassigned devices)
$total_sql = "SELECT COUNT(sensor_ID) AS total FROM sensor"; 
$total_result = $mysqli->query($total_sql);
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / RECORDS_PER_PAGE);

// Safety: Adjust current page if out of bounds
if ($total_records > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
} elseif ($total_records == 0) {
    $current_page = 1;
}

$offset = ($current_page - 1) * RECORDS_PER_PAGE;
if ($offset < 0) $offset = 0; // Absolute safety

// Fetch All Devices (REMOVED FILTER to show unassigned devices)
$read_sql = "SELECT s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, s.installed_at,
                    l.location_ID, l.FK_user_ID, l.location_name, l.address,
                    u.user_ID AS owner_user_id, u.first_name AS owner_first, u.last_name AS owner_last
             FROM sensor s
             LEFT JOIN location l ON s.FK_location_ID = l.location_ID
             LEFT JOIN users u ON l.FK_user_ID = u.user_ID
            ORDER BY s.sensor_ID ASC
            LIMIT ? OFFSET ?";

$limit = (int)RECORDS_PER_PAGE;
$offset = (int)$offset;

$stmt = $mysqli->prepare($read_sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$devices = $result->fetch_all(MYSQLI_ASSOC);

// Fetch all locations for dropdown
$locations_sql = "SELECT location_ID, FK_user_ID, location_name, address FROM location ORDER BY location_name";
$locations_result = $mysqli->query($locations_sql);
$locations = $locations_result->fetch_all(MYSQLI_ASSOC);

// Fetch users for searchable selector
$users_sql = "SELECT user_ID, first_name, last_name, email, phone_number FROM users ORDER BY last_name, first_name";
$users_result = $mysqli->query($users_sql);
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

// Available MACs (for dropdown init)
$available_macs = [];
$mac_sql = "SELECT esp_ip_unique AS mac_address FROM sensor WHERE FK_location_ID IS NULL OR FK_location_ID = 0 ORDER BY installed_at DESC";
$mac_result = $mysqli->query($mac_sql);
if ($mac_result) {
    $available_macs = $mac_result->fetch_all(MYSQLI_ASSOC);
}

$start_record = min($total_records, $offset + 1);
$end_record = min($total_records, $offset + count($devices)); // Use actual count to be precise

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - BFP</title>
    
    <!-- TAILWIND CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- LEAFLET MAP CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        /* Styles */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        /* Set initial opacity to 1 to ensure visibility even if animation fails */
        .device-row { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
        <?php for ($i = 0; $i < RECORDS_PER_PAGE; $i++): ?>
        .device-row:nth-child(<?php echo $i + 1; ?>) { animation-delay: <?php echo 0.1 + ($i * 0.1); ?>s; }
        <?php endfor; ?>
        tbody tr:hover { background: #f8fafc; transform: translateX(4px); }
        .action-btn:hover { transform: scale(1.1); }
        .modal-overlay { z-index: 1000; }
        .message-box { animation: fadeIn 0.5s ease forwards; }
        .table-scroll { max-height: 46vh; overflow: auto; }
        .table-scroll thead th { position: sticky; top: 0; z-index: 20; background: #f8fafc; backdrop-filter: blur(4px); }
        .status-badge { display: inline-flex; align-items: center; gap: 6px; }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; }
        
        input, select, textarea { color: #1e293b !important; background-color: #ffffff !important; }
        
        /* Map Container Fix */
        .leaflet-container { z-index: 10; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-800 to-slate-900 min-h-screen p-4 md:p-8 font-[Inter]">
    <div class="max-w-7xl mx-auto bg-white rounded-3xl p-6 md:p-10 shadow-2xl">
        
        <!-- Success/Error Message Box -->
        <?php if (!empty($message)): 
            $bg_color = $message_type === 'success' ? 'bg-green-500' : 'bg-red-500';
            $icon = $message_type === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z';
        ?>
        <div id="message-box" class="message-box fixed top-4 right-4 max-w-sm w-full p-4 rounded-xl shadow-lg text-white <?php echo $bg_color; ?> flex items-start gap-3 z-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon; ?>"></path>
            </svg>
            <p class="text-sm font-medium flex-grow"><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('message-box').remove()" class="text-white opacity-70 hover:opacity-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <script>setTimeout(() => { const msgBox = document.getElementById('message-box'); if (msgBox) msgBox.remove(); }, 5000);</script>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Device Management</h1>
            <div class="flex flex-col sm:flex-row gap-3 md:gap-4 items-stretch sm:items-center">
                <button onclick="openDeviceModal('create')" class="bg-red-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-red-600 transition flex items-center justify-center gap-2 shadow-lg shadow-red-500/50">
                    <span class="text-xl">+</span>
                    Add Device
                </button>
                <div class="relative">
                    <input type="text" id="device-search-input" placeholder="Search devices..." class="pl-10 pr-10 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 w-full sm:w-80 transition-all">
                </div>
            </div>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden md:block table-scroll border border-slate-200 rounded-xl shadow-inner">
            <table class="w-full min-w-full">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Device ID</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Mac Address</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-left py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Installed At</th>
                        <th class="text-center py-4 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="device-table-body">
                    <?php if (count($devices) > 0): ?>
                        <?php foreach ($devices as $device): ?>
                        <tr class="border-b border-slate-100 device-row">
                            <td class="py-3 px-6 text-sm text-slate-800 font-semibold"><?php echo htmlspecialchars($device['sensor_ID']); ?></td>
                            <td class="py-3 px-6 text-sm text-slate-700 font-mono"><?php echo htmlspecialchars($device['esp_ip_unique']); ?></td>
                            <td class="py-3 px-6 text-sm text-slate-700">
                                <?php if (!empty($device['location_name'])): ?>
                                    <div class="font-medium"><?php echo htmlspecialchars($device['location_name']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($device['address'] ?? ''); ?></div>
                                <?php else: ?>
                                    <div class="inline-block px-2 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded">Unassigned</div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                    <?php echo htmlspecialchars(ucfirst($device['sensor_type'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6">
                                <span class="status-badge px-2 py-0.5 text-xs font-medium rounded-full 
                                    <?php echo $device['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                               ($device['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <span class="status-dot <?php echo $device['status'] === 'active' ? 'bg-green-500' : 
                                                                    ($device['status'] === 'pending' ? 'bg-yellow-500' : 'bg-gray-500'); ?>"></span>
                                    <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-sm text-slate-500"><?php echo date('M d, Y', strtotime($device['installed_at'])); ?></td>
                            <td class="py-3 px-6 flex items-center justify-center gap-1">
                                <button onclick='openDeviceModal("edit", <?php echo json_encode($device); ?>)' class="action-btn p-2 text-orange-600 rounded-full hover:bg-orange-100 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                </button>
                                <button onclick="showDeleteConfirmation(<?php echo $device['sensor_ID']; ?>, '<?php echo htmlspecialchars($device['esp_ip_unique'], ENT_QUOTES); ?>')" class="action-btn p-2 text-red-600 rounded-full hover:bg-red-100 transition">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-8 text-slate-500">No devices configured yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="md:hidden space-y-4">
            <?php if (count($devices) > 0): ?>
                <?php foreach ($devices as $device): ?>
                    <div class="mobile-card bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <div class="text-xs text-slate-500 uppercase font-semibold mb-1">Device ID: <?php echo htmlspecialchars($device['sensor_ID']); ?></div>
                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($device['esp_ip_unique']); ?></div>
                            </div>
                            <span class="status-badge px-2 py-0.5 text-xs font-medium rounded-full <?php echo $device['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                            </span>
                        </div>
                        <div class="flex gap-2 pt-3 border-t border-slate-200">
                            <button onclick='openDeviceModal("edit", <?php echo json_encode($device); ?>)' class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-orange-50 text-orange-600 rounded-lg">Edit</button>
                            <button onclick="showDeleteConfirmation(<?php echo $device['sensor_ID']; ?>, '<?php echo htmlspecialchars($device['esp_ip_unique'], ENT_QUOTES); ?>')" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:text-red-600">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-slate-500 bg-slate-50 rounded-xl p-4 border">No devices configured yet.</div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4" style="display:none;">
             <div class="text-sm text-slate-600">
                <?php if ($total_records > 0): ?>
                    Showing <?php echo $start_record; ?> to <?php echo $end_record; ?> of <?php echo $total_records; ?>
                <?php else: ?>
                    No results.
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                 <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="w-8 h-8 flex items-center justify-center rounded <?php echo $i == $current_page ? 'bg-red-500 text-white' : 'text-slate-600'; ?>"><?php echo $i; ?></a>
                 <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Device Modal -->
    <div id="device-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-lg p-6 shadow-2xl overflow-y-auto max-h-[90vh]" onclick="event.stopPropagation()">
            <h2 id="modal-title" class="text-2xl font-bold text-slate-800 mb-6">Add New Device</h2>
            
            <form id="device-form" method="POST" action="devicemanagement.php">
                <input type="hidden" name="action" id="modal-action">
                <input type="hidden" name="sensor_id" id="modal-sensor-id">

                <!-- MAC ADDRESS SELECTION -->
                <div class="mb-4">
                    <label for="mac_address" class="block text-sm font-medium text-slate-700 mb-1">Device (MAC Address) <span class="text-red-500">*</span></label>
                    <select id="mac_address" name="mac_address" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">-- Select Discovered Device --</option>
                        <?php foreach ($available_macs as $mac): ?>
                            <option value="<?php echo htmlspecialchars($mac['mac_address']); ?>">
                                <?php echo htmlspecialchars($mac['mac_address']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($available_macs)): ?>
                        <p class="text-xs text-orange-600 mt-1 server-available-msg">⚠️ No unassigned devices found. Power on ESP32 to discover.</p>
                    <?php else: ?>
                        <p class="text-xs text-green-600 mt-1 server-available-msg">✅ Showing unassigned devices found on network.</p>
                    <?php endif; ?>
                    <p id="available-msg" class="text-xs mt-1" aria-live="polite"></p>
                </div>

                <!-- USER SELECTION -->
                <div class="mb-4">
                    <label for="user_search" class="block text-sm font-medium text-slate-700 mb-1">User Owner</label>
                    <input list="users-list" id="user_search" placeholder="Search user..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 mb-2">
                    <datalist id="users-list">
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_ID'] . '::' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="user_id_selected" id="user_id_selected">

                    <!-- LOCATION SELECTION -->
                    <label for="location_id" class="block text-sm font-medium text-slate-700 mb-1">Location <span class="text-red-500">*</span></label>
                    <select id="location_id" name="location_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['location_ID']; ?>" data-user-id="<?php echo $location['FK_user_ID']; ?>">
                                <?php echo htmlspecialchars($location['location_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="mt-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="create_new_location_chk" name="create_new_location" value="1" class="rounded"> 
                            <span>Create new location instead</span>
                        </label>
                    </div>

                    <!-- NEW LOCATION FIELDS with MAP -->
                    <div id="new-location-fields" style="display:none;" class="mt-3 space-y-3 p-3 bg-slate-50 rounded border">
                        <div>
                            <label class="block text-sm font-medium">New Location Name <span class="text-red-500">*</span></label>
                            <input type="text" name="new_location_name" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium">Address</label>
                            <textarea name="new_address" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                        </div>

                        <!-- MAP SECTION -->
                        <div>
                            <label class="block text-sm font-medium mb-1">Pin Location on Map <span class="text-red-500">*</span></label>
                            <div id="location-map" class="w-full h-64 rounded-lg border border-slate-300 z-0"></div>
                            <p class="text-xs text-slate-500 mt-1">Click on the map to set the location.</p>
                        </div>

                        <!-- Coordinate Inputs (Auto-filled) -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Latitude</label>
                                <input type="text" name="new_latitude" id="new_latitude" readonly class="w-full px-3 py-2 bg-slate-100 border rounded-lg text-sm cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Longitude</label>
                                <input type="text" name="new_longitude" id="new_longitude" readonly class="w-full px-3 py-2 bg-slate-100 border rounded-lg text-sm cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Type</label>
                        <select name="sensor_type" id="sensor_type" class="w-full px-3 py-2 border rounded-lg">
                            <option value="multisensor">Multisensor</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="status" id="status" class="w-full px-3 py-2 border rounded-lg">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeviceModal()" class="px-5 py-2 border rounded-lg hover:bg-slate-50">Cancel</button>
                    <button type="submit" id="modal-submit-btn" class="px-5 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="delete-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-sm p-6 shadow-2xl">
            <h3 class="text-xl font-bold text-red-600 mb-4">Confirm Deletion</h3>
            <p class="text-slate-700 mb-6">Are you sure you want to delete device: <strong id="delete-device-name"></strong>?</p>
            <form method="POST" action="devicemanagement.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="sensor_id" id="delete-sensor-id">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeleteConfirmation()" class="px-5 py-2 border rounded-lg">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const deviceModal = document.getElementById('device-modal');
        const deleteModal = document.getElementById('delete-modal');
        const locationSelectEl = document.getElementById('location_id');
        const newLocationFields = document.getElementById('new-location-fields');

        // Search filtering logic
        const searchInput = document.getElementById('device-search-input');
        const tableBody = document.getElementById('device-table-body');
        if(searchInput) {
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                const rows = tableBody.querySelectorAll('tr.device-row');
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

        function openDeviceModal(mode, deviceData = {}) {
            document.getElementById('device-form').reset();
            const submitBtn = document.getElementById('modal-submit-btn');
            const macSelect = document.getElementById('mac_address');
            
            if (mode === 'create') {
                document.getElementById('modal-title').textContent = 'Add New Device';
                document.getElementById('modal-action').value = 'create';
                document.getElementById('modal-sensor-id').value = '';
                macSelect.disabled = false;
                submitBtn.textContent = 'Add Device';
                newLocationFields.style.display = 'none';
                locationSelectEl.disabled = false;
            } else {
                document.getElementById('modal-title').textContent = 'Edit Device';
                document.getElementById('modal-action').value = 'update';
                document.getElementById('modal-sensor-id').value = deviceData.sensor_ID;
                
                if (![...macSelect.options].some(o => o.value === deviceData.esp_ip_unique)) {
                    const opt = document.createElement('option');
                    opt.value = deviceData.esp_ip_unique;
                    opt.text = deviceData.esp_ip_unique;
                    macSelect.add(opt);
                }
                macSelect.value = deviceData.esp_ip_unique;
                macSelect.disabled = true;

                document.getElementById('status').value = deviceData.status;
                submitBtn.textContent = 'Save Changes';
                
                if (deviceData.location_ID) {
                    locationSelectEl.value = deviceData.location_ID;
                }
                if (deviceData.owner_user_id) {
                     const userSearch = document.getElementById('user_search');
                     userSearch.value = `${deviceData.owner_user_id}::${deviceData.owner_first} ${deviceData.owner_last}`;
                     document.getElementById('user_id_selected').value = deviceData.owner_user_id;
                }
            }
            deviceModal.classList.remove('hidden');
        }

        // --- MAP LOGIC ---
        let map;
        let marker;

        function initMap() {
            // Default to Daet, Camarines Norte
            const defaultLat = 14.1145;
            const defaultLng = 122.9546;

            if (!map) {
                // Initialize map
                map = L.map('location-map').setView([defaultLat, defaultLng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Click event to add/move marker
                map.on('click', function(e) {
                    const lat = e.latlng.lat.toFixed(8);
                    const lng = e.latlng.lng.toFixed(8);
                    
                    updateMarker(lat, lng);
                });
            }
            
            // Fix for map rendering in hidden div
            setTimeout(() => {
                if (map) map.invalidateSize();
            }, 100);
        }

        function updateMarker(lat, lng) {
            // Update Inputs
            document.getElementById('new_latitude').value = lat;
            document.getElementById('new_longitude').value = lng;

            // Move or Create Marker
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }
        }

        function closeDeviceModal() { 
            deviceModal.classList.add('hidden'); 
            
            // Reset form UI logic
            document.getElementById('create_new_location_chk').checked = false;
            newLocationFields.style.display = 'none';
            locationSelectEl.disabled = false;
            
            // Clear marker and inputs
            if(marker) { map.removeLayer(marker); marker = null; }
            document.getElementById('new_latitude').value = '';
            document.getElementById('new_longitude').value = '';
        }
        
        function showDeleteConfirmation(id, mac) {
            document.getElementById('delete-sensor-id').value = id;
            document.getElementById('delete-device-name').textContent = mac;
            deleteModal.classList.remove('hidden');
        }
        function closeDeleteConfirmation() { deleteModal.classList.add('hidden'); }

        // Filter Locations by User
        document.getElementById('user_search').addEventListener('change', function() {
            const val = this.value;
            const parts = val.split('::');
            const userId = parts[0];
            document.getElementById('user_id_selected').value = userId;

            const options = locationSelectEl.querySelectorAll('option');
            options.forEach(opt => {
                if(!opt.value) return;
                const optUser = opt.getAttribute('data-user-id');
                if (userId && optUser !== userId) {
                    opt.style.display = 'none';
                } else {
                    opt.style.display = '';
                }
            });
            locationSelectEl.value = '';
        });

        // Toggle New Location Fields & Init Map
        document.getElementById('create_new_location_chk').addEventListener('change', function() {
            const isChecked = this.checked;
            newLocationFields.style.display = isChecked ? '' : 'none';
            locationSelectEl.disabled = isChecked;

            if (isChecked) {
                initMap();
            }
        });

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target == deviceModal) closeDeviceModal();
            if (event.target == deleteModal) closeDeleteConfirmation();
        }

        // Live-refresh discovered MACs and update #mac_address select
        async function refreshAvailableMacs() {
            try {
                const res = await fetch('devicemanagement.php?action=list_available_macs', { cache: 'no-store' });
                if (!res.ok) throw new Error('Network');
                const data = await res.json();
                const macSelect = document.getElementById('mac_address');
                const prev = macSelect.value;
                // clear existing options except placeholder
                for (let i = macSelect.options.length - 1; i >= 0; i--) {
                    if (macSelect.options[i].value === '') continue;
                    macSelect.remove(i);
                }
                if (data.macs && data.macs.length > 0) {
                    data.macs.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m;
                        opt.text = m;
                        macSelect.add(opt);
                    });
                    document.getElementById('available-msg').textContent = `✅ ${data.macs.length} discovered device(s)`;
                } else {
                    document.getElementById('available-msg').textContent = '⚠️ No unassigned devices found.';
                }
                // restore previous selection if still present
                try { macSelect.value = prev; } catch (e) {}
            } catch (err) {
                console.debug('refreshAvailableMacs error', err);
                document.getElementById('available-msg').textContent = 'Unable to fetch discovered devices';
            }
        }

        // Poll every 5 seconds
        // setInterval(refreshAvailableMacs, 5000);
        // Initial load
        refreshAvailableMacs();
    </script>
</body>
</html>
<?php $mysqli->close(); ?>