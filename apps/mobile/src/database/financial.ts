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
  sale_id: string | null;
  outstanding_amount: string;
  due_date: string | null;
  entry_type: string;
};
export type LocalPayment = {
  operation_id: string;
  customer_id: string;
  sale_id: string | null;
  amount: string;
  status: string;
  recorded_at: string;
  payment_id: string | null;
  result: string | null;
};
export type LocalRouteStop = {
  id: string;
  customer_id: string;
  customer_name: string;
  sequence: number;
  status: string;
  outcome: string | null;
  address: string | null;
  sale_due_amount_cents: string | null;
  customer_total_outstanding_cents: string | null;
  available_obligation_ids: string | null;
};
export type LocalRoute = {
  id: string;
  status: string;
  route_date: string;
  settlement_id: string | null;
  settlement_status: string | null;
  declared_cash_cents: string | null;
  received_cash_cents: string | null;
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
    'SELECT id, customer_id, sale_id, outstanding_amount, due_date, entry_type FROM authorized_obligations WHERE scope_key = ? AND customer_id = ? ORDER BY due_date',
    scope,
    customerId,
  );
}
export async function payments(scope: string): Promise<LocalPayment[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalPayment>(
    'SELECT operation_id, customer_id, amount, status, recorded_at, payment_id, result FROM local_payments WHERE scope_key = ? ORDER BY recorded_at DESC',
    scope,
  );
}
export async function routeStops(scope: string): Promise<LocalRouteStop[]> {
  const db = await initializeDatabase();
  return db.getAllAsync<LocalRouteStop>(
    'SELECT id, customer_id, sale_id, customer_name, sequence, status, outcome, address, sale_due_amount_cents, customer_total_outstanding_cents, available_obligation_ids FROM authorized_route_stops WHERE scope_key = ? ORDER BY sequence',
    scope,
  );
}
export async function route(scope: string): Promise<LocalRoute | null> {
  const db = await initializeDatabase();
  return db.getFirstAsync<LocalRoute>(
    'SELECT id, status, route_date, settlement_id, settlement_status, declared_cash_cents, received_cash_cents FROM authorized_routes WHERE scope_key = ?',
    scope,
  );
}
export async function pendingCount(scope: string): Promise<number> {
  const db = await initializeDatabase();
  const row = await db.getFirstAsync<{ count: number }>(
    "SELECT COUNT(*) AS count FROM offline_outbox WHERE tenant_id || ':' || user_id = ? AND status IN ('pending', 'retryable', 'manual_retry', 'blocked_auth', 'blocked_authorization')",
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
  const error = await db.getFirstAsync<{ value: string }>(
    'SELECT value FROM app_metadata WHERE key = ?',
    `sync:last_error:${scope}`,
  );
  return { lastSuccess: success?.value ?? null, lastError: error?.value ?? null };
}
