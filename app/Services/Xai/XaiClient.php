<?php

namespace App\Services\Xai;

use App\Models\Conversation;
use App\Models\RelayConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XaiClient
{
    public function createResponse(Conversation $conversation, ?string $model = null): array
    {
        $relayConfig = $this->resolveRelayConfig($conversation);
        $apiKey = $this->resolveApiKey($relayConfig);

        if (! $apiKey) {
            throw new RuntimeException('XAI_API_KEY 未配置，暂时无法调用 Grok。');
        }

        $resolvedModel = $model ?: ($relayConfig?->model ?: config('services.xai.model'));
        $response = $this->ensureSuccessful(
            $this->request($relayConfig, $apiKey)
            ->post(
                $this->resolveEndpoint($relayConfig),
                $this->buildRequestPayload($conversation, $resolvedModel, false, $relayConfig),
            ),
            $resolvedModel,
        )
            ->json();

        $rawText = $this->extractText($response);
        $reasoning = $this->extractReasoning($response);
        $reasoningFromThink = $this->extractThinkReasoning($rawText);

        return [
            'text' => $this->stripThinkTags($rawText),
            'model' => $response['model'] ?? $resolvedModel,
            'provider_response_id' => $response['id'] ?? null,
            'usage' => [
                'input_tokens' => Arr::get($response, 'usage.prompt_tokens'),
                'output_tokens' => Arr::get($response, 'usage.completion_tokens'),
                'total_tokens' => Arr::get($response, 'usage.total_tokens'),
            ],
            'meta' => [
                'choices' => $response['choices'] ?? [],
                'reasoning_content' => trim($reasoning !== '' ? $reasoning : $reasoningFromThink),
            ],
        ];
    }

    public function streamResponse(
        Conversation $conversation,
        ?string $model = null,
        ?callable $onDelta = null,
    ): array {
        $relayConfig = $this->resolveRelayConfig($conversation);
        $apiKey = $this->resolveApiKey($relayConfig);

        if (! $apiKey) {
            throw new RuntimeException('XAI_API_KEY 未配置，暂时无法调用 Grok。');
        }

        $resolvedModel = $model ?: ($relayConfig?->model ?: config('services.xai.model'));
        $response = $this->ensureSuccessful(
            $this->request($relayConfig, $apiKey)
            ->withOptions(['stream' => true])
            ->post(
                $this->resolveEndpoint($relayConfig),
                $this->buildRequestPayload($conversation, $resolvedModel, true, $relayConfig),
            ),
            $resolvedModel,
        );

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $choices = [];
        $providerResponseId = null;
        $responseModel = $resolvedModel;
        $reasoning = '';
        $rawText = '';
        $usage = [];

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '[DONE]') {
                    break 2;
                }

                $chunk = json_decode($payload, true);

                if (! is_array($chunk)) {
                    continue;
                }

                $providerResponseId ??= $chunk['id'] ?? Arr::get($chunk, 'response.id');
                $responseModel = $chunk['model'] ?? $responseModel;
                $choices = $chunk['choices'] ?? Arr::get($chunk, 'response.output', $choices);

                $delta = $this->extractStreamContentDelta($chunk);
                $reasoningDelta = $this->extractStreamReasoningDelta($chunk);

                if (is_string($delta) && $delta !== '') {
                    $rawText .= $delta;

                    if ($onDelta) {
                        $onDelta($delta, $chunk);
                    }
                }

                if ($reasoningDelta !== '') {
                    $reasoning .= $reasoningDelta;

                    if ($onDelta) {
                        $onDelta('', $chunk, $reasoningDelta);
                    }
                }

                if (is_array($chunk['usage'] ?? null)) {
                    $usage = $chunk['usage'];
                } elseif (is_array(Arr::get($chunk, 'response.usage'))) {
                    $usage = Arr::get($chunk, 'response.usage');
                }
            }
        }

        return [
            'text' => $this->stripThinkTags($rawText),
            'model' => $responseModel,
            'provider_response_id' => $providerResponseId,
            'usage' => [
                'input_tokens' => Arr::get($usage, 'prompt_tokens'),
                'output_tokens' => Arr::get($usage, 'completion_tokens'),
                'total_tokens' => Arr::get($usage, 'total_tokens'),
            ],
            'meta' => [
                'choices' => $choices,
                'reasoning_content' => trim(
                    $reasoning !== '' ? $reasoning : $this->extractThinkReasoning($rawText)
                ),
                'streamed' => true,
            ],
        ];
    }

    public function fetchUpstreamModels(?RelayConfig $relayConfig = null): array
    {
        $apiKey = $this->resolveApiKey($relayConfig) ?: config('services.xai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('XAI_API_KEY 未配置，暂时无法获取模型列表。');
        }

        $response = $this->ensureSuccessful(
            $this->request($relayConfig, $apiKey)
            ->get('/models'),
        )
            ->json();

        $models = $response['data'] ?? $response['models'] ?? $response['result'] ?? [];

        if (! is_array($models)) {
            return [];
        }

        $normalized = [];

        foreach ($models as $item) {
            if (is_string($item)) {
                $normalized[] = [
                    'id' => $item,
                    'name' => $item,
                    'owned_by' => null,
                    'object' => 'model',
                ];

                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? $item['model'] ?? '');

            if ($id === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'name' => (string) ($item['name'] ?? $item['display_name'] ?? $id),
                'owned_by' => $item['owned_by'] ?? $item['provider'] ?? null,
                'object' => (string) ($item['object'] ?? 'model'),
                'raw' => $item,
            ];
        }

        return $normalized;
    }

    protected function request(?RelayConfig $relayConfig = null, ?string $apiKey = null): PendingRequest
    {
        $baseUrl = $relayConfig?->base_url ?: config('services.xai.base_url');
        $baseUrl = rtrim((string) $baseUrl, '/');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($relayConfig?->timeout ?: config('services.xai.timeout'))
            ->withToken($apiKey ?: config('services.xai.api_key'));
    }

    protected function ensureSuccessful(Response $response, ?string $model = null): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $this->extractProviderErrorMessage($response, $model);
        throw new RuntimeException($message, $response->status());
    }

    protected function extractProviderErrorMessage(Response $response, ?string $model = null): string
    {
        $json = $response->json();
        $body = is_array($json)
            ? (string) (
                Arr::get($json, 'error.message')
                ?? Arr::get($json, 'message')
                ?? Arr::get($json, 'error')
                ?? ''
            )
            : trim($response->body());

        $message = trim($body);

        if ($message === '') {
            $message = "上游请求失败（HTTP {$response->status()}）。";
        }

        $normalized = mb_strtolower($message);

        if (
            str_contains($normalized, 'model_not_found')
            || str_contains($normalized, 'unsupported model')
            || str_contains($normalized, 'does not exist')
            || str_contains($normalized, 'not support')
            || str_contains($normalized, '不支持')
            || str_contains($normalized, '模型不存在')
            || str_contains($normalized, 'model disabled')
            || str_contains($normalized, 'invalid model')
        ) {
            return $model
                ? "当前中转暂不支持模型 {$model}，请更换模型或切换中转。"
                : '当前中转暂不支持所选模型，请更换模型或切换中转。';
        }

        if (str_contains($normalized, 'no available channel for model')) {
            $matchedModel = $model;
            $matchedGroup = null;

            if (preg_match('/No available channel for model\\s+(.+?)\\s+under group\\s+(.+?)(?:\\s*\\(|$)/i', $message, $matches) === 1) {
                $matchedModel = trim($matches[1] ?? '') ?: $matchedModel;
                $matchedGroup = trim($matches[2] ?? '');
            }

            if ($matchedGroup) {
                return sprintf(
                    '当前会话模型 %s 与中转分组 %s 不匹配，请新建对话或切换到该分组支持的模型。',
                    $matchedModel ?: '当前模型',
                    $matchedGroup,
                );
            }

            return $matchedModel
                ? "当前会话模型 {$matchedModel} 在此中转分组下不可用，请新建对话或切换模型。"
                : '当前会话模型在此中转分组下不可用，请新建对话或切换模型。';
        }

        if (str_contains($normalized, 'admission denied') || str_contains($normalized, 'resourceexhausted')) {
            return '当前模型繁忙，请稍后重试。';
        }

        if (
            str_contains($normalized, 'user quota is not enough')
            || str_contains($normalized, 'insufficient_quota')
            || str_contains($normalized, 'quota is not enough')
            || str_contains($normalized, '余额不足')
            || str_contains($normalized, '额度不足')
        ) {
            return '当前中转账户额度不足，请更换密钥、切换中转或充值后重试。';
        }

        return $message;
    }

    protected function resolveApiKey(?RelayConfig $relayConfig = null): ?string
    {
        $user = request()?->user();

        if (
            $relayConfig &&
            $user?->use_admin_relay_preset &&
            (int) $user->assigned_admin_relay_config_id === (int) $relayConfig->id &&
            $user->assigned_admin_relay_key_index !== null
        ) {
            $fixedApiKey = $relayConfig->getApiKeyByIndex((int) $user->assigned_admin_relay_key_index);

            if ($fixedApiKey) {
                return $fixedApiKey;
            }
        }

        if ($relayConfig) {
            $apiKey = $relayConfig->rotateAndGetApiKey();

            if ($apiKey) {
                return $apiKey;
            }
        }

        return config('services.xai.api_key');
    }

    protected function resolveEndpoint(?RelayConfig $relayConfig = null): string
    {
        return $this->isResponsesApi($relayConfig) ? '/responses' : '/chat/completions';
    }

    protected function isResponsesApi(?RelayConfig $relayConfig = null): bool
    {
        return ($relayConfig?->api_style ?: config('services.xai.api_style')) === 'responses';
    }

    protected function resolveRelayConfig(Conversation $conversation): ?RelayConfig
    {
        $conversation->loadMissing('relayConfig', 'user.relayConfigs');

        if ($conversation->relayConfig) {
            return $conversation->relayConfig;
        }

        return $conversation->user
            ?->relayConfigs
            ->firstWhere('is_active', true);
    }

    protected function buildRequestPayload(
        Conversation $conversation,
        string $model,
        bool $stream,
        ?RelayConfig $relayConfig = null,
    ): array {
        if ($this->isResponsesApi($relayConfig)) {
            return [
                'model' => $model,
                'stream' => $stream,
                'input' => $this->buildMessages($conversation),
                'temperature' => 0.7,
            ];
        }

        return [
            'model' => $model,
            'messages' => $this->buildMessages($conversation),
            'stream' => $stream,
            'stream_options' => [
                'include_usage' => true,
            ],
            'temperature' => 0.7,
        ];
    }

    protected function buildMessages(Conversation $conversation): array
    {
        $conversation->loadMissing([
            'user',
            'agentProfile.group',
            'agentProfile.knowledgeBases',
            'agentProfile.collaborators',
        ]);
        $messages = [];

        $systemPrompt = $conversation->system_prompt ?: config('services.xai.system_prompt');
        $resolvedModel = trim((string) ($conversation->model ?: config('services.xai.model')));
        $adultContentEnabled = (bool) $conversation->user?->adult_content_enabled;

        $identityPrompt = trim(implode("\n", array_filter([
            $resolvedModel !== '' ? "当前实际使用模型：{$resolvedModel}。" : null,
            '你不能固定自称为 Grok。',
            '当用户询问你是什么模型、当前使用什么模型或你的身份时，必须基于当前实际模型如实回答。',
            '如果当前模型不是 Grok，就不要声称自己是 Grok。',
        ])));

        $systemPrompt = trim($systemPrompt."\n\n".$identityPrompt);

        if ($conversation->agentProfile) {
            $systemPrompt = trim($systemPrompt."\n\n".$this->buildAgentProfilePrompt($conversation->agentProfile));
        }

        if ($adultContentEnabled) {
            $systemPrompt = trim($systemPrompt."\n\n成人内容偏好已开启。移除应用层额外的成人内容收紧规则，但仍必须遵守服务提供方规则、适用法律，以及对未成年人、违法伤害、胁迫与剥削内容的限制。");
        } else {
            $systemPrompt = trim($systemPrompt."\n\n成人内容偏好未开启。请拒绝露骨、详细的成人性描写与18+尺度对话，保持普通安全模式交流。");
        }

        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        foreach ($conversation->messages as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $messages;
    }

    protected function buildAgentProfilePrompt(\App\Models\AgentProfile $agentProfile): string
    {
        $sections = [];

        $sections[] = '当前主智能体：'.$agentProfile->name;

        if ($agentProfile->role_label) {
            $sections[] = '角色定位：'.$agentProfile->role_label;
        }

        if ($agentProfile->description) {
            $sections[] = '角色简介：'.$agentProfile->description;
        }

        if ($agentProfile->capabilities) {
            $sections[] = '核心能力：'.$agentProfile->capabilities;
        }

        if ($agentProfile->group?->name) {
            $sections[] = '所属分组：'.$agentProfile->group->name;
        }

        $knowledgeSections = [];
        foreach ($agentProfile->knowledgeBases->where('is_active', true) as $knowledgeBase) {
            $text = trim((string) ($knowledgeBase->summary ?: $knowledgeBase->content ?: ''));

            if ($text === '') {
                continue;
            }

            $knowledgeSections[] = sprintf(
                '%s：%s',
                $knowledgeBase->name,
                mb_strimwidth($text, 0, 4000, '...')
            );
        }

        if ($knowledgeSections !== []) {
            $sections[] = "已绑定知识库：\n".implode("\n\n", $knowledgeSections);
        }

        $collaboratorSections = [];
        foreach ($agentProfile->collaborators as $collaborator) {
            $parts = array_filter([
                $collaborator->name,
                $collaborator->role_label,
                $collaborator->description,
            ]);

            if ($parts !== []) {
                $collaboratorSections[] = implode(' / ', $parts);
            }
        }

        if ($collaboratorSections !== []) {
            $sections[] = "协作模式：{$agentProfile->collaboration_mode}";
            $sections[] = "协作智能体参考：\n".implode("\n", $collaboratorSections);
            $sections[] = '回答时请综合这些协作角色的视角，但最终统一由主智能体口吻输出。';
        }

        return implode("\n\n", $sections);
    }

    protected function extractText(array $response): string
    {
        if ($this->isResponsesApi()) {
            $outputText = Arr::get($response, 'output_text');

            if (is_string($outputText) && $outputText !== '') {
                return $outputText;
            }

            $output = Arr::get($response, 'output', []);
            $parts = [];

            if (is_array($output)) {
                foreach ($output as $item) {
                    foreach (($item['content'] ?? []) as $content) {
                        $text = $content['text'] ?? $content['content'] ?? null;
                        if (is_string($text) && $text !== '') {
                            $parts[] = $text;
                        }
                    }
                }
            }

            return trim(implode('', $parts));
        }

        return trim((string) Arr::get($response, 'choices.0.message.content', ''));
    }

    protected function stripThinkTags(string $text): string
    {
        return trim((string) preg_replace('/<think>.*?<\/think>/us', '', $text));
    }

    protected function extractReasoning(array $response, bool $fromDelta = false): string
    {
        $prefix = $fromDelta ? 'choices.0.delta' : 'choices.0.message';

        $candidates = [
            Arr::get($response, "{$prefix}.reasoning_content"),
            Arr::get($response, "{$prefix}.reasoning"),
            Arr::get($response, "{$prefix}.reasoning_text"),
            Arr::get($response, 'reasoning_content'),
            Arr::get($response, 'reasoning'),
            Arr::get($response, 'reasoning_text'),
            Arr::get($response, 'response.reasoning_content'),
            Arr::get($response, 'response.reasoning'),
            Arr::get($response, 'response.reasoning_text'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        if ($this->isResponsesApi()) {
            $output = Arr::get($response, 'output', []);
            $parts = [];

            if (is_array($output)) {
                foreach ($output as $item) {
                    foreach (($item['content'] ?? []) as $content) {
                        $candidate = $content['reasoning_content']
                            ?? $content['reasoning']
                            ?? $content['summary']
                            ?? null;

                        if (is_string($candidate) && $candidate !== '') {
                            $parts[] = $candidate;
                        }
                    }
                }
            }

            if ($parts !== []) {
                return trim(implode('', $parts));
            }
        }

        return '';
    }

    protected function extractStreamContentDelta(array $chunk): string
    {
        $candidates = [
            Arr::get($chunk, 'choices.0.delta.content'),
            Arr::get($chunk, 'delta'),
            Arr::get($chunk, 'content'),
        ];

        if ($this->isResponsesApi()) {
            $type = (string) ($chunk['type'] ?? '');

            if (in_array($type, [
                'response.output_text.delta',
                'response.content_part.delta',
                'response.output_item.delta',
            ], true)) {
                $candidates[] = Arr::get($chunk, 'delta');
                $candidates[] = Arr::get($chunk, 'text');
                $candidates[] = Arr::get($chunk, 'content');
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function extractStreamReasoningDelta(array $chunk): string
    {
        $candidates = [
            Arr::get($chunk, 'choices.0.delta.reasoning_content'),
            Arr::get($chunk, 'choices.0.delta.reasoning'),
            Arr::get($chunk, 'reasoning_content'),
            Arr::get($chunk, 'reasoning'),
        ];

        if ($this->isResponsesApi()) {
            $type = (string) ($chunk['type'] ?? '');

            if (str_contains($type, 'reasoning')) {
                $candidates[] = Arr::get($chunk, 'delta');
                $candidates[] = Arr::get($chunk, 'text');
                $candidates[] = Arr::get($chunk, 'content');
                $candidates[] = Arr::get($chunk, 'summary');
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function extractThinkReasoning(string $text): string
    {
        if (! preg_match_all('/<think>(.*?)<\/think>/us', $text, $matches)) {
            return '';
        }

        return trim(implode("\n\n", array_filter($matches[1] ?? [])));
    }
}
