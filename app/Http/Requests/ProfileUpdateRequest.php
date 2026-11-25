<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Email is optional for students (phone is their login). Phone changes go through support. */
class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'city' => ['nullable', 'string', 'max:100'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}
