<?php

namespace Addons\FakerAi\Services;

use Addons\FakerAi\Generators\Contracts\FakeGenerator;

/**
 * The catalogue of fake-data types. Populated in AddonServiceProvider::register().
 */
class GeneratorRegistry
{
    /** @var array<string,FakeGenerator> */
    private array $generators = [];

    public function register(FakeGenerator $generator): void
    {
        $this->generators[$generator->type()] = $generator;
    }

    public function get(string $type): ?FakeGenerator
    {
        return $this->generators[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->generators[$type]);
    }

    /** @return array<string,FakeGenerator> */
    public function all(): array
    {
        return $this->generators;
    }

    /**
     * Only the types that can actually run on this install (KB gated on its addon, etc.).
     *
     * @return array<string,FakeGenerator>
     */
    public function available(): array
    {
        return array_filter($this->generators, fn (FakeGenerator $g) => $g->isAvailable());
    }

    /**
     * Shape the available generators for the admin UI — the dropdown, target pickers and the
     * AI/non-AI hint.
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->available() as $generator) {
            $out[] = [
                'type' => $generator->type(),
                'label' => $generator->label(),
                'group' => $generator->group(),
                'requires_target' => $generator->requiresTarget(),
                'targets' => $generator->requiresTarget() ? $generator->targets() : [],
                'uses_ai' => $generator->usesAi(),
                'option_fields' => $generator->optionFields(),
            ];
        }

        return $out;
    }
}
