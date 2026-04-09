<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'student_id', 'name', 'email', 'phone', 'password', 'role',
        'department_id', 'program', 'batch', 'semester',
        'profile_photo', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ----------------------------------------------------------
    // Role helpers
    // ----------------------------------------------------------
    public function isStudent(): bool    { return $this->role === 'student'; }
    public function isAdmin(): bool      { return in_array($this->role, ['admin', 'dept_head', 'super_admin']); }
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isDeptHead(): bool   { return $this->role === 'dept_head'; }
    public function isManagement(): bool { return $this->role === 'management'; }

    // ----------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    public function assignedSubmissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'assigned_to');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
