<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_annual', 10, 2)->nullable()->comment('Annual total (discounted)');
            $table->string('currency', 3)->default('ZMW');
            $table->unsignedSmallInteger('max_users')->default(1);
            $table->json('features')->nullable()->comment('Array of feature strings shown on pricing page');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
