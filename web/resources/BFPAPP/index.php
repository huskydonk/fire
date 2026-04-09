<?php
// NOTE: This file must be named index.php
require_once 'db_config.php';

// --- Session Check Logic ---
// Check if the user is NOT logged in (no user_id in session)
if (!isset($_SESSION['user_id'])) {
    $is_logged_in = false;
    $current_user_role = 'resident'; // Default value
} else {
    // If logged in, skip the login screen
    $is_logged_in = true;
    $current_user_role = $_SESSION['user_role'] ?? 'resident'; // Store role in a variable for reference
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#C82828"/>
    <meta name="description" content="Early Alert: Integrated IoT Fire Safety Solution for the Bureau of Fire Protection.">
    <title>BFP Early Alert</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <!-- NEW: Leaflet Routing Machine CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />  
    <link rel="manifest" href="manifest.json"> 
    <script src="tailwind.js"></script>
    <script>
        // Custom Tailwind theme to match the design
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
                        'resolved-green-text': '#34A853',
                        'active-red-bg': '#FCE8E6',
                        'active-red-text': '#C5221F',
                    }
                }
            }
        }
    </script>
    <style>
        .leaflet-tile-pane { image-rendering: pixelated; }
        .h-screen-fix { height: 100vh; height: -webkit-fill-available; }

        /* Add to your styles */
.shadow-elegant {
    box-shadow: 0 2px 8px rgba(0,0,0,0.04), 0 8px 32px rgba(0,0,0,0.08);
}

.letter-spacing-tight {
    letter-spacing: 0.02em;
}

.bg-gradient-ultra-light {
    background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
}

