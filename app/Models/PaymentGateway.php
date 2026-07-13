<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_enabled',
        'is_test_mode',
        'processing_fee_type',
        'processing_fee_value',
        'credentials',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_test_mode' => 'boolean',
            'processing_fee_value' => 'decimal:2',
            'credentials' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        $credentials = $this->credentials ?: [];
        $value = $credentials[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString((string) $value);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function encryptCredentials(array $credentials): array
    {
        return collect($credentials)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => \Illuminate\Support\Facades\Crypt::encryptString((string) $value))
            ->all();
    }

    public function publicCredentials(array $fields): array
    {
        $stored = $this->credentials ?: [];

        return collect($fields)
            ->mapWithKeys(fn (array $field) => [
                $field['key'] => [
                    'configured' => array_key_exists($field['key'], $stored) && filled($stored[$field['key']]),
                    'value' => '',
                ],
            ])
            ->all();
    }
}
