import { initializeDatabase } from '../database/database';
import { queueOfflineCashPayment, queueOfflineCollectionDeclaration } from './outbox';
import { resetSqliteMock, sqliteRows } from '../../test/sqliteMock';

const payment = (overrides = {}) => ({
  operationId: 'operation-1', tenantId: 'tenant-a', userId: 'user-a', deviceId: 'device-a', recordedAt: '2026-10-12T00:00:00.000Z',
  payload: { customer_id: 'customer-a', amount: '125.50', received_at: '2026-10-12T00:00:00.000Z' }, ...overrides,
});

describe('offline cash outbox', () => {
  beforeEach(() => resetSqliteMock());

  it('stores the local payment and outbox operation atomically with its identity', async () => {
    await queueOfflineCashPayment(payment());
    const rows = sqliteRows();
    expect(rows.outbox.get('operation-1')).toMatchObject({ tenant_id: 'tenant-a', user_id: 'user-a', device_id: 'device-a', payload: expect.stringContaining('125.50'), status: 'pending' });
    expect(rows.payments.get('operation-1')).toMatchObject({ scope_key: 'tenant-a:user-a', amount: '125.50', status: 'pending_sync' });
  });

  it('stores a collection declaration in the outbox and visible local history', async () => {
    await queueOfflineCollectionDeclaration({
      operationId: 'declaration-1',
      tenantId: 'tenant-a',
      userId: 'user-a',
      deviceId: 'device-a',
      customerId: 'customer-a',
      recordedAt: '2026-10-12T00:00:00.000Z',
      payload: {
        visit_id: 'visit-a',
        components: [{ method: 'bank_transfer', amount: '125.50', reference: 'DEMO-1' }],
      },
    });

    expect(sqliteRows().outbox.get('declaration-1')).toMatchObject({
      type: 'collection_declaration',
      status: 'pending',
    });
    expect(sqliteRows().payments.get('declaration-1')).toMatchObject({
      customer_id: 'customer-a',
      amount: '125.50',
      status: 'pending',
    });
  });

  it('rolls back the outbox insert when the local payment write fails', async () => {
    const database = await initializeDatabase();
    const original = database.runAsync.bind(database);
    const spy = jest.spyOn(database, 'runAsync').mockImplementation(async (sql, ...params) => {
      if (sql.includes('INSERT INTO local_payments')) throw new Error('disk full');
      return original(sql, ...params);
    });
    await expect(queueOfflineCashPayment(payment())).rejects.toThrow('disk full');
    expect(sqliteRows().outbox.size).toBe(0);
    expect(sqliteRows().payments.size).toBe(0);
    spy.mockRestore();
  });

  it('keeps pending data across another database initialization and separates tenants', async () => {
    await queueOfflineCashPayment(payment());
    await queueOfflineCashPayment(payment({ operationId: 'operation-2', tenantId: 'tenant-b', userId: 'user-b' }));
    await initializeDatabase();
    const rows = sqliteRows();
    expect([...rows.outbox.values()].filter((row) => row.tenant_id === 'tenant-a')).toHaveLength(1);
    expect([...rows.outbox.values()].filter((row) => row.tenant_id === 'tenant-b')).toHaveLength(1);
  });
});
