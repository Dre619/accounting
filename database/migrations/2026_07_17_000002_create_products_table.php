<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            // Only 'inventory' items track stock quantity & average cost.
            $table->enum('type', ['inventory', 'service', 'non_inventory'])->default('inventory');
            $table->string('unit_of_measure')->default('each');
            $table->decimal('sales_price', 15, 2)->default(0);

            // Accounts that default into invoice/bill lines and drive postings.
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('purchase_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();

            // ZRA classification — mirrors invoice_items.item_type / cls_code_id.
            $table->enum('item_type', ['goods', 'service'])->default('goods');
            $table->unsignedBigInteger('cls_code_id')->nullable();

            // Cached running values (source of truth is stock_movements).
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->decimal('reorder_point', 15, 3)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
