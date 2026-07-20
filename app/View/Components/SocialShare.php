<?php

namespace App\View\Components;

use App\Services\SocialService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SocialShare extends Component
{
    public array $payload;

    public function __construct(
        public string $url,
        public string $title = '',
        public ?string $image = null,
    ) {
        $this->payload = app(SocialService::class)->sharePayload($url, $title, $image);
    }

    public function render(): View|Closure|string
    {
        return view('components.social-share');
    }
}
