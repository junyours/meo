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
        Schema::create('inquiries_tb', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_token', 64)->unique();
            $table->string('fullname');
            $table->string('phone', 50);
            $table->string('email')->nullable();
            $table->string('location');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('pending'); // pending, accepted, resolved, declined
            $table->text('admin_notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries_tb');
    }
};