/* Smooth transitions */
* {
    transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

/* Style to hide the default ugly routing machine inputs/controls */
.leaflet-routing-container {
    display: none !important;
}

/* Custom icon for the user's property/device location */
.custom-property-icon {
    /* Use a solid marker shape (like a shield or badge) */
    background-color: #003399; /* Dark blue color */
    border-radius: 50%; /* Circle shape */
    border: 3px solid white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
}
.custom-property-icon div {
    color: white;
    font-weight: bold;
    font-size: 14px; 
    line-height: 1;
}

/* OLD: Styles for the dark Wi-Fi UI simulation (now removed/obsolete) */
    </style>
</head>
<body class="bg-gray-800 flex items-center justify-center min-h-screen font-sans h-screen-fix">
    <div class="w-full max-w-sm h-screen-fix sm:h-[85vh] sm:rounded-lg shadow-2xl relative overflow-hidden">
        
<main id="login-screen" class="bg-white w-full h-full flex flex-col items-center justify-center p-8 absolute inset-0 z-40">
            <div class="text-center mb-8">
                <img src="logoBFP.png" alt="BFP Logo" class="w-20 h-20 mx-auto mb-4">
                <p class="text-gray-600 text-sm">Sign in as resident</p>
            </div>
            <div class="w-full">
                <form id="login-form" onsubmit="event.preventDefault();">
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username (Email)</label>
                        <input type="text" id="username" placeholder="Enter your username" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-bfp-red focus:border-transparent transition-all">
                    </div>
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" placeholder="Enter your password" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-bfp-red focus:border-transparent transition-all">
                        <!-- <a href="#" class="text-xs text-blue-600 hover:underline text-right block mt-2">Forgot Password?</a> -->
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-br from-bfp-red to-bfp-red-dark text-white font-bold py-3 px-4 rounded-xl hover:shadow-lg hover:-translate-y-0.5 transition-all">Login</button>
                </form>
                <div class="my-6 flex items-center" style="display: none;">
                    <div class="flex-grow border-t border-gray-300"></div>
                    <span class="flex-shrink mx-4 text-gray-500 text-sm">OR</span>
                    <div class="flex-grow border-t border-gray-300"></div>
                </div>
                <button id="google-login-btn" class="w-full bg-white text-gray-700 font-semibold py-3 px-4 border-2 border-gray-200 rounded-xl shadow-sm hover:bg-gray-50 hover:shadow-md transition-all flex items-center justify-center" style="display:none;">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039L38.485 11.54C34.783 7.844 29.697 5.5 24 5.5C11.696 5.5 1.5 15.696 1.5 28s10.196 22.5 22.5 22.5c11.133 0 20.375-8.23 21.611-18.847V20.083z"/>
                        <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12.5 24 12.5c3.059 0 5.842 1.154 7.961 3.039L38.485 11.54C34.783 7.844 29.697 5.5 24 5.5C16.318 5.5 9.506 9.834 6.306 14.691z"/>
                        <path fill="#4CAF50" d="M24 44.5c5.166 0 9.86-1.977 13.409-5.192l-6.19-4.819C29.211 35.091 26.715 36.5 24 36.5c-5.22 0-9.643-3.336-11.284-7.94l-6.522 5.025C9.506 39.166 16.318 44.5 24 44.5z"/>
                        <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.14-4.062 5.534l6.19 4.819C42.018 35.398 44.5 30.01 44.5 24c0-1.588-.158-3.14-.44-4.639z"/>
                    </svg>
                    Sign in with Google
                </button> 
            </div>
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
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="font-semibold" id="system-status-text">All Systems Normal</span>
                        </div>
                        <span class="bg-white/30 text-xs font-bold px-3 py-1 rounded-full" id="system-status-tag">SAFE</span>
                    </div>
                </header>

                <div class="flex-grow overflow-y-auto bg-gray-100 relative">
                    <main id="home-screen" class="app-screen p-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-stat-green text-white p-4 rounded-lg shadow-lg"><div class="flex justify-between items-center"><p class="text-sm font-semibold">Active Devices</p><svg class="w-6 h-6 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 2Z"/></svg></div><p class="text-4xl font-bold mt-2" id="active-devices-count">0</p></div>
                            <div class="bg-stat-red text-white p-4 rounded-lg shadow-lg"><div class="flex justify-between items-center"><p class="text-sm font-semibold">Active Alerts</p><svg class="w-6 h-6 opacity-80" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21H23L12 2L1 21ZM13 18H11V16H13V18ZM13 14H11V10H13V14Z"/></svg></div><p class="text-4xl font-bold mt-2" id="active-alerts-count">0</p></div>
                        </div>
                        <div class="bg-bfp-red text-white p-4 rounded-lg shadow-lg">
                            <h3 class="font-bold flex items-center mb-4 text-base sm:text-lg">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
        </svg>
        Emergency Contacts
    </h3><div class="space-y-3">
                              <div>
            <label class="text-sm font-semibold block mb-2 opacity-90">Select BFP Station:</label>
            <select id="emergency-contact-select" class="w-full bg-white text-gray-800 p-3 rounded-lg font-semibold focus:outline-none focus:ring-2 focus:ring-white appearance-none cursor-pointer hover:bg-gray-50 transition-colors">
                <option value="">Loading stations...</option>
            </select>
        </div>
                             <button class="call-trigger w-full bg-white text-bfp-red font-bold py-3 px-4 rounded-lg flex items-center justify-center space-x-2 hover:bg-gray-200 transition-colors shadow-md" data-number="">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
            </svg>
            <span>Call Selected Station</span>
        </button>
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 mb-3">Recent Activity</h2>
                            <div class="space-y-3" id="recent-activity-list"></div>
                        </div>
                    </main>

                    <main id="devices-screen" class="app-screen p-4 space-y-4 hidden">
                        <div class="flex justify-between items-center mb-3">
                            <h2 class="text-xl font-bold text-gray-800">Device Monitor</h2>
                            <button id="add-device-screen-trigger" style="display:none" class="text-bfp-red"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path></svg></button>
                        </div>
                        <div class="space-y-3" id="device-list"></div>
                    </main>

                    <main id="alerts-screen" class="app-screen p-4 space-y-4 hidden">
                        <div class="flex justify-between items-center mb-3">
                            <h2 class="text-xl font-bold text-gray-800">Alert History</h2>
                            <button class="text-sm text-blue-600">Clear All</button>
                        </div>
                        <div class="space-y-3" id="alert-history-list"></div>
                    </main>

                    <main id="location-screen" class="app-screen hidden">
                        <div class="p-4">
                            <h2 class="text-xl font-bold text-gray-800">Property Location</h2>
                            
                            <!-- 🛠️ NEW: Search Bar UI -->
                            <div class="mt-3 flex space-x-2">
                                <input type="text" id="location-search-input" placeholder="Search address or landmark..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                                <button id="location-search-btn" class="bg-blue-600 text-white p-2 rounded-lg shadow hover:bg-blue-700 transition-colors">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </button>
                            </div>
                        </div>

                        <div id="map" class="w-full h-48 bg-gray-300"></div>
                        <div class="p-4 bg-white">
                            <h3 class="font-bold text-lg text-gray-800 mb-2">Property Information</h3>
                            <div id="property-info" class="text-sm space-y-2 text-gray-600">
                                <p><strong class="text-gray-800">Address:</strong> N/A</p>
                                <p><strong class="text-gray-800">Coordinates:</strong> N/A</p>
                                <p><strong class="text-gray-800">Nearest BFP Station:</strong> N/A</p>
                                <p><strong class="text-gray-800">Visibility:</strong> <span class="text-green-600 font-semibold">N/A</span></p>
                            </div>
                        </div>
                    </main>
                    
                    <main id="settings-screen" class="app-screen p-4 space-y-4 hidden">
                        <h2 class="text-xl font-bold text-gray-800">Settings</h2>
                        <div class="bg-white p-4 rounded-lg shadow-md text-center">
                            <div class="w-24 h-24 rounded-full bg-bfp-red mx-auto mb-3 flex items-center justify-center text-white text-4xl font-bold" id="user-initials">JD</div>
                            <h3 class="font-bold text-xl text-gray-800" id="user-full-name">John Doe</h3>
                            <p class="text-sm text-gray-500" id="user-role">Property Owner</p>
                        </div>
                        <form id="profile-update-form">
                            <div class="bg-white p-4 rounded-lg shadow-md">
                                <h3 class="font-bold text-gray-800 mb-4">Profile Information</h3>
                                <div class="space-y-4 text-sm">
                                    <div class="flex items-center"><svg class="w-5 h-5 mr-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg><div><p class="text-gray-500">Full Name</p><input type="text" id="setting-full-name" value="John Doe" class="text-gray-800 font-semibold w-full border-b border-gray-200"></div></div>
                                    <div class="flex items-center"><svg class="w-5 h-5 mr-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path></svg><div><p class="text-gray-500">Email Address</p><p class="text-gray-800 font-semibold" id="setting-email">johndoe@gmail.com</p></div></div>
                                    <div class="flex items-center"><svg class="w-5 h-5 mr-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path></svg><div><p class="text-gray-500">Phone Number</p><input type="text" id="setting-phone" value="+639123456789" class="text-gray-800 font-semibold w-full border-b border-gray-200"></div></div>
                                    <div class="flex items-center" style="display:none"><svg class="w-5 h-5 mr-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg><div><p class="text-gray-500">Address</p><input type="text" id="setting-address" value="1-San Francisco Railway, Concepcion Norte" style="display:none" class="text-gray-800 font-semibold w-full border-b border-gray-200"></div></div>
                                    <div class="flex items-center"><svg class="w-5 h-5 mr-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg><div><p class="text-gray-500">Location Visibility</p><select id="setting-permission-level" class="text-gray-800 font-semibold w-full border-b border-gray-200"><option value="private">Private (Only I see)</option><option value="public">Public (BFP sees)</option></select></div></div>
                                </div>
                            </div>
                            <input type="hidden" id="setting-latitude" name="latitude" value="">
                            <input type="hidden" id="setting-longitude" name="longitude" value="">
                            <div class="space-y-3 pt-4">
                                <button type="submit" class="w-full bg-gradient-to-br from-blue-500 to-blue-600 text-white font-semibold py-3 rounded-lg shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all">Update Profile</button>
                                <button type="button" class="w-full bg-red-100 text-red-600 font-semibold py-3 rounded-lg hover:bg-red-200 transition-all" onclick="window.location.href = 'logout.php';">Sign Out</button>
                            </div>
                        </form>
                    </main>
                </div>
                
                <nav id="bottom-nav" class="bg-white border-t border-gray-200 flex justify-around sm:rounded-b-lg shadow-[0_-2px_5px_rgba(0,0,0,0.05)] z-20">
                    <a href="#home" data-screen="home-screen" class="nav-link flex flex-col items-center justify-center p-3 text-bfp-red w-full"><svg class="w-6 h-6 mb-1" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg><span class="text-xs font-semibold">Home</span></a>
                    <a href="#devices" data-screen="devices-screen" class="nav-link flex flex-col items-center justify-center p-3 text-gray-500 hover:text-bfp-red w-full"><svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg><span class="text-xs font-semibold">Devices</span></a>
                    <a href="#alerts" data-screen="alerts-screen" class="nav-link flex flex-col items-center justify-center p-3 text-gray-500 hover:text-bfp-red w-full relative"><span id="alert-badge" class="absolute top-2 right-6 w-2 h-2 bg-bfp-red rounded-full hidden"></span><svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg><span class="text-xs font-semibold">Alerts</span></a>
                    <a href="#location" data-screen="location-screen" class="nav-link flex flex-col items-center justify-center p-3 text-gray-500 hover:text-bfp-red w-full"><svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg><span class="text-xs font-semibold">Location</span></a>
                    <a href="#settings" data-screen="settings-screen" class="nav-link flex flex-col items-center justify-center p-3 text-gray-500 hover:text-bfp-red w-full"><svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg><span class="text-xs font-semibold">Settings</span></a>
                </nav>
            </div>
            
            <div id="add-device-screen" class="absolute inset-0 bg-gray-100 flex-col hidden z-30 overflow-y-auto">
                <header class="bg-white p-4 flex items-center justify-between border-b sticky top-0 z-10">
                    <button id="back-to-devices-btn"><svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                    <h2 class="text-lg font-semibold text-gray-800">Add Device</h2>
                    <button><svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg></button>
                </header>
                <div class="p-6 flex-grow">
                    <form id="add-device-form">
                        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-blue-100 text-blue-500 rounded-full mx-auto flex items-center justify-center mb-4"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0118.657 17.657c-1.566 1.566-2.343 3.657-2.343 3.657-1-2-4-2.986-7-2.986 2.5.986 5 .5 7-2.986zM12 12a2 2 0 100-4 2 2 0 000 4z"></path></svg></div>
                                <h3 class="text-xl font-bold text-gray-800">Fire Detector Setup</h3>
                                <p class="text-gray-500 mt-2">Follow the steps to connect your device.</p>
                            </div>
                            
                            <!-- UPDATED STEPS FOR REAL-WORLD CONFIGURATION -->
                            <ul class="mt-6 space-y-3 text-gray-700">
                                
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-3">1</div>
                                    <div>
                                        <p class="font-medium text-gray-800">Device Powered On</p>
                                        <p class="text-sm">Ensure the device is plugged in and ready for setup mode.</p>
                                    </div>
                                </li>
                                
                                <li class="flex items-start" id="local-ap-status-item">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 mr-3">2</div>
                                    <div id="local-ap-text">
                                        <p class="font-medium text-gray-800">Awaiting Connection (192.168.4.1)</p>
                                        <p class="text-sm text-red-600 font-bold">CRITICAL: Manually connect your phone/PC to the device's Wi-Fi network (e.g., BFP-SETUP-1234) in your OS settings.</p>
                                    </div>
                                </li>
                                
                                <li class="flex items-start" id="server-reg-status-item">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 mr-3">3</div>
                                    <div id="server-reg-text">
                                        <p class="font-medium text-gray-800">Server Registration</p>
                                        <p class="text-sm">Select device details below and click '+ Add Device' to send your home Wi-Fi credentials.</p>
                                    </div>
                                </li>
                            </ul>
                            <!-- END OF UPDATED STEPS -->
                            
                            <div class="mt-6 space-y-4">
                                <div id="user-selection-container" class="hidden">
                                    <label for="new-device-user" class="text-sm font-medium text-gray-600">Assign to User</label>
                                    <select id="new-device-user" class="mt-1 w-full p-2.5 border rounded-lg bg-gray-50"></select>
                                </div>
                                <div>
                                    <label for="new-device-location-select" class="text-sm font-medium text-gray-600">Property Location</label>
                                    <select id="new-device-location-select" class="mt-1 w-full p-2.5 border rounded-lg bg-gray-50">
                                        <option value="">Select a Location</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="new-device-type-select" class="text-sm font-medium text-gray-600">Sensor Type</label>
                                    <select id="new-device-type-select" class="mt-1 w-full p-2.5 border rounded-lg bg-gray-50">
                                        <option value="smoke">Multisensor</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="esp-unique-id" class="text-sm font-medium text-gray-600">ESP Unique ID (MAC Address)</label>
                                    <select id="esp-unique-id" class="mt-1 w-full p-2.5 border rounded-lg bg-gray-50">
                                        <option value="">Awaiting Device Connection...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-lg shadow-md hover:bg-blue-700 transition-colors opacity-50 cursor-not-allowed" disabled>+ Add Device</button>
                    </form>
                    <div class="mt-8">
                        <h3 class="font-bold text-gray-800 mb-3">Current Devices</h3>
                        <div class="space-y-3" id="add-device-current-list"></div>
                    </div>
                </div>
            </div>
            
            <!-- REMOVED: wifi-setup-screen AND wifi-password-modal -->

            <div id="call-screen" class="fixed inset-0 bg-bfp-red text-white p-6 flex-col items-center justify-between hidden z-50">
                <div class="text-center pt-16">
                    <div class="w-32 h-32 mx-auto rounded-full border-4 border-white/50 flex items-center justify-center">
                        <svg class="w-16 h-16 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 2Z"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold mt-4" id="calling-to-name">N/A</h2>
                    <p class="text-lg opacity-80" id="calling-to-number">N/A</p>
                </div>
                <div class="text-center">
                    <div class="bg-white/20 rounded-full px-4 py-2 text-sm font-semibold">CONNECTED</div>
                    <p id="call-timer" class="text-lg mt-2">00:00</p>
                </div>
                <div class="w-full bg-white/10 rounded-lg p-4 text-sm" id="call-details">
                    <h3 class="font-bold mb-2">Emergency Details</h3>
                    <p><span class="opacity-80">Property:</span> N/A</p>
                    <p><span class="opacity-80">Alert Type:</span> N/A</p>
                    <p><span class="opacity-80">Coordinates:</span> N/A</p>
                    <p><span class="opacity-80">Nearest Station:</span> N/A</p>
                </div>
                <div class="w-full flex flex-col items-center">
                    <div class="flex space-x-8">
                        <button class="bg-white/20 p-4 rounded-full"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"></path></svg></button>
                        <button class="bg-white/20 p-4 rounded-full"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M5.25 3.5a.75.75 0 000 1.5h9.5a.75.75 0 000-1.5h-9.5zM3.5 6.25a.75.75 0 01.75-.75h11.5a.75.75 0 010 1.5H4.25a.75.75 0 01-.75-.75zM2 9.25a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 9.25zM1.75 12a.75.75 0 000 1.5h16.5a.75.75 0 000-1.5H1.75zM1 15.25a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H1.75a.75.75 0 01-.75-.75z"></path></svg></button>
                        <button class="bg-white/20 p-4 rounded-full"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg></button>
                    </div>
                    <button id="end-call-btn" class="mt-6 bg-red-600 px-8 py-3 rounded-full font-semibold">End Call</button>
                </div>
            </div>
        </div>
        
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- NEW: Leaflet Routing Machine JS -->
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <script>
        document.getElementById('google-login-btn').addEventListener('click', (e) => {
            e.preventDefault();
            // Redirect to Google OAuth handler
            window.location.href = 'google_auth.php';
        });

        
let readingCache = {}; // Cache to prevent unnecessary updates
let updateCheckInterval = null;
        
       document.addEventListener('DOMContentLoaded', () => {
        // --- API CONFIG ---
        const API_ENDPOINT = 'api.php'; // <--- CORRECTED: Now uses api.php
        const POLLING_INTERVAL = 10000; // 10 seconds for live update simulation
        const isLoggedIn = <?php echo json_encode($is_logged_in); ?>;
        
        const splashScreen = document.getElementById('splash-screen');
        const loginScreen = document.getElementById('login-screen');
        const appContainer = document.getElementById('app-container');
        
        // --- STATE AND UI ELEMENTS (Start) ---
        const SCREEN_IDS = { HOME: 'home-screen', DEVICES: 'devices-screen', ALERTS: 'alerts-screen', LOCATION: 'location-screen', SETTINGS: 'settings-screen' };
        let map;
        let callTimerInterval;
        let seconds = 0;
        let routingControl = null; // Variable to hold the routing control
        // REMOVED: isWifiConnectedLocal

        const screens = document.querySelectorAll('.app-screen');
        const navLinks = document.querySelectorAll('.nav-link');
        const callScreen = document.getElementById('call-screen');
        const addDeviceScreen = document.getElementById('add-device-screen');
        // REMOVED: wifiSetupScreen and wifiPasswordModal
        const addDeviceForm = document.getElementById('add-device-form');
        const profileUpdateForm = document.getElementById('profile-update-form');
        const recentActivityList = document.getElementById('recent-activity-list');
        const deviceList = document.getElementById('device-list');
        const alertHistoryList = document.getElementById('alert-history-list');
        const propertyInfoEl = document.getElementById('property-info');
        const emergencyContactSelect = document.getElementById('emergency-contact-select');
        const currentUserRole = <?php echo json_encode($current_user_role); ?>;
        // --- STATE AND UI ELEMENTS (End) ---


        // ================================================================
        // --- CORE FUNCTIONS (MOVED TO THE TOP FOR HOISTING FIX) ---
        // ================================================================

        // --- UTILITY/HELPER FUNCTIONS ---

// REPLACE WITH THIS:
const showScreen = (screenId) => {
    screens.forEach(s => s.classList.toggle('hidden', s.id !== screenId));
    navLinks.forEach(l => {
        l.classList.toggle('text-bfp-red', l.dataset.screen === screenId);
        l.classList.toggle('text-gray-500', l.dataset.screen !== screenId);
    });

    // Always stop realtime first
    // stopRealtimeLoop();

    if (screenId === SCREEN_IDS.HOME) {
        fetchDashboardData();
    }
    
    if (screenId === SCREEN_IDS.DEVICES) {
        fetchDeviceData().then(() => {
            console.log("✅ Devices loaded, starting real-time updates");
            // startRealtimeLoop(); // START HERE
        }).catch(err => {
            console.error("Error loading devices:", err);
        });
    }
    
    if (screenId === SCREEN_IDS.ALERTS) {
        fetchDashboardData();
    }
    
    if (screenId === SCREEN_IDS.LOCATION) {
        fetchLocationData();
    }
    
    if (screenId === SCREEN_IDS.SETTINGS) {
        fetchProfileData();
    }
};

const stopRealtimeLoop = () => {
    if (updateCheckInterval) {
        clearInterval(updateCheckInterval);
        updateCheckInterval = null;
        console.log("⏹️ Real-Time Updates Stopped");
    }
};


// // Step 3: Main real-time loop
// const startRealtimeLoop = () => {
//     console.log("🚀 Real-Time Updates Starting...");
    
//     // Stop any existing interval
//     if (updateCheckInterval) clearInterval(updateCheckInterval);
    
//     // Get all sensor IDs from the DOM
//     const getAllSensorIds = () => {
//         const sensorIds = [];
//         const deviceCards = document.querySelectorAll('[id^="device-"]');
        
//         deviceCards.forEach(card => {
//             const match = card.id.match(/device-gas-(\d+)/);
//             if (match && !sensorIds.includes(match[1])) {
//                 sensorIds.push(match[1]);
//             }
//         });
        
//         return sensorIds;
//     };
    
//     // Run updates every 2 seconds
//     updateCheckInterval = setInterval(async () => {
//         const devicesScreen = document.getElementById('devices-screen');
        
//         // Only update if user is on devices screen
//         if (devicesScreen && devicesScreen.classList.contains('hidden')) {
//             return;
//         }
        
//         const sensorIds = getAllSensorIds();
        
//         if (sensorIds.length === 0) {
//             console.log("No sensors found on screen yet");
//             return;
//         }
        
//         // Fetch readings for all sensors in parallel
//         const promises = sensorIds.map(id => fetchLatestReadings(id));
//         const results = await Promise.all(promises);
        
//         // Update each one
//         results.forEach((data, index) => {
//             if (data) {
//                 updateDeviceReadingOnScreen(sensorIds[index], data);
//             }
//         });
        
//     }, 2000); // Every 2 seconds
// };
// ---THIS TOP FOR REALINSING REaltimem reading
// // Function to fetch only sensor readings (lightweight request)
// const fetchSensorReadingsOnly = async () => {
//     try {
//         // Fetch all devices first
//         const devicesResponse = await fetch(`${API_ENDPOINT}?action=get_devices`);
//         const devicesJson = await devicesResponse.json();
        
//         if (!devicesJson.success) return;
        
//         const devices = devicesJson.data;
        
//         // For each device, fetch ONLY the latest readings
//         for (const device of devices) {
//             const sensorId = device.sensor_ID;
            
//             try {
//                 const readingsResponse = await fetch(
//                     `${API_ENDPOINT}?action=get_latest_readings&sensor_id=${sensorId}`
//                 );
//                 const readingsJson = await readingsResponse.json();
                
//                 if (readingsJson.success && readingsJson.data) {
//                     const r = readingsJson.data;
                    
//                     // Create a cache key for this sensor
//                     const cacheKey = `sensor_${sensorId}`;
                    
//                     // Check if values changed
//                     const newData = {
//                         gas: Math.round((r.gas_raw || 0) * 100) / 100,
//                         temp: Math.round((r.temp_c || 0) * 100) / 100,
//                         fire_status: r.fire_status || 'SAFE',
//                         timestamp: Date.now()
//                     };
                    
//                     // Compare with cached value
//                     if (!readingCache[cacheKey] || 
//                         readingCache[cacheKey].gas !== newData.gas ||
//                         readingCache[cacheKey].temp !== newData.temp ||
//                         readingCache[cacheKey].fire_status !== newData.fire_status) {
                        
//                         // Value changed! Update the DOM
//                         updateSensorDisplay(sensorId, newData);
//                         readingCache[cacheKey] = newData;
//                     }
//                 }
//             } catch (error) {
//                 console.error(`Error fetching readings for sensor ${sensorId}:`, error);
//             }
//         }
        
//     } catch (error) {
//         console.error('Error fetching devices:', error);
//     }
// };

const fetchLatestReadings = async (sensorId) => {
    try {
        const response = await fetch(`${API_ENDPOINT}?action=get_latest_readings&sensor_id=${sensorId}`);
        const json = await response.json();
        
        if (json.success && json.data) {
            return {
                gas: json.data.gas_raw !== null ? parseInt(json.data.gas_raw) : 0,
                temp: json.data.temp_c !== null ? parseFloat(json.data.temp_c) : 0,
                fire_status: json.data.fire_status || 'SAFE'
            };
        }
    } catch (error) {
        console.error(`Error fetching readings for sensor ${sensorId}:`, error);
    }
    return null;
};

// Step 2: Update the DOM elements ONLY (no re-render)
const updateDeviceReadingOnScreen = (sensorId, newData) => {
    if (!newData) return;
    
    // Create cache key
    const cacheKey = `sensor_${sensorId}`;
    const oldData = readingCache[cacheKey];
    
    // Check if anything actually changed
    if (oldData && 
        oldData.gas === newData.gas &&
        oldData.temp === newData.temp &&
        oldData.fire_status === newData.fire_status) {
        return; // No change, don't update
    }
    
    // UPDATE GAS VALUE + LEVEL CONDITION
    const gasEl = document.getElementById(`device-gas-${sensorId}`);
    const gasLevelEl = document.getElementById(`device-gas-level-${sensorId}`);
    
    if ((gasEl || gasLevelEl) && (!oldData || oldData.gas !== newData.gas)) {
        if (gasEl) {
            gasEl.textContent = newData.gas;
            gasEl.style.color = '#C82828';
            setTimeout(() => { gasEl.style.color = '#1F2937'; }, 500);
        }
        
        // Update level badge with proper Tailwind classes
        if (gasLevelEl) {
            const levelText = newData.gas > 300 ? 'High' : 'Normal';
            const bgClass = newData.gas > 300 ? 'bg-red-100 text-red-900' : 'bg-green-100 text-green-900';
            
            gasLevelEl.textContent = levelText;
            gasLevelEl.className = `inline-block px-3 py-1 text-xs font-bold rounded transition-all ${bgClass}`;
        }
    }
    
    // UPDATE TEMPERATURE VALUE + CONDITION
    const tempEl = document.getElementById(`device-temp-${sensorId}`);
    const tempConditionEl = document.getElementById(`device-temp-condition-${sensorId}`);
    
    if ((tempEl || tempConditionEl) && (!oldData || oldData.temp !== newData.temp)) {
        if (tempEl) {
            tempEl.textContent = newData.temp.toFixed(1);
            tempEl.style.color = newData.temp > 35 ? '#DC2626' : '#1F2937';
        }
        
        // Update condition text + color
        if (tempConditionEl) {
            const conditionText = newData.temp > 35 ? 'High' : 'Normal';
            tempConditionEl.textContent = conditionText;
            tempConditionEl.style.color = newData.temp > 35 ? '#DC2626' : '#2563EB';
            tempConditionEl.style.fontWeight = 'bold';
            tempConditionEl.style.transition = 'color 0.3s ease';
        }
    }
    
    // UPDATE FIRE STATUS (Small + Large)
    const fireEl = document.getElementById(`device-fire-status-${sensorId}`);
    const fireLargeEl = document.getElementById(`device-fire-status-large-${sensorId}`);
    
    if ((fireEl || fireLargeEl) && (!oldData || oldData.fire_status !== newData.fire_status)) {
        // Update color based on status
        let colorClass = 'text-green-600';
        if (newData.fire_status === 'WARNING') colorClass = 'text-orange-500';
        if (newData.fire_status === 'CRITICAL') colorClass = 'text-bfp-red';
        
        // Update small status indicator
        if (fireEl) {
            fireEl.textContent = newData.fire_status;
            fireEl.className = `font-bold text-sm ${colorClass}`;
        }
        
        // Update large fire status display
        if (fireLargeEl) {
            fireLargeEl.textContent = newData.fire_status;
            fireLargeEl.className = `text-3xl font-bold ${colorClass} transition-all`;
            
            // Flash animation
            fireLargeEl.style.transform = 'scale(1.1)';
            setTimeout(() => { fireLargeEl.style.transform = 'scale(1)'; }, 300);
        }
    }
    
    // Save to cache
    readingCache[cacheKey] = newData;
    console.log(`✅ Updated Sensor ${sensorId}:`, newData);
};

       const renderActivityList = (incidents, targetEl) => {
    targetEl.innerHTML = '';
    const isBFP = currentUserRole === 'bfp_officer' || currentUserRole === 'bfp_assigned_at_desk';
    const isAlertsScreen = targetEl.id === 'alert-history-list';

    incidents.forEach(incident => {
        const isActive = incident.status.toUpperCase() === 'ACTIVE';
        const colorClass = isActive ? 'border-stat-red' : 'border-status-green';
        const bgColor = isActive ? 'bg-active-red-bg' : 'bg-resolved-green-bg';
        const textColor = isActive ? 'text-active-red-text' : 'text-resolved-green-text';
        const severityHtml = isActive ? `<p><span class="font-semibold text-gray-700">Severity:</span> <span class="text-stat-red font-bold">${incident.severity}</span></p>` : '';
        
        let buttonsHtml = '';
        if (isActive) {
            // Residents get Call/Dismiss on Home screen (Recent Activity)
            if (!isBFP && !isAlertsScreen) {
                buttonsHtml = `<div class="mt-4 flex space-x-2 pl-12">
                    <button class="call-trigger flex-1 bg-bfp-red text-white font-bold py-2 px-4 rounded-lg text-sm hover:bg-bfp-red-dark transition-colors" 
                        data-device="${incident.device}" data-lat="${incident.lat}" data-lon="${incident.lon}" data-type="${incident.type}">
                        Call BFP
                    </button>
                    <button class="dismiss-alert-trigger flex-1 bg-gray-200 text-gray-700 font-bold py-2 px-4 rounded-lg text-sm hover:bg-gray-300 transition-colors"
                        data-incident-id="${incident.id}">
                        Resolve
                    </button>
                   </div>`; 
            } 
            // BFP users OR Residents on the ALERTS screen get the Resolve/Call buttons
            else if (isBFP || isAlertsScreen) {
                 buttonsHtml = `<div class="mt-4 flex space-x-2 pl-12">
                    <button class="call-trigger flex-1 bg-bfp-red text-white font-bold py-2 px-4 rounded-lg text-sm hover:bg-bfp-red-dark transition-colors" 
                        data-device="${incident.device}" data-lat="${incident.lat}" data-lon="${incident.lon}" data-type="${incident.type}">
                        Call BFP
                    </button>
                    <button class="resolve-alert-trigger flex-1 bg-status-green text-white font-bold py-2 px-4 rounded-lg text-sm hover:bg-green-600 transition-colors"
                        data-incident-id="${incident.id}">
                        Resolve
                    </button>
                   </div>`;
            }
        }


        const card = document.createElement('div');
        card.className = `bg-white p-4 rounded-lg shadow-md border-l-4 ${colorClass}`;
        card.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex items-center">
                    <div class="${bgColor} p-2 rounded-full mr-3">
                        <svg class="w-6 h-6 ${isActive ? 'text-stat-red' : 'text-status-green'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isActive ? 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0118.657 17.657c-1.566 1.566-2.343 3.657-2.343 3.657-1-2-4-2.986-7-2.986 2.5.986 5 .5 7-2.986zM12 12a2 2 0 100-4 2 2 0 000 4z' : 'M13 10V3L4 14h7v7l9-11h-7z'}"></path>
                        </svg>
                    </div>
                    <div><h3 class="font-bold text-gray-800">${incident.type}</h3><p class="text-sm text-gray-500">${incident.time}</p></div>
                </div>
                <span class="${bgColor} ${textColor} text-xs font-bold px-3 py-1 rounded-full">${incident.status}</span>
            </div>
            <div class="mt-3 text-sm text-gray-600 space-y-1 pl-12">
                <p><span class="font-semibold text-gray-700">Device:</span> ${incident.device}</p>
                ${severityHtml}
            </div>
            ${buttonsHtml}
        `;
        targetEl.appendChild(card);
    });
};

const startBackgroundUpdates = () => {
    console.log("🚀 Background Real-Time Updates Started");
    
    // Update readings every 3 seconds, regardless of which screen is active
    setInterval(async () => {
        try {
            const response = await fetch(`${API_ENDPOINT}?action=get_devices`);
            const json = await response.json();
            
            if (json.success) {
                const devices = json.data;
                
                for (const device of devices) {
                    const sensorId = device.sensor_ID;
                    const readingsResponse = await fetch(
                        `${API_ENDPOINT}?action=get_latest_readings&sensor_id=${sensorId}`
                    );
                    const readingsJson = await readingsResponse.json();
                    
                    if (readingsJson.success && readingsJson.data) {
                        const r = readingsJson.data;
                        const newData = {
                            gas: Math.round((r.gas_raw || 0) * 100) / 100,
                            temp: Math.round((r.temp_c || 0) * 100) / 100,
                            fire_status: r.fire_status || 'SAFE'
                        };
                        
                        // Only update if visible
                        if (!document.getElementById('devices-screen').classList.contains('hidden')) {
                            updateSensorDisplay(sensorId, newData);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Background update error:', error);
        }
    }, 3000); // Every 3 seconds
};

// --- HELPER: Smooth Number Animation ---
const animateValue = (obj, start, end, duration) => {
    if (start === end) return;
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        // Use Math.floor to keep integers (good for raw values)
        obj.innerHTML = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
};
        // --- API CALLS ---
        const fetchDashboardData = async () => {
            try {
                const response = await fetch(`${API_ENDPOINT}?action=get_dashboard_data`);
                const json = await response.json();
                if (!json.success) throw new Error(json.message);

                const data = json.data;
                document.getElementById('active-devices-count').textContent = data.active_devices_count;
                document.getElementById('active-alerts-count').textContent = data.active_alerts_count;

                const status = data.active_alerts_count > 0 ? 'ALERT' : 'SAFE';
                const statusBg = data.active_alerts_count > 0 ? 'bg-stat-red' : 'bg-status-green';
                document.getElementById('system-status-bar').className = `mt-4 ${statusBg} text-white p-3 rounded-lg flex items-center justify-between shadow`;
                document.getElementById('system-status-text').textContent = status === 'ALERT' ? 'Active Alert Detected' : 'All Systems Normal';
                document.getElementById('system-status-tag').textContent = status;
                document.getElementById('alert-badge').classList.toggle('hidden', data.active_alerts_count === 0);

                renderActivityList(data.incidents, recentActivityList);
                renderActivityList(data.incidents, alertHistoryList);

                // Fetch and populate BFP stations
                fetchAndPopulateBFPStations();

            } catch (error) {
                console.error('API Error: get_dashboard_data', error);
                // Handle the case where the DB query failed (e.g., show default values or an error message on screen)
            }
        };

 // REPLACE THIS FUNCTION in index.php - Fetch BFP stations and populate emergency contact dropdown
// ADD THIS DEBUGGING VERSION - Replace the fetchAndPopulateBFPStations function

const fetchAndPopulateBFPStations = async () => {
    console.log('🔍 Starting fetchAndPopulateBFPStations...');
    
    try {
        // Step 1: Make the API call
        console.log(`📡 Fetching from: ${API_ENDPOINT}?action=stations`);
        const response = await fetch(`${API_ENDPOINT}?action=stations`);
        
        console.log('📦 Response status:', response.status);
        console.log('📦 Response headers:', response.headers);
        
        const json = await response.json();
        console.log('📦 Raw JSON response:', json);
        
        // Step 2: Parse the stations data
        let stations = [];
        
        if (Array.isArray(json.data)) {
            stations = json.data;
            console.log('✅ Found stations in json.data');
        } else if (Array.isArray(json)) {
            stations = json;
            console.log('✅ Found stations in json (direct array)');
        } else if (Array.isArray(json.stations)) {
            stations = json.stations;
            console.log('✅ Found stations in json.stations');
        }
        
        console.log(`📊 Total stations found: ${stations.length}`);
        console.log('📋 Stations:', stations);
        
        if (!stations || stations.length === 0) {
            throw new Error('No stations data in response');
        }

        const select = document.getElementById('emergency-contact-select');
        console.log('🎯 Select element:', select);
        
        if (!select) {
            console.error('❌ emergency-contact-select not found in DOM');
            return;
        }

        // Step 3: Clear existing options
        select.innerHTML = '';
        console.log('🧹 Cleared existing options');

        // Step 4: Populate dropdown with stations
        stations.forEach((station, index) => {
            console.log(`📌 Processing station ${index}:`, station);
            
            const option = document.createElement('option');
            
            let contactNumber = station.contact_number || '';
            console.log(`  Raw number: ${contactNumber}`);
            
            contactNumber = contactNumber.replace(/\//g, '');
            console.log(`  Cleaned number: ${contactNumber}`);
            
            option.value = station.station_name;
            option.setAttribute('data-number', contactNumber);
            option.textContent = `${station.station_name} - ${contactNumber}`;
            
            select.appendChild(option);
            console.log(`  ✅ Added option: ${station.station_name}`);
        });

        console.log(`✅ All ${stations.length} stations added to dropdown`);

        // Step 5: Set first station as default for call button
        if (stations.length > 0) {
            const callBtn = document.querySelector('.call-trigger');
            console.log('📞 Call button:', callBtn);
            
            if (callBtn) {
                const contactNumber = (stations[0].contact_number || '').replace(/\//g, '');
                callBtn.setAttribute('data-number', contactNumber);
                callBtn.setAttribute('data-station-name', stations[0].station_name);
                console.log(`✅ Set default station: ${stations[0].station_name} (${contactNumber})`);
            }
        }

        console.log('✅ BFP stations loaded successfully!');
        
    } catch (error) {
        console.error('❌ Error loading BFP stations:', error);
        
        const select = document.getElementById('emergency-contact-select');
        if (select) {
            select.innerHTML = '<option value="">⚠️ Error loading stations - Check console</option>';
        }
    }
};

// Call this function when page loads (make sure it's in your initialization)
// Add this to your DOMContentLoaded or wherever you initialize:
// setTimeout(() => fetchAndPopulateBFPStations(), 500);

document.getElementById('emergency-contact-select')?.addEventListener('change', (e) => {
    const selectedOption = e.target.options[e.target.selectedIndex];
    const callBtn = document.querySelector('.call-trigger');
    
    if (callBtn && selectedOption) {
        let contactNumber = selectedOption.getAttribute('data-number') || '';
        contactNumber = contactNumber.replace(/\//g, ''); // Remove slashes
        
        callBtn.setAttribute('data-number', contactNumber);
        callBtn.setAttribute('data-station-name', selectedOption.value);
    }
});

const fetchDeviceData = async () => {
    try {
        // 1. Fetch all static sensor data
        const response = await fetch(`${API_ENDPOINT}?action=get_devices`);
        const json = await response.json();
        if (!json.success) throw new Error(json.message);

        const devices = json.data;
        const deviceList = document.getElementById('device-list');
        const addDeviceList = document.getElementById('add-device-current-list');
        
        // Track current device IDs to handle removals
        const currentDeviceIds = new Set(devices.map(d => `device-${d.sensor_ID}`));

        // Remove devices that are no longer in the list (Main Dashboard)
        Array.from(deviceList.children).forEach(child => {
            if (!currentDeviceIds.has(child.id)) {
                child.remove();
            }
        });

        // Clear the "Add Device" list only if it's empty/needs refresh to avoid duplication issues
        // (Optional: for smoother "Add Device" screen, you might want more complex logic, 
        // but for now we will rely on the else block to add new ones)
        if(devices.length === 0) addDeviceList.innerHTML = '';

        for (const device of devices) {
            // --- Fetch Real-Time Readings ---
            let realTimeData = { gas: 0, temp: 0, live_status: device.status, fire_status: 'SAFE' };
            
            try {
                const readingsResponse = await fetch(`${API_ENDPOINT}?action=get_latest_readings&sensor_id=${device.sensor_ID}`);
                const readingsJson = await readingsResponse.json();
                
                if (readingsJson.success && readingsJson.data) {
                    const r = readingsJson.data;
                    realTimeData = {
                        gas: (r.gas_raw !== null && r.gas_raw !== 'N/A') ? parseInt(r.gas_raw) : 0,
                        temp: (r.temp_c !== null && r.temp_c !== 'N/A') ? parseFloat(r.temp_c) : 0,
                        fire_status: r.fire_status || 'SAFE'
                    };
                }
            } catch (error) {
                console.error(`Error readings Sensor ${device.sensor_ID}:`, error);
            }

            // --- Status Colors & Text ---
            const statusText = device.status.charAt(0).toUpperCase() + device.status.slice(1);
            const statusColor = device.status.toUpperCase() === 'ACTIVE' ? 'bg-green-100 text-green-800' : 
                              (device.status.toUpperCase() === 'ERROR' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
            const dotColor = device.status.toUpperCase() === 'ACTIVE' ? 'bg-green-500' : 
                           (device.status.toUpperCase() === 'ERROR' ? 'bg-red-500' : 'bg-gray-400');
            
            // FIX: Define isOnline for use in the Add Device List below
            const isOnline = device.status.toUpperCase() === 'ACTIVE';

            // Fire Status Logic
            const fireStatusColor = realTimeData.fire_status === 'SAFE' ? 'text-green-600' : 
                                  (realTimeData.fire_status === 'WARNING' ? 'text-orange-500' : 'text-red-600');
            const fireBg = realTimeData.fire_status === 'SAFE' ? 'bg-green-50' : 'bg-red-50';
            const fireIcon = realTimeData.fire_status === 'SAFE' 
                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />' 
                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />';


            // --- UPDATE EXISTING or CREATE NEW ---
            const cardId = `device-${device.sensor_ID}`;
            let card = document.getElementById(cardId);

            if (card) {
                // === UPDATE MODE (Animation Logic) ===
                
                // 1. Animate Gas
                const gasEl = document.getElementById(`device-gas-${device.sensor_ID}`);
                const currentGas = parseInt(gasEl.innerText) || 0;
                if (currentGas !== realTimeData.gas) {
                    animateValue(gasEl, currentGas, realTimeData.gas, 300); 
                }

                // 2. Animate Temp
                const tempEl = document.getElementById(`device-temp-${device.sensor_ID}`);
                if (tempEl) {
                    const currentTemp = parseFloat(tempEl.innerText) || 0;
                    // Only animate if value changed significantly
                    if (Math.abs(currentTemp - realTimeData.temp) > 0.1) {
                        // Use helper or direct update if float animation is tricky
                        tempEl.innerText = realTimeData.temp; 
                        // Note: animateValue usually handles integers. For floats, direct update is safer/cleaner.
                    } else {
                        tempEl.innerText = realTimeData.temp;
                    }
                }

                // 3. Update Status Text & Icons
                document.getElementById(`device-fire-status-${device.sensor_ID}`).innerText = realTimeData.fire_status;
                
            } else {
                // === CREATE MODE (First Load) ===

const dHtml = `
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-all" id="${cardId}">
    
    <!-- HEADER: Device Name + Status Badge -->
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-5 py-4 border-b border-gray-200">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h3 class="font-bold text-gray-900 text-lg">${device.sensor_type.toUpperCase()}</h3>
                <p class="text-xs text-gray-500 mt-1">${device.location_name}</p>
            </div>
            <span class="px-3 py-1.5 text-xs font-semibold rounded-full ${statusColor}">
                ${statusText}
            </span>
        </div>
        <p class="text-xs text-gray-600">${device.address}</p>
    </div>

    <!-- STATUS INDICATORS (Real-Time Updates) -->
    <div class="grid grid-cols-3 gap-3 px-5 py-4 bg-white border-b border-gray-100">
        <!-- Online Status -->
        <div class="text-center">
            <div class="flex items-center justify-center mb-2">
                <div class="w-3 h-3 ${dotColor} rounded-full"></div>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
            <p class="text-sm font-semibold text-gray-800">${isOnline ? 'Online' : 'Offline'}</p>
        </div>
        
        <!-- Fire Status (UPDATES REAL-TIME) -->
        <div class="text-center">
            <div class="flex items-center justify-center mb-2">
                <div class="w-6 h-6 rounded-lg ${fireBg} flex items-center justify-center">
                    <svg class="w-4 h-4 ${fireStatusColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${realTimeData.fire_status === 'SAFE' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0118.657 17.657c-1.566 1.566-2.343 3.657-2.343 3.657-1-2-4-2.986-7-2.986 2.5.986 5 .5 7-2.986zM12 12a2 2 0 100-4 2 2 0 000 4z'}"></path>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Alert</p>
            <p class="text-sm font-semibold ${fireStatusColor}" id="device-fire-status-${device.sensor_ID}">${realTimeData.fire_status}</p>
        </div>

        <!-- Device ID -->
        <div class="text-center">
            <div class="flex items-center justify-center mb-2">
                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                </svg>
            </div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">ID</p>
            <p class="text-xs font-mono text-gray-700">${device.esp_ip_unique.substring(0, 8)}...</p>
        </div>
    </div>

    <!-- READINGS SECTION (Real-Time Updates) -->
    <div class="px-5 py-4 space-y-3">
        
        <!-- Smoke/Gas Reading -->
<div class="flex items-center justify-between py-3 border-b border-gray-100">
    <div class="flex-1">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Smoke Level</div>
        <div class="flex items-baseline gap-2">
            <span class="text-gray-900 font-bold text-xl" id="device-gas-${device.sensor_ID}">${realTimeData.gas}</span>
            <span class="text-sm text-gray-500">RAW</span>
        </div>
    </div>
    <div class="text-right">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Level</div>
        <span class="inline-block px-3 py-1 text-xs font-bold rounded transition-all ${realTimeData.gas > 300 ? 'bg-red-100 text-red-900' : 'bg-green-100 text-green-900'}" id="device-gas-level-${device.sensor_ID}">
            ${realTimeData.gas > 300 ? 'High' : 'Normal'}
        </span>
    </div>
</div>

        <!-- Temperature Reading -->
<div class="flex items-center justify-between py-3 border-b border-gray-100">
    <div class="flex-1">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Temperature</div>
        <div class="flex items-baseline gap-2">
            <span class="text-gray-900 font-bold text-xl" id="device-temp-${device.sensor_ID}">${realTimeData.temp}</span>
            <span class="text-gray-700 font-medium">°C</span>
        </div>
    </div>
    <div class="text-right">
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Condition</div>
        <div class="flex items-center justify-end gap-1.5">
            <span class="text-sm font-bold transition-colors" id="device-temp-condition-${device.sensor_ID}" style="color: ${realTimeData.temp > 35 ? '#DC2626' : '#2563EB'};">
                ${realTimeData.temp > 35 ? 'High' : 'Normal'}
            </span>
        </div>
    </div>
</div>
        <!-- Fire Status Large Display (MAIN UPDATE AREA) -->
        <div class="bg-gradient-to-br ${fireBg} rounded-lg p-4 mt-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Fire Status</p>
                    <p class="text-3xl font-bold ${fireStatusColor} transition-all" id="device-fire-status-large-${device.sensor_ID}">
                        ${realTimeData.fire_status}
                    </p>
                </div>
                <svg class="w-12 h-12 ${fireStatusColor} opacity-30" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 100-2h-1a1 1 0 100 2h1zM15.657 14.243a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM11 17a1 1 0 102 0v-1a1 1 0 10-2 0v1zM5.757 15.657a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM2 10a1 1 0 100-2H1a1 1 0 100 2h1zM5.757 4.343a1 1 0 00-1.414 1.414l.707.707a1 1 0 101.414-1.414l-.707-.707z"></path>
                </svg>
            </div>
        </div>

    </div>

    <!-- FOOTER: Last Updated -->
    <div class="px-5 py-3 bg-gray-50 border-t border-gray-200 text-center">
        <p class="text-xs text-gray-500">Last updated: <span class="font-semibold">Just now</span></p>
    </div>

</div>`;

deviceList.insertAdjacentHTML('beforeend', dHtml);
                // --- ADD DEVICE CURRENT LIST (Only add if new) ---
                const cdHtml = `
                    <div class="bg-white p-4 rounded-lg shadow-md flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center mr-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0118.657 17.657c-1.566 1.566-2.343 3.657-2.343 3.657-1-2-4-2.986-7-2.986 2.5.986 5 .5 7-2.986zM12 12a2 2 0 100-4 2 2 0 000 4z"></path></svg></div>
                            <div><p class="font-semibold text-gray-800">${device.sensor_type.toUpperCase()} Sensor</p><p class="text-sm text-gray-500">${statusText}</p></div>
                        </div>
                        <div class="w-2.5 h-2.5 ${isOnline ? 'bg-green-500' : 'bg-red-500'} rounded-full"></div>
                    </div>
                `;
                addDeviceList.insertAdjacentHTML('beforeend', cdHtml);
            }
        }
    } catch (error) {
        console.error('API Error: get_devices', error);
    }
};
          

        let nearestStation = null;

        // Add this new function to fetch nearest BFP station
        const fetchNearestStation = async (latitude, longitude) => {
            try {
                const response = await fetch(`${API_ENDPOINT}?action=get_nearest_station&latitude=${latitude}&longitude=${longitude}`);
                const json = await response.json();
                if (!json.success) throw new Error(json.message);
                
                nearestStation = json.data;
                updateEmergencyContacts();
                
            } catch (error) {
                console.error('Error fetching nearest station:', error);
                // Fallback to default contact
                nearestStation = {
                    station_name: 'BFP Emergency',
                    contact_number: '(02) 8426-0219',
                    distance_km: 'N/A'
                };
                updateEmergencyContacts();
            }
        };

        const initRealTimeStream = () => {
    console.log("Starting Real-Time Stream...");
    
    // Open a persistent connection to your PHP stream
    const evtSource = new EventSource(`${API_ENDPOINT}?action=dashboard_stream`);

    evtSource.addEventListener('update', (e) => {
        const data = JSON.parse(e.data);
        
        // 1. Update Alert Counts (from your summary data)
        if(document.getElementById('active-alerts-count')) {
             document.getElementById('active-alerts-count').innerText = data.summary.active_alerts;
        }

        // 2. Update Device Cards (Temperature, Gas, Status)
        if(data.devices && Array.isArray(data.devices)) {
            data.devices.forEach(device => {
                const sensorId = device.sensor_ID;
                
                // Parse numbers from strings (e.g., "TEMP 32.5" -> 32.5)
                const tempRaw = device.latest_temp || "0";
                const gasRaw = device.latest_gas || "0";
                
                const tempVal = parseFloat(tempRaw.replace(/[^\d.]/g, '')) || 0;
                const gasVal = parseInt(gasRaw.replace(/[^\d]/g, '')) || 0;

                // UPDATE THE DOM ELEMENTS
                // We use the specific IDs set in your fetchDeviceData HTML generation
                
                // Update Temperature
                const tempEl = document.getElementById(`device-temp-${sensorId}`);
                if(tempEl) {
                    tempEl.innerText = tempVal;
                    // Optional: Change color if high temp
                    tempEl.style.color = tempVal > 35 ? '#C82828' : '#1F2937'; 
                }

                // Update Gas/Smoke
                const gasEl = document.getElementById(`device-gas-${sensorId}`);
                if(gasEl) {
                    gasEl.innerText = gasVal;
                }

                // Update Fire Status & Icon Logic
                const statusEl = document.getElementById(`device-fire-status-${sensorId}`);
                if(statusEl) {
                    // Simple Logic: If Smoke > 300 OR Temp > 50 -> CRITICAL
                    // You can adjust these thresholds
                    let status = "SAFE";
                    let colorClass = "text-green-600";
                    
                    if(gasVal > 500 || tempVal > 50) {
                        status = "CRITICAL";
                        colorClass = "text-bfp-red";
                    } else if (gasVal > 300) {
                        status = "WARNING";
                        colorClass = "text-orange-500";
                    }
                    
                    statusEl.innerText = status;
                    statusEl.className = `font-bold text-lg ${colorClass}`;
                }
            });
        }
    });

    evtSource.onerror = (err) => {
        console.error("Stream connection lost. Retrying in 3s...", err);
        evtSource.close();
        setTimeout(initRealTimeStream, 3000); // Auto-reconnect
    };

};

const updateEmergencyContacts = () => {
    const select = document.getElementById('emergency-contact-select');
    
    if (select && select.options.length > 0) {
        // Just show the currently selected option
        const selectedOption = select.options[select.selectedIndex];
        console.log('Emergency contact updated:', selectedOption.value);
    }
};

        
const fetchLocationData = async () => {
    let defaultLat = 14.3313;
    let defaultLon = 120.9358;
    let primary = null;
    let locations = [];

    // --- Cleanup: Remove previous route line if it exists ---
    if (routingControl) {
        map.removeControl(routingControl);
        routingControl = null;
    }

    try {
        const response = await fetch(`${API_ENDPOINT}?action=get_location`);
        const json = await response.json();
        if (!json.success) throw new Error(json.message);

        primary = json.data.primary_info;
        locations = json.data.device_locations;

        // FIX 1: Explicitly parse coordinates to float immediately
        primary.latitude = primary.latitude ? parseFloat(primary.latitude) : null;
        primary.longitude = primary.longitude ? parseFloat(primary.longitude) : null;
        
        defaultLat = primary?.latitude || 14.3313;
        defaultLon = primary?.longitude || 120.9358;

    } catch (error) {
        console.error('API Error: get_location', error);
    }

     if (!map) {
        map = L.map('map').setView([defaultLat, defaultLon], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        
        // Map controls are now ENABLED by default (no disabling code needed)
        
    } else {
        map.invalidateSize(); 
    }
    
    map.eachLayer((layer) => { 
        if (layer instanceof L.Marker || layer.options.className === 'custom-bfp-icon') { 
            map.removeLayer(layer); 
        } 
    });

    let mainPropertyMarker = null; 
    
    // --- 1. Fetch nearest station and update local variable ---
    let nearestStationText = 'N/A';
    if (primary && primary.latitude !== null && primary.longitude !== null && !isNaN(primary.latitude)) {
        await fetchNearestStation(primary.latitude, primary.longitude); 
        nearestStationText = nearestStation ? `${nearestStation.station_name} (${nearestStation.distance_km} km)` : 'N/A';
    }


    // --- 2. Construct the HTML for the Popup and create the Marker ---
    const currentPermission = (primary?.permission_level || 'private').toUpperCase();
    const permissionColor = currentPermission === 'PUBLIC' ? '#C82828' : '#34A853'; // Use hex colors for inline CSS
    
    if (primary && primary.latitude !== null && primary.longitude !== null && !isNaN(primary.latitude)) {
        const lat = primary.latitude;
        const lon = primary.longitude;

        const popupContent = `
            <div style="font-family: sans-serif; font-size: 14px; padding: 5px;">
                <h4 style="margin: 0 0 5px; font-weight: bold; color: #333;">${primary.location_name || 'Property Location'}</h4>
                <p style="margin: 0;"><strong>Address:</strong> ${primary.address || 'N/A'}</p>
                <p style="margin: 0;"><strong>Coordinates:</strong> ${lat.toFixed(4)}&deg; N, ${lon.toFixed(4)}&deg; E</p>
                <p style="margin: 0;"><strong>Nearest BFP:</strong> ${nearestStationText}</p>
                <p style="margin: 5px 0 0;"><strong>Visibility:</strong> <span style="font-weight: 600; color: ${permissionColor};">${currentPermission}</span></p>
            </div>
        `;
        
        // 🛠️ NEW: Custom Icon for the Property Location (Device)
        const propertyIcon = L.divIcon({
            className: 'custom-property-icon',
            html: `<div style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">🏠</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });


        // Create marker, ensuring it is NOT draggable and using the new icon
        mainPropertyMarker = L.marker([lat, lon], { 
            draggable: false, 
            icon: propertyIcon 
        }).addTo(map) 
            .bindPopup(popupContent) 
            .openPopup(); 
        
        map.setView([lat, lon], 16);
        
        // --- ADD ROUTING CONTROL ---
        if (nearestStation && nearestStation.latitude && nearestStation.longitude) {
            const stationLat = parseFloat(nearestStation.latitude);
            const stationLon = parseFloat(nearestStation.longitude);
            
            if (!isNaN(stationLat) && !isNaN(stationLon)) {
                
                // Add the BFP station marker (custom icon to differentiate)
                const stationIcon = L.divIcon({
                    className: 'custom-bfp-icon',
                    html: `<div class="bg-bfp-red text-white p-1 rounded-full border-2 border-white shadow-lg flex items-center justify-center" style="width: 24px; height: 24px; font-size: 10px;">🚒</div>`,
                    iconSize: [24, 24],
                    iconAnchor: [12, 24],
                    popupAnchor: [0, -20]
                });

                L.marker([stationLat, stationLon], { icon: stationIcon }).addTo(map)
                    .bindPopup(`<strong>BFP Station:</strong> ${nearestStation.station_name}`);
                
                // Initialize Routing Control (Draws the road)
                routingControl = L.Routing.control({
                    waypoints: [
                        L.latLng(lat, lon), // Origin: Property
                        L.latLng(stationLat, stationLon) // Destination: BFP Station
                    ],
                    routeWhileDragging: false,
                    createMarker: () => null, // Prevents creating default route markers
                    router: L.Routing.osrmv1({
                        serviceUrl: 'https://router.project-osrm.org/route/v1' // Default OSRM endpoint
                    }),
                    lineOptions: {
                        styles: [{ color: '#C82828', weight: 4, opacity: 0.8 }] // Red route line
                    },
                    autoRoute: true,
                }).addTo(map);

                // Set map view to include both points
                const bounds = L.latLngBounds([L.latLng(lat, lon), L.latLng(stationLat, stationLon)]);
                map.fitBounds(bounds, { padding: [50, 50] }); 
            }
        }
    }


    // 🛠️ Update property info display using explicit checks for null/NaN
    propertyInfoEl.innerHTML = `
        <p><strong class="text-gray-800">Address:</strong> ${primary?.address || 'N/A'}</p>
        <p><strong class="text-gray-800">Coordinates:</strong> ${primary?.latitude !== null && !isNaN(primary.latitude) ? primary.latitude.toFixed(4) + '&deg; N' : 'N/A'}, ${primary?.longitude !== null && !isNaN(primary.longitude) ? primary.longitude.toFixed(4) + '&deg; E' : 'N/A'}</p>
        <p><strong class="text-gray-800">Nearest BFP Station:</strong> ${nearestStationText}</p>
        <p><strong class="text-gray-800">Visibility:</strong> <span class="font-semibold ${currentPermission === 'PUBLIC' ? 'text-bfp-red' : 'text-status-green'}">${currentPermission}</span></p>
    `;
};
// document.addEventListener('click', async (e) => {
//     // --- Existing Call Trigger Logic ---
//     if (e.target.closest('.call-trigger')) {
//         const btn = e.target.closest('.call-trigger');
//         // Get the selected option from dropdown
//         const select = document.getElementById('emergency-contact-select');
//         const selectedOption = select ? select.options[select.selectedIndex] : null;
        
//         const targetName = btn.dataset.device || (selectedOption ? selectedOption.value : 'Emergency Contact');
//         const targetNumber = btn.dataset.number || (selectedOption ? selectedOption.getAttribute('data-number') : '');
        
//         if (!targetNumber) {
//             alert('No phone number available for this contact.');
//             return;
//         }

//         const lat = btn.dataset.lat || (nearestStation ? nearestStation.latitude : 'N/A');
//         const lon = btn.dataset.lon || (nearestStation ? nearestStation.longitude : 'N/A');
//         const alertType = btn.dataset.type || 'Direct Call';

//         document.getElementById('calling-to-name').textContent = targetName;
//         document.getElementById('calling-to-number').textContent = targetNumber;
//         document.getElementById('call-details').innerHTML = `
//             <h3 class="font-bold mb-2">Emergency Details</h3>
//             <p><span class="opacity-80">Property:</span> ${targetName}</p>
//             <p><span class="opacity-80">Alert Type:</span> ${alertType}</p>
//             <p><span class="opacity-80">Coordinates:</span> ${lat} ${lat !== 'N/A' ? '° N' : ''}, ${lon} ${lon !== 'N/A' ? '° E' : ''}</p>
//             <p><span class="opacity-80">Nearest Station:</span> ${nearestStation ? nearestStation.station_name : 'N/A'}</p>
//         `;

//         try {
//             // Send call to Arduino gateway via api.php
//             const callResponse = await fetch(`${API_ENDPOINT}?action=call_sms`, {
//                 method: 'POST',
//                 headers: { 'Content-Type': 'application/json' },
//                 body: JSON.stringify({ 
//                     phone_number: targetNumber,
//                     action_type: 'call'
//                 })
//             });
//             const callJson = await callResponse.json();
            
//             if (callJson.status === 'success') {
//                 // Successfully sent call to gateway - remove the call UI and just log
//                 console.log('✓ Call initiated to:', targetNumber);
//                 alert(`Calling ${targetName}...`);
//             } else {
//                 throw new Error(callJson.message || 'Failed to send call');
//             }
//         } catch(error) {
//             alert('Failed to initiate call: ' + error.message);
//             console.error('Call error:', error);
//         }
//     }
    
//     // --- NEW: Resolve/Dismiss Alert Logic ---
//     if (e.target.closest('.resolve-alert-trigger') || e.target.closest('.dismiss-alert-trigger')) {
//         const btn = e.target.closest('button');
//         const incidentId = btn.dataset.incidentId;
//         const isBFPResolve = btn.classList.contains('resolve-alert-trigger');
        
//         let action = isBFPResolve ? 'resolve_alert' : 'dismiss_alert';
//         let method = isBFPResolve ? 'PUT' : 'POST'; 
        
//         if (!incidentId) {
//             alert("Error: Incident ID not found on button.");
//             return;
//         }

//         try {
//             // CRITICAL: The API is case-sensitive for keys, so we send both incident_ID (for PUT) 
//             // and incident_id (for POST) for maximum compatibility with the PHP logic.
//             const response = await fetch(`${API_ENDPOINT}?action=${action}`, {
//                 method: method,
//                 headers: { 'Content-Type': 'application/json' },
//                 body: JSON.stringify({ incident_ID: incidentId, incident_id: incidentId }) 
//             });
//             const json = await response.json();
            
//             if (json.success) {
//                 alert(`Incident ${incidentId} marked as resolved successfully.`);
//                 // Refresh data to update the screen
//                 fetchDashboardData();
//             } else {
//                 alert(`Failed to resolve incident: ${json.message}`);
//             }
//         } catch(error) {
//             console.error('Resolution/Dismissal Error:', error);
//             alert('Failed to communicate with server: ' + error.message);
//         }
//     }
// });

// --- CLICK HANDLERS (UPDATED CALL LOGIC) ---
            document.addEventListener('click', async (e) => {
                if (e.target.closest('.call-trigger')) {
                    const btn = e.target.closest('.call-trigger');
                    const select = document.getElementById('emergency-contact-select');
                    const selectedOption = select ? select.options[select.selectedIndex] : null;
                    
                    // Robust number retrieval
                    let targetNumber = btn.getAttribute('data-number');
                    const targetName = btn.getAttribute('data-station-name') || (selectedOption ? selectedOption.value : 'Emergency Contact');
                    
                    if (!targetNumber && selectedOption) {
                        targetNumber = selectedOption.getAttribute('data-number');
                    }
                    
                    if (!targetNumber) {
                        alert('No phone number available for this contact.');
                        return;
                    }

                    // --- 1. OPEN NATIVE DIALER (USER CALL) ---
                    // This tries to open the phone app on mobile/desktop
                    window.location.href = `tel:${targetNumber}`;

                    // --- 2. SHOW SYSTEM UI (SYSTEM TRACKING) ---
                    document.getElementById('calling-to-name').textContent = targetName;
                    document.getElementById('calling-to-number').textContent = targetNumber;
                    
                    callScreen.classList.remove('hidden');
                    callScreen.classList.add('flex');
                    
                    seconds = 0;
                    clearInterval(callTimerInterval);
                    callTimerInterval = setInterval(() => {
                        seconds++;
                        const min = String(Math.floor(seconds / 60)).padStart(2, '0');
                        const sec = String(seconds % 60).padStart(2, '0');
                        document.getElementById('call-timer').textContent = `${min}:${sec}`;
                    }, 1000);

                    // --- 3. NOTIFY API GATEWAY (SYSTEM CALL) ---
                    try {
                        const callResponse = await fetch(`${API_ENDPOINT}?action=call_sms`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                phone_number: targetNumber,
                                action_type: 'call'
                            })
                        });
                        console.log('System call notification sent to API');
                    } catch(error) {
                        console.error('Failed to notify system of call:', error);
                    }
                }
            });

// 🛠️ NEW FUNCTION: Local Search Handler
const handleLocationSearch = () => {
    const query = document.getElementById('location-search-input').value.trim();
    if (query.length < 3) {
        // Use an alert placeholder since we cannot use modal windows here easily
        alert('Please enter a longer search query.');
        return;
    }
    
    // --- Simulation of Geocoding Search ---
    
    if (map) {
        // Move map view to a simulated result near the Philippines for demonstration
        const searchLat = 14.5995; // Manila coordinates
        const searchLon = 120.9842; 
        
        map.setView([searchLat, searchLon], 14);
        
        // Optionally add a temporary marker for the search result
        // L.marker([searchLat, searchLon]).addTo(map).bindPopup(`Search Result: ${query}`).openPopup();
        
        alert(`Searching for: "${query}". Map moved to simulated location.`);
    } else {
        alert('Map is not initialized yet.');
    }
}


        const fetchProfileData = async () => {
             try {
                const response = await fetch(`${API_ENDPOINT}?action=get_profile_and_location`);
                const json = await response.json();
                if (!json.success) throw new Error(json.message);
                
                const user = json.data.user;
                const primary_location = json.data.primary_location;
                
                // 🛠️ FIX 3: Parse coordinates when loading profile data too
                const lat = primary_location.latitude ? parseFloat(primary_location.latitude) : '';
                const lon = primary_location.longitude ? parseFloat(primary_location.longitude) : '';

                document.getElementById('user-initials').textContent = user.first_name.charAt(0) + user.last_name.charAt(0);
                document.getElementById('user-full-name').textContent = user.first_name + ' ' + user.last_name;
                document.getElementById('user-role').textContent = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                
                document.getElementById('setting-full-name').value = user.first_name + ' ' + user.last_name;
                document.getElementById('setting-email').textContent = user.email;
                document.getElementById('setting-phone').value = user.phone_number;
                document.getElementById('setting-address').value = primary_location.address || 'N/A';
                document.getElementById('setting-permission-level').value = primary_location.permission_level || 'private';
                document.getElementById('setting-latitude').value = lat; // Set parsed value
                document.getElementById('setting-longitude').value = lon; // Set parsed value

            } catch (error) {
                console.error('API Error: get_profile_and_location', error);
            }
        };
        
        const fetchLocationsForDeviceForm = async () => {
            try {
                const response = await fetch(`${API_ENDPOINT}?action=get_my_locations`);
                const json = await response.json();
                if (!json.success) throw new Error(json.message);

                const locations = json.data;
                const locationSelect = document.getElementById('new-device-location-select');
                locationSelect.innerHTML = '<option value="">Select a Location</option>'; 

                locations.forEach(location => {
                    const option = document.createElement('option');
                    option.value = location.location_ID;
                    option.textContent = `${location.location_name} (${location.address.substring(0, 30)}...)`;
                    locationSelect.appendChild(option);
                });

            } catch (error) {
                error_response("Error fetching locations for device add.", 500);
                console.error('API Error: get_my_locations', error);
            }
        };
        
        const fetchUsersForDeviceForm = async () => {
            // Only show user selection if the logged-in user is BFP personnel
            if (currentUserRole !== 'bfp_assigned_at_desk' && currentUserRole !== 'bfp_officer') {
                document.getElementById('user-selection-container').classList.add('hidden');
                return; 
            }
            try {
                const response = await fetch(`${API_ENDPOINT}?action=get_users`);
                const json = await response.json();
                if (!json.success) throw new Error(json.message);

                const users = json.data;
                const userSelect = document.getElementById('new-device-user');
                userSelect.innerHTML = ''; 

                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.user_ID;
                    option.textContent = `${user.first_name} ${user.last_name} (${user.email})`;
                    userSelect.appendChild(option);
                });
                document.getElementById('user-selection-container').classList.remove('hidden');
            } catch (error) {
                console.error('API Error: get_users', error);
                document.getElementById('user-selection-container').classList.add('hidden');
            }
        };

        let apCheckInterval; // Interval for local AP check
        let deviceMacAddress = null; // Store the MAC address returned by the device AP

        const checkDeviceAP = (startPolling = false) => {
            const apStatusItem = document.getElementById('local-ap-status-item');
            const espSelect = document.getElementById('esp-unique-id');
            const submitBtn = document.querySelector('#add-device-form button[type="submit"]');

            if (!startPolling) {
                // Initial check for manual connection
                apStatusItem.querySelector('.flex-shrink-0').className = 'flex-shrink-0 w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 mr-3';
                apStatusItem.querySelector('.flex-shrink-0').textContent = '2';
                apStatusItem.querySelector('#local-ap-text').innerHTML = `<p class="font-medium text-gray-800">Awaiting Connection (192.168.4.1)</p><p class="text-sm text-red-600 font-bold">CRITICAL: Manually connect your phone/PC to the device's Wi-Fi network (e.g., BFP-SETUP-1234) in your OS settings.</p>`;
                espSelect.innerHTML = '<option value="">Awaiting Device Connection...</option>';
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }

            if (apCheckInterval) clearInterval(apCheckInterval);

            apCheckInterval = setInterval(async () => {
                try {
                    // 1. Attempt to reach the device's local configuration endpoint (192.168.4.1)
                    const response = await fetch('http://192.168.4.1/api/info', {
                        method: 'GET',
                        mode: 'cors',
                        // Set a low timeout because this should be immediate if the user is connected
                        signal: AbortSignal.timeout(3000) 
                    });

                    if (response.ok) {
                        const deviceInfo = await response.json();
                        
                        // SUCCESS: Device is reachable locally!
                        clearInterval(apCheckInterval);
                        deviceMacAddress = deviceInfo.mac || deviceInfo.id;
                        
                        // Update UI to Step 2 (green check)
                        apStatusItem.querySelector('.flex-shrink-0').className = 'flex-shrink-0 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-3';
                        apStatusItem.querySelector('.flex-shrink-0').textContent = '✔';
                        apStatusItem.querySelector('#local-ap-text').innerHTML = `<p class="font-bold text-green-700">Device Connected (192.168.4.1)</p><p class="text-sm text-gray-600">You are now connected to the device's setup network.</p>`;

                        // Populate MAC address and enable submit button
                        espSelect.innerHTML = `<option value="${deviceMacAddress}">Device MAC: ${deviceMacAddress}</option>`;
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');

                        // Update Step 3 status
                        document.getElementById('server-reg-status-item').querySelector('.flex-shrink-0').className = 'flex-shrink-0 w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 mr-3';

                    } else {
                        // Keep polling or show loading status
                        console.log('Local AP not reachable yet. Still polling...');
                    }
                } catch (error) {
                    // Error is expected when not connected to the device AP.
                    // Keep the UI as "Awaiting Connection" (Step 1)
                }
            }, 3000); // Poll every 3 seconds
        };


        document.getElementById('back-to-devices-btn').addEventListener('click', () => {
            document.getElementById('add-device-screen').classList.add('hidden');
            document.getElementById('add-device-screen').classList.remove('flex');
            document.getElementById('main-content').classList.remove('hidden');
            
            if (apCheckInterval) clearInterval(apCheckInterval);
            showScreen(SCREEN_IDS.DEVICES);
        });

        document.getElementById('add-device-screen-trigger').addEventListener('click', () => {
            document.getElementById('main-content').classList.add('hidden');
            document.getElementById('add-device-screen').classList.remove('hidden');
            document.getElementById('add-device-screen').classList.add('flex');
            
            fetchLocationsForDeviceForm();
            fetchUsersForDeviceForm();
            checkDeviceAP(true); // START AP POLLING
        });

        // --- ADD DEVICE FORM SUBMIT LOGIC (Handles Step 2 and 3) ---
        document.getElementById('add-device-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const locationId = document.getElementById('new-device-location-select').value;
            const deviceMac = document.getElementById('esp-unique-id').value;
            const deviceType = document.getElementById('new-device-type-select').value;
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            if (!deviceMac || !locationId) {
                 // Check if the device is not yet connected to its AP (MAC not set)
                 if (!deviceMac || deviceMac.startsWith('Awaiting')) {
                    alert("Please ensure your phone/PC is manually connected to the device's setup Wi-Fi (e.g., BFP-SETUP-1234) before continuing.");
                    return;
                }
                alert("Please select a location.");
                return;
            }

            if (apCheckInterval) clearInterval(apCheckInterval); // Stop AP polling

            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.textContent = '1. Sending WiFi Credentials...';

            try {
                // ** STEP 2: Send Home WiFi credentials to device at 192.168.4.1 **
                
                // CRITICAL: Since we cannot access real Wi-Fi, we prompt the user for credentials
                const ssid = prompt('Enter your HOME Wi-Fi Network Name (SSID):');
                if (!ssid) throw new Error('Setup cancelled by user.');
                const password = prompt(`Enter password for "${ssid}":`);
                if (!password) throw new Error('Setup cancelled by user.');

                // Send WiFi config to device at 192.168.4.1
                const configResponse = await fetch('http://192.168.4.1/api/config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ssid: ssid,
                        password: password,
                        server: window.location.hostname, // Server hostname from the browser
                        port: window.location.port || (window.location.protocol === 'https:' ? 443 : 80)
                    }),
                    mode: 'cors',
                    signal: AbortSignal.timeout(10000)
                });

                if (!configResponse.ok) {
                    throw new Error('Failed to send WiFi configuration to device at 192.168.4.1. Check your manual connection.');
                }
                
                // The device is now rebooting and trying to connect to the HOME network.
                document.getElementById('server-reg-status-item').querySelector('#server-reg-text').innerHTML = `<p class="font-bold text-blue-700">2. Device Connecting to Home Wi-Fi...</p><p class="text-sm">Wait for device to appear on the server.</p>`;
                submitBtn.textContent = '2. Waiting for Device Registration...';

                // ** STEP 3A: Wait for Device to Register on Main Server **
                await waitForDeviceRegistration(deviceMac, 60000);
                
                // ** STEP 3B: Register device details in our application database **

                const data = {
                    location_id: locationId,
                    esp_ip: deviceMac,
                    type: deviceType
                };
                
                const userSelectContainer = document.getElementById('user-selection-container');
                if (!userSelectContainer.classList.contains('hidden')) {
                    data.user_id = document.getElementById('new-device-user').value;
                }

                const dbResponse = await fetch(`${API_ENDPOINT}?action=add_device`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const json = await dbResponse.json();
                if (json.success) {
                    alert("Device Added Successfully and Registered on Server!");
                    
                    document.getElementById('server-reg-status-item').querySelector('.flex-shrink-0').className = 'flex-shrink-0 w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600 mr-3';
                    document.getElementById('server-reg-status-item').querySelector('.flex-shrink-0').textContent = '✔';
                    document.getElementById('server-reg-status-item').querySelector('#server-reg-text').innerHTML = `<p class="font-bold text-green-700">3. Registration Complete!</p><p class="text-sm">Device has been added to your monitor.</p>`;

                    // Delay before navigating back
                    setTimeout(() => document.getElementById('back-to-devices-btn').click(), 1500);
                    
                } else {
                    throw new Error(json.message || "Failed to register device details in application database.");
                }

            } catch (error) {
                console.error('Device setup error:', error);
                
                // Re-enable button and reset text
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.textContent = originalText;
                
                // Reset to AP check or show error
                document.getElementById('server-reg-status-item').querySelector('.flex-shrink-0').className = 'flex-shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center text-red-600 mr-3';
                document.getElementById('server-reg-status-item').querySelector('.flex-shrink-0').textContent = '!';
                document.getElementById('server-reg-status-item').querySelector('#server-reg-text').innerHTML = `<p class="font-bold text-red-700">Setup Failed</p><p class="text-sm">Error: ${error.message}</p>`;

                alert(`Device Setup Error: ${error.message}`);
            }
        });

        const waitForDeviceRegistration = (deviceMac, maxWait = 60000) => {
            return new Promise((resolve, reject) => {
                const startTime = Date.now();
                
                const checkInterval = setInterval(async () => {
                    const elapsed = Date.now() - startTime;
                    const remainingSeconds = Math.max(0, Math.ceil((maxWait - elapsed) / 1000));
                    
                    document.getElementById('server-reg-status-item').querySelector('#server-reg-text').innerHTML = `<p class="font-bold text-blue-700">2. Device Connecting to Home Wi-Fi...</p><p class="text-sm">Waiting for server (Timeout in ${remainingSeconds}s)</p>`;

                    try {
                        const response = await fetch(`${API_ENDPOINT}?action=check_device_registration&mac=${deviceMac}`);
                        const json = await response.json();

                        if (json.success && json.data && json.data.registered) {
                            clearInterval(checkInterval);
                            resolve(json.data);
                            return;
                        }

                        if (elapsed > maxWait) {
                            clearInterval(checkInterval);
                            reject(new Error('Device registration timeout on server. Please ensure the device connected to your home Wi-Fi and the MAC address is correct.'));
                        }
                    } catch (error) {
                        console.error('Registration check error:', error);
                    }
                }, 3000);
            });
        };

        const startCallTimer = () => {
            seconds = 0;
            if (callTimerInterval) clearInterval(callTimerInterval);
            const timerEl = document.getElementById('call-timer');
            
            callTimerInterval = setInterval(() => {
                seconds++;
                const min = String(Math.floor(seconds / 60)).padStart(2, '0');
                const sec = String(seconds % 60).padStart(2, '0');
                timerEl.textContent = `${min}:${sec}`;
            }, 1000);
        };
        // ================================================================
        // --- END OF CORE FUNCTIONS ---
        // ================================================================


