  // Realtime dashboard script: initial fetch + SSE (fallback to polling)
                    const API_URL = 'api_bfp.php';
                    let dashboardSSE = null;

                    async function updateDashboardFromAPI() {
                        try {
                            const res = await fetch(`${API_URL}?endpoint=dashboard`);
                            if (!res.ok) return console.warn('Dashboard API not ok', res.status);
                            const data = await res.json();
                            if (data.status !== 'success') return console.warn('Dashboard API error', data);

                            const m = data.metrics || {};
                            document.querySelector('[data-metric="total-users"]').textContent = m.total_users ?? (data.users ? data.users.length : 0);
                            document.querySelector('[data-metric="active-devices"]').textContent = m.active_devices ?? 0;
                            document.querySelector('[data-metric="active-alerts"]').textContent = m.active_alerts ?? 0;
                            document.querySelector('[data-metric="monthly-incidents"]').textContent = m.monthly_responses ?? m.monthly_responses ?? 0;

                            // Update top alert card
                            const topAreaTitle = document.querySelector('#content-dashboard .bg-red-600 + div h3') || document.querySelector('#content-dashboard h3');
                            const topAreaDesc = document.querySelector('#content-dashboard .bg-red-600 + div p') || null;
                            const topAreaTime = document.querySelector('#content-dashboard .bg-red-600 + div .text-xs');
                            const btn = document.querySelector('#content-dashboard button[onclick^="tapAlert"]');

                            if (data.activeIncidents && data.activeIncidents.length > 0) {
                                const inc = data.activeIncidents[0];
                                if (topAreaTitle) topAreaTitle.textContent = (inc.incident_type || 'ALERT') + ' #' + inc.incident_ID;
                                if (topAreaDesc) topAreaDesc.textContent = inc.address || '';
                                if (topAreaTime) topAreaTime.textContent = inc.start_timestamp || '';
                                if (btn) {
                                    btn.textContent = 'Tap Alert';
                                    btn.onclick = () => tapAlert(inc.incident_ID);
                                }
                            } else {
                                if (topAreaTitle) topAreaTitle.textContent = 'No Active Alerts';
                                if (topAreaDesc) topAreaDesc.textContent = '';
                                if (topAreaTime) topAreaTime.textContent = '';
                                if (btn) { btn.textContent = 'Tap Alert'; btn.onclick = () => alert('No active incident'); }
                            }
                            
                            // Re-render Active Devices with click handler
                            renderDeviceConnected(data.users || []);

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
                    
                    // NEW FUNCTION: Render Device Connected List
                    function renderDeviceConnected(users) {
                        const deviceListEl = document.getElementById('device-connected-list');
                        deviceListEl.innerHTML = '';
                        
                        // Filter users who have active devices and sort them
                        const activeUsers = users.filter(u => u.status_active_devices > 0);

                        activeUsers.forEach(user => {
                            const deviceCount = user.device_count || 0;
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
                                <div onclick="showDeviceDetails(${userId})" class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-3 rounded-lg flex items-center justify-between shadow-md cursor-pointer hover:from-blue-700 transition duration-150">
                                    <span class="text-sm font-medium">${title}</span>
                                    <div class="bg-white bg-opacity-30 w-8 h-8 rounded flex items-center justify-center">
                                        <span class="text-lg">${icon}</span>
                                    </div>
                                </div>
                            `;
                            deviceListEl.insertAdjacentHTML('beforeend', html);
                        });
                    }
                    
                    // NEW FUNCTION: Show Device Details Modal
                    async function showDeviceDetails(userId) {
                        try {
                            // 1. Fetch detailed device data from API
                            // This API endpoint needs to be implemented in api_bfp.php to return:
                            // { user: {name, phone}, location: {name, address}, sensors: [{sensor_id, type, status, latest_readings: {gas, temp}}] }
                            const res = await fetch(`${API_URL}?endpoint=device_details&user_id=${userId}`);
                            const data = await res.json();

                            if (data.status !== 'success' || !data.data) {
                                alert("Failed to load device details. Check API endpoint: /device_details");
                                return;
                            }
                            
                            const d = data.data;

                            // 2. Populate Modal
                            document.getElementById('deviceModalUser').textContent = d.user.name || 'N/A';
                            document.getElementById('deviceModalUserPhone').textContent = d.user.phone || 'N/A';
                            document.getElementById('deviceModalLocationName').textContent = d.location.name || 'N/A';
                            document.getElementById('deviceModalLocationAddress').textContent = d.location.address || 'N/A';

                            const sensorList = document.getElementById('deviceModalSensorList');
                            sensorList.innerHTML = '';
                            
                            d.sensors.forEach(s => {
                                const statusColor = s.status === 'ALERT' ? 'text-red-600' : 'text-green-600';
                                const readingsHtml = s.latest_readings 
                                    ? `<p class="text-xs text-gray-700">Gas: ${s.latest_readings.gas || '—'} | Temp: ${s.latest_readings.temp || '—'}°C</p>`
                                    : `<p class="text-xs text-gray-700">No recent readings.</p>`;

                                const item = `
                                    <div class="bg-gray-100 p-3 rounded-lg border-l-4 ${s.status === 'ALERT' ? 'border-red-600' : 'border-green-600'}">
                                        <div class="font-semibold text-sm">Sensor ID: ${s.sensor_ID} (${s.type})</div>
                                        <div class="text-xs ${statusColor} font-medium mb-1">Status: ${s.status}</div>
                                        ${readingsHtml}
                                    </div>
                                `;
                                sensorList.insertAdjacentHTML('beforeend', item);
                            });

                            // 3. Show Modal
                            document.getElementById('deviceDetailModal').classList.add('active');

                        } catch (e) {
                            alert("An error occurred while fetching device details.");
                            console.error('showDeviceDetails error:', e);
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

                    // Start
                    document.addEventListener('DOMContentLoaded', () => {
                        updateDashboardFromAPI();
                        initDashboardSSE();
                        // Fallback polling every 6s in case SSE fails
                        setInterval(updateDashboardFromAPI, 6000);
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
            if (!mapContainer || mapContainer._leaflet_id) return;
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
            if (bfpStationMapInstance) {
                bfpStationMapInstance.remove();
                bfpStationMapInstance = null;
            }
            
            bfpStationMapInstance = initMap('bfpStationMap', [lat, lon], 16);
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
                    alert('Deletion failed: ' + (data.message || 'Check API response.'));
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
                    alert(`${isUpdate ? 'Update' : 'Add'} failed: ` + (data.message || 'Check API response.'));
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
            btn.classList.remove('bg-red-600', 'text-white');
            btn.classList.add('text-gray-300');
        });
        
        buttonElement.classList.add('bg-red-600', 'text-white');
        buttonElement.classList.remove('text-gray-300', 'hover:bg-gray-800');
        
        // If the button is inside a dropdown, highlight the parent too
        const parentDropdown = buttonElement.closest('#dropdown-content');
        if (parentDropdown) {
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
            if (!bfpStationMapInstance) {
                initBfpStationsMap(); // Initialize the map on first load
            } else {
                bfpStationMapInstance.invalidateSize();
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
            if(!dropdown.querySelector('.bg-red-600')){ buttonElement.classList.toggle('bg-gray-700', dropdown.classList.contains('active')); }
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
        function showPasswordChangedModal() { document.getElementById('passwordChangedModal').classList.add('active'); }
        function exitApp() { if (confirm('Are you sure you want to Logout?')) { alert('Signing Out...'); } }

        window.addEventListener('load', function() {
            updateDateTime();
            setInterval(updateDateTime, 60000);
            
            mainMapInstance = initMap('map', [14.1044, 122.9442], 14);
            const fireIcon = L.divIcon({ className: 'custom-fire-marker', html: '<div style="font-size:32px; animation: pulse 1.5s infinite;">🔥</div>', iconSize: [40, 40], iconAnchor: [20, 20] });

            // Prefer to place the marker at the incident's real coordinates if available
            try {
                const inc = window.latestIncident || null;
                if (inc && inc.latitude && inc.longitude && !isNaN(parseFloat(inc.latitude)) && !isNaN(parseFloat(inc.longitude))) {
                    const lat = parseFloat(inc.latitude);
                    const lon = parseFloat(inc.longitude);
                    const marker = L.marker([lat, lon], { icon: fireIcon }).addTo(mainMapInstance);
                    const title = (inc.incident_level ? (inc.incident_level.charAt(0).toUpperCase() + inc.incident_level.slice(1)) : 'Alert') + ' - ' + (inc.sensor_type || 'Device');
                    const addr = inc.address || inc.location_name || 'Unknown location';
                    const coordsText = (lat.toFixed(6) + '°, ' + lon.toFixed(6) + '°');
                    marker.bindPopup(`<b>${title}</b><br>${addr}<br><small>${coordsText}</small>`).openPopup();
                    // center map on the incident
                    mainMapInstance.setView([lat, lon], 16);
                    // 1. REMOVED: L.circle([lat, lon], { color: 'red', fillColor: '#f03', fillOpacity: 0.15, radius: 200 }).addTo(mainMapInstance);
                } else {
                    // fallback: place a default marker at center
                    const marker = L.marker([14.1044, 122.9442], { icon: fireIcon }).addTo(mainMapInstance);
                    marker.bindPopup('<b>Fire Alert</b><br>No active incident').openPopup();
                }

                // Render all active devices as small markers
                try {
                    const devices = window.activeDevices || [];
                    const deviceIcon = L.divIcon({ className: 'device-marker', html: '<div style="width:18px;height:18px;border-radius:50%;background:#38bdf8;border:2px solid white;"></div>', iconSize: [18, 18], iconAnchor: [9, 9] });
                    window._deviceMarkers = window._deviceMarkers || [];
                    devices.forEach(d => {
                        try {
                            const lat = parseFloat(d.latitude);
                            const lon = parseFloat(d.longitude);
                            if (!isNaN(lat) && !isNaN(lon)) {
                                const m = L.marker([lat, lon], { icon: deviceIcon }).addTo(mainMapInstance);
                                const title = (d.location_name || d.address || 'Device');
                                const popup = `<b>${title}</b><br>${d.sensor_type || ''}<br>Sensor ID: ${d.sensor_ID}`;
                                m.bindPopup(popup);
                                window._deviceMarkers.push(m);
                            }
                        } catch (e) { /* ignore per-device errors */ }
                    });
                } catch (e) { console.error('render active devices error', e); }
            } catch (e) {
                const marker = L.marker([14.1044, 122.9442], { icon: fireIcon }).addTo(mainMapInstance);
                marker.bindPopup('<b>Fire Alert</b><br>Error loading incident').openPopup();
                console.error('Map incident placement error', e);
            }

            showContent('dashboard', document.querySelector('button[onclick*="dashboard"]'));
        });

function loadPage(page, buttonElement) {
    console.log("Attempting to load:", page); // Debugging

    const contentId = 'content-' + page.replace('.php', '').replace('.html', '');
    let contentArea = document.getElementById(contentId);

    // If content is already loaded, just show it
    if (contentArea) {
        showContent(contentId.replace('content-', ''), buttonElement);
        return;
    }

    // Fetch new content
    fetch(page)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            // Create the container div
            contentArea = document.createElement('div');
            contentArea.id = contentId;
            contentArea.className = 'content-section';
            contentArea.style.display = 'none'; // Hidden initially
            
            // Wrap content
            contentArea.innerHTML = `<div class="external-content-wrapper">${html}</div>`;
            
            // Append to Main
            document.querySelector('main').appendChild(contentArea);
            
            // Execute any <script> tags inside the loaded PHP file
            const scripts = contentArea.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
            });
            
            // Show the content using your helper function
            showContent(contentId.replace('content-', ''), buttonElement);
        })
        .catch(error => {
            console.error('Error loading page:', error);
            alert('Error loading page: ' + page + '. Check console for details.');
        });
}