<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP Early Alert</title>
    <meta name="theme-color" content="#b91919">
<link rel="manifest" href="manifest-officer.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <style>
        /* Custom style for the simulated push notification toast */
        .leaflet-routing-container { display: none !important; }
        #notification-toast {
            transition: all 0.3s ease-in-out;
            transform: translateY(-100%);
            z-index: 1000;
        }
        #notification-toast.show {
            transform: translateY(0);
        }
        /* Style for the map container (REDUCED SIZE) */
        #map-container {
            width: 100%;
            height: 40vh; /* Reduced height for better phone viewing */
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        #main-leaflet-map {
            width: 100%;
            height: 100%;
        }
        
        /* Custom map icon classes */
        .custom-fire-icon {
             /* Alert icon */
             animation: pulse 1.5s infinite;
        }
        .custom-device-icon div {
             /* Small blue circle for active device */
             width:12px;
             height:12px;
             border-radius:50%;
             background:#3b82f6;
             border:3px solid white;
             box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 0, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 0, 0, 0); }
        }
        .custom-fire-icon div {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body class="font-['Inter',_sans-serif] bg-gradient-to-br from-gray-800 to-gray-900">

    <div id="notification-toast" class="fixed top-0 left-0 right-0 max-w-md mx-auto p-4 pt-6">
        <div class="bg-red-700 text-white p-4 rounded-xl shadow-2xl flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <p class="font-bold" id="toast-title">EMERGENCY ALERT</p>
                    <p class="text-sm opacity-90" id="toast-message">New incident logged in database. Check Alerts tab.</p>
                </div>
            </div>
            <button class="flex-shrink-0 ml-4 text-red-200 hover:text-white" onclick="document.getElementById('notification-toast').classList.remove('show')">
                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                 </svg>
            </button>
        </div>
    </div>
    <div id="app-page" class="max-w-md mx-auto bg-gray-50 min-h-screen flex flex-col shadow-2xl">

        <section id="login-content" class="flex-1 p-8 flex flex-col justify-center items-center h-screen w-full">
            <div class="text-center mb-10">
                <img src="logo.png" alt="BFP Logo" class="w-22 h-18 mx-auto mb-4">
                <h2 class="text-2xl font-extrabold text-gray-800">BFP Officer Login</h2>
                <p class="text-sm text-gray-500">Early Alert Response & Access</p>
            </div>
            <form id="login-form" class="w-full max-w-xs space-y-4">
                <input type="email" id="login-email" placeholder="Officer Email: officer@bfp.gov" required class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl text-sm transition-all focus:outline-none focus:border-[#d93b3b] focus:ring-2 focus:ring-[#d93b3b]/20">
                <input type="password" id="login-password" required placeholder="Password: password"  class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl text-sm transition-all focus:outline-none focus:border-[#d93b3b] focus:ring-2 focus:ring-[#d93b3b]/20">
                <button type="submit" id="login-btn" class="w-full bg-gradient-to-br from-[#d93b3b] to-[#b91919] text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/50 hover:from-red-600 hover:to-red-700 transition-all">
                    Login to Officer Hub
                </button>
                <p id="login-error" class="text-xs text-red-500 text-center hidden"></p>
            </form>
        </section>


        <div id="authenticated-content" class="hidden min-h-screen flex flex-col">
            
            <header class="bg-gradient-to-br from-[#d93b3b] to-[#b91919] text-white p-5 pt-6 rounded-b-3xl shadow-lg sticky top-0 z-20">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h1 class="text-xl font-extrabold tracking-tight">BFP Daet Station</h1>
                        <p class="text-sm opacity-95 font-medium">Fire Safety System</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold" id="current-date">Sep 14, 2025</p>
                        <p class="text-xs opacity-90" id="current-time">10:48 AM</p>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span class="text-sm font-semibold" id="header-station">BFP Daet Station</span>
                    </div>
                    <span class="bg-green-500 text-white text-xs font-bold py-1.5 px-3.5 rounded-full shadow-md shadow-green-500/30">On Duty</span>
                </div>
            </header>

            <main class="flex-1 p-5 pb-28">
                
                <section id="home-content" class="page-content"> 
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        
                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-blue-500 text-white">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="total-users">0</p>
                            <p class="text-xs text-gray-500 font-medium">Total Users</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-green-500 text-white">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707l-.707-.707V8a6 6 0 00-6-6zm-6 8a1 1 0 11-2 0 1 1 0 012 0zm12-1a1 1 0 100-2 1 1 0 000 2z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="active-devices">0</p>
                            <p class="text-xs text-gray-500 font-medium">Active Devices</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-red-500 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="active-alerts">0</p>
                            <p class="text-xs text-gray-500 font-medium">Active Alerts</p>
                        </div>

                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="monthly-responses">0</p>
                            <p class="text-xs text-gray-500 font-medium">This Month</p>
                        </div>
                        
                    </div>

                    <div class="bg-white rounded-2xl shadow-lg p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold text-gray-800">View User</h2>
                            <div class="flex items-center gap-3" style="display:none">
                                <button id="add-user-btn" class="text-green-600 text-sm font-semibold hover:text-green-700 transition-colors" onclick="openUserModal(null)">
                                    + Add
                                </button>
                                <button id="filter-users-btn" class="flex items-center gap-1 text-blue-600 text-sm font-semibold hover:text-blue-700 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path></svg>
                                    Filter
                                </button>
                            </div>
                        </div>

                        <div class="relative mb-5">
                            <input type="text" id="user-search" placeholder="Search registered users..." class="w-full p-3 pl-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        
                        <div class="divide-y divide-gray-100" id="user-list-container">
                            <div class="text-center text-gray-500 py-10">Loading users...</div>
                        </div>
                    </div>
                </section>
                <section id="maps-content" class="page-content hidden">
                    
                   <div class="space-y-4 mb-4">
    <h2 class="text-lg font-bold text-gray-800">Coverage Map</h2>
    
    <div class="flex gap-2">
        <input type="text" id="map-coord-input" placeholder="Search Lat, Lon (e.g., 14.11, 122.96)" class="flex-1 p-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
        <button onclick="searchCoordinates()" class="bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </button>
    </div>

    <div id="map-container" class="rounded-xl">
        <div id="main-leaflet-map"></div>
    </div>
</div>
                    
                    <div class="bg-white rounded-2xl p-5 shadow-sm mb-4">
                        <h3 class="font-bold text-gray-800 mb-3">Map Legend:</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-blue-500 rounded-full"></div>
                                <span class="text-gray-700">Active Devices</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-red-500 rounded-full animate-pulse"></div>
                                <span class="text-gray-700">Active Alerts</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-gray-400 rounded-full"></div>
                                <span class="text-gray-700">Inactive Devices</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-[#b91919] rounded-full"></div>
                                <span class="text-gray-700">BFP Stations</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-6" id="map-metrics-row">
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-gray-800" data-metric="total-responses">0</p>
                            <p class="text-xs text-gray-600">Total Responses</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-green-600" data-metric="avg-response-time">0m</p>
                            <p class="text-xs text-gray-600">Avg Response</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-blue-600" data-metric="zero-casualty">0</p>
                            <p class="text-xs text-gray-600">Zero Casualty</p>
                        </div>
                    </div>
                </section>
                <section id="alerts-content" class="page-content hidden">
                    <div class="mb-5">
                        <h2 class="text-xl font-bold text-gray-800">Active Emergency Alerts</h2>
                        <p class="text-sm text-gray-500" id="alerts-last-updated">Last updated: just now</p>
                    </div>
                    <div id="active-alerts-container">
                        <div class="text-center text-gray-500 py-10" id="no-alerts-message">
                            <svg class="w-16 h-16 mx-auto mb-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <p class="font-bold">All Clear</p>
                            <p class="text-sm">No active alerts at this time.</p>
                        </div>
                    </div>
                </section>

                <section id="responses-content" class="page-content hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Response History</h2>
                      <a href="export.php" class="text-blue-600 text-sm font-medium hover:text-blue-700 transition-colors">Export Report</a>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-6" id="response-metrics">
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-gray-800" data-metric="total-responses">0</p>
                            <p class="text-xs text-gray-600">Total Responses</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-green-600" data-metric="avg-response-time">0m</p>
                            <p class="text-xs text-gray-600">Avg Response</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-blue-600" data-metric="zero-casualty">0</p>
                            <p class="text-xs text-gray-600">Zero Casualty</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4" id="response-history-container">
                        <div class="text-center text-gray-500 py-10">No history available.</div>
                    </div>
                </section>

                <section id="settings-content" class="page-content hidden">
                    <div class="space-y-5">
                        <div class="bg-white p-6 rounded-2xl shadow-sm">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-gray-800">Officer Profile</h3>
                                <svg class="w-5 h-5 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"  style="display:none">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L16.732 3.732z"></path>
                                </svg>
                            </div>
                            <div class="flex items-center gap-4 mb-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center text-white">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900" id="officer-name">Fire Officer III John Santos</p>
                                    <p class="text-sm text-gray-600" id="officer-station">BFP Daet Station</p>
                                    <p class="text-xs text-gray-400" id="officer-badge">Badge #BFP-2024-0123</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <input type="text" id="officer-phone" value="+63 912 345 6789" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white" placeholder="Contact Number">
                                <input type="email" id="officer-email" value="officer.santos@bfp.gov.ph" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white" placeholder="Email Address">
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl shadow-sm"style="display:none">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Notification Settings</h3>
                            <div class="divide-y divide-gray-100">
                                <div class="flex justify-between items-center py-3">
                                    <p class="font-medium text-gray-700">Emergency Alerts</p>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input id="emergency-toggle" type="checkbox" value="" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-blue-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                <div class="flex justify-between items-center py-3">
                                    <p class="font-medium text-gray-700">SMS Notifications</p>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input id="sms-toggle" type="checkbox" value="" class="sr-only peer" checked>
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-blue-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                <div class="flex justify-between items-center py-3">
                                    <p class="font-medium text-gray-700">System Updates</p>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input id="updates-toggle" type="checkbox" value="" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-blue-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl shadow-sm">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Station Information</h3>
                            <div class="space-y-3 text-sm" id="station-info-container">
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Station Name:</span>
                                    <span class="font-semibold text-gray-800" data-station="name">BFP Daet Central</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Station Code:</span>
                                    <span class="font-semibold text-gray-800" data-station="code">BFP-DC1</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Coordinates:</span>
                                    <span class="font-semibold text-gray-800" data-station="coverage">14.1182, 122.9455</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Contact Number:</span>
                                    <span class="font-semibold text-gray-800" data-station="units">0917-555-1212</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-3 pt-2">
                            <button style="display:none" id="update-profile-btn" class="w-full bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">
                                Update Profile
                            </button>
                            <button id="sign-out-btn" class="w-full bg-red-100 text-red-600 font-semibold py-3 rounded-lg hover:bg-red-200 transition-all">
                                Sign Out</button>
                        </div>
                    </div>
                </section>
                
            </main>

            <nav class="fixed bottom-0 left-0 right-0 max-w-md mx-auto bg-white p-2 rounded-t-2xl shadow-[0_-4px_20px_rgba(0,0,0,0.08)] z-20">
                <div class="flex justify-around">
                    <a href="#home-content" class="nav-link flex-1 flex flex-col items-center py-2.5 px-2 rounded-xl text-sm font-semibold transition-all bg-[#fef2f2] text-[#d93b3b]">
                        <svg class="w-6 h-6 mb-1" stroke-width="2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>Home</span>
                    </a>
                    <a href="#alerts-content" class="nav-link flex-1 flex flex-col items-center py-2.5 px-2 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-800 transition-all relative">
                        <svg class="w-6 h-6 mb-1" stroke-width="2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>Alerts</span>
                        <div id="alerts-badge" class="absolute top-1 right-3 w-2 h-2 bg-red-500 rounded-full animate-pulse hidden"></div>
                    </a>
                    <a href="#responses-content" class="nav-link flex-1 flex flex-col items-center py-2.5 px-2 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-800 transition-all">
                        <svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Responses</span>
                    </a>
                    <a href="#maps-content" class="nav-link flex-1 flex flex-col items-center py-2.5 px-2 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-800 transition-all">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Map</span>
                    </a>
                    <a href="#settings-content" class="nav-link flex-1 flex flex-col items-center py-2.5 px-2 rounded-xl text-sm font-semibold text-gray-500 hover:text-gray-800 transition-all">
                        <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>

        </div>
    </div>

    <div id="user-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-6 rounded-2xl w-full max-w-sm shadow-2xl">
            <h3 class="text-xl font-bold text-gray-800 mb-4" id="modal-title">User Details</h3>
            <form id="user-form">
                <input type="hidden" id="modal-user-id">
                <div class="space-y-3 mb-6">
                    <input type="text" id="modal-first-name" placeholder="First Name" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="text" id="modal-last-name" placeholder="Last Name" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <!-- <input type="text" id="modal-address" placeholder="Primary Address (Required for Residents)" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white"> -->
                    <input type="text" id="modal-phone" placeholder="Phone Number" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="email" id="modal-email" placeholder="Email Address" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <select id="modal-role" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                        <option value="resident">Resident</option>
                        <option value="bfp_officer">BFP Officer</option>
                        <option value="bfp_assigned_at_desk">Assigned At Desk</option>
                    </select>
                </div>
                <div class="flex justify-between gap-3" >
                    <button style="display:none" type="button" id="delete-user-btn" class="flex-1 bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-600 transition-all hidden">Delete</button>
                    <button style="display:none" type="submit" id="save-user-btn" class="flex-1 bg-blue-500 text-white font-semibold py-3 rounded-lg hover:bg-blue-600 transition-all">Save Changes</button>
                    <button type="button" id="close-modal-btn" class="flex-1 bg-gray-300 text-gray-800 font-semibold py-3 rounded-lg hover:bg-gray-400 transition-all">Back</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        // --- UTILITY FUNCTION: HTML Sanitization ---
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return unsafe;
            return unsafe.replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">").replace(/"/g, '"').replace(/'/g, "'");
        }


        // --- 1. GLOBAL DATA VARIABLES & CONFIG ---
        const API_URL = 'api_bfp.php'; 
        let users = [];
        let activeIncidents = [];
        let resolvedIncidents = [];
        let bfpStations = [];
        let metrics = {}; 
        let activeDevices = [];
        let lastIncidentCount = -1; 
        let pollingIntervalId = null; 
        let mainMapInstance = null;
        let activeMarkers = []; 

        // --- SIMULATED DATABASE LOGIC IS REMOVED. DATA IS FETCHED VIA API ---

        // --- API CALL HANDLERS (REPLACING SIMULATED DB) ---
       async function fetchDashboardData() {
            const response = await fetch(`${API_URL}?action=dashboard`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.status !== 'success') throw new Error(data.message || 'Failed to fetch dashboard data.');

            // Manually parse user's device counts from strings to numbers
            // FIX: added (data.users || []) to prevent crash if users is undefined
            data.users = (data.users || []).map(u => ({
                ...u,
                device_count: parseInt(u.device_count || 0),
                active_device_count: parseInt(u.active_device_count || 0),
                status: u.active_device_count > 0 ? 'ACTIVE' : 'INACTIVE',
            }));
            
            // Convert 'activeDevices' properties
            // FIX: added (data.activeDevices || []) to prevent crash if undefined
data.activeDevices = (data.activeDevices || []).map(d => ({
    ...d,
    has_active_alert: parseInt(d.active_alert_count) > 0,
    // Ensure the raw 'status' is preserved from the PHP data
    status: d.status, 
    latitude: parseFloat(d.latitude),
    longitude: parseFloat(d.longitude)
}));

            return data;
        }
        async function fetchAllData() {
            try {
                const data = await fetchDashboardData(); 

                checkForNewAlerts(data.activeIncidents);

                users = data.users || [];
                activeIncidents = data.activeIncidents || [];
                resolvedIncidents = data.resolvedIncidents || [];
                bfpStations = data.stations || [];
                metrics = data.metrics || {};
                window.activeDevices = data.activeDevices || [];
                
                renderAll(); 
                updateStationDisplays();

            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }
        }


        // --- 2. AUTHENTICATION & SESSION MANAGEMENT ---

        async function checkSession() {
            // In a real app, this would check a cookie/session variable.
            // For now, it just waits for a manual login.
        }
        
        // This is called only when the login is successful
        function showDashboard(user) {
            document.getElementById('login-content').classList.add('hidden');
            document.getElementById('authenticated-content').classList.remove('hidden');
            
            const officerNameEl = document.getElementById('officer-name');
            const officerEmailEl = document.getElementById('officer-email');
            if (officerNameEl) officerNameEl.textContent = user.name || officerNameEl.textContent;
            if (officerEmailEl) officerEmailEl.value = user.email || officerEmailEl.value;


            initializeMainMap(); 
            const initialTab = 'home-content';
            switchTab(initialTab);
            // Ensure map and markers are rendered after map initialization
            if (mainMapInstance) {
                // small delay to allow map to settle in DOM
                setTimeout(() => {
                    try { renderMapContent(); } catch (e) { console.warn('renderMapContent error:', e); }
                }, 150);
            }
            updateStationDisplays();

            // if (!pollingIntervalId) {
            //     pollingIntervalId = setInterval(fetchAllData, 15000); 
            // }
            
        }

        async function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            const loginBtn = document.getElementById('login-btn');
            const loginError = document.getElementById('login-error');

            loginBtn.innerHTML = 'Logging in...';
            loginError.classList.add('hidden');
            loginBtn.classList.remove('bg-gradient-to-br', 'from-[#d93b3b]', 'to-[#b91919]');
            loginBtn.classList.add('bg-gray-400'); // Temporarily change color during fetch

            try {
                const response = await fetch(`${API_URL}?action=login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });
                const data = await response.json();

                if (data.status === 'success') {
                    const user = data.user;
                    loginBtn.innerHTML = '<div class="flex items-center justify-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg><span>Login Successful!</span></div>';
                    loginBtn.classList.remove('bg-gray-400');
                    loginBtn.classList.add('bg-green-500');
                    
                    setTimeout(async () => {
                        await fetchAllData();
                        showDashboard(user);
                    }, 600);
                } else {
                    loginError.textContent = data.message || "Login failed. Check credentials.";
                    loginError.classList.remove('hidden');
                    loginBtn.innerHTML = 'Login to Officer Hub';
                }
            } catch (error) {
                console.error('Login request failed:', error);
                loginError.textContent = "An error occurred during login attempt.";
                loginError.classList.remove('hidden');
                loginBtn.innerHTML = 'Login to Officer Hub';
            } finally {
                loginBtn.classList.remove('bg-gray-400', 'bg-green-500');
                loginBtn.classList.add('bg-gradient-to-br', 'from-[#d93b3b]', 'to-[#b91919]');
            }
        }
        
        function showLogin() {
            document.getElementById('authenticated-content').classList.add('hidden');
            document.getElementById('login-content').classList.remove('hidden');
            if (pollingIntervalId) {
                clearInterval(pollingIntervalId);
                pollingIntervalId = null;
            }
            // Clear hash in URL
            if (history && history.replaceState) { history.replaceState(null, '', 'bfplogin.php'); } else { window.location.hash = ''; }
        }


        // --- 4. PUSH NOTIFICATION SIMULATION ---
        
        function checkForNewAlerts(currentIncidents) {
            const currentCount = (currentIncidents || []).length;
            const toast = document.getElementById('notification-toast');
            
            if (lastIncidentCount === -1) {
                 lastIncidentCount = currentCount; 
                 return;
            }

            if (currentCount > lastIncidentCount) {
                const newIncident = currentIncidents[0]; 
                
                document.getElementById('toast-title').textContent = `${newIncident.incident_level.toUpperCase()} ALERT!`;
                document.getElementById('toast-message').textContent = `Incident at ${escapeHtml(newIncident.address)}.`;
                
                toast.classList.remove('bg-green-700');
                toast.classList.add('bg-red-700');
                toast.classList.add('show');
                
                setTimeout(() => toast.classList.remove('show'), 8000);
            } else if (currentCount < lastIncidentCount) {
                document.getElementById('toast-title').textContent = `INCIDENT RESOLVED`;
                document.getElementById('toast-message').textContent = `An incident was cleared. All Clear.`;
                toast.classList.remove('bg-red-700');
                toast.classList.add('bg-green-700');
                toast.classList.add('show');
                
                setTimeout(() => {
                    toast.classList.remove('show', 'bg-green-700');
                    toast.classList.add('bg-red-700');
                }, 8000);
            }


            lastIncidentCount = currentCount; 
        }

        // --- 5. CRUD/ACTION FUNCTIONS (Used by the dashboard UI) ---

        async function resolveAlert(incidentId) {
            // Allow both residents and officers to resolve alerts
            if (!confirm(`Are you sure you want to resolve this alert?`)) return;

            try {
                const response = await fetch(`${API_URL}?action=resolve_alert`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ incidentId })
                });
                const data = await response.json();

                if (data.status === 'success' || data.message) {
                    alert(data.message || 'Alert resolved successfully.');
                    fetchAllData(); 
                } else {
                    alert(`Error resolving alert: ${data.message || 'Unknown error'}`);
                }
            } catch (error) {
                alert("An error occurred during alert resolution.");
                console.error('Resolve Alert Error:', error);
            }
        }

        async function callBFPStation(phoneNumber) {
            // Send call to BFP station via Arduino gateway (no UI shown)
            try {
                const response = await fetch(`${API_URL}?action=call_sms`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        phone_number: phoneNumber, 
                        action_type: 'call'
                    })
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    console.log(`Call initiated to ${phoneNumber}`);
                } else {
                    console.error(`Call failed: ${data.message}`);
                }
            } catch (error) {
                console.error('Call error:', error);
            }
        }

        async function sendSMSToBFP(phoneNumber, message) {
            // Send SMS to BFP station via Arduino gateway (no UI shown)
            try {
                const response = await fetch(`${API_URL}?action=call_sms`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        phone_number: phoneNumber, 
                        message: message || 'Fire Alert! Emergency dispatch initiated.',
                        action_type: 'sms'
                    })
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    console.log(`SMS sent to ${phoneNumber}`);
                } else {
                    console.error(`SMS failed: ${data.message}`);
                }
            } catch (error) {
                console.error('SMS error:', error);
            }
        }

async function saveUser(userData, isNew) {
    const saveBtn = document.getElementById('save-user-btn');
    saveBtn.innerHTML = isNew ? 'Adding...' : 'Saving...';
    saveBtn.disabled = true;

    try {
        const response = await fetch(`${API_URL}?action=crud_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Response NOT OK:', response.status, errorText);
            throw new Error(`Server returned status ${response.status}. See console for details.`);
        }
        const data = await response.json();

        if (data.status === 'success') {
            alert(data.message);
            document.getElementById('user-modal').style.display = 'none';

            if (data.user) {
                updateLocalUserList(data.user);
                renderUserList();
            } else {
                console.warn("Server response missing 'user' object. Re-fetching all data.");
                fetchAllData();
            }
        } else {
            alert(`Error saving user: ${data.message || 'Unknown error.'}`);
        }
    } catch (error) {
        alert("An error occurred while saving the user. Check console for details.");
        console.error('Save User Error:', error);
    } finally {
        saveBtn.innerHTML = 'Save Changes';
        saveBtn.disabled = false;
    }
}

function updateLocalUserList(updatedUser) {
    updatedUser.device_count = parseInt(updatedUser.device_count || 0);
    updatedUser.active_device_count = parseInt(updatedUser.active_device_count || 0);
    updatedUser.status = updatedUser.active_device_count > 0 ? 'active' : 'inactive';
    
    const index = users.findIndex(u => u.user_ID.toString() === updatedUser.user_ID.toString());
    
    if (index > -1) {
        users[index] = updatedUser;
    } else {
        users.push(updatedUser);
    }
}

async function deleteUser(userId) {
    if (!confirm("Are you sure you want to permanently delete this user? This action cannot be undone.")) return;
    try {
        const response = await fetch(`${API_URL}?action=crud_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_ID: userId, action_type: 'delete' })
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            alert(data.message);
            document.getElementById('user-modal').style.display = 'none';
            fetchAllData();
        } else {
            alert(`Error deleting user: ${data.message}`);
        }
    } catch (error) {
        alert("An error occurred while deleting the user.");
        console.error('Delete User Error:', error);
    }
}

        
    async function openUserModal(userId = null) {
            const localUser = users.find(u => u.user_ID.toString() === userId?.toString());
            let user = { 
                user_ID: null, 
                first_name: '', 
                last_name: '', 
                phone_number: '', 
                email: '', 
                role: 'resident' // Default to resident
            };
            const isNew = userId === null || !localUser;
            
            if (!isNew && localUser) {
                user = { 
                    ...localUser, 
                    // Ensure role matches select options format
                    role: localUser.role.toString().toLowerCase().replace(/\s+/g, '_')
                };
            }
            
            document.getElementById('modal-title').textContent = isNew ? 'Add New User' : 'Edit User';
            document.getElementById('modal-user-id').value = user.user_ID || '';
            document.getElementById('modal-first-name').value = user.first_name;
            document.getElementById('modal-last-name').value = user.last_name;
            document.getElementById('modal-phone').value = user.phone_number;
            document.getElementById('modal-email').value = user.email || '';
            
            const roleEl = document.getElementById('modal-role');
            if (roleEl) {
                const allowed = Array.from(roleEl.options).map(o => o.value);
                roleEl.value = allowed.includes(user.role) ? user.role : 'resident';
            }

            const deleteBtn = document.getElementById('delete-user-btn');
            if (!isNew) {
                deleteBtn.classList.remove('hidden');
                deleteBtn.onclick = () => deleteUser(user.user_ID); 
            } else {
                 deleteBtn.classList.add('hidden');
            }
            
            // Note: The 'modal-status' select is not used in the final CRUD logic as the database manages status implicitly.
            document.getElementById('user-modal').style.display = 'flex';
        }

        document.getElementById('close-modal-btn').addEventListener('click', () => {
             document.getElementById('user-modal').style.display = 'none';
        });
        
        document.getElementById('user-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const userId = document.getElementById('modal-user-id').value;
            const isNew = !userId;
            const userData = {
                user_ID: userId,
                first_name: document.getElementById('modal-first-name').value,
                last_name: document.getElementById('modal-last-name').value,
                phone_number: document.getElementById('modal-phone').value,
                email: document.getElementById('modal-email').value,
                role: document.getElementById('modal-role').value,
                // status: document.getElementById('modal-status').value, // Not used in API
            };
            saveUser(userData, isNew);
        });

        function handleCallResident(phoneNumber) { alert(`Calling resident at ${phoneNumber}... (Simulated)`); }
        function handleNavigate(coordinates) {
            const cleanCoords = coordinates.split(',').map(c => c.trim().replace    (/°\s*[NSEW]/, '')).join(',');
            // Correct URL format to open Google Maps for directions/search
            window.open(`https://www.google.com/maps/search/?api=1&query=${cleanCoords}`, '_blank');
        }


        // --- 6. RENDERING FUNCTIONS ---

        function renderMetrics() {
            document.querySelector('#home-content [data-metric="total-users"]').textContent = metrics['total_users'] || 0;
            document.querySelector('#home-content [data-metric="active-devices"]').textContent = metrics['active_devices'] || 0;
            document.querySelector('#home-content [data-metric="active-alerts"]').textContent = metrics['active_alerts'] || 0;
            document.querySelector('#home-content [data-metric="monthly-responses"]').textContent = metrics['monthly_responses'] || 0;

            document.querySelector('#map-metrics-row [data-metric="total-responses"]').textContent = metrics['total_responses'] || 0;
            document.querySelector('#map-metrics-row [data-metric="avg-response-time"]').textContent = `${metrics['avg_response_time'] || 0}m`;
            document.querySelector('#map-metrics-row [data-metric="zero-casualty"]').textContent = metrics['zero_casualty'] || 0;
            
            const alertsBadge = document.getElementById('alerts-badge');
            if ((metrics['active_alerts'] || 0) > 0) {
                alertsBadge.classList.remove('hidden');
            } else {
                alertsBadge.classList.add('hidden');
            }
        }

        const allowedRoles = ['resident', 'bfp_officer', 'bfp_assigned_at_desk'];
        
        function renderUserList(filteredUsers = users.filter(u => allowedRoles.includes(u.role))){
            const container = document.getElementById('user-list-container');
            if (!container) return; 
            container.innerHTML = ''; 
//  console.log('Total users after filtering:', filteredUsers.length, filteredUsers); 
            if (filteredUsers.length === 0) {
                 container.innerHTML = '<div class="text-center text-gray-500 py-10">No users found.</div>';
                 return;
            }

            filteredUsers.forEach((user) => {
                const userElement = document.createElement('div');
                userElement.className = `py-5 flex items-center gap-4 hover:bg-gray-50/50 cursor-pointer transition-all`; 
                userElement.setAttribute('data-user-id', user.user_ID);
                userElement.addEventListener('click', () => openUserModal(user.user_ID)); 

                const isResident = user.role === 'resident';
                // Status is ACTIVE if a user has at least one active device (for residents) or is a BFP officer.
                const status = isResident ? (user.active_device_count > 0 ? 'ACTIVE' : 'INACTIVE') : 'ACTIVE';
                const statusColor = status === 'ACTIVE' ? 'text-green-600' : 'text-red-600';
                const phoneLastFour = user.phone_number ? user.phone_number.slice(-4) : 'N/A';
                const address = user.address || (isResident ? 'No registered property' : 'BFP Personnel'); 
                
                userElement.innerHTML = `
                    <img src="https://i.pravatar.cc/150?u=${user.user_ID}" alt="User" class="w-14 h-14 rounded-full object-cover border-4 border-gray-100">
                    <div class="flex-1">
                        <p class="font-bold text-gray-800 text-sm">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</p>
                    
                        <div class="grid grid-cols-3 gap-3 text-xs">
                            <div><span class="block text-gray-400">Devices</span><p class="font-semibold text-gray-600">${user.device_count || 0}</p></div>
                            <div><span class="block text-gray-400">Status</span><p class="font-semibold ${statusColor}">${status}</p></div>
                            <div><span class="block text-gray-400">Phone</span><p class="font-semibold text-gray-600">${phoneLastFour}</p></div>
                        </div>
                    </div>
                    <div class="text-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                `;
                container.appendChild(userElement);
            });
        }
        
        function renderActiveAlerts() {
            const container = document.getElementById('active-alerts-container');
            const noAlertsMessage = document.getElementById('no-alerts-message');
            if (!container || !noAlertsMessage) return;

            // Clear container but keep the "no-alerts" message element reference
            container.innerHTML = ''; 

            if (activeIncidents.length === 0) {
                container.appendChild(noAlertsMessage);
                noAlertsMessage.classList.remove('hidden');
                document.getElementById('alerts-last-updated').textContent = 'Last updated: just now (All Clear)';
                return;
            } else {
                noAlertsMessage.classList.add('hidden');
            }
            
            document.getElementById('alerts-last-updated').textContent = `Last updated: ${new Date().toLocaleTimeString()}`;

            activeIncidents.forEach(incident => {
                const timeAgo = formatTimeAgo(incident.start_timestamp);
                
                const alertElement = document.createElement('div');
                alertElement.className = 'bg-white rounded-2xl shadow-lg border-l-4 border-red-500 p-6 mb-4';
                alertElement.innerHTML = `
                    <div class="flex justify-between items-start mb-5">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 flex items-center justify-center bg-red-100 rounded-lg text-red-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-red-600">${incident.incident_level.toUpperCase()} ALERT</h3>
                                <p class="text-xs text-gray-500">${timeAgo}</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold bg-red-600 text-white py-1 px-3 rounded-full">${incident.status.toUpperCase()}</span>
                    </div>
                    <div class="space-y-3 text-sm text-gray-700 pl-14 mb-6">
                        <p><strong>Resident:</strong> ${escapeHtml(incident.first_name)} ${escapeHtml(incident.last_name)}</p>
                        <p><strong>Address:</strong> ${escapeHtml(incident.address)}</p>
                        <p><strong>Device:</strong> ${escapeHtml(incident.device)}</p>
                        <p><strong>Coordinates:</strong> ${escapeHtml(incident.coordinates)}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <button class="w-full flex items-center justify-center gap-2 bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all" onclick="handleNavigate('${escapeHtml(incident.coordinates)}')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Navigate
                        </button>
                        <button class="w-full flex items-center justify-center gap-2 bg-green-500 text-white font-semibold py-3 rounded-lg shadow-md hover:bg-green-600 transition-all" onclick="resolveAlert(${incident.incident_ID})">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Resolve
                        </button>
                    </div>
                `;
                container.appendChild(alertElement);
            });
        }

        function renderResponseHistory() {
            const container = document.getElementById('response-history-container');
            if (!container) return;

            container.innerHTML = ''; 

            document.querySelector('#response-metrics [data-metric="total-responses"]').textContent = metrics['total_responses'] || 0;
            document.querySelector('#response-metrics [data-metric="avg-response-time"]').textContent = `${metrics['avg_response_time'] || 0}m`;
            document.querySelector('#response-metrics [data-metric="zero-casualty"]').textContent = metrics['zero_casualty'] || 0;

            if (resolvedIncidents.length === 0) {
                 container.innerHTML = '<div class="text-center text-gray-500 py-10">No history available.</div>';
                 return;
            }
            
            const counts = {};
            function mapIncidentType(type, level) {
                const t = (type || '').toString().toLowerCase();
                if (t.includes('fire')) return 'Fire';
                if (t.includes('gas')) return 'Gas Leak';
                if (level.includes('medium')) return 'Medium Alarm';
                return 'Other';
            }

            resolvedIncidents.forEach(incident => {
                const type = mapIncidentType(incident.incident_type, incident.incident_level);
                counts[type] = (counts[type] || 0) + 1;
            });
            
            const summaryWrap = document.createElement('div');
            summaryWrap.className = 'bg-white rounded-xl p-3 shadow-sm mb-4';
            let summaryHtml = '<div class="flex items-center justify-between mb-2">';
            summaryHtml += '<div class="text-sm font-semibold">Recent Incident Types</div>';
            summaryHtml += `<div class="text-xs text-gray-500">Total: ${resolvedIncidents.length}</div>`;
            summaryHtml += '</div>';
            summaryHtml += '<div class="grid grid-cols-2 gap-2">';
            Object.keys(counts).slice(0, 4).forEach(key => { 
                const percentage = (counts[key] / resolvedIncidents.length) * 100;
                summaryHtml += `
                    <div class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                        <div class="w-1 h-8 bg-blue-400 rounded" style="width:${Math.max(8, percentage)}%"></div>
                        <div class="flex-1">
                            <div class="text-sm font-medium">${key}</div>
                            <div class="text-xs text-gray-500">${counts[key]} case(s)</div>
                        </div>
                    </div>
                `;
            });
            summaryHtml += '</div>';
            container.appendChild(summaryWrap);

            resolvedIncidents.forEach(incident => {
                const levelColor = incident.incident_level === 'high' ? 'bg-red-100 text-red-800' : (incident.incident_level === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800');
                const borderClass = incident.incident_level === 'high' ? 'border-red-500' : (incident.incident_level === 'medium' ? 'border-yellow-500' : 'border-orange-500');
                const type = mapIncidentType(incident.incident_type, incident.incident_level);
                const responseTime = incident.response_time !== null ? `${incident.response_time} mins` : 'N/A';

                const incidentElement = document.createElement('div');
                incidentElement.className = `bg-white rounded-2xl p-5 shadow-sm border-l-4 ${borderClass} hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer`;

                incidentElement.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-sm">${escapeHtml(incident.first_name)} ${escapeHtml(incident.last_name)} — <span class="font-normal text-xs text-gray-600">${type}</span></h3>
                                <p class="text-xs text-gray-500">${escapeHtml(incident.address)}</p>
                            </div>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full ${levelColor} font-semibold">${incident.incident_level.toUpperCase()}</span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                            <p class="text-gray-600">Date</p>
                            <p class="font-semibold text-gray-800">${new Date(incident.start_timestamp).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }).replace(/\/2025$/, '/25')}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                            <p class="text-gray-600">Response</p>
                            <p class="font-semibold text-green-600">${responseTime}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                            <p class="text-gray-600">Status</p>
                            <p class="font-semibold text-blue-600">RESOLVED</p>
                        </div>
                    </div>
                `;
                container.appendChild(incidentElement);
            });
        }

      function renderMapContent() {
    renderMetrics(); // Update metrics used here too
    if (mainMapInstance) {
        mainMapInstance.invalidateSize(true);
        updateMapMarkersFixed();  // ✅ MOVED HERE - ensures map is resized BEFORE plotting markers
    }
}

        // function initializeMainMap() {
        //     const mapId = 'main-leaflet-map';
        //     if (mainMapInstance) { mainMapInstance.invalidateSize(); return; }
        //     const initialCoords = [14.1130, 122.9555]; 
        //     const initialZoom = 14;
        //     mainMapInstance = L.map(mapId).setView(initialCoords, initialZoom);
        //     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors', maxZoom: 19 }).addTo(mainMapInstance);
        //     mainMapInstance.invalidateSize(true); // Initial fix for map tile loading
        // }

        function initializeMainMap() {
    const mapId = 'main-leaflet-map';
    
    // Check if map container exists
    const mapContainer = document.getElementById(mapId);
    if (!mapContainer) {
        console.error('Map container not found:', mapId);
        return;
    }
    
    // Destroy existing map if present
    if (mainMapInstance) {
        mainMapInstance.remove();
        mainMapInstance = null;
    }
    
    // Default to Daet, Camarines Norte area and restrict map to that region
    const DAET_BOUNDS = [[13.95, 122.75], [14.35, 123.15]];
    const DAET_CENTER = [14.1182, 122.9455];
    const DAET_ZOOM = 12;

    try {
        mainMapInstance = L.map(mapId).setView(DAET_CENTER, DAET_ZOOM);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(mainMapInstance);
        
        // Critical: invalidate size after adding to DOM
        setTimeout(() => {
            if (mainMapInstance) mainMapInstance.invalidateSize(true);
        }, 100);
        // Restrict panning/zooming to the Daet area
        try {
            mainMapInstance.setMaxBounds(DAET_BOUNDS);
        } catch (e) { console.warn('Could not set max bounds for map:', e); }
        
        console.log('Map initialized successfully');
    } catch (error) {
        console.error('Error initializing map:', error);
    }
}

// function updateMapMarkersFixed() {
//     if (!mainMapInstance) {
//         console.error('Map instance not initialized');
//         return;
//     }

//     // Clear existing markers
//     activeMarkers.forEach(marker => {
//         try {
//             mainMapInstance.removeLayer(marker);
//         } catch (e) {
//             console.warn('Error removing marker:', e);
//         }
//     });
//     activeMarkers = [];
    
//     // Clear routing if exists
//     if (routingControl) {
//         try {
//             mainMapInstance.removeControl(routingControl);
//         } catch (e) {
//             console.warn('Error removing routing:', e);
//         }
//         routingControl = null;
//     }

//     const boundsArray = [];
//     let destinationLatLng = null;
//     let startStationLatLng = null;
//     let minDistance = Infinity;

//     // === PLOT BFP STATIONS ===
//     if (Array.isArray(window.bfpStations) && window.bfpStations.length > 0) {
//         window.bfpStations.forEach(station => {
//             const lat = parseFloat(station.latitude);
//             const lon = parseFloat(station.longitude);
            
//             if (isNaN(lat) || isNaN(lon)) {
//                 console.warn('Invalid station coordinates:', station);
//                 return;
//             }

//             // Use a simple red circle for BFP stations
//             const stationMarker = L.circleMarker([lat, lon], {
//                 radius: 10,
//                 fillColor: '#b91919',
//                 color: '#fff',
//                 weight: 2,
//                 opacity: 1,
//                 fillOpacity: 0.8
//             }).addTo(mainMapInstance);
            
//             stationMarker.bindPopup(`<b>🚒 ${escapeHtml(station.station_name)}</b><br>Status: Active`);
//             activeMarkers.push(stationMarker);
//             boundsArray.push([lat, lon]);
//         });
//     }

//     // === PLOT DEVICES ===
//     if (Array.isArray(window.activeDevices) && window.activeDevices.length > 0) {
//         window.activeDevices.forEach(device => {
//             const lat = parseFloat(device.latitude);
//             const lon = parseFloat(device.longitude);
            
//             if (isNaN(lat) || isNaN(lon)) {
//                 console.warn('Invalid device coordinates:', device);
//                 return;
//             }

//             const isAlert = device.has_active_alert === true;
//             const owner = Array.isArray(users) ? users.find(u => u.user_ID.toString() === device.FK_user_ID.toString()) : null;
//             const ownerName = owner ? `${owner.first_name} ${owner.last_name}` : 'Unknown';
            
//             let markerColor, markerRadius, popupContent;

//             if (isAlert) {
//                 // Red pulsing circle for active alerts
//                 markerColor = '#ff0000';
//                 markerRadius = 12;
//                 popupContent = `<b>🔥 ACTIVE ALERT</b><br>${escapeHtml(device.location_name || device.address)}<br>Owner: ${escapeHtml(ownerName)}`;
                
//                 // Add pulse circle
//                 const pulseCircle = L.circleMarker([lat, lon], {
//                     radius: 20,
//                     fillColor: '#ff0000',
//                     color: '#ff0000',
//                     weight: 2,
//                     opacity: 0.3,
//                     fillOpacity: 0.2
//                 }).addTo(mainMapInstance);
//                 activeMarkers.push(pulseCircle);
                
//                 destinationLatLng = L.latLng(lat, lon);
//             } else if (device.status === 'inactive' || device.status === 'offline') {
//                 // Gray for inactive
//                 markerColor = '#999999';
//                 markerRadius = 8;
//                 popupContent = `<b>📍 Inactive Device</b><br>${escapeHtml(device.location_name || device.address)}<br>Owner: ${escapeHtml(ownerName)}`;
//             } else {
//                 // Blue for active
//                 markerColor = '#3b82f6';
//                 markerRadius = 10;
//                 popupContent = `<b>📍 Active Device</b><br>${escapeHtml(device.location_name || device.address)}<br>Owner: ${escapeHtml(ownerName)}`;
                
//                 if (!destinationLatLng) {
//                     destinationLatLng = L.latLng(lat, lon);
//                 }
//             }

//             const marker = L.circleMarker([lat, lon], {
//                 radius: markerRadius,
//                 fillColor: markerColor,
//                 color: '#fff',
//                 weight: 2,
//                 opacity: 1,
//                 fillOpacity: 0.8
//             }).addTo(mainMapInstance);
            
//             marker.bindPopup(popupContent);
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         });
//     }

//     // === FIT BOUNDS ===
//     if (boundsArray.length > 0) {
//         try {
//             const bounds = L.latLngBounds(boundsArray);
//             mainMapInstance.fitBounds(bounds, { padding: [50, 50] });
//         } catch (e) {
//             console.warn('Error fitting bounds:', e);
//         }
//     }

//     console.log('Map markers updated:', activeMarkers.length, 'markers');
// }


function updateMapMarkersFixed() {
    if (!mainMapInstance) {
        console.error('✗ Map instance not initialized');
        return;
    }

    activeMarkers.forEach(marker => {
        try {
            mainMapInstance.removeLayer(marker);
        } catch (e) {
            console.warn('Error removing marker:', e);
        }
    });
    activeMarkers = [];
    
    if (routingControl) {
        try {
            mainMapInstance.removeControl(routingControl);
        } catch (e) {
            console.warn('Error removing routing:', e);
        }
        routingControl = null;
    }

    const boundsArray = [];

    // ✅ FIXED: Use bfpStations (not window.bfpStations)
    if (Array.isArray(bfpStations) && bfpStations.length > 0) {
        console.log('📍 Plotting', bfpStations.length, 'BFP stations');
        
        bfpStations.forEach(station => {
            const lat = parseFloat(station.latitude);
            const lon = parseFloat(station.longitude);
            
            if (isNaN(lat) || isNaN(lon)) {
                console.warn('Invalid station coords:', station.station_name);
                return;
            }

            const stationMarker = L.circleMarker([lat, lon], {
                radius: 10,
                fillColor: '#b91919',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(mainMapInstance);
            const stationLat = lat.toFixed(6);
            const stationLon = lon.toFixed(6);
            const stationCoords = `${stationLat}, ${stationLon}`;
            const stationAddress = station.address || station.station_address || station.location || '';

            stationMarker.bindPopup(
                `<b>🚒 ${escapeHtml(station.station_name)}</b><br>` +
                `<b>Contact:</b> ${escapeHtml(station.contact_number || '')}<br>` +
                (stationAddress ? `<b>Address:</b> ${escapeHtml(stationAddress)}<br>` : '') +
                `<b>Coordinates:</b> ${escapeHtml(stationCoords)}`
            );
            activeMarkers.push(stationMarker);
            boundsArray.push([lat, lon]);
        });
    }

    // ✅ FIXED: Use window.activeDevices (this is correct)
    if (Array.isArray(window.activeDevices) && window.activeDevices.length > 0) {
        console.log('📱 Plotting', window.activeDevices.length, 'devices');
        
        window.activeDevices.forEach(device => {
            const lat = parseFloat(device.latitude);
            const lon = parseFloat(device.longitude);
            
            if (isNaN(lat) || isNaN(lon)) {
                console.warn('Invalid device coords:', device);
                return;
            }

            const isAlert = device.has_active_alert === true;
            const owner = Array.isArray(users) ? users.find(u => u.user_ID.toString() === device.FK_user_ID.toString()) : null;
            const ownerName = owner ? `${owner.first_name} ${owner.last_name}` : 'Unknown';
            
            let markerColor, markerRadius, popupContent;

            if (isAlert) {
                markerColor = '#ff0000';
                markerRadius = 12;
                const devLat = lat.toFixed(6);
                const devLon = lon.toFixed(6);
                const devCoords = `${devLat}, ${devLon}`;
                const devAddress = device.location_name || device.address || '';
                popupContent = `<b>🔥 ACTIVE ALERT</b><br>` +
                               (devAddress ? `${escapeHtml(devAddress)}<br>` : '') +
                               `Owner: ${escapeHtml(ownerName)}<br>` +
                               `<b>Coordinates:</b> ${escapeHtml(devCoords)}`;
                
                const pulseCircle = L.circleMarker([lat, lon], {
                    radius: 20,
                    fillColor: '#ff0000',
                    color: '#ff0000',
                    weight: 2,
                    opacity: 0.3,
                    fillOpacity: 0.2
                }).addTo(mainMapInstance);
                activeMarkers.push(pulseCircle);
            } else if (device.status === 'inactive' || device.status === 'offline') {
                markerColor = '#999999';
                markerRadius = 8;
                const devLat = lat.toFixed(6);
                const devLon = lon.toFixed(6);
                const devCoords = `${devLat}, ${devLon}`;
                const devAddress = device.location_name || device.address || '';
                popupContent = `<b>📍 Inactive Device</b><br>` +
                               (devAddress ? `${escapeHtml(devAddress)}<br>` : '') +
                               `Owner: ${escapeHtml(ownerName)}<br>` +
                               `<b>Coordinates:</b> ${escapeHtml(devCoords)}`;
            } else {
                markerColor = '#3b82f6';
                markerRadius = 10;
                const devLat = lat.toFixed(6);
                const devLon = lon.toFixed(6);
                const devCoords = `${devLat}, ${devLon}`;
                const devAddress = device.location_name || device.address || '';
                popupContent = `<b>🏠 Active Device</b><br>` +
                               (devAddress ? `${escapeHtml(devAddress)}<br>` : '') +
                               `Owner: ${escapeHtml(ownerName)}<br>` +
                               `<b>Coordinates:</b> ${escapeHtml(devCoords)}`;
            }

            const marker = L.circleMarker([lat, lon], {
                radius: markerRadius,
                fillColor: markerColor,
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(mainMapInstance);
            
            marker.bindPopup(popupContent);
            activeMarkers.push(marker);
            boundsArray.push([lat, lon]);
        });
    }

    // Fit bounds
    if (boundsArray.length > 0) {
        try {
            const bounds = L.latLngBounds(boundsArray);
            mainMapInstance.fitBounds(bounds, { padding: [50, 50] });
            console.log('✓ Map bounds fitted for', boundsArray.length, 'locations');
        } catch (e) {
            console.warn('Error fitting bounds:', e);
        }
    }

    console.log('✓ Map updated with', activeMarkers.length, 'markers');
}
        // let routingControl = null;
// function updateMapMarkers() {
//     if (!mainMapInstance) return;

//     activeMarkers.forEach(marker => mainMapInstance.removeLayer(marker));
//     activeMarkers = [];
    
//     // Icon Definitions (These are correct and already defined in your code)
//     const fireIcon = L.divIcon({ className: 'custom-fire-icon', html: '<div style="font-size:32px; color: #ff0000;">🚨</div>', iconSize: [30, 30], iconAnchor: [15, 30] });
//     const stationIcon = L.divIcon({ className: 'custom-bfp-icon', html: `<div class="bg-[#b91919] text-white p-1 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="width: 28px; height: 28px; font-size: 14px;">🚒</div>`, iconSize: [28, 28], iconAnchor: [14, 28] });
//     const deviceIcon = L.divIcon({ className: 'custom-device-icon', html: '<div style="width:12px;height:12px;border-radius:50%;background:#3b82f6;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });
//     // This is the icon for Inactive Devices (Gray Dot)
//     const inactiveIcon = L.divIcon({ html: '<div style="width:12px;height:12px;border-radius:50%;background:gray;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });


//     const boundsArray = [];

//     // Plot BFP Stations (NO CHANGE - for context only)
//     bfpStations.forEach(station => {
//         const lat = parseFloat(station.latitude);
//         const lon = parseFloat(station.longitude);
//         if (!isNaN(lat) && !isNaN(lon)) {
//             const marker = L.marker([lat, lon], { icon: stationIcon, draggable: false }).addTo(mainMapInstance);
//             marker.bindPopup(`<b>BFP Station:</b> ${escapeHtml(station.station_name)}`).openPopup();
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });

//     // === START OF CORRECTED LOGIC ===
//     // This assumes window.activeDevices now contains ALL devices (active, inactive, error) 
//     // and each device object includes its 'status' property.
//     window.activeDevices.forEach(device => {
//         const isAlert = device.has_active_alert;
//         const lat = parseFloat(device.latitude);
//         const lon = parseFloat(device.longitude);
//         const owner = users.find(u => u.user_ID.toString() === device.FK_user_ID.toString());
//         const title = device.location_name || device.address;
        
//         let markerIcon;
//         let statusText;
//         let statusColor;

//         if (!isNaN(lat) && !isNaN(lon)) {
            
//             // 1. Check for Active Alert (Red Pulse)
//             if (isAlert) {
//                 markerIcon = fireIcon;
//                 statusText = 'ACTIVE ALERT';
//                 statusColor = 'red';
//             } 
//             // 2. Check for Inactive/Error (Gray Dot) - ***THIS IS THE MISSING LOGIC***
//             else if (device.status === 'inactive' || device.status === 'error') { 
//                 markerIcon = inactiveIcon;
//                 statusText = device.status.toUpperCase();
//                 statusColor = 'gray';
//             }
//             // 3. Default to Active Device (Blue Dot)
//             else {
//                 markerIcon = deviceIcon;
//                 statusText = 'Active';
//                 statusColor = 'green';
//             }

//             let popupContent = `
//                 <b>Property:</b> ${escapeHtml(title)}<br>
//                 <b>Owner:</b> ${escapeHtml(owner ? owner.first_name + ' ' + owner.last_name : 'N/A')}<br>
//                 <b>Sensor:</b> ${escapeHtml(device.sensor_type)} (ID: ${device.sensor_ID})<br>
//                 <b>Status:</b> <span style="color:${statusColor};">${statusText}</span>
//             `;
            
//             const marker = L.marker([lat, lon], { icon: markerIcon, draggable: false }).addTo(mainMapInstance);
            
//             marker.bindPopup(popupContent);
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });
//     // === END OF CORRECTED LOGIC ===
    
//     if (boundsArray.length > 0) {
//         const bounds = L.latLngBounds(boundsArray);
//         mainMapInstance.fitBounds(bounds, { padding: [50, 50] });
//     }
// }   

// let routingControl = null; // Global variable to track the road line

// function updateMapMarkers() {
//     if (!mainMapInstance) return;

//     // 1. Clear existing markers and route
//     activeMarkers.forEach(marker => mainMapInstance.removeLayer(marker));
//     activeMarkers = [];
    
//     if (routingControl) {
//         mainMapInstance.removeControl(routingControl);
//         routingControl = null;
//     }

//     // 2. Define Icons
//     const fireIcon = L.divIcon({ className: 'custom-fire-icon', html: '<div style="font-size:32px; color: #ff0000;">🚨</div>', iconSize: [30, 30], iconAnchor: [15, 30] });
//     const stationIcon = L.divIcon({ className: 'custom-bfp-icon', html: `<div class="bg-[#b91919] text-white p-1 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="width: 28px; height: 28px; font-size: 14px;">🚒</div>`, iconSize: [28, 28], iconAnchor: [14, 28] });
//     const deviceIcon = L.divIcon({ className: 'custom-device-icon', html: '<div style="width:12px;height:12px;border-radius:50%;background:#3b82f6;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });
//     const inactiveIcon = L.divIcon({ html: '<div style="width:12px;height:12px;border-radius:50%;background:gray;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });

//     const boundsArray = [];
    
//     // Route Calculation Variables
//     let destinationLatLng = null; // Target (Fire or Resident)
//     let startStationLatLng = null; // Source (Station)
//     let minDistance = Infinity;

//     // 3. Plot BFP Stations
//     bfpStations.forEach(station => {
//         const lat = parseFloat(station.latitude);
//         const lon = parseFloat(station.longitude);
//         if (!isNaN(lat) && !isNaN(lon)) {
//             const marker = L.marker([lat, lon], { icon: stationIcon, draggable: false }).addTo(mainMapInstance);
//             marker.bindPopup(`<b>BFP Station:</b> ${escapeHtml(station.station_name)}`);
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });

