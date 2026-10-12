<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sale_applications ADD CONSTRAINT sale_applications_terms_check CHECK ((sale_type = 'cash' AND requested_down_payment = requested_total AND requested_installment_count IS NULL AND requested_frequency IS NULL) OR (sale_type = 'financed' AND requested_down_payment < requested_total AND requested_installment_count BETWEEN 1 AND 120 AND requested_frequency IN ('daily','weekly','biweekly','monthly')))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_state_check CHECK (sale_type IN ('cash','financed') AND status IN ('confirmed','cancelled'))");
        DB::statement("ALTER TABLE installment_plans ADD CONSTRAINT installment_plans_frequency_check CHECK (frequency IN ('daily','weekly','biweekly','monthly') AND installment_count BETWEEN 1 AND 120)");
        DB::statement("ALTER TABLE credit_assessments ADD CONSTRAINT credit_assessments_status_check CHECK (status IN ('approved','rejected'))");
        DB::statement("ALTER TABLE customer_documents ADD CONSTRAINT customer_documents_verification_check CHECK (verification_status IN ('pending','verified','rejected'))");
        DB::statement("ALTER TABLE stored_documents ADD CONSTRAINT stored_documents_status_check CHECK (status IN ('pending_upload','ready','failed','deleted'))");
        DB::statement('ALTER TABLE late_fee_policies ADD CONSTRAINT late_fee_policies_rates_check CHECK (grace_days <= 365 AND daily_basis_points <= 10000 AND cap_basis_points <= 10000)');
    }

    public function down(): void
    {
        foreach (['sale_applications_terms_check' => 'sale_applications', 'sales_state_check' => 'sales', 'installment_plans_frequency_check' => 'installment_plans', 'credit_assessments_status_check' => 'credit_assessments', 'customer_documents_verification_check' => 'customer_documents', 'stored_documents_status_check' => 'stored_documents', 'late_fee_policies_rates_check' => 'late_fee_policies'] as $name => $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
    }
};
