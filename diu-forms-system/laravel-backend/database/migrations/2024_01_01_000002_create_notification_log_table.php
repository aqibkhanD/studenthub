<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type', 60);
            $table->string('event_type', 60);
            $table->string('channel', 20);       // email | sms | inapp
            $table->string('delivery', 20);       // immediate | digest_hourly | digest_daily
            $table->string('status', 20)->default('queued');
            // Status: queued | sent | failed | suppressed
            $table->string('reference', 30)->nullable();   // submission ref e.g. DIU-2024-0042
            $table->json('payload')->nullable();            // snapshot of the notification content
            $table->string('suppression_reason', 120)->nullable();
            // e.g. "rate_limit:email_hourly", "duplicate:30min", "quiet_hours", "unsubscribed"
            $table->string('provider_message_id')->nullable(); // SMS/email gateway message ID
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notif_log_owner_time');
            $table->index(['event_type', 'channel', 'created_at'],             'notif_log_type_time');
            $table->index(['status', 'created_at'],                             'notif_log_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
    }
};