//     // 4. Plot Devices & Find Target (Priority: Alert > First Active Device)
//     window.activeDevices.forEach(device => {
//         const isAlert = device.has_active_alert;
//         const lat = parseFloat(device.latitude);
//         const lon = parseFloat(device.longitude);
//         const owner = users.find(u => u.user_ID.toString() === device.FK_user_ID.toString());
//         const title = device.location_name || device.address;
        
//         let markerIcon, statusText, statusColor;

//         if (!isNaN(lat) && !isNaN(lon)) {
            
//             // Determine Marker Type & Route Target
//             if (isAlert) {
//                 markerIcon = fireIcon;
//                 statusText = 'ACTIVE ALERT';
//                 statusColor = 'red';
//                 // If this is an alert, it becomes our PRIMARY target for the road
//                 destinationLatLng = L.latLng(lat, lon); 
//             } else if (device.status === 'inactive' || device.status === 'error') { 
//                 markerIcon = inactiveIcon;
//                 statusText = device.status.toUpperCase();
//                 statusColor = 'gray';
//             } else {
//                 markerIcon = deviceIcon;
//                 statusText = 'Active';
//                 statusColor = 'green';
//                 // If no alert exists yet, use the first active device as a "Demo Target"
//                 if (!destinationLatLng) destinationLatLng = L.latLng(lat, lon);
//             }

