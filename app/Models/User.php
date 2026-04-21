<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'adult_content_enabled', 'adult_content_toggle_visible', 'use_admin_relay_preset', 'assigned_admin_relay_config_id', 'assigned_admin_relay_key_index', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adult_content_enabled' => 'boolean',
            'adult_content_toggle_visible' => 'boolean',
            'assigned_admin_relay_config_id' => 'integer',
            'assigned_admin_relay_key_index' => 'integer',
            'adult_content_updated_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'use_admin_relay_preset' => 'boolean',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function relayConfigs(): HasMany
    {
        return $this->hasMany(RelayConfig::class);
    }

    public function assignedAdminRelayConfig()
    {
        return $this->belongsTo(RelayConfig::class, 'assigned_admin_relay_config_id');
    }

    public function agentProfiles(): HasMany
    {
        return $this->hasMany(AgentProfile::class);
    }

    public function agentGroups(): HasMany
    {
        return $this->hasMany(AgentGroup::class);
    }

    public function knowledgeBases(): HasMany
    {
        return $this->hasMany(KnowledgeBase::class);
    }
}
