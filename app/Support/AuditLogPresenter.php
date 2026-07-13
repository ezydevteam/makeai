<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns a raw admin_audit_logs row into buyer-friendly display data:
 * a human label, a category (icon + colour), the affected target, and a
 * redacted payload. This is the SINGLE source of truth for how an action
 * reads — the frontend just renders what this returns, so there is no
 * hardcoded action-parsing logic in the Vue layer.
 *
 * Resolution order for the label:
 *   1. Curated override in config('audit.actions') keyed by route name.
 *   2. Auto-label from the route name (verb + resource), which stays correct
 *      as new routes are added.
 *   3. Legacy rows (no route name) fall back to humanising the old
 *      "METHOD path" action string.
 */
class AuditLogPresenter
{
    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function present(object|array $row): array
    {
        $row = (array) $row;

        $routeName = $row['route_name'] ?? null;
        $method = $row['method'] ?? Str::before((string) ($row['action'] ?? ''), ' ');
        $categoryKey = self::categoryKeyFor($routeName, $row['action'] ?? null);
        $category = self::category($categoryKey);

        return [
            'label' => self::label($row, $routeName, $method),
            'category' => $categoryKey,
            'category_label' => __($category['label']),
            'icon' => $category['icon'],
            'color' => $category['color'],
            'method' => $method,
            'target' => self::target($row),
            'payload' => self::displayPayload($row['payload'] ?? null),
        ];
    }

    private static function label(array $row, ?string $routeName, string $method): string
    {
        if ($routeName) {
            // Index the flat map directly — route names contain dots, so
            // config("audit.actions.{$routeName}") would mis-parse them as nested keys.
            $curated = config('audit.actions')[$routeName] ?? null;

            if (is_array($curated) && isset($curated['label'])) {
                return __($curated['label']);
            }

            return self::humanizeRouteName($routeName);
        }

        return self::humanizeLegacyAction((string) ($row['action'] ?? ''), $method);
    }

    /**
     * admin.mail.templates.update → "Updated Mail Templates".
     */
    private static function humanizeRouteName(string $routeName): string
    {
        $segments = array_values(array_filter(explode('.', $routeName), fn ($s) => $s !== '' && $s !== 'admin'));

        if ($segments === []) {
            return __('Performed an action');
        }

        $verbKey = end($segments);
        $verbs = config('audit.verbs', []);
        $verb = $verbs[$verbKey] ?? null;

        // Drop the trailing verb segment from the resource name when we matched one.
        $resourceSegments = $verb !== null ? array_slice($segments, 0, -1) : $segments;

        if ($resourceSegments === []) {
            $resourceSegments = [$verbKey];
            $verb = null;
        }

        $resource = collect($resourceSegments)
            ->map(fn ($s) => Str::headline(str_replace('-', ' ', $s)))
            ->implode(' ');

        return $verb !== null
            ? trim(__($verb).' '.$resource)
            : $resource.': '.Str::headline(str_replace('-', ' ', (string) $verbKey));
    }

    /**
     * Legacy "POST admin/settings/general" → "Updated · settings › general".
     */
    private static function humanizeLegacyAction(string $action, string $method): string
    {
        $path = trim(Str::after($action, ' '));

        if ($path === '') {
            return $action !== '' ? $action : __('Performed an action');
        }

        $verb = match (strtoupper($method)) {
            'POST', 'PUT', 'PATCH' => __('Updated'),
            'DELETE' => __('Deleted'),
            default => strtoupper($method),
        };

        $cleaned = Str::of($path)
            ->replaceMatches('#^admin/#', '')
            ->replaceMatches('#/\d+#', '/#')
            ->replaceMatches('#/[0-9a-zA-Z-]{20,}#', '/#')
            ->replace('-', ' ')
            ->replace('/', ' › ')
            ->toString();

        return trim($verb.' · '.$cleaned);
    }

    private static function categoryKeyFor(?string $routeName, ?string $action): string
    {
        $segment = null;

        if ($routeName) {
            $parts = array_values(array_filter(explode('.', $routeName), fn ($s) => $s !== '' && $s !== 'admin'));
            $segment = $parts[0] ?? null;
        } elseif ($action) {
            $path = trim(Str::after($action, ' '));
            $parts = array_values(array_filter(explode('/', $path), fn ($s) => $s !== '' && $s !== 'admin'));
            $segment = $parts[0] ?? null;
        }

        if ($segment === null) {
            return 'general';
        }

        return config("audit.segment_category.{$segment}", 'general');
    }

    /**
     * @return array{label: string, icon: string, color: string}
     */
    private static function category(string $key): array
    {
        return config("audit.categories.{$key}", config('audit.categories.general', [
            'label' => 'General', 'icon' => 'ti ti-point', 'color' => 'gray',
        ]));
    }

    private static function target(array $row): ?string
    {
        $type = $row['target_type'] ?? null;
        $id = $row['target_id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        return Str::headline((string) $type).' #'.$id;
    }

    /**
     * Decode + redact the stored payload for display. Redaction runs again here
     * (not just at write time) so LEGACY rows captured before write-time
     * redaction never surface secrets in the UI.
     *
     * @return array<string, mixed>|null
     */
    private static function displayPayload(mixed $payload): ?array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
        } elseif (is_array($payload)) {
            $decoded = $payload;
        } else {
            $decoded = null;
        }

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        return AuditLogRedactor::sanitize($decoded);
    }
}
