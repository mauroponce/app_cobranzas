<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_declarations', function (Blueprint $table): void {
            $table->string('payload_hash', 64)->nullable()->after('operation_id');
        });

        Schema::table('collection_declaration_components', function (Blueprint $table): void {
            $table->string('status', 24)->default('pending')->after('amount');
            $table->decimal('confirmed_amount', 18, 2)->nullable()->after('status');
            $table->uuid('payment_id')->nullable()->after('confirmed_amount');
            $table->foreignUuid('reconciled_by_user_id')->nullable()->after('payment_id')->constrained('users')->nullOnDelete();
            $table->timestampTz('reconciled_at')->nullable()->after('reconciled_by_user_id');
            $table->unique(['tenant_id', 'id'], 'collection_components_tenant_id_id_unique');
            $table->unique(['tenant_id', 'payment_id'], 'collection_components_payment_unique');
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
        });
        DB::statement("ALTER TABLE collection_declaration_components ADD CONSTRAINT collection_declaration_components_status_check CHECK (status IN ('pending','confirmed','rejected'))");
        DB::statement('ALTER TABLE collection_declaration_components ADD CONSTRAINT collection_declaration_components_confirmed_amount_check CHECK (confirmed_amount IS NULL OR (confirmed_amount >= 0 AND confirmed_amount <= amount))');

        Schema::table('cash_settlements', function (Blueprint $table): void {
            $table->uuid('route_id')->nullable()->after('tenant_id');
            $table->unique(['tenant_id', 'route_id'], 'cash_settlements_route_unique');
            $table->foreign(['tenant_id', 'route_id'])->references(['tenant_id', 'id'])->on('collection_routes');
        });

        Schema::table('cash_settlement_items', function (Blueprint $table): void {
            $table->uuid('declaration_component_id')->nullable()->after('settlement_id');
            $table->decimal('received_amount', 18, 2)->nullable()->after('amount');
            $table->unique(['tenant_id', 'declaration_component_id'], 'cash_settlement_component_unique');
            $table->foreign(['tenant_id', 'declaration_component_id'], 'cash_settlement_items_component_fk')
                ->references(['tenant_id', 'id'])->on('collection_declaration_components');
        });
        DB::statement('ALTER TABLE cash_settlement_items ALTER COLUMN payment_id DROP NOT NULL');
        DB::statement('ALTER TABLE cash_settlement_items ADD CONSTRAINT cash_settlement_items_source_check CHECK (payment_id IS NOT NULL OR declaration_component_id IS NOT NULL)');

        Schema::table('cash_discrepancies', function (Blueprint $table): void {
            $table->decimal('received_amount', 18, 2)->nullable()->after('declared_amount');
            $table->foreignUuid('recorded_by_user_id')->nullable()->after('reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('collection_visit_reassignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('source_visit_id');
            $table->uuid('target_visit_id');
            $table->uuid('from_route_id');
            $table->uuid('to_route_id');
            $table->uuid('from_collector_membership_id');
            $table->uuid('to_collector_membership_id');
            $table->text('reason');
            $table->foreignUuid('reassigned_by_user_id')->constrained('users');
            $table->timestamps();
            $table->foreign(['tenant_id', 'source_visit_id'])->references(['tenant_id', 'id'])->on('collection_visits');
            $table->foreign(['tenant_id', 'target_visit_id'])->references(['tenant_id', 'id'])->on('collection_visits');
            $table->foreign(['tenant_id', 'from_route_id'])->references(['tenant_id', 'id'])->on('collection_routes');
            $table->foreign(['tenant_id', 'to_route_id'])->references(['tenant_id', 'id'])->on('collection_routes');
            $table->foreign(['tenant_id', 'from_collector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
            $table->foreign(['tenant_id', 'to_collector_membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships');
        });

        DB::statement('ALTER TABLE collection_routes DROP CONSTRAINT IF EXISTS collection_routes_status_check');
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_status_check CHECK (status IN ('draft','published','in_progress','submitted','completed','cancelled'))");
        DB::statement('ALTER TABLE collection_routes DROP CONSTRAINT IF EXISTS collection_routes_dates_check');
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_dates_check CHECK ((published_at IS NULL OR status IN ('published','in_progress','submitted','completed','cancelled')) AND (started_at IS NULL OR published_at IS NOT NULL) AND (completed_at IS NULL OR started_at IS NOT NULL) AND (cancelled_at IS NULL OR status = 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_visit_reassignments');
        Schema::table('cash_discrepancies', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by_user_id']);
            $table->dropColumn(['received_amount', 'recorded_by_user_id']);
        });
        DB::statement('ALTER TABLE cash_settlement_items DROP CONSTRAINT IF EXISTS cash_settlement_items_source_check');
        Schema::table('cash_settlement_items', function (Blueprint $table): void {
            $table->dropForeign('cash_settlement_items_component_fk');
            $table->dropUnique('cash_settlement_component_unique');
            $table->dropColumn(['declaration_component_id', 'received_amount']);
        });
        Schema::table('cash_settlements', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'route_id']);
            $table->dropUnique('cash_settlements_route_unique');
            $table->dropColumn('route_id');
        });
        DB::statement('ALTER TABLE collection_declaration_components DROP CONSTRAINT IF EXISTS collection_declaration_components_confirmed_amount_check');
        DB::statement('ALTER TABLE collection_declaration_components DROP CONSTRAINT IF EXISTS collection_declaration_components_status_check');
        Schema::table('collection_declaration_components', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'payment_id']);
            $table->dropForeign(['reconciled_by_user_id']);
            $table->dropUnique('collection_components_payment_unique');
            $table->dropUnique('collection_components_tenant_id_id_unique');
            $table->dropColumn(['status', 'confirmed_amount', 'payment_id', 'reconciled_by_user_id', 'reconciled_at']);
        });
        Schema::table('collection_declarations', fn (Blueprint $table) => $table->dropColumn('payload_hash'));
        DB::statement('ALTER TABLE collection_routes DROP CONSTRAINT IF EXISTS collection_routes_status_check');
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_status_check CHECK (status IN ('draft','published','in_progress','completed','cancelled'))");
        DB::statement('ALTER TABLE collection_routes DROP CONSTRAINT IF EXISTS collection_routes_dates_check');
        DB::statement("ALTER TABLE collection_routes ADD CONSTRAINT collection_routes_dates_check CHECK ((published_at IS NULL OR status IN ('published','in_progress','completed','cancelled')) AND (started_at IS NULL OR published_at IS NOT NULL) AND (completed_at IS NULL OR started_at IS NOT NULL) AND (cancelled_at IS NULL OR status = 'cancelled'))");
    }
};
