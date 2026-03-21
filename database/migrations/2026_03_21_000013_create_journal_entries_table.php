<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entry_number', 30);
            $table->date('entry_date');
            $table->string('description');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->enum('source', [
                'manual',       // manual journal entry
                'invoice',      // auto-created from invoice
                'bill',         // auto-created from bill
                'payment',      // auto-created from payment
                'opening',      // opening balances
            ])->default('manual');
            // Polymorphic link to the source document (invoice, bill, payment)
            $table->nullableMorphs('sourceable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'entry_number']);
            $table->index(['company_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
