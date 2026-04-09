<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notifiable_type', 60);
            $table->string('event_type', 60);
            $table->string('reference', 30)->nullable();
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read', 'created_at'], 'inapp_owner_unread');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
    }
};
