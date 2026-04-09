<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!isset($_SESSION['user_id'])) {
    
    $is_logged_in = false;
    $current_user_role = 'bfp_assigned_at_desk'; // Default value
    header('Location: loginweb.php');
    exit;
} else {
    // If logged in, skip the login screen
    $is_logged_in = true;
    $current_user_role = $_SESSION['user_role'] ?? 'bfp_assigned_at_desk'; // Store role in a variable for reference
}


$first_name = htmlspecialchars($_SESSION['first_name'] ?? 'John Doe');
$email = htmlspecialchars($_SESSION['email'] ?? 'No Email');
$role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$encpass = htmlspecialchars($_SESSION['enc_password'] ?? 'N/A');
$passHash = password_hash($encpass, PASSWORD_DEFAULT);

// Server-side metrics fallback (show counts without requiring API/session)
$metrics = [
    'total_users' => 0,
    'active_devices' => 0,
    'active_alerts' => 0,
    'monthly_incidents' => 0
];
$latestInc = null;
$activeDevices = [];

try {
    // Ensure the connection variable is available (assuming db_config.php defines $mPdo)
    global $mPdo; 
    
    if (isset($mPdo)) {
        // Retrieve Metrics
        $metrics['total_users'] = (int)$mPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $metrics['active_devices'] = (int)$mPdo->query("SELECT COUNT(*) FROM sensor WHERE status = 'active'")->fetchColumn();
        $metrics['active_alerts'] = (int)$mPdo->query("SELECT COUNT(*) FROM incident_log WHERE status IN ('pending','dispatched')")->fetchColumn();
        $metrics['monthly_incidents'] = (int)$mPdo->query("SELECT COUNT(*) FROM incident_log WHERE start_timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

        // Latest Active Incident Data
        $stmtInc = $mPdo->prepare("SELECT il.incident_ID, il.start_timestamp, il.status, il.incident_level, s.sensor_type, l.address, l.location_name, l.latitude, l.longitude, u.first_name, u.last_name, u.phone_number FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.status IN ('pending','dispatched') AND il.incident_level IN ('high','critical') ORDER BY il.start_timestamp DESC LIMIT 1");
        $stmtInc->execute();
        $latestInc = $stmtInc->fetch(PDO::FETCH_ASSOC);

        // Active Devices for Map
        // $stmtDev = $mPdo->prepare("SELECT s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, l.location_ID, l.location_name, l.address, l.latitude, l.longitude FROM sensor s JOIN location l ON s.FK_location_ID = l.location_ID WHERE s.status = 'active'");
        // $stmtDev->execute();
        // $activeDevices = $stmtDev->fetchAll(PDO::FETCH_ASSOC);

        $stmtDev = $mPdo->prepare("SELECT s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, l.location_ID, l.location_name, l.address, l.latitude, l.longitude FROM sensor s JOIN location l ON s.FK_location_ID = l.location_ID WHERE s.status = 'active'");
        $stmtDev->execute();
        $activeDevices = $stmtDev->fetchAll(PDO::FETCH_ASSOC);

        // [NEW] BFP Stations for Map
        $stmtStations = $mPdo->prepare("SELECT * FROM bfp_stations");
        $stmtStations->execute();
        $bfpStations = $stmtStations->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Dashboard data loading error: ' . $e->getMessage());
}

// Prepare JS variables for embedding
$incDataJson = json_encode($latestInc ?? null);
$activeDevicesJson = json_encode($activeDevices ?? []);
$bfpStationsJson = json_encode($bfpStations ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP Early Alert Dashboard</title>
   <script>
    tailwind.config = {
        theme: {
            extend: {}
        }
    }
</script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css"/>
<script src="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
<!-- 
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" /> -->
    <!-- <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script> -->
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        #map, #bfpMap { height: 100%; width: 100%; }
        /* Adjusted the height for the new BFP map management UI */
        #bfpStationMap { height: 300px; width: 100%; border-radius: 8px; margin-bottom: 1rem; } 
        .modal-backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal-backdrop.active { display: flex; }
        .toggle-switch { position: relative; width: 48px; height: 24px; background-color: #ccc; border-radius: 24px; cursor: pointer; transition: background-color 0.3s; }
        .toggle-switch.active { background-color: #ef4444; }
        .toggle-switch::after { content: ''; position: absolute; width: 20px; height: 20px; border-radius: 50%; background-color: white; top: 2px; left: 2px; transition: transform 0.3s; }
        .toggle-switch.active::after { transform: translateX(24px); }
        .dropdown-content { display: none; overflow: hidden; }
        .dropdown-content.active { display: block; }
        .dropdown-arrow { transition: transform 0.3s; }
        .dropdown-arrow.rotated { transform: rotate(90deg); }
        .dark-theme-main { background-color: #111827; color: #f9fafb; }
        .dark-theme-aside { background-color: #030712; }
        .dark-theme-header { background-color: #ffffff; color: #111827; }
        .dark-theme-card { background-color: #1f2937; }
        .dark-theme-text-primary { color: #ffffff; }
        .dark-theme-text-secondary { color: #9ca3af; }
        .leaflet-routing-container { display: none !important;}
        /* Custom Scrollbar for the device list */
#device-connected-list::-webkit-scrollbar {
    width: 6px;
}
#device-connected-list::-webkit-scrollbar-track {
    background: #f1f1f1; 
    border-radius: 4px;
}
#device-connected-list::-webkit-scrollbar-thumb {
    background: #888; 
    border-radius: 4px;
}
#device-connected-list::-webkit-scrollbar-thumb:hover {
    background: #555; 
}

        /* CSS for the Organizational Chart */
        .org-chart { display: flex; flex-direction: column; align-items: center; padding-top: 40px; position: relative; }
        .org-chart::before { content: ''; position: absolute; left: 50%; top: 0; bottom: 0; width: 100%; height: 100%; background: url('https://i.imgur.com/83n0w1Y.png') no-repeat center center; background-size: contain; opacity: 0.05; z-index: 0; }
        .org-chart ul { padding: 0; margin: 0; list-style: none; display: flex; justify-content: center; position: relative; }
        .org-chart li { padding: 40px 15px 0 15px; position: relative; display: flex; flex-direction: column; align-items: center; }
        .org-chart li::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 2px; height: 40px; background-color: #6b7280; }
        .org-chart li::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 2px; background-color: #6b7280; }
        .org-chart > ul > li::before, .org-chart > ul > li::after { display: none; }
        .org-chart li:only-child::after { display: none; }
        .org-chart li:first-child::after { left: 50%; width: 50%; }
        .org-chart li:last-child::after { width: 50%; }
        .personnel-card { background-color: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; width: 180px; text-align: center; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); position: relative; z-index: 1; }
        .personnel-card img { width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 12px auto; border: 3px solid #f3f4f6; }
        .personnel-card .name { font-weight: 700; font-size: 1rem; color: #111827; }
        .personnel-card .role { font-size: 0.8rem; color: #6b7280; }
    </style>
</head>
<body class="dark-theme-main">
    <div class="flex h-screen">
        <aside class="w-64 dark-theme-aside p-4 flex flex-col shadow-lg">
             <div class="flex items-center gap-3 p-2 border-b border-gray-700">
                <div class="bg-red-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                 <!-- <img src="components/image/0001.jpg" alt="BFP Logo" class="h-10"> -->
            </div>
            
            <nav class="flex-1 mt-4 space-y-2">
                 <!-- <div class="text-[10px] text-gray-500 mb-2 tracking-wide px-3">BFP DASHBOARD</div> -->
                <button onclick="showContent('dashboard', this)" class="nav-button w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold flex items-center gap-3 bg-red-600 text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </button>
                <audio id="bgmusic" autoplay loop>
                        <!-- <source src="0003_indian.mp3" type="audio/mpeg"> -->
                    </audio>
                <div>
                    <button onclick="toggleDropdown(this)" class="nav-button w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:bg-gray-800 flex justify-between items-center">
                        <span class="flex items-center gap-3">
                            <!-- <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg> -->
                            BFP Management
                        </span>
                        <svg id="dropdown-arrow" class="w-4 h-4 dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                    <div id="dropdown-content" class="dropdown-content pl-8 mt-1 space-y-1">

                        
                        <button onclick="loadExternalContent('usermanagement.php','external',this)" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">
                            👤 User Management</button>
                        <!-- <button onclick="showContent('bfp-map')" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">📍 BFP Map</button> -->
                        <!-- <button onclick="showContent('bfp-flowchart', this)" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">📊 Flowchart</button> -->
                        <!-- <button onclick="showContent('bfp-personnel')" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">👥 Personnel</button> -->
                        <button onclick="showContent('bfp-stations')" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">🏢 Fire Stations</button>
                        <!-- <button onclick="showContent('bfp-resources', this)" class="nav-button w-full text-left px-3 py-2 rounded-lg text-xs text-gray-400 hover:bg-gray-800">🚒 Resources</button> -->
                    </div>
                </div>

                <button onclick="loadExternalContent('reports.php', 'external', this)" class="nav-button w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:bg-gray-800 flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg>
                    Analytic Report
                </button>
                <button onclick="loadExternalContent('devicemanagement.php', 'external', this)" class="nav-button w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:bg-gray-800 flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                    System Device
                </button>
                <!-- <button onclick="showContent('settings')" class="nav-button w-full text-left px-3 py-2.5 rounded-lg text-sm font-semibold text-gray-300 hover:bg-gray-800 flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Settings
                </button> -->
            </nav>
            <div class="mt-auto p-2">
                 <button onclick="window.location.href = 'logoutdesk.php'" class="w-full bg-red-600 text-white px-4 py-3 rounded-lg font-bold text-sm hover:bg-red-700 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Logout</span>
                </button>
            </div>
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="dark-theme-header px-6 py-3 flex items-center justify-between shadow-md z-10">
                <h1 class="text-xl font-bold">BFP Early Alert : Smart IOT Fire Safety Solution</h1>
                <div class="flex items-center gap-4 text-sm">
                    <span id="datetime" class="bg-gray-100 text-gray-700 px-3 py-1.5 rounded-md font-medium"></span>
                    <button style="display:none;" class="text-gray-600 hover:text-red-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg></button>
                </div>
            </header>

            <main class="flex-1 p-6 overflow-auto">
                                    <div id="content-external" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-6" style="display: none;">
                        <div id="external-content-placeholder">
                            <p class="text-center text-gray-500 py-10">Loading...</p>
                        </div>
                    </div>
                                <div id="content-dashboard" class="content-section">
                     <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1 space-y-6">
                            <?php
                            // Server-render latest active incident (fallback when API/session may be unavailable)
                            try {
                                $incPdo = $mPdo ?? null;
                                if (!$incPdo) {
                                    $incPdo = new PDO($mDsn, $mUser, $mPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
                                }
                                $stmtInc = $incPdo->prepare("SELECT il.incident_ID, il.start_timestamp, il.status, il.incident_level, s.sensor_type, l.address, l.location_name, l.latitude, l.longitude, u.first_name, u.last_name, u.phone_number FROM incident_log il JOIN sensor s ON il.FK_sensor_ID = s.sensor_ID JOIN location l ON s.FK_location_ID = l.location_ID JOIN users u ON l.FK_user_ID = u.user_ID WHERE il.status IN ('pending','dispatched') AND il.incident_level IN ('high','critical') ORDER BY il.start_timestamp DESC LIMIT 1");
                                $stmtInc->execute();
                                $latestInc = $stmtInc->fetch();
                            } catch (Exception $e) {
                                $latestInc = null;
                                error_log('Latest incident load error: ' . $e->getMessage());
                            }
                            if ($latestInc):
                                $incTitle = htmlspecialchars(ucfirst($latestInc['incident_level'] ?? '') . ' - ' . ($latestInc['sensor_type'] ?? 'Alert'));
                                $incAddr = htmlspecialchars($latestInc['address'] ?? 'Unknown Location');
                                $incTime = htmlspecialchars(date('F d, Y | h:i A', strtotime($latestInc['start_timestamp'])));
                                $incLocationName = htmlspecialchars($latestInc['location_name'] ?? '');
                                $incLatitude = $latestInc['latitude'] ?? null;
                                $incLongitude = $latestInc['longitude'] ?? null;
                                $incPhone = htmlspecialchars($latestInc['phone_number'] ?? '');
                                $incId = (int)$latestInc['incident_ID'];
                            ?>
                            <div class="bg-white text-gray-900 rounded-xl shadow-lg overflow-hidden">
                                <div class="bg-red-600 text-white text-center py-2.5 font-bold tracking-wide">HIGH PRIORITY ALERT</div>
                                <div class="p-6 flex flex-col items-start relative">
                                    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs px-3 py-1 rounded-full font-bold shadow-md">ACTIVE</span>
                                    <h3 class="text-lg font-bold mb-2"><?php echo $incTitle; ?></h3>
                                    <p class="text-gray-700 text-sm mb-3"><?php echo $incAddr; ?></p>
                                    <p class="text-xs text-gray-500 mb-6"><?php echo $incTime; ?></p>
                                    <button onclick="tapAlert(<?php echo $incId; ?>)" class="w-full bg-red-600 text-white py-3 rounded-lg font-bold text-lg hover:bg-red-700">Tap Alert</button>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="bg-white text-gray-900 rounded-xl shadow-lg overflow-hidden">
                                <div class="bg-gray-100 text-gray-900 text-center py-2.5 font-bold tracking-wide">No Active Alerts</div>
                                <div class="p-6 flex flex-col items-center">
                                    <div class="bg-gray-200 text-gray-700 rounded-full p-5 mb-4">
                                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <h3 class="text-2xl font-bold mb-2">No Active Alerts</h3>
                                    <p class="text-gray-700 text-center text-sm mb-1">All systems normal.</p>
                                    <p class="text-xs text-gray-500 mb-6">—</p>
                                    <button onclick="tapAlert(null)" class="w-full bg-gray-300 text-gray-800 py-3 rounded-lg font-bold text-lg">Tap Alert</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="lg:col-span-2 space-y-6">
                            <div class="rounded-xl shadow-lg overflow-hidden" style="height: 350px;">
                                <div id="map"></div>
                            </div>
                             <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                 <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
                                     <div class="flex items-center justify-between mb-3">
                                         <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                     </div>
                                     <h3 class="text-sm font-medium mb-1 opacity-90">Total Users</h3>
                                     <div class="text-3xl font-bold" data-metric="total-users"><?php echo htmlspecialchars((int)($metrics['total_users'] ?? 0)); ?></div>
                                 </div>
                                 <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl shadow-lg p-5 text-white">
                                     <div class="flex items-center justify-between mb-3">
                                         <div class="relative w-8 h-8">
                                             <div class="absolute inset-0 border-[3px] border-white rounded-full"></div>
                                             <div class="absolute inset-1.5 border-[3px] border-white rounded-full"></div>
                                             <div class="absolute inset-3 bg-white rounded-full"></div>
                                         </div>
                                     </div>
                                     <h3 class="text-sm font-medium mb-1 opacity-90">Active Devices</h3>
                                     <div class="text-3xl font-bold" data-metric="active-devices"><?php echo htmlspecialchars((int)($metrics['active_devices'] ?? 0)); ?></div>
                                 </div>
                                 <div class="bg-gradient-to-br from-blue-700 to-blue-800 rounded-xl shadow-lg p-5 text-white">
                                     <div class="flex items-center justify-between mb-3">
                                         <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                     </div>
                                     <h3 class="text-sm font-medium mb-1 opacity-90">Active Alerts</h3>
                                     <div class="text-3xl font-bold" data-metric="active-alerts"><?php echo htmlspecialchars((int)($metrics['active_alerts'] ?? 0)); ?></div>
                                 </div>
                                 <div class="bg-gradient-to-br from-blue-800 to-blue-900 rounded-xl shadow-lg p-5 text-white">
                                     <div class="flex items-center justify-between mb-3">
                                         <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                     </div>
                                     <h3 class="text-sm font-medium mb-1 opacity-90">Incident this month</h3>
                                     <div class="text-3xl font-bold" data-metric="monthly-incidents"><?php echo htmlspecialchars((int)($metrics['monthly_incidents'] ?? 0)); ?></div>
                                 </div>
                             </div>
                        </div>
                    </div>
                    <div class="mt-6 bg-white text-gray-900 rounded-xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-xl font-bold">BFP Incident Logs</h2></div>
                         <div class="grid grid-cols-3 border-b border-gray-200">
                           <div class="px-6 py-3 bg-gray-800 text-white text-center font-bold text-sm">Current Incident</div>
                           <div class="px-6 py-3 bg-gray-800 text-white text-center font-bold text-sm border-l border-gray-700">Device Connected</div>
                           <div class="px-6 py-3 bg-gray-800 text-white text-center font-bold text-sm border-l border-gray-700">Operator Guide</div>
                         </div>
                         <div class="grid grid-cols-3 p-6 gap-6">
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4 relative border border-gray-200">

                <script>
                    // Realtime dashboard script: initial fetch + SSE (fallback to polling)
                    const API_URL = 'api_bfp.php';
                    let dashboardSSE = null;

                    // async function updateDashboardFromAPI() {
                    //     try {
                    //         const res = await fetch(`${API_URL}?endpoint=dashboard`);
                    //         if (!res.ok) return console.warn('Dashboard API not ok', res.status);
                    //         const data = await res.json();
                    //         if (data.status !== 'success') return console.warn('Dashboard API error', data);

                    //         const m = data.metrics || {};
                    //         document.querySelector('[data-metric="total-users"]').textContent = m.total_users ?? (data.users ? data.users.length : 0);
                    //         document.querySelector('[data-metric="active-devices"]').textContent = m.active_devices ?? 0;
                    //         document.querySelector('[data-metric="active-alerts"]').textContent = m.active_alerts ?? 0;
                    //         document.querySelector('[data-metric="monthly-incidents"]').textContent = m.monthly_responses ?? m.monthly_responses ?? 0;

                    //         // Update top alert card
                    //         const topAreaTitle = document.querySelector('#content-dashboard .bg-red-600 + div h3') || document.querySelector('#content-dashboard h3');
                    //         const topAreaDesc = document.querySelector('#content-dashboard .bg-red-600 + div p') || null;
                    //         const topAreaTime = document.querySelector('#content-dashboard .bg-red-600 + div .text-xs');
                    //         const btn = document.querySelector('#content-dashboard button[onclick^="tapAlert"]');

                    //         if (data.activeIncidents && data.activeIncidents.length > 0) {
                    //             const inc = data.activeIncidents[0];
                    //             if (topAreaTitle) topAreaTitle.textContent = (inc.incident_type || 'ALERT') + ' #' + inc.incident_ID;
                    //             if (topAreaDesc) topAreaDesc.textContent = inc.address || '';
                    //             if (topAreaTime) topAreaTime.textContent = inc.start_timestamp || '';
                    //             if (btn) {
                    //                 btn.textContent = 'Tap Alert';
                    //                 btn.onclick = () => tapAlert(inc.incident_ID);
                    //             }
                    //         } else {
                    //             if (topAreaTitle) topAreaTitle.textContent = 'No Active Alerts';
                    //             if (topAreaDesc) topAreaDesc.textContent = '';
                    //             if (topAreaTime) topAreaTime.textContent = '';
                    //             if (btn) { btn.textContent = 'Tap Alert'; btn.onclick = () => alert('No active incident'); }
                    //         }
                            
                    //         // Re-render Active Devices with click handler
                    //         window.cachedUsers = data.users || [];
                    //         renderDeviceConnected(window.cachedUsers);

                    //     } catch (e) { console.error('Failed to update dashboard', e); }
                    // }

                    async function updateDashboardFromAPI() {
    try {
        const res = await fetch(`${API_URL}?endpoint=dashboard`);
        if (!res.ok) return console.warn('Dashboard API not ok', res.status);
        const data = await res.json();
        
        // 1. Update Metrics
        const m = data.metrics || {};
        document.querySelector('[data-metric="total-users"]').textContent = m.total_users ?? 0;
        document.querySelector('[data-metric="active-devices"]').textContent = m.active_devices ?? 0;
        document.querySelector('[data-metric="active-alerts"]').textContent = m.active_alerts ?? 0;
        document.querySelector('[data-metric="monthly-incidents"]').textContent = m.monthly_responses ?? 0;

        // 2. Update Active Incident Card (Fixing the "No Show" issue)
        const activeIncidents = data.activeIncidents || [];
        const btn = document.querySelector('#content-dashboard button[onclick^="tapAlert"]');

        if (activeIncidents.length > 0) {
            const inc = activeIncidents[0]; // Get the latest incident
            
            // Populate the specific IDs defined in your HTML
            const addrElement = document.getElementById('currentIncAddress');
            const descElement = document.getElementById('currentIncDesc');
            const dateElement = document.getElementById('currentIncDate');

            if (addrElement) addrElement.textContent = inc.address || inc.location_name || 'Location Unavailable';
            
            // Construct a description
            const descText = `Type: ${inc.incident_type || 'Fire'} | Level: ${inc.incident_level || 'High'}`;
            if (descElement) descElement.textContent = descText;
            
            if (dateElement) dateElement.textContent = 'Date: ' + (inc.start_timestamp || 'Just now');

            // Update the "Tap Alert" button
            if (btn) {
                btn.textContent = 'Tap Alert';
                btn.onclick = () => tapAlert(inc.incident_ID);
                btn.classList.remove('bg-gray-300', 'text-gray-800');
                btn.classList.add('bg-red-600', 'text-white');
            }
        } else {
            // No Active Alerts
            document.getElementById('currentIncAddress').textContent = 'No Active Alerts';
            document.getElementById('currentIncDesc').textContent = 'All systems normal.';
            document.getElementById('currentIncDate').textContent = 'Date: —';
            
            if (btn) { 
                btn.textContent = 'No Alerts'; 
                btn.onclick = null;
                btn.classList.remove('bg-red-600', 'text-white');
                btn.classList.add('bg-gray-300', 'text-gray-800');
            }
        }
        
        // 3. Update Device List
        window.cachedUsers = data.users || [];
        renderDeviceConnected(window.cachedUsers);

    } catch (e) { console.error('Failed to update dashboard', e); }
}

                    // modal currently-selected incident id
                    let currentIncidentId = null;

                    // Open the alert modal for a given incident (keeps currentIncidentId)
                    function tapAlert(incidentId = null) {
                                        currentIncidentId = incidentId;
                                        // populate modal: prefer embedded latestIncident when it matches
                                        if (window.latestIncident && incidentId && parseInt(window.latestIncident.incident_ID) === parseInt(incidentId)) {
                                            populateAlertModalFromData(window.latestIncident);
                                        } else if (incidentId) {
                                            // attempt to fetch incident details (may require auth)
                                            fetch(`${API_URL}?endpoint=incidents&id=${incidentId}`).then(r => r.json()).then(js => {
                                                if (js && js.status === 'success' && js.data) {
                                                    populateAlertModalFromData(js.data);
                                                } else if (js && js.data) {
                                                    populateAlertModalFromData(js.data);
                                                } else {
                                                    // fallback: use embedded latest if available
                                                    populateAlertModalFromData(window.latestIncident);
                                                }
                                            }).catch(() => populateAlertModalFromData(window.latestIncident));
                                        } else {
                                            populateAlertModalFromData(null);
                                        }
                                        document.getElementById('alertModal').classList.add('active');
                    }
                    
                    // Renders the list of locations on the dashboard
                    function renderDeviceConnected(users) {
                        const deviceListEl = document.getElementById('device-connected-list');
                        deviceListEl.innerHTML = '';
                        
                        // Filter users who have devices installed
                        const activeUsers = users.filter(u => u.device_count > 0);

                        activeUsers.forEach(user => {
                            const title = user.location_name || user.address;
                            const userId = user.user_ID;
                            
                            let icon = '';
                            if (title.toLowerCase().includes('school') || title.toLowerCase().includes('elementary')) {
                                icon = '🏫';
                            } else if (title.toLowerCase().includes('jollibee') || title.toLowerCase().includes('restaurant')) {
                                icon = '🍔';
                            } else {
                                icon = '🏠';
                            }

                            const html = `
                                <div onclick="showLocationOverview(${userId})" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-3 rounded-lg flex items-center justify-between shadow-md cursor-pointer hover:from-blue-700 transition duration-150">
                                    <span class="text-sm font-medium">${title}</span>
                                    <div class="bg-white bg-opacity-30 w-8 h-8 rounded flex items-center justify-center">
                                        <span class="text-lg">${icon}</span>
                                    </div>
                                </div>
                            `;
                            deviceListEl.insertAdjacentHTML('beforeend', html);
                        });
                        
                        if (activeUsers.length === 0) {
                             deviceListEl.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">No locations with active devices found.</p>';
                        }
                    }
                    
                    let currentUserId = null;

                    // STEP 1: Show the Location Overview Modal (the new intermediate step)
                    function showLocationOverview(userId) {
                        currentUserId = userId; // Store ID for next step
                        
                        // Find the user data in the cache
                        const user = window.cachedUsers.find(u => u.user_ID === userId);
                        
                        // Populate the Overview Modal
                        if (user) {
                            // Populate data fields
                            document.getElementById('overviewUser').textContent = user.first_name + ' ' + user.last_name;
                            document.getElementById('overviewUserPhone').textContent = user.phone_number || 'N/A';
                            document.getElementById('overviewLocationName').textContent = user.location_name || 'N/A';
                            document.getElementById('overviewLocationAddress').textContent = user.address || 'N/A';
                            document.getElementById('overviewDeviceCount').textContent = user.device_count || 0;
                            
                            // Show the modal
                            document.getElementById('locationOverviewModal').classList.add('active');
                        } else {
                            alert('User details not found in cache. Refreshing dashboard.');
                            updateDashboardFromAPI(); // Try refreshing data
                        }
                    }

                    // STEP 2: Fetch and Show the Device Details Modal
                    async function fetchAndShowDeviceDetails() {
                        // Close the overview modal first
                        closeModal('locationOverviewModal');

                        if (!currentUserId) {
                             alert("Error: No user selected.");
                             return;
                        }
                        
                        const userId = currentUserId;

                        try {
                            // Show loading state immediately in the modal
                            document.getElementById('deviceModalUser').textContent = 'Loading...';
                            document.getElementById('deviceModalUserPhone').textContent = 'Loading...';
                            document.getElementById('deviceModalLocationName').textContent = 'Loading...';
                            document.getElementById('deviceModalLocationAddress').textContent = 'Loading...';
                            const sensorList = document.getElementById('deviceModalSensorList');
                            sensorList.innerHTML = '<p class="text-sm text-gray-500">Fetching sensor data...</p>';
                            
                            // Open the detailed modal
                            document.getElementById('deviceDetailModal').classList.add('active');


                            // 1. Fetch detailed device data from API
                            const res = await fetch(`${API_URL}?endpoint=device_details&user_id=${userId}`);
                            const data = await res.json();

                            if (data.status !== 'success' || !data.data) {
                                // Fallback for error/no data
                                document.getElementById('deviceModalUser').textContent = 'Error';
                                document.getElementById('deviceModalLocationAddress').textContent = 'Failed to load details.';
                                sensorList.innerHTML = '<p class="text-sm text-red-500">Failed to load sensor data. Check API response.</p>';
                                return;
                            }
                            
                            const d = data.data;

                            // 2. Populate Modal
                            document.getElementById('deviceModalUser').textContent = d.user.name || 'N/A';
                            document.getElementById('deviceModalUserPhone').textContent = d.user.phone || 'N/A';
                            document.getElementById('deviceModalLocationName').textContent = d.location.name || 'N/A';
                            document.getElementById('deviceModalLocationAddress').textContent = d.location.address || 'N/A';

                            sensorList.innerHTML = '';
                            
                            if (d.sensors.length === 0) {
                                sensorList.innerHTML = '<p class="text-sm text-gray-500">No sensors found for this location.</p>';
                            } else {
                                d.sensors.forEach(s => {
                                    const statusColor = s.status === 'ALERT' || s.status === 'ERROR' ? 'text-red-600' : 'text-green-600';
                                    
                                    let readingsDisplay = [];
                                    if (s.latest_readings) {
                                        if (s.latest_readings.gas) readingsDisplay.push(`Gas/Smoke: ${s.latest_readings.gas}`);
                                        if (s.latest_readings.temp) readingsDisplay.push(`Temp: ${s.latest_readings.temp}°C`);
                                    }

                                    const readingsHtml = readingsDisplay.length > 0 
                                        ? `<p class="text-xs text-gray-700">${readingsDisplay.join(' | ')}</p>`
                                        : `<p class="text-xs text-gray-500">No recent readings.</p>`;

                                    const item = `
                                        <div class="bg-gray-100 p-3 rounded-lg border-l-4 ${s.status === 'ALERT' || s.status === 'ERROR' ? 'border-red-600' : 'border-green-600'}">
                                            <div class="font-semibold text-sm">Sensor ID: ${s.sensor_ID} (${s.type})</div>
                                            <div class="text-xs ${statusColor} font-medium mb-1">Status: ${s.status}</div>
                                            ${readingsHtml}
                                        </div>
                                    `;
                                    sensorList.insertAdjacentHTML('beforeend', item);
                                });
                            }

                        } catch (e) {
                            alert("A network error occurred while fetching device details.");
                            console.error('fetchAndShowDeviceDetails error:', e);
                            closeModal('deviceDetailModal');
                        }
                    }


                    // Respond / Dispatch: set incident status to DISPATCHED
                    async function respondAlert() {
                        if (!currentIncidentId) {
                            closeModal('alertModal');
                            alert('No incident selected to respond to.');
                            return;
                        }
                        if (!confirm('Dispatch response team for incident ' + currentIncidentId + '?')) return;
                        try {
                            const res = await fetch(`${API_URL}?endpoint=incidents`, {
                                method: 'PUT', headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({ incident_ID: currentIncidentId, status: 'DISPATCHED' })
                            });
                            const d = await res.json();
                            if (d.status === 'success') {
                                alert('Incident marked as DISPATCHED');
                                closeModal('alertModal');
                                updateDashboardFromAPI();
                            } else {
                                alert('Dispatch failed: ' + (d.message || 'unknown'));
                            }
                        } catch (e) { console.error(e); alert('Dispatch failed'); }
                    }

                    // SSE hookup
                    function initDashboardSSE() {
                        try {
                            if (dashboardSSE && typeof dashboardSSE.close === 'function') dashboardSSE.close();
                            if (!('EventSource' in window)) return; // skip if not supported
                            dashboardSSE = new EventSource(`${API_URL}?endpoint=dashboard_stream`);
                            dashboardSSE.addEventListener('update', ev => {
                                try {
                                    const d = JSON.parse(ev.data);
                                    // use SSE minimal payload to update live metrics
                                    document.querySelector('[data-metric="active-alerts"]').textContent = d.active_alerts ?? 0;
                                    document.querySelector('[data-metric="monthly-incidents"]').textContent = d.total_incidents ?? 0;
                                    // update latest incident preview
                                    const latest = d.latest || [];
                                    if (latest.length > 0) {
                                        const inc = latest[0];
                                        const titleEl = document.querySelector('#content-dashboard .bg-red-600 + div h3');
                                        const descEl = document.querySelector('#content-dashboard .bg-red-600 + div p');
                                        const timeEl = document.querySelector('#content-dashboard .bg-red-600 + div .text-xs');
                                        if (titleEl) titleEl.textContent = (inc.incident_level||'') + ' - ' + (inc.address||'');
                                        if (descEl) descEl.textContent = '';
                                        if (timeEl) timeEl.textContent = inc.start_timestamp || '';
                                        // keep currentIncidentId in sync if modal is open
                                        try {
                                            if (document.getElementById('alertModal') && document.getElementById('alertModal').classList.contains('active')) {
                                                currentIncidentId = inc.incident_ID;
                                            }
                                        } catch(e) { /* ignore */ }
                                    }
                                } catch (e) { console.error('SSE parse error', e); }
                            });
                            dashboardSSE.onopen = () => console.log('Dashboard SSE open');
                            dashboardSSE.onerror = (e) => { console.warn('Dashboard SSE error', e); /* keep polling */ };
                        } catch (e) { console. warn('SSE init failed', e); }
                    }

                    function setTrendCharts() {
                        const ctx = document.getElementById('trendsChart').getContext('2d');
                        window.trendsChart = new Chart(ctx, {
                            type: 'line', // or 'bar', etc.
                            data: {
                                labels: [], // initial empty
                                datasets: [{
                                    label: 'Incidents',
                                    data: [],
                                    backgroundColor: 'rgba(255,99,132,0.2)',
                                    borderColor: 'rgba(255,99,132,1)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    }

                    function setTypesCharts() {
                        const ctx = document.getElementById('typesChart').getContext('2d');
                        window.typesChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: [], // initial empty
                                datasets: [{
                                    label: 'Incident Types',
                                    data: [],
                                    backgroundColor: [
                                        'rgba(255, 99, 132, 0.2)',
                                        'rgba(54, 162, 235, 0.2)',
                                        'rgba(255, 206, 86, 0.2)',
                                        'rgba(75, 192, 192, 0.2)',
                                        'rgba(153, 102, 255, 0.2)',
                                        'rgba(255, 159, 64, 0.2)'
                                    ],
                                    borderColor: [
                                        'rgba(255,99,132,1)',
                                        'rgba(54,162,235,1)',
                                        'rgba(255,206,86,1)',
                                        'rgba(75,192,192,1)',
                                        'rgba(153,102,255,1)',
                                        'rgba(255,159,64,1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true
                            }
                        });
                    }

                    // Start
                    document.addEventListener('DOMContentLoaded', () => {
                        updateDashboardFromAPI();
                        initDashboardSSE();
                        setTrendCharts(); // from charts.js
                        // Fallback polling every 6s in case SSE fails
                        // setInterval(updateDashboardFromAPI, 6000);
                        // Populate current incident placeholders from embedded data if available
                        try {
                            if (window.latestIncident) {
                                document.getElementById('currentIncAddress').textContent = window.latestIncident.address || (window.latestIncident.location_name || 'Unknown location');
                                document.getElementById('currentIncDesc').textContent = window.latestIncident.description || (window.latestIncident.sensor_type ? ('Device: ' + window.latestIncident.sensor_type) : 'No description');
                                if (window.latestIncident.start_timestamp) document.getElementById('currentIncDate').textContent = 'Date: ' + new Date(window.latestIncident.start_timestamp).toLocaleString();
                            } else {
                                document.getElementById('currentIncAddress').textContent = 'No Active Alerts';
                                document.getElementById('currentIncDesc').textContent = 'All systems normal.';
                                document.getElementById('currentIncDate').textContent = '';
                            }
                        } catch(e) { /* ignore */ }
                    });
                </script>
                                    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs px-3 py-1 rounded-full font-bold shadow-md">ACTIVE</span>
                                    <p id="currentIncAddress" class="text-sm font-bold mb-3 text-gray-900">Loading address...</p>
                                    <div id="currentIncDesc" class="text-xs text-gray-700 leading-relaxed mb-3 max-h-28 overflow-auto">Loading description...</div>
                                    <p id="currentIncDate" class="text-xs text-gray-500 font-medium">Date: —</p>
                                </div>
                            </div>
                            <!-- Updated: Device Connected List (UI structure starts here) -->
                            <div id="device-connected-list" class="space-y-3 max-h-[250px] overflow-y-auto pr-2">
                               <!-- List items are now rendered by renderDeviceConnected(data.users) in JS -->
                            </div>
                            <div class="space-y-3">
                               <div class="flex gap-3">
                                   <div class="bg-blue-700 text-white w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">1</div>
                                   <p class="text-xs leading-relaxed text-gray-700">When a new alert appears, immediately <span class="text-red-600 font-bold">Tap Alert</span> to acknowledge and view the incident.</p>
                               </div>
                               <div class="flex gap-3">
                                   <div class="bg-blue-700 text-white w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">2</div>
                                   <p class="text-xs leading-relaxed text-gray-700">Use the <span class="text-blue-600 font-bold">Map</span> to verify the incident location and assess proximity to key landmarks.</p>
                               </div>
                               <div class="flex gap-3">
                                   <div class="bg-blue-700 text-white w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">3</div>
                                   <p class="text-xs leading-relaxed text-gray-700">Check the <span class="text-green-600 font-bold">Device Connected</span> list to monitor the status of all sensors.</p>
                               </div>
                                <div class="flex gap-3">
                                   <div class="bg-blue-700 text-white w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">4</div>
                                   <p class="text-xs leading-relaxed text-gray-700">Dispatch the nearest fire station according to the incident level and standard protocol.</p>
                               </div>
                               <div class="flex gap-3">
                                   <div class="bg-blue-700 text-white w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm">5</div>
                                   <p class="text-xs leading-relaxed text-gray-700">After resolving, review the incident in the <span class="text-green-600 font-bold">Analytic Report</span>  to identify the any patterns of cases.</p>
                               </div>
                            </div>
                         </div>
                    </div>
                </div>

                <div id="content-bfp-map" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-6" style="display: none;">
                    <h2 class="text-2xl font-bold mb-4">📍 Map for BFP</h2>
                    <p class="text-gray-600 mb-4">Interactive map showing all BFP stations and coverage areas.</p>
                    <div class="rounded-lg h-96 overflow-hidden" id="bfpMapContainer"><div id="bfpMap"></div></div>
                </div>
                <div id="content-bfp-flowchart" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-6" style="display: none;">
                    <h2 class="text-2xl font-bold mb-4">📊 BFP Flowchart</h2>
                    <p class="text-gray-600 mb-4">Emergency response workflow and decision tree.</p>
                    <div class="bg-gray-50 rounded-lg p-6 text-center"><div class="space-y-2"><div class="bg-red-600 text-white p-4 rounded-lg font-bold">Fire Alert Received</div><div>↓</div><div class="bg-blue-600 text-white p-4 rounded-lg font-bold">Verify Location & Severity</div><div>↓</div><div class="bg-green-600 text-white p-4 rounded-lg font-bold">Dispatch Fire Units</div><div>↓</div><div class="bg-orange-600 text-white p-4 rounded-lg font-bold">Contact Resident & Notify Authorities</div></div></div>
                </div>
                <div id="content-bfp-personnel" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-0 overflow-hidden" style="display: none;">
                    <div class="bg-red-600 text-white p-8" style="border-bottom-left-radius: 50% 40px; border-bottom-right-radius: 50% 40px;">
                        <h2 class="text-3xl font-bold text-center">Daet Fire Station Organizational Chart</h2>
                    </div>
                    <div class="org-chart-container py-10">
                        <div class="org-chart">
                            <ul>
                                <li>
                                    <div class="personnel-card">
                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                        <div class="name">F/CINSP Antonio B. Razal Jr.</div>
                                        <div class="role">City Fire Director</div>
                                    </div>
                                    <ul>
                                        <li>
                                            <div class="personnel-card">
                                                <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                <div class="name">F/INSP Juan Dela Cruz</div>
                                                <div class="role">Chief, Operations</div>
                                            </div>
                                            <ul>
                                                <li>
                                                    <div class="personnel-card">
                                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                        <div class="name">SFO4 Michael Reyes</div>
                                                        <div class="role">Team Leader Alpha</div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </li>
                                        <li>
                                            <div class="personnel-card">
                                                <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                <div class="name">F/INSP Maria Santos</div>
                                                <div class="role">Chief, Admin</div>
                                            </div>
                                            <ul>
                                                <li>
                                                    <div class="personnel-card">
                                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                        <div class="name">SFO2 Carla Lim</div>
                                                        <div class="role">Records Officer</div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </li>
                                        <li>
                                            <div class="personnel-card">
                                                <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                <div class="name">F/INSP Pedro Garcia</div>
                                                <div class="role">Chief, Logistics</div>
                                            </div>
                                            <ul>
                                                <li>
                                                    <div class="personnel-card">
                                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                        <div class="name">FO3 Jose Rodriguez</div>
                                                        <div class="role">Supply Officer</div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="personnel-card">
                                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                        <div class="name">FO3 Ana Dizon</div>
                                                        <div class="role">Mechanic</div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </li>
                                         <li>
                                            <div class="personnel-card">
                                                <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                <div class="name">F/INSP Leni Ramos</div>
                                                <div class="role">Chief, Fire Safety</div>
                                            </div>
                                            <ul>
                                                <li>
                                                    <div class="personnel-card">
                                                        <img src="https://i.imgur.com/He803fc.png" alt="Profile Picture">
                                                        <div class="name">SFO1 David Villanueva</div>
                                                        <div class="role">Inspector</div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- 
                ==========================================================
                BFP STATIONS CRUD MANAGEMENT UI
                ==========================================================
                -->
                <div id="content-bfp-stations" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-6" style="display: none;">
                    <h2 class="text-2xl font-bold mb-4">🏢 Fire Station Management (CRUD)</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Left Column: Form and Map -->
                        <div class="md:col-span-1 space-y-4">
                            <h3 class="text-xl font-semibold border-b pb-2 mb-4" id="stationFormTitle">Add New Station</h3>
                            
                            <div id="bfpStationMap"></div>
                            <p class="text-sm text-gray-600 flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9a1 1 0 102 0V6a1 1 0 10-2 0v3zm1 4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"></path></svg>
                                Click the map above to accurately set coordinates.
                            </p>
                            
                            <form id="station-crud-form" class="space-y-3">
                                <input type="hidden" id="station-id" name="station_ID" value="">
                                
                                <div>
                                    <label for="station-name" class="block text-sm font-medium text-gray-700">Station Name</label>
                                    <input type="text" id="station-name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                                </div>
                                <div>
                                    <label for="station-contact" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                    <input type="text" id="station-contact" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="station-lat" class="block text-sm font-medium text-gray-700">Latitude (N)</label>
                                        <input type="text" id="station-lat" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-gray-100" readonly required>
                                    </div>
                                    <div>
                                        <label for="station-lon" class="block text-sm font-medium text-gray-700">Longitude (E)</label>
                                        <input type="text" id="station-lon" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-gray-100" readonly required>
                                    </div>
                                </div>
                                
                                <div class="pt-4 flex gap-3">
                                    <button type="submit" id="form-submit-btn" class="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg font-bold hover:bg-red-700 transition">Add Station</button>
                                    <button type="button" onclick="resetStationForm()" id="form-reset-btn" class="w-1/4 bg-gray-300 text-gray-800 py-2 px-4 rounded-lg font-medium hover:bg-gray-400 transition">Reset</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Right Column: Table and Search -->
                        <div class="md:col-span-2 space-y-4">
                             <!-- Search UI -->
                            <div class="flex gap-3">
                                <input type="text" id="station-search-input" placeholder="Search by Station Name..." class="flex-1 border border-gray-300 rounded-md shadow-sm p-2">
                                <button onclick="searchStations()" class="bg-blue-600 text-white py-2 px-4 rounded-lg font-bold hover:bg-blue-700 transition">Search</button>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg shadow">
                                <h3 class="font-bold mb-3">All Stations</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coordinates</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="stations-table-body">
                                            <!-- Data rows will be inserted here by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="content-settings" class="content-section bg-white text-gray-900 rounded-xl shadow-lg p-6" style="display: none;">
                    <h2 class="text-2xl font-bold mb-6">Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-bold text-lg mb-4">User Profile</h3>
                            <div class="flex items-center gap-4 mb-6">
                                <div class="w-20 h-20 bg-red-600 rounded-full flex items-center justify-center"><svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg></div>
                                <div>
                                    <div class="font-bold text-lg"><?php echo $first_name; ?></div><div class="text-sm text-gray-600"><?php echo $role; ?></div><div class="text-sm text-gray-500">September, 28, 2025</div>
                                </div>
                            </div>
                            <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label><?php echo $email; ?></div>
                            <div class="mb-4"><label class="block text-sm font-medium text-gray-700 mb-2">Password</label><input type="password" value="<?php echo $passHash; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" /><button onclick="alert('Password change modal would show here.')" class="text-red-600 text-sm font-medium mt-2 hover:underline">Change Password</button></div>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg mb-4">Notification Settings</h3>
                            <div class="bg-gray-50 rounded-lg p-4 mb-4"><div class="flex items-center justify-between"><div><div class="font-semibold text-gray-900">Email Notifications</div><div class="text-xs text-gray-500">Receives alert via email</div></div><div class="toggle-switch active" id="emailToggle" onclick="this.classList.toggle('active')"></div></div></div>
                            <div class="bg-gray-50 rounded-lg p-4 mb-6"><div class="flex items-center justify-between"><div><div class="font-semibold text-gray-900">Push Notification</div><div class="text-xs text-gray-500">Receives alert on your mobile devices</div></div><div class="toggle-switch" id="pushToggle" onclick="this.classList.toggle('active')"></div></div></div>
                            <h3 class="font-bold text-lg mb-4">System</h3>
                            <div class="space-y-2">
                                <button class="w-full bg-gray-50 hover:bg-gray-100 rounded-lg p-4 flex items-center justify-between"><span class="font-semibold text-gray-900">About BFP Early Alert</span><svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                                <button class="w-full bg-gray-50 hover:bg-gray-100 rounded-lg p-4 flex items-center justify-between"><span class="font-semibold text-gray-900">Privacy Policy</span><svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                            </div>
                            <div class="flex gap-2 mt-6">
                                <button onclick="showContent('dashboard', document.querySelector('button[onclick*=\'dashboard\']'))" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-2 px-4 rounded-lg font-medium">Cancel</button>
                                <button onclick="showPasswordChangedModal()" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg font-medium">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Alert Modal (Existing) -->
    <div id="alertModal" class="modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-80 overflow-hidden text-gray-900">
            <div class="bg-red-600 text-white px-4 py-3 relative">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                        <span class="font-bold text-lg">EARLY FIRE ALERT</span>
                    </div>
                    <button onclick="closeModal('alertModal')" class="text-white hover:text-gray-200 text-2xl">&times;</button>
                </div>
                <div class="mt-1"><span class="bg-red-800 text-white text-xs px-2 py-0.5 rounded font-bold">HIGH PRIORITY</span></div>
            </div>
            <div class="p-4 bg-gray-50 space-y-4">
                <div>
                    <h3 class="font-bold mb-2">Resident Information</h3>
                    <div class="text-sm space-y-1">
                        <div><span class="font-semibold">Name:</span> <span id="modalResidentName">—</span></div>
                        <div><span class="font-semibold">Phone:</span> <span id="modalResidentPhone">—</span></div>
                        <div><span class="font-semibold">Location Name:</span> <span id="modalLocationName">—</span></div>
                    </div>
                </div>
                <div class="bg-blue-50 p-3 rounded-lg"><div class="flex items-start gap-3"><svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg><div><div class="font-semibold text-sm">Location</div><div class="text-sm text-blue-600 font-semibold" id="modalLocationAddress">—</div><div class="text-xs text-gray-500" id="modalLocationCoords">—</div></div></div></div>
                <div class="bg-orange-50 p-3 rounded-lg"><div class="flex items-start gap-3"><svg class="w-5 h-5 text-orange-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path></svg><div><div class="font-semibold text-sm">Early Alert Time</div><div class="text-sm text-gray-700">Date: <span id="modalDate">—</span></div><div class="text-sm text-gray-700">Time: <span id="modalTime">—</span></div></div></div></div>
                <div class="bg-green-50 p-3 rounded-lg"><div class="flex items-start gap-3"><svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0117.657 18.657z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1014.12 11.88l-4.242 4.242z"></path></svg><div><div class="font-semibold text-sm">Device Information</div><div class="text-sm text-gray-700" id="modalDeviceInfo">—</div></div></div></div>
                <div class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="dismissAlert()" class="bg-gray-600 hover:bg-gray-700 text-white py-2.5 px-4 rounded-lg font-medium text-sm">Dismiss</button>
                        <button onclick="respondAlert()" class="bg-red-600 hover:bg-red-700 text-white py-2.5 px-4 rounded-lg font-medium text-sm flex items-center justify-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>On The Way</button>
                    </div>
                    <button style="display:none" onclick="alert('Calling Resident...')" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 px-4 rounded-lg font-medium text-sm flex items-center justify-center gap-2"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg>Call Resident</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NEW: Location Overview Modal (Intermediate Step - STEP 1 UI) -->
    <div id="locationOverviewModal" class="modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-80 overflow-hidden text-gray-900">
            <div class="bg-blue-600 text-white px-4 py-3 relative flex items-center justify-between">
                <span class="font-bold text-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Location Overview
                </span>
                <button onclick="closeModal('locationOverviewModal')" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
            <div class="p-4 bg-gray-50 space-y-4">
                <h3 class="font-bold mb-2 text-lg border-b pb-2">Property Details</h3>
                
                <div class="space-y-1">
                    <div class="text-sm"><span class="font-semibold text-gray-700">Location Name:</span> <span id="overviewLocationName" class="font-medium">—</span></div>
                    <div class="text-xs text-gray-600" id="overviewLocationAddress">—</div>
                </div>

                <div class="space-y-1 pt-2 border-t">
                    <div class="text-sm"><span class="font-semibold text-gray-700">Owner/User:</span> <span id="overviewUser" class="font-medium">—</span></div>
                    <div class="text-sm"><span class="font-semibold text-gray-700">Contact:</span> <span id="overviewUserPhone">—</span></div>
                    <div class="text-sm"><span class="font-semibold text-gray-700">Total Devices:</span> <span id="overviewDeviceCount" class="font-medium text-blue-700">—</span></div>
                </div>

                <button onclick="fetchAndShowDeviceDetails()" class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 px-4 rounded-lg font-medium text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                    View All Connected Sensors
                </button>
            </div>
        </div>
    </div>


    <!-- Device Details Modal (Existing, for STEP 2 UI) -->
    <div id="deviceDetailModal" class="modal-backdrop">
        <div class="bg-white rounded-lg shadow-2xl w-96 overflow-hidden text-gray-900">
            <div class="bg-blue-600 text-white px-4 py-3 relative flex items-center justify-between">
                <span class="font-bold text-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                    Device Details & Readings
                </span>
                <button onclick="closeModal('deviceDetailModal')" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>
            <div class="p-4 bg-gray-50 space-y-4">
                <div class="border-b pb-3">
                    <h3 class="font-bold mb-2 text-base">Owner & Location</h3>
                    <div class="text-sm space-y-1">
                        <div><span class="font-semibold text-gray-700">User:</span> <span id="deviceModalUser">—</span> (<span id="deviceModalUserPhone">—</span>)</div>
                        <div><span class="font-semibold text-gray-700">Property:</span> <span id="deviceModalLocationName">—</span></div>
                        <div class="text-xs text-gray-500" id="deviceModalLocationAddress">—</div>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-bold mb-2 text-base">Connected Sensors & Readings</h3>
                    <div id="deviceModalSensorList" class="space-y-2 max-h-48 overflow-y-auto">
                        <!-- Sensor data dynamically loaded here -->
                        <p class="text-sm text-gray-500">Loading sensor data...</p>
                    </div>
                </div>

                <button onclick="closeModal('deviceDetailModal')" class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 px-4 rounded-lg font-medium text-sm">Close</button>
            </div>
        </div>
    </div>


    <div id="passwordChangedModal" class="modal-backdrop">
        <div class="bg-white rounded-2xl shadow-2xl w-80 text-center p-8">
            <div class="relative inline-block mb-6">
                <div class="absolute -inset-2 bg-green-100 rounded-full"></div>
                <div class="relative bg-green-500 text-white w-16 h-16 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
            </div>
            <h2 class="text-2xl font-bold mb-2">Password Changed!</h2>
            <p class="text-gray-500 mb-6">Your password has been changed successfully.</p>
            <button onclick="closeModal('passwordChangedModal')" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg">Done</button>
        </div>
    </div>


    <script>
        let mainMapInstance, bfpMapInstance, bfpStationMapInstance, bfpStationMarker;
        // Global cache for user data to speed up modal population
        window.cachedUsers = []; 
        // Embed latest incident data for modal/map (may be null)
        window.latestIncident = <?php echo json_encode($latestInc ?? null); ?>;

        // Server-side: embed all active devices (sensors) with their location coordinates
        <?php
        try {
            $devPdo = $mPdo ?? new PDO($mDsn, $mUser, $mPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $stmtDev = $devPdo->prepare("SELECT s.sensor_ID, s.esp_ip_unique, s.sensor_type, s.status, l.location_ID, l.location_name, l.address, l.latitude, l.longitude FROM sensor s JOIN location l ON s.FK_location_ID = l.location_ID WHERE s.status = 'active'");
            $stmtDev->execute();
            $activeDevices = $stmtDev->fetchAll();
        } catch (Exception $e) {
            $activeDevices = [];
            error_log('Active devices load error: ' . $e->getMessage());
        }
        ?>
        window.activeDevices = <?php echo json_encode($activeDevices ?? []); ?>;
window.bfpStations = <?php echo $bfpStationsJson; ?>;
        function populateAlertModalFromData(data) {
            if (!data) {
                document.getElementById('modalResidentName').textContent = '—';
                document.getElementById('modalResidentPhone').textContent = '—';
                document.getElementById('modalLocationName').textContent = '—';
                document.getElementById('modalLocationAddress').textContent = '—';
                document.getElementById('modalLocationCoords').textContent = '—';
                document.getElementById('modalDate').textContent = '—';
                document.getElementById('modalTime').textContent = '—';
                document.getElementById('modalDeviceInfo').textContent = '—';
                return;
            }
            document.getElementById('modalResidentName').textContent = (data.first_name && data.last_name) ? data.first_name + ' ' + data.last_name : '—';
            document.getElementById('modalResidentPhone').textContent = data.phone_number || '—';
            document.getElementById('modalLocationName').textContent = data.location_name || '—';
            document.getElementById('modalLocationAddress').textContent = data.address || (data.location_name || '—');
            if (data.latitude && data.longitude) {
                document.getElementById('modalLocationCoords').textContent = data.latitude + '°N, ' + data.longitude + '°E';
            } else {
                document.getElementById('modalLocationCoords').textContent = '—';
            }
            if (data.start_timestamp) {
                const d = new Date(data.start_timestamp);
                document.getElementById('modalDate').textContent = d.toLocaleDateString();
                document.getElementById('modalTime').textContent = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            } else {
                document.getElementById('modalDate').textContent = '—';
                document.getElementById('modalTime').textContent = '—';
            }
            document.getElementById('modalDeviceInfo').textContent = data.sensor_type || data.device || '—';
        }

        function initMap(mapId, coords, zoom) {
            const mapContainer = document.getElementById(mapId);
            // Check if map container exists and if Leaflet hasn't already initialized a map here
            if (!mapContainer || mapContainer._leaflet_id) {
                // If the map exists, destroy it before trying to create a new one to prevent errors
                if (mapContainer && mapContainer._leaflet_id) {
                    const existingMap = L.map(mapId);
                    existingMap.remove();
                }
            }

            const map = L.map(mapId).setView(coords, zoom);
            
            // 🛠️ FIX: Use a standard OpenStreetMap tile layer for better visibility of landmarks (museums, markets, etc.)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                attribution: '&copy; OpenStreetMap contributors', 
                maxZoom: 19 
            }).addTo(map);
            
            return map;
        }
        
// --------------------------------------------------------
// BFP STATIONS CRUD JAVASCRIPT LOGIC
// --------------------------------------------------------

        function initBfpStationsMap(lat = 14.110500, lon = 122.959000) {
            const mapId = 'bfpStationMap';
            const mapContainer = document.getElementById(mapId);

            // If a map instance already exists, remove it first (critical for re-init when switching tabs)
            if (bfpStationMapInstance) {
                bfpStationMapInstance.remove();
                bfpStationMapInstance = null;
            }
            // Clear existing Leaflet state from container to prevent "Map container is already initialized" error
            if (mapContainer && mapContainer._leaflet_id !== undefined) {
                mapContainer._leaflet_id = null;
            }
            
            bfpStationMapInstance = initMap(mapId, [lat, lon], 16);
            if (!bfpStationMapInstance) return;

            // Initialize marker
            bfpStationMarker = L.marker([lat, lon], { draggable: true }).addTo(bfpStationMapInstance);
            
            // Update form fields on initialization
            updateStationFormCoords(lat, lon);

            // Event listener: Update coordinates when the map is clicked
            bfpStationMapInstance.on('click', function(e) {
                bfpStationMarker.setLatLng(e.latlng);
                updateStationFormCoords(e.latlng.lat, e.latlng.lng);
            });

            // Event listener: Update coordinates when the marker is dragged
            bfpStationMarker.on('dragend', function(e) {
                const markerLatLng = bfpStationMarker.getLatLng();
                updateStationFormCoords(markerLatLng.lat, markerLatLng.lng);
            });
        }
        
        function updateStationFormCoords(lat, lon) {
            document.getElementById('station-lat').value = lat.toFixed(6);
            document.getElementById('station-lon').value = lon.toFixed(6);
        }
        
        function resetStationForm() {
            document.getElementById('station-crud-form').reset();
            document.getElementById('station-id').value = '';
            document.getElementById('stationFormTitle').textContent = 'Add New Station';
            document.getElementById('form-submit-btn').textContent = 'Add Station';
            
            // Reset map to initial location (Daet BFP Central coordinates)
            const defaultLat = 14.110500;
            const defaultLon = 122.959000;
            
            if (bfpStationMapInstance) {
                bfpStationMapInstance.setView([defaultLat, defaultLon], 16);
                bfpStationMarker.setLatLng([defaultLat, defaultLon]);
                updateStationFormCoords(defaultLat, defaultLon);
            }
        }
        
        async function fetchStations(searchQuery = '') {
            const tableBody = document.getElementById('stations-table-body');
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Loading stations...</td></tr>';
            
            let url = `${API_URL}?endpoint=stations`;
            if (searchQuery) {
                url += `&search=${encodeURIComponent(searchQuery)}`;
            }

            try {
                const res = await fetch(url);
                const data = await res.json();

                if (data.status === 'success' && data.data) {
                    renderStationsTable(data.data);
                } else {
                    tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Error loading data: ${data.message || 'Check API.'}</td></tr>`;
                }
            } catch (e) {
                tableBody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-red-500">Network error fetching stations.</td></tr>`;
                console.error('Fetch stations error:', e);
            }
        }
        
        function renderStationsTable(stations) {
            const tableBody = document.getElementById('stations-table-body');
            tableBody.innerHTML = '';

            if (stations.length === 0) {
                 tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-500">No stations found.</td></tr>';
                 return;
            }

            stations.forEach(station => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                
                const latText = parseFloat(station.latitude).toFixed(6);
                const lonText = parseFloat(station.longitude).toFixed(6);
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${station.station_name}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${station.contact_number || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">${latText}, ${lonText}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button onclick="editStation(${station.station_ID})" class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</button>
                        <button onclick="deleteStation(${station.station_ID}, '${station.station_name}')" class="text-red-600 hover:text-red-900">Delete</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }

// ============================================================================
// FIXED: loadExternalContent with proper form handling
// ============================================================================

async function loadExternalContent(url, contentId, buttonElement) {
    const placeholder = document.getElementById('external-content-placeholder');
    showContent(contentId, buttonElement);
    placeholder.innerHTML = '<p class="text-center text-gray-500 py-10">Loading...</p>';
    
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'text/html' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const html = await response.text();
        placeholder.innerHTML = html; // Insert the HTML first

        // =========================================================
        // FIX: Reliable script execution after injecting HTML
        // =========================================================
        const scripts = Array.from(placeholder.querySelectorAll('script'));
        scripts.forEach(script => {
            if (script.src) {
                // If it's an external script, create and append a new one
                const newScript = document.createElement('script');
                newScript.src = script.src;
                script.parentNode.removeChild(script); // Remove the old one
                document.head.appendChild(newScript); // Append to head or body
            } else {
                // If it's an inline script, execute it immediately
                try {
                    // Use a direct execution method (eval or new Function)
                    (new Function(script.textContent))();
                } catch (e) {
                    console.error('Error executing inline script:', e);
                }
            }
        });

        // Wait a short moment to ensure the DOM has settled and any synchronous code ran
        await new Promise(resolve => setTimeout(resolve, 50)); 
        
        // ========================================
        // IMPORTANT: Intercept form submissions
        // ========================================
        interceptFormSubmissions(url);
        
        // Initialize all event listeners
        initializeContentActions();
        
        console.log('✅ External content loaded and initialized');
        
    } catch (e) {
        console.error('Load error:', e);
        placeholder.innerHTML = `
            <div class="p-6 bg-red-100 border border-red-400 text-red-700 rounded">
                <p><strong>Error:</strong> ${e.message}</p>
                <p class="text-sm mt-2">File: ${url}</p>
                <p class="text-sm">Check browser console for details</p>
            </div>
        `;
    }

    if (buttonElement) {
        console.log('🔘 Button Element:', buttonElement);
        console.log('🌐 URL Loaded:', url);
        // Check if its Analytic Report
        if (url.includes('reports.php')) {
            console.log('📈 Initializing trend charts for report');
            setTrendCharts();
            setTypesCharts();
            console.log('📊 Trend charts initialized for report');
        }
    }
}
// ============================================================================
// Intercept Form Submissions - Keep content in modal instead of redirecting
// ============================================================================
function interceptFormSubmissions(sourceUrl) {
    const placeholder = document.getElementById('external-content-placeholder');
    const forms = placeholder.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const method = (form.getAttribute('method') || 'POST').toUpperCase();
            const action = form.getAttribute('action') || sourceUrl;
            
            try {
                let fetchOptions = {
                    method: method,
                    headers: { 'Accept': 'text/html' }
                };
                
                // For GET requests, append data to URL as query string
                let fetchUrl = action;
                if (method === 'GET') {
                    // Convert FormData to URL parameters
                    const params = new URLSearchParams();
                    for (let [key, value] of formData.entries()) {
                        params.append(key, value);
                    }
                    const separator = fetchUrl.includes('?') ? '&' : '?';
                    fetchUrl = fetchUrl + separator + params.toString();
                } else {
                    // For POST, use form data in body
                    fetchOptions.body = formData;
                }
                
                const response = await fetch(fetchUrl, fetchOptions);
                const html = await response.text();
                placeholder.innerHTML = html;
                
                // Re-execute scripts in the new content
                await new Promise(resolve => setTimeout(resolve, 150));
                const scripts = placeholder.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
                
                // Re-initialize everything
                await new Promise(resolve => setTimeout(resolve, 150));
                interceptFormSubmissions(sourceUrl);
                initializeContentActions();
                
                console.log('✅ Form submitted and content updated');
                
            } catch (e) {
                console.error('Form submission error:', e);
                alert('Error submitting form: ' + e.message);
            }
        });
    });
}

// ============================================================================
// Create Global Wrapper Functions for onclick handlers
// These will be called by the loaded PHP content's onclick attributes
// ============================================================================

// User Management functions
window.openUserModal = function(mode, userData) {
    const placeholder = document.getElementById('external-content-placeholder');
    const userModal = placeholder.querySelector('#user-modal');
    const userForm = placeholder.querySelector('#user-form');
    
    if (!userModal) {
        console.error('User modal not found');
        return;
    }
    
    const title = placeholder.querySelector('#modal-title');
    const actionInput = placeholder.querySelector('#modal-action');
    const userIdInput = placeholder.querySelector('#modal-user-id');
    const submitBtn = placeholder.querySelector('#modal-submit-btn');
    const passwordInput = placeholder.querySelector('#password');
    const passwordRequired = placeholder.querySelector('#password-required');
    const passwordHint = placeholder.querySelector('#password-hint');
    
    userForm.reset();
    if (passwordInput) passwordInput.removeAttribute('required');
    if (passwordRequired) passwordRequired.classList.remove('hidden');
    if (passwordHint) passwordHint.classList.add('hidden');
    
    if (mode === 'create') {
        title.textContent = 'Add New Account';
        actionInput.value = 'create';
        userIdInput.value = '';
        submitBtn.textContent = 'Create Account';
        if (passwordInput) passwordInput.setAttribute('required', 'required');
    } else if (mode === 'edit' && userData) {
        title.textContent = `Edit Account: ${userData.first_name} ${userData.last_name}`;
        actionInput.value = 'update';
        userIdInput.value = userData.user_ID;
        submitBtn.textContent = 'Save Changes';
        
        placeholder.querySelector('#first_name').value = userData.first_name;
        placeholder.querySelector('#last_name').value = userData.last_name;
        placeholder.querySelector('#email').value = userData.email;
        placeholder.querySelector('#phone_number').value = userData.phone_number;
        placeholder.querySelector('#role').value = userData.role;
        
        if (passwordRequired) passwordRequired.classList.add('hidden');
        if (passwordHint) passwordHint.classList.remove('hidden');
        if (passwordInput) passwordInput.value = '';
    }
    
    userModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    console.log('✅ User modal opened');
};

window.closeUserModal = function() {
    const placeholder = document.getElementById('external-content-placeholder');
    const userModal = placeholder.querySelector('#user-modal');
    if (userModal) {
        userModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
};

window.showDeleteConfirmation = function(userId, fullName) {
    console.log('🔔 showDeleteConfirmation called with userId:', userId, 'fullName:', fullName);
    const placeholder = document.getElementById('external-content-placeholder');
    const deleteModal = placeholder.querySelector('#delete-modal');

    console.log('🔔 Preparing to show delete confirmation for user ID:', userId);
    
    if (!deleteModal) {
        console.error('Delete modal not found');
        return;
    }
    
    placeholder.querySelector('#delete-user-id').value = userId;

    console.log('🔔 Setting delete confirmation name to:', fullName);
    placeholder.querySelector('#delete-user-name').textContent = fullName;
    console.log('🔔 Delete confirmation details set for user ID:', userId);
    deleteModal.classList.remove('hidden');
    console.log('🔔 Delete confirmation modal displayed for user ID:', userId);
    document.body.classList.add('overflow-hidden');
    console.log('✅ Delete confirmation modal opened');
};

window.closeDeleteConfirmation = function() {
    const placeholder = document.getElementById('external-content-placeholder');
    const deleteModal = placeholder.querySelector('#delete-modal');
    if (deleteModal) {
        deleteModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
};

function getDistance(latlng1, latlng2) {
    const R = 6371000; // Earth's radius in meters
    const dLat = (latlng2.lat - latlng1.lat) * Math.PI / 180;
    const dLon = (latlng2.lng - latlng1.lng) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(latlng1.lat * Math.PI / 180) * Math.cos(latlng2.lat * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Device Management functions
window.openDeviceModal = function(mode, deviceData) {
    const placeholder = document.getElementById('external-content-placeholder');
    const deviceModal = placeholder.querySelector('#device-modal');
    
    if (!deviceModal) {
        console.error('Device modal not found');
        return;
    }
    
    const title = placeholder.querySelector('#modal-title');
    const actionInput = placeholder.querySelector('#modal-action');
    const sensorIdInput = placeholder.querySelector('#modal-sensor-id');
    const submitBtn = placeholder.querySelector('#modal-submit-btn');
    const deviceForm = placeholder.querySelector('#device-form');
    
    if (deviceForm) deviceForm.reset();
    
    if (mode === 'create') {
        title.textContent = 'Add New Device';
        actionInput.value = 'create';
        if (sensorIdInput) sensorIdInput.value = '';
        submitBtn.textContent = 'Create Device';
    } else if (mode === 'edit' && deviceData) {
        title.textContent = `Edit Device: ${deviceData.esp_ip_unique}`;
        actionInput.value = 'update';
        if (sensorIdInput) sensorIdInput.value = deviceData.sensor_ID;
        submitBtn.textContent = 'Save Changes';
        
        const espIpInput = placeholder.querySelector('#esp_ip_unique');
        if (espIpInput) espIpInput.value = deviceData.esp_ip_unique;
        
        const sensorTypeSelect = placeholder.querySelector('#sensor_type');
        if (sensorTypeSelect) sensorTypeSelect.value = deviceData.sensor_type;
        
        const statusSelect = placeholder.querySelector('#status');
        if (statusSelect) statusSelect.value = deviceData.status;
    }
    
    deviceModal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    console.log('✅ Device modal opened');
};

window.closeDeviceModal = function() {
    const placeholder = document.getElementById('external-content-placeholder');
    const deviceModal = placeholder.querySelector('#device-modal');
    if (deviceModal) {
        deviceModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
};

// Reports functions - Handle both incident modal and delete
window.openIncidentModal = function(id) {
    const placeholder = document.getElementById('external-content-placeholder');
    const modal = placeholder.querySelector('#incident-modal');
    
    if (!modal) {
        console.error('Incident modal not found');
        return;
    }
    
    const title = placeholder.querySelector('#incident-modal-title');
    const idInput = placeholder.querySelector('#incident-id');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    if (id) {
        title.textContent = 'Edit Incident';
        if (idInput) idInput.value = id;
    } else {
        title.textContent = 'New Incident';
        if (idInput) idInput.value = '';
    }
    
    console.log('✅ Incident modal opened');
};

window.closeIncidentModal = function() {
    const placeholder = document.getElementById('external-content-placeholder');
    const modal = placeholder.querySelector('#incident-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
};

// Delete incident function for reports
window.deleteIncident = function(id) {
    if (!confirm('Delete this incident?')) return;
    
    const placeholder = document.getElementById('external-content-placeholder');
    const API_URL = 'api_bfp.php';
    
    fetch(`${API_URL}?endpoint=incidents`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ incident_ID: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Incident deleted successfully');
            // Reload reports content
            loadExternalContent('reports.php', 'external', null);
        } else {
            alert('Delete failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(e => {
        console.error('Delete error:', e);
        alert('Error deleting incident');
    });
};

// Submit incident form function
window.submitIncidentForm = function(e) {
    if (e) e.preventDefault();
    
    const placeholder = document.getElementById('external-content-placeholder');
    const API_URL = 'api_bfp.php';
    
    const id = placeholder.querySelector('#incident-id').value;
    const sensor = placeholder.querySelector('#incident-sensor').value;
    const level = placeholder.querySelector('#incident-level').value;
    
    if (!sensor) {
        alert('Sensor ID is required');
        return;
    }
    
    if (!id) {
        // Create
        fetch(`${API_URL}?endpoint=incidents`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ FK_sensor_ID: sensor, incident_level: level })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                window.closeIncidentModal();
                loadExternalContent('reports.php', 'external', null);
            } else {
                alert('Create failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(e => {
            console.error('Create error:', e);
            alert('Error creating incident');
        });
    } else {
        // Update
        fetch(`${API_URL}?endpoint=incidents`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ incident_ID: id, incident_level: level })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                window.closeIncidentModal();
                loadExternalContent('reports.php', 'external', null);
            } else {
                alert('Update failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(e => {
            console.error('Update error:', e);
            alert('Error updating incident');
        });
    }
};

// ============================================================================
// Initialize All Content Actions
// ============================================================================
function initializeContentActions() {
    console.log('🔧 Initializing content actions...');
    
    const placeholder = document.getElementById('external-content-placeholder');
    
    // ========== USER MANAGEMENT SEARCH ==========
    const userSearchForm = placeholder.querySelector('form[method="GET"]');
    if (userSearchForm) {
        // Form already has submit listener from interceptFormSubmissions
        console.log('✅ User search form ready');
    }
    
    // ========== DEVICE MANAGEMENT SEARCH ==========
    const deviceSearchInput = placeholder.querySelector('#device-search-input');
    if (deviceSearchInput) {
        deviceSearchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const tableRows = placeholder.querySelectorAll('#device-table-body tr');
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            console.log(`🔍 Device search: ${visibleCount} results`);
        });
        console.log('✅ Device search initialized');
    }
    
    // ========== REPORTS - Period buttons and charts ==========
    const periodButtons = placeholder.querySelectorAll('.period-btn');
    if (periodButtons.length > 0) {
        periodButtons.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const period = btn.getAttribute('data-period');
                
                try {
                    const response = await fetch(`api_bfp.php?endpoint=incident_trends&period=${period}&owner=resident`);
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        // Update trends chart
                        const trendsChart = window.trendsChart;
                        if (trendsChart) {
                            trendsChart.data.labels = data.by_date.map(r => r.dt);
                            trendsChart.data.datasets[0].data = data.by_date.map(r => Number(r.cnt));
                            trendsChart.update();
                        }
                        
                        // Update types chart
                        const typesChart = window.typesChart;
                        if (typesChart) {
                            const typeKeys = Object.keys(data.by_type || {});
                            const typeVals = typeKeys.map(k => data.by_type[k]);
                            typesChart.data.labels = typeKeys;
                            typesChart.data.datasets[0].data = typeVals;
                            typesChart.update();
                        }
                        
                        // Update button states
                        periodButtons.forEach(b => {
                            if (b.getAttribute('data-period') === period) {
                                b.classList.remove('bg-slate-200', 'text-slate-600');
                                b.classList.add('bg-red-500', 'text-white');
                            } else {
                                b.classList.remove('bg-red-500', 'text-white');
                                b.classList.add('bg-slate-200', 'text-slate-600');
                            }
                        });
                        
                        console.log(`✅ Period changed to ${period}`);
                    }
                } catch (e) {
                    console.error('Period filter error:', e);
                }
            });
        });
        console.log('✅ Reports period buttons initialized');
    }
    
    // ========== REPORTS - Incident form ==========
    const incidentForm = placeholder.querySelector('#incident-form');
    if (incidentForm) {
        incidentForm.addEventListener('submit', window.submitIncidentForm);
        console.log('✅ Incident form initialized');
    }
    
    // ========== PAGINATION LINKS ==========
    const paginationLinks = placeholder.querySelectorAll('a[href*="?page="]');
    paginationLinks.forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const href = link.getAttribute('href');
            
            try {
                const response = await fetch(href, {
                    method: 'GET',
                    headers: { 'Accept': 'text/html' }
                });
                
                const html = await response.text();
                placeholder.innerHTML = html;
                
                // Re-execute scripts
                await new Promise(resolve => setTimeout(resolve, 150));
                const scripts = placeholder.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
                
                // Re-initialize
                await new Promise(resolve => setTimeout(resolve, 150));
                interceptFormSubmissions(href.split('?')[0]);
                initializeContentActions();
                
                console.log('✅ Pagination updated');
            } catch (e) {
                console.error('Pagination error:', e);
            }
        });
    });
    
    console.log('✅ All content actions initialized');
}

// ============================================================================
// Keep existing functions from before
// ============================================================================

function showContent(contentId, buttonElement) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });
    
    const activeContent = document.getElementById('content-' + contentId);
    if (activeContent) {
        activeContent.style.display = 'block';
    }
    
    if (buttonElement) {
        document.querySelectorAll('.nav-button').forEach(btn => {
            btn.classList.remove('bg-red-600', 'text-white', 'bg-gray-700');
            btn.classList.add('text-gray-300');
        });
        
        buttonElement.classList.add('bg-red-600', 'text-white');
        buttonElement.classList.remove('text-gray-300', 'hover:bg-gray-800');
        
        const parentDropdown = buttonElement.closest('#dropdown-content');
        if (parentDropdown) {
            parentDropdown.previousElementSibling.classList.remove('text-gray-300');
            parentDropdown.previousElementSibling.classList.add('bg-gray-700', 'text-white');
        }
    }
}

function loadPage(pageUrl, buttonElement) {
    // Only handle the logout page for a full redirect
    if (pageUrl === 'logoutdesk.php') {
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = pageUrl;
        }
    } 
    // All other internal pages should now use loadExternalContent
}
        
        function searchStations() {
            const query = document.getElementById('station-search-input').value;
            fetchStations(query);
        }

        async function editStation(id) {
            try {
                const res = await fetch(`${API_URL}?endpoint=stations&id=${id}`);
                const data = await res.json();
                
                if (data.status === 'success' && data.data && data.data[0]) {
                    const station = data.data[0];
                    document.getElementById('station-id').value = station.station_ID;
                    document.getElementById('station-name').value = station.station_name;
                    document.getElementById('station-contact').value = station.contact_number;
                    
                    const lat = parseFloat(station.latitude);
                    const lon = parseFloat(station.longitude);
                    
                    updateStationFormCoords(lat, lon);
                    
                    // Update map and marker
                    if (bfpStationMapInstance) {
                        bfpStationMapInstance.setView([lat, lon], 16);
                        bfpStationMarker.setLatLng([lat, lon]);
                    }
                    
                    document.getElementById('stationFormTitle').textContent = `Editing: ${station.station_name}`;
                    document.getElementById('form-submit-btn').textContent = 'Update Station';

                } else {
                    alert('Failed to load station details.');
                }
            } catch (e) {
                alert('Network error while fetching station.');
                console.error('Edit station error:', e);
            }
        }

        async function deleteStation(id, name) {
            if (!confirm(`Are you sure you want to delete the station: ${name} (ID: ${id})?`)) {
                return;
            }
            
            try {
                const res = await fetch(`${API_URL}?endpoint=stations`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ station_ID: id })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    alert(`Station ${name} deleted successfully.`);
                    fetchStations(); // Refresh the table
                    resetStationForm();
                } else {
                    alert('Deletion failed: ' + (data.message || 'unknown'));
                }
            } catch (e) {
                alert('Network error during deletion.');
                console.error('Delete station error:', e);
            }
        }

        document.getElementById('station-crud-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const stationId = document.getElementById('station-id').value;
            const isUpdate = !!stationId;

            const payload = {
                station_name: document.getElementById('station-name').value,
                contact_number: document.getElementById('station-contact').value,
                latitude: document.getElementById('station-lat').value,
                longitude: document.getElementById('station-lon').value
            };
            
            let method, url;
            if (isUpdate) {
                method = 'PUT';
                url = `${API_URL}?endpoint=stations`;
                payload.station_ID = stationId;
            } else {
                method = 'POST';
                url = `${API_URL}?endpoint=stations`;
            }

            try {
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.status === 'success') {
                    alert(`Station ${isUpdate ? 'updated' : 'added'} successfully!`);
                    resetStationForm();
                    fetchStations();
                } else {
                    alert(`${isUpdate ? 'Update' : 'Add'} failed: ` + (data.message || 'unknown'));
                }
            } catch (e) {
                alert('Network error during submission.');
                console.error('Form submission error:', e);
            }
        });

