<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        $formTypeId = $this->route('formType')?->id; // null on store, set on update

        return [
            'name'                => ['required', 'string', 'max:120'],
            'slug'                => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('form_types', 'slug')->ignore($formTypeId),
            ],
            'category'            => ['required', Rule::in([
                'academic', 'certification', 'complaint', 'career',
                'club', 'co_curricular', 'profile',
            ])],
            'department_id'       => ['required', 'integer', 'exists:departments,id'],
            'description'         => ['nullable', 'string', 'max:500'],
            'sla_hours'           => ['required', 'integer', 'min:1', 'max:720'],
            'requires_documents'  => ['boolean'],
            'allow_anonymous'     => ['boolean'],
            'auto_generate_doc'   => ['boolean'],
            'is_active'           => ['boolean'],

            // Nested field definitions
            'fields'              => ['sometimes', 'array', 'max:30'],
            'fields.*.label'      => ['required_with:fields', 'string', 'max:120'],
            'fields.*.field_type' => ['required_with:fields', Rule::in([
                'text', 'textarea', 'email', 'phone', 'number',
                'date', 'select', 'radio', 'checkbox', 'file',
            ])],
            'fields.*.is_required'=> ['boolean'],
            'fields.*.options'    => ['nullable', 'string', 'max:1000'],
            'fields.*.sort_order' => ['integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex'               => 'Slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique'              => 'A form type with this slug already exists.',
            'fields.*.label.required_with'      => 'Each field must have a label.',
            'fields.*.field_type.required_with' => 'Each field must have a type.',
        ];
    }
}
