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
        Schema::table('tech_prep_tb', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('contract_ntp_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tech_prep_tb', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
