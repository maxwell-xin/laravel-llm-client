<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Data;

/**
 * A model advertised by the endpoint's catalog.
 */
final readonly class Model
{
    public function __construct(
        public string  $id,
        public ?string $ownedBy = null,
        public ?int    $contextLength = null,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            ownedBy: isset($data['owned_by']) ? (string)$data['owned_by'] : null,
            contextLength: isset($data['context_length']) ? (int)$data['context_length'] : null,
        );
    }
}
