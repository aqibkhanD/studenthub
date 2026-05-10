<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * app_settings — generic key/value store for system-wide settings.
 *
 * Used for things like the current academic semester (label + start/end dates),
 * which affect dashboard period filters and metric calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed reasonable defaults so the dashboard's "Semester" period works
        // out of the box. Super admin can update these via Settings → System.
        DB::table('app_settings')->insert([
            ['key' => 'semester.label',      'value' => json_encode('Spring 2026'), 'updated_at' => now()],
            ['key' => 'semester.start_date', 'value' => json_encode('2026-01-01'),   'updated_at' => now()],
            ['key' => 'semester.end_date',   'value' => json_encode('2026-06-30'),   'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
