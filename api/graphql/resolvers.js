const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { v4: uuidv4 } = require('uuid');

const resolvers = {
  Query: {
    // User Queries
    me: async (_, __, { user, pool }) => {
      if (!user) throw new Error('Not authenticated');
      const result = await pool.query('SELECT * FROM users WHERE id = $1', [user.id]);
      return result.rows[0] ? formatUser(result.rows[0]) : null;
    },

    users: async (_, __, { pool }) => {
      const result = await pool.query('SELECT * FROM users ORDER BY created_at DESC');
      return result.rows.map(formatUser);
    },

    userById: async (_, { id }, { pool }) => {
      const result = await pool.query('SELECT * FROM users WHERE id = $1', [id]);
      return result.rows[0] ? formatUser(result.rows[0]) : null;
    },

    // Device Queries
    devices: async (_, __, { pool }) => {
      const result = await pool.query('SELECT * FROM devices ORDER BY created_at DESC');
      return result.rows.map(formatDevice);
    },

    deviceById: async (_, { id }, { pool }) => {
      const result = await pool.query('SELECT * FROM devices WHERE id = $1', [id]);
      return result.rows[0] ? formatDevice(result.rows[0]) : null;
    },

    deviceByDeviceId: async (_, { deviceId }, { pool }) => {
      const result = await pool.query('SELECT * FROM devices WHERE device_id = $1', [deviceId]);
      return result.rows[0] ? formatDevice(result.rows[0]) : null;
    },

    devicesByStatus: async (_, { status }, { pool }) => {
      const result = await pool.query('SELECT * FROM devices WHERE status = $1 ORDER BY created_at DESC', [status]);
      return result.rows.map(formatDevice);
    },

    // Transaction Queries
    transactions: async (_, __, { pool }) => {
      const result = await pool.query('SELECT * FROM transactions ORDER BY txn_date DESC');
      return result.rows.map(formatTransaction);
    },

    transactionById: async (_, { id }, { pool }) => {
      const result = await pool.query('SELECT * FROM transactions WHERE id = $1', [id]);
      return result.rows[0] ? formatTransaction(result.rows[0]) : null;
    },

    transactionsByUser: async (_, { userId }, { pool }) => {
      const result = await pool.query('SELECT * FROM transactions WHERE user_id = $1 ORDER BY txn_date DESC', [userId]);
      return result.rows.map(formatTransaction);
    },

    transactionsByStatus: async (_, { status }, { pool }) => {
      const result = await pool.query('SELECT * FROM transactions WHERE status = $1 ORDER BY txn_date DESC', [status]);
      return result.rows.map(formatTransaction);
    },

    // Incident Queries
    incidents: async (_, __, { pool }) => {
      const result = await pool.query('SELECT * FROM incidents ORDER BY incident_date DESC');
      return result.rows.map(formatIncident);
    },

    incidentById: async (_, { id }, { pool }) => {
      const result = await pool.query('SELECT * FROM incidents WHERE id = $1', [id]);
      return result.rows[0] ? formatIncident(result.rows[0]) : null;
    },

    incidentsByStatus: async (_, { status }, { pool }) => {
      const result = await pool.query('SELECT * FROM incidents WHERE status = $1 ORDER BY incident_date DESC', [status]);
      return result.rows.map(formatIncident);
    },

    incidentsBySeverity: async (_, { severity }, { pool }) => {
      const result = await pool.query('SELECT * FROM incidents WHERE severity = $1 ORDER BY incident_date DESC', [severity]);
      return result.rows.map(formatIncident);
    },

    // Analytics Queries
    analytics: async (_, { metricName, period }, { pool }) => {
      let query = 'SELECT * FROM analytics WHERE 1=1';
      const params = [];

      if (metricName) {
        query += ' AND metric_name = $' + (params.length + 1);
        params.push(metricName);
      }

      if (period) {
        query += ' AND period = $' + (params.length + 1);
        params.push(period);
      }

      query += ' ORDER BY metric_date DESC';
      const result = await pool.query(query, params);
      return result.rows.map(formatAnalytic);
    },

    analyticsByDate: async (_, { metricDate }, { pool }) => {
      const result = await pool.query('SELECT * FROM analytics WHERE metric_date = $1 ORDER BY created_at DESC', [metricDate]);
      return result.rows.map(formatAnalytic);
    },
  },

  Mutation: {
    // Authentication
    login: async (_, { username, password }, { pool }) => {
      const result = await pool.query('SELECT * FROM users WHERE username = $1', [username]);
      if (result.rows.length === 0) {
        throw new Error('User not found');
      }

      const user = result.rows[0];
      const passwordMatch = await bcrypt.compare(password, user.password_hash);
      if (!passwordMatch) {
        throw new Error('Invalid password');
      }

      const token = jwt.sign({ id: user.id, username: user.username }, process.env.JWT_SECRET || 'secret');
      return { token, user: formatUser(user) };
    },

    register: async (_, { username, email, password, fullName }, { pool }) => {
      const hashedPassword = await bcrypt.hash(password, 10);
      const id = uuidv4();

      try {
        const result = await pool.query(
          'INSERT INTO users (id, username, email, password_hash, full_name, role) VALUES ($1, $2, $3, $4, $5, $6) RETURNING *',
          [id, username, email, hashedPassword, fullName, 'user']
        );

        const user = result.rows[0];
        const token = jwt.sign({ id: user.id, username: user.username }, process.env.JWT_SECRET || 'secret');
        return { token, user: formatUser(user) };
      } catch (err) {
        if (err.code === '23505') {
          throw new Error('Username or email already exists');
        }
        throw err;
      }
    },

    // User Mutations
    createUser: async (_, { username, email, password, fullName, role }, { pool }) => {
      const hashedPassword = await bcrypt.hash(password, 10);
      const id = uuidv4();

      const result = await pool.query(
        'INSERT INTO users (id, username, email, password_hash, full_name, role) VALUES ($1, $2, $3, $4, $5, $6) RETURNING *',
        [id, username, email, hashedPassword, fullName, role]
      );
      return formatUser(result.rows[0]);
    },

    updateUser: async (_, { id, fullName, email }, { pool }) => {
      let query = 'UPDATE users SET';
      const params = [];
      let paramCount = 1;

      if (fullName) {
        query += ` full_name = $${paramCount}`;
        params.push(fullName);
        paramCount++;
      }

      if (email) {
        query += (params.length ? ',' : '') + ` email = $${paramCount}`;
        params.push(email);
        paramCount++;
      }

      if (params.length === 0) return null;

      query += ` WHERE id = $${paramCount} RETURNING *`;
      params.push(id);

      const result = await pool.query(query, params);
      return result.rows[0] ? formatUser(result.rows[0]) : null;
    },

    deleteUser: async (_, { id }, { pool }) => {
      const result = await pool.query('DELETE FROM users WHERE id = $1', [id]);
      return result.rowCount > 0;
    },

    // Device Mutations
    createDevice: async (_, { deviceId, deviceType, location, serialNumber }, { pool }) => {
      const id = uuidv4();
      const result = await pool.query(
        'INSERT INTO devices (id, device_id, device_type, location, serial_number, installation_date) VALUES ($1, $2, $3, $4, $5, $6) RETURNING *',
        [id, deviceId, deviceType, location, serialNumber, new Date()]
      );
      return formatDevice(result.rows[0]);
    },

    updateDevice: async (_, { id, location, status }, { pool }) => {
      let query = 'UPDATE devices SET';
      const params = [];
      let paramCount = 1;

      if (location) {
        query += ` location = $${paramCount}`;
        params.push(location);
        paramCount++;
      }

      if (status) {
        query += (params.length ? ',' : '') + ` status = $${paramCount}`;
        params.push(status);
        paramCount++;
      }

      if (params.length === 0) return null;

      query += ` WHERE id = $${paramCount} RETURNING *`;
      params.push(id);

      const result = await pool.query(query, params);
      return result.rows[0] ? formatDevice(result.rows[0]) : null;
    },

    deleteDevice: async (_, { id }, { pool }) => {
      const result = await pool.query('DELETE FROM devices WHERE id = $1', [id]);
      return result.rowCount > 0;
    },

    // Transaction Mutations
    createTransaction: async (_, { txnId, userId, txnType, amount, txnDate, description }, { pool }) => {
      const id = uuidv4();
      const result = await pool.query(
        'INSERT INTO transactions (id, txn_id, user_id, txn_type, amount, status, txn_date, description) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING *',
        [id, txnId, userId, txnType, amount, 'pending', txnDate, description]
      );
      return formatTransaction(result.rows[0]);
    },

    updateTransaction: async (_, { id, status }, { pool }) => {
      const result = await pool.query('UPDATE transactions SET status = $1 WHERE id = $2 RETURNING *', [status, id]);
      return result.rows[0] ? formatTransaction(result.rows[0]) : null;
    },

    deleteTransaction: async (_, { id }, { pool }) => {
      const result = await pool.query('DELETE FROM transactions WHERE id = $1', [id]);
      return result.rowCount > 0;
    },

    // Incident Mutations
    createIncident: async (_, { incidentId, location, severity, description, reportedBy }, { pool }) => {
      const id = uuidv4();
      const result = await pool.query(
        'INSERT INTO incidents (id, incident_id, location, severity, status, description, reported_by, incident_date) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING *',
        [id, incidentId, location, severity, 'active', description, reportedBy, new Date()]
      );
      return formatIncident(result.rows[0]);
    },

    updateIncident: async (_, { id, status, severity, resolvedDate }, { pool }) => {
      let query = 'UPDATE incidents SET';
      const params = [];
      let paramCount = 1;

      if (status) {
        query += ` status = $${paramCount}`;
        params.push(status);
        paramCount++;
      }

      if (severity) {
        query += (params.length ? ',' : '') + ` severity = $${paramCount}`;
        params.push(severity);
        paramCount++;
      }

      if (resolvedDate) {
        query += (params.length ? ',' : '') + ` resolved_date = $${paramCount}`;
        params.push(resolvedDate);
        paramCount++;
      }

      if (params.length === 0) return null;

      query += ` WHERE id = $${paramCount} RETURNING *`;
      params.push(id);

      const result = await pool.query(query, params);
      return result.rows[0] ? formatIncident(result.rows[0]) : null;
    },

    deleteIncident: async (_, { id }, { pool }) => {
      const result = await pool.query('DELETE FROM incidents WHERE id = $1', [id]);
      return result.rowCount > 0;
    },

    // Analytics Mutations
    createAnalytic: async (_, { metricName, metricValue, metricDate, period }, { pool }) => {
      const id = uuidv4();
      const result = await pool.query(
        'INSERT INTO analytics (id, metric_name, metric_value, metric_date, period) VALUES ($1, $2, $3, $4, $5) RETURNING *',
        [id, metricName, metricValue, metricDate, period]
      );
      return formatAnalytic(result.rows[0]);
    },
  },

  Transaction: {
    user: async (parent, _, { pool }) => {
      if (!parent.user_id) return null;
      const result = await pool.query('SELECT * FROM users WHERE id = $1', [parent.user_id]);
      return result.rows[0] ? formatUser(result.rows[0]) : null;
    },
  },

  Incident: {
    reportedByUser: async (parent, _, { pool }) => {
      if (!parent.reported_by) return null;
      const result = await pool.query('SELECT * FROM users WHERE id = $1', [parent.reported_by]);
      return result.rows[0] ? formatUser(result.rows[0]) : null;
    },
  },
};

