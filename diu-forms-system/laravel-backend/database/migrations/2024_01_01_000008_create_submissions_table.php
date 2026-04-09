<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core submissions table.
     *
     * Status flow:
     *   submitted → routed → in_review → approved | rejected | returned
     *   returned  → submitted (resubmit)
     *   Any state → escalated (by SLA breach or manual escalation)
     *
     * The `data` JSON column holds the student's answers keyed by field slug.
     * The `status_history` JSON column is a lightweight append-only log kept
     * here for fast reads; the full audit trail lives in audit_log.
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('ref', 30)->unique();            // DIU-YYYY-NNNN e.g. DIU-2024-0042
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('form_type_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('assigned_admin_id')->nullable();

            $table->enum('status', [
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
            ])->default('draft');

            $table->boolean('is_anonymous')->default(false);
            $table->json('data');                           // student's field answers
            $table->text('admin_comment')->nullable();      // latest admin note shown to student
            $table->text('return_reason')->nullable();      // populated when status = returned
            $table->date('response_deadline')->nullable();  // set when returning to student
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('sla_deadline')->nullable();  // computed from submitted_at + sla_hours
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('form_type_id')->references('id')->on('form_types');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('assigned_admin_id')->references('id')->on('admins')->nullOnDelete();

            $table->index(['user_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['assigned_admin_id', 'status']);
            $table->index('sla_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
