<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreKnowledgeBaseRequest;
use App\Http\Requests\KnowledgeBase\UpdateKnowledgeBaseRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->knowledgeBases()
            ->withCount('agentProfiles')
            ->latest('is_active')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(StoreKnowledgeBaseRequest $request): JsonResponse
    {
        $item = $request->user()->knowledgeBases()->create([
            'name' => $request->string('name')->trim()->value(),
            'description' => $request->input('description')
                ? $request->string('description')->trim()->value()
                : null,
            'summary' => $request->input('summary')
                ? $request->string('summary')->trim()->value()
                : null,
            'content' => $request->input('content')
                ? $request->string('content')->trim()->value()
                : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'data' => $item->loadCount('agentProfiles'),
        ], 201);
    }

    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase): JsonResponse
    {
        abort_unless($knowledgeBase->user_id === $request->user()->id, 404);

        $knowledgeBase->update([
            'name' => $request->string('name')->trim()->value(),
            'description' => $request->input('description')
                ? $request->string('description')->trim()->value()
                : null,
            'summary' => $request->input('summary')
                ? $request->string('summary')->trim()->value()
                : null,
            'content' => $request->input('content')
                ? $request->string('content')->trim()->value()
                : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'data' => $knowledgeBase->fresh()->loadCount('agentProfiles'),
        ]);
    }

    public function destroy(Request $request, KnowledgeBase $knowledgeBase): JsonResponse
    {
        abort_unless($knowledgeBase->user_id === $request->user()->id, 404);
        $knowledgeBase->delete();

        return response()->json([
            'message' => '知识库已删除。',
        ]);
    }
}
