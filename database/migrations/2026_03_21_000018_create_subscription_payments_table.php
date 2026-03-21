<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');
            $table->enum('method', ['online', 'offline'])->default('online');
            $table->enum('status', ['pending', 'completed', 'failed', 'rejected'])->default('pending');
            $table->string('reference', 100)->nullable()->unique()->comment('Lenco reference or manual ref');
            $table->string('proof_path')->nullable()->comment('Uploaded proof of payment (offline)');
            $table->text('notes')->nullable()->comment('Admin notes or offline instructions');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
