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
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->text('staff_reply')->nullable()->after('note');
            $table->timestamp('staff_replied_at')->nullable()->after('staff_reply');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->dropColumn(['staff_reply', 'staff_replied_at']);
        });
    }
};
