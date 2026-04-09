<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP Early Alert</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bfp-red': '#C82828',
                        'bfp-red-dark': '#B81C1C',
                        'status-green': '#34A853',
                        'stat-green': '#2EBF7F',
                        'stat-red': '#EA4335',
                        'resolved-green-bg': '#E6F4EA',
                        'active-red-bg': '#FCE8E6',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-800 flex items-center justify-center min-h-screen font-sans">
    <div class="w-full max-w-sm h-screen sm:h-[85vh] sm:rounded-lg shadow-2xl relative overflow-hidden">
        
        <main id="splash-screen" class="bg-white w-full h-full sm:rounded-lg flex flex-col items-center justify-center text-center p-8 cursor-pointer absolute inset-0 z-50">
             <div class="flex-grow flex items-center justify-center">
                <svg class="w-40 h-40" viewBox="0 0 100 100">
                    <g><path d="M50,5 L95,25 L95,75 L50,95 L5,75 L5,25 Z" fill="#003399"/><path d="M50,10 L90,30 L90,70 L50,90 L10,70 L10,30 Z" fill="#FFFFFF"/><path d="M50,15 L85,35 L85,65 L50,85 L15,65 L15,35 Z" fill="#D40000"/><circle cx="50" cy="50" r="20" fill="#FFD700"/><text x="50" y="55" font-family="Arial" font-size="10" fill="#000000" text-anchor="middle" font-weight="bold">BFP</text></g>
                </svg>
            </div>
            <p class="text-gray-400 text-sm animate-pulse">Tap to continue</p>
        </main>

        <main id="login-screen" class="bg-white w-full h-full flex-col items-center justify-center p-8 hidden absolute inset-0 z-40">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Welcome Back!</h2>
            </div>
            <form id="login-form" class="w-full">
                <div class="mb-4">
                    <input type="email" id="email" placeholder="Email Address" class="w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-bfp-red transition-all" required>
                </div>
                <div class="mb-6">
                    <input type="password" id="password" placeholder="Password" class="w-full px-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-bfp-red transition-all" required>
                </div>
                <p id="login-error" class="text-red-500 text-sm mb-4 hidden"></p>
                <button type="submit" class="w-full bg-gradient-to-br from-bfp-red to-bfp-red-dark text-white font-bold py-3 rounded-xl hover:shadow-lg transition-all">Login</button>
            </form>
        </main>
    
        <div id="app-container" class="w-full h-full hidden flex-col">
            <div id="main-content" class="w-full h-full flex flex-col flex-grow">
                <header class="bg-bfp-red text-white p-4 sm:rounded-t-lg shadow-md z-10">
                    <div class="flex justify-between items-center">
                        <div><h1 class="text-xl font-bold">Early Alert</h1><p class="text-sm opacity-90">Fire Safety System</p></div>
                        <div class="text-right"><p id="current-date" class="text-sm font-semibold"></p><p id="current-time" class="text-xs opacity-90"></p></div>
                    </div>
                    <div id="system-status-bar" class="mt-4 bg-status-green text-white p-3 rounded-lg flex items-center justify-between shadow">
                        <div class="flex items-center">
                            <span id="status-text" class="font-semibold">All Systems Normal</span>
                        </div>
                        <span id="status-badge" class="bg-white/30 text-xs font-bold px-3 py-1 rounded-full">SAFE</span>
                    </div>
                </header>

                <div class="flex-grow overflow-y-auto bg-gray-100 relative">
                    
                    <main id="home-screen" class="app-screen p-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-stat-green text-white p-4 rounded-lg shadow-lg">
                                <p class="text-sm font-semibold">Active Sensors</p>
                                <p id="dash-active-devices" class="text-4xl font-bold mt-2">-</p>
                            </div>
                            <div class="bg-stat-red text-white p-4 rounded-lg shadow-lg">
                                <p class="text-sm font-semibold">Active Alerts</p>
                                <p id="dash-active-alerts" class="text-4xl font-bold mt-2">-</p>
                            </div>
                        </div>

                        <div class="bg-bfp-red text-white p-4 rounded-lg shadow-lg">
                            <h3 class="font-bold mb-3">Emergency Contacts</h3>
                            <div class="flex items-center space-x-2">
                                <select class="flex-grow bg-white/20 border border-white/30 text-white p-2 rounded-md"><option>BFP Hotline</option></select>
                                <button class="call-trigger bg-white text-bfp-red-dark font-bold px-4 py-2 rounded-md">Call</button>
                            </div>
                        </div>

                        <div>
                            <h2 class="text-xl font-bold text-gray-800 mb-3">Recent Incidents</h2>
                            <div id="recent-activity-container" class="space-y-3">
                                </div>
                        </div>
                    </main>

                    <main id="devices-screen" class="app-screen p-4 space-y-4 hidden">
                        <div class="flex justify-between items-center mb-3">
                            <h2 class="text-xl font-bold text-gray-800">Sensor Monitor</h2>
                            <button id="add-device-screen-trigger" class="text-bfp-red font-bold text-2xl">+</button>
                        </div>
                        <div id="devices-list-container" class="space-y-3">
                            </div>
                    </main>

                    <main id="location-screen" class="app-screen hidden">
                        <div class="p-4"><h2 class="text-xl font-bold text-gray-800">Property Location</h2></div>
                        <div id="map" class="w-full h-64 bg-gray-300"></div>
                        <div class="p-4 bg-white">
                            <h3 class="font-bold text-lg text-gray-800 mb-2">Registered Address</h3>
                             <p id="loc-address" class="text-gray-600">Loading...</p>
                        </div>
                    </main>
                    
                    <main id="settings-screen" class="app-screen p-4 space-y-4 hidden">
                        <h2 class="text-xl font-bold text-gray-800">Settings</h2>
                        <div class="bg-white p-4 rounded-lg shadow-md">
                            <h3 class="font-bold text-gray-800 mb-4">Profile</h3>
                            <div class="space-y-2 text-sm">
                                <div><p class="text-gray-500">Name</p><p id="profile-name" class="font-bold"></p></div>
                                <div><p class="text-gray-500">Email</p><p id="profile-email" class="font-bold"></p></div>
                                <div><p class="text-gray-500">Phone</p><p id="profile-phone" class="font-bold"></p></div>
                            </div>
                        </div>
                        <button id="logout-btn" class="w-full bg-red-100 text-red-600 font-semibold py-3 rounded-lg">Sign Out</button>
                    </main>
                </div>

                <div id="add-device-screen" class="absolute inset-0 bg-gray-100 flex-col hidden z-30">
                    <header class="bg-white p-4 flex items-center justify-between border-b">
                        <button id="back-to-devices-btn" class="text-gray-600 font-bold">Cancel</button>
                        <h2 class="text-lg font-semibold">Add Sensor</h2>
                        <div class="w-8"></div>
                    </header>
                    <div class="p-6">
                        <form id="add-device-form">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-600">Device Unique ID (IP)</label>
                                <input type="text" id="new-device-name" class="mt-1 w-full p-2 border rounded-lg" placeholder="e.g., 192.168.1.50" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-600">Location Name</label>
                                <input type="text" id="new-device-location" class="mt-1 w-full p-2 border rounded-lg" placeholder="e.g., Kitchen" required>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg">Register Sensor</button>
                        </form>
                    </div>
                </div>

                <nav id="bottom-nav" class="bg-white border-t border-gray-200 flex justify-around sm:rounded-b-lg shadow-lg z-20">
                    <a href="#" data-screen="home-screen" class="nav-link p-3 text-bfp-red w-full text-center text-xs font-bold">Home</a>
                    <a href="#" data-screen="devices-screen" class="nav-link p-3 text-gray-500 w-full text-center text-xs font-bold">Sensors</a>
                    <a href="#" data-screen="location-screen" class="nav-link p-3 text-gray-500 w-full text-center text-xs font-bold">Map</a>
                    <a href="#" data-screen="settings-screen" class="nav-link p-3 text-gray-500 w-full text-center text-xs font-bold">Settings</a>
                </nav>
            </div>
        </div>

        <div id="call-screen" class="fixed inset-0 bg-bfp-red text-white p-6 flex-col items-center justify-between hidden z-50">
            <div class="text-center pt-16">
                <div class="w-32 h-32 mx-auto rounded-full border-4 border-white/50 flex items-center justify-center text-4xl">📞</div>
                <h2 class="text-2xl font-bold mt-4">BFP Hotline</h2>
                <p id="call-timer" class="text-lg mt-2">00:00</p>
            </div>
            <button id="end-call-btn" class="mb-12 bg-red-600 border-2 border-white px-8 py-3 rounded-full font-semibold">End Call</button>
        </div>

    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let mapInitialized = false;
        let map;
        let callInterval;

        document.addEventListener('DOMContentLoaded', () => {
            checkSession();

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    showScreen(e.target.dataset.screen || e.target.closest('a').dataset.screen);
                });
            });

            document.getElementById('splash-screen').addEventListener('click', () => {
                document.getElementById('splash-screen').classList.add('hidden');
                document.getElementById('login-screen').classList.remove('hidden');
                document.getElementById('login-screen').classList.add('flex');
            });

            // Login Logic
            document.getElementById('login-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = { 
                    email: document.getElementById('email').value, 
                    password: document.getElementById('password').value 
                };
                
                const res = await fetch('api.php?action=login', {
                    method: 'POST',
                    body: JSON.stringify(formData)
                });
                const data = await res.json();
                
                if(data.success) {
                    document.getElementById('login-screen').classList.add('hidden');
                    initApp(data.user);
                } else {
                    const err = document.getElementById('login-error');
                    err.textContent = data.message;
                    err.classList.remove('hidden');
                }
            });

            document.getElementById('logout-btn').addEventListener('click', async () => {
                await fetch('api.php?action=logout');
                window.location.reload();
            });

            // Add Device Logic
            document.getElementById('add-device-screen-trigger').addEventListener('click', () => {
                document.getElementById('add-device-screen').classList.remove('hidden');
                document.getElementById('add-device-screen').classList.add('flex');
            });
            document.getElementById('back-to-devices-btn').addEventListener('click', () => {
                 document.getElementById('add-device-screen').classList.add('hidden');
            });

            document.getElementById('add-device-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    device_name: document.getElementById('new-device-name').value,
                    location_name: document.getElementById('new-device-location').value
                };
                const res = await fetch('api.php?action=add_device', { method: 'POST', body: JSON.stringify(payload)});
                const result = await res.json();
                
                if(result.success) {
                    document.getElementById('add-device-screen').classList.add('hidden');
                    loadDevices();
                    loadDashboard();
                } else {
                    alert("Error: " + result.message);
                }
            });

            // Call Simulation
            document.querySelectorAll('.call-trigger').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('call-screen').classList.remove('hidden');
                    document.getElementById('call-screen').classList.add('flex');
                    let sec = 0;
                    callInterval = setInterval(() => {
                        sec++;
                        document.getElementById('call-timer').textContent = new Date(sec * 1000).toISOString().substr(14, 5);
                    }, 1000);
                });
            });

            document.getElementById('end-call-btn').addEventListener('click', () => {
                clearInterval(callInterval);
                document.getElementById('call-screen').classList.add('hidden');
                document.getElementById('call-screen').classList.remove('flex');
            });
        });

        async function checkSession() {
            const res = await fetch('api.php?action=check_session');
            const data = await res.json();
            if(data.loggedIn) {
                document.getElementById('splash-screen').classList.add('hidden');
                initApp(data.user);
            }
        }

        function initApp(user) {
            document.getElementById('app-container').classList.remove('hidden');
            document.getElementById('app-container').classList.add('flex');
            showScreen('home-screen');
            
            // Load Profile
            document.getElementById('profile-name').textContent = `${user.first_name} ${user.last_name}`;
            document.getElementById('profile-email').textContent = user.email;
            document.getElementById('profile-phone').textContent = user.phone_number || "N/A";

            // Initial Data Load
            loadDashboard();
            loadDevices();
            
            setInterval(loadDashboard, 5000); 
            setInterval(updateTime, 1000);
        }

        function showScreen(screenId) {
            document.querySelectorAll('.app-screen').forEach(s => s.classList.add('hidden'));
            document.getElementById(screenId).classList.remove('hidden');
            
            document.querySelectorAll('.nav-link').forEach(l => {
                l.classList.remove('text-bfp-red');
                l.classList.add('text-gray-500');
                if(l.dataset.screen === screenId) {
                    l.classList.add('text-bfp-red');
                    l.classList.remove('text-gray-500');
                }
            });

            if(screenId === 'location-screen') {
                loadLocationMap();
            }
        }

        async function loadLocationMap() {
            if(!mapInitialized) {
                // Default to PH
                map = L.map('map').setView([14.1145, 122.9546], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                mapInitialized = true;
            }
            
            const res = await fetch('api.php?action=get_location');
            const data = await res.json();
            
            if(data && data.latitude) {
                document.getElementById('loc-address').textContent = data.address;
                const lat = parseFloat(data.latitude);
                const lng = parseFloat(data.longitude);
                map.setView([lat, lng], 16);
                L.marker([lat, lng]).addTo(map).bindPopup("Your Property").openPopup();
            } else {
                 document.getElementById('loc-address').textContent = "No location data set.";
            }
        }

        async function loadDashboard() {
            const res = await fetch('api.php?action=get_dashboard');
            const data = await res.json();
            
            document.getElementById('dash-active-devices').textContent = data.active_devices;
            document.getElementById('dash-active-alerts').textContent = data.active_alerts;

            const statusDiv = document.getElementById('system-status-bar');
            const statusText = document.getElementById('status-text');
            const statusBadge = document.getElementById('status-badge');

            if(parseInt(data.active_alerts) > 0) {
                statusDiv.classList.remove('bg-status-green');
                statusDiv.classList.add('bg-stat-red');
                statusText.textContent = "Incident Detected!";
                statusBadge.textContent = "ALERT";
                statusBadge.classList.add('text-red-600');
            } else {
                statusDiv.classList.add('bg-status-green');
                statusDiv.classList.remove('bg-stat-red');
                statusText.textContent = "All Systems Normal";
                statusBadge.textContent = "SAFE";
            }

            const activityContainer = document.getElementById('recent-activity-container');
            activityContainer.innerHTML = '';
            if(data.recent_activity.length === 0) {
                 activityContainer.innerHTML = '<p class="text-gray-500 text-sm text-center">No recent incidents.</p>';
            } else {
                data.recent_activity.forEach(alert => {
                    const colorClass = alert.status === 'pending' ? 'stat-red' : 'status-green';
                    const bgClass = alert.status === 'pending' ? 'active-red-bg' : 'resolved-green-bg';
                    
                    const html = `
                        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-${colorClass}">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-gray-800">${alert.incident_type.toUpperCase()} - ${alert.incident_level}</h3>
                                    <p class="text-sm text-gray-500">${alert.location_name}</p>
                                </div>
                                <span class="bg-${bgClass} text-xs font-bold px-3 py-1 rounded-full">${alert.status}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">${alert.start_timestamp}</p>
                        </div>`;
                    activityContainer.innerHTML += html;
                });
            }
        }

        async function loadDevices() {
            const res = await fetch('api.php?action=get_devices');
            const devices = await res.json();
            const container = document.getElementById('devices-list-container');
            container.innerHTML = '';

            if(devices.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm text-center">No sensors registered.</p>';
            }

            devices.forEach(dev => {
                const isAlert = dev.status === 'error' || dev.status === 'offline';
                const statusColor = dev.status === 'active' ? 'text-green-600' : 'text-red-600';
                
                const html = `
                    <div class="bg-white p-4 rounded-lg shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-gray-800">${dev.sensor_type.toUpperCase()} Sensor</h3>
                                <p class="text-sm text-gray-500">Loc: ${dev.location_name}</p>
                                <p class="text-xs text-gray-400">ID: ${dev.esp_ip_unique}</p>
                            </div>
                            <span class="text-xs font-bold ${statusColor}">${dev.status.toUpperCase()}</span>
                        </div>
                    </div>`;
                container.innerHTML += html;
            });
        }

        function updateTime() {
            const now = new Date();
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric' });
        }
    </script>
</body>
</html>