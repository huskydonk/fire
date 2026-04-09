<?php
// Include configuration
require_once 'db_config.php';

// --- Database Connection ---
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);

// Check connection
if ($mysqli->connect_error) {
    die("ERROR: Could not connect to the database. " . $mysqli->connect_error);
}

// --- CRUD Operations Handler ---
$message = '';
$message_type = ''; // 'success' or 'error'

$mac_address = $mysqli->real_escape_string($_POST['mac_address'] ?? '');

if (empty($mac_address)) {
    throw new Exception("Please select a device (MAC address).");
}

if ($action === 'create') {
    $check_sql = "SELECT sensor_ID FROM sensor WHERE esp_ip_unique = ?";
    $check_stmt = $mysqli->prepare($check_sql);
    $check_stmt->bind_param("s", $mac_address);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        throw new Exception("Device with this MAC address already exists.");
    }
    $check_stmt->close();

    // INSERT
    $sql = "INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isss", $location_id, $mac_address, "multisensor", $status);

}

if ($action === 'broadcast_mac') {
    $mac_address = $mysqli->real_escape_string($_GET['mac'] ?? '');
    
    if (empty($mac_address)) {
        http_response_code(400);
        echo json_encode(['error' => 'MAC required']);
        exit;
    }
    
    // Check if already registered
    $check = $mysqli->query("SELECT sensor_ID FROM sensor WHERE esp_ip_unique = '{$mac_address}'");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'registered']);
        exit;
    }
    
    // Log as available device (optional: if you want to track discovery)
    $insert = @$mysqli->query("INSERT IGNORE INTO sensor (esp_ip_unique, sensor_type, status) VALUES ('{$mac_address}', 'multisensor', 'pending')");
    
    echo json_encode(['status' => 'available', 'message' => 'Ready for setup']);
}

// ========== CHANGE #4: UPDATE query ==========
elseif ($action === 'update' && $sensor_id > 0) {
    $sql = "UPDATE sensor SET FK_location_ID = ?, esp_ip_unique = ?, sensor_type = ?, status = ? WHERE sensor_ID = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isssi", $location_id, $mac_address, $sensor_type, $status, $sensor_id);
    // OLD: esp_ip_unique instead of mac_address
}

// // Handle CREATE, UPDATE, DELETE actions
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $action = $_POST['action'] ?? '';
//     $sensor_id = (int)($_POST['sensor_id'] ?? 0);
//     $location_id = (int)($_POST['location_id'] ?? 0);
//     $user_id_selected = (int)($_POST['user_id_selected'] ?? 0);
//     $create_new_location = isset($_POST['create_new_location']) ? 1 : 0;
    
//     // New Location Fields
//     $new_location_name = $mysqli->real_escape_string($_POST['new_location_name'] ?? '');
//     $new_address = $mysqli->real_escape_string($_POST['new_address'] ?? '');
//     $new_latitude = $_POST['new_latitude'] ?? null;
//     $new_longitude = $_POST['new_longitude'] ?? null;
    
//     // Device Fields
//     $mac_address = $mysqli->real_escape_string($_POST['mac_address'] ?? '');
//     // Fallback if dropdown is disabled (edit mode)
//     if(empty($mac_address)) $mac_address = $mysqli->real_escape_string($_POST['esp_ip_unique'] ?? ''); 
    
//     $sensor_type = $mysqli->real_escape_string($_POST['sensor_type'] ?? 'multisensor');
//     $status = $mysqli->real_escape_string($_POST['status'] ?? 'inactive');
    
//     $capabilities_post = $_POST['capabilities'] ?? [];;

//     try {

        
//         if ($action === 'create' || $action === 'update') {
//             // Validate required fields: either an existing location must be selected or a new location provided
//             if (empty($esp_ip_unique)) {
//                 throw new Exception("Please fill in the ESP IP address.");
//             }
//             if (empty($location_id) && empty($create_new_location)) {
//                 throw new Exception("Please select an existing location or create a new one.");
//             }

//             // If the user requested to create a new location, insert it now and set $location_id to the new row
//             if (!empty($create_new_location)) {
//                 if (empty($new_location_name)) {
//                     throw new Exception('New location name is required when creating a location.');
//                 }
//                 if (empty($user_id_selected)) {
//                     throw new Exception('Please select a user to own the new location.');
//                 }

//                 $ins_sql = "INSERT INTO location (FK_user_ID, location_name, address, latitude, longitude, permission_level, date_created) VALUES (?, ?, ?, ?, ?, 'private', NOW())";
//                 $ins_stmt = $mysqli->prepare($ins_sql);
//                 if (!$ins_stmt) {
//                     throw new Exception('Failed to prepare location insert statement.');
//                 }
//                 // Use null for lat/long if empty to allow nullable columns
//                 $lat_param = $new_latitude !== '' ? $new_latitude : null;
//                 $lng_param = $new_longitude !== '' ? $new_longitude : null;
//                 $ins_stmt->bind_param('issss', $user_id_selected, $new_location_name, $new_address, $lat_param, $lng_param);
//                 if (!$ins_stmt->execute()) {
//                     $ins_stmt->close();
//                     throw new Exception('Failed to create new location: ' . $ins_stmt->error);
//                 }
//                 $location_id = $ins_stmt->insert_id;
//                 $ins_stmt->close();
//             }

