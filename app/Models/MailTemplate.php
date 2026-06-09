<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $fillable = [
        'slug', 'name', 'subject', 'content', 'is_active',
        'is_system', 'requires_pro', 'category', 'last_edited_by',
    ];

    /**
     * Render the template with provided variables.
     */
    public function render(array $variables = []): array
    {
        $vars = array_merge([
            'site_name' => settings('app_name', config('app.name')),
            'site_url' => settings('app_url', config('app.url')),
            'site_logo_url' => settings('site_logo_url', ''),
            'support_email' => settings('support_email', settings('mail_from_address', '')),
            'current_year' => now()->year,
            'year' => now()->year,
            'unsubscribe_url' => $variables['unsubscribe_url'] ?? '#',
        ], $variables);

        $subject = $this->subject;
        $content = $this->content;

        foreach ($vars as $key => $value) {
            $safe = e((string) $value);
            $subject = str_replace('{'.$key.'}', $safe, $subject);
            $content = str_replace('{'.$key.'}', $safe, $content);
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }
}
