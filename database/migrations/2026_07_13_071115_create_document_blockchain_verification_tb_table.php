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
        Schema::create('document_blockchain_verification_tb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('document_scanner_tb')->onDelete('cascade');

            $table->string('blockchain_tx_hash')->nullable();
            $table->timestamp('blockchain_verified_at')->nullable();
            $table->boolean('blockchain_verified')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_blockchain_verification_tb');
    }
};
