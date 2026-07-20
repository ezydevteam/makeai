<?php

namespace Tests\Unit;

use App\Services\AI\PromptBuilder;
use Tests\TestCase;

/**
 * Phase 1 prompt fix: the shared user-prompt template lists nine placeholders,
 * but most tools collect only a handful. The old interpolator turned every
 * uncollected placeholder into the literal "[MISSING: key]" and left "{name}"
 * (never a form field) as "[MISSING: name]" on 100% of tools — all of it sent
 * straight to the model.
 *
 * In production the prompt must now be clean: name resolved, empty optional
 * fields dropped, no debug markers. In debug the marker survives as an author
 * aid for spotting a misconfigured custom tool.
 */
class PromptBuilderInterpolationTest extends TestCase
{
    private const USER_TEMPLATE = "Tool: {name}\n"
        ."Topic or task: {topic}\n"
        ."Target audience: {audience}\n"
        ."Tone: {tone}\n"
        ."Output language: {language}\n"
        ."Keywords or key points: {keywords}\n"
        ."Additional instructions: {additional}";

    /** Interpolate + strip exactly as PromptBuilder::build() does for the user prompt. */
    private function render(array $fields): string
    {
        $builder = new PromptBuilder();

        $interpolate = new \ReflectionMethod($builder, 'interpolate');
        $interpolate->setAccessible(true);

        $strip = new \ReflectionMethod($builder, 'stripEmptyLabelLines');
        $strip->setAccessible(true);

        // build() merges the tool name in before interpolating.
        $fields = array_merge(['name' => 'Code Generator'], $fields);

        return $strip->invoke($builder, $interpolate->invoke($builder, self::USER_TEMPLATE, $fields));
    }

    public function test_production_prompt_is_clean_with_no_missing_markers(): void
    {
        config(['app.debug' => false]);

        $result = $this->render([
            'topic' => 'Reverse a linked list',
            'language' => 'English',
        ]);

        // The name resolves; no tool should ever show "[MISSING: name]".
        $this->assertStringContainsString('Tool: Code Generator', $result);
        $this->assertStringContainsString('Topic or task: Reverse a linked list', $result);
        $this->assertStringContainsString('Output language: English', $result);

        // Nothing the tool did not collect leaks through.
        $this->assertStringNotContainsString('[MISSING:', $result);
        $this->assertStringNotContainsString('Target audience:', $result);
        $this->assertStringNotContainsString('Tone:', $result);
        $this->assertStringNotContainsString('Keywords', $result);
    }

    public function test_provided_fields_survive_and_empty_ones_are_dropped(): void
    {
        config(['app.debug' => false]);

        // audience is present but blank — it should be dropped, not shown empty.
        $result = $this->render([
            'topic' => 'Launch email',
            'tone' => 'Friendly',
            'audience' => '',
            'language' => 'Spanish',
        ]);

        $this->assertStringContainsString('Tone: Friendly', $result);
        $this->assertStringNotContainsString('Target audience:', $result);
        $this->assertStringNotContainsString("\n\n\n", $result); // no blank-line gaps
    }

    public function test_debug_keeps_the_missing_marker_as_an_author_aid(): void
    {
        config(['app.debug' => true]);

        $result = $this->render(['topic' => 'x', 'language' => 'English']);

        // A placeholder the tool does not collect is surfaced only in debug.
        $this->assertStringContainsString('[MISSING: keywords]', $result);
        // The tool name still resolves even in debug.
        $this->assertStringContainsString('Tool: Code Generator', $result);
    }
}
