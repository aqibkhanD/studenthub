<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'student_id', 'name', 'email', 'phone', 'password',
        'role', 'department_id', 'program', 'batch', 'semester',
        'profile_photo', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    // ── Scopes ──────────────────────────────────────
    public function scopeStudents($query)  { return $query->where('role', 'student'); }
    public function scopeAdmins($query)    { return $query->where('role', 'admin'); }
    public function scopeActive($query)    { return $query->where('is_active', true); }

    // ── Role helpers ─────────────────────────────────
    public function isStudent():   bool { return $this->role === 'student'; }
    public function isAdmin():     bool { return $this->role === 'admin'; }
    public function isSuperAdmin():bool { return $this->role === 'super_admin'; }

    // ── Relationships ─────────────────────────────────
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** Submissions created by this student */
    public function submissions()
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    /** Submissions assigned to this admin */
    public function assignedSubmissions()
    {
        return $this->hasMany(Submission::class, 'assigned_to');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }
}
