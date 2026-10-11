<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        Schema::create('tenant_counters', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('kind');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->primary(['tenant_id', 'kind']);
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('customer_number', 32);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('document_type', 16)->nullable();
            $table->string('document_number', 32)->nullable();
            $table->string('document_normalized', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp_phone', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'customer_number']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
        });
        DB::statement('CREATE UNIQUE INDEX customers_document_unique ON customers (tenant_id, document_type, document_normalized) WHERE document_normalized IS NOT NULL AND deleted_at IS NULL');
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('active','inactive','blocked'))");
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('customer_id');
            $table->string('label');
            $table->string('street');
            $table->string('street_number', 30);
            $table->string('floor', 30)->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('AR');
            $table->text('reference')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
        });
        DB::statement('CREATE UNIQUE INDEX customer_primary_address_unique ON customer_addresses (tenant_id, customer_id) WHERE is_primary AND deleted_at IS NULL');
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('brand')->nullable();
            $table->string('unit')->default('unit');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sku']);
            $table->foreign(['tenant_id', 'category_id'])->references(['tenant_id', 'id'])->on('product_categories');
            $table->index(['tenant_id', 'name']);
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('active','inactive'))");
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('name');
            $table->string('code');
            $table->string('type', 16);
            $table->string('currency', 3)->default('ARS');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
        });
        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_list_type_check CHECK (type IN ('public','wholesale','internal'))");
        DB::statement('CREATE UNIQUE INDEX price_lists_default_unique ON price_lists (tenant_id) WHERE is_default AND is_active');
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('price_list_id');
            $table->uuid('product_id');
            $table->decimal('amount', 18, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'price_list_id'])->references(['tenant_id', 'id'])->on('price_lists');
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE product_prices ADD CONSTRAINT product_prices_amount_check CHECK (amount >= 0 AND (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from))');
        DB::statement("ALTER TABLE product_prices ADD CONSTRAINT product_prices_period_exclusion EXCLUDE USING gist (tenant_id WITH =, price_list_id WITH =, product_id WITH =, daterange(COALESCE(valid_from, '-infinity'::date), COALESCE(valid_until + 1, 'infinity'::date), '[)') WITH &&)");
        Schema::create('product_costs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('product_id');
            $table->decimal('amount', 18, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
        });
        DB::statement('ALTER TABLE product_costs ADD CONSTRAINT product_costs_amount_check CHECK (amount >= 0 AND (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from))');
        DB::statement("ALTER TABLE product_costs ADD CONSTRAINT product_costs_period_exclusion EXCLUDE USING gist (tenant_id WITH =, product_id WITH =, daterange(COALESCE(valid_from, '-infinity'::date), COALESCE(valid_until + 1, 'infinity'::date), '[)') WITH &&)");
        Schema::create('quotations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->string('quotation_number', 32);
            $table->uuid('customer_id');
            $table->uuid('seller_id');
            $table->string('status', 24)->default('draft');
            $table->uuid('price_list_id');
            $table->string('currency', 3)->default('ARS');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('down_payment_amount', 18, 2)->default(0);
            $table->decimal('financed_amount', 18, 2)->default(0);
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->string('installment_frequency', 16)->nullable();
            $table->decimal('estimated_installment_amount', 18, 2)->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->jsonb('customer_snapshot');
            $table->jsonb('address_snapshot')->nullable();
            $table->timestamps();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'quotation_number']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'price_list_id'])->references(['tenant_id', 'id'])->on('price_lists');
            $table->foreign(['tenant_id', 'seller_id'])->references(['tenant_id', 'user_id'])->on('tenant_memberships');
            $table->index(['tenant_id', 'status', 'created_at']);
        });
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_check CHECK (status IN ('draft','pending_review','approved','sent','accepted','rejected','expired','cancelled'))");
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_amount_check CHECK (subtotal >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND down_payment_amount >= 0 AND financed_amount >= 0 AND down_payment_amount <= total_amount AND financed_amount = total_amount - down_payment_amount)');
        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('quotation_id');
            $table->uuid('product_id')->nullable();
            $table->string('description');
            $table->string('sku_snapshot')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->foreign(['tenant_id', 'quotation_id'])->references(['tenant_id', 'id'])->on('quotations');
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products');
            $table->unique(['tenant_id', 'quotation_id', 'position']);
        });
        DB::statement('ALTER TABLE quotation_items ADD CONSTRAINT quotation_items_amount_check CHECK (quantity > 0 AND unit_price >= 0 AND discount_amount >= 0 AND line_total >= 0)');
        Schema::create('quotation_status_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('quotation_id');
            $table->foreignUuid('actor_user_id')->constrained('users');
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign(['tenant_id', 'quotation_id'])->references(['tenant_id', 'id'])->on('quotations');
        });
        Schema::create('quotation_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->uuid('quotation_id');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('type', 32);
            $table->text('notes');
            $table->timestamp('occurred_at');
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'quotation_id'])->references(['tenant_id', 'id'])->on('quotations');
            $table->index(['tenant_id', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        foreach (['quotation_activities', 'quotation_status_history', 'quotation_items', 'quotations', 'product_costs', 'product_prices', 'price_lists', 'products', 'product_categories', 'customer_addresses', 'customers', 'tenant_counters'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
