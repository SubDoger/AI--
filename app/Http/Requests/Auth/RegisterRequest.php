<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入昵称。',
            'email.required' => '请输入邮箱地址。',
            'email.email' => '请输入有效的邮箱地址。',
            'email.unique' => '该邮箱已被注册。',
            'password.required' => '请输入密码。',
            'password.min' => '密码至少需要 8 位。',
            'password.confirmed' => '两次输入的密码不一致。',
            'device_name.max' => '设备名称过长。',
        ];
    }
}
