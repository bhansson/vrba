<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ai_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 191);
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('system_prompt')->nullable();
            $table->longText('prompt');
            $table->json('context')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
            $table->index(['is_active', 'team_id']);
        });

        Schema::create('product_ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_ai_template_id')->constrained('product_ai_templates')->cascadeOnDelete();
            $table->string('sku', 191)->index();
            $table->string('status', 32)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'product_ai_template_id', 'status']);
            $table->index(['status', 'queued_at']);
        });

        Schema::create('product_ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_ai_template_id')->constrained('product_ai_templates')->cascadeOnDelete();
            $table->foreignId('product_ai_job_id')->nullable()->constrained('product_ai_jobs')->nullOnDelete();
            $table->string('sku', 191);
            $table->json('content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'product_ai_template_id', 'created_at']);
            $table->index(['team_id', 'product_ai_template_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('product_ai_generations');
        Schema::dropIfExists('product_ai_jobs');
        Schema::dropIfExists('product_ai_templates');
    }
};
