<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Models\AgentProfile;
use App\Models\Conversation;
use App\Models\RelayConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->user()
            ->conversations()
            ->with([
                'agentProfile:id,name,avatar,role_label',
                'relayConfig:id,name,model,available_models',
            ])
            ->withCount('messages')
            ->latest('last_message_at')
            ->latest('id');

        if ($request->filled('agent_profile_id')) {
            $query->where('agent_profile_id', (int) $request->input('agent_profile_id'));
        }

        $conversations = $query->get();

        return response()->json([
            'data' => $conversations,
        ]);
    }

    public function store(StoreConversationRequest $request): Response
    {
        $user = $request->user();
        $agentProfile = $this->resolveAgentProfile($request);
        $relayConfig = $this->resolveRelayConfig($request, $agentProfile);

        $conversation = $user->conversations()->create([
            'title' => $request->input('title', $agentProfile?->name ?: '新对话'),
            'model' => $this->resolveConversationModel(
                requestedModel: $request->input('model'),
                fallbackModel: $agentProfile?->model ?: ($relayConfig?->model ?: config('services.xai.model')),
                relayConfig: $relayConfig,
            ),
            'relay_config_id' => $relayConfig?->id,
            'agent_profile_id' => $agentProfile?->id,
            'system_prompt' => $request->input('system_prompt', $agentProfile?->system_prompt),
        ]);

        return response()->json([
            'data' => $conversation->load([
                'agentProfile:id,name,avatar,role_label',
                'relayConfig:id,name,model,available_models',
            ])->loadCount('messages'),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return response()->json([
            'data' => $conversation->load([
                'messages',
                'agentProfile:id,name,avatar,role_label',
                'relayConfig:id,name,model,available_models',
            ]),
        ]);
    }

    public function update(StoreConversationRequest $request, Conversation $conversation): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $agentProfile = $this->resolveAgentProfile($request);
        $relayConfig = $this->resolveRelayConfig($request, $agentProfile);

        $conversation->update([
            ...$request->safe()->except(['model']),
            'model' => $request->has('model')
                ? $this->resolveConversationModel(
                    requestedModel: $request->input('model'),
                    fallbackModel: $conversation->model,
                    relayConfig: $request->has('relay_config_id')
                        ? ($relayConfig ?? $conversation->relayConfig)
                        : ($conversation->relayConfig ?? $relayConfig),
                )
                : $conversation->model,
            'relay_config_id' => $request->has('relay_config_id')
                ? $request->input('relay_config_id')
                : ($relayConfig?->id ?? $conversation->relay_config_id),
            'agent_profile_id' => $request->has('agent_profile_id')
                ? $request->input('agent_profile_id')
                : ($agentProfile?->id ?? $conversation->agent_profile_id),
        ]);

        return response()->json([
            'data' => $conversation->fresh()->load([
                'agentProfile:id,name,avatar,role_label',
                'relayConfig:id,name,model,available_models',
            ])->loadCount('messages'),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return response()->noContent();
    }

    protected function resolveAgentProfile(StoreConversationRequest $request): ?AgentProfile
    {
        $agentProfileId = $request->input('agent_profile_id');

        if (! $agentProfileId) {
            return null;
        }

        return $request->user()
            ->agentProfiles()
            ->find($agentProfileId);
    }

    protected function resolveRelayConfig(
        StoreConversationRequest $request,
        ?AgentProfile $agentProfile = null,
    ): ?RelayConfig {
        $user = $request->user();

        if ($user?->use_admin_relay_preset && $user->assigned_admin_relay_config_id) {
            return RelayConfig::query()
                ->whereKey($user->assigned_admin_relay_config_id)
                ->first();
        }

        $relayConfigId = $request->input('relay_config_id') ?: $agentProfile?->relay_config_id;

        if ($relayConfigId) {
            return $user
                ->relayConfigs()
                ->find($relayConfigId);
        }

        return $user
            ?->relayConfigs()
            ->where('is_active', true)
            ->first();
    }

    protected function resolveConversationModel(
        ?string $requestedModel,
        ?string $fallbackModel,
        ?RelayConfig $relayConfig = null,
    ): string {
        $resolvedModel = trim((string) ($requestedModel ?: $fallbackModel ?: config('services.xai.model')));

        if ($relayConfig && ! $relayConfig->supportsModel($resolvedModel)) {
            $availableModel = $relayConfig->getAvailableModels()[0] ?? $relayConfig->model ?? config('services.xai.model');
            return trim((string) $availableModel);
        }

        return $resolvedModel;
    }
}
