<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. Registrar's Office
            $table->string('code', 30)->unique();          // e.g. REG, CSE, DSA
            $table->string('email')->nullable();           // dept routing email
            $table->string('head_name')->nullable();
            $table->integer('sla_hours')->default(72);     // default SLA for submissions routed here
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
