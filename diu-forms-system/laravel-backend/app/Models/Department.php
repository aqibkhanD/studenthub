<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'email', 'head_name', 'sla_hours', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function formTypes(): HasMany
    {
        return $this->hasMany(FormType::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
