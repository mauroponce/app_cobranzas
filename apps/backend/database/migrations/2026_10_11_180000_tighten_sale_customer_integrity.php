<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->unique(['tenant_id', 'id', 'customer_id']));
        Schema::table('sale_applications', fn (Blueprint $table) => $table->unique(['tenant_id', 'id', 'customer_id']));
        Schema::table('sales', fn (Blueprint $table) => $table->unique(['tenant_id', 'id', 'customer_id']));
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->uuid('customer_id')->nullable();
            $table->unique(['tenant_id', 'id', 'customer_id']);
        });
        DB::statement('UPDATE installment_plans p SET customer_id = s.customer_id FROM sales s WHERE p.tenant_id = s.tenant_id AND p.sale_id = s.id');
        DB::statement('ALTER TABLE installment_plans ALTER COLUMN customer_id SET NOT NULL');
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'sale_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sales');
        });
        Schema::table('installments', function (Blueprint $table): void {
            $table->uuid('sale_id')->nullable();
            $table->unique(['tenant_id', 'id', 'sale_id', 'customer_id']);
        });
        DB::statement('UPDATE installments i SET sale_id = p.sale_id FROM installment_plans p WHERE i.tenant_id = p.tenant_id AND i.plan_id = p.id');
        DB::statement('ALTER TABLE installments ALTER COLUMN sale_id SET NOT NULL');
        Schema::table('installments', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'plan_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('installment_plans');
            $table->foreign(['tenant_id', 'sale_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sales');
        });
        Schema::table('sale_applications', fn (Blueprint $table) => $table->foreign(['tenant_id', 'quotation_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('quotations'));
        Schema::table('sales', fn (Blueprint $table) => $table->foreign(['tenant_id', 'sale_application_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sale_applications'));
        Schema::table('customer_documents', fn (Blueprint $table) => $table->foreign(['tenant_id', 'sale_application_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sale_applications'));
        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'sale_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sales');
            $table->foreign(['tenant_id', 'installment_id', 'sale_id', 'customer_id'])->references(['tenant_id', 'id', 'sale_id', 'customer_id'])->on('installments');
        });
        DB::statement("ALTER TABLE customer_account_entries ADD CONSTRAINT customer_account_entries_kind_check CHECK ((entry_type IN ('cash','down_payment') AND sale_id IS NOT NULL AND installment_id IS NULL) OR (entry_type = 'installment' AND sale_id IS NOT NULL AND installment_id IS NOT NULL))");
        DB::statement("ALTER TABLE late_fee_policies ADD CONSTRAINT late_fee_policies_period_exclusion EXCLUDE USING gist (tenant_id WITH =, daterange(effective_from, COALESCE(effective_until + 1, 'infinity'::date), '[)') WITH &&)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE late_fee_policies DROP CONSTRAINT late_fee_policies_period_exclusion');
        DB::statement('ALTER TABLE customer_account_entries DROP CONSTRAINT customer_account_entries_kind_check');
        Schema::table('customer_account_entries', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'installment_id', 'sale_id', 'customer_id']);
            $table->dropForeign(['tenant_id', 'sale_id', 'customer_id']);
        });
        Schema::table('customer_documents', fn (Blueprint $table) => $table->dropForeign(['tenant_id', 'sale_application_id', 'customer_id']));
        Schema::table('sales', fn (Blueprint $table) => $table->dropForeign(['tenant_id', 'sale_application_id', 'customer_id']));
        Schema::table('sale_applications', fn (Blueprint $table) => $table->dropForeign(['tenant_id', 'quotation_id', 'customer_id']));
        Schema::table('installments', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'plan_id', 'customer_id']);
            $table->dropForeign(['tenant_id', 'sale_id', 'customer_id']);
            $table->dropUnique(['tenant_id', 'id', 'sale_id', 'customer_id']);
            $table->dropColumn('sale_id');
        });
        Schema::table('installment_plans', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'sale_id', 'customer_id']);
            $table->dropUnique(['tenant_id', 'id', 'customer_id']);
            $table->dropColumn('customer_id');
        });
        Schema::table('sales', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'id', 'customer_id']));
        Schema::table('sale_applications', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'id', 'customer_id']));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'id', 'customer_id']));
    }
};
