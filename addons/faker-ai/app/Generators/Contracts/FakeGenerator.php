<?php

namespace Addons\FakerAi\Generators\Contracts;

use Addons\FakerAi\Models\FakerBatch;

/**
 * One data type FakerAI can seed. Keep the surface small so a ninth type is one class + one
 * line in the service provider.
 *
 * Contract: generate() reads everything it needs off the batch (requested_count, target,
 * options), performs the inserts/increments, records each mutation on the batch via
 * FakerBatch::recordInsert()/recordIncrement(), and returns how many items actually landed.
 * rollback() reverses precisely what generate() recorded.
 */
interface FakeGenerator
{
    /** Stable machine key, e.g. 'testimonials'. Stored on the batch. */
    public function type(): string;

    /** Human label for the dropdown, e.g. 'Testimonials'. */
    public function label(): string;

    /** UI grouping, e.g. 'Blog', 'Tools', 'Knowledge Base'. */
    public function group(): string;

    /**
     * Can this type be generated on THIS install right now? KB ratings, for example, need the
     * ai-knowledge-base addon active. Unavailable types are hidden from the UI and refused
     * server-side.
     */
    public function isAvailable(): bool;

    /** Does the UI need a target picker (a tool, a post, an article)? */
    public function requiresTarget(): bool;

    /**
     * Options for the target picker: [['value' => …, 'label' => …], …]. The first entry is
     * usually a "spread across all" pseudo-target where that makes sense.
     */
    public function targets(): array;

    /** Does producing this type call the AI, or is it pure counters (views/shares/ratings)? */
    public function usesAi(): bool;

    /**
     * Extra type-specific option fields the UI should render (beyond count/language/tone/prompt).
     * Each: ['key' => …, 'label' => …, 'type' => 'select', 'options' => [['value','label'], …],
     * 'default' => …]. Their chosen values arrive in $batch->options keyed by 'key'.
     *
     * @return array<int,array<string,mixed>>
     */
    public function optionFields(): array;

    /** Do the work described by the batch; return the number of items inserted/applied. */
    public function generate(FakerBatch $batch): int;

    /** Undo exactly what this batch created. */
    public function rollback(FakerBatch $batch): void;
}
