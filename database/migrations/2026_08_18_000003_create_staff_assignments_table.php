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
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('type', 30)->default('assignment'); // 'assignment', 'note', 'deadline', 'message'
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('role_in_project', 100)->nullable();
            $table->date('target_deadline')->nullable();
            $table->string('priority', 20)->default('normal'); // 'low', 'normal', 'high', 'urgent'
            $table->string('status', 30)->default('pending'); // 'pending', 'in_progress', 'completed', 'cancelled'
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign key to project_tb if exists
            $table->foreign('project_id')->references('id')->on('project_tb')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
