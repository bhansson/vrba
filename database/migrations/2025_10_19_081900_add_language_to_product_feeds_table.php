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
        Schema::table('product_feeds', function (Blueprint $table): void {
            $table->string('language', 10)->default('en')->after('feed_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_feeds', function (Blueprint $table): void {
            $table->dropColumn('language');
        });
    }
};

