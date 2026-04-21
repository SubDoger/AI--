<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'relay_config_id',
    'agent_group_id',
    'name',
    'role_label',
    'category',
    'tags',
    'avatar',
    'model',
    'starter_prompt',
    'description',
    'capabilities',
    'welcome_message',
    'suggested_prompts',
    'collaboration_mode',
    'system_prompt',
    'is_active',
])]
class AgentProfile extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'collaboration_mode' => 'string',
            'suggested_prompts' => 'array',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relayConfig(): BelongsTo
    {
        return $this->belongsTo(RelayConfig::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AgentGroup::class, 'agent_group_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBase::class);
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(
            AgentProfile::class,
            'agent_profile_collaborators',
            'agent_profile_id',
            'collaborator_agent_profile_id',
        );
    }
}
