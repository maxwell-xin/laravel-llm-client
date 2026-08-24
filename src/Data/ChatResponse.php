<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Data;

use MaxCloudApps\LlmClient\Exceptions\LlmResponseException;

/**
 * One completed chat response.
 */
final readonly class ChatResponse
{
    /**
     * @param  array<string, mixed>  $raw  The decoded response body, for anything this object does not model.
     */
    public function __construct(
        public string $content,
        public string $model,
        public Usage $usage,
        public ?string $finishReason,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws LlmResponseException when the body is not a usable chat completion.
     */
    public static function from(array $data): self
    {
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content)) {
            throw LlmResponseException::missingContent($data);
        }

        return new self(
            content: $content,
            model: (string) ($data['model'] ?? ''),
            usage: Usage::from($data['usage'] ?? null),
            finishReason: isset($data['choices'][0]['finish_reason'])
                ? (string) $data['choices'][0]['finish_reason']
                : null,
            raw: $data,
        );
    }

    /**
     * The content with a surrounding markdown code fence removed.
     *
     * Models wrap JSON in ```json fences even when told not to, which is enough
     * to break an otherwise valid json_decode() on the caller's side.
     */
    public function contentWithoutFences(): string
    {
        $text = trim($this->content);

        if (str_starts_with($text, '```')) {
            $text = (string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text);
        }

        return trim($text);
    }

    /**
     * Decode the content as JSON.
     *
     * @return array<mixed>
     *
     * @throws LlmResponseException when the content is not a JSON object or array.
     */
    public function json(): array
    {
        $decoded = json_decode($this->contentWithoutFences(), true);

        if (! is_array($decoded)) {
            throw LlmResponseException::notJson($this->content);
        }

        return $decoded;
    }
}
