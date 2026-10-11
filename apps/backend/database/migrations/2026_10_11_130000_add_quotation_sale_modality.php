<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->string('sale_modality', 16)->default('cash'));
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_modality_check CHECK (sale_modality IN ('cash','financed'))");
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('sale_modality'));
    }
};
