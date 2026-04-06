# Google Maps Integration - IoT Smart Fire Alarm Monitoring

Google Maps has been integrated into the Analytics page to display real-time **IoT smart fire alarm device locations** for BFP (Bureau of Fire Protection) monitoring.

## ✅ Current Setup

The API key `AIzaSyBCbqOiVzRHcDf3hWKDQGFUqfvvnSK6q1A` is already embedded in analytics.html (demo key).

## 🗺️ Features

- **Real-time Device Monitoring** - Shows all deployed smart fire alarm devices on the map
- **Device Status Indicators** - 🟢 Online (Green), 🔴 Offline (Red), 🟠 Inactive (Orange)
- **Interactive Info Windows** - Click device markers to see full details:
  - Device ID (e.g., BFP-001-TLY)
  - Device Type (Smoke Detector, Heat Detector, CO Detector)
  - User Address/Location
  - Serial Number
  - Installation Date
  - Last Active Time
- **Auto-fit Bounds** - Map automatically zooms to show all deployed devices
- **Quick Access** - Click "View Details" to go to device management page
- **Custom Styling** - Matches F.I.E.R.C.E design system

## 📍 How It Works

1. **On page load**, map initializes centered on Metro Manila (14.5994°N, 120.9842°E)
2. **Fetches all devices** from GraphQL API using authentication token
3. **Places markers** at device locations with color coding:
   - 🟢 **Green** = Online (active and monitoring)
   - 🔴 **Red** = Offline (requires attention)
   - 🟠 **Orange** = Inactive (not in use)
4. **Click markers** to view device details (ID, type, address, serial, last active)

## 🎯 Use Cases for BFP

- **Device Coverage Planning** - See which areas have smart fire alarm coverage
- **Maintenance Scheduling** - Identify offline devices needing maintenance
- **Emergency Response** - Know device locations during fire incidents
- **Asset Tracking** - Monitor deployment of all IoT fire detection devices
- **User Service** - Quickly locate customer's installed device address

## 🔧 For Production Use

Get your own Google Maps API key:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable **Maps JavaScript API**
4. Create an **API Key** credential
5. Replace the key in `analytics.html` (line 9):

```html
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY"></script>
```

## 🔐 API Restrictions (Important!)

The demo key has **IP restrictions** - only works on localhost. For production:

1. Go to API credentials
2. Click your API key
3. Set **Application restrictions**:
   - HTTP referrers (websites)
   - Add your domain: `https://yourapp.com/*`
4. Set **API restrictions**:
   - Maps JavaScript API

## 📝 Sample Devices Displayed

Three sample devices for demo (from database):

```
BFP-001-TLY | Smoke Detector   | Talisay Central Elementary | Online
BFP-002-TLY | Heat Detector    | City Hall Annex            | Online  
BFP-003-TLY | CO Detector      | Police Station             | Offline
```

To add real device coordinates from your database:

Update `locationCoords` object in the `displayDevicesOnMap()` function:

```javascript
const locationCoords = {
  'Talisay Central Elementary': { lat: 14.5505, lng: 120.9631 },
  'City Hall Annex': { lat: 14.5945, lng: 120.9842 },
  // Add more locations from your user addresses
};
```

## 🎨 Customization

### Change Marker Colors by Status
Edit status mapping in `displayDevicesOnMap()`:
```javascript
let markerColor = 'green';           // online = green
if (device.status === 'offline') markerColor = 'red';      // offline = red
if (device.status === 'inactive') markerColor = 'orange';  // inactive = orange
```

### Change Default Map Center
Update `defaultCenter` in `initMap()`:
```javascript
const defaultCenter = { lat: 14.5994, lng: 120.9842 }; // Change to your city
```

### Change Map Style
Modify the `styles` array in `initMap()` function for custom appearance.

## 📊 Data Flow

```
BFP Officer visits Analytics page
           ↓
       Checks authentication
           ↓
Initialize Google Map (Metro Manila center)
           ↓
Fetch all smart fire alarm devices from GraphQL API
(/graphql/devices)
           ↓
Display device markers on map by location
           ↓
Officer clicks marker → Info window shows device details
           ↓
Officer clicks "View Details" → Redirects to device.html
```

## ✨ Features for Future Enhancement

- [ ] **Real-time Updates** - WebSocket subscriptions for instant status changes
- [ ] **Heatmap Overlay** - Show device density/coverage across city
- [ ] **Search/Filter** - Find devices by status, type, location, serial number
- [ ] **Device History** - Click device to see maintenance/response history
- [ ] **Coverage Zones** - Draw service areas for BFP stations
- [ ] **Alert Notifications** - Highlight devices with recent fires/alerts
- [ ] **Geofencing** - Define safe vs high-risk zones on map
- [ ] **Device Clusters** - Group nearby devices on zoom-out
- [ ] **Export Reports** - Generate device location/status reports

## 🆘 Troubleshooting

**Map not showing**
- Check that Google Maps API key is valid
- Verify API key has **Maps JavaScript API** enabled
- Check browser console (DevTools → Console) for errors

**Devices not appearing**
- Verify authentication token is set (check localStorage)
- Confirm GraphQL API is running (`http://localhost:4000/graphql`)
- Make sure devices exist in database: run `npm run db:seed` in api folder
- Check that device locations match `locationCoords` keys

**"Quota exceeded" error**
- Google Maps API has usage limits
- Sign up for Google Cloud billing (first $200/month free)
- Implement caching to reduce API calls

**Wrong Coordinates**
- Update device coordinates in `locationCoords` object to match actual user addresses
- Consider using Geocoding API to convert addresses to coordinates

---

**F.I.E.R.C.E is now tracking all deployed smart fire alarm devices for BFP monitoring!** 🗺️📍🚒
