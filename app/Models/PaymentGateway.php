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

    /**
     * Never let the (encrypted) gateway credentials blob ride along when the model is
     * serialized to JSON/array — e.g. if a raw PaymentGateway is ever handed to an
     * Inertia/API response. Controllers already expose only curated fields
     * (publicCredentials()), but this makes a leak impossible at the model layer.
     * Direct access ($gateway->credentials, getCredential()) is unaffected.
     */
    protected $hidden = [
        'credentials',
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
            // Trimmed on the way out as well as on the way in, so a key already saved
            // with a stray space is repaired without the admin having to re-paste it.
            $decrypted = trim(\Illuminate\Support\Facades\Crypt::decryptString((string) $value));

            return $decrypted === '' ? $default : $decrypted;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Credentials are trimmed before storage. Nothing a gateway issues — key, secret,
     * token, merchant id — carries meaningful leading or trailing whitespace, and a key
     * pasted with a trailing newline goes straight into an Authorization header and is
     * rejected by the gateway with an error that names the header rather than the key
     * (Paddle: "Authentication header included, but incorrectly formatted"). Internal
     * newlines survive, which matters for PEM keys and bank-transfer instructions.
     */
    public static function encryptCredentials(array $credentials): array
    {
        return collect($credentials)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => \Illuminate\Support\Facades\Crypt::encryptString((string) $value))
            ->all();
    }

    /**
     * A recognisable hint at a stored credential, safe to render in the admin.
     *
     * Enough for an admin to tell at a glance WHICH key is saved — the difference between
     * a live and a test key is usually the first few characters — without the panel ever
     * holding the secret itself. Never the whole value, and nothing at all for a short
     * one, where a partial reveal would be most of it.
     *
     * A multi-line field (bank-transfer instructions) is not a secret and masks into
     * nonsense, so it gets a plain marker instead of a character preview.
     */
    private static function maskCredential(string $value, string $type = 'text'): string
    {
        if ($type === 'textarea') {
            return str_repeat('•', 12);
        }

        $length = mb_strlen($value);

        if ($length <= 8) {
            return str_repeat('•', max($length, 4));
        }

        return mb_substr($value, 0, 3).str_repeat('•', 6).mb_substr($value, -3);
    }

    public function publicCredentials(array $fields): array
    {
        $stored = $this->credentials ?: [];

        return collect($fields)
            ->mapWithKeys(function (array $field) use ($stored) {
                $configured = array_key_exists($field['key'], $stored) && filled($stored[$field['key']]);
                // Decrypted only to build the mask; the plaintext never leaves this method.
                $plain = $configured ? (string) $this->getCredential($field['key'], '') : '';

                return [
                    $field['key'] => [
                        'configured' => $configured,
                        // A credential that will not decrypt (usually a changed APP_KEY) is
                        // configured but unusable — say so rather than showing a mask that
                        // implies it works.
                        'masked' => $configured && $plain !== ''
                            ? self::maskCredential($plain, $field['type'] ?? 'text')
                            : '',
                    ],
                ];
            })
            ->all();
    }

}
