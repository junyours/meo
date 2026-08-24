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
        Schema::create('document_ai_analysis_tb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('document_scanner_tb')->onDelete('cascade');

            $table->string('ai_classification')->nullable();
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->json('ai_tags')->nullable();
            $table->json('ai_entities')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_ai_analysis_tb');
    }
};
