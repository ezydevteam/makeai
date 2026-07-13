<?php

declare(strict_types=1);

namespace App\Services\Integrations\Contracts;

/**
 * A non-LLM tool engine (plagiarism, AI-detector, grammar, translation, …).
 *
 * Implementations also expose a static `fromSettings(): self` factory (kept off
 * the interface to avoid PHP static-return covariance friction). The
 * UtilityToolRunner decides integration-vs-LLM fallback; an engine's job is just
 * to run and either return a normalized envelope or throw on failure.
 */
interface ToolIntegration
{
    /** Whether credentials/config are present for this engine to run. */
    public function isConfigured(): bool;

    /**
     * Execute against user input. Returns a normalized envelope:
     *   ['ok' => bool, 'type' => string, 'provider' => string, ...tool-specific, 'raw' => mixed]
     * Throw on transport/provider failure — the runner handles fallback.
     */
    public function run(array $input): array;
}
