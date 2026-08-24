<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_scanner_tb', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->nullable()->after('file_size')->index();
        });
    }

    public function down(): void
    {
        Schema::table('document_scanner_tb', function (Blueprint $table) {
            $table->dropIndex(['page_number']);
            $table->dropColumn('page_number');
        });
    }
};
