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
        Schema::create('tech_prep_tb', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('project_id')->constrained('project_tb')->onDelete('cascade');
            $table->unsignedTinyInteger('hazard_assessment_status')->nullable();
            $table->unsignedTinyInteger('pow_ded_status')->nullable();
            $table->unsignedTinyInteger('supplementary_budget_status')->nullable();
            $table->unsignedTinyInteger('alobs_status')->nullable();
            $table->unsignedTinyInteger('ecc_cnc_status')->nullable();
            $table->unsignedTinyInteger('submission_tech_docs_status')->nullable();
            $table->unsignedTinyInteger('bidding_status')->nullable();
            $table->unsignedTinyInteger('contract_ntp_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tech_prep_tb');
    }
};
