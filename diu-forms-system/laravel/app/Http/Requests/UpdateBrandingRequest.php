<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            // University details
            'university_name'      => ['sometimes', 'required', 'string', 'max:120'],
            'university_short_name'=> ['sometimes', 'nullable', 'string', 'max:20'],
            'portal_title'         => ['sometimes', 'required', 'string', 'max:100'],
            'contact_email'        => ['sometimes', 'nullable', 'email', 'max:120'],
            'contact_phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'website_url'          => ['sometimes', 'nullable', 'url', 'max:200'],
            'address'              => ['sometimes', 'nullable', 'string', 'max:300'],
            'primary_color'        => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Feature flags (submitted as boolean-like strings from JSON)
            'feature_self_registration'     => ['sometimes', 'boolean'],
            'feature_anonymous_complaints'  => ['sometimes', 'boolean'],
            'feature_sms_notifications'     => ['sometimes', 'boolean'],
            'feature_certificate_verify'    => ['sometimes', 'boolean'],
            'feature_maintenance_mode'      => ['sometimes', 'boolean'],

            // SMS gateway credentials
            'sms_api_token'     => ['sometimes', 'nullable', 'string', 'max:200'],
            'sms_sid'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'sms_sender_id'     => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Primary colour must be a valid hex code (e.g. #0D2B4E).',
        ];
    }
}
