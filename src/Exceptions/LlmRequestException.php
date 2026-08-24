<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Exceptions;

/**
 * The endpoint answered with a non-2xx status.
 */
class LlmRequestException extends LlmException
{
    /**
     * @param array<string, mixed> $body The decoded error body, when there was one.
     */
    public function __construct(
        string                $message,
        public readonly int   $status = 0,
        public readonly array $body = [],
    )
    {
        parent::__construct($message, $status);
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromResponse(int $status, array $body, string $rawBody): self
    {
        return new self(
            'The LLM endpoint returned HTTP ' . $status . ': ' . self::describe($body, $rawBody),
            status: $status,
            body: $body,
        );
    }

    /**
     * Prefer the endpoint's own error message; fall back to the raw body,
     * truncated so a stack trace or HTML error page stays readable in logs.
     *
     * @param array<string, mixed> $body
     */
    protected static function describe(array $body, string $rawBody): string
    {
        $message = $body['error']['message'] ?? $body['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return $message;
        }

        $rawBody = trim($rawBody);

        if ($rawBody === '') {
            return '(empty response body)';
        }

        return strlen($rawBody) > 500 ? substr($rawBody, 0, 500) . '…' : $rawBody;
    }
}
