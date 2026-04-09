<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE submission_status AS ENUM (
            'draft',
            'submitted',
            'routed',
            'in_review',
            'action_required',
            'escalated',
            'approved',
            'rejected',
            'returned',
            'completed',
            'cancelled'
        )");

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique(); // e.g. "DIU-2026-00421"
            $table->foreignId('form_type_id')->constrained('form_types')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_anonymous')->default(false);
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();

            // Status state machine
            $table->string('status', 20)->default('draft');  // submission_status enum

            // All form field responses stored as JSON
            $table->jsonb('form_data');

            // Assignment
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->unsignedTinyInteger('current_step')->default(1);

            // Timing
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('sla_deadline')->nullable();   // computed on submission
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Internal admin notes (never shown to student)
            $table->text('internal_notes')->nullable();

            // Final output document
            $table->string('output_document', 255)->nullable();

            $table->timestamps();

            // Indexes for common query patterns
            $table->index('status');
            $table->index('student_id');
            $table->index('department_id');
            $table->index('form_type_id');
            $table->index('assigned_to');
            $table->index('submitted_at');
            $table->index('sla_deadline');
            $table->index('reference_no');
        });

        DB::statement("ALTER TABLE submissions ADD CONSTRAINT chk_submission_status CHECK (status::submission_status IS NOT NULL)");

        // Sequence table for reference number generation (DIU-YYYY-NNNNN)
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('submissions');
        DB::statement("DROP TYPE IF EXISTS submission_status");
    }
};
