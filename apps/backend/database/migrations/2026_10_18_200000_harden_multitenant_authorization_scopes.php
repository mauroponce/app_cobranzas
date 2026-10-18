<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SETTLEMENT_CAPABILITIES = [
        'settlements.view',
        'settlements.create',
        'settlements.submit',
    ];

    public function up(): void
    {
        if ($this->setCollectorScope('own') > 0) {
            DB::table('tenants')->increment('authorization_version');
        }
    }

    public function down(): void
    {
        if ($this->setCollectorScope('all') > 0) {
            DB::table('tenants')->increment('authorization_version');
        }
    }

    private function setCollectorScope(string $scope): int
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', self::SETTLEMENT_CAPABILITIES)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return 0;
        }

        return DB::table('role_permissions')
            ->where('role', 'collector')
            ->whereIn('permission_id', $permissionIds)
            ->where('scope', '!=', $scope)
            ->update(['scope' => $scope, 'updated_at' => now()]);
    }
};
