<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Exceptions;

/**
 * The endpoint answered HTTP 429.
 *
 * Worth catching separately: this is the one failure that is expected to
 * succeed on a later attempt without anything being changed, so callers
 * typically retry it rather than treating the job as failed.
 */
final class LlmRateLimitException extends LlmRequestException
{
    /**
     * @param  array<string, mixed>  $body
     * @param  string|null  $retryAfterHeader  The raw Retry-After header, when the endpoint sent one.
     */
    public function __construct(
        string $message,
        int $status = 0,
        array $body = [],
        protected readonly ?string $retryAfterHeader = null,
    ) {
        parent::__construct($message, $status, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(int $status, array $body, string $rawBody, ?string $retryAfterHeader = null): self
    {
        return new self(
            'The LLM endpoint is rate limited: '.self::describe($body, $rawBody),
            status: $status,
            body: $body,
            retryAfterHeader: $retryAfterHeader,
        );
    }

    /**
     * Seconds the endpoint asked the caller to wait, when it said so.
     *
     * The standard Retry-After header comes first, because that is where OpenAI
     * and most gateways put it. RFC 9110 allows it to be either a number of
     * seconds or an HTTP date, so both are handled. Some endpoints report the
     * same number in the JSON body instead, which is the remaining fallback.
     */
    public function retryAfter(): ?int
    {
        if (is_numeric($this->retryAfterHeader)) {
            return max(0, (int) $this->retryAfterHeader);
        }

        if ($this->retryAfterHeader !== null && $this->retryAfterHeader !== '') {
            $timestamp = strtotime($this->retryAfterHeader);

            if ($timestamp !== false) {
                return max(0, $timestamp - time());
            }
        }

        $retryAfter = $this->body['error']['retry_after'] ?? $this->body['retry_after'] ?? null;

        return is_numeric($retryAfter) ? max(0, (int) $retryAfter) : null;
    }
}
