<?php
// PHP login session check logic should ideally be at the top to redirect if already authenticated,
// but since this file handles both login and the dashboard content, the check is typically done in JS.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP Early Alert</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#b91919">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- LEAFLET IMPORTS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* Custom style for the simulated push notification toast */
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
    </style>
</head>
<body class="font-['Inter',_sans-serif] bg-gradient-to-br from-gray-800 to-gray-900">

    <!-- *** SIMULATED PUSH NOTIFICATION TOAST *** -->
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
    <!-- *** END TOAST *** -->


    <div id="app-page" class="max-w-md mx-auto bg-gray-50 min-h-screen flex flex-col shadow-2xl">

        <!-- === LOGIN CONTENT (VISIBLE BEFORE AUTH) === -->
        <section id="login-content" class="flex-1 p-8 flex flex-col justify-center items-center h-screen w-full">
            <div class="text-center mb-10">
                <img src="logo.png" alt="BFP Logo" class="w-22 h-18 mx-auto mb-4">
                <h2 class="text-2xl font-extrabold text-gray-800">BFP Officer Login</h2>
                <p class="text-sm text-gray-500">Early Alert Response & Access</p>
            </div>
            <form id="login-form" class="w-full max-w-xs space-y-4">
                <input type="email" id="login-email" placeholder="Officer Email" required class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl text-sm transition-all focus:outline-none focus:border-[#d93b3b] focus:ring-2 focus:ring-[#d93b3b]/20">
                <input type="password" id="login-password" placeholder="Password"  class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl text-sm transition-all focus:outline-none focus:border-[#d93b3b] focus:ring-2 focus:ring-[#d93b3b]/20">
                <button type="submit" id="login-btn" class="w-full bg-gradient-to-br from-[#d93b3b] to-[#b91919] text-white font-bold py-4 rounded-xl shadow-lg shadow-red-500/50 hover:from-red-600 hover:to-red-700 transition-all">
                    Login to Officer Hub
                </button>
                <p id="login-error" class="text-xs text-red-500 text-center hidden"></p>
            </form>
        </section>


        <!-- === DASHBOARD CONTENT (VISIBLE AFTER AUTH) === -->
        <div id="authenticated-content" class="hidden min-h-screen flex flex-col">
            
            <header class="bg-gradient-to-br from-[#d93b3b] to-[#b91919] text-white p-5 pt-6 rounded-b-3xl shadow-lg sticky top-0 z-20">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h1 class="text-xl font-extrabold tracking-tight">BFP Command Center</h1>
                        <p class="text-sm opacity-95 font-medium">Early Alert System</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold" id="current-date">Nov 02, 2025</p>
                        <p class="text-xs opacity-90" id="current-time">10:48 AM</p>
                    </div>
                </div>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span class="text-sm font-semibold" id="header-station">Manila Central Station</span>
                    </div>
                    <span class="bg-green-500 text-white text-xs font-bold py-1.5 px-3.5 rounded-full shadow-md shadow-green-500/30">On Duty</span>
                </div>
            </header>

            <main class="flex-1 p-5 pb-28">
                
                <!-- === HOME CONTENT (METRICS & USER LIST - Matches BFP Home Image) === -->
                <section id="home-content" class="page-content"> 
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        
                        <!-- Total Users (Blue) - Image 1 -->
                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-blue-500 text-white">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="total-users">5</p>
                            <p class="text-xs text-gray-500 font-medium">Total Users</p>
                        </div>

                        <!-- Active Devices (Green) - Image 1 -->
                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-green-500 text-white">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707l-.707-.707V8a6 6 0 00-6-6zm-6 8a1 1 0 11-2 0 1 1 0 012 0zm12-1a1 1 0 100-2 1 1 0 000 2z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="active-devices">20</p>
                            <p class="text-xs text-gray-500 font-medium">Active Devices</p>
                        </div>

                        <!-- Active Alerts (Red) - Image 1 -->
                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-red-500 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="active-alerts">1</p>
                            <p class="text-xs text-gray-500 font-medium">Active Alerts</p>
                        </div>

                        <!-- This Month / Monthly Responses (Purple) - Image 1 -->
                        <div class="bg-white p-5 rounded-2xl flex flex-col items-start justify-center gap-2 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-purple-500 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                            <p class="text-3xl font-extrabold text-gray-800 leading-none" data-metric="monthly-responses">5</p>
                            <p class="text-xs text-gray-500 font-medium">This Month</p>
                        </div>
                        
                    </div>

                    <!-- Registered Users List - Image 1 -->
                    <div class="bg-white rounded-2xl shadow-lg p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold text-gray-800">Registered Users</h2>
                            <div class="flex items-center gap-3">
                                <button id="add-user-btn" class="text-green-600 text-sm font-semibold hover:text-green-700 transition-colors" onclick="openUserModal(null)">
                                    + Add
                                </button>
                                <!-- Filter Button - Image 1 -->
                                <button id="filter-users-btn" class="flex items-center gap-1 text-blue-600 text-sm font-semibold hover:text-blue-700 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path></svg>
                                    Filter
                                </button>
                            </div>
                        </div>

                        <!-- Search Bar - Image 1 -->
                        <div class="relative mb-5">
                            <input type="text" id="user-search" placeholder="Search registered users..." class="w-full p-3 pl-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                            <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        
                        <!-- User List Container -->
                        <div class="divide-y divide-gray-100" id="user-list-container">
                            <div class="text-center text-gray-500 py-10">Loading users...</div>
                            <!-- Dynamic user list items here -->
                            <!-- Example pre-populated as per image: -->
                             <div class="py-5 flex items-center gap-4 hover:bg-gray-50/50 cursor-pointer transition-all">
                                <img src="https://i.pravatar.cc/150?u=1" alt="User" class="w-14 h-14 rounded-full object-cover border-4 border-gray-100">
                                <div class="flex-1">
                                    <p class="font-bold text-gray-800 text-sm">Juan Dela Cruz</p>
                                    <p class="text-xs text-gray-500 mb-2">123 Main Street, Manila</p>
                                    <div class="grid grid-cols-3 gap-3 text-xs">
                                        <div><span class="block text-gray-400">Devices</span><p class="font-semibold text-gray-600">4</p></div>
                                        <div><span class="block text-gray-400">Status</span><p class="font-semibold text-green-600">ACTIVE</p></div>
                                        <div><span class="block text-gray-400">Phone</span><p class="font-semibold text-gray-600">6789</p></div>
                                    </div>
                                </div>
                                <div class="text-gray-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
                            </div>
                            <div class="py-5 flex items-center gap-4 hover:bg-gray-50/50 cursor-pointer transition-all">
                                <img src="https://i.pravatar.cc/150?u=2" alt="User" class="w-14 h-14 rounded-full object-cover border-4 border-gray-100">
                                <div class="flex-1">
                                    <p class="font-bold text-gray-800 text-sm">Maria Santos</p>
                                    <p class="text-xs text-gray-500 mb-2">456 Rizal Avenue, Quezon City</p>
                                    <div class="grid grid-cols-3 gap-3 text-xs">
                                        <div><span class="block text-gray-400">Devices</span><p class="font-semibold text-gray-600">4</p></div>
                                        <div><span class="block text-gray-400">Status</span><p class="font-semibold text-red-600">ACTIVE</p></div>
                                        <div><span class="block text-gray-400">Phone</span><p class="font-semibold text-gray-600">5432</p></div>
                                    </div>
                                </div>
                                <div class="text-gray-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></div>
                            </div>
                        </div>
                    </div>
                </section>
                <!-- === END HOME CONTENT === -->


                <!-- === MAPS CONTENT (Map View - Matches BFP Maps Image) === -->
                <section id="maps-content" class="page-content hidden">
                    
                    <div class="space-y-4 mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Coverage Map</h2>
                        
                        <!-- Coordinate Search Bar -->
                        <div class="flex gap-2">
                            <input type="text" id="map-coord-input" placeholder="Search Lat, Lon (e.g., 14.11, 122.96)" class="flex-1 p-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                            <button onclick="searchCoordinates()" class="bg-blue-600 text-white p-3 rounded-lg hover:bg-blue-700 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </button>
                        </div>

                        <!-- Map Container -->
                        <div id="map-container" class="rounded-xl">
                            <div id="main-leaflet-map"></div>
                        </div>
                    </div>
                    
                    <!-- Map Legend - Matches BFP Maps Image -->
                    <div class="bg-white rounded-2xl p-5 shadow-sm mb-4">
                        <h3 class="font-bold text-gray-800 mb-3">Map Legend:</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-green-500 rounded-full"></div>
                                <span class="text-gray-700">Active Properties</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-red-500 rounded-full animate-pulse"></div>
                                <span class="text-gray-700">Active Alerts</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-gray-400 rounded-full"></div>
                                <span class="text-gray-700">Inactive</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-[#b91919] rounded-full"></div>
                                <span class="text-gray-700">BFP Stations</span>
                            </div>
                        </div>
                    </div>

                    <!-- Map Metrics Row - Matches BFP Maps Image -->
                    <div class="grid grid-cols-3 gap-3 mb-6" id="map-metrics-row">
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-gray-800" data-metric="total-responses">5</p>
                            <p class="text-xs text-gray-600">Total Responses</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-green-600" data-metric="avg-response-time">7m</p>
                            <p class="text-xs text-gray-600">Avg Responses</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-blue-600" data-metric="zero-casualty">5</p>
                            <p class="text-xs text-gray-600">Zero Casualty</p>
                        </div>
                    </div>
                </section>
                <!-- === END MAPS CONTENT === -->


                <!-- Alerts Content (Matches BFP Alert Image) -->
                <section id="alerts-content" class="page-content hidden">
                    <div class="mb-5">
                        <h2 class="text-xl font-bold text-gray-800">Active Emergency Alerts</h2>
                        <p class="text-sm text-gray-500" id="alerts-last-updated">Last updated: 2 minutes ago</p>
                    </div>
                    <div id="active-alerts-container">
                        <!-- Example pre-populated as per image: -->
                        <div class="bg-white rounded-2xl shadow-lg border-l-4 border-red-500 p-6 mb-4">
                            <div class="flex justify-between items-start mb-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 flex items-center justify-center bg-red-100 rounded-lg text-red-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-red-600">FIRE ALERT</h3>
                                        <p class="text-xs text-gray-500">2:34 PM - Nov 15</p>
                                    </div>
                                </div>
                                <span class="text-xs font-bold bg-red-600 text-white py-1 px-3 rounded-full">ACTIVE</span>
                            </div>
                            <div class="space-y-3 text-sm text-gray-700 pl-14 mb-6">
                                <p><strong>Resident:</strong> Sarah Johnson</p>
                                <p><strong>Address:</strong> 1245 Oak St, Apt 3B</p>
                                <p><strong>Device:</strong> Kitchen Smoke Detector</p>
                                <p><strong>Coordinates:</strong> 40.7128, -74.0060</p>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <button class="w-full flex items-center justify-center gap-2 bg-gradient-to-br from-red-500 to-red-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all" onclick="handleCallResident('N/A')">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    Call
                                </button>
                                <button class="w-full flex items-center justify-center gap-2 bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all" onclick="handleNavigate('40.7128, -74.0060')">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Navigate
                                </button>
                                <button class="w-full flex items-center justify-center gap-2 bg-green-500 text-white font-semibold py-3 rounded-lg shadow-md hover:bg-green-600 transition-all" onclick="resolveAlert(999)">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Resolve
                                </button>
                            </div>
                        </div>
                        <div class="text-center text-gray-500 py-10 hidden" id="no-alerts-message">
                            <svg class="w-16 h-16 mx-auto mb-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            <p class="font-bold">All Clear</p>
                            <p class="text-sm">No active alerts at this time.</p>
                        </div>
                    </div>
                </section>

                <!-- Responses Content (Matches BFP Responses Image) -->
                <section id="responses-content" class="page-content hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Response History</h2>
                        <button class="text-blue-600 text-sm font-medium hover:text-blue-700 transition-colors">Export Report</button>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-6" id="response-metrics">
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-gray-800" data-metric="total-responses">5</p>
                            <p class="text-xs text-gray-600">Total Responses</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-green-600" data-metric="avg-response-time">7m</p>
                            <p class="text-xs text-gray-600">Avg Response</p>
                        </div>
                        <div class="bg-white rounded-xl p-4 shadow-sm text-center">
                            <p class="text-2xl font-bold text-blue-600" data-metric="zero-casualty">5</p>
                            <p class="text-xs text-gray-600">Zero Casualty</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4" id="response-history-container">
                        <div class="text-center text-gray-500 py-10 hidden">No history available.</div>
                        <!-- Example pre-populated as per image: -->
                        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4 border-red-500 hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800 text-sm">Juan Dela Cruz — <span class="font-normal text-xs text-gray-600">Fire</span></h3>
                                        <p class="text-xs text-gray-500">123 Main Street, Manila</p>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800 font-semibold">HIGH</span>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3 text-xs">
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Date</p>
                                    <p class="font-semibold text-gray-800">2025-10-18</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Response</p>
                                    <p class="font-semibold text-green-600">8 mins</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Status</p>
                                    <p class="font-semibold text-blue-600">RESOLVED</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4 border-orange-500 hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-800 text-sm">Maria Santos — <span class="font-normal text-xs text-gray-600">Gas Leak</span></h3>
                                        <p class="text-xs text-gray-500">456 Rizal Avenue, Quezon City</p>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 font-semibold">MEDIUM</span>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3 text-xs">
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Date</p>
                                    <p class="font-semibold text-gray-800">2025-10-15</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Response</p>
                                    <p class="font-semibold text-green-600">6 mins</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-2 text-center">
                                    <p class="text-gray-600">Status</p>
                                    <p class="font-semibold text-blue-600">RESOLVED</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Settings Content (Restored) -->
                <section id="settings-content" class="page-content hidden">
                    <div class="space-y-5">
                        <div class="bg-white p-6 rounded-2xl shadow-sm">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-gray-800">Officer Profile</h3>
                                <svg class="w-5 h-5 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                    <p class="text-sm text-gray-600" id="officer-station">Manila Central Station</p>
                                    <p class="text-xs text-gray-400" id="officer-badge">Badge #BFP-2024-0123</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <input type="text" id="officer-phone" value="+63 912 345 6789" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white" placeholder="Contact Number">
                                <input type="email" id="officer-email" value="officer.santos@bfp.gov.ph" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white" placeholder="Email Address">
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-2xl shadow-sm">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Notification Settings</h3>
                            <div class="divide-y divide-gray-100">
                                <!-- Notification toggles here (No change needed) -->
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
                                <!-- Station Info will be dynamically inserted here by JS -->
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Station Name:</span>
                                    <span class="font-semibold text-gray-800" data-station="name">Manila Central</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Station Code:</span>
                                    <span class="font-semibold text-gray-800" data-station="code">MNL-001</span>
                                </div>
                                <div class="flex justify-between py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Coordinates:</span>
                                    <span class="font-semibold text-gray-800" data-station="coverage">N/A</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Available Units:</span>
                                    <span class="font-semibold text-gray-800" data-station="units">4 Fire Trucks</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-3 pt-2">
                            <button id="update-profile-btn" class="w-full bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">
                                Update Profile
                            </button>
                            <button id="sign-out-btn" class="w-full bg-red-100 text-red-600 font-semibold py-3 rounded-lg hover:bg-red-200 transition-all">
                                Sign Out</button>
                        </div>
                    </div>
                </section>
                
            </main>

            <!-- Navigation Bar (Corrected to 5 links) -->
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

    <!-- User Detail/Edit Modal (CRUD - U/D/R) -->
    <div id="user-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden items-center justify-center p-4 z-50">
        <div class="bg-white p-6 rounded-2xl w-full max-w-sm shadow-2xl">
            <h3 class="text-xl font-bold text-gray-800 mb-4" id="modal-title">User Details</h3>
            <form id="user-form">
                <input type="hidden" id="modal-user-id">
                <div class="space-y-3 mb-6">
                    <input type="text" id="modal-first-name" placeholder="First Name" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="text" id="modal-last-name" placeholder="Last Name" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="text" id="modal-address" placeholder="Address" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="text" id="modal-phone" placeholder="Phone Number" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <input type="email" id="modal-email" placeholder="Email Address" required class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                    <select id="modal-role" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                        <option value="RESIDENT">Resident</option>
                        <option value="BFP_OFFICER">BFP Officer</option>
                        <option value="BFP_ASSIGNED_AT_DESK">Assigned At Desk</option>
                        <option value="BFP_ADMIN">BFP Admin</option>
                    </select>
                    <select id="modal-status" class="w-full p-3 bg-gray-100 border-2 border-gray-200 rounded-lg text-sm transition-all focus:outline-none focus:border-blue-500 focus:bg-white">
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="INACTIVE">INACTIVE</option>
                    </select>
                </div>
                <div class="flex justify-between gap-3">
                    <button type="button" id="delete-user-btn" class="flex-1 bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-600 transition-all hidden">Delete</button>
                    <button type="submit" id="save-user-btn" class="flex-1 bg-blue-500 text-white font-semibold py-3 rounded-lg hover:bg-blue-600 transition-all">Save Changes</button>
                    <button type="button" id="close-modal-btn" class="flex-1 bg-gray-300 text-gray-800 font-semibold py-3 rounded-lg hover:bg-gray-400 transition-all">Cancel</button>
                </div>
            </form>
        </div>
    </div>


    <script>
        // --- UTILITY FUNCTION: HTML Sanitization (Fixes SSE error) ---
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return unsafe;
            return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }


        // --- 1. GLOBAL DATA VARIABLES & CONFIG ---
        const API_URL = 'api_bfp.php'; 
        let users = [];
        let activeIncidents = [];
        let resolvedIncidents = [];
        let bfpStations = [];
        let metrics = {
             'total_users': 5,
             'active_devices': 20,
             'active_alerts': 1,
             'monthly_responses': 5,
             'total_responses': 5,
             'avg_response_time': 7,
             'zero_casualty': 5
        }; // Seeded with image data
        let lastIncidentCount = -1; // -1 indicates initial load state
        let pollingIntervalId = null; 
        let mainMapInstance = null; // Reference to the Leaflet map instance
        
        // --- 2. AUTHENTICATION & SESSION MANAGEMENT ---

        async function checkSession() {
            try {
                const response = await fetch(`${API_URL}?endpoint=session`);
                const data = await response.json();
                
                if (data.status === 'success' && data.logged_in) {
                    // Pre-fetch all necessary data immediately upon login success
                    await fetchAllData(); 
                    showDashboard(data.user);
                } else {
                    showLogin();
                }
            } catch (error) {
                console.error("Session check failed:", error);
                showLogin();
            }
        }

        async function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            const loginBtn = document.getElementById('login-btn');
            const errorElement = document.getElementById('login-error');
            
            // Show loading state immediately with progress text
            loginBtn.innerHTML = '<div class="flex items-center justify-center gap-2"><svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span id="login-progress">Connecting...</span></div>';
            loginBtn.disabled = true;
            errorElement.classList.add('hidden');

            // Simulate progress updates
            const progressTexts = ['Connecting...', 'Authenticating...', 'Verifying...'];
            let progressIndex = 0;
            const progressInterval = setInterval(() => {
                progressIndex = (progressIndex + 1) % progressTexts.length;
                const progressEl = document.getElementById('login-progress');
                if (progressEl) progressEl.textContent = progressTexts[progressIndex];
            }, 800);

            try {
                // Add timeout to prevent hanging
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout

                // SIMULATE API CALL
                const response = {
                    ok: true,
                    json: async () => ({
                        status: 'success',
                        user: { name: 'Fire Officer III John Santos' }
                    })
                };
                
                // const response = await fetch(`${API_URL}?endpoint=login`, {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/json' },
                //     body: JSON.stringify({ email: email, password: password }),
                //     signal: controller.signal
                // });
                
                clearTimeout(timeoutId);
                clearInterval(progressInterval);
                
                const data = await response.json();

                if (data.status === 'success') {
                    // Show success message
                    loginBtn.innerHTML = '<div class="flex items-center justify-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg><span>Login Successful!</span></div>';
                    loginBtn.classList.remove('from-[#d93b3b]', 'to-[#b91919]');
                    loginBtn.classList.add('bg-green-500');
                    
                    // Brief delay to show success, then load dashboard
                    setTimeout(() => {
                        showDashboard(data.user);
                        // Reset button state for future use
                        loginBtn.innerHTML = 'Login to Officer Hub';
                        loginBtn.classList.remove('bg-green-500');
                        loginBtn.classList.add('bg-gradient-to-br', 'from-[#d93b3b]', 'to-[#b91919]');
                    }, 600);
                } else {
                    clearInterval(progressInterval);
                    errorElement.textContent = data.message || "Login failed. Please check your credentials.";
                    errorElement.classList.remove('hidden');
                    resetLoginButton(loginBtn);
                }
            } catch (error) {
                clearInterval(progressInterval);
                console.error("Login API error:", error);
                
                if (error.name === 'AbortError') {
                    errorElement.textContent = "Login timeout. Please check your connection and try again.";
                } else {
                    errorElement.textContent = "Connection error. Please check your internet.";
                }
                
                errorElement.classList.remove('hidden');
                resetLoginButton(loginBtn);
            }
        }

        function resetLoginButton(loginBtn) {
            loginBtn.innerHTML = 'Login to Officer Hub';
            loginBtn.disabled = false;
            loginBtn.classList.remove('bg-green-500');
            loginBtn.classList.add('bg-gradient-to-br', 'from-[#d93b3b]', 'to-[#b91919]');
        }
        async function handleLogout() {
            if (!confirm('Are you sure you want to sign out?')) return;
            
            clearInterval(pollingIntervalId); 
            pollingIntervalId = null;
            
            // Close SSE connection if it exists
            if (window.dashboardSSE && typeof window.dashboardSSE.close === 'function') {
                window.dashboardSSE.close();
            }

            try {
                // await fetch(`${API_URL}?endpoint=logout`); // SIMULATED API CALL
            } catch (error) {
                console.error("Logout API error:", error);
            } finally {
                showLogin();
            }
        }
        
        function showDashboard(user) {
            document.getElementById('login-content').classList.add('hidden');
            document.getElementById('authenticated-content').classList.remove('hidden');
            
            // Set Officer Name in Settings header (use DB name)
            const officerNameEl = document.getElementById('officer-name');
            if (officerNameEl) officerNameEl.textContent = user.name || officerNameEl.textContent;

            // Initialize map immediately
            initializeMainMap(); 
            
            // Ensure first tab is active
            const initialTab = 'home-content';
            switchTab(initialTab);
            
            // Try to populate station name immediately if available
            updateStationDisplays();
            
            // Attempt to subscribe for push notifications
            // subscribeForPush().then(sub => {
            //     if (sub) console.log('Subscribed for push notifications');
            // });

            // Realtime updates via Server-Sent Events (SSE) with fallback to polling (modified to use SSE)
            try {
                if (window.dashboardSSE && typeof window.dashboardSSE.close === 'function') {
                    window.dashboardSSE.close();
                }
                if (typeof EventSource !== 'undefined') {
                    // This is a placeholder as the backend stream endpoint is not provided
                    console.log("Simulating SSE updates...");
                    // window.dashboardSSE = new EventSource(`${API_URL}?endpoint=dashboard_stream`);
                    // window.dashboardSSE.addEventListener('update', (ev) => { /* ... SSE logic ... */ });
                    // window.dashboardSSE.onopen = () => console.log('SSE connected');
                    // window.dashboardSSE.onerror = (e) => { /* ... SSE error logic ... */ };
                    
                    // Fallback to polling to ensure data updates
                    if (!pollingIntervalId) {
                        pollingIntervalId = setInterval(fetchAllData, 15000); 
                    }
                }
            } catch (e) {
                console.warn('Realtime SSE initialization failed, falling back to polling...', e);
                if (!pollingIntervalId) {
                    pollingIntervalId = setInterval(fetchAllData, 15000); 
                }
            }
        }

        function showLogin() {
            document.getElementById('authenticated-content').classList.add('hidden');
            document.getElementById('login-content').classList.remove('hidden');
            
            // Reset state
            lastIncidentCount = -1;
            clearInterval(pollingIntervalId);
        }


        // --- 3. AJAX/API FUNCTIONS (Data Fetch & CRUD) ---

        async function fetchAllData() {
             // SIMULATE API CALL WITH DUMMY DATA MATCHING THE IMAGE
             const dummyData = {
                status: 'success',
                users: [
                    { user_ID: 1, first_name: 'Juan', last_name: 'Dela Cruz', address: '123 Main Street, Manila', phone_number: '639171236789', status: 'ACTIVE', device_count: 4, email: 'juan@test.com', role: 'RESIDENT' },
                    { user_ID: 2, first_name: 'Maria', last_name: 'Santos', address: '456 Rizal Avenue, Quezon City', phone_number: '639189875432', status: 'ACTIVE', device_count: 4, email: 'maria@test.com', role: 'RESIDENT' },
                    { user_ID: 3, first_name: 'Pedro', last_name: 'Reyes', address: '789 Aurora Blvd, Pasig City', phone_number: '639191112222', status: 'INACTIVE', device_count: 2, email: 'pedro@test.com', role: 'RESIDENT' },
                ],
                activeIncidents: [
                    { incident_ID: 999, first_name: 'Sarah', last_name: 'Johnson', address: '1245 Oak St, Apt 3B', device: 'Kitchen Smoke Detector', coordinates: '40.7128, -74.0060', phone_number: '555-1234', incident_level: 'HIGH', status: 'ACTIVE', start_timestamp: new Date().toISOString() },
                ],
                resolvedIncidents: [
                    { incident_ID: 101, first_name: 'Juan', last_name: 'Dela Cruz', address: '123 Main Street, Manila', incident_level: 'HIGH', date: '2025-10-18', response_time: 8, incident_type: 'Fire' },
                    { incident_ID: 102, first_name: 'Maria', last_name: 'Santos', address: '456 Rizal Avenue, Quezon City', incident_level: 'MEDIUM', date: '2025-10-15', response_time: 6, incident_type: 'Gas Leak' },
                    { incident_ID: 103, first_name: 'Pedro', last_name: 'Reyes', address: '789 Aurora Blvd, Pasig City', incident_level: 'HIGH', date: '2025-10-10', response_time: 5, incident_type: 'Fire' },
                ],
                stations: [
                    { station_ID: 1, station_name: 'BFP Daet Station', latitude: 14.1130, longitude: 122.9555, contact_number: '054-111-2222' }
                ],
                metrics: {
                    'total_users': 5,
                    'active_devices': 20,
                    'active_alerts': 1,
                    'monthly_responses': 5,
                    'total_responses': 5,
                    'avg_response_time': 7,
                    'zero_casualty': 5
                },
                activeDevices: [ // Devices with coordinates for map plotting
                    { sensor_ID: 'DVC-001', FK_user_ID: 1, latitude: 14.1150, longitude: 122.9560, sensor_type: 'Smoke', location_name: 'Living Room' },
                    { sensor_ID: 'DVC-002', FK_user_ID: 2, latitude: 14.1100, longitude: 122.9540, sensor_type: 'Gas', location_name: 'Kitchen' },
                    { sensor_ID: 'DVC-003', FK_user_ID: 3, latitude: 14.1110, longitude: 122.9580, sensor_type: 'Heat', location_name: 'Garage' },
                ]
            };

            const data = dummyData;

            if (data.status === 'success') {
                if (lastIncidentCount === -1) {
                        lastIncidentCount = (data.activeIncidents || []).length;
                }

                // Update global data
                users = data.users || [];
                activeIncidents = data.activeIncidents || [];
                resolvedIncidents = data.resolvedIncidents || [];
                bfpStations = data.stations || [];
                metrics = data.metrics || {};
                window.activeDevices = data.activeDevices || [];
                
                renderAll(); 
                updateStationDisplays();
            } else {
                console.error("API Error:", data.message);
            }
        }

        // --- 4. MAP AND MARKER LOGIC ---
        
        let activeMarkers = [];

        function initializeMainMap() {
            const mapId = 'main-leaflet-map';
            if (mainMapInstance) {
                mainMapInstance.invalidateSize();
                return;
            }
            
            // Initial center (Daet area)
            const initialCoords = [14.1130, 122.9555]; 
            const initialZoom = 14;

            mainMapInstance = L.map(mapId).setView(initialCoords, initialZoom);
            
            // Use standard OSM tiles for better landmark visibility
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                attribution: '&copy; OpenStreetMap contributors', 
                maxZoom: 19 
            }).addTo(mainMapInstance);
        }

        function updateMapMarkers() {
            if (!mainMapInstance) return;

            // Clear existing markers
            activeMarkers.forEach(marker => mainMapInstance.removeLayer(marker));
            activeMarkers = [];
            
            // --- 1. Define Icons ---
            
            // Icon for Active Alerts (Fire)
            const fireIcon = L.divIcon({ 
                className: 'custom-fire-icon', 
                html: '<div style="font-size:32px; color: #ff0000; animation: pulse 1.5s infinite;">🚨</div>', // Use alert emoji
                iconSize: [30, 30], 
                iconAnchor: [15, 30] 
            });

            // Icon for BFP Stations (Red Truck)
            const stationIcon = L.divIcon({
                className: 'custom-bfp-icon',
                html: `<div class="bg-[#b91919] text-white p-1 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="width: 28px; height: 28px; font-size: 14px;">🚒</div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 28]
            });
            
            // Icon for Active Devices (Blue Circle - IoT Properties)
            const deviceIcon = L.divIcon({ 
                className: 'custom-device-icon', 
                html: '<div style="width:12px;height:12px;border-radius:50%;background:#3b82f6;border:3px solid white;"></div>', 
                iconSize: [24, 24], 
                iconAnchor: [12, 12] 
            });

            // --- 2. Plot BFP Stations (Fixed) ---
            bfpStations.forEach(station => {
                const lat = parseFloat(station.latitude);
                const lon = parseFloat(station.longitude);
                if (!isNaN(lat) && !isNaN(lon)) {
                    const marker = L.marker([lat, lon], { icon: stationIcon, draggable: false }).addTo(mainMapInstance);
                    marker.bindPopup(`<b>BFP Station:</b> ${escapeHtml(station.station_name)}`).openPopup();
                    activeMarkers.push(marker);
                }
            });

            // --- 3. Plot Active Devices (Properties) ---
            const boundsArray = [];
            
            (window.activeDevices || []).forEach(device => {
                const isAlert = activeIncidents.some(inc => inc.FK_sensor_ID === device.sensor_ID);
                const lat = parseFloat(device.latitude);
                const lon = parseFloat(device.longitude);
                const owner = users.find(u => u.user_ID === device.FK_user_ID); 
                const title = device.location_name || device.address;

                if (!isNaN(lat) && !isNaN(lon)) {
                    let marker;
                    let popupContent = `
                        <b>Property:</b> ${escapeHtml(title)}<br>
                        <b>Owner:</b> ${escapeHtml(owner ? owner.first_name + ' ' + owner.last_name : 'N/A')}<br>
                        <b>Sensor:</b> ${escapeHtml(device.sensor_type)} (${device.sensor_ID})<br>
                        <b>Status:</b> <span style="color:${isAlert ? 'red' : 'green'};">${isAlert ? 'ALERT' : 'Active'}</span>
                    `;
                    
                    if (isAlert) {
                         marker = L.marker([lat, lon], { icon: fireIcon, draggable: false }).addTo(mainMapInstance);
                    } else {
                         marker = L.marker([lat, lon], { icon: deviceIcon, draggable: false }).addTo(mainMapInstance);
                    }
                    
                    marker.bindPopup(popupContent);
                    activeMarkers.push(marker);
                    boundsArray.push([lat, lon]);
                }
            });
            
            // Fit map to show all relevant markers if any exist
            if (boundsArray.length > 0) {
                const bounds = L.latLngBounds(boundsArray);
                mainMapInstance.fitBounds(bounds, { padding: [50, 50] });
            }
        }
        
        function searchCoordinates() {
            const input = document.getElementById('map-coord-input').value.trim();
            const coordsMatch = input.match(/^(\-?\d+(\.\d+)?)\s*[,\s]\s*(\-?\d+(\.\d+)?)$/);
            
            if (coordsMatch) {
                const lat = parseFloat(coordsMatch[1]);
                const lon = parseFloat(coordsMatch[3]);
                
                if (isNaN(lat) || isNaN(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) {
                    alert('Invalid coordinates. Please use format: Lat, Lon (e.g., 14.1105, 122.9590)');
                    return;
                }

                if (mainMapInstance) {
                    mainMapInstance.setView([lat, lon], 17);
                    
                    const searchIcon = L.divIcon({
                        className: 'custom-search-icon',
                        html: `<div style="font-size:24px; color: gold; text-shadow: 0 0 3px black;">📍</div>`,
                        iconSize: [30, 30],
                        iconAnchor: [15, 30]
                    });
                    
                    if (window.searchMarker) mainMapInstance.removeLayer(window.searchMarker);
                    
                    window.searchMarker = L.marker([lat, lon], { icon: searchIcon }).addTo(mainMapInstance);
                    window.searchMarker.bindPopup(`Searched Location: ${lat.toFixed(4)}, ${lon.toFixed(4)}`).openPopup();
                }
            } else {
                 alert('Invalid coordinate format. Please use Lat, Lon (e.g., 14.1105, 122.9590)');
            }
        }


        // --- 5. CRUD/ACTION FUNCTIONS (Used by the dashboard UI) ---

        async function resolveAlert(incidentId) {
            if (!confirm(`Are you sure you want to resolve Incident ID ${incidentId}?`)) return;

            try {
                // SIMULATE API CALL
                // const response = await fetch(`${API_URL}?endpoint=resolve_alert`, { method: 'PUT', body: JSON.stringify({ incident_ID: incidentId }) });
                // const result = await response.json();
                const result = { status: 'success', message: `Incident ${incidentId} resolved.` };

                if (result.status === 'success') {
                    alert(result.message);
                    fetchAllData(); 
                } else {
                    alert("Error resolving alert: " + result.message);
                }
            } catch (error) {
                console.error("API Call Failed:", error);
            }
        }

        async function saveUser(userData, isNew) {
             alert("User CRUD is simulated: Saving changes...");
             document.getElementById('user-modal').style.display = 'none';
             fetchAllData(); 
        }

        async function deleteUser(userId) {
            if (!confirm("Are you sure you want to permanently delete this user?")) return;
            alert(`User CRUD is simulated: Deleting user ${userId}...`);
            document.getElementById('user-modal').style.display = 'none';
            fetchAllData(); 
        }
        
        // Modal functions
        async function openUserModal(userId = null) {
            let user = { user_ID: null, first_name: '', last_name: '', address: '', phone_number: '', status: 'ACTIVE', email: '', role: 'RESIDENT' };
            const isNew = userId === null;
            if (!isNew) {
                const localUser = users.find(u => u.user_ID.toString() === userId.toString());
                if (localUser) { user = { ...user, ...localUser }; }
            }
            
            document.getElementById('modal-title').textContent = isNew ? 'Add New User' : 'Edit User';
            document.getElementById('modal-user-id').value = user.user_ID || '';
            document.getElementById('modal-first-name').value = user.first_name;
            document.getElementById('modal-last-name').value = user.last_name;
            document.getElementById('modal-address').value = user.address;
            document.getElementById('modal-phone').value = user.phone_number;
            document.getElementById('modal-status').value = user.status;
            document.getElementById('modal-email').value = user.email || '';
            
            const rawRole = (user.role || user.user_role || 'RESIDENT').toString();
            const normalized = rawRole.toUpperCase().replace(/\s+/g, '_');
            const roleEl = document.getElementById('modal-role');
            if (roleEl) {
                const allowed = Array.from(roleEl.options).map(o => o.value);
                roleEl.value = allowed.includes(normalized) ? normalized : 'RESIDENT';
            }

            const deleteBtn = document.getElementById('delete-user-btn');
            if (!isNew) {
                deleteBtn.classList.remove('hidden');
                deleteBtn.onclick = () => deleteUser(user.user_ID); 
            } else {
                 deleteBtn.classList.add('hidden');
            }
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
                address: document.getElementById('modal-address').value,
                phone_number: document.getElementById('modal-phone').value,
                email: document.getElementById('modal-email').value,
                role: document.getElementById('modal-role').value,
                status: document.getElementById('modal-status').value,
            };
            saveUser(userData, isNew);
        });

        // Other action handlers
        function handleCallResident(phoneNumber) { alert(`Calling resident at ${phoneNumber}... (Simulated)`); }
        function handleNavigate(coordinates) {
             const cleanCoords = coordinates.split(',').map(c => c.trim().replace(/°\s*[NSEW]/, '')).join(',');
             window.open(`https://maps.google.com/?q=${cleanCoords}`, '_blank');
        }


        // --- 6. RENDERING FUNCTIONS (To update the UI with data) ---

        function renderMetrics() {
            // These target the BFP Home screen metrics (4 boxes)
            document.querySelector('#home-content [data-metric="total-users"]').textContent = metrics['total_users'] || 0;
            document.querySelector('#home-content [data-metric="active-devices"]').textContent = metrics['active_devices'] || 0;
            document.querySelector('#home-content [data-metric="active-alerts"]').textContent = metrics['active_alerts'] || 0;
            document.querySelector('#home-content [data-metric="monthly-responses"]').textContent = metrics['monthly_responses'] || 0;

            const alertsBadge = document.getElementById('alerts-badge');
            if ((metrics['active_alerts'] || 0) > 0) {
                alertsBadge.classList.remove('hidden');
            } else {
                alertsBadge.classList.add('hidden');
            }
        }
        
        function renderUserList(filteredUsers = users) {
            const container = document.getElementById('user-list-container');
            if (!container) return; 
            container.innerHTML = ''; 

            if (filteredUsers.length === 0) {
                 container.innerHTML = '<div class="text-center text-gray-500 py-10">No users found.</div>';
                 return;
            }

            filteredUsers.forEach((user) => {
                const userElement = document.createElement('div');
                userElement.className = `py-5 flex items-center gap-4 hover:bg-gray-50/50 cursor-pointer transition-all`; 
                userElement.setAttribute('data-user-id', user.user_ID);
                userElement.addEventListener('click', () => openUserModal(user.user_ID)); 

                const statusColor = user.status === 'ACTIVE' ? 'text-green-600' : 'text-red-600';
                const phoneLastFour = user.phone_number ? user.phone_number.slice(-4) : 'N/A';
                const displayName = `${user.first_name} ${user.last_name}`;
                
                userElement.innerHTML = `
                    <img src="https://i.pravatar.cc/150?u=${user.user_ID}" alt="User" class="w-14 h-14 rounded-full object-cover border-4 border-gray-100">
                    <div class="flex-1">
                        <p class="font-bold text-gray-800 text-sm">${escapeHtml(displayName)}</p>
                        <p class="text-xs text-gray-500 mb-2">${escapeHtml(user.address)}</p>
                        <div class="grid grid-cols-3 gap-3 text-xs">
                            <div><span class="block text-gray-400">Devices</span><p class="font-semibold text-gray-600">${user.device_count || 0}</p></div>
                            <div><span class="block text-gray-400">Status</span><p class="font-semibold ${statusColor}">${user.status}</p></div>
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

            container.innerHTML = ''; 

            if (activeIncidents.length === 0) {
                container.appendChild(noAlertsMessage);
                noAlertsMessage.classList.remove('hidden');
                document.getElementById('alerts-last-updated').textContent = 'Last updated: just now (All Clear)';
                return;
            } else {
                noAlertsMessage.classList.add('hidden');
            }

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
                                <h3 class="font-bold text-red-600">${incident.incident_level} ALERT</h3>
                                <p class="text-xs text-gray-500">${timeAgo}</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold bg-red-600 text-white py-1 px-3 rounded-full">${incident.status}</span>
                    </div>
                    <div class="space-y-3 text-sm text-gray-700 pl-14 mb-6">
                        <p><strong>Resident:</strong> ${escapeHtml(incident.first_name)} ${escapeHtml(incident.last_name)}</p>
                        <p><strong>Address:</strong> ${escapeHtml(incident.address)}</p>
                        <p><strong>Device:</strong> ${escapeHtml(incident.device)}</p>
                        <p><strong>Coordinates:</strong> ${escapeHtml(incident.coordinates)}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <button class="w-full flex items-center justify-center gap-2 bg-gradient-to-br from-red-500 to-red-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all" onclick="handleCallResident('${escapeHtml(incident.phone_number)}')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            Call
                        </button>
                        <button class="w-full flex items-center justify-center gap-2 bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all" onclick="handleNavigate('${escapeHtml(incident.coordinates)}')">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
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
            document.getElementById('alerts-last-updated').textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
        }

        function renderResponseHistory() {
            const container = document.getElementById('response-history-container');
            if (!container) return;

            container.innerHTML = ''; 

            // Update Response Metrics row
            document.querySelector('#response-metrics [data-metric="total-responses"]').textContent = metrics['total_responses'] || 0;
            document.querySelector('#response-metrics [data-metric="avg-response-time"]').textContent = `${metrics['avg_response_time'] || 0}m`;
            document.querySelector('#response-metrics [data-metric="zero-casualty"]').textContent = metrics['zero_casualty'] || 0;

            if (resolvedIncidents.length === 0) {
                 container.innerHTML = '<div class="text-center text-gray-500 py-10">No history available.</div>';
                 return;
            }
            // Build a counts-by-type summary to show a small chart and badges
            const counts = {};
            function mapIncidentType(device, level) {
                const d = (device || '').toString().toLowerCase();
                if (d.includes('smoke') || d.includes('heat') || d.includes('flame') || d.includes('fire')) return 'Fire';
                if (d.includes('gas') || d.includes('co') || d.includes('carbon')) return 'Gas Leak';
                if (d.includes('panic') || d.includes('panic_button') || d.includes('alarm')) return 'Panic/Medical';
                if (d.includes('water') || d.includes('flood') || d.includes('leak')) return 'Flood/Leak';
                if (d.includes('door') || d.includes('motion') || d.includes('break')) return 'Security';
                if (level && level.toString().toUpperCase() === 'HIGH') return 'Critical Incident';
                return 'Other';
            }

            resolvedIncidents.forEach(incident => {
                const type = incident.incident_type || mapIncidentType(incident.device || incident.sensor_type, incident.incident_level);
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
                summaryHtml += `
                    <div class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                        <div class="w-2 h-8 bg-blue-400 rounded" style="width:${Math.max(8, (counts[key] / resolvedIncidents.length) * 100)}%"></div>
                        <div class="flex-1">
                            <div class="text-sm font-medium">${key}</div>
                            <div class="text-xs text-gray-500">${counts[key]} case(s)</div>
                        </div>
                    </div>
                `;
            });
            summaryHtml += '</div>';
            summaryWrap.innerHTML = summaryHtml;
            container.appendChild(summaryWrap);

            resolvedIncidents.forEach(incident => {
                const levelColor = incident.incident_level === 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800';
                const borderClass = incident.incident_level === 'HIGH' ? 'border-red-500' : 'border-orange-500';
                const type = mapIncidentType(incident.device || incident.sensor_type, incident.incident_level);

                const incidentElement = document.createElement('div');
                incidentElement.className = `bg-white rounded-2xl p-5 shadow-sm border-l-4 ${borderClass} hover:shadow-lg hover:-translate-y-0.5 transition-all cursor-pointer`;

                incidentElement.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-sm">${escapeHtml(incident.first_name)} ${escapeHtml(incident.last_name)} — <span class="font-normal text-xs text-gray-600">${type}</span></h3>
                                <p class="text-xs text-gray-500">${escapeHtml(incident.address)}</p>
                            </div>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full ${levelColor} font-semibold">${incident.incident_level}</span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3 text-xs">
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                            <p class="text-gray-600">Date</p>
                            <p class="font-semibold text-gray-800">${incident.date}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                            <p class="text-gray-600">Response</p>
                            <p class="font-semibold text-green-600">${incident.response_time} mins</p>
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
             // This targets the Map Metrics row (3 boxes)
            document.querySelector('#maps-content [data-metric="total-responses"]').textContent = metrics['total_responses'] || 0;
            document.querySelector('#maps-content [data-metric="avg-response-time"]').textContent = `${metrics['avg_response_time'] || 0}m`;
            document.querySelector('#maps-content [data-metric="zero-casualty"]').textContent = metrics['zero_casualty'] || 0;
            // Ensure map size is right on render
            if (mainMapInstance) mainMapInstance.invalidateSize(true);
        }

        function renderAll() {
            renderMetrics(); 
            renderUserList(); 
            renderActiveAlerts(); 
            renderResponseHistory(); 
            renderMapContent(); 
            updateMapMarkers(); 
        }

        function updateStationDisplays() {
            try {
                if (!Array.isArray(bfpStations) || bfpStations.length === 0) return;

                let station = bfpStations.find(s => /daet/i.test(s.station_name)) || bfpStations[0];
                if (!station) return;

                const headerEl = document.getElementById('header-station');
                if (headerEl) { headerEl.textContent = station.station_name.replace(/^BFP\s+/i, ''); }

                const officerStationEl = document.getElementById('officer-station');
                if (officerStationEl) { officerStationEl.textContent = station.station_name.replace(/^BFP\s+/i, ''); }

                const nameEls = document.querySelectorAll('[data-station="name"]');
                nameEls.forEach(el => el.textContent = station.station_name.replace(/^BFP\s+/i, ''));

                const codeEls = document.querySelectorAll('[data-station="code"]');
                const words = (station.station_name || '').split(/\s+/).filter(Boolean);
                const initials = words.map(w => w[0]).join('').substring(0, 3).toUpperCase();
                const stationId = station.station_ID || '';
                const computedCode = station.station_code || (stationId ? `BFP-${initials}${stationId}` : 'N/A');
                codeEls.forEach(el => el.textContent = computedCode);

                const coverageEls = document.querySelectorAll('[data-station="coverage"]');
                const lat = station.latitude || '';
                const lng = station.longitude || '';
                const coords = (lat && lng) ? `${lat}, ${lng}` : 'N/A';
                coverageEls.forEach(el => el.textContent = coords);

                const storageKey = 'availableUnits';
                const savedUnits = localStorage.getItem(storageKey);
                const unitsText = savedUnits || station.contact_number || 'N/A';
                const unitsEls = document.querySelectorAll('[data-station="units"]');
                unitsEls.forEach(el => el.textContent = unitsText);

                if (!savedUnits && station.contact_number) {
                    localStorage.setItem(storageKey, station.contact_number);
                }
            } catch (e) {
                console.error('updateStationDisplays error:', e);
            }
        }

        function loadNotificationSettings() {
            try {
                const keys = ['emergencyAlerts', 'smsNotifications', 'systemUpdates'];
                const mapping = { emergencyAlerts: 'emergency-toggle', smsNotifications: 'sms-toggle', systemUpdates: 'updates-toggle' };
                keys.forEach(k => {
                    const stored = localStorage.getItem(k);
                    const el = document.getElementById(mapping[k]);
                    if (el && stored !== null) { el.checked = stored === '1'; }
                });
            } catch (e) { console.error('loadNotificationSettings error:', e); }
        }

        function saveNotificationSetting(key, checked) {
            try {
                localStorage.setItem(key, checked ? '1' : '0');
            } catch (e) { console.error('saveNotificationSetting error:', e); }
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
            if (targetId === 'maps-content' && mainMapInstance) { setTimeout(() => mainMapInstance.invalidateSize(true), 100); }
        }

        function formatTimeAgo(timestamp) {
            const now = new Date();
            const date = new Date(timestamp);
            const seconds = Math.floor((now - date) / 1000);
            if (seconds < 60) return `${seconds}s ago`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            return date.toLocaleDateString();
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
            setInterval(updateTime, 1000);

            checkSession(); 

            document.getElementById('login-form').addEventListener('submit', handleLogin);
            document.getElementById('sign-out-btn').addEventListener('click', handleLogout);

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
                        user.address.toLowerCase().includes(searchTerm)
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

            const storedUnits = localStorage.getItem('availableUnits');
            if (storedUnits) {
                document.querySelectorAll('[data-station="units"]').forEach(el => el.textContent = storedUnits);
            }

        });
        
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => { console.log('Service Worker registered successfully:', registration); })
                    .catch(error => { console.log('Service Worker registration failed:', error); });
            });
        }
        
        // Web Push Subscription placeholders are left out for brevity but logic is in original code

    </script>

</body>
</html>