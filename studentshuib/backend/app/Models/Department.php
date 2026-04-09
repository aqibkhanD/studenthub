<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $fillable = [
        'name', 'slug', 'code', 'description', 'email', 'phone',
        'head_user_id', 'sla_hours', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function formTypes(): HasMany
    {
        return $this->hasMany(FormType::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function slaEscalationRules(): HasMany
    {
        return $this->hasMany(SlaEscalationRule::class);
    }
}
