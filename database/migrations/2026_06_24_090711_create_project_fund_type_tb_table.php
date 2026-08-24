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
        Schema::create('project_fund_type_tb', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->enum('fund_type', ['National', 'Provincial', 'LGU']);
            $table->string('fund_source');
            $table->foreignId('project_id')->constrained('project_tb')->onDelete('cascade');
            $table->index(['fund_type', 'fund_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_fund_type_tb');
    }
};
