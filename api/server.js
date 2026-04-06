const express = require('express');
const { ApolloServer } = require('apollo-server-express');
const cors = require('cors');
require('dotenv').config();
const { pool } = require('./db/connection');
const { typeDefs, resolvers } = require('./graphql');
const auth = require('./middleware/auth');

const app = express();

// Middleware
app.use(cors());
app.use(express.json());
app.use(auth);

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ status: 'Server is running' });
});

// GraphQL Server
const startServer = async () => {
  const server = new ApolloServer({
    typeDefs,
    resolvers,
    context: ({ req }) => ({
      user: req.user,
      pool,
    }),
  });

  await server.start();
  server.applyMiddleware({ app });

  const PORT = process.env.PORT || 4000;
  app.listen(PORT, () => {
    console.log(`🚀 Server running at http://localhost:${PORT}/graphql`);
    console.log(`📊 GraphQL Playground available at http://localhost:${PORT}${server.graphqlPath}`);
  });
};

startServer().catch((err) => {
  console.error('Failed to start server:', err);
  process.exit(1);
});

module.exports = app;
