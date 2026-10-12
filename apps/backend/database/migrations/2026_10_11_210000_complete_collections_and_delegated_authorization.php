<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_features', function (Blueprint $table): void {
            $table->boolean('is_available')->default(true)->after('feature_id');
            $table->timestamp('available_at')->nullable()->after('is_available');
        });
        Schema::table('permissions', fn (Blueprint $table) => $table->jsonb('allowed_scopes')->default('["all"]'));
        Schema::table('membership_permission_overrides', function (Blueprint $table): void {
            $table->string('scope', 16)->nullable()->change();
        });
        DB::statement('ALTER TABLE membership_permission_overrides DROP CONSTRAINT IF EXISTS membership_permission_overrides_scope_check');
        DB::statement("ALTER TABLE membership_permission_overrides ADD CONSTRAINT membership_permission_overrides_scope_check CHECK (scope IS NULL OR scope IN ('own','assigned','team','all'))");

        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id', 'customer_id'], 'customer_account_entries_tenant_id_id_customer_id_unique');
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('received_by_user_id')->nullable()->after('received_by');
            $table->uuid('registered_by_user_id')->nullable()->after('received_by_user_id');
            $table->string('source', 24)->nullable()->after('origin');
            $table->timestamp('reversed_at')->nullable()->after('confirmed_at');
            $table->uuid('client_operation_id')->nullable()->after('mobile_operation_id');
            $table->string('reference')->nullable()->after('external_reference');
            $table->unique(['tenant_id', 'id', 'customer_id'], 'payments_tenant_id_id_customer_id_unique');
            $table->unique(['tenant_id', 'client_operation_id'], 'payments_tenant_id_client_operation_id_unique');
        });
        DB::statement('UPDATE payments SET received_by_user_id = received_by, registered_by_user_id = received_by, source = origin, reference = external_reference WHERE received_by_user_id IS NULL');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('confirmed','pending_verification','under_review','rejected','reversed'))");

        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable()->after('payment_id');
            $table->uuid('account_entry_id')->nullable()->after('customer_id');
            $table->uuid('reversed_by_allocation_id')->nullable()->after('account_entry_id');
        });
        DB::statement('ALTER TABLE payment_allocations ALTER COLUMN installment_id DROP NOT NULL');
        DB::statement('ALTER TABLE payment_allocations DROP CONSTRAINT IF EXISTS payment_allocations_tenant_id_payment_id_installment_id_unique');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_target_unique UNIQUE NULLS NOT DISTINCT (tenant_id, payment_id, account_entry_id)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_entry_customer_fk FOREIGN KEY (tenant_id, account_entry_id, customer_id) REFERENCES customer_account_entries (tenant_id, id, customer_id)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_payment_customer_fk FOREIGN KEY (tenant_id, payment_id, customer_id) REFERENCES payments (tenant_id, id, customer_id)');
        DB::statement('UPDATE payment_allocations allocation SET customer_id = payment.customer_id, account_entry_id = entry.id FROM payments payment, customer_account_entries entry WHERE payment.id = allocation.payment_id AND payment.tenant_id = allocation.tenant_id AND entry.tenant_id = allocation.tenant_id AND entry.installment_id = allocation.installment_id');

        Schema::create('payment_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('payment_id');
            $table->uuid('stored_document_id');
            $table->string('kind', 32);
            $table->timestamps();
            $table->unique(['tenant_id', 'payment_id', 'kind']);
            $table->foreign(['tenant_id', 'payment_id'])->references(['tenant_id', 'id'])->on('payments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'stored_document_id'])->references(['tenant_id', 'id'])->on('stored_documents')->cascadeOnDelete();
        });
        Schema::create('cash_settlement_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('settlement_id');
            $table->uuid('stored_document_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->timestamps();
            $table->foreign(['tenant_id', 'settlement_id'])->references(['tenant_id', 'id'])->on('cash_settlements')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'stored_document_id'])->references(['tenant_id', 'id'])->on('stored_documents')->nullOnDelete();
        });
        Schema::table('cash_settlements', function (Blueprint $table): void {
            $table->uuid('presented_by_user_id')->nullable()->after('collector_id');
            $table->decimal('received_amount', 18, 2)->nullable()->after('approved_amount');
            $table->timestamp('presented_at')->nullable()->after('submitted_at');
        });
        DB::statement('UPDATE cash_settlements SET presented_by_user_id = collector_id WHERE presented_by_user_id IS NULL');
        DB::statement('ALTER TABLE cash_settlements DROP CONSTRAINT IF EXISTS cash_settlements_status_check');
        DB::statement("ALTER TABLE cash_settlements ADD CONSTRAINT cash_settlements_status_check CHECK (status IN ('draft','submitted','under_review','approved','disputed','cancelled'))");
        DB::statement('ALTER TABLE cash_settlement_items DROP CONSTRAINT IF EXISTS cash_settlement_items_tenant_id_payment_id_unique');
        DB::statement('ALTER TABLE cash_settlement_items ADD CONSTRAINT cash_settlement_items_settlement_payment_unique UNIQUE (tenant_id, settlement_id, payment_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_settlement_receipts');
        Schema::dropIfExists('payment_documents');
        // Financial history intentionally remains forward-only in production; down is for local rollback.
        Schema::table('cash_settlements', function (Blueprint $table): void {
            $table->dropColumn(['presented_by_user_id', 'received_amount', 'presented_at']);
        });
        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->dropColumn(['customer_id', 'account_entry_id', 'reversed_by_allocation_id']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_tenant_id_id_customer_id_unique');
            $table->dropUnique('payments_tenant_id_client_operation_id_unique');
            $table->dropColumn(['received_by_user_id', 'registered_by_user_id', 'source', 'reversed_at', 'client_operation_id', 'reference']);
        });
        Schema::table('customer_account_entries', fn (Blueprint $table) => $table->dropUnique('customer_account_entries_tenant_id_id_customer_id_unique'));
        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn('allowed_scopes'));
        Schema::table('tenant_features', fn (Blueprint $table) => $table->dropColumn(['is_available', 'available_at']));
    }
};
