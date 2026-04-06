const fs = require('fs');
const path = require('path');
const { pool } = require('../db/connection');
require('dotenv').config();

async function initializeDatabase() {
  try {
    console.log('🔄 Initializing database...');

    // Read schema file
    const schemaPath = path.join(__dirname, '../db/schema.sql');
    const schema = fs.readFileSync(schemaPath, 'utf8');

    // Execute schema
    await pool.query(schema);

    console.log('✅ Database schema created successfully!');
    console.log('📊 Tables created: users, devices, transactions, incidents, analytics');
    
    process.exit(0);
  } catch (err) {
    console.error('❌ Error initializing database:', err);
    process.exit(1);
  }
}

initializeDatabase();
