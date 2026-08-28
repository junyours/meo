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
        Schema::table('inquiries_tb', function (Blueprint $table) {
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete()->after('accepted_at');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete()->after('resolved_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('resolved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inquiries_tb', function (Blueprint $table) {
            $table->dropForeign(['accepted_by']);
            $table->dropForeign(['resolved_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['accepted_by', 'resolved_by', 'updated_by']);
        });
    }
};
