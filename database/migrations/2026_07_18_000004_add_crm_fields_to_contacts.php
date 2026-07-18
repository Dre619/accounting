<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // A contact can now exist as a lead before it ever buys.
            $table->enum('lifecycle_stage', ['lead', 'prospect', 'customer', 'lost'])
                ->default('customer')->after('type');
            $table->foreignId('owner_id')->nullable()->after('lifecycle_stage')
                ->constrained('users')->nullOnDelete();
            $table->string('source')->nullable()->after('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['lifecycle_stage', 'source']);
        });
    }
};