//             // Create Popup Content
//             let popupContent = `
//                 <b>Property:</b> ${escapeHtml(title)}<br>
//                 <b>Owner:</b> ${escapeHtml(owner ? owner.first_name + ' ' + owner.last_name : 'N/A')}<br>
//                 <b>Sensor:</b> ${escapeHtml(device.sensor_type)} (ID: ${device.sensor_ID})<br>
//                 <b>Status:</b> <span style="color:${statusColor};">${statusText}</span>
//             `;

//             const marker = L.marker([lat, lon], { icon: markerIcon, draggable: false }).addTo(mainMapInstance);
//             marker.bindPopup(popupContent);
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });

//     // 5. Find Nearest Station to the chosen Target
//     if (destinationLatLng && bfpStations.length > 0) {
//         bfpStations.forEach(station => {
//             const sLat = parseFloat(station.latitude);
//             const sLon = parseFloat(station.longitude);
//             if (!isNaN(sLat) && !isNaN(sLon)) {
//                 const sLatLng = L.latLng(sLat, sLon);
//                 const dist = mainMapInstance.distance(destinationLatLng, sLatLng);
//                 if (dist < minDistance) {
//                     minDistance = dist;
//                     startStationLatLng = sLatLng;
//                 }
//             }
//         });
//     }