function showContent(contentId, buttonElement) {
    // Hide all content sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Show the requested content
    const activeContent = document.getElementById('content-' + contentId);
    if (activeContent) {
        activeContent.style.display = 'block';
    }
    
    // Update navigation highlighting
    if (buttonElement) {
        document.querySelectorAll('.nav-button').forEach(btn => {
            btn.classList.remove('bg-red-600', 'text-white', 'bg-gray-700'); // Remove bg-gray-700 too
            btn.classList.add('text-gray-300');
        });
        
        buttonElement.classList.add('bg-red-600', 'text-white');
        buttonElement.classList.remove('text-gray-300', 'hover:bg-gray-800');
        
        // If the button is inside a dropdown, highlight the parent too
        const parentDropdown = buttonElement.closest('#dropdown-content');
        if (parentDropdown) {
             // Highlight the parent dropdown button without changing its text color
            parentDropdown.previousElementSibling.classList.remove('text-gray-300');
            parentDropdown.previousElementSibling.classList.add('bg-gray-700', 'text-white');
        }
    }
    
    // Refresh map if dashboard is shown
    if (contentId === 'dashboard') {
        setTimeout(() => {
            if (mainMapInstance) mainMapInstance.invalidateSize();
        }, 10);
    }
    
    // Initialize BFP map if needed
    if (contentId === 'bfp-stations') {
        setTimeout(() => {
            const mapContainer = document.getElementById('bfpStationMap');
            // FIX: If the map hasn't been initialized or was destroyed, re-initialize it.
            // Leaflet map needs the container to be visible before initialization.
            if (!bfpStationMapInstance || !mapContainer._leaflet_id) {
                initBfpStationsMap(); // Initialize the map on first load/re-load
            } else {
                // If it exists, ensure it refreshes its visual size
                bfpStationMapInstance.invalidateSize();
                // Recenter it just in case
                bfpStationMapInstance.setView(bfpStationMarker.getLatLng(), 16);
            }
            fetchStations(); // Load the station table data
        }, 10);
    }
}
        
        function toggleDropdown(buttonElement) {
            const dropdown = document.getElementById('dropdown-content');
            const arrow = document.getElementById('dropdown-arrow');
            dropdown.classList.toggle('active');
            arrow.classList.toggle('rotated');
            
            // If the dropdown is closing AND no sub-item is active, remove the gray background
            if(!dropdown.classList.contains('active')) {
                const isActiveSubItem = dropdown.querySelector('.bg-red-600');
                if (!isActiveSubItem) {
                    buttonElement.classList.remove('bg-gray-700');
                }
            } else {
                // If opening, set a subtle background
                 buttonElement.classList.add('bg-gray-700');
            }
        }

        function updateDateTime() {
            const now = new Date();
            const date = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const time = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            document.getElementById('datetime').innerHTML = `📅 ${date} | ${time}`;
        }
        
        // Modal Functions
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        // Note: tapAlert, respondAlert and dismissAlert are implemented above to support modal population and API actions.
        // Function to handle the "Dismiss" button (False Alarm)
