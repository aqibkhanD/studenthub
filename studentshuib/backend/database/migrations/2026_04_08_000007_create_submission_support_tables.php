<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------
        // Submission Status History (immutable audit trail)
        // ----------------------------------------------------------
        Schema::create('submission_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedBigInteger('changed_by')->nullable(); // NULL = system
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('comment')->nullable();              // required on reject/return
            $table->boolean('is_visible_to_student')->default(true);
            $table->unsignedTinyInteger('step_number')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index('submission_id');
            $table->index('changed_by');
            $table->index('changed_at');
        });

        // ----------------------------------------------------------
        // Submission Documents (uploads by student or admin)
        // ----------------------------------------------------------
        DB::statement("CREATE TYPE doc_source AS ENUM ('student_upload', 'admin_upload', 'generated_output')");

        Schema::create('submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size');             // bytes
            $table->string('mime_type', 100);
            $table->string('document_type', 20)->default('student_upload'); // doc_source enum
            $table->string('description', 255)->nullable();
            $table->boolean('is_public')->default(true);      // visible to student
            $table->timestamp('created_at')->useCurrent();

            $table->index('submission_id');
        });

        DB::statement("ALTER TABLE submission_documents ADD CONSTRAINT chk_doc_source CHECK (document_type::doc_source IS NOT NULL)");

        // ----------------------------------------------------------
        // Submission Comments (student ↔ admin thread)
        // ----------------------------------------------------------
        Schema::create('submission_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable(); // NULL = system message
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);   // admin-only note
            $table->boolean('is_system')->default(false);     // auto-generated
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('submission_comments')->nullOnDelete();
            $table->timestamps();

            $table->index('submission_id');
        });

        // ----------------------------------------------------------
        // Approval Records (multi-step workflow tracking)
        // ----------------------------------------------------------
        DB::statement("CREATE TYPE approval_action AS ENUM ('pending', 'approved', 'rejected', 'skipped')");

        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 10)->default('pending'); // approval_action enum
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['submission_id', 'workflow_step_id'], 'uq_approval');
            $table->index('approver_id');
        });

        DB::statement("ALTER TABLE approval_records ADD CONSTRAINT chk_approval_action CHECK (action::approval_action IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
        DB::statement("DROP TYPE IF EXISTS approval_action");
        Schema::dropIfExists('submission_comments');
        Schema::dropIfExists('submission_documents');
        DB::statement("DROP TYPE IF EXISTS doc_source");
        Schema::dropIfExists('submission_status_history');
    }
};