//     // 6. Draw Red Road (Station -> Target)
//     if (destinationLatLng && startStationLatLng) {
//         if (typeof L.Routing !== 'undefined') {
//             routingControl = L.Routing.control({
//                 waypoints: [
//                     startStationLatLng, // Start
//                     destinationLatLng   // End
//                 ],
//                 router: L.Routing.osrmv1({
//                     serviceUrl: 'https://router.project-osrm.org/route/v1'
//                 }),
//                 lineOptions: {
//                     styles: [{color: '#ef4444', opacity: 0.8, weight: 6}] // Red Line
//                 },
//                 createMarker: function() { return null; }, // No extra markers
//                 addWaypoints: false,
//                 draggableWaypoints: false,
//                 fitSelectedRoutes: true,
//                 show: false
//             }).addTo(mainMapInstance);
//         } else {
//             console.error("Routing Library missing. Road cannot be drawn.");
//         }
//     } else if (boundsArray.length > 0) {
//         // Only fit bounds manually if no route was drawn (otherwise route handles zoom)
//         mainMapInstance.fitBounds(L.latLngBounds(boundsArray), { padding: [50, 50] });
//     }
// }


/**
    * Calculate straight-line distance between two coordinates using Leaflet's distanceTo method.
    * @param {number} lat1 - Latitude of the first point.
    * @param {number} lon1 - Longitude of the first point.
    * @param {number} lat2 - Latitude of the second point.
    * @param {number} lon2 - Longitude of the second point.
    * @returns {string} - Distance in kilometers formatted to two decimal places.
    */
