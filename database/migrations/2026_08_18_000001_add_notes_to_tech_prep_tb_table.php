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
            $table->text('hazard_assessment_notes')->nullable()->after('hazard_assessment_status');
            $table->text('pow_ded_notes')->nullable()->after('pow_ded_status');
            $table->text('supplementary_budget_notes')->nullable()->after('supplementary_budget_status');
            $table->text('alobs_notes')->nullable()->after('alobs_status');
            $table->text('ecc_cnc_notes')->nullable()->after('ecc_cnc_status');
            $table->text('submission_tech_docs_notes')->nullable()->after('submission_tech_docs_status');
            $table->text('bidding_notes')->nullable()->after('bidding_status');
            $table->text('contract_ntp_notes')->nullable()->after('contract_ntp_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tech_prep_tb', function (Blueprint $table) {
            $table->dropColumn([
                'hazard_assessment_notes',
                'pow_ded_notes',
                'supplementary_budget_notes',
                'alobs_notes',
                'ecc_cnc_notes',
                'submission_tech_docs_notes',
                'bidding_notes',
                'contract_ntp_notes',
            ]);
        });
    }
};
