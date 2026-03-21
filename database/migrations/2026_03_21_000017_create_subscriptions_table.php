<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'expired'])->default('trialing');
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->date('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
