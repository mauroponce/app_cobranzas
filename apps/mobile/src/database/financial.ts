import { initializeDatabase } from './database';

export type LocalCustomer = {
  id: string;
  name: string;
  status: string;
  collector_name?: string | null;
  zone_name?: string | null;
  assignment_status?: string | null;
};
export type LocalObligation = {
  id: string;
  customer_id: string;
  outstanding_amount: string;
  due_date: string | null;
  entry_type: string;
};
export type LocalPayment = {
  operation_id: string;
  customer_id: string;
  amount: string;
  status: string;
  recorded_at: string;
  payment_id: string | null;
};
export type LocalRouteStop = {
  id: string;
  customer_id: string;
  customer_name: string;
  sequence: number;
  status: string;
  address: string | null;
  due_amount_cents: string | null;
};
export const scopeKey = (tenantId: string, userId: string) => `${tenantId}:${userId}`;

export async function customers(scope: string): Promise<LocalCustomer[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalCustomer>(
    'SELECT id, name, status, collector_name, zone_name, assignment_status FROM authorized_customers WHERE scope_key = ? ORDER BY name',
    scope,
  );
}
export async function obligations(scope: string, customerId: string): Promise<LocalObligation[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalObligation>(
    'SELECT id, customer_id, outstanding_amount, due_date, entry_type FROM authorized_obligations WHERE scope_key = ? AND customer_id = ? ORDER BY due_date',
    scope,
    customerId,
  );
}
export async function payments(scope: string): Promise<LocalPayment[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalPayment>(
    'SELECT operation_id, customer_id, amount, status, recorded_at, payment_id FROM local_payments WHERE scope_key = ? ORDER BY recorded_at DESC',
    scope,
  );
}
export async function routeStops(scope: string): Promise<LocalRouteStop[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalRouteStop>(
    'SELECT id, customer_id, customer_name, sequence, status, address, due_amount_cents FROM authorized_route_stops WHERE scope_key = ? ORDER BY sequence',
    scope,
  );
}
export async function pendingCount(scope: string): Promise<number> {
  const db = await initializeDatabase();
  const row = await db.getFirstAsync<{ count: number }>(
    "SELECT COUNT(*) AS count FROM offline_outbox WHERE tenant_id || ':' || user_id = ? AND status IN ('pending', 'retryable')",
    scope,
  );
  return row?.count ?? 0;
}
export async function syncInfo(
  scope: string,
): Promise<{ lastSuccess: string | null; lastError: string | null }> {
  const db = await initializeDatabase();
  const success = await db.getFirstAsync<{ value: string }>(
    'SELECT value FROM app_metadata WHERE key = ?',
    `sync:last_success:${scope}`,
  );
  const error = await db.getFirstAsync<{ last_error: string }>(
    "SELECT last_error FROM offline_outbox WHERE tenant_id || ':' || user_id = ? AND last_error IS NOT NULL ORDER BY recorded_at DESC LIMIT 1",
    scope,
  );
  return { lastSuccess: success?.value ?? null, lastError: error?.last_error ?? null };
}
