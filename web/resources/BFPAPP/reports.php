<?php
// reports.php - dynamic analytic report using the database
// Adjust DB credentials if different from api_bfp.php
require_once "config.php";
//$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
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

if (isset($_GET['endpoint'])) {
    // ... (rest of API router setup/logic and full switch/case block) ...
    
    // API headers and settings
    header('Content-Type: application/json');
    // ... (other API headers) ...

    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    $response = ['status' => 'error', 'message' => 'Invalid Request']; 
    
    // --- FULL API ROUTER LOGIC HERE ---
    // switch ($endpoint) {
    //   // ... (all cases) ...
    //   case 'incidents':
    //     $is_bfp_role = isset($_SESSION['role']) && ($_SESSION['role'] === 'bfp_officer' || $_SESSION['role'] === 'bfp_assigned_at_desk');
    //     if (!$is_bfp_role) { http_response_code(401); $response = ['status' => 'error', 'message' => 'Authentication required.']; break; }
    //     $response = handleIncidentsCrud($pdo, $method, $input);
    //     break;
    //   // ... (all other cases) ...
    //   default: http_response_code(404); $response = ['status' => 'error', 'message' => 'Endpoint not found.']; break;
    // }
    
    echo json_encode($response);
    exit; // CRITICAL: Stop here to prevent cancellation/HTML output.
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

// Metrics
try {
    $totalIncidents = (int)$pdo->query("SELECT COUNT(*) FROM incident_log")->fetchColumn();

    $avgResponse = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, start_timestamp, end_timestamp)) FROM incident_log WHERE end_timestamp IS NOT NULL AND end_timestamp <> '0000-00-00 00:00:00'")
        ->fetchColumn();
    $avgResponse = $avgResponse !== null ? round($avgResponse, 1) : 0;

    // False alarms heuristic: incident_level = 'LOW' OR status indicates false alarm
    $falseAlarms = (int)$pdo->query("SELECT COUNT(*) FROM incident_log WHERE incident_level = 'LOW' OR status = 'FALSE_ALARM'")
        ->fetchColumn();

    $activeDevices = (int)$pdo->query("SELECT COUNT(*) FROM sensor WHERE status = 'ACTIVE'")
        ->fetchColumn();

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

} catch (\PDOException $e) {
    http_response_code(500);
    echo "<h1>Query error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytic Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const API_URL = 'api_bfp.php';
// Embedded fallback data from server (used when API is unreachable or auth blocks client)
const FALLBACK_TRENDS = <?php echo json_encode($trendsByDate ?? []); ?>;
const FALLBACK_TYPES = <?php echo json_encode($typesBy ?? (object)[]); ?>;

// Charts
let trendsChart = null;
let typesChart = null;
let currentPeriod = 'monthly';

// Override fetch temporarily for debugging
const originalFetch = window.fetch;
window.fetch = function(...args) {
    console.log('Fetching:', args[0]);
    return originalFetch.apply(this, args).then(response => {
        response.clone().text().then(text => {
            if (!response.headers.get('content-type')?.includes('application/json')) {
                console.warn('Non-JSON response received:', text.substring(0, 300));
            }
        });
        return response;
    });
};

function createCharts() {
    const tCtx = document.getElementById('trendsChart').getContext('2d');
    trendsChart = new Chart(tCtx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Incidents', data: [], borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.12)', fill: true }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    const pCtx = document.getElementById('typesChart').getContext('2d');
    typesChart = new Chart(pCtx, {
        type: 'doughnut',
        data: { labels: [], datasets: [{ data: [], backgroundColor: ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#9ca3af'] }] },
        options: { responsive: true }
    });
}
async function fetchTrends(period = 'monthly') {
    try {
        const res = await fetch(`${API_URL}?endpoint=incident_trends&period=${period}&owner=resident`);
        
        // Check if response is JSON
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await res.text();
            console.error('API returned non-JSON response:', text.substring(0, 200));
            throw new Error('API returned non-JSON response');
        }
        
        const data = await res.json();
        if (data.status === 'success') {
            const labels = data.by_date.map(r => r.dt);
            const values = data.by_date.map(r => Number(r.cnt));
            trendsChart.data.labels = labels;
            trendsChart.data.datasets[0].data = values;
            trendsChart.update();

            const typeKeys = Object.keys(data.by_type || {});
            const typeVals = typeKeys.map(k => data.by_type[k]);
            typesChart.data.labels = typeKeys;
            typesChart.data.datasets[0].data = typeVals;
            typesChart.update();
            return;
        }
        console.warn('Trend API returned non-success, falling back to embedded data', data);
    } catch (e) {
        console.warn('Failed to fetch trends, using embedded fallback', e.message || e);
    }

    // Fallback: use server-rendered embedded data
    try {
        const labels = FALLBACK_TRENDS.map(r => r.dt);
        const values = FALLBACK_TRENDS.map(r => Number(r.cnt));
        trendsChart.data.labels = labels;
        trendsChart.data.datasets[0].data = values;
        trendsChart.data.datasets[0].label = 'Incidents (Fallback)';
        trendsChart.update();

        const typeKeys = Object.keys(FALLBACK_TYPES || {});
        const typeVals = typeKeys.map(k => FALLBACK_TYPES[k]);
        typesChart.data.labels = typeKeys;
        typesChart.data.datasets[0].data = typeVals;
        typesChart.update();
    } catch (e) {
        console.error('Failed to render fallback trends', e);
    }
}


async function fetchIncidents() {
    try {
        const res = await fetch(`${API_URL}?endpoint=incidents&owner=resident`);
        
        // Check for non-200 HTTP status
        if (!res.ok) {
             const errorText = await res.text();
             console.error('API Error Response (fetchIncidents):', res.status, res.statusText, errorText.substring(0, 100));
             // Do not throw or alert, just stop processing and rely on fallback/empty table
             return; 
        }
        
        // FIX: The previous JSON parsing check is redundant if res.ok is already checked and we rely on try/catch for SyntaxError
        const data = await res.json();
        
        if (data.status !== 'success') {
             console.error('Incidents load error:', data);
             return;
        }
        renderIncidentsTable(data.data || []);
    } catch (e) { 
        // This catches network errors and JSON parsing errors
        console.error('Failed to fetch incidents:', e.message || e); 
    }
}

// Period button handlers
document.addEventListener('DOMContentLoaded', () => {
    createCharts();
    // initial load
    setActivePeriodButton(currentPeriod);
    fetchTrends(currentPeriod);
    fetchIncidents();

    // Poll every 12 seconds for live feel
    setInterval(() => fetchTrends(currentPeriod), 12000);
    setInterval(fetchIncidents, 15000);

    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = btn.getAttribute('data-period');
            currentPeriod = p;
            setActivePeriodButton(p);
            fetchTrends(p);
        });
    });

    // Incident modal and form
    document.getElementById('new-incident-btn').addEventListener('click', () => openIncidentModal());
    document.getElementById('incident-cancel').addEventListener('click', closeIncidentModal);
    document.getElementById('incident-form').addEventListener('submit', submitIncidentForm);
});


