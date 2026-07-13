<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Services;

/**
 * Outcome of a slash command. A command either answers directly (no model call, no
 * credits spent) or grounds the model call with retrieved context.
 */
final class SlashCommandResult
{
    private function __construct(
        public readonly ?string $reply,
        public readonly ?string $systemAppend,
        public readonly ?string $message,
        public readonly array $sources,
    ) {}

    /**
     * Answer immediately from local data. Costs nothing and never reaches a provider.
     */
    public static function reply(string $text): self
    {
        return new self(reply: $text, systemAppend: null, message: null, sources: []);
    }

    /**
     * Hand off to the model, with retrieved context appended to the system prompt.
     */
    public static function ground(string $systemAppend, string $message, array $sources = []): self
    {
        return new self(reply: null, systemAppend: $systemAppend, message: $message, sources: $sources);
    }

    public function isDirectReply(): bool
    {
        return $this->reply !== null;
    }
}
