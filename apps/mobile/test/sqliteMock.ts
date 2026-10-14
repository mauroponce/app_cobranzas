type RecordValue = Record<string, unknown>;

type State = {
  metadata: Map<string, string>;
  outbox: Map<string, RecordValue>;
  payments: Map<string, RecordValue>;
  customers: Map<string, RecordValue>;
  obligations: Map<string, RecordValue>;
  routeStops: Map<string, RecordValue>;
  routes: Map<string, RecordValue>;
};

const state: State = {
  metadata: new Map(),
  outbox: new Map(),
  payments: new Map(),
  customers: new Map(),
  obligations: new Map(),
  routeStops: new Map(),
  routes: new Map(),
};

function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}
function snapshot(): State {
  return {
    metadata: new Map(state.metadata),
    outbox: new Map([...state.outbox].map(([key, value]) => [key, clone(value)])),
    payments: new Map([...state.payments].map(([key, value]) => [key, clone(value)])),
    customers: new Map([...state.customers].map(([key, value]) => [key, clone(value)])),
    obligations: new Map([...state.obligations].map(([key, value]) => [key, clone(value)])),
    routeStops: new Map([...state.routeStops].map(([key, value]) => [key, clone(value)])),
    routes: new Map([...state.routes].map(([key, value]) => [key, clone(value)])),
  };
}
function restore(value: State): void {
  state.metadata = value.metadata;
  state.outbox = value.outbox;
  state.payments = value.payments;
  state.customers = value.customers;
  state.obligations = value.obligations;
  state.routeStops = value.routeStops;
  state.routes = value.routes;
}
function values<T extends RecordValue>(entries: Map<string, T>): T[] {
  return [...entries.values()].map(clone);
}

