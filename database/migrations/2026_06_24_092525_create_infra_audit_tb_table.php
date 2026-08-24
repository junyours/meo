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
        Schema::create('infra_audit_tb', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('project_id')->constrained('project_tb')->onDelete('cascade')->nullable();
            $table->integer('form1');
            $table->integer('form2a');
            $table->integer('form2b');
            $table->unsignedTinyInteger('status'); // 0: Not Submitted, 1: Submitted, 2: Approved, 3: Disapproved
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_audit_tb');
    }
};
