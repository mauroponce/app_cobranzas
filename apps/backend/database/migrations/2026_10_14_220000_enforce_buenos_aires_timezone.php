<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TIMEZONE = 'America/Argentina/Buenos_Aires';

    public function up(): void
    {
        DB::table('tenants')->update(['timezone' => self::TIMEZONE]);
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_timezone_buenos_aires_check CHECK (timezone = '".self::TIMEZONE."')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_timezone_buenos_aires_check');
    }
};