const database = {
  execAsync: async (): Promise<void> => undefined,
  withTransactionAsync: async (callback: () => Promise<void>): Promise<void> => {
    const before = snapshot();
    try {
      await callback();
    } catch (error) {
      restore(before);
      throw error;
    }
  },
  runAsync: async (sql: string, ...parameters: unknown[]): Promise<void> => {
    if (sql.includes('INSERT INTO offline_outbox')) {
      const [operationId, tenantId, userId, deviceId, payload, recordedAt] = parameters as string[];
      if (state.outbox.has(operationId))
        throw new Error('UNIQUE constraint failed: offline_outbox.operation_id');
      state.outbox.set(operationId, {
        operation_id: operationId,
        tenant_id: tenantId,
        user_id: userId,
        device_id: deviceId,
        type: sql.includes("'collection_declaration'")
          ? 'collection_declaration'
          : 'cash_payment',
        payload,
        recorded_at: recordedAt,
        status: 'pending',
        attempt_count: 0,
        last_error: null,
        synced_at: null,
        next_attempt_at: null,
      });
      return;
    }
    if (sql.includes('INSERT INTO local_payments')) {
      const [operationId, scope, customerId, amount, recordedAt] = parameters as string[];
      if (state.payments.has(operationId))
        throw new Error('UNIQUE constraint failed: local_payments.operation_id');
      state.payments.set(operationId, {
        operation_id: operationId,
        scope_key: scope,
        customer_id: customerId,
        amount,
        status: sql.includes("'pending', ?") ? 'pending' : 'pending_sync',
        recorded_at: recordedAt,
        payment_id: null,
        result: null,
      });
      return;
    }
    if (sql.startsWith('UPDATE offline_outbox SET status = ?, synced_at')) {
      const [status, syncedAt, lastError, operationId] = parameters as string[];
      const row = state.outbox.get(operationId);
      if (row)
        Object.assign(row, {
          status,
          synced_at: syncedAt,
          last_error: lastError,
          next_attempt_at: null,
        });
      return;
    }
    if (sql.startsWith('UPDATE offline_outbox SET status = ?, attempt_count')) {
      const [status, attemptCount, lastError, nextAttemptAt, operationId] = parameters;
      const row = state.outbox.get(String(operationId));
      if (row)
        Object.assign(row, {
          status,
          attempt_count: attemptCount,
          last_error: lastError,
          next_attempt_at: nextAttemptAt,
        });
      return;
    }
    if (sql.startsWith('UPDATE local_payments SET status')) {
      const [status, paymentId, result, operationId] = parameters;
      const row = state.payments.get(String(operationId));
      if (row) Object.assign(row, { status, payment_id: paymentId, result });
      return;
    }
    if (sql.startsWith('INSERT OR REPLACE INTO app_metadata')) {
      state.metadata.set(String(parameters[0]), String(parameters[1]));
      return;
    }
    if (sql.startsWith('DELETE FROM app_metadata')) {
      state.metadata.delete(String(parameters[0]));
      return;
    }
    if (sql.startsWith('DELETE FROM authorized_customers')) {
      for (const [key, row] of state.customers)
        if (row.scope_key === parameters[0]) state.customers.delete(key);
      return;
    }
    if (sql.startsWith('DELETE FROM authorized_obligations')) {
      for (const [key, row] of state.obligations)
        if (row.scope_key === parameters[0]) state.obligations.delete(key);
      return;
    }
    if (sql.startsWith('DELETE FROM authorized_route_stops')) {
      for (const [key, row] of state.routeStops)
        if (row.scope_key === parameters[0]) state.routeStops.delete(key);
      return;
    }
    if (sql.startsWith('DELETE FROM authorized_routes')) {
      state.routes.delete(String(parameters[0]));
      return;
    }
    if (sql.includes('authorized_customers')) {
      const [scope, id, name, status, updatedAt, collectorName, zoneName, assignmentStatus] =
        parameters as string[];
      state.customers.set(`${scope}:${id}`, {
        scope_key: scope,
        id,
        name,
        status,
        updated_at: updatedAt,
        collector_name: collectorName,
        zone_name: zoneName,
        assignment_status: assignmentStatus,
      });
      return;
    }
    if (sql.includes('authorized_obligations')) {
      const [scope, id, customerId, amount, dueDate, entryType] = parameters as string[];
      state.obligations.set(`${scope}:${id}`, {
        scope_key: scope,
        id,
        customer_id: customerId,
        outstanding_amount: amount,
        due_date: dueDate,
        entry_type: entryType,
      });
      return;
    }
    if (sql.includes('authorized_route_stops')) {
      const [scope, id, customerId, customerName, sequence, status, address, dueAmountCents] =
        parameters as string[];
      state.routeStops.set(`${scope}:${id}`, {
        scope_key: scope,
        id,
        customer_id: customerId,
        customer_name: customerName,
        sequence,
        status,
        address,
        due_amount_cents: dueAmountCents,
      });
      return;
    }
    if (sql.includes('authorized_routes')) {
      const [scope, id, status, routeDate] = parameters as string[];
      state.routes.set(scope, { scope_key: scope, id, status, route_date: routeDate });
      return;
    }
    throw new Error(`Unsupported SQL: ${sql}`);
  },
  getAllAsync: async <T>(sql: string, ...parameters: unknown[]): Promise<T[]> => {
    if (sql.includes('FROM offline_outbox')) {
      const [tenantId, userId, now] = parameters;
      return values(state.outbox)
        .filter(
          (row) =>
            row.tenant_id === tenantId &&
            row.user_id === userId &&
            (sql.includes('status IN')
              ? /['"]pending['"]/.test(sql) &&
                (row.status === 'pending' ||
                  row.status === 'retryable' ||
                  (sql.includes('manual_retry') && row.status === 'manual_retry'))
              : true) &&
            (!sql.includes('next_attempt_at') ||
              row.next_attempt_at === null ||
              String(row.next_attempt_at) <= String(now)),
        )
        .sort((a, b) => String(a.recorded_at).localeCompare(String(b.recorded_at))) as T[];
    }
    if (sql.includes('FROM local_payments')) {
      const [scope] = parameters;
      return values(state.payments).filter((row) => row.scope_key === scope) as T[];
    }
    if (sql.includes('FROM authorized_customers')) {
      const [scope] = parameters;
      return values(state.customers).filter((row) => row.scope_key === scope) as T[];
    }
    if (sql.includes('FROM authorized_obligations')) {
      const [scope, customer] = parameters;
      return values(state.obligations).filter(
        (row) => row.scope_key === scope && (!customer || row.customer_id === customer),
      ) as T[];
    }
    throw new Error(`Unsupported query: ${sql}`);
  },
  getFirstAsync: async <T>(sql: string, ...parameters: unknown[]): Promise<T | null> => {
    if (sql.includes('FROM app_metadata')) {
      const value = state.metadata.get(String(parameters[0]));
      return value === undefined ? null : ({ value } as T);
    }
    if (sql.includes('COUNT(*)')) {
      const [scope] = parameters;
      return {
        count: values(state.outbox).filter(
          (row) =>
            row.tenant_id === String(scope).split(':')[0] &&
            row.user_id === String(scope).split(':')[1] &&
            ['pending', 'retryable'].includes(String(row.status)),
        ).length,
      } as T;
    }
    throw new Error(`Unsupported query: ${sql}`);
  },
};

export function resetSqliteMock(): void {
  restore({
    metadata: new Map(),
    outbox: new Map(),
    payments: new Map(),
    customers: new Map(),
    obligations: new Map(),
    routeStops: new Map(),
    routes: new Map(),
  });
}
export function sqliteRows(): State {
  return snapshot();
}
export async function openDatabaseAsync(): Promise<typeof database> {
  return database;
}
