<?php

namespace Addons\FakerAi\Services;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a field spec into rows of realistic fake content via the site's configured AI, billed
 * to the internal system user (User::internalAi()) so it never touches a customer's credits.
 *
 * One AI call per chunk — a run of 60 testimonials is 3 calls of 20, not 60 calls. Output is
 * forced to a JSON array; a single stricter retry covers the occasional model that wraps it in
 * prose or a code fence.
 */
class FakeContentGenerator
{
    public function __construct(private AiService $ai) {}

    /**
     * @param  string  $noun  what each object represents, e.g. "customer testimonial"
     * @param  array<string,string>  $fields  key => human description/constraints for that key
     * @param  int  $count  how many objects to produce
     * @param  array  $style  ['language' => …, 'tone' => …, 'prompt' => …, 'context' => …]
     * @param  FakerBatch  $batch  token spend is accumulated onto this batch
     * @return array<int,array<string,mixed>> validated list, each an assoc array of the fields
     */
    public function generate(string $noun, array $fields, int $count, array $style, FakerBatch $batch): array
    {
        $chunkSize = max(1, (int) addon_setting('faker-ai', 'ai_chunk_size', 20));
        $items = [];

        for ($remaining = $count; $remaining > 0; $remaining -= $chunkSize) {
            $n = min($chunkSize, $remaining);
            $items = array_merge($items, $this->draftChunk($noun, $fields, $n, $style, $batch));
        }

        return array_slice($items, 0, $count);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function draftChunk(string $noun, array $fields, int $n, array $style, FakerBatch $batch): array
    {
        $system = $this->systemPrompt($noun, $fields, $style);
        $user = $this->userPrompt($noun, $n, $style);

        $raw = $this->ask($system, $user, $n, $batch);
        $parsed = $this->parse($raw);

        // One stricter retry if the model didn't return usable JSON.
        if ($parsed === null) {
            $raw = $this->ask(
                $system."\n\nIMPORTANT: your previous reply could not be parsed. Reply with NOTHING but a raw JSON array. No prose, no code fences.",
                $user,
                $n,
                $batch,
            );
            $parsed = $this->parse($raw);
        }

        if (! is_array($parsed)) {
            return [];
        }

        // Keep only well-shaped objects that carry at least one requested field.
        return array_values(array_filter($parsed, function ($row) use ($fields) {
            if (! is_array($row)) {
                return false;
            }

            return (bool) array_intersect(array_keys($fields), array_keys($row));
        }));
    }

    private function ask(string $system, string $user, int $n, FakerBatch $batch): string
    {
        try {
            [$provider, $model] = $this->resolveProviderModel();

            $response = $this->ai->complete(
                user: User::internalAi(),
                prompt: $user,
                systemPrompt: $system,
                provider: $provider,
                model: $model,
                options: [
                    'temperature' => 0.9,
                    // Rough headroom: ~120 tokens/object plus a fixed preamble, capped so a
                    // huge chunk can't request an absurd completion window.
                    'max_tokens' => min(4000, ($n * 120) + 400),
                ],
                toolSlug: 'faker-ai',
            );

            $batch->tokens_input += $response->inputTokens;
            $batch->tokens_output += $response->outputTokens;
            $batch->save();

            return (string) $response->content;
        } catch (Throwable $e) {
            // Surface the reason on the batch; the job marks it failed. A blank string here
            // yields zero parsed items, which the caller treats as "nothing generated".
            $batch->error = Str::limit($e->getMessage(), 480);
            $batch->save();

            return '';
        }
    }

    /**
     * Pick a provider/model FakerAI can actually reach.
     *
     * The site default (default_ai_provider/model) is used AS-IS when a key for that provider is
     * configured — passing null lets AiService apply it plus its own fallback. But when the
     * default provider has NO key (a common install state: default left on "openai" while only,
     * say, openrouter is set up), every AI-authored generation would 500 with "…is not
     * configured". Rather than let the whole batch fail on a setting FakerAI doesn't own, fall
     * back to the first provider that HAS an available key and one of its active chat models.
     *
     * Cached for the batch's lifetime — the set of keys won't change mid-run.
     *
     * @return array{0:?string,1:?string} [provider, model]; nulls mean "use the site default".
     */
    private function resolveProviderModel(): array
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $available = AiKey::available()->pluck('provider')->unique();

        $default = (string) settings('default_ai_provider', 'openai');
        if ($default !== '' && $available->contains($default)) {
            return $resolved = [null, null]; // site default is reachable — let AiService drive it
        }

        $provider = $available->first();
        if (! $provider) {
            return $resolved = [null, null]; // nothing configured; let the normal error surface
        }

        $model = AiModel::where('provider', $provider)
            ->where('type', 'chat')
            ->where('is_active', true)
            ->orderBy('name')
            ->value('slug');

        return $resolved = [$provider, $model];
    }

    private function systemPrompt(string $noun, array $fields, array $style): string
    {
        $language = $style['language'] ?? 'English';
        $tone = $style['tone'] ?? 'authentic, varied, conversational';

        $lines = [];
        foreach ($fields as $key => $desc) {
            $lines[] = "  - \"{$key}\": {$desc}";
        }
        $schema = implode("\n", $lines);

        $context = ! empty($style['context'])
            ? "Context about the site: {$style['context']}\n"
            : '';

        return <<<PROMPT
        You generate realistic, believable SAMPLE data to populate a demo website. The data is
        fictional but must read as if written by real, different people — vary length, wording,
        names, and sentiment. Avoid repetition and obvious placeholder text.

        Produce a JSON array of {$noun} objects. Each object MUST have exactly these keys:
        {$schema}

        {$context}Language: write all human-readable text in {$language}.
        Style: {$tone}.

        Output rules: reply with ONLY a raw, valid JSON array. No markdown, no code fences, no
        commentary before or after. Use straight double quotes for all keys and string values.
        PROMPT;
    }

    private function userPrompt(string $noun, int $n, array $style): string
    {
        $extra = ! empty($style['prompt']) ? "\nAdditional guidance: {$style['prompt']}" : '';

        return "Generate {$n} distinct {$noun} objects now as a JSON array.{$extra}";
    }

    /**
     * Extract the JSON array from a model reply. Returns null if nothing parseable is found.
     *
     * @return array<int,mixed>|null
     */
    private function parse(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Strip a ```json … ``` fence if the model added one.
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $raw);
        }

        // Isolate the outermost array, tolerating stray prose around it.
        $start = strpos($raw, '[');
        $end = strrpos($raw, ']');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $json = substr($raw, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
