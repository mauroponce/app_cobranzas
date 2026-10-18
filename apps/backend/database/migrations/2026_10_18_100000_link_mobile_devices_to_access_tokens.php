<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->unsignedBigInteger('access_token_id')->nullable()->after('membership_id');
            $table->timestampTz('last_seen_at')->nullable()->after('device_identifier');
            $table->foreign('access_token_id')->references('id')->on('personal_access_tokens')->nullOnDelete();
            $table->index(['tenant_id', 'revoked_at', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'revoked_at', 'last_seen_at']);
            $table->dropForeign(['access_token_id']);
            $table->dropColumn(['access_token_id', 'last_seen_at']);
        });
    }
};
