<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending digest events accumulate here until the digest job runs.
     * ProcessDigestJob reads rows grouped by (notifiable, delivery),
     * assembles a single batched email, marks rows dispatched, and logs to
     * notification_log.
     */
    public function up(): void
    {
        Schema::create('pending_digests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type', 60);
            $table->string('event_type', 60);
            $table->string('channel', 20);               // always 'email' for digests
            $table->string('delivery', 20);              // digest_hourly | digest_daily
            $table->string('reference', 30)->nullable(); // submission ref
            $table->json('payload');                     // full notification content snapshot
            $table->boolean('dispatched')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(
                ['notifiable_type', 'notifiable_id', 'delivery', 'dispatched'],
                'pending_digest_queue'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_digests');
    }
};
