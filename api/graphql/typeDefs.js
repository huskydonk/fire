const { gql } = require('apollo-server-express');

const typeDefs = gql`
  type User {
    id: ID!
    username: String!
    email: String!
    fullName: String
    role: String!
    isActive: Boolean!
    createdAt: String!
    updatedAt: String!
  }

  type Device {
    id: ID!
    deviceId: String!
    deviceType: String!
    location: String!
    status: String!
    serialNumber: String
    installationDate: String
    lastActive: String
    createdAt: String!
    updatedAt: String!
  }

  type Transaction {
    id: ID!
    txnId: String!
    userId: ID!
    user: User
    txnType: String!
    amount: Float!
    status: String!
    txnDate: String!
    txnTime: String
    description: String
    createdAt: String!
    updatedAt: String!
  }

  type Incident {
    id: ID!
    incidentId: String!
    location: String!
    severity: String!
    status: String!
    description: String
    reportedBy: ID
    reportedByUser: User
    incidentDate: String!
    resolvedDate: String
    createdAt: String!
    updatedAt: String!
  }

  type Analytics {
    id: ID!
    metricName: String!
    metricValue: Int!
    metricDate: String!
    period: String
    createdAt: String!
  }

  type AuthPayload {
    token: String!
    user: User!
  }

  type Query {
    # User Queries
    me: User
    users: [User!]!
    userById(id: ID!): User

    # Device Queries
    devices: [Device!]!
    deviceById(id: ID!): Device
    deviceByDeviceId(deviceId: String!): Device
    devicesByStatus(status: String!): [Device!]!

    # Transaction Queries
    transactions: [Transaction!]!
    transactionById(id: ID!): Transaction
    transactionsByUser(userId: ID!): [Transaction!]!
    transactionsByStatus(status: String!): [Transaction!]!

    # Incident Queries
    incidents: [Incident!]!
    incidentById(id: ID!): Incident
    incidentsByStatus(status: String!): [Incident!]!
    incidentsBySeverity(severity: String!): [Incident!]!

    # Analytics Queries
    analytics(metricName: String, period: String): [Analytics!]!
    analyticsByDate(metricDate: String!): [Analytics!]!
  }

  type Mutation {
    # Authentication
    login(username: String!, password: String!): AuthPayload!
    register(username: String!, email: String!, password: String!, fullName: String!): AuthPayload!

    # User Mutations
    createUser(username: String!, email: String!, password: String!, fullName: String!, role: String!): User!
    updateUser(id: ID!, fullName: String, email: String): User!
    deleteUser(id: ID!): Boolean!

    # Device Mutations
    createDevice(deviceId: String!, deviceType: String!, location: String!, serialNumber: String): Device!
    updateDevice(id: ID!, location: String, status: String): Device!
    deleteDevice(id: ID!): Boolean!

    # Transaction Mutations
    createTransaction(txnId: String!, userId: ID!, txnType: String!, amount: Float!, txnDate: String!, description: String): Transaction!
    updateTransaction(id: ID!, status: String): Transaction!
    deleteTransaction(id: ID!): Boolean!

    # Incident Mutations
    createIncident(incidentId: String!, location: String!, severity: String!, description: String, reportedBy: ID!): Incident!
    updateIncident(id: ID!, status: String, severity: String, resolvedDate: String): Incident!
    deleteIncident(id: ID!): Boolean!

    # Analytics Mutations
    createAnalytic(metricName: String!, metricValue: Int!, metricDate: String!, period: String): Analytics!
  }
`;

module.exports = typeDefs;