// --- LOGIN/INITIALIZATION EXECUTION LOGIC ---
if (isLoggedIn) {
    loginScreen.classList.add('hidden');
    appContainer.classList.remove('hidden');
    appContainer.classList.add('flex');
    
    // 🚨 UPDATED: Fetch location and nearest station immediately upon login 
    // to populate the 'nearestStation' variable used on the home screen.
    fetchLocationData(); 
fetchAndPopulateBFPStations();
initRealTimeStream();
    showScreen(SCREEN_IDS.HOME); 
} else {
    loginScreen.classList.remove('hidden');
    loginScreen.style.display = 'flex';
    appContainer.classList.add('hidden');
}

        // --- EVENT LISTENERS ---
        
        // 🛠️ NEW: Location Search Event Listener
        document.getElementById('location-search-btn').addEventListener('click', handleLocationSearch);
        document.getElementById('location-search-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleLocationSearch();
            }
        });

        // === LOCAL LOGIN (AJAX POST) ===
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch(`${API_ENDPOINT}?action=login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const json = await response.json();

                if (json.success) {
                    window.location.reload(); 
                } else {
                    alert('Login Failed: ' + json.message);
                }
            } catch (error) {
                console.error('Login error:', error);
                alert('Server connection error. Cannot log in.');
            }
        });

        // Navigation 
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showScreen(e.currentTarget.dataset.screen);
            });
        });
        
//     document.addEventListener('click', async (e) => {
//     if (e.target.closest('.call-trigger')) {
//         const btn = e.target.closest('.call-trigger');
        
//         // Get the selected option from dropdown
//         const select = document.getElementById('emergency-contact-select');
//         const selectedOption = select.options[select.selectedIndex];
        
//         const targetName = btn.dataset.device || selectedOption.value;
//         const targetNumber = btn.dataset.number || selectedOption.getAttribute('data-number');
//         const lat = btn.dataset.lat || (nearestStation ? nearestStation.latitude : 'N/A');
//         const lon = btn.dataset.lon || (nearestStation ? nearestStation.longitude : 'N/A');
//         const alertType = btn.dataset.type || 'Direct Call';

//         document.getElementById('calling-to-name').textContent = targetName;
//         document.getElementById('calling-to-number').textContent = targetNumber;
//         document.getElementById('call-details').innerHTML = `
//             <h3 class="font-bold mb-2">Emergency Details</h3>
//             <p><span class="opacity-80">Property:</span> ${targetName}</p>
//             <p><span class="opacity-80">Alert Type:</span> ${alertType}</p>
//             <p><span class="opacity-80">Coordinates:</span> ${lat} ${lat !== 'N/A' ? '° N' : ''}, ${lon} ${lon !== 'N/A' ? '° E' : ''}</p>
//             <p><span class="opacity-80">Nearest Station:</span> ${nearestStation ? nearestStation.station_name : 'N/A'}</p>
//         `;

//         try {
//             const response = await fetch(`${API_ENDPOINT}?action=call_api`, {
//                 method: 'POST',
//                 headers: { 'Content-Type': 'application/json' },
//                 body: JSON.stringify({ 
//                     target_number: targetNumber, 
//                     location: targetName,
//                     station_name: nearestStation?.station_name 
//                 })
//             });
//             const json = await response.json();
//             if (!json.success) throw new Error(json.message);
            
//             callScreen.classList.remove('hidden');
//             callScreen.classList.add('flex');
//             startCallTimer(); 
//         } catch(error) {
//             alert('Failed to initiate call: ' + error.message);
//         }
//     }
// });

        document.getElementById('end-call-btn').addEventListener('click', () => {
            callScreen.classList.add('hidden');
            callScreen.classList.remove('flex');
            clearInterval(callTimerInterval);
        });
        
        // Profile Update Form
        profileUpdateForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const updatedProfile = {
                full_name: document.getElementById('setting-full-name').value,
                phone: document.getElementById('setting-phone').value,
                address: document.getElementById('setting-address').value,
                permission_level: document.getElementById('setting-permission-level').value,
                latitude: document.getElementById('setting-latitude').value,
                longitude: document.getElementById('setting-longitude').value
            };
            
            try {
                const response = await fetch(`${API_ENDPOINT}?action=update_profile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(updatedProfile)
                });
                
                const json = await response.json();
                if (!json.success) throw new Error(json.message);

                fetchProfileData();
                alert(json.data.message);
            } catch(error) {
                alert('Failed to update profile: ' + error.message);
            }
        });

        // --- INITIALIZATIONS ---
        const updateTime = () => {
            const now = new Date();
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
        };

        const startPolling = () => {
            setInterval(() => {
                if (!document.getElementById(SCREEN_IDS.HOME).classList.contains('hidden')) {
                    fetchDashboardData();
                }
            }, POLLING_INTERVAL);
        };

        updateTime();
        // setInterval(updateTime, 60000);
        // startPolling();
    });
    </script>
</body>
</html>