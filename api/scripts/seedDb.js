const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');
const { pool } = require('../db/connection');
require('dotenv').config();

async function seedDatabase() {
  try {
    console.log('🌱 Seeding database with sample data...');

    // Hash password
    const hashedPassword = await bcrypt.hash('password123', 10);

    // Insert sample users
    const adminUser = await pool.query(
      `INSERT INTO users (id, username, email, password_hash, full_name, role, is_active)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [uuidv4(), 'admin', 'admin@fierce.local', hashedPassword, 'Admin User', 'admin', true]
    );
    console.log('✅ Admin user created');

    const regularUser = await pool.query(
      `INSERT INTO users (id, username, email, password_hash, full_name, role, is_active)
       VALUES ($1, $2, $3, $4, $5, $6, $7)
       RETURNING id`,
      [uuidv4(), 'officer1', 'officer1@fierce.local', hashedPassword, 'Officer One', 'officer', true]
    );
    console.log('✅ Officer user created');

    // Insert sample devices
    await pool.query(
      `INSERT INTO devices (id, device_id, device_type, location, status, serial_number, installation_date, last_active)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'BFP-001-TLY', 'Smoke Detector', 'Talisay Central Elementary', 'online', 'SN992834-X', '2024-01-15', new Date()]
    );

    await pool.query(
      `INSERT INTO devices (id, device_id, device_type, location, status, serial_number, installation_date, last_active)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'BFP-002-TLY', 'Heat Detector', 'City Hall Annex', 'online', 'SN992835-X', '2024-02-20', new Date()]
    );

    await pool.query(
      `INSERT INTO devices (id, device_id, device_type, location, status, serial_number, installation_date, last_active)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'BFP-003-TLY', 'CO Detector', 'Police Station', 'offline', 'SN992836-X', '2024-03-10', null]
    );
    console.log('✅ Sample devices created');

    // Insert sample transactions
    await pool.query(
      `INSERT INTO transactions (id, txn_id, user_id, txn_type, amount, status, txn_date, txn_time, description)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)`,
      [uuidv4(), 'TXN-001', adminUser.rows[0].id, 'Payment', 1250.00, 'completed', '2024-01-15', '10:30:00', 'Equipment purchase']
    );

    await pool.query(
      `INSERT INTO transactions (id, txn_id, user_id, txn_type, amount, status, txn_date, txn_time, description)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)`,
      [uuidv4(), 'TXN-002', regularUser.rows[0].id, 'Refund', 500.00, 'completed', '2024-01-15', '09:15:00', 'Maintenance fee refund']
    );

    await pool.query(
      `INSERT INTO transactions (id, txn_id, user_id, txn_type, amount, status, txn_date, txn_time, description)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)`,
      [uuidv4(), 'TXN-003', adminUser.rows[0].id, 'Transfer', 2000.00, 'pending', '2024-01-14', '15:45:00', 'Budget transfer']
    );
    console.log('✅ Sample transactions created');

    // Insert sample incidents
    await pool.query(
      `INSERT INTO incidents (id, incident_id, location, severity, status, description, reported_by, incident_date)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'INC-001', 'Talisay Central Elementary', 'high', 'resolved', 'Structure fire in main building', adminUser.rows[0].id, '2024-01-10 14:30:00']
    );

    await pool.query(
      `INSERT INTO incidents (id, incident_id, location, severity, status, description, reported_by, incident_date)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'INC-002', 'City Hall Annex', 'medium', 'active', 'Small electrical fire in office', regularUser.rows[0].id, '2024-01-15 09:20:00']
    );

    await pool.query(
      `INSERT INTO incidents (id, incident_id, location, severity, status, description, reported_by, incident_date)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)`,
      [uuidv4(), 'INC-003', 'Police Station', 'low', 'pending', 'False alarm - detector malfunction', adminUser.rows[0].id, '2024-01-16 11:00:00']
    );
    console.log('✅ Sample incidents created');

    // Insert sample analytics
    await pool.query(
      `INSERT INTO analytics (id, metric_name, metric_value, metric_date, period)
       VALUES ($1, $2, $3, $4, $5)`,
      [uuidv4(), 'Total Incidents', 45, '2024-01-16', 'daily']
    );

    await pool.query(
      `INSERT INTO analytics (id, metric_name, metric_value, metric_date, period)
       VALUES ($1, $2, $3, $4, $5)`,
      [uuidv4(), 'Devices Online', 24, '2024-01-16', 'daily']
    );

    await pool.query(
      `INSERT INTO analytics (id, metric_name, metric_value, metric_date, period)
       VALUES ($1, $2, $3, $4, $5)`,
      [uuidv4(), 'Response Time (min)', 8, '2024-01-16', 'daily']
    );

    await pool.query(
      `INSERT INTO analytics (id, metric_name, metric_value, metric_date, period)
       VALUES ($1, $2, $3, $4, $5)`,
      [uuidv4(), 'Active Users', 12, '2024-01-16', 'daily']
    );
    console.log('✅ Sample analytics created');

    console.log('\n✅ Database seeding completed successfully!');
    console.log('\n📝 Sample Credentials:');
    console.log('   Admin: admin / password123');
    console.log('   Officer: officer1 / password123');
    
    process.exit(0);
  } catch (err) {
    console.error('❌ Error seeding database:', err);
    process.exit(1);
  }
}

seedDatabase();
