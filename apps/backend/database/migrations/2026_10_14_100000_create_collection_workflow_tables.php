<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('route_id');
            $table->uuid('sale_id');
            $table->uuid('customer_id');
            $table->unsignedInteger('sequence');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->decimal('scheduled_amount', 18, 2)->default(0);
            $table->jsonb('obligation_snapshot')->default('[]');
            $table->string('outcome', 24)->nullable();
            $table->string('no_payment_reason', 48)->nullable();
            $table->text('no_payment_notes')->nullable();
            $table->timestampTz('recorded_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['route_id', 'sequence']);
            $table->foreign(['tenant_id', 'route_id'])->references(['tenant_id', 'id'])->on('collection_routes')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'sale_id', 'customer_id'])->references(['tenant_id', 'id', 'customer_id'])->on('sales');
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->index(['tenant_id', 'sale_id', 'status']);
        });
        DB::statement("ALTER TABLE collection_visits ADD CONSTRAINT collection_visits_status_check CHECK (status IN ('pending','recorded','reassigned','cancelled','closed'))");
        DB::statement("ALTER TABLE collection_visits ADD CONSTRAINT collection_visits_outcome_check CHECK (outcome IS NULL OR outcome IN ('paid','no_payment','no_debt'))");

        Schema::create('collection_declarations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('visit_id');
            $table->uuid('customer_id');
            $table->uuid('declared_by_user_id');
            $table->uuid('operation_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('status', 24)->default('pending');
            $table->timestampTz('declared_at');
            $table->timestampTz('received_at')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'operation_id']);
            $table->foreign(['tenant_id', 'visit_id'])->references(['tenant_id', 'id'])->on('collection_visits');
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign('declared_by_user_id')->references('id')->on('users');
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE collection_declarations ADD CONSTRAINT collection_declarations_status_check CHECK (status IN ('pending','approved','rejected','conflicted'))");
        DB::statement('ALTER TABLE collection_declarations ADD CONSTRAINT collection_declarations_amount_check CHECK (amount > 0)');

        Schema::create('collection_declaration_components', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('declaration_id');
            $table->string('method', 24);
            $table->decimal('amount', 18, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'declaration_id'])->references(['tenant_id', 'id'])->on('collection_declarations')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE collection_declaration_components ADD CONSTRAINT collection_declaration_components_method_check CHECK (method IN ('cash','bank_transfer'))");
        DB::statement('ALTER TABLE collection_declaration_components ADD CONSTRAINT collection_declaration_components_amount_check CHECK (amount > 0)');

        Schema::create('collection_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('route_id')->nullable();
            $table->uuid('visit_id')->nullable();
            $table->uuid('declaration_id')->nullable();
            $table->string('type', 48);
            $table->string('status', 24)->default('open');
            $table->text('details');
            $table->foreignUuid('reported_by')->constrained('users');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'route_id'])->references(['tenant_id', 'id'])->on('collection_routes')->nullOnDelete();
            $table->foreign(['tenant_id', 'visit_id'])->references(['tenant_id', 'id'])->on('collection_visits')->nullOnDelete();
            $table->foreign(['tenant_id', 'declaration_id'])->references(['tenant_id', 'id'])->on('collection_declarations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_incidents');
        Schema::dropIfExists('collection_declaration_components');
        Schema::dropIfExists('collection_declarations');
        Schema::dropIfExists('collection_visits');
    }
};
