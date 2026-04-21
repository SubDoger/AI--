<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AgentProfileController;
use App\Http\Controllers\Api\AgentGroupController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\RelayConfigController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => ['status' => 'ok']);
    Route::get('/public-settings', [SettingsController::class, 'showPublic']);

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/admin/settings', [SettingsController::class, 'showAdmin']);
        Route::put('/admin/settings', [SettingsController::class, 'updateAdmin']);
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::post('/admin/users', [AdminUserController::class, 'store']);
        Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
        Route::get('/relay-configs', [RelayConfigController::class, 'index']);
        Route::post('/relay-configs', [RelayConfigController::class, 'store']);
        Route::put('/relay-configs/{relayConfig}', [RelayConfigController::class, 'update']);
        Route::delete('/relay-configs/{relayConfig}', [RelayConfigController::class, 'destroy']);
        Route::post('/relay-configs/{relayConfig}/activate', [RelayConfigController::class, 'activate']);
        Route::get('/relay-configs/{relayConfig}/upstream-models', [ModelController::class, 'index']);
        Route::get('/agent-groups', [AgentGroupController::class, 'index']);
        Route::post('/agent-groups', [AgentGroupController::class, 'store']);
        Route::put('/agent-groups/{agentGroup}', [AgentGroupController::class, 'update']);
        Route::delete('/agent-groups/{agentGroup}', [AgentGroupController::class, 'destroy']);
        Route::get('/knowledge-bases', [KnowledgeBaseController::class, 'index']);
        Route::post('/knowledge-bases', [KnowledgeBaseController::class, 'store']);
        Route::put('/knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'update']);
        Route::delete('/knowledge-bases/{knowledgeBase}', [KnowledgeBaseController::class, 'destroy']);
        Route::get('/agent-profiles', [AgentProfileController::class, 'index']);
        Route::post('/agent-profiles', [AgentProfileController::class, 'store']);
        Route::get('/agent-profiles/{agentProfile}/conversations', [AgentProfileController::class, 'conversations']);
        Route::post('/agent-profiles/{agentProfile}/duplicate', [AgentProfileController::class, 'duplicate']);
        Route::put('/agent-profiles/{agentProfile}', [AgentProfileController::class, 'update']);
        Route::delete('/agent-profiles/{agentProfile}', [AgentProfileController::class, 'destroy']);

        Route::apiResource('conversations', ConversationController::class);
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('/conversations/{conversation}/messages/stream', [MessageController::class, 'stream']);
    });
});
