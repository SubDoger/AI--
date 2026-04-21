<?php

namespace App\Http\Requests\KnowledgeBase;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'content' => ['nullable', 'string', 'max:30000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
