<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurable form catalogue managed by super_admin via admin-settings.
     * The `fields` JSON column stores the dynamic field schema.
     *
     * Field schema shape (array of objects):
     * [
     *   { "key": "purpose", "label": "Purpose", "type": "select",
     *     "required": true, "options": ["Bank Account","Visa","Other"] },
     *   { "key": "details", "label": "Details",  "type": "textarea", "required": false }
     * ]
     * Supported types: text | textarea | select | radio | checkbox | date | file
     */
    public function up(): void
    {
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();           // e.g. bonafide, migration_cert
            $table->string('name');                         // e.g. Bonafide Certificate
            $table->enum('category', [
                'academic_certification',
                'complaint',
                'career_counseling',
                'club_cocurricular',
                'finance',
                'it_support',
                'other',
            ]);
            $table->unsignedBigInteger('department_id');    // routes to this dept
            $table->integer('sla_hours')->default(72);      // overrides dept default
            $table->boolean('requires_docs')->default(false);
            $table->boolean('allow_anonymous')->default(false);
            $table->boolean('auto_generate')->default(false); // auto-approve & generate cert
            $table->text('instructions')->nullable();
            $table->json('fields');                         // dynamic field schema
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};
