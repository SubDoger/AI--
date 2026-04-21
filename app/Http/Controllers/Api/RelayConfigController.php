<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RelayConfig\StoreRelayConfigRequest;
use App\Http\Requests\RelayConfig\UpdateRelayConfigRequest;
use App\Models\RelayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RelayConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $configs = $request->user()
            ->relayConfigs()
            ->latest('is_active')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $configs,
        ]);
    }

    public function store(StoreRelayConfigRequest $request): JsonResponse
    {
        $user = $request->user();

        $config = DB::transaction(function () use ($request, $user): RelayConfig {
            $apiKeys = $this->parseApiKeys($request->string('api_key')->value());
            $shouldActivate = $request->boolean('is_active')
                || ! $user->relayConfigs()->where('is_active', true)->exists();

            if ($shouldActivate) {
                $user->relayConfigs()->update(['is_active' => false]);
            }

            $config = $user->relayConfigs()->create([
                'name' => $request->string('name')->trim()->value(),
                'base_url' => $this->normalizeBaseUrl($request->string('base_url')->trim()->value()),
                'api_key' => $apiKeys[0] ?? '',
                'api_keys' => $apiKeys,
                'api_key_cursor' => 0,
                'api_style' => $request->string('api_style')->value(),
                'model' => $request->string('model')->trim()->value(),
                'available_models' => $this->parseAvailableModels($request->input('available_models', [])),
                'timeout' => (int) $request->input('timeout', 120),
                'is_active' => $shouldActivate,
            ]);

            return $config;
        });

        return response()->json([
            'data' => $config->fresh(),
            'message' => '中转配置已创建。',
        ], 201);
    }

    public function update(UpdateRelayConfigRequest $request, RelayConfig $relayConfig): JsonResponse
    {
        abort_unless($relayConfig->user_id === $request->user()->id, 404);

        $config = DB::transaction(function () use ($request, $relayConfig): RelayConfig {
            $shouldActivate = $request->boolean('is_active');

            if ($shouldActivate) {
                RelayConfig::query()
                    ->where('user_id', $relayConfig->user_id)
                    ->whereKeyNot($relayConfig->id)
                    ->update(['is_active' => false]);
            }

            $attributes = [
                'name' => $request->string('name')->trim()->value(),
                'base_url' => $this->normalizeBaseUrl($request->string('base_url')->trim()->value()),
                'api_style' => $request->string('api_style')->value(),
                'model' => $request->string('model')->trim()->value(),
                'available_models' => $this->parseAvailableModels($request->input('available_models', [])),
                'timeout' => (int) $request->input('timeout', 120),
                'is_active' => $shouldActivate,
            ];

            if ($request->filled('api_key')) {
                $relayConfig->syncApiKeys($this->parseApiKeys($request->string('api_key')->value()));
            }

            $relayConfig->update($attributes);

            if (! RelayConfig::query()
                ->where('user_id', $relayConfig->user_id)
                ->where('is_active', true)
                ->exists()) {
                $relayConfig->forceFill(['is_active' => true])->save();
            }

            return $relayConfig->fresh();
        });

        return response()->json([
            'data' => $config,
            'message' => '中转配置已更新。',
        ]);
    }

    public function destroy(Request $request, RelayConfig $relayConfig): JsonResponse
    {
        abort_unless($relayConfig->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($relayConfig): void {
            $userId = $relayConfig->user_id;
            $wasActive = $relayConfig->is_active;

            $relayConfig->delete();

            if ($wasActive) {
                RelayConfig::query()
                    ->where('user_id', $userId)
                    ->latest('updated_at')
                    ->first()
                    ?->forceFill(['is_active' => true])
                    ->save();
            }
        });

        return response()->json([
            'message' => '中转配置已删除。',
        ]);
    }

    public function activate(Request $request, RelayConfig $relayConfig): JsonResponse
    {
        abort_unless($relayConfig->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($relayConfig): void {
            RelayConfig::query()
                ->where('user_id', $relayConfig->user_id)
                ->update(['is_active' => false]);

            $relayConfig->forceFill(['is_active' => true])->save();
        });

        return response()->json([
            'data' => $relayConfig->fresh(),
            'message' => '已切换当前中转。',
        ]);
    }

    protected function normalizeBaseUrl(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'https://'.$trimmed;
        }

        return rtrim($trimmed, '/');
    }

    protected function parseApiKeys(string $value): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $item): string => trim($item),
                preg_split('/[\r\n,，]+/', $value) ?: [],
            ),
            static fn (string $item): bool => $item !== '',
        ));
    }

    protected function parseAvailableModels(array $models): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($item): string => trim((string) $item), $models),
            static fn (string $item): bool => $item !== '',
        )));
    }
}
