import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';

// Tiny persistent store whose only job is idempotency: WooCommerce fires
// `order.updated` on every little change, so without this we'd re-text a
// customer every time. One row per (order, event) we've already sent.
export function createStore(dbPath) {
  const dir = path.dirname(dbPath);
  if (dir && dir !== '.' && !fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }

  const db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.exec(`
    CREATE TABLE IF NOT EXISTS sent_notifications (
      order_id TEXT NOT NULL,
      event    TEXT NOT NULL,
      to_phone TEXT,
      sid      TEXT,
      sent_at  TEXT NOT NULL,
      PRIMARY KEY (order_id, event)
    );
  `);

  const insertStmt = db.prepare(
    `INSERT OR IGNORE INTO sent_notifications (order_id, event, sent_at)
     VALUES (?, ?, ?)`,
  );
  const recordStmt = db.prepare(
    `UPDATE sent_notifications SET to_phone = ?, sid = ? WHERE order_id = ? AND event = ?`,
  );

  return {
    // Atomically claim an (order, event). Returns true if this call is the
    // first to claim it (i.e. we should send), false if already sent/claimed.
    claim(orderId, event) {
      const result = insertStmt.run(String(orderId), event, new Date().toISOString());
      return result.changes === 1;
    },

    // Attach delivery details after a successful send, for auditability.
    record(orderId, event, toPhone, sid) {
      recordStmt.run(toPhone || null, sid || null, String(orderId), event);
    },

    // Release a claim so a later webhook can retry (used when a send fails).
    release(orderId, event) {
      db.prepare(
        `DELETE FROM sent_notifications WHERE order_id = ? AND event = ?`,
      ).run(String(orderId), event);
    },

    close() {
      db.close();
    },
  };
}
