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
        Schema::create('project_tb', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('project_name');
            $table->string('location');
            $table->float('total_project_cost', 10, 2);
            $table->string('source_of_fund');
            $table->integer('year');
            $table->integer('project_duration');
            $table->date('start_date');
            $table->date('target_completion_date');
            $table->date('actual_completion_date')->nullable();
            $table->integer('time_extention');
            $table->decimal('percentage_of_accomplishment', 5, 2);
            $table->string('contractor');
            $table->unsignedTinyInteger('status'); // 0: Ongoing, 1: Completed, 2: Delayed


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tb');
    }
};
