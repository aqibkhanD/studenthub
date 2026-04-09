<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'email', 'phone',
        'head_user_id', 'sla_hours', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function formTypes()
    {
        return $this->hasMany(FormType::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function escalationRules()
    {
        return $this->hasMany(SlaEscalationRule::class)->orderBy('escalation_level');
    }

    /** Pending submissions in this department */
    public function pendingSubmissions()
    {
        return $this->submissions()->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
    }
}
