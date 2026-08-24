<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Data;

/**
 * Token counts for one completion.
 */
final readonly class Usage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
    ) {}

    /**
     * Build from an OpenAI "usage" object. Endpoints are inconsistent about
     * reporting usage at all, so every field falls back to zero rather than
     * failing a response that is otherwise perfectly usable.
     *
     * @param  array<string, mixed>|null  $usage
     */
    public static function from(?array $usage): self
    {
        return new self(
            promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usage['completion_tokens'] ?? 0),
            totalTokens: (int) ($usage['total_tokens'] ?? 0),
        );
    }
}
