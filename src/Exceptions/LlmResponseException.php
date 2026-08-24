<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Exceptions;

/**
 * The endpoint answered 2xx, but the body was not a usable chat completion.
 */
final class LlmResponseException extends LlmException
{
    /**
     * @param array<string, mixed> $data
     */
    public static function missingContent(array $data): self
    {
        $encoded = json_encode($data);
        $encoded = is_string($encoded) ? $encoded : '(unencodable body)';

        return new self(
            'The LLM endpoint returned no message content: '
            . (strlen($encoded) > 500 ? substr($encoded, 0, 500) . '…' : $encoded)
        );
    }

    public static function notJson(string $content): self
    {
        return new self(
            'Expected JSON content from the LLM endpoint but could not decode it: '
            . (strlen($content) > 500 ? substr($content, 0, 500) . '…' : $content)
        );
    }
}