function calculateStraightLineDistance(lat1, lon1, lat2, lon2) {
    if (!L || typeof L.latLng !== 'function') return 'N/A'; // Check for Leaflet availability
    
    const point1 = L.latLng(lat1, lon1);
    const point2 = L.latLng(lat2, lon2);
    
    // distanceTo returns meters, so divide by 1000 for kilometers
    const distanceMeters = point1.distanceTo(point2);
    const distanceKm = (distanceMeters / 1000).toFixed(2); 
    
    return `${distanceKm} km`;
}

// function updateMapMarkers() {
//     if (!mainMapInstance) return;

//     activeMarkers.forEach(marker => mainMapInstance.removeLayer(marker));
//     activeMarkers = [];
    
//     // Icon Definitions (These are correct and already defined in your code)
//     const fireIcon = L.divIcon({ className: 'custom-fire-icon', html: '<div style="font-size:32px; color: #ff0000;">🚨</div>', iconSize: [30, 30], iconAnchor: [15, 30] });
//     const stationIcon = L.divIcon({ className: 'custom-bfp-icon', html: `<div class="bg-[#b91919] text-white p-1 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="width: 28px; height: 28px; font-size: 14px;">🚒</div>`, iconSize: [28, 28], iconAnchor: [14, 28] });
//     const deviceIcon = L.divIcon({ className: 'custom-device-icon', html: '<div style="width:12px;height:12px;border-radius:50%;background:#3b82f6;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });
//     // This is the icon for Inactive Devices (Gray Dot)
//     const inactiveIcon = L.divIcon({ html: '<div style="width:12px;height:12px;border-radius:50%;background:gray;border:3px solid white;"></div>', iconSize: [24, 24], iconAnchor: [12, 12] });


