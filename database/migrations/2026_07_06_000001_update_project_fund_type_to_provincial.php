<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE project_fund_type_tb MODIFY fund_type ENUM('National', 'Province', 'Provincial', 'LGU') NOT NULL");
        DB::table('project_fund_type_tb')
            ->where('fund_type', 'Province')
            ->update(['fund_type' => 'Provincial']);
        DB::statement("ALTER TABLE project_fund_type_tb MODIFY fund_type ENUM('National', 'Provincial', 'LGU') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE project_fund_type_tb MODIFY fund_type ENUM('National', 'Province', 'Provincial', 'LGU') NOT NULL");
        DB::table('project_fund_type_tb')
            ->where('fund_type', 'Provincial')
            ->update(['fund_type' => 'Province']);
        DB::statement("ALTER TABLE project_fund_type_tb MODIFY fund_type ENUM('National', 'LGU', 'Province') NOT NULL");
    }
};
