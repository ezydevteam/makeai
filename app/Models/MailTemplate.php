<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailTemplate extends Model
{
    protected $fillable = [
        'slug', 'name', 'subject', 'content', 'is_active',
        'is_system', 'requires_pro', 'category', 'last_edited_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'requires_pro' => 'boolean',
    ];

    /**
     * The admin who last saved this template (for the "Edited by" label).
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_edited_by');
    }

    /**
     * The {token} => escaped-value map every template and the surrounding layout
     * are substituted with. Caller-supplied variables win over the defaults.
     *
     * Values are HTML-escaped; the templates themselves are trusted
     * (admin-authored) HTML.
     */
    public static function replacements(array $variables = []): array
    {
        $vars = array_merge([
            'site_name' => settings('app_name', config('app.name')),
            'site_url' => settings('app_url', config('app.url')),
            'site_logo_url' => settings('site_logo_light', ''),
            // ?: (not the settings() default arg) so a seeded-but-empty
            // site_support_email still falls back to the from-address.
            'support_email' => settings('site_support_email') ?: settings('mail_from_address', ''),
            'current_year' => now()->year,
            'year' => now()->year,
            'unsubscribe_url' => $variables['unsubscribe_url'] ?? '#',
        ], $variables);

        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{'.$key.'}'] = e((string) $value);
        }

        return $replacements;
    }

    /**
     * Wrap already-rendered body HTML in the configured mail layout.
     *
     * The layout carries its own {site_name}/{year} tokens, so it needs the same
     * substitution the body got — replacing only {content} shipped those tokens
     * to recipients verbatim in every email the platform sent.
     */
    public static function wrapInLayout(string $content, array $variables = []): string
    {
        $layout = settings('mail_layout', '{content}');

        // {content} is added last so it wins over any caller variable of the same
        // name, and is inserted raw — it is rendered HTML, not a value to escape.
        return strtr($layout, array_merge(
            self::replacements($variables),
            ['{content}' => $content],
        ));
    }

    /**
     * Render the template with provided variables.
     */
    public function render(array $variables = []): array
    {
        // Substitute simultaneously with strtr so a value that happens to contain
        // "{another_key}" is never re-processed as a placeholder on a later pass.
        $replacements = self::replacements($variables);

        return [
            'subject' => strtr($this->subject, $replacements),
            'content' => strtr($this->content, $replacements),
        ];
    }
}
