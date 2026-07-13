<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiTool;
use App\Models\User;
use App\Services\AiDetectorService;
use App\Services\GrammarService;
use App\Services\Integrations\Contracts\ToolIntegration;
use App\Services\Integrations\TranslationIntegration;
use App\Services\PlagiarismService;
use Throwable;

/**
 * Runs a "utility" tool (grammar, AI-detector, plagiarism, translation) through
 * its integration when configured + enabled, otherwise through the LLM — per the
 * tool's `generation_mode`:
 *
 *   - integration               : integration only (no fallback; errors if unavailable)
 *   - integration_llm_fallback  : integration, else LLM
 *   - llm                       : LLM only
 *
 * LLM path uses the tool's own model_override when set, else the global default
 * (AiTool::fallbackModel()), and bills tokens via AiService/TokenGuard. Integration
 * path bills the fixed per-call credit cost via TokenGuard::chargeExternalTool().
 */
class UtilityToolRunner
{
    /** integration slug => engine class (implements ToolIntegration, has static fromSettings()). */
    private const ENGINES = [
        'grammar' => GrammarService::class,
        'ai_detector' => AiDetectorService::class,
        'plagiarism' => PlagiarismService::class,
        'translation' => TranslationIntegration::class,
    ];

    public function __construct(private readonly AiService $ai) {}

    /**
     * Integrations that have a runnable engine — the selectable options for a tool
     * on the admin edit page. Keyed off ENGINES so there's a single source of truth.
     *
     * @return array<int, array{slug:string, name:string}>
     */
    public static function availableIntegrations(): array
    {
        $catalog = config('external-tools.integrations', []);

        return collect(array_keys(self::ENGINES))
            ->map(fn (string $slug) => ['slug' => $slug, 'name' => $catalog[$slug]['name'] ?? ucfirst($slug)])
            ->values()->all();
    }

    public function run(AiTool $tool, array $input, User $user): array
    {
        if ($tool->usesIntegration()) {
            $slug = (string) $tool->integration_slug;
            $engine = $this->resolveEngine($slug);
            $enabled = (bool) settings("external_{$slug}_enabled", false);

            if ($engine && $enabled && $engine->isConfigured()) {
                $result = null;
                try {
                    $result = $engine->run($input);
                } catch (Throwable $e) {
                    report($e); // engine/transport failure → fallback decision below
                }

                if (is_array($result) && ($result['ok'] ?? false) === true) {
                    // Billing is best-effort: a billing/broadcast hiccup must NOT discard a
                    // successful integration result or trigger an LLM re-run.
                    $credits = 0.0;
                    try {
                        $credits = TokenGuard::chargeExternalTool($user, $slug, ['tool_slug' => $tool->slug]);
                    } catch (Throwable $e) {
                        report($e);
                    }

                    return array_merge($result, ['source' => 'integration', 'credits_used' => $credits]);
                }

                // Engine errored or returned a soft failure.
                if (! $tool->allowsLlmFallback()) {
                    return [
                        'ok' => false,
                        'source' => 'integration',
                        'type' => $slug,
                        'error' => translate('The :name service is currently unavailable. Please try again later.', ['name' => $tool->name]),
                    ];
                }
                // integration_llm_fallback → fall through to the LLM.
            } elseif (! $tool->allowsLlmFallback()) {
                return [
                    'ok' => false,
                    'source' => 'integration',
                    'type' => $slug,
                    'error' => translate('This tool needs its integration to be configured and enabled.'),
                ];
            }
            // Not enabled/configured, in fallback mode → LLM.
        }

        return $this->runLlm($tool, $input, $user);
    }

    /**
     * Render a runner result as Markdown so it displays in the same OutputPanel
     * every other tool uses (no special frontend needed).
     */
    public static function toMarkdown(array $result): string
    {
        if (($result['ok'] ?? false) !== true) {
            return '';
        }

        if (($result['source'] ?? '') === 'llm') {
            return (string) ($result['content'] ?? '');
        }

        return match ($result['type'] ?? '') {
            'grammar' => self::grammarMarkdown($result),
            'ai_detection' => self::detectionMarkdown($result),
            'plagiarism' => self::plagiarismMarkdown($result),
            'translation' => (string) ($result['translated'] ?? ''),
            default => (string) ($result['content'] ?? ''),
        };
    }

    private static function grammarMarkdown(array $r): string
    {
        $out = "**Corrected**\n\n".($r['corrected'] ?? '')."\n";
        $issues = $r['issues'] ?? [];
        if (count($issues)) {
            $out .= "\n**Issues (".count($issues).")**\n";
            foreach ($issues as $i) {
                $sugg = ! empty($i['replacements']) ? ' — '.implode(', ', $i['replacements']) : '';
                $out .= '- '.($i['message'] ?? '').$sugg."\n";
            }
        } else {
            $out .= "\n_No issues found._\n";
        }

        return $out;
    }

    private static function detectionMarkdown(array $r): string
    {
        $verdict = match ($r['verdict'] ?? '') {
            'ai' => 'Likely AI',
            'human' => 'Likely Human',
            default => 'Mixed / Uncertain',
        };

        return "**{$r['ai_probability']}% likely AI-generated** — {$verdict}\n\n_Human: {$r['human_probability']}%_";
    }

    private static function plagiarismMarkdown(array $r): string
    {
        $out = "**{$r['plagiarism_percent']}% matched content**\n";
        $matches = $r['matches'] ?? [];
        if (count($matches)) {
            $out .= "\n**Sources**\n";
            foreach ($matches as $m) {
                $title = $m['title'] ?: $m['url'];
                $out .= "- [{$title}]({$m['url']}) — {$m['matched_percent']}%\n";
            }
        } else {
            $out .= "\n_No matching sources found._\n";
        }

        return $out;
    }

    private function resolveEngine(string $slug): ?ToolIntegration
    {
        $class = self::ENGINES[$slug] ?? null;

        return $class ? $class::fromSettings() : null;
    }

    private function runLlm(AiTool $tool, array $input, User $user): array
    {
        $model = $tool->fallbackModel();
        $result = $this->ai->runTemplate($user, $tool, $input, null, $model);

        return [
            'ok' => true,
            'source' => 'llm',
            'type' => $tool->integration_slug ?: 'llm',
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? $model,
            'content' => $result['content'] ?? '',
            'raw' => $result,
        ];
    }
}