//                 // Server-side validation: if a user was selected, ensure the chosen location belongs to that user
//                 if (!empty($user_id_selected)) {
//                     $loc_check_sql = "SELECT FK_user_ID FROM location WHERE location_ID = ? LIMIT 1";
//                     $loc_check_stmt = $mysqli->prepare($loc_check_sql);
//                     if (!$loc_check_stmt) {
//                         throw new Exception('Failed to prepare location validation statement.');
//                     }
//                     $loc_check_stmt->bind_param('i', $location_id);
//                     $loc_check_stmt->execute();
//                     $loc_check_stmt->bind_result($fk_user_id);
//                     if ($loc_check_stmt->fetch()) {
//                         if ((int)$fk_user_id !== (int)$user_id_selected) {
//                             $loc_check_stmt->close();
//                             throw new Exception('Selected location does not belong to the chosen user.');
//                         }
//                     } else {
//                         $loc_check_stmt->close();
//                         throw new Exception('Selected location not found.');
//                     }
//                     $loc_check_stmt->close();
//                 }

//                 if ($action === 'create') {
//                 // Check for existing ESP IP
//                 $check_sql = "SELECT sensor_ID FROM sensor WHERE esp_ip_unique = ?";
//                 $check_stmt = $mysqli->prepare($check_sql);
//                 $check_stmt->bind_param("s", $mac_address);
//                 $check_stmt->execute();
//                 $check_stmt->store_result();
//                 if ($check_stmt->num_rows > 0) {
//                     throw new Exception("Device with this ESP IP already exists.");
//                 }

//                 // $sql = "INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status) VALUES (?, ?, ?, ?)";
//                 // $stmt = $mysqli->prepare($sql);
//                 // $stmt->bind_param("isss", $location_id, $esp_ip_unique, $sensor_type, $status);
                
//                 if ($stmt->execute()) {
//                     $sensor_insert_id = $stmt->insert_id;
//                     // Persist capabilities (normalized join table)
//                     if (!empty($capabilities_post)) {
//                         foreach ($capabilities_post as $cap_code) {
//                             $cap_code = $mysqli->real_escape_string($cap_code);
//                             // find capability id
//                             $cstmt = $mysqli->prepare("SELECT capability_id FROM sensor_capability WHERE code = ? LIMIT 1");
//                             if ($cstmt) {
//                                 $cstmt->bind_param('s', $cap_code);
//                                 $cstmt->execute();
//                                 $cstmt->bind_result($cap_id);
//                                 if ($cstmt->fetch()) {
//                                     $cstmt->close();
//                                     $ins = $mysqli->prepare("INSERT IGNORE INTO sensor_has_capability (sensor_ID, capability_id) VALUES (?, ?)");
//                                     if ($ins) { $ins->bind_param('ii', $sensor_insert_id, $cap_id); $ins->execute(); $ins->close(); }
//                                 } else {
//                                     $cstmt->close();
//                                 }
//                             }
//                         }
//                     }
//                     // If this is a multisensor, attempt to insert initial readings for each capability.
//                     // This is defensive: only runs if a `sensor_reading` table exists and will try to map common column names.
//                     if ($sensor_type === 'multisensor') {
//                         // Determine which capabilities to insert: use posted capabilities or default set
//                         $caps_to_insert = !empty($capabilities_post) ? $capabilities_post : ['smoke', 'ir_fire', 'thermistor'];

//                         // Check if sensor_reading table exists
//                         $tbl_check = $mysqli->query("SHOW TABLES LIKE 'sensor_reading'");
//                         if ($tbl_check && $tbl_check->num_rows > 0) {
//                             $cols_res = $mysqli->query("DESCRIBE sensor_reading");
//                             $cols = [];
//                             if ($cols_res) {
//                                 while ($cr = $cols_res->fetch_assoc()) { $cols[] = $cr['Field']; }

//                                 // Helper to find a column name by candidate keywords
//                                 $find_col = function($candidates) use ($cols) {
//                                     foreach ($candidates as $cand) {
//                                         foreach ($cols as $col) {
//                                             if (stripos($col, $cand) !== false) return $col;
//                                         }
//                                     }
//                                     return null;
//                                 };

//                                 $sensor_col = $find_col(['sensor_id', 'sensor_ID', 'sensorId', 'sensor']);
//                                 $type_col = $find_col(['reading_type', 'type', 'capability', 'capability_code']);
//                                 $value_col = $find_col(['reading_value', 'value', 'reading', 'val']);
//                                 $ts_col = $find_col(['created_at', 'timestamp', 'ts', 'reading_ts', 'date_created']);

//                                 foreach ($caps_to_insert as $cap_code) {
//                                     $cap_esc = $mysqli->real_escape_string($cap_code);
//                                     $fields = [];
//                                     $values = [];
//                                     if ($sensor_col) { $fields[] = "`$sensor_col`"; $values[] = (int)$sensor_insert_id; }
//                                     if ($type_col) { $fields[] = "`$type_col`"; $values[] = "'{$cap_esc}'"; }
//                                     if ($value_col) { $fields[] = "`$value_col`"; $values[] = 0; }
//                                     if ($ts_col) { $fields[] = "`$ts_col`"; $values[] = "NOW()"; }

//                                     if (!empty($fields)) {
//                                         $sql_ins = "INSERT INTO sensor_reading (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
//                                         // Execute and ignore failures (table schemas may vary)
//                                         @$mysqli->query($sql_ins);
//                                     }
//                                 }
//                             }
//                         }
//                     }

//                 $message = "Device '{$mac_address}' created successfully.";
//                     $message_type = 'success';
//                 } else {
//                     throw new Exception("Error executing query: " . $stmt->error);
//                 }

//             } elseif ($action === 'update' && $sensor_id > 0) {
//                 $sql = "UPDATE sensor SET FK_location_ID = ?, esp_ip_unique = ?, sensor_type = ?, status = ? WHERE sensor_ID = ?";
//                 $stmt = $mysqli->prepare($sql);
//                 $stmt->bind_param("isssi", $location_id, $esp_ip_unique, $sensor_type, $status, $sensor_id);

