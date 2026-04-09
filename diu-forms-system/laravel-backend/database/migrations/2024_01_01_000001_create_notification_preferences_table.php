<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            // Polymorphic: works for both students (users) and admins
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type', 60); // App\Models\User | App\Models\Admin
            $table->string('event_type', 60);
            // Events: submission_confirmed | submission_in_review | action_required
            //         submission_returned  | submission_approved  | submission_rejected
            //         certificate_ready    | admin_comment
            //         new_submission       | submission_resubmit  | sla_warning
            //         sla_breach           | escalation           | role_change
            //         new_admin            | setting_change
            $table->string('channel', 20);
            // Channels: email | sms | inapp
            $table->string('delivery', 20)->default('immediate');
            // Delivery: immediate | digest_hourly | digest_daily | never
            $table->timestamps();

            $table->unique(
                ['notifiable_type', 'notifiable_id', 'event_type', 'channel'],
                'notif_pref_unique'
            );
            $table->index(['notifiable_type', 'notifiable_id'], 'notif_pref_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
