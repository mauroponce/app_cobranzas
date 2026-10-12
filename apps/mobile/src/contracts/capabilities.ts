export type PermissionScope = 'own' | 'assigned' | 'team' | 'all';

export type Capabilities = {
  user: { id: string; name: string; email: string };
  tenant: { id: string; name: string; slug: string; timezone: string; currency: string };
  role: string;
  features: { key: string; enabled: boolean; available: boolean }[];
  permissions: Record<string, PermissionScope>;
  authorization_version: { tenant: number; membership: number };
};

export function canAccess(capabilities: Capabilities | null, permission: string): boolean {
  return capabilities?.permissions[permission] !== undefined;
}
