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
        Schema::create('pow_prep_tb', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('project_id')->constrained('project_tb')->onDelete('cascade');
            $table->integer('project_cost');
            $table->string('office_concern');
            $table->unsignedTinyInteger('status'); // 0: Not Submitted, 1: Submitted, 2: Approved, 3: Disapproved
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pow_prep_tb');
    }
};
