<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaEscalationRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'department_id', 'form_type_id', 'escalate_after_hours',
        'escalate_to_user_id', 'notify_student', 'escalation_level',
    ];

    protected $casts = [
        'notify_student' => 'boolean',
        'created_at'     => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    public function escalateTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }
}
