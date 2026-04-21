<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RelayConfig;
use App\Services\Xai\XaiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ModelController extends Controller
{
    public function index(Request $request, RelayConfig $relayConfig, XaiClient $xaiClient): JsonResponse
    {
        abort_unless((int) $relayConfig->getAttribute('user_id') === (int) $request->user()->id, 404);

        try {
            return response()->json([
                'data' => $xaiClient->fetchUpstreamModels($relayConfig),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage() ?: '获取模型列表失败。',
            ], 500);
        }
    }
}
