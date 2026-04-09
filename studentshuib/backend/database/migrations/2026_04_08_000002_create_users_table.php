<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create the user_role enum type in PostgreSQL
        DB::statement("CREATE TYPE user_role AS ENUM (
            'student',
            'admin',
            'dept_head',
            'super_admin',
            'management'
        )");

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20)->unique()->nullable(); // e.g. "221-15-5812", NULL for staff
            $table->string('name', 150);
            $table->string('email', 191)->unique();
            $table->string('phone', 20)->nullable();           // BD mobile: +8801XXXXXXXXX
            $table->string('password', 255);
            $table->string('role', 20)->default('student');    // references user_role enum via check
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('program', 100)->nullable();        // e.g. "B.Sc. in CSE"
            $table->string('batch', 20)->nullable();           // e.g. "55"
            $table->string('semester', 20)->nullable();        // e.g. "7th"
            $table->string('profile_photo', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
            $table->index('student_id');
        });

        // Add enum check constraint
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_user_role CHECK (role::user_role IS NOT NULL)");

        // Now add the deferred FK on departments.head_user_id
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_user_id']);
        });
        Schema::dropIfExists('users');
        DB::statement("DROP TYPE IF EXISTS user_role");
    }
};
