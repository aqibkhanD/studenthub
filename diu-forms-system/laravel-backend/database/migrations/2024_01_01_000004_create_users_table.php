<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students — authenticated via Sanctum.
     * DIU student ID used as the primary login identifier alongside email.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->unique();   // e.g. 221-15-5812
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('department', 100)->nullable(); // e.g. CSE, BBA, EEE
            $table->string('batch', 20)->nullable();       // e.g. 50th, Fall-2022
            $table->string('program', 60)->nullable();     // B.Sc., MBA, etc.
            $table->string('password');
            $table->boolean('email_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('notif_email_enabled')->default(true);
            $table->boolean('notif_sms_enabled')->default(true);
            $table->boolean('quiet_hours_enabled')->default(true);
            $table->time('quiet_start')->default('22:00');
            $table->time('quiet_end')->default('07:30');
            $table->string('unsubscribe_token', 64)->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