async function dismissAlert() {
    if (!currentIncidentId) {
        closeModal('alertModal');
        return;
    }

    // specific confirmation for dismissal
    if (!confirm('Mark this alert as a FALSE ALARM (Rejected)?')) {
        return;
    }

    try {
        // Call the NEW endpoint
        const res = await fetch(`${API_URL}?endpoint=dismiss_alert`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ incidentId: currentIncidentId })
        });

        const data = await res.json();

        if (data.status === 'success') {
            alert('Alert marked as False Alarm.');
            closeModal('alertModal');
            updateDashboardFromAPI(); // Refresh UI
        } else {
            alert('Failed to dismiss: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        console.error('Dismiss error:', e);
        alert('Network error.');
    }
}
        function showPasswordChangedModal() { document.getElementById('passwordChangedModal').classList.add('active'); }
        function exitApp() { if (confirm('Are you sure you want to Logout?')) { alert('Signing Out...'); } }

        // window.addEventListener('load', function() {
        //     updateDateTime();
        //     // setInterval(updateDateTime, 60000);
            
        //     mainMapInstance = initMap('map', [14.1044, 122.9442], 14);
        //     const fireIcon = L.divIcon({ className: 'custom-fire-marker', html: '<div style="font-size:32px; animation: pulse 1.5s infinite;">🔥</div>', iconSize: [40, 40], iconAnchor: [20, 20] });

        //     // Prefer to place the marker at the incident's real coordinates if available
        //     try {
        //         const inc = window.latestIncident || null;
        //         if (inc && inc.latitude && inc.longitude && !isNaN(parseFloat(inc.latitude)) && !isNaN(parseFloat(inc.longitude))) {
        //             const lat = parseFloat(inc.latitude);
        //             const lon = parseFloat(inc.longitude);
        //             const marker = L.marker([lat, lon], { icon: fireIcon }).addTo(mainMapInstance);
        //             const title = (inc.incident_level ? (inc.incident_level.charAt(0).toUpperCase() + inc.incident_level.slice(1)) : 'Alert') + ' - ' + (inc.sensor_type || 'Device');
        //             const addr = inc.address || inc.location_name || 'Unknown location';
        //             const coordsText = (lat.toFixed(6) + '°, ' + lon.toFixed(6) + '°');
        //             marker.bindPopup(`<b>${title}</b><br>${addr}<br><small>${coordsText}</small>`).openPopup();
        //             // center map on the incident
        //             mainMapInstance.setView([lat, lon], 16);
        //             // 1. REMOVED: L.circle([lat, lon], { color: 'red', fillColor: '#f03', fillOpacity: 0.15, radius: 200 }).addTo(mainMapInstance);
        //         } else {
        //             // fallback: place a default marker at center
        //             const marker = L.marker([14.1044, 122.9442], { icon: fireIcon }).addTo(mainMapInstance);
        //             marker.bindPopup('<b>Fire Alert</b><br>No active incident').openPopup();
        //         }

        //         // Render all active devices as small markers
        //         try {
        //             const devices = window.activeDevices || [];
        //             const deviceIcon = L.divIcon({ className: 'device-marker', html: '<div style="width:18px;height:18px;border-radius:50%;background:#38bdf8;border:2px solid white;"></div>', iconSize: [18, 18], iconAnchor: [9, 9] });
        //             window._deviceMarkers = window._deviceMarkers || [];
        //             devices.forEach(d => {
        //                 try {
        //                     const lat = parseFloat(d.latitude);
        //                     const lon = parseFloat(d.longitude);
        //                     if (!isNaN(lat) && !isNaN(lon)) {
        //                         const m = L.marker([lat, lon], { icon: deviceIcon }).addTo(mainMapInstance);
        //                         const title = (d.location_name || d.address || 'Device');
        //                         const popup = `<b>${title}</b><br>${d.sensor_type || ''}<br>Sensor ID: ${d.sensor_ID}`;
        //                         m.bindPopup(popup);
        //                         window._deviceMarkers.push(m);
        //                     }
        //                 } catch (e) { /* ignore per-device errors */ }
        //             });
        //         } catch (e) { console.error('render active devices error', e); }
        //     } catch (e) {
        //         const marker = L.marker([14.1044, 122.9442], { icon: fireIcon }).addTo(mainMapInstance);
        //         marker.bindPopup('<b>Fire Alert</b><br>Error loading incident').openPopup();
        //         console.error('Map incident placement error', e);
        //     }

        //     showContent('dashboard', document.querySelector('button[onclick*="dashboard"]'));
        // });
