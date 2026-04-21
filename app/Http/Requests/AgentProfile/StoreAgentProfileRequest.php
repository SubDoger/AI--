<?php

namespace App\Http\Requests\AgentProfile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'role_label' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array', 'max:12'],
            'tags.*' => ['string', 'max:30'],
            'agent_group_id' => [
                'nullable',
                'integer',
                Rule::exists('agent_groups', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
            'avatar' => ['nullable', 'string', 'max:32'],
            'model' => ['required', 'string', 'max:100'],
            'starter_prompt' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capabilities' => ['nullable', 'string', 'max:4000'],
            'welcome_message' => ['nullable', 'string', 'max:2000'],
            'suggested_prompts' => ['nullable', 'array', 'max:8'],
            'suggested_prompts.*' => ['string', 'max:120'],
            'collaboration_mode' => ['nullable', Rule::in(['solo', 'round_robin', 'supervisor'])],
            'knowledge_base_ids' => ['nullable', 'array', 'max:12'],
            'knowledge_base_ids.*' => [
                'integer',
                Rule::exists('knowledge_bases', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
            'collaborator_ids' => ['nullable', 'array', 'max:8'],
            'collaborator_ids.*' => [
                'integer',
                Rule::exists('agent_profiles', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
            'system_prompt' => ['nullable', 'string', 'max:8000'],
            'is_active' => ['nullable', 'boolean'],
            'relay_config_id' => [
                'nullable',
                'integer',
                Rule::exists('relay_configs', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
        ];
    }
}
