export type PermissionScope = 'own' | 'assigned' | 'team' | 'all';

export type CapabilityFeature = {
  key: string;
  enabled: boolean;
  available: boolean;
};

export type CapabilitiesResponse = {
  data: {
    user: { id: string; name: string; email: string };
    tenant: { id: string; name: string; slug: string; timezone: string; currency: string };
    role: string;
    features: CapabilityFeature[];
    permissions: Record<string, PermissionScope>;
    authorization_version: { tenant: number; membership: number };
  };
};

export type SyncOperation = {
  operation_id: string;
  schema_version: number;
  type: 'cash_payment';
  payload: {
    customer_id: string;
    amount: string;
    allocations?: Array<{ account_entry_id: string; amount?: string }>;
    received_at: string;
    notes?: string;
  };
  recorded_at: string;
};

export type CollectionPortfolioPull = {
  customers: Array<{ id: string; customer_number: string; first_name: string; last_name: string; status: string; updated_at: string }>;
  obligations: Array<{ id: string; customer_id: string; outstanding_amount: string; due_date: string | null; entry_type: string }>;
  cursor: number;
  has_more: boolean;
  reset: boolean;
  portfolio_version: number;
};