window.addEventListener('load', function() {
            updateDateTime();
            
            // 1. Initialize Map
            mainMapInstance = initMap('map', [14.1117, 122.9496], 13); // Default view

            // 2. Define Icons
            const fireIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/512/9312/9312231.png', 
                iconSize: [60, 60],
                iconAnchor: [30, 60],
                popupAnchor: [0, -60]
            });

            const houseIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/512/619/619032.png',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            const stationIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/512/2237/2237536.png', 
                iconSize: [45, 45],
                iconAnchor: [22, 45],
                popupAnchor: [0, -45]
            });

            // Variables used for the route
            let destinationLatLng = null; // The House (Target)
            let startStationLatLng = null; // The Station (Source)
            let minDistance = Infinity;

            // 3. FIND TARGET LOCATION (Incident OR First Device)
            try {
                const inc = window.latestIncident || null;
                
                if (inc && inc.latitude && inc.longitude && !isNaN(parseFloat(inc.latitude))) {
                    // CASE A: Real Incident
                    const lat = parseFloat(inc.latitude);
                    const lon = parseFloat(inc.longitude);
                    destinationLatLng = L.latLng(lat, lon);
                    
                    L.marker(destinationLatLng, { icon: fireIcon }).addTo(mainMapInstance)
                       .bindPopup(`
    <b style="color:red">🔥 ${inc.incident_level || 'Alert'}</b><br>
    ${inc.address}<br>
    <b>Lat:</b> ${lat}<br>
    <b>Lng:</b> ${lon}
`)

                    
                    L.circle(destinationLatLng, { color: 'red', radius: 150 }).addTo(mainMapInstance);

                } else {
                    // CASE B: No Incident -> Connect to the FIRST available device
                    const devices = window.activeDevices || [];
                    for(let d of devices) {
                        const dLat = parseFloat(d.latitude);
                        const dLon = parseFloat(d.longitude);
                        if (!isNaN(dLat) && !isNaN(dLon)) {
                            destinationLatLng = L.latLng(dLat, dLon);
                            break; // Stop at the first valid device
                        }
                    }
                    // Fallback to center if absolutely no devices found
                    if(!destinationLatLng) destinationLatLng = L.latLng(14.1117, 122.9496);
                }
            } catch (e) { console.error('Target point error', e); }

            // 4. PLOT STATIONS & FIND NEAREST
            try {
                const stations = window.bfpStations || [];
                stations.forEach(s => {
                    const lat = parseFloat(s.latitude);
                    const lon = parseFloat(s.longitude);
                    
                    if (!isNaN(lat) && !isNaN(lon)) {
                        const stationPos = L.latLng(lat, lon);
                        L.marker(stationPos, { icon: stationIcon }).addTo(mainMapInstance)
                           L.marker(stationPos, { icon: stationIcon }).addTo(mainMapInstance)
    .bindPopup(`
        <b>🏢 ${s.station_name}</b><br>
        Available<br>
        <b>Lat:</b> ${lat}<br>
        <b>Lng:</b> ${lon}
    `);


                        // Find closest station to the target
                        if (destinationLatLng) {
                            const dist = mainMapInstance.distance(destinationLatLng, stationPos);
                            if (dist < minDistance) {
                                minDistance = dist;
                                startStationLatLng = stationPos;
                            }
                        }
                    }
                });

                

                // 5. DRAW THE RED ROAD (Station -> House)
                // if (destinationLatLng && startStationLatLng) {
                    
                //     if (typeof L.Routing !== 'undefined') {
                //         const control = L.Routing.control({
                //             waypoints: [
                //                 startStationLatLng, // Start: Nearest Station
                //                 destinationLatLng   // End: House/Incident
                //             ],
                //             router: L.Routing.osrmv1({
                //                 serviceUrl: 'https://router.project-osrm.org/route/v1'
                //             }),
                //             lineOptions: {
                //                 styles: [{color: '#ef4444', opacity: 0.9, weight: 7}] // THICK RED LINE
                //             },
                //             createMarker: function() { return null; }, // Hide default routing markers
                //             addWaypoints: false,
                //             draggableWaypoints: false,
                //             fitSelectedRoutes: true, // Auto-zoom to show full path
                //             show: false // Hide text instructions box
                //         }).addTo(mainMapInstance);

                //         // Force map to fit both points perfectly
                //         const bounds = L.latLngBounds([startStationLatLng, destinationLatLng]);
                //         mainMapInstance.fitBounds(bounds, { padding: [100, 100] });
                //     }
                // }

            } catch (e) { console.error('Routing error:', e); }

            // 6. Plot Other Active Devices
            try {
                const devices = window.activeDevices || [];
                devices.forEach(d => {
                    try {
                        const lat = parseFloat(d.latitude);
                        const lon = parseFloat(d.longitude);
                        if (!isNaN(lat) && !isNaN(lon)) {
                            // Avoid duplicate marker on top of the destination
                            if(destinationLatLng && destinationLatLng.lat === lat && destinationLatLng.lng === lon) return;
                            
                            L.marker([lat, lon], { icon: houseIcon }).addTo(mainMapInstance)
                                .bindPopup(`
    <b>🏠 ${d.location_name || 'Device'}</b><br>
    <b>Lat:</b> ${lat}<br>
    <b>Lng:</b> ${lon}
`)

                        }
                    } catch (e) { /* ignore */ }
                });
            } catch (e) { console.error('Device plot error', e); }

            showContent('dashboard', document.querySelector('button[onclick*="dashboard"]'));
        });
        window.cachedUsers = [];
        window.latestIncident = <?php echo $incDataJson; ?>;
        window.activeDevices = <?php echo $activeDevicesJson; ?>;
    </script>
</body>
</html>