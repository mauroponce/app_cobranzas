export type SyncState = 'confirmed' | 'pending' | 'conflict' | 'syncing' | 'error';
export type VisitStatus = 'pending' | 'completed' | 'rescheduled' | 'omitted';
export type RouteStop = {
  id: string;
  order: number;
  customerId: string;
  customerName: string;
  address: string;
  dueMinor: string | null;
  status: VisitStatus;
  onlinePaymentConfirmed: boolean;
};
export type Customer = {
  id: string;
  name: string;
  address: string;
  phone: string | null;
  collectorName: string | null;
  dueMinor: string | null;
  activeSales: number | null;
  pendingInstallments: number | null;
  status: string;
};
export type Payment = {
  id: string;
  customerName: string;
  amountMinor: string;
  method: 'cash' | 'transfer' | 'online';
  occurredAt: string;
  syncState: SyncState;
};
export type Notice = {
  id: string;
  title: string;
  message: string;
  occurredAt: string;
  kind: 'online' | 'route' | 'reminder' | 'conflict';
};

// Laravel has no collector data endpoints yet. Empty arrays avoid presenting mockup values as real records.
export const collectorData: {
  routeStops: RouteStop[];
  customers: Customer[];
  payments: Payment[];
  notices: Notice[];
} = {
  routeStops: [],
  customers: [],
  payments: [],
  notices: [],
};

export const APP_TIME_ZONE = 'America/Argentina/Buenos_Aires';

export function formatMoney(minorUnits: string | null): string {
  if (minorUnits === null) return '—';
  const value = BigInt(minorUnits);
  const sign = value < 0n ? '-' : '';
  const absolute = value < 0n ? -value : value;
  const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${sign}$ ${whole},${(absolute % 100n).toString().padStart(2, '0')}`;
}

export function formatDate(date: Date): string {
  return new Intl.DateTimeFormat('es-AR', {
    timeZone: APP_TIME_ZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(date);
}

export function formatDateTime(value: Date | string): string {
  const date = value instanceof Date ? value : new Date(value);

  return new Intl.DateTimeFormat('es-AR', {
    timeZone: APP_TIME_ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(date);
}
