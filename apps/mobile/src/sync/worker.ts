import { apiGet, apiPost } from '../services/api/client';
import { initializeDatabase } from '../database/database';
import { scopeKey } from '../database/financial';

type Session = { token: string; deviceId: string; tenantId: string; userId: string };
type SyncOptions = { force?: boolean };
let running: Promise<void> | undefined;

export async function syncNow(session: Session, options: SyncOptions = {}): Promise<void> {
  if (running) return running;

  running = runSync(session, options);
  try {
    await running;
  } finally {
    running = undefined;
  }
}

async function runSync(session: Session, options: SyncOptions): Promise<void> {
  try {
    const db = await initializeDatabase();
    const scope = scopeKey(session.tenantId, session.userId);
    const statuses = options.force
      ? "('pending', 'retryable', 'manual_retry')"
      : "('pending', 'retryable')";
    const nextAttemptClause = options.force
      ? ''
      : ' AND (next_attempt_at IS NULL OR next_attempt_at <= ?)';
    const queryParameters: [string, string, ...string[]] = options.force
      ? [session.tenantId, session.userId]
      : [session.tenantId, session.userId, new Date().toISOString()];
    const rows = await db.getAllAsync<{
      operation_id: string;
      type: string;
      payload: string;
      recorded_at: string;
      attempt_count: number;
    }>(
      `SELECT operation_id, type, payload, recorded_at, attempt_count FROM offline_outbox WHERE tenant_id = ? AND user_id = ? AND status IN ${statuses}${nextAttemptClause} ORDER BY recorded_at LIMIT 50`,
      ...queryParameters,
    );
    if (rows.length) {
      const response = await apiPost<{
        data: {
          operations: {
            operation_id: string;
            status: string;
            payment_id?: string;
            declaration_id?: string;
            error?: string;
          }[];
        };
      }>(
        '/sync/push',
        {
          device_id: session.deviceId,
          operations: rows.map((row) => ({
            operation_id: row.operation_id,
            schema_version: 1,
            type: row.type,
            payload: JSON.parse(row.payload),
            recorded_at: row.recorded_at,
          })),
        },
        session.token,
      );
      await db.withTransactionAsync(async () => {
        for (const result of response.data.operations) {
          const status = result.status === 'applied' ? 'confirmed' : result.status;
          await db.runAsync(
            'UPDATE offline_outbox SET status = ?, synced_at = ?, last_error = ? WHERE operation_id = ?',
            status,
            new Date().toISOString(),
            result.error ?? null,
            result.operation_id,
          );
          await db.runAsync(
            'UPDATE local_payments SET status = ?, payment_id = ?, result = ? WHERE operation_id = ?',
            status,
            result.payment_id ?? result.declaration_id ?? null,
            JSON.stringify(result),
            result.operation_id,
          );
        }
      });
      if (response.data.operations.length !== rows.length)
        throw new Error('Respuesta parcial de sincronización');
    }
    let cursor = Number(
      (
        await db.getFirstAsync<{ value: string }>(
          'SELECT value FROM app_metadata WHERE key = ?',
          `sync:cursor:${scope}`,
        )
      )?.value ?? 0,
    );
    let portfolioVersion = (
      await db.getFirstAsync<{ value: string }>(
        'SELECT value FROM app_metadata WHERE key = ?',
        `sync:portfolio_version:${scope}`,
      )
    )?.value;
    do {
      const pull = await apiGet<{
        data: {
          customers: {
            id: string;
            first_name: string;
            last_name: string;
            status: string;
            updated_at: string;
            collector_name?: string | null;
            zone_name?: string | null;
            assignment_status?: string | null;
          }[];
          obligations: {
            id: string;
            customer_id: string;
            sale_id: string;
            outstanding_amount: string;
            due_date: string | null;
            entry_type: string;
          }[];
          route_stops: {
            id: string;
            customer_id: string;
            sale_id: string;
            customer_name: string;
            sequence: number;
            status: string;
            outcome: string | null;
            address: string;
            sale_due_amount_cents: string;
            customer_total_outstanding_cents: string;
            available_obligation_ids: string[];
          }[];
          route: {
            id: string;
            status: string;
            route_date: string;
            settlement: {
              id: string | null;
              status: string;
              declared_cash_cents: string;
              received_cash_cents: string | null;
            } | null;
          } | null;
          cursor: number;
          has_more: boolean;
          reset?: boolean;
          portfolio_version?: number;
        };
      }>(
        `/sync/pull?cursor=${cursor}${portfolioVersion ? `&portfolio_version=${encodeURIComponent(portfolioVersion)}` : ''}`,
        session.token,
      );
      await db.withTransactionAsync(async () => {
        if (pull.data.reset) {
          await db.runAsync('DELETE FROM authorized_obligations WHERE scope_key = ?', scope);
          await db.runAsync('DELETE FROM authorized_customers WHERE scope_key = ?', scope);
        }
        await db.runAsync('DELETE FROM authorized_route_stops WHERE scope_key = ?', scope);
        await db.runAsync('DELETE FROM authorized_routes WHERE scope_key = ?', scope);
        for (const customer of pull.data.customers)
          await db.runAsync(
            'INSERT OR REPLACE INTO authorized_customers (scope_key,id,name,status,updated_at,collector_name,zone_name,assignment_status) VALUES (?,?,?,?,?,?,?,?)',
            scope,
            customer.id,
            `${customer.last_name}, ${customer.first_name}`,
            customer.status,
            customer.updated_at,
            customer.collector_name ?? null,
            customer.zone_name ?? null,
            customer.assignment_status ?? null,
          );
        for (const entry of pull.data.obligations)
          await db.runAsync(
            'INSERT OR REPLACE INTO authorized_obligations (scope_key,id,customer_id,sale_id,outstanding_amount,due_date,entry_type) VALUES (?,?,?,?,?,?,?)',
            scope,
            entry.id,
            entry.customer_id,
            entry.sale_id,
            entry.outstanding_amount,
            entry.due_date,
            entry.entry_type,
          );
        for (const stop of pull.data.route_stops ?? [])
          await db.runAsync(
            'INSERT OR REPLACE INTO authorized_route_stops (scope_key,id,customer_id,sale_id,customer_name,sequence,status,outcome,address,sale_due_amount_cents,customer_total_outstanding_cents,available_obligation_ids) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            scope,
            stop.id,
            stop.customer_id,
            stop.sale_id,
            stop.customer_name,
            stop.sequence,
            stop.status,
            stop.outcome,
            stop.address,
            stop.sale_due_amount_cents,
            stop.customer_total_outstanding_cents,
            JSON.stringify(stop.available_obligation_ids),
          );
        if (pull.data.route)
          await db.runAsync(
            'INSERT INTO authorized_routes (scope_key,id,status,route_date,settlement_id,settlement_status,declared_cash_cents,received_cash_cents) VALUES (?,?,?,?,?,?,?,?)',
            scope,
            pull.data.route.id,
            pull.data.route.status,
            pull.data.route.route_date,
            pull.data.route.settlement?.id ?? null,
            pull.data.route.settlement?.status ?? null,
            pull.data.route.settlement?.declared_cash_cents ?? null,
            pull.data.route.settlement?.received_cash_cents ?? null,
          );
        await db.runAsync(
          'INSERT OR REPLACE INTO app_metadata (key, value) VALUES (?, ?)',
          `sync:cursor:${scope}`,
          String(pull.data.cursor),
        );
        if (pull.data.portfolio_version !== undefined)
          await db.runAsync(
            'INSERT OR REPLACE INTO app_metadata (key, value) VALUES (?, ?)',
            `sync:portfolio_version:${scope}`,
            String(pull.data.portfolio_version),
          );
      });
      cursor = pull.data.cursor;
      portfolioVersion =
        pull.data.portfolio_version === undefined
          ? portfolioVersion
          : String(pull.data.portfolio_version);
      if (!pull.data.has_more) break;
    } while (true);
    await db.runAsync(
      'INSERT OR REPLACE INTO app_metadata (key, value) VALUES (?, ?)',
      `sync:last_success:${scope}`,
      new Date().toISOString(),
    );
    await db.runAsync('DELETE FROM app_metadata WHERE key = ?', `sync:last_error:${scope}`);
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Error de sincronización';
    const blockedStatus = /401/.test(message)
      ? 'blocked_auth'
      : /403/.test(message)
        ? 'blocked_authorization'
        : null;
    const db = await initializeDatabase();
    const scope = scopeKey(session.tenantId, session.userId);
    const rows = await db.getAllAsync<{ operation_id: string; attempt_count: number }>(
      "SELECT operation_id, attempt_count FROM offline_outbox WHERE tenant_id = ? AND user_id = ? AND status IN ('pending', 'retryable', 'manual_retry')",
      session.tenantId,
      session.userId,
    );
    await db.withTransactionAsync(async () => {
      for (const row of rows) {
        const attempts = row.attempt_count + 1;
        const status = blockedStatus ?? (attempts >= 8 ? 'manual_retry' : 'retryable');
        const delay = Math.min(300000, 1000 * 2 ** Math.min(attempts, 8));
        await db.runAsync(
          'UPDATE offline_outbox SET status = ?, attempt_count = ?, last_error = ?, next_attempt_at = ? WHERE operation_id = ?',
          status,
          attempts,
          message,
          status === 'retryable' ? new Date(Date.now() + delay).toISOString() : null,
          row.operation_id,
        );
      }
      await db.runAsync(
        'INSERT OR REPLACE INTO app_metadata (key, value) VALUES (?, ?)',
        `sync:last_error:${scope}`,
        message,
      );
    });
    throw error;
  }
}
