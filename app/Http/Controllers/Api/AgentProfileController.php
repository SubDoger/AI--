<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentProfile\StoreAgentProfileRequest;
use App\Http\Requests\AgentProfile\UpdateAgentProfileRequest;
use App\Models\AgentProfile;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AgentProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = $request->user()
            ->agentProfiles()
            ->with([
                'relayConfig:id,name,model',
                'group:id,name,color',
                'knowledgeBases:id,name',
                'collaborators:id,name,avatar,role_label',
            ])
            ->withCount('conversations')
            ->latest('is_active')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function store(StoreAgentProfileRequest $request): JsonResponse
    {
        $profile = DB::transaction(function () use ($request): AgentProfile {
            $profile = $request->user()->agentProfiles()->create([
                ...$this->extractAttributes($request),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncRelations($profile, $request);

            return $profile;
        });

        return response()->json([
            'data' => $profile->fresh()->load([
                'relayConfig:id,name,model',
                'group:id,name,color',
                'knowledgeBases:id,name',
                'collaborators:id,name,avatar,role_label',
            ])->loadCount('conversations'),
            'message' => '智能体已创建。',
        ], 201);
    }

    public function update(
        UpdateAgentProfileRequest $request,
        AgentProfile $agentProfile,
    ): JsonResponse {
        abort_unless($agentProfile->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($agentProfile, $request): void {
            $agentProfile->update([
                ...$this->extractAttributes($request),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncRelations($agentProfile, $request);
        });

        return response()->json([
            'data' => $agentProfile->fresh()->load([
                'relayConfig:id,name,model',
                'group:id,name,color',
                'knowledgeBases:id,name',
                'collaborators:id,name,avatar,role_label',
            ])->loadCount('conversations'),
            'message' => '智能体已更新。',
        ]);
    }

    public function duplicate(Request $request, AgentProfile $agentProfile): JsonResponse
    {
        abort_unless($agentProfile->user_id === $request->user()->id, 404);

        $duplicate = DB::transaction(function () use ($request, $agentProfile): AgentProfile {
            $duplicate = $request->user()->agentProfiles()->create([
                ...Arr::except($agentProfile->toArray(), [
                    'id',
                    'created_at',
                    'updated_at',
                    'conversations_count',
                    'relay_config',
                    'group',
                    'knowledge_bases',
                    'collaborators',
                    'user_id',
                ]),
                'name' => $agentProfile->name.' 副本',
                'is_active' => false,
            ]);

            $duplicate->knowledgeBases()->sync($agentProfile->knowledgeBases()->pluck('knowledge_bases.id'));
            $duplicate->collaborators()->sync($agentProfile->collaborators()->pluck('agent_profiles.id'));

            return $duplicate;
        });

        return response()->json([
            'data' => $duplicate->load([
                'relayConfig:id,name,model',
                'group:id,name,color',
                'knowledgeBases:id,name',
                'collaborators:id,name,avatar,role_label',
            ])->loadCount('conversations'),
            'message' => '智能体已复制。',
        ], 201);
    }

    public function conversations(Request $request, AgentProfile $agentProfile): JsonResponse
    {
        abort_unless($agentProfile->user_id === $request->user()->id, 404);

        $items = Conversation::query()
            ->where('user_id', $request->user()->id)
            ->where('agent_profile_id', $agentProfile->id)
            ->withCount('messages')
            ->latest('last_message_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function destroy(Request $request, AgentProfile $agentProfile): JsonResponse
    {
        abort_unless($agentProfile->user_id === $request->user()->id, 404);

        $agentProfile->delete();

        return response()->json([
            'message' => '智能体已删除。',
        ]);
    }

    protected function extractAttributes(StoreAgentProfileRequest|UpdateAgentProfileRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->value(),
            'agent_group_id' => $request->input('agent_group_id'),
            'role_label' => $this->nullableTrimmed($request, 'role_label'),
            'category' => $this->nullableTrimmed($request, 'category'),
            'tags' => $this->normalizeList($request->input('tags', [])),
            'avatar' => $request->input('avatar')
                ? mb_substr($request->string('avatar')->trim()->value(), 0, 32)
                : 'AI',
            'model' => $request->string('model')->trim()->value(),
            'starter_prompt' => $this->nullableTrimmed($request, 'starter_prompt'),
            'description' => $this->nullableTrimmed($request, 'description'),
            'capabilities' => $this->nullableTrimmed($request, 'capabilities'),
            'welcome_message' => $this->nullableTrimmed($request, 'welcome_message'),
            'suggested_prompts' => $this->normalizeList($request->input('suggested_prompts', [])),
            'collaboration_mode' => $request->input('collaboration_mode', 'solo'),
            'system_prompt' => $this->nullableTrimmed($request, 'system_prompt'),
            'relay_config_id' => $request->input('relay_config_id'),
        ];
    }

    protected function syncRelations(
        AgentProfile $agentProfile,
        StoreAgentProfileRequest|UpdateAgentProfileRequest $request,
    ): void {
        $knowledgeBaseIds = collect($request->input('knowledge_base_ids', []))
            ->map(fn ($item) => (int) $item)
            ->filter()
            ->values()
            ->all();

        $collaboratorIds = collect($request->input('collaborator_ids', []))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => $item !== $agentProfile->id)
            ->values()
            ->all();

        $agentProfile->knowledgeBases()->sync($knowledgeBaseIds);
        $agentProfile->collaborators()->sync($collaboratorIds);
    }

    protected function normalizeList(mixed $items): ?array
    {
        $values = collect(is_array($items) ? $items : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }

    protected function nullableTrimmed(StoreAgentProfileRequest|UpdateAgentProfileRequest $request, string $key): ?string
    {
        return $request->input($key)
            ? $request->string($key)->trim()->value()
            : null;
    }
}
