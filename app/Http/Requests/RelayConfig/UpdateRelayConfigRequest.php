<?php

namespace App\Http\Requests\RelayConfig;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRelayConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'base_url' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = $this->normalizeBaseUrl((string) $value);

                    if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
                        $fail('请输入有效的中转地址。');
                    }
                },
            ],
            'api_key' => [
                'nullable',
                'string',
                'max:10000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }

                    if ($this->parseApiKeys((string) $value) === []) {
                        $fail('请至少填写一个 API Key。');
                    }
                },
            ],
            'api_style' => ['required', Rule::in(['chat_completions', 'responses'])],
            'model' => ['required', 'string', 'max:100'],
            'available_models' => ['nullable', 'array', 'max:500'],
            'available_models.*' => ['string', 'max:100'],
            'timeout' => ['nullable', 'integer', 'min:10', 'max:600'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function normalizeBaseUrl(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            return 'https://'.$trimmed;
        }

        return $trimmed;
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
}