//     const boundsArray = [];

//     // Plot BFP Stations (NO CHANGE - for context only)
//     bfpStations.forEach(station => {
//         const lat = parseFloat(station.latitude);
//         const lon = parseFloat(station.longitude);
//         if (!isNaN(lat) && !isNaN(lon)) {
//             const marker = L.marker([lat, lon], { icon: stationIcon, draggable: false }).addTo(mainMapInstance);
//             marker.bindPopup(`<b>BFP Station:</b> ${escapeHtml(station.station_name)}`).openPopup();
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });

//     // === START OF REVISED LOGIC (Device Plotting with Coordinates and Road/Address) ===
//     window.activeDevices.forEach(device => {
//         const isAlert = device.has_active_alert;
//         const lat = parseFloat(device.latitude);
//         const lon = parseFloat(device.longitude);
//         const owner = users.find(u => u.user_ID.toString() === device.FK_user_ID.toString());
//         const title = device.location_name || device.address; // This variable holds the "ROAD/ADDRESS" info
        
//         let markerIcon;
//         let statusText;
//         let statusColor;

//         if (!isNaN(lat) && !isNaN(lon)) {
            
//             // 1. Check for Active Alert (Red Pulse)
//             if (isAlert) {
//                 markerIcon = fireIcon;
//                 statusText = 'ACTIVE ALERT';
//                 statusColor = 'red';
//             } 
//             // 2. Check for Inactive/Error (Gray Dot) - ***THIS IS THE MISSING LOGIC***
//             else if (device.status === 'inactive' || device.status === 'error') { 
//                 markerIcon = inactiveIcon;
//                 statusText = device.status.toUpperCase();
//                 statusColor = 'gray';
//             }
//             // 3. Default to Active Device (Blue Dot)
//             else {
//                 markerIcon = deviceIcon;
//                 statusText = 'Active';
//                 statusColor = 'green';
//             }

