<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE workflow_type AS ENUM ('single', 'sequential', 'parallel')");
        DB::statement("CREATE TYPE step_action AS ENUM ('approve', 'review', 'sign_off')");

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('form_type_id')->nullable()->unique()->constrained('form_types')->nullOnDelete();
            $table->string('type', 15)->default('single'); // workflow_type enum
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');        // 1, 2, 3...
            $table->string('step_name', 150);                  // "HOD Approval"
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('assigned_role', 20)->default('admin');
            $table->string('action_required', 10)->default('approve'); // step_action enum
            $table->unsignedSmallInteger('sla_hours')->default(48);
            $table->boolean('is_optional')->default(false);

            $table->unique(['workflow_id', 'step_number'], 'uq_workflow_step');
        });

        DB::statement("ALTER TABLE workflows ADD CONSTRAINT chk_workflow_type CHECK (type::workflow_type IS NOT NULL)");
        DB::statement("ALTER TABLE workflow_steps ADD CONSTRAINT chk_step_action CHECK (action_required::step_action IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        DB::statement("DROP TYPE IF EXISTS workflow_type");
        DB::statement("DROP TYPE IF EXISTS step_action");
    }
};
