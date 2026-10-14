<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_routes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('route_date');
            $table->uuid('collector_membership_id');
            $table->uuid('zone_id')->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->uuid('created_by_membership_id');
            $table->uuid('published_by_membership_id')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'route_date', 'status']);
            $table->index(['tenant_id', 'collector_membership_id', 'route_date']);
            $table->foreign(['tenant_id', 'collector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->foreign(['tenant_id', 'zone_id'])->references(['tenant_id', 'id'])->on('collection_zones');
            $table->foreign(['tenant_id', 'created_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->foreign(['tenant_id', 'published_by_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
        });
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_status_check CHECK (status IN ('draft', 'published', 'in_progress', 'completed', 'cancelled'))");
        DB::statement('ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_version_check CHECK (version > 0)');
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_dates_check CHECK ((published_at IS NULL OR status IN ('published', 'in_progress', 'completed', 'cancelled')) AND (started_at IS NULL OR published_at IS NOT NULL) AND (completed_at IS NULL OR started_at IS NOT NULL) AND (cancelled_at IS NULL OR status = 'cancelled'))");

        Schema::create('collection_route_stops', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('route_id');
            $table->uuid('customer_id');
            $table->uuid('customer_address_id')->nullable();
            $table->unsignedInteger('sequence');
            $table->uuid('assigned_collector_membership_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('planned_notes')->nullable();
            $table->jsonb('address_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['route_id', 'sequence']);
            $table->unique(['route_id', 'customer_id']);
            $table->index(['tenant_id', 'assigned_collector_membership_id', 'status']);
            $table->foreign(['tenant_id', 'route_id'])->references(['tenant_id', 'id'])->on('collection_routes')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'customer_address_id'])->references(['tenant_id', 'id'])->on('customer_addresses');
            $table->foreign(['tenant_id', 'assigned_collector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
        });
        DB::statement("ALTER TABLE collection_route_stops ADD CONSTRAINT collection_route_stops_status_check CHECK (status IN ('pending', 'cancelled', 'in_progress', 'visited', 'skipped', 'rescheduled'))");
        DB::statement('ALTER TABLE collection_route_stops ADD CONSTRAINT collection_route_stops_sequence_check CHECK (sequence > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_route_stops');
        Schema::dropIfExists('collection_routes');
    }
};
