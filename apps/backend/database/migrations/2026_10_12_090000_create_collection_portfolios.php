<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_zones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });
        DB::statement("ALTER TABLE collection_zones ADD CONSTRAINT collection_zones_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('customer_collection_zone_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id');
            $table->uuid('zone_id');
            $table->uuid('assigned_by_membership_id');
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'zone_id'])->references(['tenant_id', 'id'])->on('collection_zones')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'assigned_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->index(['tenant_id', 'zone_id', 'unassigned_at']);
        });
        DB::statement('CREATE UNIQUE INDEX customer_collection_zone_assignments_active_customer_unique ON customer_collection_zone_assignments (tenant_id, customer_id) WHERE unassigned_at IS NULL');
        DB::statement('ALTER TABLE customer_collection_zone_assignments ADD CONSTRAINT customer_collection_zone_assignments_dates_check CHECK (unassigned_at IS NULL OR unassigned_at >= assigned_at)');

        Schema::create('collection_teams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });
        DB::statement("ALTER TABLE collection_teams ADD CONSTRAINT collection_teams_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('collection_team_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('team_id');
            $table->uuid('membership_id');
            $table->string('team_role', 16);
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'team_id'])->references(['tenant_id', 'id'])->on('collection_teams')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->cascadeOnDelete();
            $table->index(['tenant_id', 'membership_id', 'left_at']);
        });
        DB::statement('CREATE UNIQUE INDEX collection_team_members_active_member_unique ON collection_team_members (tenant_id, team_id, membership_id) WHERE left_at IS NULL');
        DB::statement("ALTER TABLE collection_team_members ADD CONSTRAINT collection_team_members_role_check CHECK (team_role IN ('supervisor', 'collector'))");
        DB::statement('ALTER TABLE collection_team_members ADD CONSTRAINT collection_team_members_dates_check CHECK (left_at IS NULL OR left_at >= joined_at)');

        Schema::create('customer_collector_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('customer_id');
            $table->uuid('collector_membership_id');
            $table->uuid('assigned_by_membership_id');
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'collector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->foreign(['tenant_id', 'assigned_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->index(['tenant_id', 'collector_membership_id', 'unassigned_at']);
        });
        DB::statement('CREATE UNIQUE INDEX customer_collector_assignments_active_customer_unique ON customer_collector_assignments (tenant_id, customer_id) WHERE unassigned_at IS NULL');
        DB::statement('ALTER TABLE customer_collector_assignments ADD CONSTRAINT customer_collector_assignments_dates_check CHECK (unassigned_at IS NULL OR unassigned_at >= assigned_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_collector_assignments');
        Schema::dropIfExists('collection_team_members');
        Schema::dropIfExists('collection_teams');
        Schema::dropIfExists('customer_collection_zone_assignments');
        Schema::dropIfExists('collection_zones');
    }
};
