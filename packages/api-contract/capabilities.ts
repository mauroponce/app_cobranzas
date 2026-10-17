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
  type: 'collection_declaration' | 'visit_outcome';
  payload:
    | {
        visit_id: string;
        components: Array<{
          method: 'cash' | 'bank_transfer';
          amount: string;
          reference?: string;
        }>;
      }
    | {
        visit_id: string;
        reason: 'absent' | 'refused' | 'insufficient_funds' | 'other';
        notes?: string;
      };
  recorded_at: string;
};

export type CollectionPortfolioPull = {
  customers: Array<{ id: string; customer_number: string; first_name: string; last_name: string; status: string; updated_at: string }>;
  obligations: Array<{ id: string; customer_id: string; sale_id: string; outstanding_amount: string; due_date: string | null; entry_type: string }>;
  route: null | {
    id: string;
    status: 'published' | 'in_progress' | 'submitted' | 'completed';
    route_date: string;
    settlement: null | {
      id: string | null;
      status: string;
      declared_cash_cents: string;
      received_cash_cents: string | null;
    };
  };
  route_stops: Array<{
    id: string;
    customer_id: string;
    sale_id: string;
    sale_due_amount_cents: string;
    customer_total_outstanding_cents: string;
    available_obligation_ids: string[];
  }>;
  cursor: number;
  has_more: boolean;
  reset: boolean;
  portfolio_version: number;
};
