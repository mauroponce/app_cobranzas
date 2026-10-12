<?php

namespace Database\Seeders;

use App\Domain\Tenancy\RoleMatrix;
use App\Models\Feature;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\TenantFeature;
use Illuminate\Database\Seeder;

class AuthorizationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $featureKeys = ['customers', 'catalog', 'quotations', 'sales', 'collections', 'collections_assignments', 'collections_teams', 'collections_zones', 'cash_settlements', 'reports', 'routes', 'documents', 'integrations', 'administration'];
        foreach ($featureKeys as $key) {
            Feature::firstOrCreate(['key' => $key], ['name' => $key, 'category' => 'operaciones']);
        }
        $features = Feature::all()->keyBy('key');
        foreach (RoleMatrix::CAPABILITIES as $capability) {
            $prefix = explode('.', $capability)[0];
            $featureKey = match ($capability) {
                'customers.assign_collector', 'customers.unassign_collector', 'customers.view_assignments' => 'collections_assignments',
                default => match ($prefix) {
                    'products' => 'catalog', 'collections', 'payments' => 'collections', 'collection_teams' => 'collections_teams', 'collection_zones' => 'collections_zones', 'settlements' => 'cash_settlements', 'users', 'tenant', 'authorization' => 'administration', default => $prefix
                },
            };
            $scopes = match ($capability) {
                'payments.view', 'payments.create', 'settlements.view', 'settlements.create', 'settlements.submit', 'customers.view', 'collections.portfolio.view', 'sales.view' => ['own', 'assigned', 'team', 'all'],
                'collection_teams.view', 'collection_teams.manage', 'collection_teams.manage_members' => ['team', 'all'],
                default => ['all'],
            };
            $permission = Permission::firstOrCreate(['key' => $capability], ['feature_id' => $features[$featureKey]->id, 'description' => $capability, 'allowed_scopes' => $scopes]);
            $permission->update(['feature_id' => $features[$featureKey]->id, 'allowed_scopes' => $scopes]);
            foreach (RoleMatrix::ROLES as $role) {
                if (in_array($capability, RoleMatrix::permissions($role), true)) {
                    $scope = $this->defaultScope($role, $capability);
                    RolePermission::updateOrCreate(['role' => $role, 'permission_id' => $permission->id], ['scope' => $scope]);
                }
            }
        }
        foreach (Tenant::all() as $tenant) {
            foreach ($features as $feature) {
                TenantFeature::firstOrCreate(['tenant_id' => $tenant->id, 'feature_id' => $feature->id], ['is_available' => true, 'available_at' => now(), 'is_enabled' => true, 'enabled_at' => now()]);
            }
        }
    }

    private function defaultScope(string $role, string $capability): string
    {
        if (! in_array($capability, ['customers.view', 'payments.view', 'payments.create', 'collections.portfolio.view'], true)) {
            return 'all';
        }

        return match ($role) {
            'collector' => 'assigned',
            'supervisor' => 'team',
            default => 'all',
        };
    }
}
