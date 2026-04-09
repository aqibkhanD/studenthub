<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable, append-only audit trail.
     * Written by AuditLogObserver — never updated or deleted.
     *
     * `diff` stores before/after for status_change and setting_change events:
     * { "field": "status", "from": "in_review", "to": "approved" }
     */
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Actor — who triggered the event
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_type', 60);   // App\Models\User | App\Models\Admin
            $table->string('actor_name');        // denormalised snapshot — survives deletion
            $table->string('actor_role', 30);    // student | admin | super_admin | system

            // Event
            $table->enum('action', [
                'submission',       // new submission created
                'status_change',    // status updated
                'comment',          // admin added a comment
                'document',         // file uploaded
                'role_change',      // admin role changed
                'setting_change',   // system setting modified
                'login',            // successful login
            ]);
            $table->string('reference', 30)->nullable(); // submission ref if relevant
            $table->text('details');                     // human-readable description
            $table->json('diff')->nullable();            // { field, from, to }

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');             // no updated_at — this table is append-only

            $table->index(['actor_type', 'actor_id', 'created_at'], 'audit_actor');
            $table->index(['action', 'created_at'],                  'audit_action');
            $table->index('reference',                               'audit_ref');
            $table->index('created_at',                              'audit_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
