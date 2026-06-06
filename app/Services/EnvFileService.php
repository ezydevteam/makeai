<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Reads and writes individual .env key-value pairs
 * while preserving all comments, blank lines, and formatting.
 */
class EnvFileService
{
    private string $path;

    public function __construct()
    {
        $this->path = base_path('.env');
    }

    /**
     * Read a single key from .env.
     * Returns null if the key doesn't exist.
     */
    public function read(string $key): ?string
    {
        if (! file_exists($this->path)) {
            return null;
        }

        $content = file_get_contents($this->path);
        $quotedKey = preg_quote($key, '/');

        if (preg_match('/^' . $quotedKey . '\s*=\s*(.*)$/im', $content, $matches)) {
            $value = trim($matches[1]);

            // Strip surrounding quotes if present
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Unescape escaped quotes inside the value
            $value = str_replace(['\"', "\\'"], ['"', "'"], $value);

            return $value;
        }

        return null;
    }

    /**
     * Write (or update) a single key=value pair to .env.
     * Values containing spaces or special chars are auto-quoted.
     */
    public function write(string $key, string $value): void
    {
        $this->writeMultiple([$key => $value]);
    }

    /**
     * Batch write multiple pairs.
     * Accepts ['KEY' => 'value', ...].
     */
    public function writeMultiple(array $pairs): void
    {
        if (empty($pairs)) {
            return;
        }

        if (! file_exists($this->path)) {
            Log::warning('EnvFileService: .env file not found at ' . $this->path);

            return;
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        $foundKeys = [];
        $newLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip blank lines and comments — keep them as-is
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $newLines[] = $line;

                continue;
            }

            // Check if this line is a key=value pair
            if (preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $trimmed, $matches)) {
                $existingKey = $matches[1];

                if (array_key_exists($existingKey, $pairs)) {
                    $newValue = $this->formatValue($pairs[$existingKey]);
                    $newLines[] = $existingKey . '=' . $newValue;
                    $foundKeys[$existingKey] = true;

                    continue;
                }
            }

            $newLines[] = $line;
        }

        // Append any keys that weren't already in the file
        foreach ($pairs as $key => $value) {
            if (! isset($foundKeys[$key])) {
                $newLines[] = '';
                $newLines[] = $key . '=' . $this->formatValue($value);
            }
        }

        file_put_contents($this->path, implode("\n", $newLines) . "\n");
    }

    /**
     * Quote a value if it contains spaces, hash, or special characters.
     */
    private function formatValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        // Already quoted
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return $value;
        }

        // Need quoting if value contains spaces, #, =, or quotes
        if (preg_match('/[\s#="\']/', $value)) {
            $escaped = str_replace('"', '\"', $value);

            return '"' . $escaped . '"';
        }

        return $value;
    }
}