function setActivePeriodButton(period) {
    document.querySelectorAll('.period-btn').forEach(b => {
        if (b.getAttribute('data-period') === period) {
            b.classList.add('bg-red-500'); b.classList.remove('bg-slate-200'); b.classList.remove('text-slate-600'); b.classList.add('text-white');
        } else {
            b.classList.remove('bg-red-500'); b.classList.add('bg-slate-200'); b.classList.remove('text-white'); b.classList.add('text-slate-600');
        }
    });
}

// Incidents list and CRUD
// async function fetchIncidents() {
//     try {
//         const res = await fetch(`${API_URL}?endpoint=incidents&owner=resident`);
//         const data = await res.json();
//         if (data.status !== 'success') return console.error('Incidents load error', data);
//         renderIncidentsTable(data.data || []);
//     } catch (e) { console.error('Failed to fetch incidents', e); }
// }

function renderIncidentsTable(rows) {
    const tbody = document.getElementById('incidents-table-body');
    tbody.innerHTML = '';
    rows.forEach(r => {
        const tr = document.createElement('tr'); tr.className = 'border-b'; tr.dataset.incidentId = r.incident_ID;
        // FIXED: Added Sensor ID column to JS rendering
        tr.innerHTML = `
            <td class="py-3 px-4 text-sm text-slate-700">${escapeHtml(r.address || '')}</td>
            <td class="py-3 px-4 text-sm text-slate-700">${escapeHtml(r.start_timestamp || '')}</td>
            <td class="py-3 px-4 text-sm text-slate-700">${escapeHtml(r.incident_type || '')}</td>
            <td class="py-3 px-4 text-sm text-slate-700">${escapeHtml(r.incident_level || '')}</td>
            <td class="py-3 px-4 text-sm text-slate-700">
                <span class="text-xs bg-slate-100 px-2 py-1 rounded inline-block">${escapeHtml(r.FK_sensor_ID || 'N/A')}</span>
            </td>
            <td class="py-3 px-4 text-sm text-slate-700">
                <button class="edit-incident-btn text-blue-600 mr-3">Edit</button>
                <button class="delete-incident-btn text-red-600">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Removed the commented-out event listeners as delegation is used below
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }
document.addEventListener('click', (e) => {
    // Edit button click
    if (e.target.classList.contains('edit-incident-btn')) {
        const incidentId = e.target.closest('tr').dataset.incidentId;
        openIncidentModal(incidentId);
    }
    
    // Delete button click
    if (e.target.classList.contains('delete-incident-btn')) {
        const incidentId = e.target.closest('tr').dataset.incidentId;
        deleteIncident(incidentId);
    }
});

function openIncidentModal(id = null) {
    const modal = document.getElementById('incident-modal');
    const title = document.getElementById('incident-modal-title');
    const idInput = document.getElementById('incident-id');
    const sensorInput = document.getElementById('incident-sensor');
    const levelSelect = document.getElementById('incident-level');
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    idInput.value = '';
    sensorInput.value = '';
    levelSelect.value = 'LOW';
    sensorInput.disabled = false; // Enable for new incident
    
        if (id) {
        title.textContent = 'Edit Incident';
        // Fetch incident data
        fetch(`${API_URL}?endpoint=incidents&id=${id}`)
            .then(r => {
                // FIX: Check for network level errors first (e.g., 401 Unauthorized, 404 Not Found)
                if (!r.ok) {
                    throw new Error(`HTTP Status ${r.status}: Failed to fetch incident data. Check session/authentication.`);
                }
                return r.json();
            })
            .then(d => {
                if (d.status === 'success' && d.data) {
                    const incident = d.data;
                    idInput.value = incident.incident_ID || '';
                    sensorInput.value = incident.FK_sensor_ID || '';
                    sensorInput.disabled = true; // Disable sensor editing on update
                    levelSelect.value = incident.incident_level || 'LOW';
                } else {
                    // This executes if the API call returns 200 OK but the JSON body has status: 'error'
                    console.error('API Error: Incident ID', id, 'returned non-success status:', d);
                    // FIX: Use the error message from the API response body
                    const errorMessage = d.message || `ID ${id} not found or a server error occurred.`;
                    alert('Error loading incident: ' + errorMessage); 
                    closeIncidentModal();
                }
            })
            .catch(e => {
                // This handles network errors, JSON parsing errors, and the custom Error thrown above (e.g., 401)
                console.error('Error fetching incident ID', id, ':', e.message || e);
                // Use the custom error message if available, or the generic one
                alert('Error loading incident: ' + (e.message || 'Network error or invalid data format.')); 
                closeIncidentModal();
            });
    } else {
        title.textContent = 'New Incident';
    }
}
function closeIncidentModal(){ document.getElementById('incident-modal').classList.add('hidden'); document.getElementById('incident-modal').classList.remove('flex'); }

async function submitIncidentForm(e) {
    e.preventDefault();
    
    const idInput = document.getElementById('incident-id');
    const sensorInput = document.getElementById('incident-sensor');
    const levelSelect = document.getElementById('incident-level');
    
    const id = idInput.value.trim();
    const sensor = sensorInput.value.trim();
    const level = levelSelect.value;
    
    if (!sensor && !id) { // Only require sensor ID for POST
        alert('Sensor ID is required for a new incident');
        return;
    }
    
    try {
        if (!id) {
            // CREATE new incident
            const res = await fetch(`${API_URL}?endpoint=incidents`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    FK_sensor_ID: sensor,
                    incident_level: level
                    // incident_type is handled server-side (using a placeholder)
                })
            });
            
            // FIX: Check for non-200 HTTP status
            if (!res.ok) {
                 const errorText = await res.text();
                 console.error('Create failed (HTTP Error):', res.status, errorText);
                 alert('Create failed: HTTP Error ' + res.status + '. Check console.');
                 return;
            }

            const data = await res.json();
            
            if (data.status === 'success') {
                alert('Incident created successfully');
                closeIncidentModal();
                fetchIncidents();
                fetchTrends(currentPeriod); // Update charts
            } else {
                  // FIX: Correctly log the returned data, not 'd'
                  console.error('API Error: Incident creation returned non-success status:', data);
                  alert('Create failed: ' + (data.message || 'Unknown server error'));
            }
        } else {
            // UPDATE existing incident
            const res = await fetch(`${API_URL}?endpoint=incidents`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    incident_ID: id,
                    incident_level: level // Only updating level in this simplified PUT
                })
            });

            // FIX: Check for non-200 HTTP status
            if (!res.ok) {
                 const errorText = await res.text();
                 console.error('Update failed (HTTP Error):', res.status, errorText);
                 alert('Update failed: HTTP Error ' + res.status + '. Check console.');
                 return;
            }

            const data = await res.json();
            
            if (data.status === 'success') {
                alert('Incident updated successfully');
                closeIncidentModal();
                fetchIncidents();
                fetchTrends(currentPeriod); // Update charts
            } else {
                console.error('API Error: Incident update returned non-success status:', data);
                alert('Update failed: ' + (data.message || 'Unknown server error'));
            }
        }
    } catch (e) {
        console.error('Form submission error (Network/JSON Parse):', e);
        alert('Error: ' + e.message + '. Check console for details.');
    }
}

async function deleteIncident(id) {
    if (!confirm('Are you sure you want to delete this incident? This cannot be undone.')) {
        return;
    }
    
    try {
        const res = await fetch(`${API_URL}?endpoint=incidents`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ incident_ID: id })
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            alert('Incident deleted successfully');
            fetchIncidents();
            fetchTrends(currentPeriod); // Update charts
        } else {
            alert('Delete failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        console.error('Delete error:', e);
        alert('Error: ' + e.message);
    }
}

</script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card { animation: fadeIn 0.6s ease-out forwards; }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .chart-card { animation: fadeIn 0.6s ease-out forwards; animation-delay: 0.5s; }
        .table-section { animation: fadeIn 0.6s ease-out forwards; animation-delay: 0.6s; }
        .stat-icon { transition: transform 0.3s ease; }
        .stat-card:hover .stat-icon { transform: scale(1.1) rotate(5deg); }
        tbody tr { transition: all 0.2s ease; }
        tbody tr:hover { transform: translateX(4px); }
        .action-link { position: relative; transition: color 0.2s ease; }
        .action-link::after { content: ''; position: absolute; width: 0; height: 2px; bottom: -2px; left: 0; background-color: #ef4444; transition: width 0.3s ease; }
        .action-link:hover::after { width: 100%; }
        button { transition: all 0.2s ease; }
        button:active { transform: scale(0.95); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-800 to-slate-900 min-h-screen p-5">
    <div class="max-w-7xl mx-auto bg-slate-50 rounded-3xl p-10">
        <h1 class="text-3xl font-bold text-slate-800 mb-8">Analytic Report</h1>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-10">
            <!-- Total Incidents -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm flex justify-between items-start">
                <div>
                    <h3 class="text-sm text-slate-500 font-medium mb-2">Total Incidents</h3>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?php echo number_format($totalIncidents); ?></div>
                    <div class="text-sm text-green-600 font-medium">▲ 12% last month</div>
                </div>
                <div class="stat-icon w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center text-2xl">ℹ️</div>
            </div>

            <!-- Response Time -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm flex justify-between items-start">
                <div>
                    <h3 class="text-sm text-slate-500 font-medium mb-2">Response Time</h3>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($avgResponse); ?> <span class="text-lg font-medium">min</span></div>
                    <div class="text-sm text-red-600 font-medium">▼ 5% last month</div>
                </div>
                <div class="stat-icon w-14 h-14 rounded-full bg-green-100 flex items-center justify-center text-2xl">🕐</div>
            </div>

            <!-- False Alarms -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm flex justify-between items-start">
                <div>
                    <h3 class="text-sm text-slate-500 font-medium mb-2">False Alarms</h3>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?php echo number_format($falseAlarms); ?></div>
                    <div class="text-sm text-green-600 font-medium">▲ 8% last month</div>
                </div>
                <div class="stat-icon w-14 h-14 rounded-full bg-yellow-100 flex items-center justify-center text-2xl">⚠️</div>
            </div>

            <!-- Active Devices -->
            <div class="stat-card bg-white rounded-2xl p-6 shadow-sm flex justify-between items-start">
                <div>
                    <h3 class="text-sm text-slate-500 font-medium mb-2">Active Devices</h3>
                    <div class="text-4xl font-bold text-slate-800 mb-1"><?php echo number_format($activeDevices); ?></div>
                    <div class="text-sm text-slate-600 font-medium">93% uptime</div>
                </div>
                <div class="stat-icon w-14 h-14 rounded-full bg-purple-100 flex items-center justify-center text-2xl">📡</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">
            <div class="chart-card lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm">
                <h2 class="text-xl font-bold text-slate-800 mb-4">Incident Trends</h2>
                <div class="flex gap-2 mb-5" id="trends-periods">
                    <button data-period="weekly" class="period-btn px-4 py-2 rounded-lg bg-slate-200 text-slate-600 text-sm font-medium">Weekly</button>
                    <button data-period="monthly" class="period-btn px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-medium">Monthly</button>
                    <button data-period="yearly" class="period-btn px-4 py-2 rounded-lg bg-slate-200 text-slate-600 text-sm font-medium">Yearly</button>
                </div>
                <div class="h-64 bg-slate-100 rounded-xl p-4">
                    <canvas id="trendsChart" aria-label="Incident trends chart"></canvas>
                </div>
            </div>

            <div class="chart-card bg-white rounded-2xl p-6 shadow-sm">
                <h2 class="text-xl font-bold text-slate-800 mb-4">Incident By Types</h2>
                <div class="h-64 bg-slate-100 rounded-xl p-4">
                    <canvas id="typesChart" aria-label="Incident types chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Incidents Table -->
        <div class="table-section bg-white rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-800 mb-5">Recent Incidents</h2>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <button id="new-incident-btn" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm">New Incident</button>
                    <?php if (empty($recent)): ?>
                        <form method="post" style="display:inline-block;margin-left:8px">
                            <input type="hidden" name="action" value="seed_sample" />
                            <button type="submit" class="px-3 py-2 bg-amber-500 text-white rounded-lg text-sm">Seed Sample Data</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
         <div class="overflow-x-auto">
    <table class="w-full">
        <thead>
            <tr class="bg-slate-50">
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Location</th>
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date & Time</th>
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Level</th>
                <!-- FIXED: Added Sensor ID and Action header -->
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Sensor ID</th>
                <th class="text-left py-4 px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Action</th>
            </tr>
        </thead>
        <tbody id="incidents-table-body">
            <?php foreach ($recent as $row): ?>
            <!-- FIXED: Added action buttons to PHP rendering for initial load consistency -->
            <tr class="border-b" data-incident-id="<?php echo $row['incident_ID']; ?>" data-sensor-id="<?php echo $row['FK_sensor_ID'] ?? ''; ?>">
                <td class="py-3 px-4 text-sm text-slate-700"><?php echo htmlspecialchars($row['address']); ?></td>
                <td class="py-3 px-4 text-sm text-slate-700"><?php echo htmlspecialchars($row['start_timestamp']); ?></td>
                <td class="py-3 px-4 text-sm text-slate-700"><?php echo htmlspecialchars($row['type']); ?></td>
                <td class="py-3 px-4 text-sm text-slate-700"><?php echo htmlspecialchars($row['incident_level']); ?></td>
                <td class="py-3 px-4 text-sm text-slate-700">
                    <span class="text-xs bg-slate-100 px-2 py-1 rounded inline-block"><?php echo htmlspecialchars($row['FK_sensor_ID'] ?? 'N/A'); ?></span>
                </td>
                <td class="py-3 px-4 text-sm text-slate-700">
                    <button class="edit-incident-btn text-blue-600 mr-3 cursor-pointer hover:underline" type="button">Edit</button>
                    <button class="delete-incident-btn text-red-600 cursor-pointer hover:underline" type="button">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        </div>
        
        <!-- Incident Modal -->
        <div id="incident-modal" class="fixed inset-0 bg-black bg-opacity-40 hidden items-center justify-center">
            <div class="bg-white rounded-xl p-6 w-full max-w-lg">
                <h3 id="incident-modal-title" class="text-lg font-bold mb-3">New Incident</h3>
                <form id="incident-form" class="space-y-3">
                    <input type="hidden" id="incident-id" />
                    <div>
                        <label class="text-xs text-slate-600">Sensor ID</label>
                        <input id="incident-sensor" class="w-full border rounded px-3 py-2" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-600">Incident Level</label>
                        <select id="incident-level" class="w-full border rounded px-3 py-2">
                            <option value="LOW">LOW</option>
                            <option value="MEDIUM">MEDIUM</option>
                            <option value="HIGH">HIGH</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" id="incident-cancel" class="px-4 py-2">Cancel</button>
                        <button type="submit" id="incident-save" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
