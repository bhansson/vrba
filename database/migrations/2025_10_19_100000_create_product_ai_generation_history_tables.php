<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191)->index();
            $table->string('prompt_type', 64);
            $table->string('status', 32)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'prompt_type', 'status']);
            $table->index(['status', 'queued_at']);
        });

        Schema::create('product_ai_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_ai_description_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_ai_usps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191);
            $table->json('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_ai_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191);
            $table->json('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        Schema::create('product_ai_review_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 191);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_ai_review_summaries');
        Schema::dropIfExists('product_ai_faqs');
        Schema::dropIfExists('product_ai_usps');
        Schema::dropIfExists('product_ai_description_summaries');
        Schema::dropIfExists('product_ai_descriptions');
        Schema::dropIfExists('product_ai_jobs');
    }
};
