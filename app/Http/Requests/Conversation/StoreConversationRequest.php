<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string', 'max:4000'],
            'relay_config_id' => [
                'nullable',
                'integer',
                Rule::exists('relay_configs', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
            'agent_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('agent_profiles', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
        ];
    }
}
