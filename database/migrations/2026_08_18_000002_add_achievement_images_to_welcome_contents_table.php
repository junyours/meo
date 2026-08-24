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
        Schema::table('welcome_contents', function (Blueprint $table) {
            if (!Schema::hasColumn('welcome_contents', 'achievement_images')) {
                $table->json('achievement_images')->nullable()->after('slideshow_images');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('welcome_contents', function (Blueprint $table) {
            if (Schema::hasColumn('welcome_contents', 'achievement_images')) {
                $table->dropColumn('achievement_images');
            }
        });
    }
};
