<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RelayConfig;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        if (! (bool) $currentUser?->is_admin) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '仅管理员可新增用户。',
            ], 403);
        }

        $validated = $request->validate([
            'adult_content_enabled' => ['nullable', 'boolean'],
            'adult_content_toggle_visible' => ['nullable', 'boolean'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'is_admin' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = User::query()->create([
            'adult_content_enabled' => (bool) ($validated['adult_content_enabled'] ?? false),
            'adult_content_toggle_visible' => array_key_exists('adult_content_toggle_visible', $validated)
                ? (bool) $validated['adult_content_toggle_visible']
                : true,
            'adult_content_updated_at' => array_key_exists('adult_content_enabled', $validated)
                ? now()
                : null,
            'email' => trim((string) $validated['email']),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'name' => trim((string) $validated['name']),
            'password' => (string) $validated['password'],
            'use_admin_relay_preset' => false,
        ]);

        return response()->json([
            'data' => $user->only([
                'id',
                'name',
                'email',
                'is_admin',
                'adult_content_enabled',
                'adult_content_toggle_visible',
                'use_admin_relay_preset',
                'assigned_admin_relay_config_id',
                'assigned_admin_relay_key_index',
                'created_at',
                'updated_at',
            ]),
            'message' => '用户已创建。',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if (! (bool) $request->user()?->is_admin) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '仅管理员可访问用户管理。',
            ], 403);
        }

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'is_admin',
                'adult_content_enabled',
                'adult_content_toggle_visible',
                'use_admin_relay_preset',
                'assigned_admin_relay_config_id',
                'assigned_admin_relay_key_index',
                'created_at',
                'updated_at',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if (! (bool) $currentUser?->is_admin) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '仅管理员可修改用户。',
            ], 403);
        }

        $validated = $request->validate([
            'adult_content_enabled' => ['nullable', 'boolean'],
            'adult_content_toggle_visible' => ['nullable', 'boolean'],
            'assigned_admin_relay_config_id' => ['nullable', 'integer'],
            'assigned_admin_relay_key_index' => ['nullable', 'integer', 'min:0'],
            'is_admin' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'use_admin_relay_preset' => ['nullable', 'boolean'],
        ]);

        $assignedRelayConfig = null;
        if (array_key_exists('assigned_admin_relay_config_id', $validated) && $validated['assigned_admin_relay_config_id']) {
            $assignedRelayConfig = RelayConfig::query()
                ->where('user_id', $currentUser?->id)
                ->find($validated['assigned_admin_relay_config_id']);

            if (! $assignedRelayConfig) {
                return response()->json([
                    'code' => 'INVALID_RELAY_CONFIG',
                    'message' => '只能为用户指定当前管理员账号下的中转配置。',
                ], 422);
            }

            $keyIndex = $validated['assigned_admin_relay_key_index'] ?? null;
            $keys = $assignedRelayConfig->getApiKeys();

            if ($keyIndex !== null && ! array_key_exists((int) $keyIndex, $keys)) {
                return response()->json([
                    'code' => 'INVALID_RELAY_KEY',
                    'message' => '指定的中转密钥不存在。',
                ], 422);
            }
        }

        if (
            array_key_exists('is_admin', $validated) &&
            $currentUser &&
            $currentUser->is($user) &&
            ! (bool) $validated['is_admin']
        ) {
            return response()->json([
                'code' => 'INVALID_OPERATION',
                'message' => '不能取消当前登录管理员自己的管理员权限。',
            ], 422);
        }

        $user->forceFill([
            'adult_content_enabled' => array_key_exists('adult_content_enabled', $validated) ? (bool) $validated['adult_content_enabled'] : $user->adult_content_enabled,
            'adult_content_toggle_visible' => array_key_exists('adult_content_toggle_visible', $validated) ? (bool) $validated['adult_content_toggle_visible'] : $user->adult_content_toggle_visible,
            'assigned_admin_relay_config_id' => array_key_exists('assigned_admin_relay_config_id', $validated)
                ? ($validated['assigned_admin_relay_config_id'] ?: null)
                : $user->assigned_admin_relay_config_id,
            'assigned_admin_relay_key_index' => array_key_exists('assigned_admin_relay_config_id', $validated) && empty($validated['assigned_admin_relay_config_id'])
                ? null
                : (array_key_exists('assigned_admin_relay_key_index', $validated)
                    ? ($validated['assigned_admin_relay_key_index'] ?? null)
                    : $user->assigned_admin_relay_key_index),
            'is_admin' => array_key_exists('is_admin', $validated) ? (bool) $validated['is_admin'] : $user->is_admin,
            'name' => array_key_exists('name', $validated) ? trim((string) $validated['name']) : $user->name,
            'use_admin_relay_preset' => array_key_exists('use_admin_relay_preset', $validated)
                ? (bool) $validated['use_admin_relay_preset']
                : $user->use_admin_relay_preset,
        ])->save();

        if (! $user->use_admin_relay_preset) {
            $user->forceFill([
                'assigned_admin_relay_config_id' => null,
                'assigned_admin_relay_key_index' => null,
            ])->save();
        }

        return response()->json([
            'data' => $user->only([
                'id',
                'name',
                'email',
                'is_admin',
                'adult_content_enabled',
                'adult_content_toggle_visible',
                'use_admin_relay_preset',
                'assigned_admin_relay_config_id',
                'assigned_admin_relay_key_index',
                'created_at',
                'updated_at',
            ]),
            'message' => '用户信息已更新。',
        ]);
    }
}
