<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateAdminSettingsRequest;
use App\Http\Requests\Auth\UpdateSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function showPublic(): JsonResponse
    {
        $setting = AppSetting::current();

        return response()->json([
            'data' => [
                'login_description' => $setting->login_description,
                'login_title' => $setting->login_title,
                'site_name' => $setting->site_name,
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'adult_content_enabled' => (bool) $user?->adult_content_enabled,
                'adult_content_toggle_visible' => (bool) $user?->adult_content_toggle_visible,
                'adult_content_updated_at' => $user?->adult_content_updated_at,
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! (bool) $user?->adult_content_toggle_visible) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '当前账号未开放成人偏好开关。',
            ], 403);
        }

        $user?->forceFill([
            'adult_content_enabled' => $request->boolean('adult_content_enabled'),
            'adult_content_updated_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'adult_content_enabled' => (bool) $user?->adult_content_enabled,
                'adult_content_toggle_visible' => (bool) $user?->adult_content_toggle_visible,
                'adult_content_updated_at' => $user?->adult_content_updated_at,
            ],
            'message' => '偏好设置已更新。',
        ]);
    }

    public function showAdmin(Request $request): JsonResponse
    {
        if (! (bool) $request->user()?->is_admin) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '仅管理员可访问系统设置。',
            ], 403);
        }

        $setting = AppSetting::current();

        return response()->json([
            'data' => [
                'login_description' => $setting->login_description,
                'login_title' => $setting->login_title,
                'site_name' => $setting->site_name,
            ],
        ]);
    }

    public function updateAdmin(UpdateAdminSettingsRequest $request): JsonResponse
    {
        if (! (bool) $request->user()?->is_admin) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => '仅管理员可更新系统设置。',
            ], 403);
        }

        $setting = AppSetting::current();
        $setting->forceFill([
            'login_description' => $request->string('login_description')->trim()->value(),
            'login_title' => $request->string('login_title')->trim()->value(),
            'site_name' => $request->string('site_name')->trim()->value(),
        ])->save();

        return response()->json([
            'data' => [
                'login_description' => $setting->login_description,
                'login_title' => $setting->login_title,
                'site_name' => $setting->site_name,
            ],
            'message' => '系统设置已更新。',
        ]);
    }
}