// Helper functions to format database responses
function formatUser(user) {
  return {
    id: user.id,
    username: user.username,
    email: user.email,
    fullName: user.full_name,
    role: user.role,
    isActive: user.is_active,
    createdAt: user.created_at.toISOString(),
    updatedAt: user.updated_at.toISOString(),
  };
}

function formatDevice(device) {
  return {
    id: device.id,
    deviceId: device.device_id,
    deviceType: device.device_type,
    location: device.location,
    status: device.status,
    serialNumber: device.serial_number,
    installationDate: device.installation_date ? device.installation_date.toISOString() : null,
    lastActive: device.last_active ? device.last_active.toISOString() : null,
    createdAt: device.created_at.toISOString(),
    updatedAt: device.updated_at.toISOString(),
  };
}

function formatTransaction(transaction) {
  return {
    id: transaction.id,
    txnId: transaction.txn_id,
    userId: transaction.user_id,
    txnType: transaction.txn_type,
    amount: parseFloat(transaction.amount),
    status: transaction.status,
    txnDate: transaction.txn_date.toISOString(),
    txnTime: transaction.txn_time,
    description: transaction.description,
    createdAt: transaction.created_at.toISOString(),
    updatedAt: transaction.updated_at.toISOString(),
  };
}

function formatIncident(incident) {
  return {
    id: incident.id,
    incidentId: incident.incident_id,
    location: incident.location,
    severity: incident.severity,
    status: incident.status,
    description: incident.description,
    reportedBy: incident.reported_by,
    incidentDate: incident.incident_date.toISOString(),
    resolvedDate: incident.resolved_date ? incident.resolved_date.toISOString() : null,
    createdAt: incident.created_at.toISOString(),
    updatedAt: incident.updated_at.toISOString(),
  };
}

function formatAnalytic(analytic) {
  return {
    id: analytic.id,
    metricName: analytic.metric_name,
    metricValue: analytic.metric_value,
    metricDate: analytic.metric_date.toISOString(),
    period: analytic.period,
    createdAt: analytic.created_at.toISOString(),
  };
}

module.exports = resolvers;
