<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('sale_id');
            $table->uuid('stored_document_id');
            $table->string('document_type', 32);
            $table->timestamps();
            $table->unique(['tenant_id', 'sale_id', 'document_type']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales');
            $table->foreign(['tenant_id', 'stored_document_id'])->references(['tenant_id', 'id'])->on('stored_documents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_documents');
    }
};
