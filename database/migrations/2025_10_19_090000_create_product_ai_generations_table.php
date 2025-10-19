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
        Schema::create('product_ai_generations', function (Blueprint $table) {
            $table->id();
            $table->string('product_sku', 191)->unique();
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->text('usps')->nullable();
            $table->text('faq')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ai_generations');
    }
};
