<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'form_type_id', 'label', 'field_key', 'field_type',
        'options', 'is_required', 'placeholder', 'help_text',
        'validation_rules', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }
}
