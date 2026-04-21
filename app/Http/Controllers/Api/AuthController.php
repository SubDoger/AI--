<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function serializeUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('assignedAdminRelayConfig:id,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'adult_content_enabled' => $user->adult_content_enabled,
            'adult_content_toggle_visible' => $user->adult_content_toggle_visible,
            'adult_content_updated_at' => $user->adult_content_updated_at,
            'use_admin_relay_preset' => $user->use_admin_relay_preset,
            'assigned_admin_relay_config_id' => $user->assigned_admin_relay_config_id,
            'assigned_admin_relay_key_index' => $user->assigned_admin_relay_key_index,
            'assigned_admin_relay_config_name' => $user->assignedAdminRelayConfig?->name,
        ];
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));
        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码不正确。'],
            ]);
        }

        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => '已退出登录。',
        ]);
    }
}