//             // --- ADDED COORDINATES AND ROAD/ADDRESS ---
//             const coordinates = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;

//             let popupContent = `
//                 <b>Location/Road:</b> ${escapeHtml(title)}<br>
//                 <b>Coordinates:</b> ${coordinates}<br>
//                 <b>Owner:</b> ${escapeHtml(owner ? owner.first_name + ' ' + owner.last_name : 'N/A')}<br>
//                 <b>Sensor:</b> ${escapeHtml(device.sensor_type)} (ID: ${device.sensor_ID})<br>
//                 <b>Status:</b> <span style="color:${statusColor};">${statusText}</span>
//             `;
//             // ------------------------------------------
            
//             const marker = L.marker([lat, lon], { icon: markerIcon, draggable: false }).addTo(mainMapInstance);
            
//             marker.bindPopup(popupContent);
//             activeMarkers.push(marker);
//             boundsArray.push([lat, lon]);
//         }
//     });
//     // === END OF REVISED LOGIC ===
    
//     if (boundsArray.length > 0) {
//         const bounds = L.latLngBounds(boundsArray);
//         mainMapInstance.fitBounds(bounds, { padding: [50, 50] });
//     }
// }

let routingControl = null;

function updateMapMarkers() {
    if (!mainMapInstance) return;

    // 1. Clear existing markers/routes
    activeMarkers.forEach(marker => mainMapInstance.removeLayer(marker));
    activeMarkers = [];
    if (routingControl) {
        mainMapInstance.removeControl(routingControl);
        routingControl = null;
    }

    // 2. Define Image Icons (Matching your Dashboard)
    const fireIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/9312/9312231.png',
        iconSize: [50, 50], iconAnchor: [25, 50], popupAnchor: [0, -50]
    });

    const stationIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/2237/2237536.png', // Building Icon
        iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40]
    });

    const deviceIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/619/619032.png', // House Icon
        iconSize: [30, 30], iconAnchor: [15, 30], popupAnchor: [0, -30]
    });

    const inactiveIcon = L.divIcon({ 
        html: '<div style="width:14px;height:14px;border-radius:50%;background:gray;border:2px solid white;"></div>', 
        iconSize: [14, 14], iconAnchor: [7, 7] 
    });

    const boundsArray = [];
    let destinationLatLng = null; 
    let startStationLatLng = null;
    let minDistance = Infinity;

    // 3. Plot BFP Stations
    if (typeof bfpStations !== 'undefined' && Array.isArray(bfpStations)) {
        bfpStations.forEach(station => {
            const lat = parseFloat(station.latitude);
            const lon = parseFloat(station.longitude);
            if (!isNaN(lat) && !isNaN(lon)) {
                const marker = L.marker([lat, lon], { icon: stationIcon }).addTo(mainMapInstance);
                const sLat = lat.toFixed(6);
                const sLon = lon.toFixed(6);
                const sCoords = `${sLat}, ${sLon}`;
                const sAddress = station.address || station.station_address || station.location || '';
                marker.bindPopup(
                    `<b>🏢 ${escapeHtml(station.station_name)}</b><br>` +
                    (sAddress ? `<b>Address:</b> ${escapeHtml(sAddress)}<br>` : '') +
                    `<b>Coordinates:</b> ${escapeHtml(sCoords)}<br>` +
                    `<b>Contact:</b> ${escapeHtml(station.contact_number || '')}`
                );
                activeMarkers.push(marker);
                boundsArray.push([lat, lon]);
            }
        });
    }

    // 4. Plot Devices & Identify Target
    const devicesList = window.activeDevices || [];
    devicesList.forEach(device => {
        const isAlert = device.has_active_alert;
        const lat = parseFloat(device.latitude);
        const lon = parseFloat(device.longitude);
        const owner = users.find(u => u.user_ID.toString() === device.FK_user_ID.toString());
        const name = owner ? `${owner.first_name} ${owner.last_name}` : 'Unknown';
        
        if (!isNaN(lat) && !isNaN(lon)) {
            let markerIcon = deviceIcon;
            
            // Priority: Alert > Inactive > Active
            if (isAlert) {
                markerIcon = fireIcon;
                destinationLatLng = L.latLng(lat, lon); // Set as Route Target
                
                // Add Red Circle Pulse
                const circle = L.circle([lat, lon], { color: 'red', fillColor: '#f03', fillOpacity: 0.3, radius: 200 }).addTo(mainMapInstance);
                activeMarkers.push(circle);
            } else if (device.status === 'inactive') {
                markerIcon = inactiveIcon;
            } else {
                // If no alert exists, use first active device as demo target
                if (!destinationLatLng) destinationLatLng = L.latLng(lat, lon);
            }

            const marker = L.marker([lat, lon], { icon: markerIcon }).addTo(mainMapInstance);
            const dLat = lat.toFixed(6);
            const dLon = lon.toFixed(6);
            const dCoords = `${dLat}, ${dLon}`;
            const dAddress = device.location_name || device.address || '';
            marker.bindPopup(
                `<b>${isAlert ? '🔥 ALERT' : '🏠 Device'}</b><br>` +
                `${escapeHtml(name)}<br>` +
                (dAddress ? `${escapeHtml(dAddress)}<br>` : '') +
                `<b>Coordinates:</b> ${escapeHtml(dCoords)}`
            );
            activeMarkers.push(marker);
            boundsArray.push([lat, lon]);
        }
    });

    // 5. Find Nearest Station & Draw Road
    if (destinationLatLng && bfpStations.length > 0) {
        bfpStations.forEach(station => {
            const sLat = parseFloat(station.latitude);
            const sLon = parseFloat(station.longitude);
            if (!isNaN(sLat) && !isNaN(sLon)) {
                const sLatLng = L.latLng(sLat, sLon);
                const dist = mainMapInstance.distance(destinationLatLng, sLatLng);
                if (dist < minDistance) {
                    minDistance = dist;
                    startStationLatLng = sLatLng;
                }
            }
        });

        if (startStationLatLng && typeof L.Routing !== 'undefined') {
            routingControl = L.Routing.control({
                waypoints: [startStationLatLng, destinationLatLng],
                router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
                lineOptions: { styles: [{color: '#ef4444', opacity: 0.8, weight: 6}] },
                createMarker: function() { return null; },
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false
            }).addTo(mainMapInstance);
        }
    } else if (boundsArray.length > 0) {
        mainMapInstance.fitBounds(boundsArray, { padding: [50, 50] });
    }
}
        
        function renderAll() {
            renderMetrics(); 
            renderUserList(); 
            renderActiveAlerts(); 
            renderResponseHistory(); 
            renderMapContent(); 
        }

        function switchTab(targetId) {
            if (!targetId) return;

            document.querySelectorAll('.page-content').forEach(p => { p.id === targetId ? p.classList.remove('hidden') : p.classList.add('hidden'); });
            document.querySelectorAll('.nav-link').forEach(link => {
                const href = link.getAttribute('href');
                if (href === `#${targetId}`) {
                    link.classList.add('bg-[#fef2f2]', 'text-[#d93b3b]');
                    link.classList.remove('text-gray-500');
                } else {
                    link.classList.remove('bg-[#fef2f2]', 'text-[#d93b3b]');
                    link.classList.add('text-gray-500');
                }
            });

            if (history && history.replaceState) { history.replaceState(null, '', `#${targetId}`); } else { window.location.hash = `#${targetId}`; }
            if (targetId === 'maps-content') {
                // Define Daet / Camarines Norte bounds and center (restrict map to this region)
                const DAET_BOUNDS = [[13.95, 122.75], [14.35, 123.15]];
                const DAET_CENTER = [14.1182, 122.9455];
                const DAET_ZOOM = 12;

                if (mainMapInstance) {
                    // CRUCIAL: Must invalidate size when the map div is revealed
                    setTimeout(() => {
                        try {
                            mainMapInstance.invalidateSize(true);
                            renderMapContent(); // refresh markers and bounds when opening map tab
                            // Finally clamp view to Daet area
                            try {
                                mainMapInstance.setMaxBounds(DAET_BOUNDS);
                                mainMapInstance.setView(DAET_CENTER, DAET_ZOOM);
                            } catch (e) { console.warn('Failed to enforce Daet bounds:', e); }
                        } catch (e) { console.warn('Map refresh error on tab switch:', e); }
                    }, 120);
                } else {
                    // If map not yet initialized, initialize and render
                    try { initializeMainMap(); } catch (e) { console.warn('init map on tab switch failed:', e); }
                    setTimeout(() => {
                        try { renderMapContent(); } catch (e) {}
                        try {
                            if (mainMapInstance) {
                                mainMapInstance.setMaxBounds(DAET_BOUNDS);
                                mainMapInstance.setView(DAET_CENTER, DAET_ZOOM);
                            }
                        } catch (e) { console.warn('Failed to set Daet view after init:', e); }
                    }, 300);
                }
            }
        }

        // --- Other Utilities ---
        
        function updateStationDisplays() {
            try {
                // Find BFP Daet Central or default to the first station
                const station = bfpStations.find(s => /daet/i.test(s.station_name)) || bfpStations[0];
                if (!station) return;

                const stationName = station.station_name.replace(/^BFP\s+/i, '');
                const coords = `${parseFloat(station.latitude).toFixed(4)}, ${parseFloat(station.longitude).toFixed(4)}`;

                //document.getElementById('header-station')?.textContent = stationName;
                document.querySelectorAll('[data-station="name"]').forEach(el => el.textContent = stationName);
                document.querySelectorAll('[data-station="code"]').forEach(el => el.textContent = `BFP-DC${station.station_id}`);
                document.querySelectorAll('[data-station="coverage"]').forEach(el => el.textContent = coords);
                document.querySelectorAll('[data-station="units"]').forEach(el => el.textContent = station.contact_number);
            } catch (e) { console.error('updateStationDisplays error:', e); }
        }

        function loadNotificationSettings() {
            const mapping = { emergencyAlerts: 'emergency-toggle', smsNotifications: 'sms-toggle', systemUpdates: 'updates-toggle' };
            Object.keys(mapping).forEach(k => {
                const el = document.getElementById(mapping[k]);
                if (el) el.checked = localStorage.getItem(k) !== '0';
            });
        }

        function saveNotificationSetting(key, checked) {
            localStorage.setItem(key, checked ? '1' : '0');
        }
        
        function formatTimeAgo(timestamp) {
            const seconds = Math.floor((new Date() - new Date(timestamp)) / 1000);
            if (seconds < 60) return `${seconds}s ago`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            return new Date(timestamp).toLocaleDateString();
        }

        function updateTime() {
            const currentDateEl = document.getElementById('current-date');
            const currentTimeEl = document.getElementById('current-time');
            if (currentDateEl && currentTimeEl) { 
                const now = new Date();
                currentDateEl.textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                currentTimeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            }
        }


        document.addEventListener('DOMContentLoaded', () => {
            updateTime();
            // setInterval(updateTime, 1000);

            // checkSession(); // Only required if a real session mechanism existed

            document.getElementById('login-form').addEventListener('submit', handleLogin);
            document.getElementById('sign-out-btn').addEventListener('click', () => { if(confirm('Sign Out?')) showLogin(); });

            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const targetId = link.getAttribute('href').substring(1);
                    switchTab(targetId);
                });
            });

            const userSearchEl = document.getElementById('user-search');
            if (userSearchEl) {
                userSearchEl.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    const filtered = users.filter(user => 
                        user.first_name.toLowerCase().includes(searchTerm) || 
                        user.last_name.toLowerCase().includes(searchTerm) ||
                        user.email.toLowerCase().includes(searchTerm)
                    );
                    renderUserList(filtered);
                });
            }
            
            loadNotificationSettings();
            
            const emergencyToggle = document.getElementById('emergency-toggle');
            const smsToggle = document.getElementById('sms-toggle');
            const updatesToggle = document.getElementById('updates-toggle');
            if (emergencyToggle) emergencyToggle.addEventListener('change', (e) => saveNotificationSetting('emergencyAlerts', e.target.checked));
            if (smsToggle) smsToggle.addEventListener('change', (e) => saveNotificationSetting('smsNotifications', e.target.checked));
            if (updatesToggle) updatesToggle.addEventListener('change', (e) => saveNotificationSetting('systemUpdates', e.target.checked));
            
            // Initial render on load (if not authenticated)
            fetchAllData();
            
            // Map coordinate search (simulated)
            window.searchCoordinates = function() {
                const input = document.getElementById('map-coord-input').value.trim();
                const coords = input.split(',').map(c => parseFloat(c.trim()));
                if (coords.length === 2 && !isNaN(coords[0]) && !isNaN(coords[1])) {
                    if (mainMapInstance) {
                         mainMapInstance.setView([coords[0], coords[1]], 16);
                         L.marker([coords[0], coords[1]]).addTo(mainMapInstance).bindPopup("Searched Location").openPopup();
                    } else {
                         alert("Map is not initialized. Please navigate to the Map tab.");
                    }
                } else {
                    alert("Invalid coordinate format. Use 'Lat, Lon' (e.g., 14.11, 122.96).");
                }
            };

        });
        
    </script>

</body>
</html>