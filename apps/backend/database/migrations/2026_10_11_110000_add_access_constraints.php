<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'suspended'))");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('active', 'suspended', 'cancelled'))");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_currency_check CHECK (currency = 'ARS')");
        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'invited', 'suspended'))");
        DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT memberships_role_check CHECK (role IN ('owner', 'admin', 'supervisor', 'seller', 'collector', 'logistics', 'viewer'))");
        DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('owner', 'admin', 'supervisor', 'seller', 'collector', 'logistics', 'viewer'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenant_invitations DROP CONSTRAINT invitations_role_check');
        DB::statement('ALTER TABLE tenant_memberships DROP CONSTRAINT memberships_role_check');
        DB::statement('ALTER TABLE tenant_memberships DROP CONSTRAINT memberships_status_check');
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT tenants_currency_check');
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT tenants_status_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check');
    }
};
