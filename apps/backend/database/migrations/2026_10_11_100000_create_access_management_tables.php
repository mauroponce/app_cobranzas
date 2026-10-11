<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('status')->default('active')->index();
            $table->string('timezone')->default('America/Argentina/Buenos_Aires');
            $table->string('currency', 3)->default('ARS');
        });

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->index(['tenant_id', 'status', 'role']);
        });

        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token_hash', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'expires_at']);
        });
        DB::statement('CREATE UNIQUE INDEX tenant_pending_invitation_unique ON tenant_invitations (tenant_id, lower(email)) WHERE accepted_at IS NULL AND revoked_at IS NULL');

        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('granted_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('platform_administrators');
        DB::statement('DROP INDEX IF EXISTS tenant_pending_invitation_unique');
        Schema::dropIfExists('tenant_invitations');
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status', 'role']);
            $table->dropColumn(['status', 'joined_at']);
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['status', 'timezone', 'currency']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['status', 'last_login_at']));
    }
};
