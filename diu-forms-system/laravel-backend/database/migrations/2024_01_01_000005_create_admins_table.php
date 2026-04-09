<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin and super-admin accounts — separate table from students.
     * Roles: admin | super_admin
     * Each admin belongs to one department and can only be assigned
     * submissions routed to that department (unless super_admin).
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'super_admin'])->default('admin');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notif_email_enabled')->default(true);
            $table->boolean('notif_sms_enabled')->default(true);
            $table->boolean('quiet_hours_enabled')->default(true);
            $table->time('quiet_start')->default('22:00');
            $table->time('quiet_end')->default('07:30');
            $table->string('unsubscribe_token', 64)->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
