import * as SQLite from 'expo-sqlite';

let databasePromise: Promise<SQLite.SQLiteDatabase> | undefined;

export function initializeDatabase(): Promise<SQLite.SQLiteDatabase> {
  databasePromise ??= SQLite.openDatabaseAsync('app-cobranzas.db')
    .then(async (database) => {
      await database.execAsync(
        'CREATE TABLE IF NOT EXISTS app_metadata (key TEXT PRIMARY KEY NOT NULL, value TEXT NOT NULL)',
      );
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