//                 if ($stmt->execute()) {
//                     // Update capabilities: replace existing with new set
//                     $del = $mysqli->prepare("DELETE FROM sensor_has_capability WHERE sensor_ID = ?");
//                     if ($del) { $del->bind_param('i', $sensor_id); $del->execute(); $del->close(); }
//                     if (!empty($capabilities_post)) {
//                         foreach ($capabilities_post as $cap_code) {
//                             $cap_code = $mysqli->real_escape_string($cap_code);
//                             $cstmt = $mysqli->prepare("SELECT capability_id FROM sensor_capability WHERE code = ? LIMIT 1");
//                             if ($cstmt) {
//                                 $cstmt->bind_param('s', $cap_code);
//                                 $cstmt->execute();
//                                 $cstmt->bind_result($cap_id);
//                                 if ($cstmt->fetch()) {
//                                     $cstmt->close();
//                                     $ins = $mysqli->prepare("INSERT IGNORE INTO sensor_has_capability (sensor_ID, capability_id) VALUES (?, ?)");
//                                     if ($ins) { $ins->bind_param('ii', $sensor_id, $cap_id); $ins->execute(); $ins->close(); }
//                                 } else { $cstmt->close(); }
//                             }
//                         }
//                     }
//                     $message = "Device ID: {$sensor_id} updated successfully.";
//                     $message_type = 'success';
//                 } else {
//                     throw new Exception("Error executing query: " . $stmt->error);
//                 }
//             }

//         } elseif ($action === 'delete' && $sensor_id > 0) {
//             $sql = "DELETE FROM sensor WHERE sensor_ID = ?";
//             $stmt = $mysqli->prepare($sql);
//             $stmt->bind_param("i", $sensor_id);

