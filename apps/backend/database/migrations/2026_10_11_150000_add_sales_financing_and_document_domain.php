<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained();
            $table->string('disk'); $table->string('bucket'); $table->string('object_key')->unique();
            $table->string('original_name'); $table->string('mime_type'); $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable(); $table->string('status', 24)->default('pending_upload');
            $table->foreignUuid('uploaded_by')->constrained('users'); $table->timestamps(); $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('customer_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained(); $table->uuid('customer_id'); $table->uuid('stored_document_id');
            $table->string('document_type', 48); $table->string('verification_status', 24)->default('pending');
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('verified_at')->nullable(); $table->text('rejection_reason')->nullable(); $table->foreignUuid('uploaded_by')->constrained('users'); $table->timestamps();
            $table->unique(['tenant_id','id']); $table->foreign(['tenant_id','customer_id'])->references(['tenant_id','id'])->on('customers'); $table->foreign(['tenant_id','stored_document_id'])->references(['tenant_id','id'])->on('stored_documents');
        });
        Schema::create('sale_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained(); $table->string('application_number', 32); $table->uuid('quotation_id')->nullable(); $table->uuid('customer_id'); $table->uuid('seller_id'); $table->string('sale_type',16); $table->string('status',24)->default('draft');
            $table->decimal('requested_total',18,2)->default(0); $table->decimal('requested_down_payment',18,2)->default(0); $table->decimal('requested_financing_charge',18,2)->default(0); $table->unsignedSmallInteger('requested_installment_count')->nullable(); $table->string('requested_frequency',16)->nullable(); $table->date('requested_first_due_date')->nullable(); $table->text('notes')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
            $table->unique(['tenant_id','id']); $table->unique(['tenant_id','application_number']); $table->foreign(['tenant_id','customer_id'])->references(['tenant_id','id'])->on('customers'); $table->foreign(['tenant_id','seller_id'])->references(['tenant_id','user_id'])->on('tenant_memberships'); $table->foreign(['tenant_id','quotation_id'])->references(['tenant_id','id'])->on('quotations');
        });
        Schema::create('sale_application_items', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained(); $table->uuid('sale_application_id'); $table->uuid('product_id')->nullable(); $table->string('description'); $table->string('sku_snapshot')->nullable(); $table->decimal('quantity',12,3); $table->decimal('unit_price',18,2); $table->decimal('discount_amount',18,2)->default(0); $table->decimal('line_total',18,2); $table->unsignedInteger('position'); $table->timestamps();
            $table->foreign(['tenant_id','sale_application_id'])->references(['tenant_id','id'])->on('sale_applications'); $table->foreign(['tenant_id','product_id'])->references(['tenant_id','id'])->on('products'); $table->unique(['tenant_id','sale_application_id','position']);
        });
        Schema::create('credit_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained(); $table->uuid('sale_application_id'); $table->string('status',24)->default('pending'); $table->decimal('requested_amount',18,2); $table->decimal('approved_amount',18,2)->nullable(); $table->decimal('approved_down_payment',18,2)->nullable(); $table->decimal('approved_financing_charge',18,2)->nullable(); $table->unsignedSmallInteger('approved_installment_count')->nullable(); $table->string('approved_frequency',16)->nullable(); $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete(); $table->text('review_notes')->nullable(); $table->timestamp('reviewed_at')->nullable(); $table->timestamps(); $table->foreign(['tenant_id','sale_application_id'])->references(['tenant_id','id'])->on('sale_applications');
        });
        Schema::table('sales', function (Blueprint $table): void { $table->uuid('sale_application_id')->nullable()->after('sale_number'); $table->uuid('quotation_id')->nullable(); $table->decimal('subtotal',18,2)->default(0); $table->decimal('discount_amount',18,2)->default(0); $table->decimal('down_payment_amount',18,2)->default(0); $table->decimal('financed_principal',18,2)->default(0); $table->decimal('financing_charge_amount',18,2)->default(0); $table->decimal('financed_total',18,2)->default(0); $table->timestamp('confirmed_at')->nullable(); $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('cancelled_at')->nullable(); $table->text('cancellation_reason')->nullable(); });
        Schema::table('installment_plans', function (Blueprint $table): void { $table->date('first_due_date')->nullable(); $table->string('rounding_policy',32)->default('last_installment'); $table->jsonb('schedule_snapshot')->nullable(); });
        Schema::create('customer_account_entries', function (Blueprint $table): void { $table->uuid('id')->primary(); $table->foreignUuid('tenant_id')->constrained(); $table->uuid('customer_id'); $table->uuid('sale_id')->nullable(); $table->uuid('installment_id')->nullable(); $table->string('entry_type',32); $table->decimal('amount',18,2); $table->date('due_date')->nullable(); $table->timestamp('created_at')->useCurrent(); $table->foreign(['tenant_id','customer_id'])->references(['tenant_id','id'])->on('customers'); $table->foreign(['tenant_id','sale_id'])->references(['tenant_id','id'])->on('sales'); $table->foreign(['tenant_id','installment_id'])->references(['tenant_id','id'])->on('installments'); });
        DB::statement("ALTER TABLE sale_applications ADD CONSTRAINT sale_applications_status_check CHECK (status IN ('draft','pending_documents','pending_review','changes_requested','approved','rejected','converted','cancelled'))");
    }
    public function down(): void { Schema::dropIfExists('customer_account_entries'); Schema::dropIfExists('credit_assessments'); Schema::dropIfExists('sale_application_items'); Schema::dropIfExists('sale_applications'); Schema::dropIfExists('customer_documents'); Schema::dropIfExists('stored_documents'); }
};
