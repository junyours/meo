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
        Schema::create('document_ocr_results_tb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('document_scanner_tb')->onDelete('cascade');

            $table->longText('extracted_text')->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->string('ocr_language')->default('eng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_ocr_results_tb');
    }
};
