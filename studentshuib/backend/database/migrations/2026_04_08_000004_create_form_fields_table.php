<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE field_type AS ENUM (
            'text', 'textarea', 'select',
            'radio', 'checkbox', 'date',
            'file', 'phone', 'email', 'number'
        )");

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_type_id')->constrained('form_types')->cascadeOnDelete();
            $table->string('label', 191);              // "Reason for Request"
            $table->string('field_key', 100);          // "reason_for_request"
            $table->string('field_type', 20);          // references field_type enum
            $table->jsonb('options')->nullable();       // options for select/radio/checkbox
            $table->boolean('is_required')->default(false);
            $table->string('placeholder', 255)->nullable();
            $table->string('help_text', 500)->nullable();
            $table->string('validation_rules', 500)->nullable(); // Laravel validation string
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unique(['form_type_id', 'field_key'], 'uq_form_field');
        });

        DB::statement("ALTER TABLE form_fields ADD CONSTRAINT chk_field_type CHECK (field_type::field_type IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
        DB::statement("DROP TYPE IF EXISTS field_type");
    }
};
