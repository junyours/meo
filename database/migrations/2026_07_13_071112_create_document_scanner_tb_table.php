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
        Schema::create('document_scanner_tb', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('project_id')->nullable()->constrained('project_tb')->onDelete('cascade');
            $table->foreignId('techprep_id')->nullable()->constrained('tech_prep_tb')->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('parent_document_id')->nullable()->constrained('document_scanner_tb')->onDelete('set null');

            // Document identification
            $table->string('document_name');
            $table->string('document_type')->default('pdf');
            $table->string('file_path');
            $table->string('file_hash')->unique();
            $table->unsignedBigInteger('file_size')->default(0);

            // Document metadata
            $table->unsignedInteger('page_count')->nullable();
            $table->string('resolution')->nullable();
            $table->string('color_mode')->nullable();

            // Version control
            $table->unsignedInteger('version')->default(1);

            // Processing status
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('processing_error')->nullable();

            // Scan metadata
            $table->string('scan_device')->nullable();
            $table->string('scan_software')->nullable();
            $table->ipAddress('scan_ip')->nullable();
            $table->string('scan_location')->nullable();

            // Access control
            $table->json('access_permissions')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('expires_at')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('processing_status');
            $table->index('document_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_scanner_tb');
    }
};
