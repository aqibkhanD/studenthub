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
        // Notifications (in-app + SMS log)
        // ----------------------------------------------------------
        DB::statement("CREATE TYPE notification_channel AS ENUM ('in_app', 'sms', 'email')");

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->foreign('submission_id')->references('id')->on('submissions')->nullOnDelete();
            $table->string('channel', 10);                      // notification_channel enum
            $table->string('type', 100);                        // e.g. "submission.approved"
            $table->string('title', 255);
            $table->text('body');
            $table->string('phone_number', 20)->nullable();     // for SMS
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('submission_id');
            $table->index('channel');
            $table->index('is_read');
            $table->index('sent_at');
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_channel CHECK (channel::notification_channel IS NOT NULL)");

        // ----------------------------------------------------------
        // SLA Escalation Rules
        // ----------------------------------------------------------
        Schema::create('sla_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('form_type_id')->nullable()->constrained('form_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('escalate_after_hours');
            $table->unsignedBigInteger('escalate_to_user_id')->nullable(); // NULL = dept head
            $table->foreign('escalate_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->boolean('notify_student')->default(true);
            $table->unsignedTinyInteger('escalation_level')->default(1); // 1st, 2nd...
            $table->timestamp('created_at')->useCurrent();

            $table->index('department_id');
        });

        // ----------------------------------------------------------
        // Audit Logs (system-wide append-only trail)
        // ----------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 100);                     // e.g. "submission.created"
            $table->string('auditable_type', 100);             // model name
            $table->unsignedBigInteger('auditable_id');
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['auditable_type', 'auditable_id'], 'idx_auditable');
            $table->index('created_at');
        });

        // ----------------------------------------------------------
        // Personal Access Tokens (Laravel Sanctum)
        // ----------------------------------------------------------
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sla_escalation_rules');
        Schema::dropIfExists('notifications');
        DB::statement("DROP TYPE IF EXISTS notification_channel");
    }
};
