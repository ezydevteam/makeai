<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Document extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'tool_slug',
        'word_count',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }

    public static function calculateWordCount(?string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        // Replace closing block tags with a space to prevent words from running together
        $spacedContent = preg_replace('/<\/(p|h1|h2|h3|h4|h5|h6|div|li|tr|th|td|blockquote|pre)>/i', ' ', $content);

        // Strip HTML tags
        $text = strip_tags($spacedContent);

        // Decode HTML entities (e.g. &nbsp; or &amp;)
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Trim whitespace
        $text = trim($text);

        if (empty($text)) {
            return 0;
        }

        // Split by any Unicode whitespace
        $words = preg_split('/\s+/u', $text);

        return $words ? count($words) : 0;
    }
}

