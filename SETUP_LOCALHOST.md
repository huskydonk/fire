# F.I.E.R.C.E - Complete Localhost Setup Guide

Complete step-by-step guide to set up and run the F.I.E.R.C.E fire station management system on your local machine for demo.

---

## 📋 Prerequisites

Make sure you have these installed:

- **Node.js 16+** - Download from https://nodejs.org/
  - Verify: Run `node --version` in terminal (should be v16.0.0+)
  - Verify: Run `npm --version` in terminal (should be 7.0.0+)

- **PostgreSQL 12+** - Download from https://www.postgresql.org/
  - Verify: Run `psql --version` in terminal
  - Default credentials: username `postgres`, password `Admin123`

- **Git** (optional) - Download from https://git-scm.com/

---

## 🚀 Quick Start (5 minutes)

### Step 1: Start PostgreSQL Service

**Windows:**
```powershell
# Open PowerShell as Administrator
Start-Service postgresql-x64-18
Get-Service postgresql-x64-18  # Verify it's running
```

**Mac:**
```bash
brew services start postgresql@14
```

**Linux:**
```bash
sudo systemctl start postgresql
```

### Step 2: Create Database

```bash
# Open terminal and connect to PostgreSQL
psql -U postgres

# Inside PostgreSQL prompt:
CREATE DATABASE fierce_db;
\q  # Exit PostgreSQL
```

### Step 3: Install Backend Dependencies

```bash
cd e:\Projects\Capstone\Web\fire\api
npm install
```

### Step 4: Initialize Database Schema

```bash
npm run db:init
```

### Step 5: Seed Sample Data

```bash
npm run db:seed
```

### Step 6: Start Backend Server

```bash
npm run dev
```

You should see:
```
✓ GraphQL Server running on http://localhost:4000
✓ Connected to database fierce_db
```

### Step 7: Serve Frontend (New Terminal Window)

**Option A: Using Python (Recommended)**
```bash
cd e:\Projects\Capstone\Web\fire\web\resources
python -m http.server 5500
```

**Option B: Using Node.js HTTP Server**
```bash
cd e:\Projects\Capstone\Web\fire\web\resources
npx http-server -p 5500
```

**Option C: Using VS Code Live Server**
- Open `index.html` in VS Code
- Right-click → "Open with Live Server"

### Step 8: Access the Application

Open your browser and navigate to:

```
http://localhost:5500/login.html
```

---

## 🔐 Login Credentials

Use these demo accounts to log in:

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `password123` |
| Officer | `officer1` | `password123` |

---

## 📁 Project Structure

```
e:\Projects\Capstone\Web\fire\
├── api/                          # Backend (GraphQL)
│   ├── node_modules/
│   ├── graphql/
│   │   ├── typeDefs.js          # GraphQL schema
│   │   └── resolvers.js         # GraphQL resolvers
│   ├── db/
│   │   ├── connection.js        # PostgreSQL connection
│   │   └── schema.sql           # Database schema
│   ├── scripts/
│   │   └── seedDb.js            # Database seeding script
│   ├── server.js                # Main Express server
│   ├── .env                     # Environment variables
│   ├── package.json
│   └── QUICKSTART.md
│
└── web/                          # Frontend (HTML/CSS/JS)
    └── resources/
        ├── index.html           # Dashboard
        ├── login.html           # Login page
        ├── analytics.html       # Analytics & maps
        ├── device.html          # Device management
        ├── transaction.html     # Transactions
        ├── settings.html        # User settings
        ├── usermanagement.html  # User management
        ├── assets/
        │   ├── apollo-client.js # GraphQL client
        │   └── bootstrap/       # Bootstrap CSS
        └── image/
```

---

## 🛠️ Detailed Backend Setup

### Check PostgreSQL Connection

```bash
psql -U postgres -h localhost
```

If you get an error like "password authentication failed":

**Windows:**
1. Open `C:\Program Files\PostgreSQL\18\data\pg_hba.conf`
2. Change first line from `trust` to `md5` or `scram-sha-256`
3. Restart PostgreSQL service

### Verify Database Created

```bash
psql -U postgres -d fierce_db
\dt  # List all tables
\q  # Exit
```

You should see 5 tables:
- `users`
- `devices`
- `transactions`
- `incidents`
- `analytics`

### Check Backend Environment Variables

Edit `e:\Projects\Capstone\Web\fire\api\.env`:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME=fierce_db
DB_USER=postgres
DB_PASSWORD=Admin123

# Server Configuration
PORT=4000
NODE_ENV=development

# JWT Secret (change in production)
JWT_SECRET=your_super_secret_jwt_key_change_in_production

# API Configuration
CORS_ORIGIN=http://localhost:5500
```

### Test GraphQL API

Backend should be running on `http://localhost:4000`

1. Open browser → `http://localhost:4000/graphql`
2. Click "Query your server" button
3. Paste this query:

```graphql
{
  devices {
    id
    deviceId
    location
    status
  }
}
```

4. Press Ctrl+Enter or click play button
5. You should see 3 sample devices

