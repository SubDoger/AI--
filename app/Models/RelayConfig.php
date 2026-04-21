<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name',
    'base_url',
    'api_key',
    'api_keys',
    'api_key_cursor',
    'api_style',
    'model',
    'available_models',
    'timeout',
    'is_active',
])]
class RelayConfig extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_keys' => 'encrypted:array',
            'api_key_cursor' => 'integer',
            'available_models' => 'array',
            'is_active' => 'boolean',
            'timeout' => 'integer',
        ];
    }

    protected $hidden = [
        'api_key',
        'api_keys',
    ];

    protected $appends = [
        'api_key_masked',
        'api_keys_plain',
        'api_keys_count',
        'api_keys_masked',
        'has_api_key',
        'available_models_list',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getHasApiKeyAttribute(): bool
    {
        return count($this->getApiKeys()) > 0;
    }

    public function getApiKeyMaskedAttribute(): string
    {
        $value = (string) ($this->getApiKeys()[0] ?? '');
        $length = mb_strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 4)
            .str_repeat('*', max(4, $length - 8))
            .mb_substr($value, -4);
    }

    public function getApiKeysCountAttribute(): int
    {
        return count($this->getApiKeys());
    }

    public function getApiKeysMaskedAttribute(): array
    {
        return array_map(
            fn (string $value): string => $this->maskApiKey($value),
            $this->getApiKeys(),
        );
    }

    public function getApiKeysPlainAttribute(): array
    {
        return $this->getApiKeys();
    }

    public function getAvailableModelsListAttribute(): array
    {
        return $this->getAvailableModels();
    }

    public function getApiKeys(): array
    {
        $values = $this->api_keys;

        if (! is_array($values) || $values === []) {
            $values = filled($this->api_key) ? [$this->api_key] : [];
        }

        return array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                $values,
            ),
            static fn (string $value): bool => $value !== '',
        ));
    }

    public function syncApiKeys(array $keys): void
    {
        $keys = array_values(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $keys),
            static fn (string $value): bool => $value !== '',
        ));

        $this->api_keys = $keys;
        $this->api_key = $keys[0] ?? '';
        $this->api_key_cursor = 0;
    }

    public function getAvailableModels(): array
    {
        $values = $this->available_models;

        if (! is_array($values)) {
            $values = [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                $values,
            ),
            static fn (string $value): bool => $value !== '',
        )));
    }

    public function syncAvailableModels(array $models): void
    {
        $this->available_models = array_values(array_unique(array_filter(
            array_map(static fn ($value): string => trim((string) $value), $models),
            static fn (string $value): bool => $value !== '',
        )));
    }

    public function supportsModel(?string $model): bool
    {
        $normalizedModel = trim((string) $model);

        if ($normalizedModel === '') {
            return true;
        }

        $availableModels = $this->getAvailableModels();

        if ($availableModels === []) {
            return true;
        }

        return in_array($normalizedModel, $availableModels, true);
    }

    public function rotateAndGetApiKey(): ?string
    {
        $selectedKey = null;

        DB::transaction(function () use (&$selectedKey): void {
            /** @var self|null $config */
            $config = self::query()->lockForUpdate()->find($this->getKey());

            if (! $config) {
                return;
            }

            $keys = $config->getApiKeys();

            if ($keys === []) {
                return;
            }

            $cursor = max(0, (int) $config->api_key_cursor);
            $index = $cursor % count($keys);
            $selectedKey = $keys[$index];

            $config->forceFill([
                'api_key' => $selectedKey,
                'api_keys' => $keys,
                'api_key_cursor' => ($index + 1) % count($keys),
            ])->save();
        });

        return $selectedKey;
    }

    public function getApiKeyByIndex(?int $index): ?string
    {
        $keys = $this->getApiKeys();

        if ($keys === []) {
            return null;
        }

        if ($index === null) {
            return $keys[0] ?? null;
        }

        return $keys[$index] ?? null;
    }

    protected function maskApiKey(string $value): string
    {
        $length = mb_strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return mb_substr($value, 0, 4)
            .str_repeat('*', max(4, $length - 8))
            .mb_substr($value, -4);
    }
}
