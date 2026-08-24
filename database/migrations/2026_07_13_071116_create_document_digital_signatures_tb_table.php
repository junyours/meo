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
        Schema::create('document_digital_signatures_tb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('document_scanner_tb')->onDelete('cascade');

            $table->string('digital_signature')->nullable();
            $table->string('signed_by')->nullable();
            $table->timestamp('signed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_digital_signatures_tb');
    }
};
