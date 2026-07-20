<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\Language;
use App\Models\ToolEmbed;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use App\Services\AI\ToolAccessService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmbedController extends Controller
{
    /**
     * How long an unlock grant stays valid, in seconds.
     */
    private const UNLOCK_TTL = 7200;

    private function corsHeaders(Request $request): array
    {
        $origin = $request->headers->get('Origin');

        return [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];
    }

    public function show(Request $request, string $token): Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $tool = AiTool::where('slug', $embed->tool_slug)->where('is_active', true)->firstOrFail();

        if (! $tool->is_embeddable) {
            abort(403, translate('This tool is not embeddable.'));
        }

        $this->assertOriginAllowed($request, $embed);

        $this->recordEmbedUsage($embed);

        $languages = Language::where('is_active', true)->get(['code', 'name'])->toArray();
        $models = AiModel::active()->ofType('chat')->orderBy('provider')->orderBy('name')->get(['slug', 'name', 'provider'])->toArray();

        return response()->view('embed.tool', [
            'embed' => $embed,
            'tool' => $tool,
            'requiresPassword' => filled($embed->password_hash),
            'appName' => settings('app_name', 'MakeAI'),
            'languages' => $languages,
            'models' => $models,
        ])->header('Content-Security-Policy', 'frame-ancestors '.$this->frameAncestors($embed));
    }

    public function unlockOptions(Request $request, string $token): Response
    {
        return response('', 204)->withHeaders($this->corsHeaders($request));
    }

    public function unlock(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();

        $request->validate(['password' => 'required|string']);

        if (blank($embed->password_hash) || ! Hash::check($request->password, $embed->password_hash)) {
            abort(403, translate('Incorrect password.'));
        }

        // A stateless grant, not a session flag: the embed runs in a third-party
        // iframe where the SameSite=Lax session cookie is never sent back, so a
        // session-based unlock could not be enforced on run().
        return response()->json([
            'unlocked' => true,
            'unlock_token' => $this->issueUnlockGrant($embed),
        ])->withHeaders($this->corsHeaders($request));
    }

    public function runOptions(Request $request, string $token): Response
    {
        return response('', 204)->withHeaders($this->corsHeaders($request));
    }

    public function run(Request $request, string $token): StreamedResponse
    {
        $embed = ToolEmbed::where('token', $token)->where('is_active', true)->firstOrFail();
        $tool = AiTool::where('slug', $embed->tool_slug)->where('is_active', true)->firstOrFail();

        if (! $tool->is_embeddable) {
            abort(403, translate('This tool is not embeddable.'));
        }

        $this->assertOriginAllowed($request, $embed);
        $this->assertUnlocked($request, $embed);

        $validated = $request->validate([
            'fields' => 'required|array|max:50',
            'unlock_token' => 'nullable|string|max:2000',
        ]);

        $fields = $validated['fields'];

        // Visitors may only pick models the admin has activated — anything
        // else would be billed to the embed owner at an unknown rate.
        if (isset($fields['model'])) {
            $modelValid = is_string($fields['model'])
                && AiModel::where('slug', $fields['model'])->active()->exists();

            if (! $modelValid) {
                unset($fields['model']);
            }
        }

        // Cap total input size before it inflates token costs
        $maxChars = max(1000, (int) settings('ai_max_input_chars', 30000));
        if (strlen((string) json_encode($fields)) > $maxChars) {
            abort(422, translate('Your input is too long. Please shorten it and try again.'));
        }

        $owner = $embed->user;

        // A public embed runs as (and is billed to) its owner. Gate on the owner's
        // access so a pro/plan-required tool can't be run for free by anonymous
        // visitors through an embed published by a user who lacks access to it.
        app(ToolAccessService::class)->assertCanUse($tool, $owner);

        $promptBuilder = app(PromptBuilder::class);
        $completion = $promptBuilder->build($tool, $fields, $owner);

        $provider = ProviderRegistry::resolve($completion->provider ?? 'openai');
        TokenGuard::before($owner, $tool, $completion->model);

        $this->recordEmbedUsage($embed);

        return response()->stream(function () use ($provider, $completion, $owner, $tool) {
            $usage = null;
            $aborted = false;

            try {
                $stream = $provider->streamChatCompletion(
                    $completion->toMessages(),
                    $completion->model,
                    $completion->toOptions(),
                );

                foreach ($stream as $chunk) {
                    // Only the StreamEnd chunk carries token counts. The driver also
                    // yields arrays for reasoning events, and taking any array as the
                    // usage payload left $usage = ['reasoning_end' => true] when a
                    // visitor left mid-reasoning — the charge then died on a missing
                    // input_tokens and the run was billed to nobody.
                    if (is_array($chunk) && isset($chunk['input_tokens'])) {
                        $usage = $chunk;

                        continue;
                    }

                    if (! is_string($chunk)) {
                        continue;
                    }

                    if (! $aborted && $this->clientDisconnected()) {
                        $aborted = true;
                    }

                    // Keep draining the generator after the visitor disconnects instead
                    // of breaking: the provider generates (and charges us for) the whole
                    // completion regardless, and its usage chunk comes last. Bailing out
                    // here meant an aborted embed run was billed to nobody — a visitor
                    // could hammer an embed and leave the owner's tokens unmetered.
                    if ($aborted) {
                        continue;
                    }

                    echo 'data: '.json_encode(['token' => $chunk])."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if ($usage) {
                    TokenGuard::after(
                        $owner,
                        $usage['input_tokens'],
                        $usage['output_tokens'],
                        $usage['model'] ?? $completion->model,
                        $completion->provider ?? 'openai',
                        'embed',
                        ['template_slug' => $tool->slug, 'aborted' => $aborted],
                        true,
                        ! $aborted,
                    );
                }
            } catch (\Throwable $e) {
                report($e);

                TokenGuard::recordFailure($owner, $completion->provider ?? 'openai', $completion->model, 'embed', 0, 0, [
                    'template_slug' => $tool->slug,
                    'error' => $e->getMessage(),
                ]);

                echo 'data: '.json_encode(['error' => translate('Generation failed. Please try again.')])."\n\n";
            }

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            ...$this->corsHeaders($request),
        ]);
    }

    /**
     * Has the visitor gone away mid-stream?
     *
     * A method rather than an inline connection_aborted() so the abort path can be
     * driven in a test — the native call always reports "connected" under PHPUnit,
     * which is precisely the branch that used to leak an unbilled generation.
     */
    protected function clientDisconnected(): bool
    {
        return connection_aborted() === 1;
    }

    /**
     * A password-protected embed may only generate with a valid unlock grant.
     */
    private function assertUnlocked(Request $request, ToolEmbed $embed): void
    {
        if (blank($embed->password_hash)) {
            return;
        }

        if (! $this->unlockGrantIsValid((string) $request->input('unlock_token'), $embed)) {
            abort(403, translate('This tool is password protected. Please unlock it first.'));
        }
    }

    private function issueUnlockGrant(ToolEmbed $embed): string
    {
        return Crypt::encryptString(json_encode([
            'token' => $embed->token,
            'exp' => now()->addSeconds(self::UNLOCK_TTL)->getTimestamp(),
        ]));
    }

    private function unlockGrantIsValid(string $grant, ToolEmbed $embed): bool
    {
        if ($grant === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($grant), true);
        } catch (DecryptException) {
            return false;
        }

        if (! is_array($payload)) {
            return false;
        }

        // Bind the grant to this embed so a grant for one embed can't unlock another.
        return hash_equals((string) ($payload['token'] ?? ''), (string) $embed->token)
            && (int) ($payload['exp'] ?? 0) > now()->getTimestamp();
    }

    /**
     * Enforce the embed's domain allowlist for cross-origin API calls.
     *
     * Note the Origin header is absent on the iframe's own navigation and equals
     * OUR host on the embed page's same-origin fetches — so this cannot be the
     * only defence. The real domain restriction for framing is the
     * frame-ancestors CSP emitted by show(); this closes the direct-API path.
     */
    private function assertOriginAllowed(Request $request, ToolEmbed $embed): void
    {
        $origin = $request->headers->get('Origin');

        if (! $origin) {
            return;
        }

        // The embed page itself is served from our domain, so its fetches are
        // same-origin and must always be allowed.
        if (parse_url($origin, PHP_URL_HOST) === $request->getHost()) {
            return;
        }

        $allowed = array_filter(array_map('trim', (array) ($embed->allowed_origins ?? [])));

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $allowedOrigin) {
            if ($origin === $allowedOrigin || str_ends_with($origin, '.'.$allowedOrigin)) {
                return;
            }
        }

        abort(403, translate('Origin not allowed.'));
    }

    /**
     * The frame-ancestors source list that restricts which sites may iframe this
     * embed. Browsers enforce this on the framing itself, which the Origin header
     * cannot do (it is not sent on iframe navigations).
     */
    private function frameAncestors(ToolEmbed $embed): string
    {
        $allowed = array_filter(array_map('trim', (array) ($embed->allowed_origins ?? [])));

        if ($allowed === []) {
            return '*';
        }

        return collect($allowed)
            ->map(fn (string $origin) => str_contains($origin, '://') ? $origin : "https://{$origin} https://*.{$origin}")
            ->implode(' ');
    }

    /**
     * Count the run against the embed. Persisted on the row the dashboard already
     * reads, rather than a raw Redis counter nothing consumed — and Redis is not
     * guaranteed to exist on an install (the cache driver here is `database`).
     */
    private function recordEmbedUsage(ToolEmbed $embed): void
    {
        ToolEmbed::where('id', $embed->id)->update([
            'usage_count' => DB::raw('usage_count + 1'),
            'last_used_at' => now(),
        ]);
    }
}
