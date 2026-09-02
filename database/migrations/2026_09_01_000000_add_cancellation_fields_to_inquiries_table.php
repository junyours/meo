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
            if (!Schema::hasColumn('inquiries_tb', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('admin_notes');
            }
            if (!Schema::hasColumn('inquiries_tb', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('inquiries_tb', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inquiries_tb', function (Blueprint $table) {
            if (Schema::hasColumn('inquiries_tb', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('inquiries_tb', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('inquiries_tb', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
        });
    }
};
