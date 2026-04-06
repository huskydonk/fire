# F.I.E.R.C.E Backend - Quick Start Guide

Get the GraphQL API running in 5 minutes.

## Prerequisites
- Node.js 16+ installed
- PostgreSQL 12+ running locally

## 1. Install & Setup (2 minutes)

```bash
cd api
npm install
```

Create a PostgreSQL database:
```bash
createdb fierce_db
```

Update `.env` with your PostgreSQL credentials:
```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=fierce_db
DB_USER=postgres
DB_PASSWORD=dc1f0d66f0954ec493e1eee43e9b2ab8
JWT_SECRET=your_secret_key
PORT=4000
```

## 2. Initialize Database (1 minute)

```bash
npm run db:init      # Create tables and schema
npm run db:seed      # Add sample data
```

## 3. Start the Server (1 minute)

```bash
npm run dev
```

Server runs on `http://localhost:4000`
GraphQL Playground: `http://localhost:4000/graphql`

## 4. First Test Query (1 minute)

Open GraphQL Playground and paste this:

```graphql
query {
  devices {
    id
    deviceId
    location
    status
  }
}
```

Press play. You should see 3 sample devices.

## Test Users

Login credentials for testing:
- **Username**: `admin` | **Password**: `password123`
- **Username**: `officer1` | **Password**: `password123`

Try this mutation to login:
```graphql
mutation {
  login(username: "admin", password: "password123") {
    token
    user {
      username
      role
    }
  }
}
```

Copy the token and add to headers in GraphQL Playground:
```json
{
  "Authorization": "Bearer <your_token>"
}
```

## Common Commands

| Command | What it does |
|---------|-------------|
| `npm run dev` | Start dev server (with auto-reload) |
| `npm start` | Start production server |
| `npm run db:init` | Recreate database schema |
| `npm run db:seed` | Add sample data |

## Troubleshooting

**"Could not connect to database"**
- Make sure PostgreSQL is running
- Check `.env` credentials

**"Port 4000 already in use"**
- Change PORT in `.env` or kill existing process

**"Module not found"**
- Run `npm install` again

## Next Steps

- View full API docs in [README.md](./README.md)
- Connect frontend with Apollo Client
- Add more test data with GraphQL mutations
- Deploy to Azure Container Apps

---

Need help? Check the [README.md](./README.md) for comprehensive API documentation.
