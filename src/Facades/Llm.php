<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Facades;

use Illuminate\Support\Facades\Facade;
use MaxCloudApps\LlmClient\LlmClient;

/**
 * @method static \MaxCloudApps\LlmClient\Data\ChatResponse chat(list<\MaxCloudApps\LlmClient\Data\Message> $messages, ?string $model = null, array<string, mixed> $options = [])
 * @method static \MaxCloudApps\LlmClient\Data\ChatResponse prompt(string $prompt, ?string $system = null, ?string $model = null, array<string, mixed> $options = [])
 * @method static list<\MaxCloudApps\LlmClient\Data\Model> models()
 *
 * @see LlmClient
 */
final class Llm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LlmClient::class;
    }
}
