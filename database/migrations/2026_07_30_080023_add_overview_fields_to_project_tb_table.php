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
        Schema::table('project_tb', function (Blueprint $table) {
            $table->decimal('original_cost', 15, 2)->nullable()->after('total_project_cost');
            $table->decimal('revised_cost', 15, 2)->nullable()->after('original_cost');
            $table->text('project_description')->nullable()->after('revised_cost');
            $table->integer('days_suspension_order')->default(0)->after('time_extention');
            $table->date('revised_completion_date')->nullable()->after('actual_completion_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tb', function (Blueprint $table) {
            $table->dropColumn([
                'original_cost',
                'revised_cost',
                'project_description',
                'days_suspension_order',
                'revised_completion_date',
            ]);
        });
    }
};