---

## 🌐 Detailed Frontend Setup

### Verify Frontend Files Exist

Check these files are in `e:\Projects\Capstone\Web\fire\web\resources\`:

```
✓ index.html
✓ login.html
✓ analytics.html
✓ device.html
✓ transaction.html
✓ settings.html
✓ usermanagement.html
✓ assets/apollo-client.js
✓ assets/bootstrap/css/bootstrap.min.css
```

### Start Web Server (Choose One Method)

**Method 1: Python HTTP Server (Recommended)**
```bash
cd e:\Projects\Capstone\Web\fire\web\resources
python -m http.server 5500
```

Expected output:
```
Serving HTTP on 0.0.0.0 port 5500 (http://0.0.0.0:5500/) ...
```

**Method 2: Node.js HTTP Server**
```bash
cd e:\Projects\Capstone\Web\fire\web\resources
npx http-server -p 5500
```

**Method 3: VS Code Live Server**
- Install extension: "Live Server" by Ritwick Dey
- Right-click `index.html` → "Open with Live Server"

### Access Application

1. Open browser
2. Go to `http://localhost:5500/login.html`
3. Log in with credentials above
4. You should see dashboard with real data

---

## ✅ Verification Checklist

### Backend Verification

- [ ] PostgreSQL service is running
- [ ] Database `fierce_db` created
- [ ] 5 tables exist in database
- [ ] Backend server running on port 4000
- [ ] GraphQL endpoint accessible at `http://localhost:4000/graphql`
- [ ] Sample data seeded (3 devices visible in query)
- [ ] No errors in terminal

### Frontend Verification

- [ ] Web server running on port 5500
- [ ] Login page loads at `http://localhost:5500/login.html`
- [ ] Can log in with admin/password123
- [ ] Dashboard shows real data from GraphQL
- [ ] All pages load without 404 errors
- [ ] Responsive design works on mobile view (F12 → toggle device toolbar)

### Data Flow Verification

- [ ] Login page communicates with backend
- [ ] Dashboard loads devices from GraphQL
- [ ] Analytics page shows Google Maps with device locations
- [ ] No CORS errors in browser console (F12 → Console tab)

---

## 🐛 Troubleshooting

### PostgreSQL Connection Failed

**Error:** `FATAL: Remaining connection slots are reserved for non-replication superuser connections`

**Solution:**
```powershell
# Kill existing connections
# Windows: Restart the service
Stop-Service postgresql-x64-18
Start-Service postgresql-x64-18
```

### Port Already in Use

**Error:** `Error: listen EADDRINUSE: address already in use :::4000`

**Solution (Windows PowerShell):**
```powershell
# Find process using port 4000
Get-Process | Where-Object { $_.ProcessName -match "node" }

# Kill the process
Stop-Process -Id <PID> -Force

# Or use netstat to find port:
netstat -ano | findstr :4000
taskkill /PID <PID> /F
```

**Solution (Mac/Linux):**
```bash
# Find process using port 4000
lsof -i :4000

# Kill the process
kill -9 <PID>
```

### Database Not Found

**Error:** `ENOENT: no such file or directory`

**Solution:**
```bash
cd e:\Projects\Capstone\Web\fire\api
npm run db:init
npm run db:seed
```

### CORS Error in Browser Console

**Error:** `Access to XMLHttpRequest blocked by CORS policy`

**Solution:**
Check `api/.env` has correct CORS_ORIGIN:
```env
CORS_ORIGIN=http://localhost:5500
```

Restart backend server after changing `.env`

### Apollo Client Connection Error

**Error:** `Network error: Unexpected end of JSON input`

**Solution:**
1. Verify backend running: `http://localhost:4000`
2. Check backend console for errors
3. Clear browser cache (Ctrl+Shift+Delete)
4. Reload page (Ctrl+F5)

### Google Maps Not Loading

**Error:** `Oops! Something went wrong` on analytics page

**Solution:**
- Map uses demo API key (IP-restricted to localhost)
- Works on localhost only
- For production, get own API key from Google Cloud Console
- See [GOOGLE_MAPS_GUIDE.md](web/resources/GOOGLE_MAPS_GUIDE.md)

### Table Data Not Showing

**Error:** Empty tables on device/user pages

**Solution:**
```bash
# Reseed database
npm run db:seed

# Check GraphQL query works
curl http://localhost:4000/graphql -X POST \
  -H "Content-Type: application/json" \
  -d '{"query": "{ devices { id deviceId } }"}'
```

---

## 📊 Testing the Demo

### Test 1: Login Flow
1. Open `http://localhost:5500/login.html`
2. Enter `admin` / `password123`
3. Click "Sign In"
4. Should redirect to dashboard
5. Token stored in browser localStorage

### Test 2: Dashboard Data
1. Dashboard should show:
   - Total Devices: 3
   - Total Incidents: 5
   - Device Status Chart
   - Device locations on map
2. All data from GraphQL API

### Test 3: Responsive Design
1. Open DevTools (F12)
2. Click device toolbar icon
3. Try different screen sizes:
   - iPhone 12 (390px)
   - iPad (768px)
   - Desktop (1920px)
4. Navigation should adapt

### Test 4: Navigation
1. Click menu items in topbar
2. Navigate to all pages:
   - Dashboard
   - Analytics (with map)
   - Devices
   - Transactions
   - Users
   - Settings

### Test 5: GraphQL Queries
1. Open `http://localhost:4000/graphql`
2. Test these queries:

```graphql
# Get all devices
{
  devices {
    id
    deviceId
    location
    status
  }
}

# Get all users
{
  users {
    id
    username
    role
  }
}

# Get current user
{
  me {
    username
    role
  }
}
```

---

## 📱 Mobile Testing

### Responsive Breakpoints

Test at these widths:
- **1024px+** - Desktop (full layout)
- **768px-1024px** - Tablet (hamburger menu)
- **576px-768px** - Mobile (card layouts)
- **< 576px** - Small phone (minimal layout)

### Using Chrome DevTools

1. Open DevTools (F12)
2. Click device toolbar: ![icon](https://devtools.chrome.com)
3. Choose device or custom size
4. Test all pages at different sizes

### Common Mobile Issues

- **Text too small?** Adjust breakpoint CSS at 576px in HTML files
- **Buttons not clickable?** Mobile touch area should be 44x44px minimum
- **Images overflow?** Check `max-width: 100%` on img elements

---

## 🚀 Running in Production

See [INTEGRATION_GUIDE.md](web/resources/INTEGRATION_GUIDE.md) for:
- Deploying to Azure
- Configuring production API keys
- Setting up HTTPS
- Database backups

---

## 📞 Support

### Common Questions

**Q: How do I change the password?**
A: Edit `api/scripts/seedDb.js` and change `password123`, then run `npm run db:seed` again

**Q: Can I add more sample data?**
A: Edit `api/scripts/seedDb.js` and add more INSERT statements, then run `npm run db:seed`

**Q: How do I backup my database?**
A: 
```bash
pg_dump -U postgres fierce_db > backup.sql
```

**Q: Can I run backend and frontend on different machines?**
A: Yes, update `CORS_ORIGIN` in `.env` to frontend's IP address

### Useful Commands

```bash
# Backend
npm run dev              # Start dev server
npm run db:init        # Create database schema
npm run db:seed        # Add sample data
npm start              # Production server

# Database
psql -U postgres       # Connect to PostgreSQL
createdb fierce_db     # Create database
dropdb fierce_db       # Delete database
pg_dump -U postgres fierce_db > backup.sql  # Backup

# Frontend
python -m http.server 5500    # Serve on port 5500
npx http-server -p 5500       # Alternative server
```

---

## 📚 Additional Resources

- **Backend API Docs:** [api/README.md](api/README.md)
- **Quick Start:** [api/QUICKSTART.md](api/QUICKSTART.md)
- **Integration Guide:** [web/resources/INTEGRATION_GUIDE.md](web/resources/INTEGRATION_GUIDE.md)
- **Google Maps Setup:** [web/resources/GOOGLE_MAPS_GUIDE.md](web/resources/GOOGLE_MAPS_GUIDE.md)

---

## ✨ Demo Features

### Included in This Setup

✅ **Authentication**
- Login with JWT tokens
- Role-based access (Admin/Officer)
- Session management

✅ **Dashboard**
- Real-time device status
- Statistics cards
- Charts and graphs
- Google Maps integration

✅ **Device Management**
- View all devices
- Device status indicators
- Location tracking
- Search and filter

✅ **Analytics**
- Interactive Google Maps
- Device locations with markers
- Device details on click
- Status-based coloring

✅ **Responsive Design**
- Works on all screen sizes
- Mobile-first approach
- Hamburger menu on mobile
- Touch-friendly interface

### Sample Data

**Users:**
- Admin account: `admin` / `password123`
- Officer account: `officer1` / `password123`

**Devices:**
- BFP-001-TLY (Talisay Central Elementary) - Online
- BFP-002-TLY (City Hall Annex) - Online
- BFP-003-TLY (Police Station) - Offline

**Transactions:**
- 2 sample transactions with different statuses

**Incidents:**
- 5 sample incidents with various severity levels

**Analytics:**
- Sample metrics for demonstration

---

## 🎯 Next Steps After Setup

1. **Test all features** - Use the checklist above
2. **Customize sample data** - Edit seed scripts
3. **Deploy to Azure** - Follow [INTEGRATION_GUIDE.md](web/resources/INTEGRATION_GUIDE.md)
4. **Add real data** - Connect to actual fire station data
5. **Customize styling** - Modify CSS for your branding

---

## 📝 Notes

- Default API runs on **http://localhost:4000**
- Default Web server runs on **http://localhost:5500**
- All passwords hashed with bcryptjs
- JWT tokens valid for 24 hours
- Database resets when you run `npm run db:seed`

---

**Last Updated:** April 2026  
**Version:** 1.0.0  
**Status:** Ready for Demo ✅
