import * as SQLite from 'expo-sqlite';

let databasePromise: Promise<SQLite.SQLiteDatabase> | undefined;

export function initializeDatabase(): Promise<SQLite.SQLiteDatabase> {
  databasePromise ??= SQLite.openDatabaseAsync('app-cobranzas.db')
    .then(async (database) => {
      await database.execAsync(
        'CREATE TABLE IF NOT EXISTS app_metadata (key TEXT PRIMARY KEY NOT NULL, value TEXT NOT NULL)',
      );
      await database.execAsync(`CREATE TABLE IF NOT EXISTS offline_outbox (
        operation_id TEXT PRIMARY KEY NOT NULL, tenant_id TEXT NOT NULL, user_id TEXT NOT NULL,
        device_id TEXT NOT NULL, type TEXT NOT NULL, payload TEXT NOT NULL, recorded_at TEXT NOT NULL,
        status TEXT NOT NULL, attempt_count INTEGER NOT NULL DEFAULT 0, last_error TEXT, synced_at TEXT
      )`);
      try {
        await database.execAsync('ALTER TABLE offline_outbox ADD COLUMN next_attempt_at TEXT');
      } catch {}
      await database.execAsync(`CREATE TABLE IF NOT EXISTS authorized_obligations (
        scope_key TEXT NOT NULL, id TEXT NOT NULL, customer_id TEXT NOT NULL, outstanding_amount TEXT NOT NULL,
        due_date TEXT, entry_type TEXT, PRIMARY KEY (scope_key, id)
      )`);
      try {
        await database.execAsync('ALTER TABLE authorized_obligations ADD COLUMN entry_type TEXT');
      } catch {}
      await database.execAsync(`CREATE TABLE IF NOT EXISTS authorized_customers (
        scope_key TEXT NOT NULL, id TEXT NOT NULL, name TEXT NOT NULL, status TEXT NOT NULL, updated_at TEXT,
        PRIMARY KEY (scope_key, id)
      )`);
      for (const column of ['collector_name TEXT', 'zone_name TEXT', 'assignment_status TEXT']) {
        try {
          await database.execAsync(`ALTER TABLE authorized_customers ADD COLUMN ${column}`);
        } catch {}
      }
      await database.execAsync(`CREATE TABLE IF NOT EXISTS local_payments (
        operation_id TEXT PRIMARY KEY NOT NULL, scope_key TEXT NOT NULL, customer_id TEXT NOT NULL,
        amount TEXT NOT NULL, status TEXT NOT NULL, payment_id TEXT, recorded_at TEXT NOT NULL, result TEXT
      )`);
      await database.execAsync(`CREATE TABLE IF NOT EXISTS authorized_route_stops (
        scope_key TEXT NOT NULL, id TEXT NOT NULL, customer_id TEXT NOT NULL, customer_name TEXT NOT NULL,
        sequence INTEGER NOT NULL, status TEXT NOT NULL, address TEXT, due_amount_cents TEXT,
        PRIMARY KEY (scope_key, id)
      )`);
      await database.execAsync(`CREATE TABLE IF NOT EXISTS authorized_routes (
        scope_key TEXT PRIMARY KEY NOT NULL, id TEXT NOT NULL, status TEXT NOT NULL, route_date TEXT NOT NULL
      )`);
      return database;
    })
    .catch((error: unknown) => {
      databasePromise = undefined;
      throw error;
    });
  return databasePromise;
}

export async function getMetadata(key: string): Promise<string | null> {
  const database = await initializeDatabase();
  const row = await database.getFirstAsync<{ value: string }>(
    'SELECT value FROM app_metadata WHERE key = ?',
    key,
  );
  return row?.value ?? null;
}

export async function setMetadata(key: string, value: string): Promise<void> {
  const database = await initializeDatabase();
  await database.runAsync(
    'INSERT OR REPLACE INTO app_metadata (key, value) VALUES (?, ?)',
    key,
    value,
  );
}
