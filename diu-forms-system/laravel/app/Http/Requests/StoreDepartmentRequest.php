<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id;

        return [
            // Core department fields
            'name'           => ['required', 'string', 'max:120'],
            'slug'           => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('departments', 'slug')->ignore($departmentId),
            ],
            'code'           => [
                'nullable', 'string', 'max:20',
                Rule::unique('departments', 'code')->ignore($departmentId),
            ],
            'head_of_dept'   => ['nullable', 'string', 'max:120'],
            'contact_email'  => ['nullable', 'email', 'max:120'],
            'contact_phone'  => ['nullable', 'string', 'max:30'],
            'is_active'      => ['boolean'],

            // Signatory block (written to config/signatories.php)
            'signatory'                    => ['sometimes', 'array'],
            'signatory.name'               => ['required_with:signatory', 'string', 'max:120'],
            'signatory.title'              => ['required_with:signatory', 'string', 'max:120'],
            'signatory.department'         => ['nullable', 'string', 'max:120'],
            'signatory.institution'        => ['nullable', 'string', 'max:120'],
            'signatory.phone'              => ['nullable', 'string', 'max:30'],
            'signatory.email'              => ['nullable', 'email', 'max:120'],

            // SLA escalation rules (array of rule objects)
            'escalation_rules'             => ['sometimes', 'array', 'max:10'],
            'escalation_rules.*.hours'     => ['required_with:escalation_rules', 'integer', 'min:1'],
            'escalation_rules.*.action'    => ['required_with:escalation_rules', Rule::in(['warn', 'escalate', 'notify_head'])],
            'escalation_rules.*.notify_roles' => ['nullable', 'array'],
            'escalation_rules.*.notify_roles.*' => ['string', Rule::in(['admin', 'super_admin'])],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'  => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'A department with this slug already exists.',
            'code.unique' => 'A department with this code already exists.',
        ];
    }
}
