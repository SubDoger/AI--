<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentGroup\StoreAgentGroupRequest;
use App\Http\Requests\AgentGroup\UpdateAgentGroupRequest;
use App\Models\AgentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groups = $request->user()
            ->agentGroups()
            ->withCount('agentProfiles')
            ->orderBy('sort_order')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $groups,
        ]);
    }

    public function store(StoreAgentGroupRequest $request): JsonResponse
    {
        $group = $request->user()->agentGroups()->create([
            'name' => $request->string('name')->trim()->value(),
            'color' => $request->input('color', 'slate'),
            'description' => $request->input('description')
                ? $request->string('description')->trim()->value()
                : null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        return response()->json([
            'data' => $group->loadCount('agentProfiles'),
        ], 201);
    }

    public function update(UpdateAgentGroupRequest $request, AgentGroup $agentGroup): JsonResponse
    {
        abort_unless($agentGroup->user_id === $request->user()->id, 404);

        $agentGroup->update([
            'name' => $request->string('name')->trim()->value(),
            'color' => $request->input('color', 'slate'),
            'description' => $request->input('description')
                ? $request->string('description')->trim()->value()
                : null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        return response()->json([
            'data' => $agentGroup->fresh()->loadCount('agentProfiles'),
        ]);
    }

    public function destroy(Request $request, AgentGroup $agentGroup): JsonResponse
    {
        abort_unless($agentGroup->user_id === $request->user()->id, 404);
        $agentGroup->delete();

        return response()->json([
            'message' => '分组已删除。',
        ]);
    }
}
