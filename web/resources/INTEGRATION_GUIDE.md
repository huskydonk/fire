# Frontend-Backend Integration Complete ✅

Your F.I.E.R.C.E system is now fully integrated with GraphQL!

## 🚀 What's New

### New Files Created:
1. **assets/apollo-client.js** - Apollo Client configuration and helper functions
2. **login.html** - Authentication page for logging in users
3. **Updated index.html** - Now fetches data from GraphQL API

## 🔐 Authentication Flow

### 1. Login Page
- Navigate to: `http://localhost:5500/fire/web/resources/login.html` (or your local server)
- Demo credentials:
  - Username: `admin` | Password: `password123`
  - Username: `officer1` | Password: `password123`

### 2. Token Storage
- Token is stored in browser's `localStorage` as `authToken`
- Automatically sent with every API request in `Authorization` header

### 3. Dashboard Access
- After login, redirected to `index.html`
- Dashboard now loads real data from GraphQL API

## 📝 How It Works

### apollo-client.js Functions

**Make a GraphQL Query:**
```javascript
const data = await graphqlQuery(`
  query {
    devices {
      id
      deviceId
      location
      status
    }
  }
`);
```

**Make a GraphQL Mutation:**
```javascript
const result = await graphqlMutation(`
  mutation {
    createIncident(
      incidentId: "INC-001"
      location: "123 Main St"
      severity: "high"
      description: "Fire detected"
      reportedBy: "user-id"
    ) {
      id
      incidentId
      status
    }
  }
`);
```

**Manage Authentication:**
```javascript
setAuthToken(token);        // Save token
getAuthToken();             // Get token
clearAuthToken();           // Logout
```

## 🔄 Real-Time Data Updates

index.html now:
- ✅ Checks authentication on page load
- ✅ Fetches devices and incidents from API
- ✅ Updates UI with live data
- ✅ Supports logout functionality

## 📡 API Endpoints Available

All requests go to: `http://localhost:4000/graphql`

### Common Queries:
```graphql
query {
  me { id username role email }
  devices { id deviceId location status }
  incidents { id incidentId severity status }
  transactions { id txnId amount status }
  users { id username email fullName }
  analytics { id metricName metricValue metricDate }
}
```

### Common Mutations:
```graphql
mutation {
  login(username: "admin", password: "password123") { token }
  createDevice(deviceId: "D1", deviceType: "sensor", location: "Floor 1") { id }
  createIncident(incidentId: "I1", location: "Building A", severity: "high") { id }
  updateIncident(id: "uuid", status: "resolved") { id status }
}
```

## 🌐 CORS Configuration

Backend already has CORS enabled for localhost. If deploying:

Update `server.js` CORS settings:
```javascript
app.use(cors({
  origin: 'http://localhost:3000', // Your frontend URL
  credentials: true
}));
```

## 🧪 Testing the Integration

1. **Start backend**: Already running on `http://localhost:4000`
2. **Open login page**: Browser to `login.html`
3. **Login with**: admin / password123
4. **Check console**: Browser DevTools → Console to see API responses
5. **View dashboard**: Data should load automatically

## 📱 Update Other Pages

To add API integration to other pages (settings.html, device.html, etc.):

```html
<!-- At top of page, in <head> -->
<script src="assets/apollo-client.js"></script>

<!-- Before </body> -->
<script>
  async function loadPageData() {
    try {
      const data = await graphqlQuery(`
        query {
          devices { id deviceId location status }
        }
      `);
      // Update UI with data
    } catch (error) {
      console.error('Failed to load:', error);
    }
  }
  
  document.addEventListener('DOMContentLoaded', () => {
    if (!getAuthToken()) window.location.href = 'login.html';
    loadPageData();
  });
</script>
```

## 🛠️ Environment Setup

**Backend running**: ✅ http://localhost:4000
**PostgreSQL running**: ✅ Connected
**Frontend ready**: ✅ Ready to serve

## 📚 Next Steps

- [ ] Add logout button to top navigation
- [ ] Update all pages to use graphqlQuery()
- [ ] Add form submissions (create/update mutations)
- [ ] Implement real-time subscriptions (optional)
- [ ] Deploy to Azure Container Apps

## 🚨 Troubleshooting

**"Not authenticated" error**
- Check that you're logged in (visit login.html)
- Verify token in localStorage: `console.log(getAuthToken())`

**CORS errors**
- Ensure backend is running on http://localhost:4000
- Check that Authorization header is being sent

**Devices not loading**
- Check GraphQL Playground: http://localhost:4000/graphql
- Run test query to verify API responds

---

**You now have a full-stack F.I.E.R.C.E system!** 🎉
