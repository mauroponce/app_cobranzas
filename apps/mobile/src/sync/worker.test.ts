import { getMetadata } from '../database/database';
import { syncInfo } from '../database/financial';
import { queueOfflineCashPayment, queueOfflineCollectionDeclaration } from './outbox';
import { syncNow } from './worker';
import { apiGet, apiPost } from '../services/api/client';
import { resetSqliteMock, sqliteRows } from '../../test/sqliteMock';

jest.mock('../services/api/client', () => ({ apiGet: jest.fn(), apiPost: jest.fn() }));

const mockedGet = jest.mocked(apiGet);
const mockedPost = jest.mocked(apiPost);
const session = { token: 'token', deviceId: 'device-a', tenantId: 'tenant-a', userId: 'user-a' };
const pull = (cursor = 1, hasMore = false) => ({
  data: {
    customers: [
      {
        id: `customer-${cursor}`,
        first_name: 'Ana',
        last_name: 'Pérez',
        status: 'active',
        updated_at: '2026-10-12T00:00:00Z',
      },
    ],
    obligations: [
      {
        id: `entry-${cursor}`,
        customer_id: `customer-${cursor}`,
        sale_id: `sale-${cursor}`,
        outstanding_amount: '100.00',
        due_date: null,
        entry_type: 'installment',
      },
    ],
    route: {
      id: 'route-1',
      status: 'published',
      route_date: '2026-10-12',
      settlement: {
        id: null,
        status: 'not_presented',
        declared_cash_cents: '0',
        received_cash_cents: null,
      },
    },
    route_stops: [
      {
        id: 'visit-1',
        customer_id: `customer-${cursor}`,
        sale_id: `sale-${cursor}`,
        customer_name: 'Pérez, Ana',
        sequence: 1,
        status: 'recorded',
        outcome: 'paid',
        address: '',
        sale_due_amount_cents: '10000',
        customer_total_outstanding_cents: '10000',
        available_obligation_ids: [`entry-${cursor}`],
      },
    ],
    cursor,
    has_more: hasMore,
  },
});
async function enqueue(id = 'operation-1'): Promise<void> {
  await queueOfflineCashPayment({
    operationId: id,
    tenantId: session.tenantId,
    userId: session.userId,
    deviceId: session.deviceId,
    recordedAt: '2026-10-12T00:00:00.000Z',
    payload: {
      customer_id: 'customer-1',
      amount: '100.00',
      received_at: '2026-10-12T00:00:00.000Z',
    },
  });
}

