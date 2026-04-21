<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\RelayConfig;
use App\Services\Xai\XaiClient;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    protected function normalizeStreamException(Throwable $exception): array
    {
        $message = $exception->getMessage();
        $code = 'SERVER_ERROR';

        if (str_contains($message, 'Admission denied') || str_contains($message, 'ResourceExhausted')) {
            return [
                'code' => 'MODEL_OVERLOADED',
                'message' => '当前模型繁忙，请稍后重试。',
            ];
        }

        if (
            str_contains($message, '暂不支持模型')
            || str_contains($message, '模型不存在')
            || str_contains($message, 'unsupported model')
            || str_contains($message, 'invalid model')
            || str_contains($message, '不匹配')
            || str_contains($message, '不可用')
        ) {
            return [
                'code' => 'MODEL_NOT_SUPPORTED',
                'message' => $message,
            ];
        }

        if (
            str_contains($message, '额度不足')
            || str_contains($message, '余额不足')
            || str_contains($message, 'quota')
        ) {
            return [
                'code' => 'QUOTA_EXHAUSTED',
                'message' => $message,
            ];
        }

        if (str_contains($message, 'Request Entity Too Large')) {
            return [
                'code' => 'REQUEST_TOO_LARGE',
                'message' => '上下文内容过长，请新建对话或缩短输入。',
            ];
        }

        return [
            'code' => $code,
            'message' => $message ?: '服务暂时异常，请稍后重试。',
        ];
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        return response()->json([
            'data' => $conversation->messages()->get(),
        ]);
    }

    public function store(
        StoreMessageRequest $request,
        Conversation $conversation,
        XaiClient $xaiClient,
    ): JsonResponse {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $relayConfigId = $conversation->relay_config_id;
        $requestedModel = $request->input('model', $conversation->model);
        $relayConfig = $conversation->relayConfig;

        if ($relayConfig && ! $relayConfig->supportsModel($requestedModel)) {
            return response()->json([
                'code' => 'MODEL_NOT_SUPPORTED',
                'message' => $this->buildUnsupportedModelMessage($relayConfig, (string) $requestedModel),
            ], 422);
        }

        $userMessage = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'relay_config_id' => $relayConfigId,
            'role' => 'user',
            'content' => $request->string('content')->toString(),
            'model' => $requestedModel,
        ]);

        $result = $xaiClient->createResponse(
            conversation: $conversation->fresh('messages'),
            model: $requestedModel,
        );

        $assistantMessage = $conversation->messages()->create([
            'relay_config_id' => $relayConfigId,
            'role' => 'assistant',
            'content' => $result['text'],
            'model' => $result['model'],
            'provider_response_id' => $result['provider_response_id'],
            'prompt_tokens' => $result['usage']['input_tokens'] ?? null,
            'completion_tokens' => $result['usage']['output_tokens'] ?? null,
            'total_tokens' => $result['usage']['total_tokens'] ?? null,
            'meta' => $result['meta'],
        ]);

        $this->touchConversation($conversation, $userMessage->content);

        return response()->json([
            'data' => [
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ],
        ], 201);
    }

    public function stream(
        StoreMessageRequest $request,
        Conversation $conversation,
        XaiClient $xaiClient,
    ): StreamedResponse {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $relayConfigId = $conversation->relay_config_id;
        $requestedModel = $request->input('model', $conversation->model);
        $relayConfig = $conversation->relayConfig;

        if ($relayConfig && ! $relayConfig->supportsModel($requestedModel)) {
            return response()->stream(function () use ($relayConfig, $requestedModel): void {
                echo "event: error\n";
                echo 'data: '.json_encode([
                    'code' => 'MODEL_NOT_SUPPORTED',
                    'message' => $this->buildUnsupportedModelMessage($relayConfig, (string) $requestedModel),
                ], JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();
            }, 200, [
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $userMessage = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'relay_config_id' => $relayConfigId,
            'role' => 'user',
            'content' => $request->string('content')->toString(),
            'model' => $requestedModel,
        ]);

        $this->touchConversation($conversation, $userMessage->content);

        $response = new StreamedResponse(function () use ($conversation, $request, $xaiClient, $userMessage, $relayConfigId, $requestedModel): void {
            ignore_user_abort(true);

            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            echo "event: ready\n";
            echo 'data: '.json_encode(['message_id' => $userMessage->id], JSON_UNESCAPED_UNICODE)."\n\n";
            @ob_flush();
            flush();

            try {
                $result = $xaiClient->streamResponse(
                    conversation: $conversation->fresh('messages'),
                    model: $requestedModel,
                    onDelta: function (string $chunk, array $rawChunk, string $reasoningChunk = ''): void {
                        if ($reasoningChunk !== '') {
                            echo "event: reasoning\n";
                            echo 'data: '.json_encode(['content' => $reasoningChunk], JSON_UNESCAPED_UNICODE)."\n\n";
                            @ob_flush();
                            flush();
                        }

                        if ($chunk !== '') {
                            echo "event: delta\n";
                            echo 'data: '.json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE)."\n\n";
                            @ob_flush();
                            flush();
                        }
                    },
                );

                $assistantMessage = $conversation->messages()->create([
                    'relay_config_id' => $relayConfigId,
                    'role' => 'assistant',
                    'content' => $result['text'],
                    'model' => $result['model'],
                    'provider_response_id' => $result['provider_response_id'],
                    'prompt_tokens' => $result['usage']['input_tokens'] ?? null,
                    'completion_tokens' => $result['usage']['output_tokens'] ?? null,
                    'total_tokens' => $result['usage']['total_tokens'] ?? null,
                    'meta' => $result['meta'],
                ]);

                echo "event: done\n";
                echo 'data: '.json_encode([
                    'assistant_message' => $assistantMessage,
                ], JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();
            } catch (Throwable $exception) {
                $error = $this->normalizeStreamException($exception);
                echo "event: error\n";
                echo 'data: '.json_encode($error, JSON_UNESCAPED_UNICODE)."\n\n";
                @ob_flush();
                flush();
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    protected function buildUnsupportedModelMessage(RelayConfig $relayConfig, string $model): string
    {
        $availableModels = $relayConfig->getAvailableModels();

        if ($availableModels === []) {
            return "当前中转暂不支持模型 {$model}，请更换模型或切换中转。";
        }

        return sprintf(
            '模型 %s 不在当前中转可用模型列表中。请改用：%s',
            $model,
            implode('、', array_slice($availableModels, 0, 12)),
        );
    }

    protected function touchConversation(Conversation $conversation, string $content): void
    {
        $conversation->forceFill([
            'last_message_at' => now(),
            'title' => $conversation->messages()->count() <= 1
                ? mb_strimwidth($content, 0, 48, '...')
                : $conversation->title,
        ])->save();
    }
}
