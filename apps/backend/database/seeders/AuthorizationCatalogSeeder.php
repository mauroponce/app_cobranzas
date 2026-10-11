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
        $featureKeys = ['customers', 'catalog', 'quotations', 'sales', 'collections', 'cash_settlements', 'reports', 'routes', 'documents', 'integrations', 'administration'];
        foreach ($featureKeys as $key) {
            Feature::firstOrCreate(['key' => $key], ['name' => $key, 'category' => 'operaciones']);
        }
        $features = Feature::all()->keyBy('key');
        foreach (RoleMatrix::CAPABILITIES as $capability) {
            $prefix = explode('.', $capability)[0];
            $featureKey = match ($prefix) {
                'products' => 'catalog', 'collections', 'payments' => 'collections', 'settlements' => 'cash_settlements', 'users', 'tenant', 'authorization' => 'administration', default => $prefix
            };
            $permission = Permission::firstOrCreate(['key' => $capability], ['feature_id' => $features[$featureKey]->id, 'description' => $capability]);
            foreach (RoleMatrix::ROLES as $role) {
                if (in_array($capability, RoleMatrix::permissions($role), true)) {
                    RolePermission::firstOrCreate(['role' => $role, 'permission_id' => $permission->id], ['scope' => 'all']);
                }
            }
        }
        foreach (Tenant::all() as $tenant) {
            foreach ($features as $feature) {
                TenantFeature::firstOrCreate(['tenant_id' => $tenant->id, 'feature_id' => $feature->id], ['is_enabled' => true, 'enabled_at' => now()]);
            }
        }
    }
}
