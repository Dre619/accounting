<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_section_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['manual_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_images');
    }
};
