<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->unsignedBigInteger('authorization_version')->default(1));
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->unsignedBigInteger('authorization_version')->default(1);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 48);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('tenant_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_id']);
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->foreignUuid('feature_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('role', 32);
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16)->default('all');
            $table->timestamps();
            $table->unique(['role', 'permission_id']);
        });
        Schema::create('membership_permission_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('membership_id');
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->string('effect', 8);
            $table->string('scope', 16)->default('all');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'membership_id', 'permission_id']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE membership_permission_overrides ADD CONSTRAINT membership_permission_overrides_effect_check CHECK (effect IN ('allow','deny'))");
        DB::statement("ALTER TABLE membership_permission_overrides ADD CONSTRAINT membership_permission_overrides_scope_check CHECK (scope IN ('own','assigned','team','all'))");

        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('sale_number', 32);
            $table->uuid('customer_id');
            $table->uuid('seller_id');
            $table->string('sale_type', 16);
            $table->string('status', 24)->default('confirmed');
            $table->decimal('total_amount', 18, 2);
            $table->string('currency', 3)->default('ARS');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sale_number']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'seller_id'])->references(['tenant_id', 'user_id'])->on('tenant_memberships');
        });
        Schema::create('installment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('sale_id');
            $table->unsignedSmallInteger('installment_count');
            $table->string('frequency', 16);
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('financial_charge_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sale_id']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales');
        });
        Schema::create('installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('plan_id');
            $table->uuid('customer_id');
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('outstanding_amount', 18, 2);
            $table->string('status', 24)->default('pending');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'plan_id', 'sequence']);
            $table->foreign(['tenant_id', 'plan_id'])->references(['tenant_id', 'id'])->on('installment_plans');
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->index(['tenant_id', 'customer_id', 'due_date']);
        });
        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_amount_check CHECK (amount > 0 AND outstanding_amount >= 0 AND outstanding_amount <= amount)');

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('customer_id');
            $table->uuid('received_by');
            $table->string('method', 24);
            $table->string('origin', 24);
            $table->decimal('amount', 18, 2);
            $table->decimal('unallocated_amount', 18, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('status', 32);
            $table->timestamp('received_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->uuid('mobile_operation_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'mobile_operation_id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'received_by'])->references(['tenant_id', 'user_id'])->on('tenant_memberships');
            $table->index(['tenant_id', 'customer_id', 'received_at']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash','bank_transfer'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('confirmed','pending_verification','under_review','reversed'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0 AND unallocated_amount >= 0 AND unallocated_amount <= amount)');
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('payment_id');
            $table->uuid('installment_id');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'payment_id', 'installment_id']);
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
            $table->foreign(['tenant_id', 'installment_id'])->references(['tenant_id', 'id'])->on('installments');
        });
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_check CHECK (amount > 0)');
        Schema::create('customer_credits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('customer_id');
            $table->uuid('payment_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('remaining_amount', 18, 2);
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
        });
        Schema::create('payment_conflicts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('payment_id');
            $table->string('type', 48);
            $table->string('status', 24)->default('open');
            $table->jsonb('details')->default('{}');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
        });
        Schema::create('payment_reversals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('payment_id');
            $table->foreignUuid('reversed_by')->constrained('users');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
            $table->unique(['tenant_id', 'payment_id']);
        });
        Schema::create('cash_settlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('collector_id');
            $table->decimal('declared_amount', 18, 2)->default(0);
            $table->decimal('approved_amount', 18, 2)->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'collector_id'])->references(['tenant_id', 'user_id'])->on('tenant_memberships');
            $table->index(['tenant_id', 'collector_id', 'status']);
        });
        Schema::create('cash_settlement_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('settlement_id');
            $table->uuid('payment_id');
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->unique(['tenant_id', 'payment_id']);
            $table->foreign(['tenant_id', 'settlement_id'])->references(['tenant_id', 'id'])->on('cash_settlements');
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments');
        });
        Schema::create('cash_discrepancies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('settlement_id');
            $table->decimal('expected_amount', 18, 2);
            $table->decimal('declared_amount', 18, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'settlement_id'])->references(['tenant_id', 'id'])->on('cash_settlements');
        });
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('membership_id');
            $table->string('device_identifier', 128);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'membership_id', 'device_identifier']);
            $table->foreign(['tenant_id', 'membership_id'])->references(['tenant_id', 'id'])->on('tenant_memberships')->cascadeOnDelete();
        });
        Schema::create('mobile_sync_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('device_id');
            $table->uuid('operation_id');
            $table->string('type', 64);
            $table->string('payload_hash', 64);
            $table->string('status', 32);
            $table->jsonb('result')->default('{}');
            $table->timestamp('recorded_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'operation_id']);
            $table->foreign(['tenant_id', 'device_id'])->references(['tenant_id', 'id'])->on('mobile_devices');
        });
    }

    public function down(): void
    {
        foreach (['mobile_sync_operations', 'mobile_devices', 'cash_discrepancies', 'cash_settlement_items', 'cash_settlements', 'payment_reversals', 'payment_conflicts', 'customer_credits', 'payment_allocations', 'payments', 'installments', 'installment_plans', 'sales', 'membership_permission_overrides', 'role_permissions', 'permissions', 'tenant_features', 'features'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropColumn('authorization_version');
        });
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('authorization_version'));
    }
};
