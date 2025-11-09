<?php

use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_studio_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_ai_job_id')->nullable()->constrained('product_ai_jobs')->nullOnDelete();
            $table->string('source_type', 32);
            $table->string('source_reference', 2048)->nullable();
            $table->text('prompt');
            $table->string('model', 191);
            $table->string('storage_disk', 64);
            $table->string('storage_path', 2048);
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->string('response_id', 191)->nullable();
            $table->string('response_model', 191)->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'created_at']);
            $table->index(['product_ai_job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_studio_generations');
    }
};
