<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Exceptions;

use Throwable;

/**
 * The endpoint could not be reached at all — wrong host, container down,
 * connect timeout. Distinct from LlmRequestException, which means the endpoint
 * answered and refused.
 */
final class LlmConnectionException extends LlmException
{
    public static function to(string $baseUrl, Throwable $previous): self
    {
        return new self(
            "Could not reach the LLM endpoint at [{$baseUrl}]: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
