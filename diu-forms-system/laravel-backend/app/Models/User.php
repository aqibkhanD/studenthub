<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'student_id', 'name', 'email', 'phone',
        'department', 'batch', 'program', 'password',
        'notif_email_enabled', 'notif_sms_enabled',
        'quiet_hours_enabled', 'quiet_start', 'quiet_end',
    ];

    protected $hidden = ['password', 'remember_token', 'unsubscribe_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'notif_email_enabled'  => 'boolean',
        'notif_sms_enabled'    => 'boolean',
        'quiet_hours_enabled'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(InAppNotification::class, 'notifiable_id')
                    ->where('notifiable_type', self::class)
                    ->latest();
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'notifiable_id')
                    ->where('notifiable_type', self::class);
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function hasUnsubscribedEmail(): bool
    {
        return !$this->notif_email_enabled;
    }

    public function hasUnsubscribedSms(): bool
    {
        return !$this->notif_sms_enabled;
    }

    public function unreadNotificationCount(): int
    {
        return $this->inAppNotifications()->where('read', false)->count();
    }

    public function getRouteKeyName(): string
    {
        return 'student_id';
    }
}
