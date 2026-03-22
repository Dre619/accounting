<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->enum('item_type', ['goods', 'service'])->default('service')->after('sort_order');
            $table->unsignedBigInteger('cls_code_id')->nullable()->after('item_type')
                ->comment('FK to goods_codes.id or service_codes.id depending on item_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'cls_code_id']);
        });
    }
};
