/**
 * datebalazs.com — Local MySQL API server
 *
 * Setup:
 *   npm install express mysql2 cors
 *   node server.js
 */

const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(cors({ origin: '*' }));
app.use(express.json());

// ── DB config ──────────────────────────────────────────────
const DB = {
  host: 'localhost',
  user: 'datebala_techuser',
  password: '9Vu6V4Jma4DAW-l}',
  database: 'datebala_datebalazs',
};

const schemaSql = fs.readFileSync(path.join(__dirname, 'setup.sql'), 'utf8');
let pool;

async function initializeDatabase() {
  const initConn = await mysql.createConnection({
    host: DB.host,
    user: DB.user,
    password: DB.password,
    multipleStatements: true,
  });

  try {
    const statements = schemaSql
      .split(/;\s*\n/)
      .map((statement) => statement.trim())
      .filter(Boolean)
      .filter((statement) => !statement.startsWith('--'));

    for (const statement of statements) {
      await initConn.query(statement);
    }
  } finally {
    await initConn.end();
  }

  pool = await mysql.createPool(DB);
}

async function getPool() {
  if (!pool) {
    await initializeDatabase();
  }
  return pool;
}

// ── POST /api/event  (all interactions) ───────────────────
app.post('/api/event', async (req, res) => {
  try {
    const { type, data, ts } = req.body;
    const db = await getPool();

    await db.execute(
      'INSERT INTO events (type, data, ts) VALUES (?, ?, ?)',
      [type, JSON.stringify(data), ts]
    );

    if (type === 'contact') {
      const { instagram = '', facebook = '', whatsapp = '', secure_chat = '', bumble_profile = '', lang = '' } = data;
      const bumble = data.bumble ?? bumble_profile;
      await db.execute(
        'INSERT INTO contacts (instagram,facebook,whatsapp,secure_chat,bumble_profile,lang,ts) VALUES (?,?,?,?,?,?,?)',
        [instagram, facebook, whatsapp, secure_chat, bumble, lang, ts]
      );
    }

    if (type === 'review') {
      const { name = 'Anonymous', stars = 0, text = '', lang = '' } = data;
      await db.execute(
        'INSERT INTO reviews (name,stars,review,lang,ts) VALUES (?,?,?,?,?)',
        [name, stars, text, lang, ts]
      );
    }

    res.json({ ok: true });
  } catch (err) {
    console.error('[API]', err.message);
    res.status(500).json({ ok: false, error: err.message });
  }
});

// ── GET /api/reviews (load reviews from DB) ───────────────
app.get('/api/reviews', async (req, res) => {
  try {
    const db = await getPool();
    const [rows] = await db.execute(
      'SELECT name, stars, review AS text, ts FROM reviews ORDER BY ts DESC LIMIT 50'
    );
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// ── GET /api/contacts (view who said Yes) ────────────────
app.get('/api/contacts', async (req, res) => {
  try {
    const db = await getPool();
    const [rows] = await db.execute('SELECT * FROM contacts ORDER BY ts DESC');
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

async function start() {
  try {
    await initializeDatabase();
  } catch (err) {
    console.error('[DB]', err.message);
  }

  const PORT = 3001;
  app.listen(PORT, () => {
    console.log(`✅ datebalazs API running at http://localhost:${PORT}`);
    console.log('   POST /api/event     — log all interactions');
    console.log('   GET  /api/reviews   — list reviews');
    console.log('   GET  /api/contacts  — list contacts (who said Yes)');
  });
}

start();
