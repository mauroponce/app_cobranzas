<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_applications', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'quotation_id']);
        });
        Schema::table('sale_application_items', fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        Schema::table('credit_assessments', fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        Schema::table('sales', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'sale_application_id']);
            $table->foreign(['tenant_id', 'sale_application_id'])->references(['tenant_id', 'id'])->on('sale_applications');
            $table->foreign(['tenant_id', 'quotation_id'])->references(['tenant_id', 'id'])->on('quotations');
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('sale_id');
            $table->uuid('product_id')->nullable();
            $table->string('description');
            $table->string('sku_snapshot')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales');
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
            $table->unique(['tenant_id', 'sale_id', 'position']);
        });
        Schema::create('sale_application_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('sale_application_id');
            $table->foreignUuid('actor_id')->constrained('users');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['tenant_id', 'sale_application_id'])->references(['tenant_id', 'id'])->on('sale_applications');
        });
        Schema::table('customer_documents', function (Blueprint $table): void {
            $table->uuid('sale_application_id')->nullable();
            $table->foreign(['tenant_id', 'sale_application_id'])->references(['tenant_id', 'id'])->on('sale_applications');
        });
        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->string('status', 16)->default('open');
            $table->unique(['tenant_id', 'id']);
        });
        DB::statement('CREATE UNIQUE INDEX customer_account_entries_installment_unique ON customer_account_entries (tenant_id, installment_id) WHERE installment_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX customer_account_entries_sale_kind_unique ON customer_account_entries (tenant_id, sale_id, entry_type) WHERE installment_id IS NULL AND sale_id IS NOT NULL');
        DB::statement('ALTER TABLE customer_account_entries ADD CONSTRAINT customer_account_entries_amount_check CHECK (amount > 0 AND outstanding_amount >= 0 AND outstanding_amount <= amount)');
        DB::statement('ALTER TABLE sale_applications ADD CONSTRAINT sale_applications_amount_check CHECK (requested_total >= 0 AND requested_down_payment >= 0 AND requested_down_payment <= requested_total AND requested_financing_charge >= 0)');
        DB::statement('ALTER TABLE credit_assessments ADD CONSTRAINT credit_assessments_amount_check CHECK (requested_amount >= 0 AND (approved_amount IS NULL OR approved_amount >= 0) AND (approved_down_payment IS NULL OR approved_down_payment >= 0) AND (approved_financing_charge IS NULL OR approved_financing_charge >= 0))');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_amount_check CHECK (total_amount >= 0 AND subtotal >= 0 AND discount_amount >= 0 AND down_payment_amount >= 0 AND financed_principal >= 0 AND financing_charge_amount >= 0 AND financed_total >= 0 AND subtotal - discount_amount = total_amount AND total_amount - down_payment_amount = financed_principal AND financed_principal + financing_charge_amount = financed_total)');
        DB::statement('ALTER TABLE installment_plans ADD CONSTRAINT installment_plans_total_check CHECK (installment_count > 0 AND principal_amount >= 0 AND financial_charge_amount >= 0 AND total_amount = principal_amount + financial_charge_amount)');
        DB::statement('ALTER TABLE sale_application_items ADD CONSTRAINT sale_application_items_amount_check CHECK (quantity > 0 AND unit_price >= 0 AND discount_amount >= 0 AND line_total > 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_amount_check CHECK (quantity > 0 AND unit_price >= 0 AND line_total > 0)');
        Schema::create('late_fee_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->unsignedInteger('version');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedInteger('daily_basis_points')->default(0);
            $table->unsignedInteger('cap_basis_points')->default(0);
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'version']);
        });
        DB::statement('ALTER TABLE late_fee_policies ADD CONSTRAINT late_fee_policies_period_check CHECK (effective_until IS NULL OR effective_until >= effective_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fee_policies');
        Schema::dropIfExists('sale_application_status_history');
        Schema::dropIfExists('sale_items');
        Schema::table('customer_documents', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'sale_application_id']);
            $table->dropColumn('sale_application_id');
        });
        Schema::table('customer_account_entries', fn (Blueprint $table) => $table->dropColumn(['outstanding_amount', 'status']));
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'sale_application_id']);
            $table->dropForeign(['tenant_id', 'quotation_id']);
            $table->dropUnique(['tenant_id', 'sale_application_id']);
        });
        Schema::table('sale_applications', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'quotation_id']));
    }
};
