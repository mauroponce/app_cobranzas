<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE late_fee_policies ADD CONSTRAINT late_fee_policies_positive_check CHECK (version > 0 AND grace_days >= 0 AND daily_basis_points >= 0 AND cap_basis_points >= 0)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_sequence_check CHECK (sequence > 0)');
        DB::statement('ALTER TABLE sale_application_items ADD CONSTRAINT sale_application_items_position_check CHECK (position > 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_position_check CHECK (position > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT sale_items_position_check');
        DB::statement('ALTER TABLE sale_application_items DROP CONSTRAINT sale_application_items_position_check');
        DB::statement('ALTER TABLE installments DROP CONSTRAINT installments_sequence_check');
        DB::statement('ALTER TABLE late_fee_policies DROP CONSTRAINT late_fee_policies_positive_check');
    }
};
