# F.I.E.R.C.E Backend API

GraphQL API for the F.I.E.R.C.E (Fire Incident Emergency Response & Coordination Engine) fire station management system.

## Setup Instructions

### Prerequisites
- Node.js 16+ (Download from https://nodejs.org/)
- PostgreSQL 12+ (Download from https://www.postgresql.org/)

### Step 1: Install Dependencies
```bash
cd api
npm install
```

### Step 2: Set Up PostgreSQL Database
```bash
# Create database
createdb fierce_db

# Update .env file with your PostgreSQL credentials
DB_HOST=localhost
DB_PORT=5432
DB_NAME=fierce_db
DB_USER=postgres
DB_PASSWORD=dc1f0d66f0954ec493e1eee43e9b2ab8
```

### Step 3: Initialize Database Schema
```bash
npm run db:init
```

### Step 4: Seed Sample Data
```bash
npm run db:seed
```

This will create test users:
- Username: `admin` | Password: `password123` (Admin role)
- Username: `officer1` | Password: `password123` (Officer role)

### Step 5: Start the Server
```bash
npm run dev
```

Server runs on `http://localhost:4000`
GraphQL Playground: `http://localhost:4000/graphql`

## API Examples

### Authentication

**Login:**
```graphql
mutation {
  login(username: "admin", password: "password123") {
    token
    user {
      id
      username
      email
      role
    }
  }
}
```

**Register:**
```graphql
mutation {
  register(
    username: "newuser"
    email: "user@example.com"
    password: "password123"
    fullName: "John Doe"
  ) {
    token
    user {
      id
      username
      email
    }
  }
}
```

### Users

**Get all users:**
```graphql
query {
  users {
    id
    username
    email
    fullName
    role
  }
}
```

**Get current user:**
```graphql
query {
  me {
    id
    username
    email
    fullName
  }
}
```

### Devices

**Get all devices:**
```graphql
query {
  devices {
    id
    deviceId
    deviceType
    location
    status
    serialNumber
    lastActive
  }
}
```

**Get devices by status:**
```graphql
query {
  devicesByStatus(status: "active") {
    id
    deviceId
    location
    status
  }
}
```

**Create device:**
```graphql
mutation {
  createDevice(
    deviceId: "DEV-001"
    deviceType: "smoke-detector"
    location: "Building A, Floor 1"
    serialNumber: "SN123456"
  ) {
    id
    deviceId
    status
  }
}
```

### Transactions

**Get all transactions:**
```graphql
query {
  transactions {
    id
    txnId
    txnType
    amount
    status
    txnDate
    description
  }
}
```

**Get transactions by user:**
```graphql
query {
  transactionsByUser(userId: "user-uuid") {
    id
    txnId
    amount
    status
  }
}
```

### Incidents

**Get all incidents:**
```graphql
query {
  incidents {
    id
    incidentId
    location
    severity
    status
    incidentDate
  }
}
```

**Get incidents by severity:**
```graphql
query {
  incidentsBySeverity(severity: "high") {
    id
    incidentId
    location
    description
  }
}
```

**Create incident:**
```graphql
mutation {
  createIncident(
    incidentId: "INC-001"
    location: "123 Main St"
    severity: "high"
    description: "Fire detected on second floor"
    reportedBy: "officer-uuid"
  ) {
    id
    incidentId
    status
  }
}
```

### Analytics

**Get analytics:**
```graphql
query {
  analytics(metricName: "incidents_today", period: "daily") {
    id
    metricName
    metricValue
    metricDate
  }
}
```

## Authentication

Include JWT token in the Authorization header:
```
Authorization: Bearer <your_token>
```

## Database Schema

- **users**: User accounts with authentication
- **devices**: Fire detection and safety devices
- **transactions**: Financial transactions
- **incidents**: Fire incidents and emergencies
- **analytics**: System metrics and statistics

## Available Scripts

- `npm start` - Start production server
- `npm run dev` - Start development server with nodemon
- `npm run db:init` - Initialize database schema
- `npm run db:seed` - Seed sample data

## Environment Variables

Create a `.env` file in the api folder:
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=fierce_db
DB_USER=postgres
DB_PASSWORD=password
JWT_SECRET=your_secret_key
NODE_ENV=development
PORT=4000
```

## License

MIT
