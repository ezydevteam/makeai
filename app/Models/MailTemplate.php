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

    /**
     * The admin who last saved this template (for the "Edited by" label).
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_edited_by');
    }

    /**
     * Render the template with provided variables.
     */
    public function render(array $variables = []): array
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

        // Build all replacements first, then substitute simultaneously with strtr
        // so a value that happens to contain "{another_key}" is never re-processed
        // as a placeholder on a later pass. Values are HTML-escaped; the template
        // itself is trusted (admin-authored) HTML.
        $replacements = [];
        foreach ($vars as $key => $value) {
            $replacements['{'.$key.'}'] = e((string) $value);
        }

        return [
            'subject' => strtr($this->subject, $replacements),
            'content' => strtr($this->content, $replacements),
        ];
    }
}
