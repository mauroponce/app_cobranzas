import { initializeDatabase } from '../database/database';

export type OfflineCashPayment = {
  operationId: string;
  tenantId: string;
  userId: string;
  deviceId: string;
  payload: { customer_id: string; amount: string; allocations?: { account_entry_id: string; amount?: string }[]; received_at: string; notes?: string };
  recordedAt: string;
};

export type OfflineCollectionDeclaration = {
  operationId: string;
  tenantId: string;
  userId: string;
  deviceId: string;
  customerId: string;
  payload: {
    visit_id: string;
    components: { method: 'cash' | 'bank_transfer'; amount: string; reference?: string }[];
  };
  recordedAt: string;
};

export async function queueOfflineCollectionDeclaration(
  declaration: OfflineCollectionDeclaration,
): Promise<void> {
  const database = await initializeDatabase();
  const amount = declaration.payload.components[0]?.amount ?? '0.00';

  await database.withTransactionAsync(async () => {
    await database.runAsync(
      `INSERT INTO offline_outbox (operation_id, tenant_id, user_id, device_id, type, payload, recorded_at, status, attempt_count)
       VALUES (?, ?, ?, ?, 'collection_declaration', ?, ?, 'pending', 0)`,
      declaration.operationId,
      declaration.tenantId,
      declaration.userId,
      declaration.deviceId,
      JSON.stringify(declaration.payload),
      declaration.recordedAt,
    );
    await database.runAsync(
      "INSERT INTO local_payments (operation_id, scope_key, customer_id, amount, status, recorded_at) VALUES (?, ?, ?, ?, 'pending', ?)",
      declaration.operationId,
      `${declaration.tenantId}:${declaration.userId}`,
      declaration.customerId,
      amount,
      declaration.recordedAt,
    );
  });
}

export async function queueOfflineCashPayment(payment: OfflineCashPayment): Promise<void> {
  const database = await initializeDatabase();
  await database.withTransactionAsync(async () => {
  await database.runAsync(
    `INSERT INTO offline_outbox (operation_id, tenant_id, user_id, device_id, type, payload, recorded_at, status, attempt_count)
     VALUES (?, ?, ?, ?, 'cash_payment', ?, ?, 'pending', 0)`,
    payment.operationId,
    payment.tenantId,
    payment.userId,
    payment.deviceId,
    JSON.stringify(payment.payload),
    payment.recordedAt,
  );
  await database.runAsync("INSERT INTO local_payments (operation_id, scope_key, customer_id, amount, status, recorded_at) VALUES (?, ?, ?, ?, 'pending_sync', ?)", payment.operationId, `${payment.tenantId}:${payment.userId}`, String(payment.payload.customer_id), String(payment.payload.amount), payment.recordedAt);
  });
}
