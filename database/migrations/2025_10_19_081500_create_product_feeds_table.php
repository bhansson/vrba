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
        Schema::create('product_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('feed_url')->nullable();
            $table->json('field_mappings');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_feed_id')
                ->constrained('product_feeds')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('sku', 191);
            $table->string('gtin', 191)->nullable();
            $table->string('title');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('url', 2048);
            $table->string('image_link', 2048)->nullable();
            $table->text('additional_image_link')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'sku', 'product_feed_id']);
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_feeds');
    }
};