//             if ($stmt->execute()) {
//                 $message = "Device ID: {$sensor_id} deleted successfully.";
//                 $message_type = 'success';
//             } else {
//                 throw new Exception("Error executing query: " . $stmt->error);
//             }
//         }
//     } catch (Exception $e) {
//         $message = "Operation failed: " . $e->getMessage();
//         $message_type = 'error';
//     }
// }

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
        // Validate MAC address early
        if (empty($mac_address)) {
            throw new Exception("Please select a device (MAC address).");
        }

        if ($action === 'create' || $action === 'update') {
            // Validate required fields: either an existing location must be selected or a new location provided
            if (empty($mac_address)) {
                throw new Exception("Please fill in the MAC address.");
            }
            if (empty($location_id) && empty($create_new_location)) {
                throw new Exception("Please select an existing location or create a new one.");
            }

            // If the user requested to create a new location, insert it now and set $location_id to the new row
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
                // Use null for lat/long if empty to allow nullable columns
                $lat_param = $new_latitude !== '' ? $new_latitude : null;
                $lng_param = $new_longitude !== '' ? $new_longitude : null;
                $ins_stmt->bind_param('issss', $user_id_selected, $new_location_name, $new_address, $lat_param, $lng_param);
                if (!$ins_stmt->execute()) {
                    $ins_stmt->close();
                    throw new Exception('Failed to create new location: ' . $ins_stmt->error);
                }
                $location_id = $ins_stmt->insert_id;
                $ins_stmt->close();
            }

            // Server-side validation: if a user was selected, ensure the chosen location belongs to that user
            if (!empty($user_id_selected)) {
                $loc_check_sql = "SELECT FK_user_ID FROM location WHERE location_ID = ? LIMIT 1";
                $loc_check_stmt = $mysqli->prepare($loc_check_sql);
                if (!$loc_check_stmt) {
                    throw new Exception('Failed to prepare location validation statement.');
                }
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

            if ($action === 'create') {
                // Check for existing MAC address
                $check_sql = "SELECT sensor_ID FROM sensor WHERE esp_ip_unique = ?";
                $check_stmt = $mysqli->prepare($check_sql);
                $check_stmt->bind_param("s", $mac_address);
                $check_stmt->execute();
                $check_stmt->store_result();
                if ($check_stmt->num_rows > 0) {
                    $check_stmt->close();
                    throw new Exception("Device with this MAC address already exists.");
                }
                $check_stmt->close();

                // INSERT
                $sql = "INSERT INTO sensor (FK_location_ID, esp_ip_unique, sensor_type, status) VALUES (?, ?, ?, ?)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("isss", $location_id, $mac_address, $sensor_type, $status);
                
                if ($stmt->execute()) {
                    $sensor_insert_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Persist capabilities (normalized join table)
                    if (!empty($capabilities_post)) {
                        foreach ($capabilities_post as $cap_code) {
                            $cap_code = $mysqli->real_escape_string($cap_code);
                            // find capability id
                            $cstmt = $mysqli->prepare("SELECT capability_id FROM sensor_capability WHERE code = ? LIMIT 1");
                            if ($cstmt) {
                                $cstmt->bind_param('s', $cap_code);
                                $cstmt->execute();
                                $cstmt->bind_result($cap_id);
                                if ($cstmt->fetch()) {
                                    $cstmt->close();
                                    $ins = $mysqli->prepare("INSERT IGNORE INTO sensor_has_capability (sensor_ID, capability_id) VALUES (?, ?)");
                                    if ($ins) { 
                                        $ins->bind_param('ii', $sensor_insert_id, $cap_id); 
                                        $ins->execute(); 
                                        $ins->close(); 
                                    }
                                } else {
                                    $cstmt->close();
                                }
                            }
                        }
                    }
                    
                    // If this is a multisensor, attempt to insert initial readings for each capability.
                    if ($sensor_type === 'multisensor') {
                        // Determine which capabilities to insert: use posted capabilities or default set
                        $caps_to_insert = !empty($capabilities_post) ? $capabilities_post : ['smoke', 'ir_fire', 'thermistor'];

                        // Check if sensor_reading table exists
                        $tbl_check = $mysqli->query("SHOW TABLES LIKE 'sensor_reading'");
                        if ($tbl_check && $tbl_check->num_rows > 0) {
                            $cols_res = $mysqli->query("DESCRIBE sensor_reading");
                            $cols = [];
                            if ($cols_res) {
                                while ($cr = $cols_res->fetch_assoc()) { 
                                    $cols[] = $cr['Field']; 
                                }

                                // Helper to find a column name by candidate keywords
                                $find_col = function($candidates) use ($cols) {
                                    foreach ($candidates as $cand) {
                                        foreach ($cols as $col) {
                                            if (stripos($col, $cand) !== false) return $col;
                                        }
                                    }
                                    return null;
                                };

                                $sensor_col = $find_col(['sensor_id', 'sensor_ID', 'sensorId', 'sensor']);
                                $type_col = $find_col(['reading_type', 'type', 'capability', 'capability_code']);
                                $value_col = $find_col(['reading_value', 'value', 'reading', 'val']);
                                $ts_col = $find_col(['created_at', 'timestamp', 'ts', 'reading_ts', 'date_created']);

                                foreach ($caps_to_insert as $cap_code) {
                                    $cap_esc = $mysqli->real_escape_string($cap_code);
                                    $fields = [];
                                    $values = [];
                                    if ($sensor_col) { $fields[] = "`$sensor_col`"; $values[] = (int)$sensor_insert_id; }
                                    if ($type_col) { $fields[] = "`$type_col`"; $values[] = "'{$cap_esc}'"; }
                                    if ($value_col) { $fields[] = "`$value_col`"; $values[] = 0; }
                                    if ($ts_col) { $fields[] = "`$ts_col`"; $values[] = "NOW()"; }

                                    if (!empty($fields)) {
                                        $sql_ins = "INSERT INTO sensor_reading (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
                                        // Execute and ignore failures (table schemas may vary)
                                        @$mysqli->query($sql_ins);
                                    }
                                }
                            }
                        }
                    }

                    $message = "Device '{$mac_address}' created successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }

            } elseif ($action === 'update' && $sensor_id > 0) {
                $sql = "UPDATE sensor SET FK_location_ID = ?, esp_ip_unique = ?, sensor_type = ?, status = ? WHERE sensor_ID = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("isssi", $location_id, $mac_address, $sensor_type, $status, $sensor_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // Update capabilities: replace existing with new set
                    $del = $mysqli->prepare("DELETE FROM sensor_has_capability WHERE sensor_ID = ?");
                    if ($del) { 
                        $del->bind_param('i', $sensor_id); 
                        $del->execute(); 
                        $del->close(); 
                    }
                    
                    if (!empty($capabilities_post)) {
                        foreach ($capabilities_post as $cap_code) {
                            $cap_code = $mysqli->real_escape_string($cap_code);
                            $cstmt = $mysqli->prepare("SELECT capability_id FROM sensor_capability WHERE code = ? LIMIT 1");
                            if ($cstmt) {
                                $cstmt->bind_param('s', $cap_code);
                                $cstmt->execute();
                                $cstmt->bind_result($cap_id);
                                if ($cstmt->fetch()) {
                                    $cstmt->close();
                                    $ins = $mysqli->prepare("INSERT IGNORE INTO sensor_has_capability (sensor_ID, capability_id) VALUES (?, ?)");
                                    if ($ins) { 
                                        $ins->bind_param('ii', $sensor_id, $cap_id); 
                                        $ins->execute(); 
                                        $ins->close(); 
                                    }
                                } else { 
                                    $cstmt->close(); 
                                }
                            }
                        }
                    }
                    $message = "Device ID: {$sensor_id} updated successfully.";
                    $message_type = 'success';
                } else {
                    throw new Exception("Error executing query: " . $stmt->error);
                }
            }

        } elseif ($action === 'delete' && $sensor_id > 0) {
            $sql = "DELETE FROM sensor WHERE sensor_ID = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $sensor_id);

            if ($stmt->execute()) {
                $stmt->close();
                $message = "Device ID: {$sensor_id} deleted successfully.";
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
$current_page = (int)($_GET['page'] ?? 1);
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * RECORDS_PER_PAGE;

// Get total number of records
$total_sql = "SELECT COUNT(sensor_ID) AS total FROM sensor";
$total_result = $mysqli->query($total_sql);
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / RECORDS_PER_PAGE);

if ($total_records > 0 && $current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * RECORDS_PER_PAGE;
}

$read_sql = "SELECT s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, s.installed_at,
                    l.location_ID, l.FK_user_ID, l.location_name, l.address,
                    u.user_ID AS owner_user_id, u.first_name AS owner_first, u.last_name AS owner_last
             FROM sensor s
             LEFT JOIN location l ON s.FK_location_ID = l.location_ID
             LEFT JOIN users u ON l.FK_user_ID = u.user_ID
            ORDER BY s.sensor_ID ASC
            LIMIT ? OFFSET ?";

$limit = RECORDS_PER_PAGE;
$stmt = $mysqli->prepare($read_sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$devices = $result->fetch_all(MYSQLI_ASSOC);

// Fetch capabilities for listed devices and attach codes array to each device
$sensor_ids = array_column($devices, 'sensor_ID');
if (!empty($sensor_ids)) {
    $in = implode(',', array_map('intval', $sensor_ids));
    $cap_q = "SELECT shc.sensor_ID, sc.code FROM sensor_has_capability shc JOIN sensor_capability sc ON shc.capability_id = sc.capability_id WHERE shc.sensor_ID IN ($in)";
    $cap_res = $mysqli->query($cap_q);
    $cap_map = [];
    if ($cap_res) {
        while ($r = $cap_res->fetch_assoc()) {
            $cap_map[$r['sensor_ID']][] = $r['code'];
        }
    }
    foreach ($devices as &$d) {
        $d['capabilities'] = $cap_map[$d['sensor_ID']] ?? [];
    }
    unset($d);
}

// Fetch all locations for dropdown (include owner FK)
$locations_sql = "SELECT location_ID, FK_user_ID, location_name, address FROM location ORDER BY location_name";
$locations_result = $mysqli->query($locations_sql);
$locations = $locations_result->fetch_all(MYSQLI_ASSOC);

// Fetch users for searchable selector
$users_sql = "SELECT user_ID, first_name, last_name, email, phone_number FROM users ORDER BY last_name, first_name";
$users_result = $mysqli->query($users_sql);
$users = $users_result ? $users_result->fetch_all(MYSQLI_ASSOC) : [];

$available_macs = [];
$mac_sql = "SELECT DISTINCT esp_ip_unique FROM sensor ORDER BY esp_ip_unique DESC LIMIT 100";
$mac_result = $mysqli->query($mac_sql);
if ($mac_result) {
    $available_macs = $mac_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch capability list (if table exists)
$capabilities_master = [];
$cap_sql = "SELECT capability_id, code, label FROM sensor_capability ORDER BY capability_id";
$cap_res = $mysqli->query($cap_sql);
if ($cap_res) {
    $capabilities_master = $cap_res->fetch_all(MYSQLI_ASSOC);
}

$start_record = min($total_records, $offset + 1);
$end_record = min($total_records, $offset + RECORDS_PER_PAGE);

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - BFP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .device-row {
            animation: fadeIn 0.4s ease-out forwards;
        }
        
        <?php for ($i = 0; $i < RECORDS_PER_PAGE; $i++): ?>
        .device-row:nth-child(<?php echo $i + 1; ?>) { animation-delay: <?php echo 0.1 + ($i * 0.1); ?>s; }
        <?php endfor; ?>

        tbody tr:hover {
            background: #f8fafc;
            transform: translateX(4px);
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .modal-overlay {
            z-index: 1000;
        }

        .message-box {
            animation: fadeIn 0.5s ease forwards;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8 !important;
            opacity: 1;
        }

        input:focus,
        select:focus,
        textarea:focus {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #1e293b !important;
            -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        label, p, span, div, td, th {
            color: inherit;
        }

        table {
            color: #1e293b;
        }

        tbody tr {
            background-color: #ffffff;
        }

        .modal-overlay .bg-white * {
            color: #1e293b;
        }

        /* Scrollable table container with sticky header for desktop */
        .table-scroll {
            max-height: 46vh;
            overflow: auto;
        }
        .table-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #f8fafc; /* same as header row background */
            backdrop-filter: blur(4px);
        }

        .modal-overlay input,
        .modal-overlay select,
        .modal-overlay textarea {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        #device-search-input {
            transition: all 0.3s ease;
        }

        #device-search-input:focus {
            width: 100%;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
        }

        mark {
            background-color: #fef08a;
            padding: 2px 4px;
            border-radius: 2px;
            font-weight: 500;
        }

        .clear-search-btn {
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            background: transparent;
            border: none;
        }

        .clear-search-btn:hover {
            background-color: rgba(148, 163, 184, 0.1);
        }

        .device-row,
        .mobile-card {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .device-row[style*="display: none"],
        .mobile-card[style*="display: none"] {
            opacity: 0;
            transform: scale(0.95);
        }

        #device-search-input:not(:placeholder-shown) {
            border-color: #ef4444;
            background-color: #fef2f2;
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
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
        <script>
            setTimeout(() => {
                const msgBox = document.getElementById('message-box');
                if (msgBox) msgBox.remove();
            }, 5000);
        </script>
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
                    <input 
                        type="text" 
                        id="device-search-input"
                        placeholder="Search devices..." 
                        class="pl-10 pr-10 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent w-full sm:w-80 transition-all"
                        autocomplete="off"
                    >
                    <svg class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 transform -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Desktop Table View (scrollable with sticky header) -->
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
                                <div class="font-medium"><?php echo htmlspecialchars($device['location_name'] ?? 'No Location'); ?></div>
                                <?php if (!empty($device['address'])): ?>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($device['address']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-6">
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full 
                                    <?php echo $device['sensor_type'] === 'multisensor' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($device['sensor_type'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6">
                                <span class="status-badge px-2 py-0.5 text-xs font-medium rounded-full 
                                    <?php 
                                        $status_class = [
                                            'active' => 'bg-green-100 text-green-800', 
                                            'inactive' => 'bg-gray-100 text-gray-800',
                                            'offline' => 'bg-yellow-100 text-yellow-800',
                                            'error' => 'bg-red-100 text-red-800'
                                        ][$device['status']] ?? 'bg-gray-100 text-gray-800';
                                        echo $status_class;
                                    ?>">
                                    <span class="status-dot <?php 
                                        $dot_class = [
                                            'active' => 'bg-green-500', 
                                            'inactive' => 'bg-gray-500',
                                            'offline' => 'bg-yellow-500',
                                            'error' => 'bg-red-500'
                                        ][$device['status']] ?? 'bg-gray-500';
                                        echo $dot_class;
                                    ?>"></span>
                                    <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-6 text-sm text-slate-500"><?php echo date('M d, Y', strtotime($device['installed_at'])); ?></td>
                            <td class="py-3 px-6 flex items-center justify-center gap-1">
                                <button 
                                    onclick='openDeviceModal("edit", <?php echo json_encode($device); ?>)'
                                    class="action-btn p-2 text-orange-600 rounded-full hover:bg-orange-100 transition"
                                    title="Edit Device"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                </button>
                                <button 
                                    onclick="showDeleteConfirmation(<?php echo $device['sensor_ID']; ?>, '<?php echo htmlspecialchars($device['esp_ip_unique'], ENT_QUOTES); ?>')"
                                    class="action-btn p-2 text-red-600 rounded-full hover:bg-red-100 transition"
                                    title="Delete Device"
                                >
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-slate-500">No devices found.</td>
                        </tr>
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
                                <div class="text-xs text-slate-500 uppercase font-semibold mb-1">Device ID</div>
                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($device['sensor_ID']); ?></div>
                            </div>
                            <span class="status-badge px-2 py-0.5 text-xs font-medium rounded-full 
                                <?php 
                                    $status_class = [
                                        'active' => 'bg-green-100 text-green-800', 
                                        'inactive' => 'bg-gray-100 text-gray-800',
                                        'offline' => 'bg-yellow-100 text-yellow-800',
                                        'error' => 'bg-red-100 text-red-800'
                                    ][$device['status']] ?? 'bg-gray-100 text-gray-800';
                                    echo $status_class;
                                ?>">
                                <span class="status-dot <?php 
                                    $dot_class = [
                                        'active' => 'bg-green-500', 
                                        'inactive' => 'bg-gray-500',
                                        'offline' => 'bg-yellow-500',
                                        'error' => 'bg-red-500'
                                    ][$device['status']] ?? 'bg-gray-500';
                                    echo $dot_class;
                                ?>"></span>
                                <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                            </span>
                        </div>
                        <div class="space-y-2 mb-3">
                            <div>
                                <div class="text-xs text-slate-500">Mac Address</div>
                                <div class="text-sm text-slate-700 font-mono"><?php echo htmlspecialchars($device['esp_ip_unique']); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Location</div>
                                <div class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($device['location_name'] ?? 'No Location'); ?></div>
                                <?php if (!empty($device['address'])): ?>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($device['address']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-4">
                                <div>
                                    <div class="text-xs text-slate-500">Type</div>
                                    <div class="text-sm text-slate-700"><?php echo htmlspecialchars(ucfirst($device['sensor_type'])); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Installed At</div>
                                    <div class="text-sm text-slate-700"><?php echo date('M d, Y', strtotime($device['installed_at'])); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-3 border-t border-slate-200">
                            <button 
                                onclick='openDeviceModal("edit", <?php echo json_encode($device); ?>)'
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                Edit
                            </button>
                            <button 
                                onclick="showDeleteConfirmation(<?php echo $device['sensor_ID']; ?>, '<?php echo htmlspecialchars($device['esp_ip_unique'], ENT_QUOTES); ?>')"
                                class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-red-50 hover:text-red-600 transition"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-8 text-slate-500 bg-slate-50 rounded-xl p-4 border border-slate-200">No devices found. Try adding a new device!</div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-slate-600">
                <?php if ($total_records > 0): ?>
                    Showing <span class="font-semibold"><?php echo $start_record; ?></span> to <span class="font-semibold"><?php echo $end_record; ?></span> of <span class="font-semibold"><?php echo $total_records; ?></span> results
                <?php else: ?>
                    No results to show.
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2">
                <a href="<?php echo $current_page > 1 ? '?page=' . ($current_page - 1) : '#'; ?>" 
                   class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition <?php echo $current_page === 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                
                <?php 
                    $range = 2;
                    $start = max(1, $current_page - $range);
                    $end = min($total_pages, $current_page + $range);
                    if ($start > 1) { echo '<span class="text-slate-400">...</span>'; }
                    for ($i = $start; $i <= $end; $i++):
                        $active_class = $i === $current_page ? 'bg-red-500 text-white' : 'text-slate-600 hover:bg-slate-100';
                ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="w-10 h-10 <?php echo $active_class; ?> rounded-lg font-medium flex items-center justify-center transition">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; if ($end < $total_pages) { echo '<span class="text-slate-400">...</span>'; } ?>

                <a href="<?php echo $current_page < $total_pages ? '?page=' . ($current_page + 1) : '#'; ?>" 
                   class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition <?php echo $current_page === $total_pages || $total_records === 0 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Device Modal (Create/Edit) -->
    <div id="device-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white rounded-xl w-full max-w-lg p-6 shadow-2xl transform scale-100 transition-transform duration-300" onclick="event.stopPropagation()">
            <h2 id="modal-title" class="text-2xl font-bold text-slate-800 mb-6">Add New Device</h2>
            
            <form id="device-form" method="POST" action="devicemanagement.php">
                <input type="hidden" name="action" id="modal-action">
                <input type="hidden" name="sensor_id" id="modal-sensor-id">

                <div class="mb-4">
                    <label for="mac_address" class="block text-sm font-medium text-slate-700 mb-1">Device (MAC Address) <span class="text-red-500">*</span></label>
                    <select id="mac_address" name="mac_address" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">-- Select a Device --</option>
                        <?php foreach ($available_macs as $mac): ?>
                            <option value="<?php echo htmlspecialchars($mac['mac_address']); ?>">
                                <?php echo htmlspecialchars($mac['mac_address']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">💡 Power on the ESP32 device to detect its MAC address automatically</p>
                </div>

                <div class="mb-4">
                    <label for="user_search" class="block text-sm font-medium text-slate-700 mb-1">User (search) <span class="text-red-500">*</span></label>
                    <input list="users-list" id="user_search" placeholder="Search user by name or email" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 mb-2">
                    <datalist id="users-list">
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_ID'] . '::' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' | ' . ($user['email'] ?? '')); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="user_id_selected" id="user_id_selected">

                    <label for="location_id" class="block text-sm font-medium text-slate-700 mb-1">Location <span class="text-red-500">*</span></label>
                    <select id="location_id" name="location_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['location_ID']; ?>" data-user-id="<?php echo $location['FK_user_ID']; ?>">
                                <?php echo htmlspecialchars($location['location_name']); ?>
                                <?php if (!empty($location['address'])): ?>
                                    - <?php echo htmlspecialchars($location['address']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-3">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="create_new_location_chk" name="create_new_location" value="1" class="rounded"> 
                            <span>Create new location instead</span>
                        </label>
                    </div>

                    <div id="new-location-fields" style="display:none;" class="mt-3 space-y-3">
                        <div>
                            <label for="new_location_name" class="block text-sm font-medium text-slate-700 mb-1">New Location Name <span class="text-red-500">*</span></label>
                            <input type="text" id="new_location_name" name="new_location_name" placeholder="e.g., Customer Home" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label for="new_address" class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                            <textarea id="new_address" name="new_address" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Street, Barangay, City"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="new_latitude" class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                                <input type="text" id="new_latitude" name="new_latitude" placeholder="14.123456" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                            <div>
                                <label for="new_longitude" class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                                <input type="text" id="new_longitude" name="new_longitude" placeholder="121.012345" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                        </div>
                    </div>
                    <?php if (empty($locations)): ?>
                        <p class="text-xs text-orange-600 mt-1">⚠️ No locations available. Please add a location first.</p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="sensor_type" class="block text-sm font-medium text-slate-700 mb-1">Sensor Type <span class="text-red-500">*</span></label>
                        <select id="sensor_type" name="sensor_type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="multisensor">Multisensor</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="status" name="status" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            <option value="inactive">Inactive</option>
                            <option value="active">Active</option>
                            <option value="offline">Offline</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                </div>

                <!-- Capabilities UI removed: multisensor is the only sensor_type and server will use the default capabilities to create readings -->

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeviceModal()" class="px-5 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" id="modal-submit-btn" class="px-5 py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition">
                        Create Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal-overlay hidden fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white rounded-xl w-full max-w-sm p-6 shadow-2xl transform scale-100 transition-transform duration-300" onclick="event.stopPropagation()">
            <h3 class="text-xl font-bold text-red-600 mb-4">Confirm Device Deletion</h3>
            <p class="text-slate-700 mb-6">Are you sure you want to delete device: <strong id="delete-device-name"></strong>?</p>
            
            <form method="POST" action="devicemanagement.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="sensor_id" id="delete-sensor-id">
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeDeleteConfirmation()" class="px-5 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition">
                        Delete Device
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Modal Logic ---
        const deviceModal = document.getElementById('device-modal');
        const deleteModal = document.getElementById('delete-modal');

        function openDeviceModal(mode, deviceData = {}) {
            const title = document.getElementById('modal-title');
            const actionInput = document.getElementById('modal-action');
            const sensorIdInput = document.getElementById('modal-sensor-id');
            const submitBtn = document.getElementById('modal-submit-btn');

            document.getElementById('device-form').reset();

                if (mode === 'create') {
                title.textContent = 'Add New Device';
                actionInput.value = 'create';
                sensorIdInput.value = '';
                submitBtn.textContent = 'Create Device';
                } else if (mode === 'edit') {
                title.textContent = `Edit Device: ${deviceData.esp_ip_unique}`;
                actionInput.value = 'update';
                sensorIdInput.value = deviceData.sensor_ID;
                submitBtn.textContent = 'Save Changes';
                
                document.getElementById('mac_address').value = deviceData.mac_address;
                document.getElementById('sensor_type').value = deviceData.sensor_type;
                document.getElementById('status').value = deviceData.status;
                
                // Set user search + location if available from deviceData
                const userInput = document.getElementById('user_search');
                const userIdInput = document.getElementById('user_id_selected');
                if (deviceData.owner_user_id) {
                    const userLabel = `${deviceData.owner_user_id}::${deviceData.owner_first} ${deviceData.owner_last}`;
                    userInput.value = userLabel;
                    userIdInput.value = deviceData.owner_user_id;
                }

                // Set capabilities checkboxes if provided
                if (Array.isArray(deviceData.capabilities)) {
                    deviceData.capabilities.forEach(code => {
                        const cb = document.querySelector(`input[name="capabilities[]"][value="${code}"]`);
                        if (cb) cb.checked = true;
                    });
                }

                // Find and set location - search through options to find matching location
                const locationSelect = document.getElementById('location_id');
                const locationOptions = locationSelect.querySelectorAll('option');
                locationOptions.forEach(option => {
                    if (option.textContent.includes(deviceData.location_name)) {
                        locationSelect.value = option.value;
                    }
                });
                // Filter locations to the selected user (if set)
                filterLocationsByUser(userIdInput.value || null);
            }

            deviceModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeDeviceModal() {
            deviceModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function showDeleteConfirmation(sensorId, espIp) {
            document.getElementById('delete-sensor-id').value = sensorId;
            document.getElementById('delete-device-name').textContent = espIp;
            deleteModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeDeleteConfirmation() {
            deleteModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        deviceModal.addEventListener('click', (e) => {
            if (e.target === deviceModal) closeDeviceModal();
        });

        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) closeDeleteConfirmation();
        });

        // --- Search Functionality ---
        const searchInput = document.getElementById('device-search-input');
        const deviceTableBody = document.getElementById('device-table-body');
        const mobileCardsContainer = document.querySelector('.md\\:hidden.space-y-4');

        let allDevices = [];

        function initializeDeviceData() {
            const tableRows = deviceTableBody.querySelectorAll('tr.device-row');
            allDevices = Array.from(tableRows).map(row => {
                const cells = row.querySelectorAll('td');
                return {
                    element: row,
                    deviceId: cells[0]?.textContent.trim() || '',
                    espIp: cells[1]?.textContent.trim() || '',
                    location: cells[2]?.textContent.trim() || '',
                    type: cells[3]?.textContent.trim() || '',
                    status: cells[4]?.textContent.trim() || ''
                };
            });

            const mobileCards = mobileCardsContainer?.querySelectorAll('.mobile-card') || [];
            allDevices.forEach((device, index) => {
                if (mobileCards[index]) {
                    device.mobileElement = mobileCards[index];
                }
            });
        }

        function performSearch(searchTerm) {
            const term = searchTerm.toLowerCase().trim();
            
            if (!term) {
                showAllDevices();
                return;
            }

            let visibleCount = 0;

            allDevices.forEach(device => {
                const matchFound = 
                    device.deviceId.toLowerCase().includes(term) ||
                    device.espIp.toLowerCase().includes(term) ||
                    device.location.toLowerCase().includes(term) ||
                    device.type.toLowerCase().includes(term) ||
                    device.status.toLowerCase().includes(term);

                if (matchFound) {
                    device.element.style.display = '';
                    if (device.mobileElement) {
                        device.mobileElement.style.display = '';
                    }
                    visibleCount++;
                } else {
                    device.element.style.display = 'none';
                    if (device.mobileElement) {
                        device.mobileElement.style.display = 'none';
                    }
                }
            });

            updateNoResultsMessage(visibleCount, term);
        }

        function showAllDevices() {
            allDevices.forEach(device => {
                device.element.style.display = '';
                if (device.mobileElement) {
                    device.mobileElement.style.display = '';
                }
            });
            removeNoResultsMessage();
        }

        function updateNoResultsMessage(visibleCount, searchTerm) {
            removeNoResultsMessage();

            if (visibleCount === 0) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="7" class="text-center py-8 text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <p class="font-medium">No devices found matching "${searchTerm}"</p>
                        <p class="text-sm text-slate-400 mt-1">Try a different search term</p>
                    </td>
                `;
                deviceTableBody.appendChild(noResultsRow);

                if (mobileCardsContainer) {
                    const noResultsCard = document.createElement('div');
                    noResultsCard.id = 'no-results-card';
                    noResultsCard.className = 'text-center py-8 text-slate-500 bg-slate-50 rounded-xl p-4 border border-slate-200';
                    noResultsCard.innerHTML = `
                        <svg class="w-12 h-12 mx-auto mb-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <p class="font-medium">No devices found matching "${searchTerm}"</p>
                        <p class="text-sm text-slate-400 mt-1">Try a different search term</p>
                    `;
                    mobileCardsContainer.appendChild(noResultsCard);
                }
            }
        }

        function removeNoResultsMessage() {
            document.getElementById('no-results-row')?.remove();
            document.getElementById('no-results-card')?.remove();
        }

        if (searchInput) {
            initializeDeviceData();

            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performSearch(e.target.value);
                }, 300);
            });

            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    performSearch(e.target.value);
                }
            });

            searchInput.addEventListener('focus', function() {
                if (!this.parentElement.querySelector('.clear-search-btn')) {
                    const clearBtn = document.createElement('button');
                    clearBtn.className = 'clear-search-btn absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600 transition';
                    clearBtn.type = 'button';
                    clearBtn.innerHTML = `
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    `;
                    clearBtn.style.display = this.value ? 'block' : 'none';
                    clearBtn.addEventListener('click', () => {
                        searchInput.value = '';
                        performSearch('');
                        clearBtn.style.display = 'none';
                        searchInput.focus();
                    });
                    this.parentElement.appendChild(clearBtn);
                }
            });

            searchInput.addEventListener('input', function() {
                const clearBtn = this.parentElement.querySelector('.clear-search-btn');
                if (clearBtn) {
                    clearBtn.style.display = this.value ? 'block' : 'none';
                }
            });
        }

        // --- User selector and location filtering ---
        const userSearchInput = document.getElementById('user_search');
        const userIdHidden = document.getElementById('user_id_selected');
        const locationSelectEl = document.getElementById('location_id');

        function filterLocationsByUser(userId) {
            const options = locationSelectEl.querySelectorAll('option');
            options.forEach(opt => {
                // always keep the empty option
                if (!opt.value) return opt.style.display = '';
                const optUser = opt.getAttribute('data-user-id');
                if (!userId) {
                    opt.style.display = '';
                } else if (optUser === String(userId)) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
            // if currently selected option was filtered out, clear selection
            if (locationSelectEl.value) {
                const selOpt = locationSelectEl.querySelector('option[value="' + locationSelectEl.value + '"]');
                if (selOpt && selOpt.style.display === 'none') locationSelectEl.value = '';
            }
        }

        if (userSearchInput) {
            userSearchInput.addEventListener('change', function() {
                const val = this.value || '';
                // expected format: id::Full Name | email
                const parts = val.split('::');
                const id = parts[0] && parts[0].match(/^\d+$/) ? parts[0] : '';
                userIdHidden.value = id;
                filterLocationsByUser(id || null);
            });

            // Reset filter when cleared
            userSearchInput.addEventListener('input', function(e){
                if (!this.value) {
                    userIdHidden.value = '';
                    filterLocationsByUser(null);
                }
            });
        }

        // Toggle new location fields
        const createNewLocationChk = document.getElementById('create_new_location_chk');
        const newLocationFields = document.getElementById('new-location-fields');
        if (createNewLocationChk) {
            createNewLocationChk.addEventListener('change', function() {
                if (this.checked) {
                    newLocationFields.style.display = '';
                    // disable the location select so user won't submit an existing location
                    locationSelectEl.disabled = true;
                } else {
                    newLocationFields.style.display = 'none';
                    locationSelectEl.disabled = false;
                }
            });
        }
    </script>
</body>
</html>
<?php
$mysqli->close();
?>