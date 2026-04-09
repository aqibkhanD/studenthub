<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role',
        'department_id', 'is_active',
        'notif_email_enabled', 'notif_sms_enabled',
        'quiet_hours_enabled', 'quiet_start', 'quiet_end',
    ];

    protected $hidden = ['password', 'remember_token', 'unsubscribe_token'];

    protected $casts = [
        'is_active'            => 'boolean',
        'notif_email_enabled'  => 'boolean',
        'notif_sms_enabled'    => 'boolean',
        'quiet_hours_enabled'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedSubmissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'assigned_admin_id');
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

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function hasUnsubscribedEmail(): bool
    {
        return !$this->notif_email_enabled;
    }

    public function hasUnsubscribedSms(): bool
    {
        return !$this->notif_sms_enabled;
    }

    public function canAccessSubmission(Submission $submission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return $submission->department_id === $this->department_id;
    }
}
