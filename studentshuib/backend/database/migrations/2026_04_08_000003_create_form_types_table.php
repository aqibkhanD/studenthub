<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE form_category AS ENUM (
            'academic_certification',
            'complaint',
            'career_counseling',
            'club_cocurricular',
            'profile_portfolio',
            'finance',
            'it_support',
            'other'
        )");

        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('category', 30);               // form_category enum
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();     // shown to student before submission
            $table->boolean('requires_documents')->default(false);
            $table->boolean('allow_anonymous')->default(false);  // complaints only
            $table->boolean('auto_generate_doc')->default(false); // auto PDF on approval
            $table->string('doc_template_path', 255)->nullable();
            $table->unsignedSmallInteger('sla_hours')->nullable(); // overrides dept default
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('department_id');
        });

        DB::statement("ALTER TABLE form_types ADD CONSTRAINT chk_form_category CHECK (category::form_category IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('form_types');
        DB::statement("DROP TYPE IF EXISTS form_category");
    }
};