describe('sync worker', () => {
  beforeEach(() => {
    resetSqliteMock();
    mockedGet.mockReset();
    mockedPost.mockReset();
  });

  it('pushes once, marks the local payment confirmed, pulls data and persists cursor and success time', async () => {
    await enqueue();
    mockedPost.mockResolvedValue({
      data: {
        operations: [{ operation_id: 'operation-1', status: 'applied', payment_id: 'payment-1' }],
      },
    });
    mockedGet.mockResolvedValue(pull());
    await syncNow(session);
    expect(mockedPost).toHaveBeenCalledWith(
      '/sync/push',
      expect.objectContaining({
        device_id: 'device-a',
        operations: [expect.objectContaining({ operation_id: 'operation-1' })],
      }),
      'token',
    );
    expect(sqliteRows().outbox.get('operation-1')).toMatchObject({ status: 'confirmed' });
    expect(sqliteRows().payments.get('operation-1')).toMatchObject({
      status: 'confirmed',
      payment_id: 'payment-1',
    });
    expect(await getMetadata('sync:cursor:tenant-a:user-a')).toBe('1');
    expect(await getMetadata('sync:last_success:tenant-a:user-a')).toEqual(expect.any(String));
    expect(sqliteRows().routes.get('tenant-a:user-a')).toMatchObject({
      id: 'route-1',
      status: 'published',
    });
    expect(sqliteRows().routeStops.get('tenant-a:user-a:visit-1')).toMatchObject({
      status: 'recorded',
      outcome: 'paid',
    });
  });

  it('keeps a synced collection declaration visible as pending review', async () => {
    await queueOfflineCollectionDeclaration({
      operationId: 'declaration-1',
      tenantId: session.tenantId,
      userId: session.userId,
      deviceId: session.deviceId,
      customerId: 'customer-1',
      recordedAt: '2026-10-12T00:00:00.000Z',
      payload: { visit_id: 'visit-1', components: [{ method: 'cash', amount: '100.00' }] },
    });
    mockedPost.mockResolvedValue({
      data: {
        operations: [
          {
            operation_id: 'declaration-1',
            status: 'pending_review',
            declaration_id: 'declaration-server-1',
          },
        ],
      },
    });
    mockedGet.mockResolvedValue(pull());

    await syncNow(session);

    expect(sqliteRows().payments.get('declaration-1')).toMatchObject({
      status: 'pending_review',
      payment_id: 'declaration-server-1',
    });
  });

  it('continues paginated pull from the persisted cursor', async () => {
    mockedGet.mockResolvedValueOnce(pull(2, true)).mockResolvedValueOnce(pull(3, false));
    await syncNow(session);
    expect(mockedGet.mock.calls.map(([url]) => url)).toEqual([
      '/sync/pull?cursor=0',
      '/sync/pull?cursor=2',
    ]);
    expect(await getMetadata('sync:cursor:tenant-a:user-a')).toBe('3');
  });

  it('keeps an operation and schedules exponential retry after a timeout', async () => {
    await enqueue();
    mockedPost.mockRejectedValue(new Error('timeout'));
    await expect(syncNow(session)).rejects.toThrow('timeout');
    const row = sqliteRows().outbox.get('operation-1');
    expect(row).toMatchObject({ status: 'retryable', attempt_count: 1, last_error: 'timeout' });
    expect(new Date(String(row?.next_attempt_at)).getTime() - Date.now()).toBeGreaterThanOrEqual(
      1900,
    );
    expect(new Date(String(row?.next_attempt_at)).getTime() - Date.now()).toBeLessThanOrEqual(2000);
    expect(await getMetadata('sync:last_success:tenant-a:user-a')).toBeNull();
    expect(await syncInfo('tenant-a:user-a')).toEqual({
      lastSuccess: null,
      lastError: 'timeout',
    });
  });

  it.each(['401', '403'])(
    'blocks automatic synchronization on HTTP %s without rejecting the financial operation',
    async (status) => {
      await enqueue();
      mockedPost.mockRejectedValue(new Error(`API request failed: ${status}`));
      await expect(syncNow(session)).rejects.toThrow(status);
      expect(sqliteRows().outbox.get('operation-1')).toMatchObject({
        status: status === '401' ? 'blocked_auth' : 'blocked_authorization',
      });
      expect(sqliteRows().payments.get('operation-1')).toMatchObject({ status: 'pending_sync' });
    },
  );

  it('retains the operation for a manual retry after eight transient failures', async () => {
    await enqueue();
    mockedPost.mockRejectedValue(new Error('API request failed: 500'));
    for (let index = 0; index < 8; index += 1) {
      await expect(syncNow(session, { force: true })).rejects.toThrow('500');
    }
    expect(sqliteRows().outbox.get('operation-1')).toMatchObject({
      status: 'manual_retry',
      attempt_count: 8,
    });
  });

  it('does not lose a pending operation on a partial push response', async () => {
    await enqueue('operation-1');
    await enqueue('operation-2');
    mockedPost.mockResolvedValue({
      data: {
        operations: [{ operation_id: 'operation-1', status: 'applied', payment_id: 'payment-1' }],
      },
    });
    await expect(syncNow(session)).rejects.toThrow('Respuesta parcial');
    expect(sqliteRows().outbox.get('operation-1')).toMatchObject({ status: 'confirmed' });
    expect(sqliteRows().outbox.get('operation-2')).toMatchObject({ status: 'retryable' });
  });

  it('serializes simultaneous triggers', async () => {
    await enqueue();
    let resolvePush:
      | ((value: {
          data: { operations: { operation_id: string; status: string; payment_id: string }[] };
        }) => void)
      | undefined;
    mockedPost.mockReturnValue(
      new Promise((resolve) => {
        resolvePush = resolve;
      }),
    );
    const first = syncNow(session);
    const second = syncNow(session);
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(mockedPost).toHaveBeenCalledTimes(1);
    resolvePush?.({
      data: {
        operations: [{ operation_id: 'operation-1', status: 'applied', payment_id: 'payment-1' }],
      },
    });
    mockedGet.mockResolvedValue(pull());
    await Promise.all([first, second]);
    expect(mockedPost).toHaveBeenCalledTimes(1);
  });

  it('removes a revoked portfolio from the cache without deleting pending financial operations', async () => {
    await enqueue();
    mockedPost.mockResolvedValue({
      data: {
        operations: [{ operation_id: 'operation-1', status: 'applied', payment_id: 'payment-1' }],
      },
    });
    mockedGet.mockResolvedValueOnce({ ...pull(), data: { ...pull().data, portfolio_version: 1 } });
    await syncNow(session);
    await enqueue('operation-2');
    mockedGet.mockResolvedValueOnce({
      data: {
        customers: [],
        obligations: [],
        cursor: 0,
        has_more: false,
        reset: true,
        portfolio_version: 2,
      },
    });
    mockedPost.mockResolvedValue({
      data: { operations: [{ operation_id: 'operation-2', status: 'under_review' }] },
    });
    await syncNow(session, { force: true });
    expect(sqliteRows().customers.size).toBe(0);
    expect(sqliteRows().outbox.get('operation-2')).toMatchObject({ status: 'under_review' });
    expect(await getMetadata('sync:portfolio_version:tenant-a:user-a')).toBe('2');
  });
});
