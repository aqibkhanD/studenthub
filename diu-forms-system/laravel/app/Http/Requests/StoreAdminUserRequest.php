<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'super_admin';
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'          => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:120'],
            'email'         => [
                $isUpdate ? 'sometimes' : 'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role'          => [
                $isUpdate ? 'sometimes' : 'required',
                Rule::in(['admin', 'super_admin']),
            ],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'is_active'     => ['boolean'],

            // Password: required on create, optional on update
            'password' => array_merge(
                $isUpdate ? ['sometimes', 'nullable'] : ['required'],
                [Password::min(8)->letters()->mixedCase()->numbers()]
            ),
        ];
    }
}
